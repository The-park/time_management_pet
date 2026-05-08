<?php

namespace App\Services;

use Phpml\Classification\NaiveBayes;
use Phpml\FeatureExtraction\TfIdfTransformer;
use Phpml\FeatureExtraction\TokenCountVectorizer;
use Phpml\ModelManager;
use Phpml\Tokenization\NGramWordTokenizer;
use Phpml\Tokenization\WordTokenizer;

/**
 * Naive-Bayes-based binary classifier that labels activity descriptions as
 * either "Productive" or "Unproductive". Designed to run entirely on the local
 * server — no external API calls.
 *
 * Pipeline:
 *   raw text → lowercase + WordTokenizer → TokenCountVectorizer (bag-of-words)
 *   → TfIdfTransformer → NaiveBayes::predict → 'productive' | 'unproductive'
 *
 * Three artefacts are persisted to disk so the model is trained ONCE and
 * loaded on subsequent requests:
 *   storage/app/classifier/model.bin       — serialised NaiveBayes
 *   storage/app/classifier/vectorizer.bin  — fitted TokenCountVectorizer
 *                                             (vocabulary must match training)
 *   storage/app/classifier/transformer.bin — fitted TfIdfTransformer
 *                                             (IDF weights must match training)
 *
 * If any artefact is missing or the corpus has grown via the feedback loop,
 * train() rebuilds all three from scratch.
 */
class ActivityClassifierService
{
    public const PRODUCTIVE = 'productive';
    public const UNPRODUCTIVE = 'unproductive';

    private string $modelDir;
    private string $modelPath;
    private string $vectorizerPath;
    private string $transformerPath;
    private string $corpusPath;

    private ?NaiveBayes $classifier = null;
    private ?TokenCountVectorizer $vectorizer = null;
    private ?TfIdfTransformer $transformer = null;

    public function __construct(?string $storageRoot = null)
    {
        $this->modelDir         = ($storageRoot ?: storage_path('app')).DIRECTORY_SEPARATOR.'classifier';
        $this->modelPath        = $this->modelDir.DIRECTORY_SEPARATOR.'model.bin';
        $this->vectorizerPath   = $this->modelDir.DIRECTORY_SEPARATOR.'vectorizer.bin';
        $this->transformerPath  = $this->modelDir.DIRECTORY_SEPARATOR.'transformer.bin';
        $this->corpusPath       = $this->modelDir.DIRECTORY_SEPARATOR.'corpus.json';

        if (! is_dir($this->modelDir)) {
            @mkdir($this->modelDir, 0775, true);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Training data
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Returns the training corpus in php-ml's expected shape.
     * Sources from ActivityClassifierCorpus which ships ~1,000 hand-curated
     * examples spanning every realistic activity category — see that class
     * for the breakdown. Splitting it out keeps this service focused on
     * pipeline orchestration.
     */
    public static function defaultCorpus(): array
    {
        return ActivityClassifierCorpus::all();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Train + persist
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Train (or retrain) on the given corpus, then serialise all three
     * artefacts to disk. If $samples / $labels are omitted, the merged
     * default-corpus + accumulated user-feedback corpus is used.
     */
    public function train(?array $samples = null, ?array $labels = null): void
    {
        if ($samples === null || $labels === null) {
            [$samples, $labels] = $this->loadFullCorpus();
        }

        // Normalise: lowercase + collapse runs of whitespace. Keeps the
        // vocabulary tight and deterministic across runs.
        $samples = array_map(fn ($t) => $this->normalise((string) $t), $samples);

        // php-ml's NaiveBayes is a Gaussian implementation: it computes
        // mean + variance per feature per class. Feeding it TF-IDF floats
        // dilutes high-signal class-specific tokens (e.g. "gym", "tiktok")
        // because the IDF down-weights them when they appear in short logs.
        // Raw bag-of-words counts via TokenCountVectorizer keep those
        // signals strong and improve held-out accuracy on short queries.
        //
        // (Aside: NGramWordTokenizer(1,2) was tried — it explodes the
        // vocabulary 5-7x and OOM'd the serializer at 1k+ examples. Stuck
        // with WordTokenizer; accuracy is recovered via richer corpus
        // anchoring instead — see ActivityClassifierCorpus.)
        $vectorizer = new TokenCountVectorizer(new WordTokenizer());
        $vectorizer->fit($samples);
        $vectorizer->transform($samples);

        // The transformer is still constructed (and persisted) so that the
        // pipeline shape is forward-compatible if we re-introduce TF-IDF
        // later — predict() is identical regardless. But we don't apply it
        // to the training data here.
        $transformer = new TfIdfTransformer($samples);

        $classifier = new NaiveBayes();
        $classifier->train($samples, $labels);

        // ModelManager (and its saveToFile) only accepts Estimator instances,
        // so we use it for the classifier and fall back to plain serialize()
        // for the fitted vectorizer + transformer. All three need to live
        // together so the predict() pipeline can be reproduced exactly.
        (new ModelManager())->saveToFile($classifier, $this->modelPath);
        file_put_contents($this->vectorizerPath,  serialize($vectorizer),  LOCK_EX);
        file_put_contents($this->transformerPath, serialize($transformer), LOCK_EX);

        $this->classifier  = $classifier;
        $this->vectorizer  = $vectorizer;
        $this->transformer = $transformer;
    }

    /**
     * Load the persisted model — or train + save it if any of the three
     * artefact files is missing. After this returns, the service is ready
     * to predict().
     */
    public function loadOrTrain(): void
    {
        if ($this->classifier && $this->vectorizer && $this->transformer) {
            return;
        }

        $allExist = is_file($this->modelPath)
            && is_file($this->vectorizerPath)
            && is_file($this->transformerPath);

        if (! $allExist) {
            $this->train();
            return;
        }

        try {
            $this->classifier  = (new ModelManager())->restoreFromFile($this->modelPath);
            $this->vectorizer  = unserialize((string) file_get_contents($this->vectorizerPath));
            $this->transformer = unserialize((string) file_get_contents($this->transformerPath));
            if (! $this->vectorizer instanceof TokenCountVectorizer
                || ! $this->transformer instanceof TfIdfTransformer) {
                throw new \RuntimeException('Restored artefacts are wrong type.');
            }
        } catch (\Throwable $e) {
            // Corrupted artefacts — fall back to a fresh train.
            $this->train();
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Predict
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Classify a single activity description. Returns 'productive' or
     * 'unproductive'. Triggers loadOrTrain() lazily so callers don't have
     * to remember.
     *
     * Hybrid pipeline:
     *   1. shortInputOverride() — for ≤ 3-token inputs with an
     *      unambiguous productive or unproductive keyword and no
     *      negator words, return the deterministic label. Solves the
     *      Gaussian NB blind spot on single-token text where per-feature
     *      variance washes out the per-class mean.
     *   2. NaiveBayes classifier on the full vectorised input — handles
     *      multi-word logs with nuanced context, mixed sentiment, and
     *      double negatives.
     */
    public function predict(string $text): string
    {
        $override = $this->shortInputOverride($text);
        if ($override !== null) {
            return $override;
        }

        $this->loadOrTrain();

        $sample = [$this->normalise($text)];
        // CRITICAL: re-use the FITTED vectorizer. Calling ->fit() again
        // here would rebuild the vocabulary against this one sample and
        // silently produce useless predictions. TF-IDF transformer is
        // intentionally NOT applied here — see train() comment.
        $this->vectorizer->transform($sample);

        return (string) $this->classifier->predict($sample[0]);
    }

    /**
     * Lexicon-based short-input handler. For an input of 3 tokens or
     * fewer (no negators present), return the deterministic class if
     * exactly one of the two keyword lists matches. Otherwise return
     * null so predict() falls through to the NB classifier.
     *
     * The negator escape ("no gym", "skipped meditation") forces the
     * harder, multi-word-aware ML path to handle nuance.
     */
    private function shortInputOverride(string $text): ?string
    {
        $tokens = preg_split('/[^a-z0-9]+/', mb_strtolower(trim($text))) ?: [];
        $tokens = array_values(array_filter($tokens, fn ($t) => $t !== ''));
        if (count($tokens) === 0 || count($tokens) > 3) {
            return null;
        }

        static $productiveWords = [
            // verbs / activities
            'studied', 'studying', 'study', 'finished', 'completed',
            'shipped', 'deployed', 'coded', 'coding', 'code', 'debugged',
            'reviewed', 'planning', 'planned', 'journaling', 'journaled',
            'meditate', 'meditated', 'meditation', 'learn', 'learning',
            'learnt', 'learned', 'practice', 'practiced', 'practising',
            'practicing', 'reading', 'write', 'writing', 'wrote', 'built',
            'build', 'designed', 'organized', 'organised', 'mentored',
            'taught', 'created', 'researching', 'researched', 'worked',
            'practised',
            // nouns / domains that are essentially productive on their own
            'gym', 'workout', 'exercise', 'yoga', 'pilates', 'run',
            'running', 'jog', 'jogging', 'cycling', 'cycle', 'swimming',
            'swim', 'leetcode', 'focus', 'productive',
        ];
        static $unproductiveWords = [
            'tiktok', 'reels', 'instagram', 'twitter', 'facebook',
            'snapchat', 'discord', 'doomscrolling', 'doomscroll', 'scroll',
            'scrolled', 'scrolling', 'binge', 'binged', 'binging', 'lazy',
            'wasted', 'procrastinated', 'procrastinating', 'hungover',
            'netflix', 'youtube', 'twitch', 'cod', 'pubg', 'unproductive',
        ];
        static $negators = [
            'no', 'not', 'never', 'didnt', 'skipped', 'skip', 'missed',
            'avoided', 'instead', 'failed',
        ];

        // Negator anywhere → defer to ML (handles "no gym", "skipped study").
        foreach ($tokens as $t) {
            if (in_array($t, $negators, true)) {
                return null;
            }
        }

        $hitsP = false;
        $hitsU = false;
        foreach ($tokens as $t) {
            if (in_array($t, $productiveWords, true))   $hitsP = true;
            if (in_array($t, $unproductiveWords, true)) $hitsU = true;
        }

        if ($hitsP && ! $hitsU) return self::PRODUCTIVE;
        if ($hitsU && ! $hitsP) return self::UNPRODUCTIVE;
        // Mixed (both classes match) or no match — defer to ML.
        return null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Feedback loop
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Record a user correction and retrain the model so future predictions
     * benefit from the new example. Persisted in corpus.json so the
     * training set keeps growing across deploys.
     *
     * $expectedLabel must be one of the class constants.
     */
    public function recordFeedback(string $text, string $expectedLabel): void
    {
        if (! in_array($expectedLabel, [self::PRODUCTIVE, self::UNPRODUCTIVE], true)) {
            throw new \InvalidArgumentException("Label must be productive or unproductive.");
        }

        $extra = $this->loadFeedbackCorpus();
        $extra['samples'][] = (string) $text;
        $extra['labels'][]  = $expectedLabel;
        $this->saveFeedbackCorpus($extra);

        // Retrain immediately on the merged corpus so the next predict()
        // already reflects the correction.
        $this->train();
    }

    /**
     * Returns ['default_count', 'feedback_count'] — useful for the admin
     * UI / debugging.
     */
    public function corpusStats(): array
    {
        $default = self::defaultCorpus();
        $extra   = $this->loadFeedbackCorpus();

        return [
            'default_count'  => count($default['samples']),
            'feedback_count' => count($extra['samples']),
            'total'          => count($default['samples']) + count($extra['samples']),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internals
    // ─────────────────────────────────────────────────────────────────────────

    private function normalise(string $text): string
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    private function loadFullCorpus(): array
    {
        $base  = self::defaultCorpus();
        $extra = $this->loadFeedbackCorpus();

        return [
            array_merge($base['samples'], $extra['samples']),
            array_merge($base['labels'],  $extra['labels']),
        ];
    }

    private function loadFeedbackCorpus(): array
    {
        if (! is_file($this->corpusPath)) {
            return ['samples' => [], 'labels' => []];
        }
        try {
            $data = json_decode((string) file_get_contents($this->corpusPath), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($data) || ! isset($data['samples'], $data['labels'])) {
                return ['samples' => [], 'labels' => []];
            }
            return [
                'samples' => array_values((array) $data['samples']),
                'labels'  => array_values((array) $data['labels']),
            ];
        } catch (\Throwable $e) {
            return ['samples' => [], 'labels' => []];
        }
    }

    private function saveFeedbackCorpus(array $data): void
    {
        file_put_contents(
            $this->corpusPath,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
    }
}
