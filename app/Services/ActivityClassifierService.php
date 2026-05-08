<?php

namespace App\Services;

use Phpml\Classification\NaiveBayes;
use Phpml\FeatureExtraction\TfIdfTransformer;
use Phpml\FeatureExtraction\TokenCountVectorizer;
use Phpml\ModelManager;
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
     * Curated training corpus. 32 examples covering:
     *   - obvious productive (coding, studying, reading) and unproductive
     *     (binge-watching, doomscrolling)
     *   - mixed-sentiment cases ("played PubG for 20 mins as a break then
     *     coded for 4 hours" → productive)
     *   - double-negatives ("not wasted, finished the assignment" → productive)
     *   - "the day got wasted" full-day-review pattern → unproductive
     *
     * Returns ['samples' => [...texts...], 'labels' => [...]].
     */
    public static function defaultCorpus(): array
    {
        $productive = [
            'finished the AWS Lambda module and ran the practice test',
            'wrote unit tests for the goal attribution service',
            'studied chapter 8 of the CEH study guide',
            'pair programmed with the team on the OAuth flow',
            'completed homework for differential equations',
            'shipped the dashboard refactor and reviewed PRs',
            'deep work session on the quarterly planning doc',
            'attended the design review and took action items',
            'read three chapters of the system design book',
            'practiced LeetCode for an hour, solved two mediums',
            'gym workout, leg day, full routine',
            'morning run 5k, then journaled for 15 minutes',
            'cleaned the apartment and did the laundry properly',
            'cooked a proper meal instead of ordering takeout',
            'practiced guitar scales for 30 minutes',
            'meditation 20 minutes then planned the week',
            // Mixed-sentiment but ultimately productive:
            'played PubG for 20 mins as a break then coded for 4 hours',
            'scrolled instagram for 5 mins then finished the report',
            'not wasted, actually finished the assignment',
            'started watching netflix but stopped after 10 mins to study',
            'commute home but used it to listen to a tech podcast',
        ];

        $unproductive = [
            'binge watched netflix all afternoon',
            'doomscrolling reels on instagram for hours',
            'spent the evening on youtube shorts',
            'random reddit rabbit hole, lost track of time',
            'tiktok all morning, did nothing else',
            'argued in twitter replies for two hours',
            'gaming session on cod, six hours straight',
            'watched random anime episodes back to back',
            'scrolled facebook feed mindlessly all evening',
            'discord drama for the entire night',
            // Tricky long-form full-day reviews:
            'wake up and eat breakfast and roaming in the end whole day got wasted',
            'planned to study but ended up napping and binging youtube',
            'thought I would code today, just procrastinated and watched twitch',
            'meant to gym, lay in bed scrolling instead, complete waste',
            'long shower, then phone for hours, basically a wasted day',
            // Edge: short outings that were unfocused
            'aimless mall walking, bought nothing, watched random people',
        ];

        $samples = array_merge($productive, $unproductive);
        $labels  = array_merge(
            array_fill(0, count($productive),   self::PRODUCTIVE),
            array_fill(0, count($unproductive), self::UNPRODUCTIVE),
        );

        return ['samples' => $samples, 'labels' => $labels];
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

        $vectorizer = new TokenCountVectorizer(new WordTokenizer());
        $vectorizer->fit($samples);
        $vectorizer->transform($samples);

        $transformer = new TfIdfTransformer($samples);
        $transformer->transform($samples);

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
     */
    public function predict(string $text): string
    {
        $this->loadOrTrain();

        $sample = [$this->normalise($text)];
        // CRITICAL: re-use the FITTED vectorizer + transformer. Calling
        // ->fit() again here would rebuild the vocabulary against this one
        // sample and silently produce useless predictions.
        $this->vectorizer->transform($sample);
        $this->transformer->transform($sample);

        return (string) $this->classifier->predict($sample[0]);
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
