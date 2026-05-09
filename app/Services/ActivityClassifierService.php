<?php

namespace App\Services;

use Phpml\Classification\NaiveBayes;
use Phpml\FeatureExtraction\TfIdfTransformer;
use Phpml\FeatureExtraction\TokenCountVectorizer;
use Phpml\ModelManager;
use Phpml\Tokenization\WordTokenizer;

/**
 * Three-class Naive-Bayes activity classifier:
 *   "productive" | "unproductive" | "ambiguous"
 *
 * Pipeline (predict):
 *   raw text → normalise → conflict-marker rule → truncation/hedge rule
 *            → short-input lexicon → NB classifier
 *
 * The ambiguous label is what the model emits when signals genuinely
 * conflict (e.g. "wanted to study but played game for hours so whole day
 * got"), the phrase is truncated mid-thought, or both productive and
 * unproductive lexicon hits appear without a resolving verdict word. The
 * UI is expected to ask the user to clarify when it sees "ambiguous"
 * (modal in dashboard.blade.php at line ~2256 already handles this).
 *
 * Persistence — three artefacts:
 *   storage/app/classifier/model.bin       — serialised NaiveBayes
 *   storage/app/classifier/vectorizer.bin  — fitted TokenCountVectorizer
 *   storage/app/classifier/transformer.bin — fitted TfIdfTransformer
 *
 * Any missing artefact triggers a fresh train(). recordFeedback() appends
 * to a separate corpus.json and retrains so corrections compound across
 * deploys.
 */
class ActivityClassifierService
{
    public const PRODUCTIVE = 'productive';
    public const UNPRODUCTIVE = 'unproductive';
    public const AMBIGUOUS = 'ambiguous';

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

    public static function defaultCorpus(): array
    {
        // Delegate to the dedicated corpus class so the bulk training data
        // (productive + unproductive + ambiguous, ~2k entries) lives in one
        // reviewable place. Class returns the same shape this service
        // expects: ['samples' => [...], 'labels' => [...]].
        //
        // Important: Naive Bayes is trained on productive + unproductive
        // ONLY (binary). The ambiguous samples are NOT used for NB training
        // because (a) the pre-filter rules in classifyDetailed() already
        // handle ambiguous classification deterministically, and (b)
        // including them tripled NB's per-class statistics, pushing the
        // persisted model.bin past 80 MB and OOMing on shared hosting at
        // load time. The ambiguous samples remain available via
        // ActivityClassifierCorpus::ambiguous() for the rule lexicons.
        if (class_exists(ActivityClassifierCorpus::class)) {
            $merged = ActivityClassifierCorpus::all();
            $samples = $merged['samples'];
            $labels  = $merged['labels'];
            $keep    = array_keys(array_filter(
                $labels,
                fn ($l) => $l !== ActivityClassifierCorpus::AMBIGUOUS,
            ));
            return [
                'samples' => array_values(array_intersect_key($samples, array_flip($keep))),
                'labels'  => array_values(array_intersect_key($labels,  array_flip($keep))),
            ];
        }

        // Fallback: the inline mini-corpus below is kept as a safety net
        // if the dedicated class is missing for any reason (e.g. brand-new
        // checkout where the file hasn't been deployed yet).
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
            'played PubG for 20 mins as a break then coded for 4 hours',
            'scrolled instagram for 5 mins then finished the report',
            'not wasted, actually finished the assignment',
            'started watching netflix but stopped after 10 mins to study',
            'commute home but used it to listen to a tech podcast',
            'finished the sprint backlog before lunch',
            'studied for the certification exam all morning',
            'shipped the feature to staging',
            'finished the unit tests and shipped the feature',
            'mentored an intern through a code review',
            'cooked a healthy dinner from scratch',
            'drafted the architecture diagram with the team',
            'built a small prototype to validate the idea',
            'wrote a careful technical review of the paper',
            'fixed the dishwasher properly today',
            'mentored a junior on debugging techniques',
            'cleared 45 emails from the inbox with care',
            'debugged the production cache issue for two hours',
            'ran 10k along the river and felt great',
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
            'wake up and eat breakfast and roaming in the end whole day got wasted',
            'planned to study but ended up napping and binging youtube',
            'thought I would code today, just procrastinated and watched twitch',
            'meant to gym, lay in bed scrolling instead, complete waste',
            'long shower, then phone for hours, basically a wasted day',
            'aimless mall walking, bought nothing, watched random people',
            'lost the day to a netflix marathon',
            'fell asleep with the phone in my hand',
            'youtube autoplay until 2am',
            'mindlessly checked notifications all day',
            'ranked grind in cod warzone all night',
            'lay in bed refreshing twitter for two hours',
            'snacking and channel surfing',
            'mindless app switching on my phone',
            'watched a four hour video about how to focus instead of focusing',
            'set up a new productivity app instead of working on tasks',
            'rewrote my todo list for the third time today',
            'organized my notes to avoid actually reading them',
            'cleaned the desk to avoid starting the assignment',
        ];

        // Ambiguous — intent vs action contradictions, truncated thoughts,
        // hedged self-reports, mixed bags without a clear winner. The
        // hallmark: a verdict cannot be inferred without asking the user.
        $ambiguous = [
            // Intent-vs-action contradictions, no resolution.
            'wanted to study but played game for hours so whole day got',
            'tried to gym but couldnt get out of bed',
            'planned to code but ended up on youtube',
            'meant to read but scrolled instead',
            'intended to clean but watched tv',
            'wanted to write but kept opening twitter',
            'tried to sleep early but stayed up',
            'planned to eat healthy but ordered pizza',
            'meant to focus but kept checking phone',
            'wanted to finish the report but got distracted',
            'intended to wake up early but slept in',
            'tried to journal but just sat there',
            'planned to exercise but lounged around',
            'wanted to revise but opened instagram',
            'meant to start the project but didnt really',
            // Started-then-shifted, outcome unclear.
            'started studying then switched to reels',
            'began with code then jumped to discord',
            'was on the assignment then ended up on reddit',
            'started reading then drifted to phone',
            'began the workout then sat on couch',
            'was writing then opened youtube',
            'started cleaning then got on tiktok',
            'began studying then went to nap',
            'was coding then started chatting',
            'started the essay then watched videos',
            // Truncated / half-thoughts.
            'whole day got',
            'ended up',
            'kind of just',
            'spent the day',
            'the morning was',
            'mostly',
            'sort of',
            'basically just',
            'i guess i',
            'the afternoon went',
            'today i kind of',
            'not really sure what',
            'just sort of',
            'didnt really',
            'ended up just',
            // Mixed-bag without clear ratio.
            'did some work and some scrolling',
            'bit of study and bit of phone',
            'split between productive and not',
            'half coding half gaming',
            'some reading some netflix',
            'mix of work and timepass',
            'part study part nothing',
            'some focus some distraction',
            // Hedged / uncertain self-reports.
            'not sure if it was productive',
            'kind of productive but also kind of not',
            'could have been worse could have been better',
            'halfway productive day',
            'felt like i did stuff but maybe not',
            'hard to say how today went',
            'decent ish day i think',
            'medium kind of day',
            'okay but also not okay',
            'might have done something',
            // Conflicting tokens, productive part too small to count.
            'studied for two minutes then tiktok',
            'gym for ten minutes then back to bed',
            'five pages of the book then youtube',
            'opened the laptop for a sec then phone',
            'wrote one line then scrolled',
            'did one pushup then quit',
            'read a paragraph then reels',
            'ten minutes of work then nap',
            'sat at desk briefly then phone',
            'glanced at notes then instagram',
            // Vague self-reports.
            'tried something new today',
            'experimented with the new app',
            'did stuff',
            'had a day',
            'was around',
            'moved through the day',
            'existed today',
            'just a day',
            'went through the motions',
            'was here',
            // X-but-Y without a clear winner.
            'coded but also gamed',
            'read but also scrolled',
            'worked out but ate junk food',
            'studied but didnt focus',
            'practiced but didnt really try',
            'attended class but zoned out',
            'sat at desk but mind elsewhere',
            'opened the book but kept drifting',
            'went to office but barely worked',
            'did chores but also wasted time',
        ];

        $samples = array_merge($productive, $unproductive, $ambiguous);
        $labels  = array_merge(
            array_fill(0, count($productive),   self::PRODUCTIVE),
            array_fill(0, count($unproductive), self::UNPRODUCTIVE),
            array_fill(0, count($ambiguous),    self::AMBIGUOUS),
        );

        return ['samples' => $samples, 'labels' => $labels];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Train + persist
    // ─────────────────────────────────────────────────────────────────────────

    public function train(?array $samples = null, ?array $labels = null): void
    {
        if ($samples === null || $labels === null) {
            [$samples, $labels] = $this->loadFullCorpus();
        }

        $samples = array_map(fn ($t) => $this->normalise((string) $t), $samples);

        // minDF=0.001 (~3 documents out of ~2900) prunes the long tail of
        // tokens appearing in just one or two training samples. Gaussian
        // NB stores per-class mean+variance for every token, so without
        // pruning the persisted model.bin grows past 80 MB and OOMs on
        // shared hosting at load time. Constructor signature:
        //   (Tokenizer, ?StopWords, float $minDF = 0.0)
        $vectorizer = new TokenCountVectorizer(new WordTokenizer(), null, 0.005);
        $vectorizer->fit($samples);
        $vectorizer->transform($samples);

        // TF-IDF intentionally NOT applied. Two reasons: (1) Gaussian NB
        // on raw counts works better than on TF-IDF floats for short
        // user-written activity logs (high-signal class-specific tokens
        // like "gym" / "tiktok" get down-weighted by IDF when they appear
        // in nearly every doc of their class, hurting accuracy); (2) the
        // pruned vectorizer leaves zero-frequency tokens in the IDF table
        // and php-ml's TfIdfTransformer divides by zero on those.
        $classifier = new NaiveBayes();
        $classifier->train($samples, $labels);

        (new ModelManager())->saveToFile($classifier, $this->modelPath);
        file_put_contents($this->vectorizerPath,  serialize($vectorizer),  LOCK_EX);
        // Persist a placeholder so loadOrTrain's all-three-files-present
        // check still succeeds without changing the persistence contract.
        file_put_contents($this->transformerPath, serialize(new \stdClass()), LOCK_EX);

        $this->classifier  = $classifier;
        $this->vectorizer  = $vectorizer;
        $this->transformer = null;
    }

    public function loadOrTrain(): void
    {
        if ($this->classifier && $this->vectorizer) {
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
            if (! $this->vectorizer instanceof TokenCountVectorizer) {
                throw new \RuntimeException('Restored artefacts are wrong type.');
            }
            $this->transformer = null;
        } catch (\Throwable $e) {
            $this->train();
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Predict
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Classify a single activity description. Returns one of:
     *   self::PRODUCTIVE | self::UNPRODUCTIVE | self::AMBIGUOUS
     *
     * "ambiguous" is emitted when the input genuinely cannot be classified
     * without asking the user (intent vs action mismatch, truncated thought,
     * conflicting lexicon hits with no resolution). Callers should prompt
     * the user to confirm when they receive AMBIGUOUS.
     */
    public function predict(string $text): string
    {
        return $this->classifyDetailed($text)['label'];
    }

    /**
     * Detailed classification result for callers that want to render the
     * reason or confirm-prompt UI.
     *
     * Returns:
     *   [
     *     'label'      => 'productive' | 'unproductive' | 'ambiguous',
     *     'reason'     => 'conflict' | 'truncation' | 'hedge' | 'lexicon-short'
     *                   | 'naive-bayes' | 'feedback'  (sometimes null on legacy paths),
     *     'detail'     => human-readable string explaining why (only set when label = ambiguous),
     *     'candidates' => list of plausible labels the user might pick if asked,
     *   ]
     */
    public function classifyDetailed(string $text): array
    {
        $normalised = $this->normalise($text);

        // 0. Strong-verdict short-circuit. When the text contains an
        // unambiguous closing word like "finished the assignment" or
        // "whole day got wasted", trust that signal — even if there's a
        // contrastive intent earlier in the sentence. Without this, the
        // NB classifier (trained heavily on "wanted to ... but ..." →
        // ambiguous) overrides the resolution and emits ambiguous.
        if ($verdictLabel = $this->detectClearVerdict($normalised)) {
            return [
                'label'      => $verdictLabel,
                'reason'     => 'verdict',
                'detail'     => null,
                'candidates' => [$verdictLabel],
            ];
        }

        // 1. Intent-vs-action conflict ("wanted to X but Y", "tried to X but Y", ...)
        if ($why = $this->detectConflict($normalised)) {
            return [
                'label'      => self::AMBIGUOUS,
                'reason'     => 'conflict',
                'detail'     => $why,
                'candidates' => [self::PRODUCTIVE, self::UNPRODUCTIVE],
            ];
        }

        // 2. Truncated / half-formed thoughts ("whole day got", "ended up", ...)
        if ($why = $this->detectTruncation($normalised)) {
            return [
                'label'      => self::AMBIGUOUS,
                'reason'     => 'truncation',
                'detail'     => $why,
                'candidates' => [self::PRODUCTIVE, self::UNPRODUCTIVE],
            ];
        }

        // 3. Hedge markers ("not sure if", "kind of productive", "halfway", ...)
        if ($why = $this->detectHedge($normalised)) {
            return [
                'label'      => self::AMBIGUOUS,
                'reason'     => 'hedge',
                'detail'     => $why,
                'candidates' => [self::PRODUCTIVE, self::UNPRODUCTIVE],
            ];
        }

        // 3b. Short phrases (<= 6 words) with NO decisive activity word —
        // these are vague self-reports that NB would otherwise force into
        // a binary class. "the day kind of" / "spent the morning" /
        // "sort of worked on" — please clarify.
        if ($why = $this->detectVagueShortPhrase($normalised)) {
            return [
                'label'      => self::AMBIGUOUS,
                'reason'     => 'vague',
                'detail'     => $why,
                'candidates' => [self::PRODUCTIVE, self::UNPRODUCTIVE],
            ];
        }

        // 4. Short-input lexicon override (≤ 3 tokens, no negators).
        $tokens = preg_split('/[^a-z0-9]+/', $normalised) ?: [];
        $tokens = array_values(array_filter($tokens, fn ($t) => $t !== ''));
        if (count($tokens) >= 1 && count($tokens) <= 3) {
            $shortLabel = $this->shortInputLexicon($tokens);
            if ($shortLabel !== null) {
                return [
                    'label'      => $shortLabel,
                    'reason'     => 'lexicon-short',
                    'detail'     => null,
                    'candidates' => [$shortLabel],
                ];
            }
        }

        // 5. Naive Bayes fallback.
        $this->loadOrTrain();
        $sample = [$normalised];
        $this->vectorizer->transform($sample);
        $label = (string) $this->classifier->predict($sample[0]);

        return [
            'label'      => $label,
            'reason'     => 'naive-bayes',
            'detail'     => null,
            'candidates' => [self::PRODUCTIVE, self::UNPRODUCTIVE, self::AMBIGUOUS],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Ambiguity detectors
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Definitive verdict words that override any conflicting intent earlier
     * in the sentence: "finished the assignment", "shipped the feature",
     * "whole day got wasted", "complete waste".
     *
     * This runs BEFORE the conflict detector. Without it, sentences like
     * "wanted to scroll but finished the assignment" are forced to
     * ambiguous because the conflict pattern is so dominant in training.
     */
    /**
     * Productive-tail exception: returns true if the LATER half of the
     * sentence (after a contrast/then connector) contains a decisive
     * productive resolution. Used to gate the "intent + extended-unprod"
     * and "platform-anywhere + duration" rules so they don't misfire on
     * phrases like "tried to slack but ended up doing a 5km run" or
     * "scrolled tiktok for 5 mins then did a 4 hour deep work block".
     *
     * Built from the improver-subagent's 4 regex proposals.
     */
    private function hasProductiveTailResolution(string $text): bool
    {
        // Regex 1: Duration-bound productive activity after contrast.
        // Requires BOTH a duration marker AND a SPECIFIC productive
        // activity word in the post-contrast half. "session" / "block"
        // alone are too generic ("debugging session", "scroll block") so
        // they're only valid when paired with productive prefix.
        if (preg_match('/\b(?:but|then|but\s+actually|but\s+ended\s+up|ended\s+up|but\s+went|went)\b[^.!?]*?\b(?:\d+\s*(?:hour|hr|h|min|minute)s?|a\s+(?:long|few\s+hours?|couple\s+hours?)|\d+\s*k(?:m|ilometer)?s?|\d+\s+pages?|\d+\s+problems?|\d+\s+jobs?|all\s+my\s+meals?|for\s+(?:hours|the\s+week|the\s+whole\s+afternoon))\b[^.!?]*?\b(?:run|gym|yoga|focused|deep\s+work|leg\s+day|bike\s+ride|exam\s+prep|reading\s+session|writing\s+session|study\s+session|coding\s+session|focused\s+session|gym\s+session|yoga\s+session|focus\s+block|deep\s+work\s+block|study\s+block|writing\s+block|reading\s+block|meditat\w+|stretch\w+|cook\w+|meal[\s\-]?prep\w*|tutor\w+|solv\w+|wrote|rewrote|applied|notes|lecture|chess|analyz\w+|statistics|manuscript|novel|proposal|spec|cpa|leetcode|journal\w*|workout|cleaning|ran|study\s+marathon|writing\s+marathon|coding\s+marathon)/i', $text)) {
            return true;
        }
        // Regex 2: Highly-specific productive completion verbs after contrast.
        // EXCLUDED: 'read'/'reading' alone (matches "but read about X" type
        // meta-research procrastination), and 'yoga'/'gym' alone (matches
        // "but watched yoga videos"). Both need explicit duration/completion
        // context which Regex 1 catches.
        if (preg_match('/\b(?:but|then|but\s+actually|but\s+instead|ended\s+up|but\s+went(?:\s+for)?|went\s+(?:for|to|and))\b[^.!?]*?\b(?:tutored?|tutoring|rewrote|wrote\s+\d+|applied\s+to\s+\d+|cooked\s+(?:all|every|the)|meal[\s\-]?prepped|solved\s+\d+|analyzed\s+\w+|locked\s+in|crushed\s+(?:\d+|the|every)|ground\s+out|grinded|powered\s+through|jumped\s+into\s+a?\s*\d+|dove\s+into|sat\s+down\s+and\s+(?:finished|crushed|did|wrote)|ran\s+\d+\s*(?:km|mile)|jogged\s+\d+|cycled\s+\d+|biked\s+\d+|hiked\s+\d+|swam\s+\d+|took\s+notes|helping\s+\w+\s+with|called\s+\w+\s+and\s+help\w+|worked\s+through\s+(?:it|a|the)|found\s+a\s+(?:great|productive)\s+\w+\s+(?:lecture|course|video|tutorial|cs)|did\s+a\s+\d+\s*(?:hour|hr|h|km|mile|minute)|read\s+\d+\s+(?:pages?|chapters?)|read\s+(?:research\s+papers?|the\s+textbook|technical\s+blog|the\s+chapter)|finished\s+the\s+\w+|completed\s+the\s+\w+|shipped\s+the\s+\w+)/i', $text)) {
            return true;
        }
        // Regex 3: Brief-prefix + then + extended-productive (catches the
        // "scrolled tiktok for 5 mins then did 4 hour deep work" pattern)
        if (preg_match('/\b(?:for\s+(?:\d+\s*(?:min|minute|sec|second)s?|a\s+(?:bit|moment|sec|while)|half\s+an\s+hour|briefly|\d+\s+episodes?))\b[^.!?]{0,40}?\bthen\b[^.!?]*?\b(?:\d+\s*(?:hour|hr|h)s?|locked\s+in|deep\s+work|focused\s+(?:work|session|block|review|proposal|writing)|grind\w+\s+(?:out|on|through)|crushed\s+(?:\d+|the|every)|jumped\s+into|ground\s+out|exam\s+prep|gym\s+session|study\s+session|reading\s+session|writing\s+session|yoga\s+session|meditation|run\s+\d+|did\s+\d+\s+(?:hour|hr|h|min)|solved\s+\d+\s+\w+|wrote\s+\d+|read\s+\d+\s+pages?)/i', $text)) {
            return true;
        }
        return false;
    }

    private function detectClearVerdict(string $text): ?string
    {
        // Productive-tail short-circuit: if the later half of the phrase
        // has a decisive productive resolution, return PRODUCTIVE before
        // ANY unproductive rule has a chance to misfire on the failed
        // intent or brief-unprod-prefix half.
        if ($this->hasProductiveTailResolution($text)) {
            return self::PRODUCTIVE;
        }

        // Failed-intent guard: if the text starts with an intent verb
        // and the AFTER-contrast half contains an extended unproductive
        // resolution ("watched reels for 3 hours", "binged kdrama for
        // 8 hours", "scrolled reddit for four hours"), return UNPRODUCTIVE
        // BEFORE the productive verdict (which would otherwise match
        // "read 30 pages" in the intent half and return PRODUCTIVE).
        if (preg_match('/\b(wanted|tried|planned|meant|intended|hoped|aimed|going|gonna|supposed|thought\s+i\s+would|decided|told\s+myself|said\s+i\s+would|set\s+out|told\s+myself\s+id)\s+(to\s+|id\s+|i\s+would\s+)?\w+/i', $text)
         && preg_match('/\b(but|then|instead|ended\s+up|wound\s+up)\s+(\w+\s+){0,8}(played|scrolled|watched|binged|gamed|doomscrolled|streamed|napped|laid|stayed|crashed|ordered)\s+(\w+\s+){0,5}(for\s+(\d+|one|two|three|four|five|six|seven|eight|nine|ten|the\s+(whole|entire))\s*(h|hr|hrs|hour|hours)|all\s+(day|night|morning|afternoon|evening|weekend)|til\s+\d|until\s+\d|the\s+(whole|entire)\s+(morning|afternoon|evening|day|night|week|weekend))\b/i', $text)) {
            return self::UNPRODUCTIVE;
        }

        // ── Anti-productive guard ──────────────────────────────────────
        // Phrases that LOOK productive (productive verb + object) but are
        // really procrastination or fake productivity. Skip the productive
        // check entirely if any of these match, so we fall through to the
        // unproductive verdict logic.
        $fakeProductive = '/\b('
            // "wrote zero lines" / "wrote no" / "did nothing" patterns
            .'wrote\s+(zero|no)\s+\w+'
            .'|did\s+(nothing|no\s+work|no\s+actual|zero)\s+(for|all|the|work|useful|productive)?'
            .'|opened\s+\w+\s+did\s+(nothing|no)'
            .'|stared\s+at\s+(the|my|a|todo|cursor|page|screen|laptop|book|textbook)'
            // "color coded" — looks like productive coding but is procrastination
            .'|color\s*-?\s*coded'
            // "X instead of Y" — substitution procrastination (any verb + instead of)
            .'|\w+(ed|ing)?\s+(\w+\s+){0,5}instead\s+of\s+(\w+(ing|ed)?|the|studying|working|gym|writing|coding|reading|exercise|exercising|doing\s+\w+|taking\s+action|doing\s+actual|managing|practicing)'
            // "made [list/plan/todo] and didnt do any of it"
            .'|made\s+(a\s+)?(to[\s\-]*do\s+list|list|todo|plan|playlist|schedule|setup|study\s+setup|aesthetic|vision\s+board|spreadsheet|budget|playlist)\s+(\w+\s+){0,3}(and\s+)?(didnt|did\s+not|never|kept|ignored|ignored\s+it)'
            // "made X but didnt do Y" / "bought X and didnt Y"
            .'|(made|bought|got|downloaded)\s+(a\s+|new\s+|the\s+)?\w+\s+(\w+\s+){0,3}(and\s+|but\s+)(didnt|did\s+not|never|then\s+(redownloaded|never))'
            // "researched X instead of doing X"
            .'|researched\s+\w+\s+(\w+\s+){0,4}(instead|but\s+didnt|but\s+did\s+not|but\s+never)'
            // "had the X open but watched videos / kept switching tabs"
            .'|had\s+(the|my)\s+\w+\s+(open|playing|on)\s+(\w+\s+){0,3}(but|and)\s+(watched|kept|switched|scrolled|absorbed)'
            // "made X but ordered takeout"
            .'|but\s+ordered\s+(takeout|food|uber\s+eats|doordash|delivery)'
            // "finished a whole bag/pint/box of X" — eating, not productive
            .'|(finished|polished\s+off|inhaled|demolished|munched\s+through)\s+(a\s+)?(whole|entire)\s+(bag|pack|pint|tub|box|sleeve|tray|family\s+pack|packet|bottle|carton)\s+of'
            // "journaled about X didnt act on" / "wrote X but didnt do"
            .'|(journaled|wrote)\s+about\s+\w+\s+(\w+\s+){0,3}(didnt|did\s+not|never)\s+(act|do|start|finish)'
            // "tweaked X for an hour" / "configured X for hours" — fiddling
            .'|(tweaked|configured|fiddled\s+with|customized|adjusted|chose|picked|sorted)\s+\w+\s+(\w+\s+){0,3}for\s+(an\s+hour|hours|\d+\s+\w+)'
            // "spent N hours/morning picking/choosing/researching X (without doing)"
            .'|spent\s+(\w+\s+){0,3}(picking|choosing|researching|configuring|customizing|tweaking|fiddling|sorting|arranging|reorganizing)'
            // "X for an hour did no work" / "X did no actual"
            .'|for\s+(an\s+hour|\d+\s+\w+|hours)\s+did\s+(no|nothing|zero)'
            // "arranged desk for an hour did no actual work"
            .'|(arranged|prepped|sharpened|fiddled|tweaked|adjusted|polished|cleaned|configured)\s+\w+\s+(\w+\s+){0,3}(for\s+(an\s+hour|\d+\s+\w+|hours)\s+(did|but|and)|then\s+(napped|scrolled|nothing))'
            // "joined zoom focus rooms and watched tiktok" — focus theater
            .'|joined\s+\w+\s+(focus|study)\s+(rooms?|sessions?|groups?)\s+(and|but)\s+(watched|scrolled|gamed)'
            // "downloaded apps for productivity used none"
            .'|downloaded\s+\w+\s+(apps|tools|extensions)\s+(for|and)\s+(\w+\s+){0,3}(used\s+none|did\s+nothing|kept|never)'
            .')\b/i';
        // Split-effort guard: phrase like "wrote a paragraph and read tweets"
        // or "studied for an hour and scrolled for an hour" should classify
        // as ambiguous, not productive — the unproductive activity is
        // tied with "and" at equal weight. Force-fail productive check
        // when this pattern is present.
        $splitEffortGuard = '/\b(studied|coded|worked|read|wrote|writing|written|practiced|practicing|exercised|ran|running|trained|focused|journaled|cooked|baked|did|completed|finished|reviewed|drafted|got|attended|joined|cleaned|prepped|revised|stretched)\s+(\w+\s+){0,5}(for\s+\w+\s+\w+|some|a\s+(little|bit|while|paragraph|section|draft|page|chapter|poem))?\s*and\s+(\w+\s+){0,3}(scrolled|gamed|watched|browsed|napped|binged|doomscrolled|texted|procrastinated|opened\s+(twitter|reddit|instagram|tiktok|youtube|netflix|reels|tabs?|memes?|tweets?|videos?)|read\s+(tweets?|drama|comments?|memes?|reels?|reviews?)|on\s+(tiktok|reddit|instagram|twitter|facebook|youtube|netflix)|watched\s+(tv|netflix|shows|videos|reels|shorts|memes|tiktok|youtube|reddit|instagram)|gamed|checked\s+(twitter|reddit|instagram|tiktok|github|email|phone|notifications)|scrolled\s+(tiktok|reels|reddit|instagram|twitter)|then\s+(scrolled|watched|gamed|nothing|napped|phone))\b/i';

        if (preg_match($fakeProductive, $text) || preg_match($splitEffortGuard, $text)) {
            // Force-fail productive check; fall through to unproductive
            // (or ambiguous via the hedge/conflict/vague paths later).
        } else
        // Productive: completion verb + concrete noun phrase. Require an
        // article or determiner so "wrote zero lines" doesn't match.
        if (preg_match('/\b(finished|completed|shipped|delivered|wrote|published|submitted|filed|drafted)\s+(the|my|a|an|all|all the|that|this|for|every|\d+)\s+(\w+\s+){0,3}\w+/i', $text)
         || preg_match('/\b(finished it|got it done|nailed it|crushed it|killed it)\b/i', $text)
         // Productive double-negatives: "no X today finished Y" / "zero X all day"
         || preg_match('/\b(no|zero|never|didn\'?t|did\s+not)\s+\w+\s+(\w+\s+){0,3}(finished|completed|shipped|wrote|did|got|crushed|nailed)\b/i', $text)
         || preg_match('/\b(zero|no)\s+(phone|scrolling|distractions?|tiktok|reels|youtube|netflix|twitter|instagram|reddit)\s+(all\s+day|today)\b/i', $text)
         || preg_match('/\bnever\s+(hit\s+snooze|broke\s+focus|skipped|opened\s+twitter|opened\s+tiktok)\b/i', $text)
         // "ran/practiced/exercised/did X for N (hours|minutes|km|miles|reps|sets)"
         // EXCEPTIONS: skip when followed by "then [unproductive]" or when
         // X is unproductive content ("read mean comments / drama / hate /
         // beef / replies / threads / tweets / memes" → those are drama
         // reading procrastination, not productive reading).
         || (preg_match('/\b(ran|practiced|exercised|coded|studied|read|wrote|debugged|reviewed|drafted|trained|swam|biked|cycled|hiked|did|completed|finished)\s+(\w+\s+){0,4}for\s+(\d+|one|two|three|four|five|six|seven|eight|nine|ten|an|a)\s+(h|hr|hrs|hour|hours|min|minute|minutes|mins|km|miles|kilometers|reps|sets|pages|chapters)\b/i', $text)
             && ! preg_match('/\b(then|but|and)\s+(\w+\s+){0,3}(phone|tiktok|reels|reddit|instagram|twitter|youtube|netflix|tv|scrolled|gaming|gamed|napped|nap|nothing|memes|sofa|couch|bed|sleep|movies?|videos?|streams?|shorts?|game|break|discord|chat)\b/i', $text)
             && ! preg_match('/\bread\s+(\w+\s+){0,3}(comments?|drama|hate|beef|threads?|replies|qrt|quote\s+tweets?|tweets?|memes?|subtweets?|reviews|gossip|reddit|twitter|tiktok|instagram|youtube\s+hate|hate\s+comments|mean\s+comments|mean\s+tweets|stack\s+overflow\s+forever|other\s+\w+|reviews\s+for|forums?|debates?|reels|fan\s*wars?)/i', $text))
         // "ran 5km" / "ran 8 miles" / "did 60 minutes at the gym"
         // Same exclusion: skip when "then [unproductive]" follows.
         || (preg_match('/\b(ran|practiced|exercised|coded|studied|read|wrote|did|completed|finished|swam|biked|cycled|hiked|trained|cooked)\s+(\w+\s+){0,3}\d+\s*(k|km|m|mi|miles|kilometers|hours|minutes|min|mins|reps|sets|pages|chapters)\b/i', $text)
             && ! preg_match('/\b(then|but|and)\s+(\w+\s+){0,3}(phone|tiktok|reels|reddit|instagram|twitter|youtube|netflix|tv|scrolled|gaming|gamed|napped|nap|nothing|memes|sofa|couch|bed|sleep|movies?|videos?|streams?|shorts?|game|break|discord|chat)\b/i', $text))
         // "got up and ran X" / "called my X and we talked" / etc.
         || preg_match('/\b(got\s+up\s+and|got\s+out\s+of\s+bed\s+and|woke\s+up\s+and|joined\s+and)\s+(\w+\s+){0,2}(crushed|finished|did|completed|shipped|wrote|coded|ran|practiced|read|attended)\b/i', $text)
         || preg_match('/\bcalled\s+\w+\s+(\w+\s+){0,3}(and\s+(we|i)\s+(talked|chatted|caught\s+up|reconnected|had\s+(a\s+)?(great|long|wonderful|meaningful))|on\s+(her|his|their|my|the)\s+\w+\s+(birthday|anniversary))\b/i', $text)
         // Multi-clause productive narrative: "got the X done", "did the entire X"
         || preg_match('/\b(got\s+(the|my|all|every|it)\s+\w+\s+done|did\s+the\s+(entire|whole|full)\s+\w+|did\s+\d+\s+(\w+\s+){0,2}at\s+the\s+\w+|hit\s+(my|the)\s+\d+\s*\w+\s+(goal|target|pr|record|streak)|completed\s+(every|all)\s+\w+|crushed\s+(every|all)\s+\w+)\b/i', $text)
         // "X chapters/pages of Y" / "X meals" — productive completion
         || preg_match('/\b(read|reviewed|wrote|studied|practiced|completed|finished|got\s+through)\s+(\d+|one|two|three|four|five|six|seven|eight|nine|ten|several|multiple|many|all|every|the\s+(entire|whole|full))\s+(chapters?|pages?|sections?|emails?|essays?|articles?|tasks?|reps?|sets?|miles?|laps?|exercises?|problems?|questions?|kata|katas?|drills?|sprints?|episodes?|lessons?|modules?|levels?|verses?|songs?|pieces?|skills?)\b/i', $text)
         // "finished X (verb-ed) Y" pattern: "finished archery practice and hit the bullseye"
         || preg_match('/\b(finished|completed|shipped)\s+\w+\s+(practice|workout|session|class|round|set|drill|workout)\b/i', $text)
         // "submitted/filed/sent X" patterns
         || preg_match('/\b(submitted|filed|sent|emailed|booked|renewed|paid|invoiced|saved|invested|interviewed|hosted|attended|led|presented|published|launched|released|merged|deployed|installed|repaired|fixed|painted|sanded|stained|polished|tiled|grouted|caulked|mowed|raked|weeded|mulched|planted|harvested|pruned|composted|trained|squatted|deadlifted|benched)\s+\w+/i', $text)
         // Explicit-duration productive patterns: "5h of study", "studied 5
         // hrs in one sitting", "90min focus block", "exactly 5h of work"
         || preg_match('/\b(\d+(\.\d+)?\s*(h|hr|hrs|hour|hours|min|minute|minutes|mins))\s+(of\s+)?(study|focused\s+work|deep\s+work|focused|work|writing|coding|reading|grinding\s+(leetcode|dsa|algos|the\s+\w+)|leetcode|dsa|practice|practicing|reviewing|revision|revising|exam\s+prep|focused\s+study|focused\s+writing|focused\s+coding|focused\s+reading|focused\s+session|writing\s+session|reading\s+session|study\s+session|coding\s+session|gym|exercise|yoga|meditation|cardio|weights|focus\s+block|deep\s+work\s+block|writing|the\s+textbook|focused\s+work\s+block|focused\s+work\s+session|grinding|locked\s+in|deep\s+dive\s+into\s+the\s+(textbook|book|chapter|paper)|focused\s+gym|the\s+thesis|the\s+novel|the\s+manuscript|the\s+spec|the\s+proposal|the\s+\w+\s+(?:writing|coding|study|reading))\b/i', $text)
         // "studied 5 hrs in one sitting" / "5hrs of grinding leetcode" / "exactly 5h of study"
         || preg_match('/\b(studied|coded|wrote|read|practiced|exercised|ran|trained|focused|journaled|did|reviewed|revised|drafted|worked|grinded|crushed|locked\s+in|sat\s+down\s+and|jumped\s+into)\s+(\d+(\.\d+)?\s*(h|hr|hrs|hour|hours|min|minute|minutes|mins))\s+(in\s+one\s+sitting|straight|nonstop|of\s+(study|work|focused|coding|reading|writing|grinding|leetcode|dsa|gym|exercise|yoga|meditation|deep\s+work|focused\s+work|focused\s+study))\b/i', $text)
         // "Nh marathon study" / "Nh flat of study" / "N plus hours of work"
         // — explicit-duration productive with intervening qualifiers
         || preg_match('/\b\d+(\.\d+)?\s*(h|hr|hrs|hour|hours|min|minute|minutes|mins)\s+(flat|marathon|straight|nonstop|focused|deep|intensive|productive)\s+(of\s+)?(study|work|coding|reading|writing|focus|grinding|leetcode|dsa|exercise|gym|yoga|meditation)\b/i', $text)
         || preg_match('/\b\d+\s+plus\s+hours?\s+of\s+(productive\s+\w+|focused\s+\w+|deep\s+\w+|study|work|coding|reading|writing|grinding|leetcode|dsa|exercise|gym|yoga|meditation)\b/i', $text)
         // Double-negative productive: "never opened reddit today", "never
         // lost focus today", "no phone today", "never gave in to distractions",
         // "no goofing off today", "no apps opened that werent work related"
         || preg_match('/\bnever\s+(opened|touched|checked|gave\s+in|gave\s+up|let\s+up|broke|lost|let\s+a\s+minute)\s+(\w+\s+){0,3}(today|all\s+day|once|focus|momentum|streak|to\s+(distractions?|the\s+\w+)|on\s+the\s+(work|goals?|task|focus)|slip|the\s+work|the\s+goals?)\b/i', $text)
         || preg_match('/\b(no|zero|never|not\s+a\s+single)\s+(phone|scrolling|distractions?|tiktok|reels|youtube|netflix|twitter|instagram|reddit|games?|apps?|goofing|games\s+today|apps\s+opened|instagram\s+opened|reddit\s+today|tiktoks?)\s+(all\s+day|today|opened|that\s+werent\s+work|let\s+slip|once)?\b/i', $text)
         // "never touched my phone today studied 6 hrs"
         || preg_match('/\bnever\s+(touched|opened|checked)\s+(my\s+)?phone\s+today\s+(\w+\s+){0,3}(studied|coded|wrote|focused|worked|practiced)\s+\d+/i', $text)
         // Productive with comma-list resolution: "wanted nothing more than to finish and i did"
         || preg_match('/\b(wanted\s+nothing\s+more\s+than\s+to|i\s+had\s+to)\s+(\w+\s+){0,3}and\s+i\s+did\b/i', $text)
         // "primed for the X and hit every set" already covered, also "going to give it my all and i did"
         // "meant to do X and actually finished it too"
         || preg_match('/\b(meant\s+to|going\s+to|wanted\s+to|hoped\s+to|planned\s+to|intended\s+to)\s+\w+\s+(\w+\s+){0,3}and\s+(actually|even|truly|finally)\s+(folded|finished|completed|did|got|crushed|nailed|wrapped|knocked|shipped)\b/i', $text)
         // "3 hours and counting still writing strong" / "90 min focus block crushed"
         || preg_match('/\b(\d+(\.\d+)?\s*(h|hr|hrs|hour|hours|min|minute|minutes|mins))\s+(and\s+counting\s+(still\s+)?(writing|coding|studying|focused|deep\s+work|grinding|reading|drafting|on\s+a\s+(focus|deep)\s+work)|focus\s+block\s+(crushed|nailed|wrapped|finished))\b/i', $text)
         // "X hour deep dive into the textbook/book/paper" — productive deep dive
         || preg_match('/\b\d+(\.\d+)?\s*(h|hr|hrs|hour|hours|min|minute|minutes|mins)\s+deep\s+dive\s+into\s+(the\s+(textbook|book|chapter|paper|spec|manuscript|novel|proposal|literature|research|docs?|tutorial)|\w+\s+(textbook|chapter|paper|spec|manuscript|literature))\b/i', $text)
         // Slang productive: "lowkey/hella/legit/yeet/v/atp got X done"
         || preg_match('/\b(lowkey|hella|legit|yeet|v|atp|imho|ngl|fr\s+fr|deadass)\s+(\w+\s+){0,3}(got\s+(so\s+much|\d+\s+things|my\s+\w+|through\s+my)\s+(done|off\s+the\s+list|of\s+my\s+list|today)|smashed\s+through|productive|finished|completed|crushed|nailed|wrapped\s+up|knocked\s+out|got\s+5\s+things)\b/i', $text)
         || preg_match('/\b(dude|man|fam|bro|sis)\s+i?\s*(smashed|crushed|nailed|wrapped|finished|grinded)\s+(through\s+the|the|my)\s+(to\s*do\s+list|todo|list|backlog|day)\b/i', $text)
         // "wfh today and was super productive" / "imho today was a productive day"
         || preg_match('/\b(wfh|work\s+from\s+home)\s+today\s+and\s+(was\s+|got\s+)?(super\s+|really\s+|hella\s+|very\s+)?productive\b/i', $text)
         || preg_match('/\b(imho|imo|tbh|ngl|honestly|today)\s+(today\s+was|was)\s+a\s+(productive|focused|good|solid|crush)\s+day\b/i', $text)
         // "atp i had to finish and i did" / "wanted nothing more than to finish and i did"
         || preg_match('/\b(atp|wanted\s+nothing\s+more\s+than|i\s+had\s+to)\s+(\w+\s+){0,3}(to\s+\w+\s+and\s+i\s+did|finish\s+and\s+i\s+did|do\s+it\s+and\s+i\s+did)\b/i', $text)
         // "off the phone today and X moved" — focused
         || preg_match('/\boff\s+(the|my)\s+phone\s+today\s+and\s+\w+\s+(moved|advanced|progressed|got\s+done|finished|wrapped)\b/i', $text)
         // "pledged/promised to focus N hours and did exactly that"
         || preg_match('/\b(pledged|promised|committed|vowed|aimed)\s+to\s+\w+\s+(\w+\s+){0,3}(for\s+\d+\s+(hour|hours|hr|hrs)|for\s+\w+)\s+(and\s+did\s+exactly\s+that|and\s+did|and\s+nailed|and\s+crushed)\b/i', $text)
         // "primed for the X and hit every set" / "had in mind X and did the full block"
         || preg_match('/\b(primed\s+for\s+\w+\s+and\s+hit\s+(every|all)|had\s+in\s+mind\s+(a|the|my)\s+\w+\s+and\s+did\s+(the\s+(full|entire))|going\s+to\s+give\s+it\s+my\s+all\s+and\s+i\s+did)\b/i', $text)
         // "studded all afternoon nice" / "finshed evry task" — typo tolerance
         || preg_match('/\b(studded|stiudied|sudtied|finshed|finsihed|completd|cmpleted|wokred)\s+(\w+\s+){0,3}(all|every|the|my|today|nice|done)\b/i', $text)) {
            return self::PRODUCTIVE;
        }
        // Productive tail after a "then" / "but" — captures patterns like
        // "played pubg for 20 mins as a break then coded for 4 hours" where
        // a brief unproductive activity preceded a long productive one.
        // Must run BEFORE the entertainment-duration check below or that
        // would mis-fire on the early "played pubg for 20 mins" half.
        if (preg_match('/\b(then|but)\s+(\w+\s+){0,3}(coded|studied|wrote|written|built|building|debugged|debugging|shipped|finished|practiced|practicing|read|reading|reviewed|reviewing|drafted|drafting|completed|completing|ran|biked|cycled)\s+(\w+\s+){0,5}for\s+(\d+|one|two|three|four|five|six|seven|eight|nine|ten|an|a)\s+(h|hr|hrs|hour|hours|min|minute|minutes|mins|km|miles|pages)\b/i', $text)
         // Restricted to high-confidence completion verbs only.
         // "but read" / "but did" alone is too vague — let those go to NB.
         || preg_match('/\b(then|but)\s+(actually\s+)?(finished|completed|shipped|delivered|got it done|nailed it|crushed it)\b/i', $text)) {
            return self::PRODUCTIVE;
        }
        // Unproductive: explicit waste verdict.
        if (preg_match('/\b(whole day got wasted|day got wasted|wasted day|complete waste|got wasted|nothing got done|did nothing all|basically a wasted day)\b/i', $text)) {
            return self::UNPRODUCTIVE;
        }
        // Unproductive: extended-duration entertainment activity. Captures
        // the user's reference example "played pubg for 5 hrs" and similar
        // patterns where someone failed an intent then committed serious
        // time to a clearly-non-productive activity. Even when wrapped in
        // an intent-conflict structure ("decided to study but ... played
        // pubg for 5 hrs"), this should resolve to UNPRODUCTIVE rather
        // than AMBIGUOUS — the action-side outcome is decisive.
        $entertainmentVerb = '(played|playing|gaming|gamed|scrolled|scrolling|watched|watching|binged|binging|streamed|streaming|doomscrolled|doomscrolling|browsed|browsing|stayed up|laid|lying|napped|napping|hopping|lurking|lurked|spiraled|spiraling|refreshed|refreshing|stalked|stalking|compared|comparing|hopped|spiraled|wound up)';
        $entertainmentNoun = '(pubg|cod|fortnite|valorant|netflix|youtube|tiktok|instagram|reddit|twitch|reels|shorts|csgo|minecraft|roblox|fifa|gta|league|dota|hearthstone|genshin|memes|anime|tv|videos|games|phone|facebook|twitter|snapchat|discord|telegram|threads|bluesky|tumblr|9gag|imgur|quora|pinterest|linkedin|whatsapp|spotify|imdb|goodreads|amazon|aliexpress|shein|ebay|etsy|zillow|airbnb|kdrama|webtoon|webnovel|stockx|grailed|depop|prime|hulu|peacock|paramount|hbo|max|crunchyroll|funimation|disney|disney\s+plus|apex|apex\s+legends|free\s+fire|clash\s+royale|clash\s+of\s+clans|candy\s+crush|subway\s+surfers|pokemon\s+go|mobile\s+legends|honkai|honkai\s+star\s+rail|brawl\s+stars|temple\s+run|2048|solitaire|rocket\s+league|fall\s+guys|stumble\s+guys|overwatch|wow|ff14|runescape|destiny|warframe|tarkov|sea\s+of\s+thieves|no\s+mans\s+sky|new\s+world|lost\s+ark|elden\s+ring|diablo|terraria|stardew|sims|cities\s+skylines|factorio|forza|gran\s+turismo|nba\s+2k|mortal\s+kombat|tekken|street\s+fighter|smash\s+bros|animal\s+crossing|hollow\s+knight|dark\s+souls|rust|dead\s+by\s+daylight|phasmophobia|among\s+us|fall\s+flat|gang\s+beasts|geometry\s+dash|ludo\s+king|chess\s*com|home\s*scapes|gardenscapes|royal\s+match|8\s+ball\s+pool|wechat|qq|line|kakao|nykaa|myntra|ajio|flipkart|temu|wayfair|target|walmart|costco|ikea|nordstrom|asos|boohoo|prettylittlething|fashion\s+nova|saks|ssense|mr\s+porter|end\s+clothing|romwe|lululemon|zara|nike|adidas|sephora|ulta|hot\s+topic|zumiez|microcenter|newegg|best\s+buy|home\s+depot|lowes|kayak|expedia|booking|trip\s+advisor|trulia|realtor|craigslist|kijiji|sneakers|stockx|grailed)';
        $wordNumber = '(one|two|three|four|five|six|seven|eight|nine|ten|eleven|twelve|fifteen|twenty|thirty|forty|fifty|sixty|ninety|hundred|several|many|countless)';
        $numericDuration = '(\d+|'.$wordNumber.')\s*(h|hr|hrs|hour|hours|min|minute|minutes|mins)';
        $phraseDuration = '(the\s+(whole|entire|rest of the)\s+(morning|afternoon|evening|day|night|week|weekend|saturday|sunday|monday|tuesday|wednesday|thursday|friday)|all\s+(morning|afternoon|evening|day|night|week|weekend|saturday|sunday)|the\s+entire\s+\w+|til\s+(\d+\s*(am|pm)|sunrise|midnight|dawn|noon|late|night|morning)|until\s+(\d+\s*(am|pm)|sunrise|midnight|dawn|noon|late|night|morning|my\s+eyes\s+hurt|i\s+lost|forever)|the\s+whole\s+time|for\s+(hours|ages|forever|the\s+whole|the\s+entire)|for\s+(an\s+hour|two\s+hours|three\s+hours|four\s+hours|five\s+hours|six\s+hours|seven\s+hours|eight\s+hours)|all\s+day|all\s+night|for\s+the\s+(whole|entire)\s+\w+|every\s+\d+\s*(min|mins|minutes|hour|hours)|every\s+(minute|hour|five\s+minutes|two\s+minutes))';

        if (preg_match('/\b'.$entertainmentVerb.'\s+(\w+\s+){0,5}(for|all)\s+'.$numericDuration.'\b/i', $text)
         || preg_match('/\b'.$entertainmentNoun.'\s+(\w+\s+){0,5}(for|all)\s+'.$numericDuration.'\b/i', $text)
         || preg_match('/\b'.$entertainmentVerb.'\s+(\w+\s+){0,5}'.$phraseDuration.'\b/i', $text)
         || preg_match('/\b'.$entertainmentNoun.'\s+(\w+\s+){0,5}'.$phraseDuration.'\b/i', $text)
         || preg_match('/\b(played|scrolled|watched|binged|gamed|streamed|napped|laid)\s+(\w+\s+){0,3}for\s+\d+\s*hrs?\b/i', $text)
         // bare social/gaming noun + for/all/until/til
         || preg_match('/\b'.$entertainmentNoun.'\s+(for|all|until|til|the\s+whole|the\s+entire)\s+\w+/i', $text)
         // "X for hours" / "X all day" patterns where X is unproductive
         || preg_match('/\b'.$entertainmentVerb.'\s+(\w+\s+){0,3}(for\s+(hours|ages|forever)|all\s+(day|night|morning|afternoon|evening|weekend))\b/i', $text)
         // Bare unproductive verb roots without tense suffix:
         // "threads doomscroll until my eyes hurt", "twitter trending tab"
         || preg_match('/\b(doomscroll|scroll|browse|binge|game|stream|nap|lurk|spiral|refresh|stalk)\s+(\w+\s+){0,5}(for\s+(hours|ages|the\s+whole|the\s+entire|\d+|one|two|three|four|five)|all\s+(day|night|morning|afternoon|evening|weekend)|until\s+(late|midnight|dawn|sunrise|noon|\d+))\b/i', $text)
         // Platform + bare-noun-followed-by-time-adverbial
         || preg_match('/\b'.$entertainmentNoun.'\s+(\w+\s+){0,4}(rabbit\s+hole|black\s+hole|spiral|loop|marathon|binge|refresh|drama|hopping|scrolling|browsing|lurking|stalking)\b/i', $text)
         // bare entertainment-noun cluster + endings like "all afternoon",
         // "for hours", "until late", "every (X minutes)"
         || preg_match('/\b'.$entertainmentNoun.'\s+(\w+\s+){0,5}(every\s+(\d+|few|two|three|five|ten)\s*(min|mins|minutes|hours|seconds)|pointlessly|aimlessly|mindlessly)\b/i', $text)
         // Compulsive checking phrases — explicit "every Nx" markers.
         // We restrict the "kept refreshing X" pattern to require a
         // follow-up duration ("every Nx" / "for hours" / etc.) — without
         // it, "kept refreshing twitter" reads as ambiguous (no clear
         // duration commitment).
         || preg_match('/\b(checked|refresh(ed|ing)?|opened)\s+(\w+\s+){0,5}every\s+(\d+|few|two|three|five|ten)\s*(min|mins|minutes|seconds)\b/i', $text)
         || preg_match('/\bkept\s+(refresh(ing)?|opening|checking|reloading|tap(ping)?|hitting)\s+(\w+\s+){0,5}(every\s+(\d+|few|two|three|five|ten)|for\s+(hours|ages|the\s+(whole|entire)|\d+|one|two|three|four|five)|all\s+(day|night|morning|afternoon|evening|weekend))\b/i', $text)
         // Anywhere-in-phrase rule: entertainment platform noun + a clear
         // long-duration marker, regardless of proximity. Catches phrases
         // like "pinterest wedding boards even though im single for hours"
         // where the duration is far from the platform name.
         // EXCEPTION: skip if a productive verdict word is also present
         // ("no twitter today wrote the entire essay" should stay productive).
         // ALSO skip if the action half is decisively productive ("wanted
         // to scroll instagram but ended up reading research papers for
         // ages" — the platform appears in the FAILED-INTENT half, not the
         // action half, and the action half is reading-research).
         || (preg_match('/\b'.$entertainmentNoun.'\b/i', $text)
             && preg_match('/\b(for\s+(hours|ages|forever|the\s+(whole|entire))|all\s+(day|night|morning|afternoon|evening|weekend|saturday|sunday)|the\s+(whole|entire)\s+(morning|afternoon|evening|day|night|week|weekend|saturday|sunday)|every\s+(\d+|few|two|three|five)\s*(min|minute|minutes|seconds)|even\s+though\s+\w+\s+\w+\s+for\s+hours)\b/i', $text)
             && ! preg_match('/\b(no|zero|never|didn\'?t|wrote|finished|shipped|completed|delivered|got it done|nailed it|crushed it|pushed through|powered through|finished\s+the|wrote\s+the|completed\s+the)\b/i', $text)
             // Productive-tail exception: if the action half (after but/then/etc.)
             // contains a decisively productive resolution, skip.
             && ! preg_match('/\b(but|then|instead|ended\s+up|wound\s+up|actually)\s+(\w+\s+){0,8}(read(ing)?|stud(y(ing)?|ied)|cod(e|ing|ed)|wrote|writing|practic(ed|ing)|exercis(ed|ing)|ran|run(ning)?|trained|train(ing)?|focused|journal(ed|ing)|cook(ed|ing)|baked?|cleaned|prepped|completed|finished|reviewed|drafted|got|attended|joined|tutored|taught|mentored|deep\s+work|deep\s+dive\s+on\s+my|focused\s+(study|work|session|block|sprint)|leetcode|dsa|gym|workout|yoga|meditation|research\s+papers?|textbook|stanford|cs\d+|ml\s+course|bar\s+prep|gre|mcat|cpa|side\s+project|build(ing)?\s+the|outline(d)?|live\s+coding|grading|debugging|tax\s+forms|bike\s+ride|run\s+errands|chess|piano|guitar|violin|paper|essay|novel|chapter)\b/i', $text)
             // Brief-unproductive-then-productive exception: "scrolled tiktok for 5 mins then did 3 hours of focused work"
             && ! preg_match('/\b(then|but|and)\s+(\w+\s+){0,3}(did|crushed|locked\s+in|sat\s+down|jumped|dove|ground|finished|completed|shipped|wrote|coded|studied|practiced|read|exercised|ran|trained|focused|went)\s+(\w+\s+){0,5}(\d+\s+(hour|hr|h)|focused|deep|intensive|study|work)\b/i', $text))
         // Hangover / recovery wasted markers
         || preg_match('/\b(hungover|hangover|post-?party|post-?drink(ing)?|post-?night|post-?club|post-?rave|post-?festival|post-?gig|brutal\s+hangover|wrecked\s+from)\b/i', $text)
         // "X spiral" / "X marathon" — usually unproductive entertainment
         // BUT exclude productive marathon variants (athletic) AND
         // "marathon study" / "marathon writing" (intensive sessions).
         || (preg_match('/\b(doom\s*spiral|drama\s+spiral|binge\s+session|rabbit\s+hole|black\s+hole|hopping|hopped|drama\s+thread|comments\s+section|comment\s+war|reply\s+chain|comment\s+chain)\b/i', $text)
             && ! preg_match('/\b(ran|run|finished|completed|trained|training|study|coding|writing|reading|focused|deep\s+work|exercise)\b/i', $text))
         // Fake-reading patterns: "read first page of N books"
         || preg_match('/\bread\s+(first\s+page|one\s+page|the\s+intro|just\s+the\s+title|abstract\s+only)\s+of\s+\w+/i', $text)
         // "read X comments/drama/hate/etc for N hours" — drama reading procrastination
         || preg_match('/\bread\s+(\w+\s+){0,3}(comments?|drama|hate|beef|threads?|replies|qrt|tweets?|memes?|subtweets?|reviews|gossip|mean\s+\w+|youtube\s+hate|hate\s+\w+|forums?|debates?|reels|fan\s*wars?|other\s+\w+|reddit\s+\w+|twitter\s+\w+)\s+(\w+\s+){0,3}for\s+(\d+|one|two|three|four|five|six|seven|eight|nine|ten)\s+(h|hr|hrs|hour|hours|min|minutes?)\b/i', $text)
         // "read youtube hate comments compulsively" / "read X compulsively"
         || preg_match('/\bread\s+(\w+\s+){0,3}(comments?|drama|hate|threads?|replies|tweets?|memes?|reviews?)\s+(compulsively|aimlessly|mindlessly|pointlessly)\b/i', $text)
         // Bare gaming-game names (no verb required) + duration noun phrase
         || preg_match('/\b'.$entertainmentNoun.'\s+(matches|sessions|levels|grind|raid|runs|games|episodes|movies|shows|streams|videos|reels|shorts|posts|threads|boards|feed|tab|scroll|page|chain|loop|hopping|spiral|marathon|binge|rabbit\s+hole)\s*(\w+\s+){0,4}(for\s+(hours|ages|forever|the|\d+|one|two|three|four|five|six|seven|eight|nine|ten)|all\s+(day|night|morning|afternoon|evening|weekend|saturday|sunday)|the\s+(whole|entire))\b/i', $text)
         // Activity nouns + duration: "matches all night", "raid sessions for hours", "feed for two hours"
         || preg_match('/\b(matches|sessions|grind|raid|games|episodes|movies|shows|streams|videos|reels|shorts|feed|tab|scroll|loop|marathon|binge|spiral|rabbit\s+hole|black\s+hole|hopping)\s+(for\s+(hours|ages|forever|the|\d+|one|two|three|four|five|six|seven|eight|nine|ten)|all\s+(day|night|morning|afternoon|evening|weekend|saturday|sunday)|the\s+(whole|entire))\b/i', $text)
         // Stress eating / mindless snacking / channel surfing / midnight X
         || preg_match('/\b(stress\s+(eat(ing|en|ed)?|chew(ing|ed)?|snack(ing|ed)?)|mindless(ly)?\s+(snack(ing|ed)|graz(ing|ed)|eat(ing|en|ed))|emotional\s+eating|channel\s+(surf(ing|ed)?|hopp(ing|ed)?)|midnight\s+(fridge|cookie|snack|raid)|fridge\s+raid)\b/i', $text)
         // Excessive sleep / lying-in-bed patterns
         // Sleep / lying-in-bed patterns — REQUIRE duration so phrases like
         // "meant to gym but stayed in bed scrolling" stay ambiguous (no
         // duration → user can't tell how long; ask).
         || preg_match('/\b(slept\s+til\s+(\d+|noon|the\s+afternoon|late|midnight|dawn|sunrise|the\s+evening)|lay(ed|laid)?\s+in\s+bed\s+(\w+\s+){0,3}(for\s+(hours|the|ages|\d+)|all\s+\w+|til|until|the\s+whole|the\s+entire)|hit\s+snooze|nap(ped|ping)?\s+(for\s+(\d|hours|ages|the)|all\s+\w+|til\s+\w+|until\s+\w+|the\s+(whole|entire))|stayed\s+in\s+bed\s+(\w+\s+){0,3}(scrolling|watching|on\s+(phone|reddit|tiktok|youtube|netflix|twitter|instagram))\s+(\w+\s+){0,3}(for\s+(hours|the|\d|ages)|all\s+\w+|til\s+\w+|until\s+\w+|the\s+(whole|entire))|stayed\s+in\s+bed\s+(\w+\s+){0,3}(all|for\s+(hours|ages|\d|the))|crashed\s+(at|early)|stayed\s+up\s+(til|until|gaming|scrolling|watching|on)|slept\s+(through|the\s+(whole|entire|afternoon|morning))|laid\s+(in\s+bed|down|on\s+the\s+couch)\s+(\w+\s+){0,3}(scrolling|watching|all\s+\w+|for\s+(hours|ages|\d)|hungover|napping))\b/i', $text)
         // Comparison spirals / online drama / stalking
         || preg_match('/\b(spiral(ed|ing)?\s+(comparing|over|on|reading|stalking)|stalk(ed|ing)?\s+\w+\s+on\s+(instagram|facebook|twitter|linkedin|reddit|tiktok)|got\s+into\s+\w+\s+(argument|fight|drama|war)|read\s+\w+\s+drama|got\s+sucked\s+into|fell\s+into\s+\w+\s+(rabbit|drama|spiral|loop))\b/i', $text)
         // Procrastination / fake-productivity patterns
         // Procrastination/fake-productivity. Tightened: "stared at X" and
         // "opened X then closed" only fire when accompanied by an explicit
         // duration ("for an hour", "for hours") — without it, those phrases
         // are genuinely ambiguous.
         || preg_match('/\b(reorganized\s+(my\s+)?(desktop|notes|inbox|todo|kanban|board|files|notion|calendar|playlist|setup|workspace)\s+instead\s+of|made\s+(a\s+)?(playlist|list|plan|todo|schedule)\s+(and\s+)?(didnt|never)|color[- ]?coded|fake\s+productive|pretended\s+to\s+(study|work)|sat\s+at\s+(the\s+)?(desk|laptop|keyboard|computer)\s+(and|but)\s+(did|made|wrote|accomplished)\s+(no|nothing|zero)|stared\s+at\s+(the|my)\s+(todo|list|cursor|page|screen|laptop|book|textbook)\s+(\w+\s+){0,3}for\s+(an\s+hour|hours|\d+\s+\w+)|spent\s+(an\s+hour|hours)\s+(picking|choosing|configuring|customizing|tweaking|fiddling)|opened\s+(the\s+)?\w+\s+(and\s+)?(then\s+)?closed\s+(\w+\s+){0,2}for\s+(an\s+hour|hours|\d+))\b/i', $text)
         // Compulsive checking patterns
         // Compulsive-checking — REQUIRES a quantifier ("every Nx" / "for hours")
         // so "kept refreshing twitter" alone stays ambiguous.
         || preg_match('/\b(refresh(ed|ing)?\s+(\w+\s+){0,3}every|checked\s+(\w+\s+){0,3}every|kept\s+(refresh(ing)?|opening|checking|reloading|tap(ping)?|hitting)\s+(\w+\s+){0,5}(every\s+(\d+|few|two|three|five|ten)|for\s+(hours|ages|the|\d+)|all\s+(day|night|morning|afternoon|evening))|notification\s+check\s+(loop|all)|phone\s+(in\s+hand\s+(for|all|the)|pickup\s+(loop|count)|peek(ing)?\s+(loop|under)|grab\s+loop|glance\s+(loop|for))|opened\s+phone\s+(unconsciously|every|loop)|f5\s+(spam(med)?|reload\s+addiction)|hit\s+refresh\s+(on|loop|every))\b/i', $text)
         // Passive consumption phrases
         || preg_match('/\b(window\s+(shop(ping|ped)?|browse?d?)|aimless(ly)?\s+(scroll(ing|ed)?|browse?(ing|d)?|wander(ing|ed)?)|pointless(ly)?\s+(scroll(ing|ed)?|browse?(ing|d)?|lurk(ing|ed)?)|mindless(ly)?\s+(check(ing|ed)?|tap(ping|ped)?|swip(ing|ed)?)|cart\s+(never|abandoned|filled)|bookmarked\s+\d+\s+items?|wishlist\s+(building|edits))\b/i', $text)
         // "X instead of Y" — substitution procrastination. Catches
         // "cleaned room instead of studying", "reorganized notion all
         // morning instead of working", "researched X for hours instead
         // of doing it". Note: this matches even when X is normally a
         // productive verb because the "instead of Y" qualifier signals
         // that the user evaluated their day as wasted relative to intent.
         || preg_match('/\b\w+(ed|ing)?\s+(\w+\s+){0,5}instead\s+of\s+(\w+(ing|ed)?|the|studying|working|gym|writing|coding|reading|exercise|exercising)\b/i', $text)
         // "X but didnt Y" / "X but never Y" — failed-intent patterns
         // when X is small/preparatory and Y is the real goal
         || preg_match('/\b(researched|read|watched|listened|made\s+(a|the))\s+(\w+\s+){0,4}(instead|but\s+didnt|but\s+did\s+not|but\s+never|and\s+didnt|and\s+never)\s+(\w+\s+){0,2}(study|studying|work|working|do\s+it|start|started|finish|begin|begun|gym|exercise|run|practice|practiced|wrote|coded|read\s+them|act\s+on)\b/i', $text)
         // "researched X for N hours but didn't" pattern
         || preg_match('/\b(researched|made|watched)\s+\w+\s+(\w+\s+){0,3}(for\s+(an\s+hour|hours|\d+\s+\w+)|all\s+\w+)\s+(but|and)\s+(didnt|did\s+not|never|kept|then\s+napped)\b/i', $text)
         // "Nh of (platform/activity)" — bare numeric duration prefix
         // (also accepts word-form numbers like "four hours of...")
         || preg_match('/\b(\d+(\.\d+)?|one|two|three|four|five|six|seven|eight|nine|ten|eleven|twelve|fifteen|twenty|thirty|forty|fifty|sixty|hundred)\s*(h|hr|hrs|hour|hours|min|minute|minutes)\s+(of\s+)?(scrolling|scroll|gaming|grinding|grind|browsing|lurking|tv|netflix|youtube|yt|tiktok|reels|reddit|twitter|instagram|facebook|twitch|discord|telegram|whatsapp|memes|tweets|videos|shorts|games|matches|sessions|levels|raids|dms|chat|chats|streams|episodes|movies|shows|drama|deep\s+dive|rabbit\s+hole|doomscroll(ing)?|mobile\s+games?|mobile\s+gaming)\b/i', $text)
         // "X hour deep dive into Y" / "X hour scroll session"
         || preg_match('/\b\d+(\.\d+)?\s*(h|hr|hrs|hour|hours|min|minutes?)\s+(deep\s+dive|scroll(ing)?|tiktok|reels|reddit|netflix|youtube|grinding|gaming|doom|drama|spiral|rabbit|browse|browsing|marathon|binge)\s+(session|into|of|on|at)?/i', $text)
         // "N hour Y session" reversed: "30 minute scroll session"
         || preg_match('/\b\d+(\.\d+)?\s*(min|minute|minutes|h|hr|hrs|hour|hours)\s+(scroll|tiktok|reels|reddit|netflix|youtube|grinding|gaming|doom|browse|browsing|marathon|binge)\s+session\b/i', $text)
         // Self-evaluative wasted day patterns
         || preg_match('/\b(absolute|big|huge|complete|total|literal|pure|sheer|absolutely|completely|totally)\s+(useless|zero|waste|wasted|nothing|bust|write[\s\-]?off|trash|disaster|unproductive|fail)\s+(day|of\s+a\s+day|kind\s+of|day\s+overall)?\b/i', $text)
         || preg_match('/\bday\s+(was|got|is|fully|completely|absolutely|totally)\s+(a|an)?\s*(complete|total|absolute|big|huge|fully|just|really)?\s*(bust|waste|wasted|disaster|trash|write[\s\-]?off|nothing|fail|gone|down\s+the\s+drain|thrown\s+away|flushed|ruined|in\s+pajamas|spent|just\s+a)\b/i', $text)
         || preg_match('/\b(big|huge|total|complete|absolute)?\s*(fat\s+)?(unproductive|zero|nothing|wasted|trash|bust)\s+(day|of|kind\s+of)\b/i', $text)
         // "didnt X" — failed productive activity. Only fires for STRONG
         // productive-verb negations, not generic "couldnt get" / "didnt
         // do" which can apply to any noun (including "couldnt get out
         // of bed", which is failed-intent → ambiguous, not unproductive
         // verdict).
         || preg_match('/\b(didnt|did\s+not|never|hardly|barely|wouldnt|would\s+not)\s+(\w+\s+){0,3}(accomplish|finish|complete|read|write|practice|study|studied|review|revise|exercise|gym|cook|clean|attend|prepare|start|open\s+the\s+(textbook|book|laptop)|workout|produce|cook\s+a\s+single|read\s+the|write\s+a|read\s+anything|write\s+a\s+single|do\s+my|run\s+a\s+single|practice\s+\w+\s+today|do\s+a\s+single|bother|focus|reach\s+the|make\s+it\s+to|manage\s+to|manage\s+a\s+single|prep|file|submit|fix|debug|deploy|ship|complete\s+a\s+single|act\s+on|move\s+on\s+the)\b/i', $text)
         // "X for hours did/wrote no Y" — failed-productivity-after-time
         || preg_match('/\b(\w+(ed|ing)?)\s+(\w+\s+){0,5}(for\s+(an\s+hour|hours|\d+\s+\w+)|all\s+\w+)\s+(did|wrote|got|produced|achieved|accomplished)\s+(no|nothing|zero)\b/i', $text)
         // "configured X for N hours not working" / "designed logo for app i havent started"
         || preg_match('/\b(configured|designed|tweaked|customized|adjusted|chose|sorted|arranged|fiddled\s+with|polished|tuned|set\s+up|installed|setup|built|prepared|prepped|organized|made)\s+\w+\s+(\w+\s+){0,5}(not\s+working|never\s+(used|started|opened|finished)|i\s+havent\s+(started|finished|done|opened)|that\s+i\s+never|then\s+(napped|nothing)|then\s+phone|but\s+(then\s+)?phone)\b/i', $text)
         // "X became Y" / "X turned into Y" — time-creep patterns
         || preg_match('/\b(\d+|a\s+(quick|short))\s*(min|minute|minutes|h|hr|hrs|hour|hours)?\s+\w+\s+(became|turned\s+into|stretched\s+into|dragged\s+into|extended\s+to|ate)\s+(\w+\s+){0,3}(hours?|all\s+day|all\s+night|forever|the\s+whole|the\s+entire)\b/i', $text)
         // Self-deprecating "deeply focused on instagram" / sarcastic
         || preg_match('/\b(deeply\s+focused\s+on|devoted\s+myself\s+to|dedicated\s+\w+\s+to|earmarked\s+\w+\s+for|envisioned\s+\w+\s+(got|wound|ended)|committed\s+to\s+(focus|productivity)\s+(committed|got))\b.*\b(instagram|tiktok|youtube|netflix|reddit|twitter|reels|gaming|scrolling|procrastination|nothing)\b/i', $text)
         // "X i regret"/"life is gone"/"zero work"/"no homework"
         || preg_match('/\b(i\s+regret\s+(it|today)|life\s+is\s+gone|zero\s+work|no\s+(work|homework|study|reading|writing|coding|exercise)\s+(done|today))\b/i', $text)
         // Texting slang
         || preg_match('/\b(atp|deadass|cap|fr\s+fr|smh|ngl)\s+(\w+\s+){0,5}(didnt|did\s+not|wasted|nothing|scroll|tiktok|reels|reddit|netflix|youtube|gaming)\b/i', $text)
         // "convinced myself i deserved a break which lasted 5 hours"
         || preg_match('/\b(convinced\s+myself|told\s+myself|let\s+myself|gave\s+myself)\s+\w+\s+(\w+\s+){0,4}(break|rest|nap)\s+(which|that)\s+(lasted|turned|became|stretched)\s+(\w+\s+){0,2}(hours|all|the)\b/i', $text)
         // "ate during X hour stream/episode/marathon"
         || preg_match('/\bate\s+\w+\s+(\w+\s+){0,4}(during|while|through)\s+(\w+\s+){0,3}(stream|episode|marathon|binge|tv|netflix|youtube|tiktok|reels|reels|movie)\b/i', $text)
         // Single-word entertainment with explicit duration: "8am to 6pm netflix"
         || preg_match('/\b\d+\s*(am|pm)\s+to\s+\d+\s*(am|pm)\s+(netflix|youtube|tiktok|reddit|twitter|instagram|gaming|scrolling)\b/i', $text)
         // "X auto battle for hours" / "rpg for hours" — mobile gaming
         || preg_match('/\b(rpg|mmorpg|auto\s+battle|gacha|raid\s+session)\s+(\w+\s+){0,3}(for\s+(hours|\d+\s+\w+|the\s+(whole|entire))|all\s+\w+)\b/i', $text)
         // "made schedule and ignored it" / "made meal plan and ordered takeout"
         || preg_match('/\bmade\s+(a\s+)?(meal\s+plan|schedule|plan|todo|todo\s+list|to\s*do\s+list|list|playlist|study\s+plan|workout\s+plan|study\s+setup|aesthetic|routine|vision\s+board|spreadsheet|budget|chart|board|kanban)\s+(\w+\s+){0,3}(and|but|then)\s+(\w+\s+){0,3}(ignored|never\s+(used|opened|started)|didnt|did\s+not|ordered|skipped|forgot|threw\s+away)\b/i', $text)
         // "bought new planner and didnt plan anything" / "bought X never used"
         || preg_match('/\b(bought|got|downloaded|installed|purchased)\s+(a\s+)?(new\s+|the\s+)?\w+\s+(\w+\s+){0,3}(and\s+|but\s+)(didnt|did\s+not|never\s+(used|opened|started|plan(ned)?))\b/i', $text)
         // "deleted apps for productivity then redownloaded"
         || preg_match('/\b(deleted|uninstalled|removed)\s+(\w+\s+){0,3}(apps?|tools|extensions)\s+(\w+\s+){0,3}(then\s+(redownloaded|downloaded|reinstalled))\b/i', $text)
         // "spent hour picking what to study" — preparation procrastination
         || preg_match('/\bspent\s+(an?\s+)?(hour|hours|\w+\s+(picking|choosing|deciding|configuring|tweaking|fiddling))\s+(picking|choosing|deciding|finding|researching|configuring|customizing|tweaking|fiddling|sorting|arranging|reorganizing|setting\s+up|optimizing)\b/i', $text)
         // (REMOVED) "studied for 2 minutes then phone" — these are now
         // classified as AMBIGUOUS in detectHedge below, not UNPRODUCTIVE.
         // The productive intent + brief productive effort makes the
         // verdict genuinely unclear from the user's perspective.
         // "X session became Y hours" — time creep
         || preg_match('/\b(\d+\s*(min|minute|minutes|h|hr|hrs|hour|hours)|a\s+quick\s+\w+|quick\s+\d+\s*(min|hour))\s+(\w+\s+){0,3}(scroll|tiktok|reels|reddit|netflix|youtube|gaming|grinding|doom|browse|browsing|marathon|binge|session)\s+(became|turned\s+into|stretched\s+into|extended\s+to|ate)\s+(\w+\s+){0,3}(hours?|all|forever|the)\b/i', $text)
         // "absolute zero productivity" / "big fat unproductive" / "trash day"
         || preg_match('/\b(absolute\s+(zero|nothing|disaster|trash|write[\s\-]?off|useless)|big\s+(fat\s+)?(unproductive|nothing|zero|bust|waste)|huge\s+(waste|nothing|bust)|complete\s+(bust|waste|fail|disaster)|total\s+(bust|waste|fail|disaster|trash|write[\s\-]?off|nothing|day\s+waste|slacker)|trash\s+day|slacker\s+day|big\s+time)\b/i', $text)
         // "intent + decisive failure resolution" — captures phrases not
         // handled by conflict detector because the failure marker is in
         // detectClearVerdict's anti-productive list. "wanted to do meal
         // prep but ordered takeout and watched tv" → UNPRODUCTIVE.
         || (preg_match('/\b(wanted|tried|planned|meant|intended|hoped|aimed|going|gonna|supposed|thought i would|decided|told myself|said i would|set out|aiming|aimed|was\s+supposed)\s+to/i', $text)
             && preg_match('/\b(but|then|instead|ended\s+up|wound\s+up|kept|couldn\'?t|couldnt|didn\'?t|didnt)\s+(\w+\s+){0,8}(slept\s+(in\s+)?til|nap(ped|ping)?\s+(instead|all|for|the)|stayed\s+in\s+bed|laid\s+in\s+bed|hit\s+snooze\s+\d|binged\s+(netflix|youtube|tutorials|kdrama|anime|shows|the\s+office)|ordered\s+(takeout|food|uber\s+eats)\s+(and|then)\s+(watched|scrolled|gamed)|reorganized\s+\w+\s+(playlists?|notes|kanban|spotify)|never\s+tried|rewatched\s+the\s+office|my\s+phone\s+won|my\s+body\s+wanted\s+(hbo|netflix|youtube|tiktok|tv)|watched\s+yt\s+instead|binged\s+netflix\s+instead|scrolled\s+(\w+\s+){0,2}(instead|then\s+ordered)|scroll\s+fest|all\s+day\s+(on|scrolling))\b/i', $text))
         // "failed to X" — explicit failure of productive activity
         || preg_match('/\bfailed\s+to\s+(\w+\s+){0,3}(finish|complete|start|do|read|write|study|practice|review|revise|work|gym|run|exercise|cook|clean|attend|show|focus|wake|ship|prep|make|deliver|hit|reach|meet|file|submit|pay|book|renew|move\s+on|follow|finish\s+the|complete\s+the|do\s+the|read\s+the|write\s+the|finish\s+anything|do\s+any|do\s+a|do\s+my|do\s+the|wake\s+up|prep\s+(my|the)|run\s+\w+|read\s+\w+|write\s+\w+|study\s+\w+|practice\s+\w+|do\s+anything|complete\s+anything|finish\s+a|read\s+anything|write\s+any|all\s+my)\b/i', $text)
         // "failed all my goals" / "failed every single goal"
         || preg_match('/\bfailed\s+(\w+\s+){0,2}(all|every|some|any|the|both)\s+(\w+\s+){0,2}(goals?|tasks?|todos?|targets?|objectives?|milestones?|deadlines?|plans?)\b/i', $text)
         // "fully blank productivity day" / "blank productivity"
         || preg_match('/\b(fully\s+blank|blank|empty|hollow|barren|fruitless|sterile)\s+(productivity|achievements?|day|days|outcomes?|hours?)\b/i', $text)
         // "full N hours on platform" / "the full 5 hours" / "the entire X on Y"
         || preg_match('/\b(full|the\s+full|the\s+whole|the\s+entire|literally|whole)\s+\d+\s*(h|hr|hrs|hour|hours)\s+(on|of|at|in)\s+(\w+\s+){0,2}(netflix|youtube|tiktok|reddit|twitter|instagram|facebook|reels|shorts|gaming|grinding|scrolling|memes|videos)\b/i', $text)
         // "configured/built X for hours did no Y" — fake productivity fail
         || preg_match('/\b(configured|built|set\s+up|tweaked|adjusted|customized|prepped|organized|sorted|arranged|optimized|tuned|fiddled\s+with)\s+\w+\s+(\w+\s+){0,3}for\s+(\d+|two|three|four|five|six|seven|eight|nine|ten|an?|several|many)\s*(hour|hours|min|minutes)\s+(\w+\s+){0,3}(did|wrote|got|made|built|achieved)\s+(no|nothing|zero|none)\b/i', $text)
         // "X hours straight no joke / scrolling / gaming"
         || preg_match('/\b\d+\s*(h|hr|hrs|hour|hours)\s+(straight|nonstop|in\s+a\s+row)\s+(no\s+joke|gaming|scrolling|grinding|doomscrolling|of\s+\w+)?\b/i', $text)
         // "gaming sesh of N hours" — informal session
         || preg_match('/\b(gaming|grinding|doomscrolling|browsing|lurking)\s+sesh\s+of\s+\d+\s*(h|hr|hrs|hour|hours)\b/i', $text)
         // "gone" suffixed to entertainment
         || preg_match('/\b(\d+\s*(hr|hrs|hour|hours)|all\s+\w+)\s+(gone|wasted|disappeared|down\s+the\s+drain|in\s+the\s+void|to\s+(tiktok|reddit|netflix|youtube|gaming|scrolling))\b/i', $text)
         // "googled X" / "stalked X" — explicit drama/stalking patterns
         || preg_match('/\b(googled|stalked|spied\s+on|investigated|looked\s+up|searched\s+for)\s+(\w+\s+){0,3}(ex(es)?|crush|partner|new\s+\w+|old\s+(classmate|friend|crush))\s+(for\s+(ages|hours|\d|\w+)|all\s+(day|night|afternoon|evening|morning))?/i', $text)
         // "X chat for N hours" / "discord chat for 4 hours"
         || preg_match('/\b(discord|telegram|whatsapp|slack|teams)\s+(chat|server|channel|chatting)\s+for\s+\d+\s*(h|hr|hrs|hour|hours)\b/i', $text)
         // "ate X / finished Y watching Z" — eating + entertainment cluster
         || preg_match('/\b(ate|finished|inhaled|polished|demolished|munched|grazed)\s+(\w+\s+){0,5}(watching|while\s+watching|the\s+whole\s+(episode|show|season|movie|stream)|in\s+front\s+of\s+(tv|netflix|youtube|reels|tiktok))\b/i', $text)
         // "binged tehe whole season" / typo + binge + season
         || preg_match('/\bbinged\s+\w*\s*(whole|entire|the)\s+(season|series|show|episodes?)\b/i', $text)
         // "fell back to sleep after alarm" — wake-fail patterns
         || preg_match('/\b(fell\s+back\s+to\s+sleep|hit\s+snooze|went\s+back\s+to\s+bed|crashed\s+(at|early)|crashed\s+for|nap\s+turned\s+into|figured\s+id\s+rest)\s+(\w+\s+){0,5}(wasted\s+\w+|all|hours|the\s+whole|\d+\s+hour)\b/i', $text)
         // "dead set on X dead is the right word" — sarcastic productivity-fail
         || preg_match('/\b(dead\s+set\s+on\s+\w+\s+dead\s+is|committed\s+to\s+\w+\s+committed\s+(crime|to\s+nothing|to\s+failure)|gearing\s+up\s+gearing\s+failed|gonna\s+\w+\s+gonna\s+(scroll|game|nap|procrastinate)|gotta\s+\w+\s+gotta\s+(scroll|game|nap|procrastinate)|bouta\s+\w+\s+bouta\s+(scroll|game|nap|procrastinate)|finna\s+\w+\s+(got|finna\s+got)\s+distracted)\b/i', $text)
         // "bro/dude/fam i was supposed to X and didnt" — slang failure
         || preg_match('/\b(bro|dude|fam|jk|atp|ngl|tbh|smh|lol|cap)\s+(\w+\s+){0,5}(supposed\s+to\s+\w+|i\s+(was|am)\s+supposed)\s+(\w+\s+){0,5}(and\s+didnt|but\s+didnt|i\s+didnt|never)\b/i', $text)
         // "compared X paths on linkedin doomscrolling" — explicit doomscroll on platform
         || preg_match('/\b(compared|stalked|spied|lurked|browsed)\s+\w+\s+(on|in|at)\s+(linkedin|instagram|tiktok|reddit|twitter|facebook|youtube)\s+(\w+\s+){0,3}(doomscroll(ing)?|scroll(ing)?|for\s+\w+|all\s+\w+)\b/i', $text)
         // "copied notes prettily without learning" — fake-productive
         || preg_match('/\b(copied|formatted|highlighted|reorganized|color[\s\-]?coded|prettified|decorated|aestheticized)\s+\w+\s+(\w+\s+){0,3}(without\s+(learning|reading|doing|studying|absorbing|practicing)|but\s+(didnt|never)\s+(learn|read|do|study|absorb|practice))\b/i', $text)
         // "didnt look at the lecture slides" / "didnt look at X"
         || preg_match('/\b(didnt|did\s+not|never)\s+(look\s+at|open|read|review|skim)\s+(\w+\s+){0,3}(the\s+(lecture|slides|notes|textbook|book|chapter|email|inbox|todo))\b/i', $text)
         // "downloaded X for productivity used none"
         || preg_match('/\b(downloaded|installed|got|bought)\s+\w+\s+(\w+\s+){0,3}(for\s+productivity|to\s+(focus|study|work|help))\s+(\w+\s+){0,3}(used\s+(none|nothing)|never\s+(opened|used)|didnt\s+(open|use))\b/i', $text)
         // "an hour turned into all day" / "5 mins ended up 3 hour"
         || preg_match('/\b(\d+\s*(min|minute|minutes|h|hr|hrs|hour|hours)|an?\s+hour|a\s+few\s+\w+)\s+(\w+\s+){0,3}(turned\s+into|stretched\s+to|extended\s+to|ended\s+up\s+\d+|became)\s+(\w+\s+){0,3}(\d+\s*(h|hr|hour|hours)|all\s+(day|night)|forever)\b/i', $text)
         // "game plan got benched by mobile games" — sarcastic
         || preg_match('/\b(game\s+plan|study\s+plan|workout\s+plan|focus\s+plan|productivity\s+plan)\s+(\w+\s+){0,3}(got\s+(benched|sidetracked|hijacked|derailed|killed)|died\s+at|never\s+started|got\s+nothing)\b/i', $text)
         // "bracing for it but it never came just netflix did"
         || preg_match('/\b(bracing|prepping|gearing|primed|ready|set)\s+(for|up)\s+\w+\s+(\w+\s+){0,5}(but|and)\s+(\w+\s+){0,3}(never\s+(came|started|happened)|just\s+(netflix|youtube|tiktok|scrolling|gaming|nothing))\b/i', $text)
         // "no X / zero X / never X" with productive object
         || preg_match('/\b(no|zero|never|hardly|barely)\s+(work|homework|sleep|study|studying|reading|writing|coding|exercise|exercises?|workout|practice|practicing|effort|focus|productivity|achievement|achievements|chapter|chapters|pages|reps|reps|drills|sets|tasks|task|drafts|essays|miles|km|kilometers)\s+(today|done|all\s+day|completed|happened)?\b/i', $text)
         // Suffix "no X / zero X" after a time/platform marker
         || preg_match('/\b(all\s+(day|night|morning|afternoon|evening)\s+(on\s+\w+\s+)?|(for\s+|the\s+(whole|entire))\s*\d+\s*(h|hr|hrs|hours?|min|minute|minutes?)\s+(on\s+\w+\s+)?)\s*(no|zero)\s+(work|homework|sleep|study|reading|writing|coding|exercise|workout|practice|focus|productivity)\b/i', $text)
         // "X hours of mindless Y" / "forty five minutes of mindless"
         || preg_match('/\b(\d+|\w+\s+\w+|forty\s+five|thirty|forty|fifty)\s*(min|minute|minutes|h|hr|hrs|hour|hours)\s+of\s+(mindless|aimless|pointless|pure)\s+\w+/i', $text)
         // "from morning till noon on reels" / "for 5h i scrolled"
         || preg_match('/\bfrom\s+(\w+\s+){0,3}(till|until|to)\s+(\w+\s+){0,2}(on|of|at)\s+(\w+\s+){0,2}(reels|tiktok|reddit|netflix|youtube|instagram|twitter|gaming|scrolling|memes|phones?)\b/i', $text)
         // "for 5h i scrolled" / "the whole 5 hours"
         || preg_match('/\b(for\s+\d+\s*(h|hr|hrs|hours?|min|minutes?))\s+(\w+\s+){0,2}(i|we)\s+(scroll(ed|ing)?|gamed|watched|binged|browsed|doomscrolled|napped)\b/i', $text)
         // "X hours of nothing today" / "8 hours of nothing"
         || preg_match('/\b\d+\s*(h|hr|hrs|hours?)\s+of\s+(pure\s+)?(nothing|procrastination|wasted|distraction|tiktok|reels|netflix|youtube|reddit|gaming|grinding|doomscrolling|browsing)\b/i', $text)
         // "day was/got X" descriptive — bust/waste/nothing
         || preg_match('/\bday\s+(was|got|got\s+just|spent|just\s+a|was\s+just\s+a|was\s+a\s+complete|was\s+a\s+total|completely|fully|absolutely|totally)\s+(\w+\s+){0,3}(scroll|scrolling|nothing|in\s+front|wasted|wasted\s+in|in\s+pajamas|spent\s+scrolling|on\s+(phone|netflix|youtube|tiktok|reels)|pajama|down\s+the\s+drain|flushed|gone|thrown\s+away|ruined|lost|written\s+off|write\s+off|no\s+go|trash|disaster|in\s+front\s+of|just\s+a\s+phone|spent\s+in\s+pajamas|just\s+a\s+(phone|scroll))\b/i', $text)
         // "empty X" descriptors of unproductive outcome
         || preg_match('/\b(empty|hollow)\s+(achievements?|hours?|productivity|day|days|outcomes?)\b/i', $text)
         // Snacking + entertainment combos
         || preg_match('/\b(ate|finished|inhaled|polished|demolished|grazed|snacked|munched)\s+(\w+\s+){0,4}(during|while|through|the\s+whole)\s+(\w+\s+){0,3}(stream|tiktok|netflix|youtube|reels|episode|movie|show|binge|marathon|tv|anime|kdrama|videos?|reddit|instagram)\b/i', $text)
         // "ate X straight from the tub for an hour watching tv"
         || preg_match('/\b(ate|finished|inhaled|polished|demolished)\s+(\w+\s+){0,5}(watching|while\s+watching|in\s+front\s+of)\s+(tv|netflix|youtube|reels|tiktok|reddit|instagram|twitch|hulu|disney|prime|hbo)\b/i', $text)
         // "blocked out time and then unblocked it for instagram" /
         // "carved out time and filled it with reddit"
         || preg_match('/\b(blocked|carved|set|allocated|earmarked|reserved|booked)\s+(out\s+)?(time|the\s+\w+|\w+\s+slot)\s+(\w+\s+){0,3}(and|but|then)\s+(\w+\s+){0,3}(unblocked|filled|spent|gave|wasted|threw|opened|broke)\s+(it|that|the\s+\w+)\s+(\w+\s+){0,2}(for|with|on)\s+(\w+\s+){0,2}(instagram|tiktok|reddit|youtube|netflix|twitter|reels|gaming|scrolling)\b/i', $text)
         // "X hours and counting (still scrolling/on platform)"
         || preg_match('/\b\d+\s*(h|hr|hrs|hour|hours)\s+(and\s+counting)\s+(\w+\s+){0,3}(still\s+\w+|on\s+(tiktok|reddit|instagram|twitter|netflix|youtube)|scrolling|gaming|grinding|doomscrolling)\b/i', $text)
         // "still scrolling/gaming after N hours"
         || preg_match('/\b(still\s+(scrolling|gaming|grinding|doomscrolling|on\s+(tiktok|reels|reddit|instagram))|nonstop\s+(scrolling|gaming))\b/i', $text)
         // Texting slang failure phrases
         || preg_match('/\b(bouta\s+\w+\s+bouta\s+(scroll|game|nap|watch|tiktok|reels|netflix)|finna\s+\w+\s+(got|finna\s+got)\s+distracted|cap\s+if\s+i|deadass\s+(didnt|did\s+not|nothing)|fr\s+fr\s+(didnt|wasted|nothing)|ngl\s+(didnt|wasted|nothing))\b/i', $text)
         // "committed/dedicated/devoted to X" + sarcastic failure
         || preg_match('/\b(committed|dedicated|devoted|earmarked|envisioned|determined|expected|primed|allocated|resolved)\s+(\w+\s+){0,5}(to\s+(focus|productivity|study|work|gym|exercise))\s+(\w+\s+){0,5}(none\s+of\s+it|nothing|crime\s+against|got\s+(procrastination|nothing|distracted|scroll)|expected\s+wrong|failed)\b/i', $text)
         // "deeply focused on instagram apparently" / sarcastic-apparently
         || preg_match('/\b(deeply\s+focused|laser\s+focused|locked\s+in|tunnel\s+visioned|all\s+in|fully\s+committed)\s+(on|to)\s+(\w+\s+){0,2}(instagram|tiktok|youtube|netflix|reddit|twitter|reels|tv|scrolling|gaming)\b/i', $text)
         // "finally decided to procrastinate" / "deciding to scroll apparently"
         || preg_match('/\b(finally\s+decided\s+to|decided\s+to|deciding\s+to|chose\s+to)\s+(\w+\s+){0,2}(procrastinate|scroll|game|nap|do\s+nothing)\b/i', $text)
         // "missed class" / "missed the deadline"
         || preg_match('/\b(missed|skipped)\s+(\w+\s+){0,2}(class|the\s+(deadline|meeting|gym|workout|appointment|class|lecture))\b/i', $text)
         // "X took my soul" / "lost the day to" / "life is gone"
         || preg_match('/\b(took\s+my\s+soul|lost\s+(the\s+)?day\s+to|life\s+is\s+gone|day\s+lost\s+to|down\s+the\s+drain|no\s+joke\s+gaming|gaming\s+\w+\s+no\s+joke|gaming\s+gaming|scrolling\s+scrolling)\b/i', $text)
         // "ten apps for productivity used none" / "downloaded X used none"
         || preg_match('/\b(downloaded|installed|got)\s+(\w+\s+){0,4}(used\s+none|never\s+(used|opened)|then\s+(redownloaded|reinstalled))\b/i', $text)
         // "DMs got me distracted for 3 hrs" / "distracted for X hours"
         || preg_match('/\b(distracted|sidetracked|pulled\s+away)\s+(\w+\s+){0,3}for\s+(\d+|an?|few)\s*(h|hr|hrs|hours?|min|minutes?)\b/i', $text)
         // "expected to study expected wrong" — sarcastic self-eval
         || preg_match('/\b(expected|hoped|wanted|planned|primed|determined)\s+(to\s+|for\s+)?\w+\s+(\w+\s+){0,2}(expected\s+wrong|got\s+nothing|got\s+procrastination|got\s+distracted|hoped\s+wrong|wanted\s+wrong|did\s+nothing)\b/i', $text)
         // "flopped X completely" / "trash day"
         || preg_match('/\b(flopped|bungled|botched|fumbled|tanked|bombed|wrecked)\s+(the\s+)?(day|sunday|saturday|monday|tuesday|wednesday|thursday|friday|morning|afternoon|evening|completely)\b/i', $text)
         // Slang explicit-failure phrases
         || preg_match('/\b(highkey|lowkey|hella|literal\s+trash|literally\s+(zero|nothing)|literally\s+a\s+(wasted|trash)|imho\s+today\s+was\s+a\s+(write\s+off|waste|trash|disaster)|idk\s+where\s+the\s+day\s+went|idc\s+i\s+(procrastinated|wasted|did\s+nothing)|gtg\s+\w+\s+(scrolled|just\s+scrolled|jk))\s+(\w+\s+){0,3}(procrastinat(ed|ing)|today|day|scrolled|wasted|trash|nothing|netflix|tiktok|gaming|scroll(ed|ing)?|literal|the\s+entire)?\b/i', $text)
         // "intent on focus my intent failed"
         || preg_match('/\b(intent|intention)\s+(on|to|was|to\s+be|of)\s+\w+\s+(\w+\s+){0,3}(intent\s+(failed|did\s+not|never\s+came)|did\s+not\s+survive|never\s+came|got\s+nothing|failed)\b/i', $text)
         // "had to study had to take a 4 hour nap apparently"
         || preg_match('/\bhad\s+(to\s+\w+|in\s+mind|it\s+on\s+my\s+list)\s+(\w+\s+){0,5}(had\s+to|still\s+on|still\s+at|after\s+\d+\s*hours?)\s+(\w+\s+){0,3}(nap|scroll|netflix|tiktok|nothing|chapter\s+1|the\s+list)\b/i', $text)
         // "X till my eyes hurt" / "X until 2am"
         || preg_match('/\b(insta|insta\s*gram|instagram|tiktok|reddit|twitter|netflix|youtube|reels|scrolling|gaming)\s+(\w+\s+){0,3}(till|until)\s+(my\s+eyes\s+hurt|\d+\s*am|\d+\s*pm|late|midnight|dawn|sunrise)\b/i', $text)
         // "insta X" with typos / abbreviations
         || preg_match('/\b(insta|insta\s*gram)\s+(dms|stories|sotries|scolling|scrolling|scroll(ing)?|reels|memes|feed|tab|gram|marathon)\s+(\w+\s+){0,3}(for\s+\w+|all\s+\w+|till|until|today|no\s+shame|marathon|straight|hurt)?\b/i', $text)
         // "X for like N hrs"
         || preg_match('/\bfor\s+like\s+\d+\s*(hr|hrs|hour|hours)\b/i', $text)
         // "half a day on X"
         || preg_match('/\b(half\s+a\s+day|half\s+the\s+day)\s+(on|of|at|in)\s+(\w+\s+){0,2}(tiktok|reels|reddit|instagram|netflix|youtube|twitter|gaming|scrolling|memes|videos)\b/i', $text)
         // "literally wasted/zero done"
         || preg_match('/\b(literally|absolutely|completely|totally|truly)\s+(a\s+wasted|zero\s+done|nothing|no\s+productivity|wasted)\s+(\w+\s+){0,3}(today|all\s+day|sunday|saturday|monday|day)?\b/i', $text)
         // "checked X compulsively for/all"
         || preg_match('/\bchecked\s+\w+\s+(\w+\s+){0,3}compulsively\s+(for\s+\w+|all\s+\w+|every\s+\w+)\b/i', $text)
         // "compared X paths on Y" stalking
         || preg_match('/\bcompared\s+(\w+\s+){0,2}(on|in|across)\s+(\w+\s+){0,2}(linkedin|instagram|tiktok|reddit|twitter|facebook|youtube)\s+(\w+\s+){0,2}(doomscrolling|for\s+\w+|all\s+\w+)\b/i', $text)
         // "deep dive on X social/instagram"
         || preg_match('/\bdeep\s+dive\s+(on|in|into)\s+(\w+\s+){0,3}(social|instagram|tiktok|reddit|twitter|facebook|crush|ex|exes|old\s+\w+)\s+(\w+\s+){0,3}(for\s+\w+|all\s+\w+)?/i', $text)
         // "determined to write but stared at blank page for X hours"
         || preg_match('/\b(determined|hoping|hoped)\s+to\s+\w+\s+(but|then)\s+(\w+\s+){0,3}(stared|sat|laid|napped)\s+(\w+\s+){0,5}(for\s+\d+\s*\w+|all\s+\w+|the\s+(whole|entire))\b/i', $text)
         // "discord chat for N hours" / "disocrd" typo
         || preg_match('/\b(disco?rd|discord|telegram|whatsapp|slack|teams)\s+(chat|server|channel|chatting|group|dms)\s+(\w+\s+){0,3}for\s+\d+\s*(h|hr|hrs|hour|hours|min|minute|minutes)\b/i', $text)
         // "flipped channels eating snacks for X hours"
         || preg_match('/\b(flipped|surfed)\s+channels\s+(\w+\s+){0,3}(eating\s+\w+|for\s+(an\s+hour|hours|\d+\s+\w+))\b/i', $text)
         // "followed feud comments / drama for hours"
         || preg_match('/\b(followed|read|spiraled|got\s+into)\s+(\w+\s+){0,2}(feud|drama|pile[\s\-]?on|stan\s+war|comment\s+(war|chain)|reaction\s+beef|cancel)\s+(\w+\s+){0,3}(for\s+\w+|all\s+\w+|til\s+\w+)\b/i', $text)
         // "lmao N hours of platform later"
         || preg_match('/\b(lmao|lol|wow|wtf|dang|damn)\s+(\d+\s*(h|hr|hrs|hour|hours))\s+of\s+(\w+\s+){0,2}(later|of\s+\w+\s+later|netflix|youtube|tiktok|reels|reddit|valorant|pubg|fortnite|gaming|scrolling)\b/i', $text)
         // "no homework / no work" SUFFIX after platform/duration
         || preg_match('/\b(\d+\s*(h|hr|hrs|hour|hours)|all\s+\w+|the\s+(whole|entire))\s+(of\s+\w+|on\s+\w+)\s+(\w+\s+){0,3}(no\s+(homework|work|sleep|study|reading|writing))\b/i', $text)
         // "still on chapter 1 after Nh" / "still no Y after Nh"
         || preg_match('/\b(still\s+(on\s+chapter\s+\d|no\s+(homework|work|study|sleep|reading|writing|progress)|nothing|on\s+my\s+list))\s+(\w+\s+){0,3}(after|for)\s+\d+\s*(h|hr|hrs|hour|hours)\b/i', $text)
         // "Nh later still no Y"
         || preg_match('/\b\d+\s*(h|hr|hrs|hour|hours)\s+later\s+(\w+\s+){0,3}still\s+(no|nothing|on\s+chapter)\b/i', $text)
         // "idea was to X idea died" / sarcastic "hype died"
         || preg_match('/\b(idea\s+was\s+to\s+\w+\s+idea\s+died|hype\s+died|nope\s+(binged|napped|gamed|scrolled)|hyped\s+\w+\s+(died|hype\s+died))\b/i', $text)
         // "Nh nap apparently"
         || preg_match('/\b\d+\s*(h|hr|hrs|hour|hours)\s+nap\s+(apparently|wow|lol|nope|instead)?\b/i', $text)
         // "still on my list"
         || preg_match('/\bstill\s+on\s+my\s+list\b/i', $text)
         // "about to start been about to start for N hours"
         || preg_match('/\babout\s+to\s+start\s+been\s+about\s+to\s+start\s+for\s+\d+\s*(h|hr|hrs|hour|hours)\b/i', $text)
         // "day flushed away" / "day vanished" / "day full of zero"
         || preg_match('/\bday\s+(flushed\s+away|vanished\s+\w+|full\s+of\s+zero|wasted\s+in\s+front|spent\s+scrolling|down\s+the\s+drain|just\s+gone|gone)\b/i', $text)
         // "just gone the entire day"
         || preg_match('/\bjust\s+gone\s+the\s+entire\s+day\b/i', $text)
         // "never reviewed/sent/wrote/practiced/etc." — failed-action negation
         || preg_match('/\bnever\s+(reviewed|sent|wrote|read|did|ran|exercised|completed|finished|practiced|studied|coded|opened|started|attended|made|got|drafted|published|shipped|filed|submitted|booked|paid|saved|trained|taught|prepared|organized|cooked|cleaned)\s+(\w+\s+){0,3}\w+\b/i', $text)
         // "made X never reviewed/used/saved/logged" — fake productive variants
         || preg_match('/\bmade\s+(a\s+|the\s+|fancy\s+|new\s+)?(\w+\s+){0,3}(never\s+(reviewed|used|opened|started|saved|logged|read|kept)|didnt\s+\w+|did\s+not\s+\w+|for\s+nonexistent)\b/i', $text)
         // "objective/mission was X failed/hit by"
         || preg_match('/\b(objective|mission|goal|aim|plan|intention|target)\s+(was|is)\s+\w+\s+(\w+\s+){0,3}(was\s+(failed|abandoned|killed|dead)|got\s+(failed|killed|abandoned|hit\s+by)|hit\s+by|failed\s+by|abandoned\s+for)\b/i', $text)
         // "X to be Y X failed" / "made up my mind to be Y and failed"
         || preg_match('/\b(made\s+up\s+my\s+mind\s+to|told\s+myself\s+to|determined\s+to|decided\s+to|committed\s+to)\s+(\w+\s+){0,3}(and\s+(immediately\s+)?(failed|fell\s+off|gave\s+up|caved)|but\s+(failed|fell\s+off|caved))\b/i', $text)
         // Typo platforms: netfilx, netfix
         || preg_match('/\b(netfilx|netfix|youtbue|insta\s*gram?|tiktoks?|reels?|reditt|reddit)\s+(\w+\s+){0,3}(took|ate|stole|burned|and\s+chill)\s+(\d+|the|all|my)\b/i', $text)
         // "X and chill for N hrs no Y"
         || preg_match('/\b(netflix|netfilx|netfix|youtube|hulu|disney)\s+and\s+chill\s+(\w+\s+){0,3}(for\s+\d+\s*(h|hr|hrs|hour|hours)|no\s+(studying|work|homework|sleep))\b/i', $text)
         // "pov: N hours of platform" — slang
         || preg_match('/\bpov:?\s+(\w+\s+){0,3}(\d+\s*(h|hr|hrs|hour|hours)\s+(on|of)\s+(\w+\s+){0,2}(tiktok|reels|reddit|netflix|youtube|gaming|scrolling)|zero\s+studying|nothing\s+done)\b/i', $text)
         // "X took N hours of my life" — passive entertainment
         || preg_match('/\b(netflix|netfilx|netfix|tiktok|reels|reddit|youtube|instagram|twitter|valorant|pubg|fortnite|minecraft|league|dota|csgo|gaming|scrolling)\s+(took|ate|stole|burned|consumed)\s+\d+\s*(h|hr|hrs|hour|hours)\s+of\s+my\s+(life|day|week|time)\b/i', $text)
         // "needed to X needed Y more apparently" — sarcastic
         || preg_match('/\bneeded\s+to\s+\w+\s+(needed|got|wanted)\s+(\w+\s+){0,2}(netflix|tiktok|reels|reddit|youtube|gaming|scrolling|nap|napping|nothing)\s+(\w+\s+){0,2}(more|apparently|instead)\b/i', $text)
         // "looking to procrastinate apparently and succeeded"
         || preg_match('/\b(looking\s+to|trying\s+to|going\s+to|hoping\s+to)\s+procrastinate\s+(\w+\s+){0,3}(apparently|and\s+succeeded|all\s+day|today)\b/i', $text)
         // "locked in then unlocked into Y"
         || preg_match('/\b(locked\s+in|focused|dialed\s+in)\s+(\w+\s+){0,3}(then\s+(unlocked|broke|drifted|caved|spiraled)\s+(into|to)|then\s+(scrolled|gamed|napped|watched))\b/i', $text)
         // "low effort low reward day wasted on Y"
         || preg_match('/\b(low|zero|no)\s+(effort|reward|productivity|output)\s+(\w+\s+){0,3}(low|zero|no)\s+(reward|output|effort|productivity)\s+(\w+\s+){0,3}(day|wasted|on)\b/i', $text)
         // Hangover/recovery patterns explicitly
         || preg_match('/\b(post[\s\-]?party\s+(doom|recovery|crash|blob)|party\s+(night\s+)?recovery\s+(slept|wasted|did\s+nothing)|party\s+recovery\s+wasted|recovery\s+day\s+(turned|with\s+no|on\s+couch)|recovery\s+from\s+\w+\s+(slept|did\s+nothing|scrolled|laid))\b/i', $text)
         // "napped at X and ruined sleep" / "napped through Y"
         || preg_match('/\b(napped|nap)\s+(at\s+\d+\s*(am|pm)|through\s+(\w+\s+){0,3}hours)\s+(\w+\s+){0,3}(and\s+ruined|important|the\s+(whole|entire))\b/i', $text)
         // "hit alarm and went back to sleep" / "hit snooze N times"
         || preg_match('/\b(hit\s+(the\s+)?alarm\s+and\s+(went\s+back\s+to\s+sleep|kept\s+(napping|sleeping))|hit\s+snooze\s+(\d+\s+times|nine\s+times|fifty\s+times))\b/i', $text)
         // "ate/finished X straight from tub for N hours watching Y"
         || preg_match('/\b(ate|finished|inhaled|polished|demolished|munched|binged)\s+(\w+\s+){0,8}(watching|while\s+watching|in\s+front\s+of)\s+(tv|netflix|youtube|reels|tiktok|reddit|instagram|twitch|hulu|disney|prime|hbo|shows|movies?|videos?)\b/i', $text)
         // "binged X during Y" — eating + scroll/episode
         || preg_match('/\b(binged|ate|finished|inhaled|polished)\s+(\w+\s+){0,5}(during|while|through)\s+(\w+\s+){0,3}(tiktok\s+scroll|tiktok|reels|netflix|youtube|reddit|instagram|stream|episode|movie|show|binge|marathon|tv|anime|kdrama|videos?)\b/i', $text)
         // "imho today was a write off" / "lol today was wasted"
         || preg_match('/\b(imho|imo|tbh|ngl|lol|lmao|fr\s+fr|deadass|cap)\s+(today|the\s+day|my\s+day|sunday|saturday|monday)\s+(was|is)\s+(a\s+)?(write[\s\-]?off|waste|wasted|trash|disaster|nothing|zero|fail)\b/i', $text)
         // "primed myself but stayed primed forever"
         || preg_match('/\b(primed|prepared|geared\s+up|psyched|hyped)\s+(myself|up)?\s+(\w+\s+){0,3}(but\s+stayed|but\s+never|never\s+(started|moved|came)|stayed\s+(primed|prepared|hyped)\s+forever|forever)\b/i', $text)
         // Failed-pledge patterns: "pledged to X and lasted Y minutes"
         || preg_match('/\b(pledged|swore|vowed|promised)\s+to\s+\w+\s+(\w+\s+){0,3}(and\s+lasted|but\s+lasted)\s+\d+\s*(min|minutes|seconds?)\s+(\w+\s+){0,2}(before|until)\s+(\w+\s+){0,2}(discord|tiktok|reddit|instagram|twitter|youtube|netflix|gaming|scrolling)\b/i', $text)
         // "mentally prepared and then mentally checked out"
         || preg_match('/\b(mentally\s+prepared|mentally\s+ready|mentally\s+set)\s+(\w+\s+){0,3}(and\s+then|but\s+then|then)\s+(\w+\s+){0,3}(mentally\s+checked\s+out|gave\s+up|caved|napped|scrolled)\b/i', $text)
         // "intended X didnt Y" — failed intent + bare didnt at end
         || preg_match('/\b(intended|planned|meant|wanted|tried)\s+to\s+\w+\s+(\w+\s+){0,3}didn\'?t\s+(\w+\s+){0,2}(at\s+all|today|properly|even|once|a\s+single)\b/i', $text)
         // Specific typos: "studided" / "wokring" etc.
         || preg_match('/\b(studided|wokring|stiudying|sutdied|stuyding|cleanign|coddding|writting|excerise|hommework)\s+(\w+\s+){0,3}(at\s+all|never|nothing|no|didnt|did\s+not)\b/i', $text)
         // "for hours" / "for an hour" anywhere when phrase has unproductive
         // markers that aren't otherwise caught
         || preg_match('/\b(chose|picked|configured|tweaked|fiddled\s+with|customized|adjusted|sorted|arranged|prepped|polished|optimized|set\s+up|tuned)\s+\w+\s+(\w+\s+){0,3}for\s+(an\s+hour|hours|\d+\s+\w+)\b/i', $text)
         // Bare "no X today/all day" for productive activities
         || preg_match('/\bno\s+(progress|chores?|homework|journaling|reading|writing|coding|exercise|studying|study|practice|focus|productivity|work|sleep|gym|workout|effort|output|movement|wins?|tasks?|essays?|drafts?|chapters?|pages?)\s+(\w+\s+){0,2}(today|all\s+day|done|completed|happened|on\s+the\s+\w+|on\s+\w+|tonight)?\b/i', $text)
         // Procrastination misspellings + slang descriptors
         || preg_match('/\b(procrastinaiton|procrastinted|procratinated|procrastinatin|procarstinated)\s+(level|the|all|today)\b/i', $text)
         || preg_match('/\b(procrastination\s+(level|champion|expert|master|professional)|procrastinating\s+(\w+\s+){0,2}(the\s+(whole|entire)|all\s+\w+))\b/i', $text)
         // "prolly the X day" / "the laziest day"
         || preg_match('/\b(prolly|probably|definitely|easily|literally|honestly)\s+the\s+(laziest|most\s+unproductive|worst|trash|nothing)\s+day\b/i', $text)
         // "promised id X ended up at Y"
         || preg_match('/\b(promised|swore|vowed)\s+\w+\s+\w+\s+(\w+\s+){0,3}(ended\s+up\s+at|ended\s+up\s+\w+ing|never\s+\w+ed)\s+(\w+\s+){0,3}(bakery|bar|club|netflix|mall|sofa|couch|bed|tiktok|reels|reddit|gaming)\b/i', $text)
         // "psyched myself up and then napped"
         || preg_match('/\b(psyched|hyped|geared|primed|prepared|amped)\s+(myself\s+)?(up|for)\s+(\w+\s+){0,3}and\s+then\s+(napped|nap|scrolled|gamed|watched|laid|stayed|crashed|nothing)\b/i', $text)
         // "read 2am subtweets" / drama at late hours
         || preg_match('/\bread\s+(\d+\s*(am|pm)\s+)?(subtweets|tweets|drama|threads|comments|qrt|quote\s+tweets|replies)\s+(\w+\s+){0,3}(and\s+(spiraled|got\s+sucked|wasted)|for\s+\w+|all\s+\w+)?\b/i', $text)
         // "read documentation/X for ages didnt implement"
         || preg_match('/\bread\s+(documentation|docs|the\s+\w+|tutorials?|guides?|articles?)\s+(\w+\s+){0,3}(for\s+(ages|hours|\d+\s+\w+)|all\s+\w+)\s+(didnt|did\s+not|never)\s+(implement|do|start|begin|build|act|apply)\b/i', $text)
         // "day unproductive front to back"
         || preg_match('/\bday\s+unproductive\s+(front\s+to\s+back|all\s+day|completely|totally|absolutely|fully)\b/i', $text)
         // "gunning for productivity not gunning hard enough"
         || preg_match('/\b(gunning|aiming|going|trying)\s+for\s+\w+\s+(\w+\s+){0,3}not\s+(gunning|aiming|going|trying)\s+hard\s+enough\b/i', $text)
         // "committed to be lazy and excelled"
         || preg_match('/\b(committed|pledged|determined)\s+to\s+be\s+(lazy|unproductive|wasted|nothing)\s+and\s+(excelled|succeeded|nailed)\b/i', $text)
         // "gave myself an hour to focus and gave it to youtube"
         || preg_match('/\bgave\s+(myself\s+)?(\w+\s+){0,3}(an\s+hour|\d+\s+\w+|the\s+(morning|afternoon|day|hour))\s+to\s+(focus|study|work|read|write|exercise|gym)\s+(\w+\s+){0,3}(and|but)\s+(gave\s+it\s+to|spent\s+it\s+on|gave\s+it\s+all)\s+(\w+\s+){0,2}(youtube|netflix|tiktok|reels|reddit|instagram|gaming|scrolling)\b/i', $text)
         // "hoping to be productive nope just napped"
         || preg_match('/\b(hoping\s+to\s+be|trying\s+to\s+be|wanting\s+to\s+be)\s+productive\s+(\w+\s+){0,3}(nope|nah|but\s+nope)\s+(\w+\s+){0,3}(just\s+(napped|scrolled|watched|gamed)|napped|scrolled|watched|gamed|binged)\b/i', $text)
         // "intended to read 2 chapters but watched anime til 4am"
         || preg_match('/\b(intended|planned|wanted|meant|tried)\s+to\s+(read|study|do)\s+\d+\s+\w+\s+(\w+\s+){0,3}but\s+(\w+\s+){0,3}(watched|binged|scrolled|gamed|napped)\s+\w+\s+(til\s+\d|until\s+\d|all\s+\w+|for\s+\w+)\b/i', $text)
         // "skipped X" / "skipped my Y" / "skipped Y entirely"
         || preg_match('/\bskipped\s+(\w+\s+){0,3}(every\s+task|the\s+\w+|my\s+\w+|completely|entirely|today|exercise|journaling|meditation|gym|workout|run|class|test\s+prep|prep\s+session|seminar|tutoring|study|reading|writing|coding|practice|homework|lunch|dinner|breakfast|morning\s+routine|evening\s+routine)\b/i', $text)
         // "intent + but/then PRECEDING productive pattern" — block productive
         // by re-routing to UNPRODUCTIVE when the intent is followed by
         // extended-duration entertainment regardless of productive count.
         // E.g. "told myself id read 100 pages but played minecraft for 8 hours"
         || (preg_match('/\b(told\s+myself|intended|planned|wanted|meant|tried|hoped|aimed|going|gonna|supposed|thought\s+i\s+would|decided|set\s+out)\s+(to\s+|id\s+|i\s+would\s+|i\'?d\s+)?\w+/i', $text)
             && preg_match('/\b(but|then|instead|ended\s+up)\s+(\w+\s+){0,5}(played|playing|gaming|gamed|scrolled|scrolling|watched|watching|binged|binging|streamed|streaming|doomscrolled|napped|napping|laid|lying)\s+(\w+\s+){0,5}(for\s+\d+\s*(h|hr|hrs|hour|hours|min|minute|minutes)|all\s+(day|night|morning|afternoon|evening|weekend)|til\s+\d+|until\s+\d+|the\s+(whole|entire))\b/i', $text))
         // "X for hours but only Y"
         || preg_match('/\b(told\s+myself|intended|planned|wanted|meant|tried)\s+(\w+\s+){0,3}for\s+\d+\s*(h|hr|hrs|hour|hours)\s+(\w+\s+){0,3}but\s+only\s+(scrolled|watched|gamed|binged|napped)\s+(\w+\s+){0,2}(tiktok|reels|reddit|netflix|youtube|twitter|instagram|valorant|pubg|fortnite|csgo)\b/i', $text)
         // "researched X didnt Y" without explicit "instead"
         || preg_match('/\bresearched\s+\w+\s+(\w+\s+){0,3}didnt\s+(build|do|implement|act|start|finish|read|use|apply|make)\b/i', $text)
         // "resolved/swore/vowed to X and Y" where Y is unproductive
         || preg_match('/\b(resolved|swore|vowed|promised|pledged|committed|determined)\s+to\s+\w+\s+(\w+\s+){0,3}(and|but)\s+(\w+\s+){0,3}(slept|napped|scrolled|gamed|watched|binged|laid|stayed|crashed|wasted|did\s+nothing)\b/i', $text)
         // "rip my productivity / huge fail / took the l"
         || preg_match('/\b(rip\s+my\s+productivity|took\s+the\s+l\s+on\s+productivity|huge\s+fail|massive\s+fail|epic\s+fail|big\s+fail|big\s+l|its\s+a\s+fail)\b/i', $text)
         // "rly/really disappointed in myself today"
         || preg_match('/\b(rly|really)\s+(disappointed|frustrated|upset)\s+in\s+(myself|today)\b/i', $text)
         // Day-name + waste/write-off/nothing
         || preg_match('/\b(monday|tuesday|wednesday|thursday|friday|saturday|sunday)\s+(a\s+)?(full\s+waste|write\s+off|writeoff|wasted|completely\s+wasted|gone|in\s+the\s+drain|trash|disaster|bust|nothing)\b/i', $text)
         // Typo platforms: tikok, tiktokd, tiwtter, scolled, scrooled, redit, twiter, instgaram, youtbe, youtbue, youtub, netfix
         || preg_match('/\b(tikok|tiktokd|tiwtter|scolled|scrooled|redit|twiter|instgaram|youtbe|youtbue|youtub|netfix|netfilx|hommework|insta\s+sotries|insta\s+scolling)\s+(\w+\s+){0,5}(for\s+\d+|hrs?|hours?|all|today|straight|cant|wasted|fest|i\s+need|until)\b/i', $text)
         // "tiktoks tiktoks tiktoks no homework" — repetition + failure
         || preg_match('/\b(tiktoks|reels|reddit|netflix|gaming)\s+(tiktoks|reels|reddit|netflix|gaming)\s+(tiktoks|reels|reddit|netflix|gaming)\s+(\w+\s+){0,2}no\s+(homework|work|study|sleep)\b/i', $text)
         // "tiktoking until i hated myself" / "tiktoks for hours i need help"
         || preg_match('/\b(tiktoking|scrolling|gaming|doomscrolling|browsing)\s+(\w+\s+){0,3}(until|til)\s+(\w+\s+){0,3}(hated\s+myself|i\s+hated|i\s+need\s+help|my\s+eyes\s+hurt|cant\s+\w+|i\s+(was|am)\s+sick)\b/i', $text)
         || preg_match('/\b(tiktoks?|reels|reddit|netflix|gaming|scrolling|doomscrolling)\s+for\s+\w+\s+(\w+\s+){0,3}(i\s+need\s+help|i\s+hate|cant\s+(believe|stop)|need\s+help)\b/i', $text)
         // "scrolled X to Y completely useless"
         || preg_match('/\bscrolled\s+\d+\s*(am|pm)\s+to\s+\d+\s*(am|pm)\s+(\w+\s+){0,2}(completely\s+useless|wasted|nothing|all\s+morning|all\s+afternoon)\b/i', $text)
         // "scrolled X then Y then Z" — multi-segment scrolling
         || preg_match('/\bscrolled\s+\d+\s*(h|hr|hrs|hour|hours|min|minutes?)\s+(\w+\s+){0,2}(then\s+(napped|watched|gamed|scrolled)\s+(\w+\s+){0,2}then\s+scrolled)\b/i', $text)
         // "X hours in one sitting" / "in one sitting"
         || preg_match('/\b\d+\s*(h|hr|hrs|hour|hours)\s+in\s+one\s+sitting\b/i', $text)
         // "set aside time and squandered every second on Y"
         || preg_match('/\b(set\s+aside|carved\s+out|allocated|earmarked|reserved)\s+(time|the\s+\w+|\w+)\s+(\w+\s+){0,3}(and|but|then)\s+(\w+\s+){0,3}(squandered|wasted|gave|filled|spent)\s+(\w+\s+){0,3}(on|to|with)\s+(\w+\s+){0,2}(memes|reels|tiktok|reddit|netflix|youtube|gaming|scrolling)\b/i', $text)
         // "stoked then immediately demotivated by twitter"
         || preg_match('/\b(stoked|hyped|psyched|amped|primed|excited|motivated)\s+(then|but)\s+(\w+\s+){0,3}(immediately|quickly|fast|right\s+away)\s+(demotivated|killed|crushed|lost\s+it|gave\s+up|caved)\s+(\w+\s+){0,3}(by|to|with)\s+(\w+\s+){0,2}(twitter|reddit|tiktok|reels|instagram|netflix|youtube|gaming|scrolling)\b/i', $text)
         // "straight up wasted N hours on Y"
         || preg_match('/\bstraight\s+up\s+(wasted|gone|nothing)\s+(\w+\s+){0,3}(\d+\s*hours?|today|on\s+\w+)\b/i', $text)
         // "supes lazy today watched X"
         || preg_match('/\b(supes|sus|lit)\s+(lazy|unproductive|wasted|trash)\s+(\w+\s+){0,3}(today|watched|all)\b/i', $text)
         // "swore id X but my brain said no" / "my brain said no and watched X"
         || preg_match('/\bmy\s+brain\s+said\s+no\s+(\w+\s+){0,3}(and|then)\s+(watched|scrolled|gamed|binged|napped)\b/i', $text)
         // "swore off X for N minutes oops"
         || preg_match('/\bswore\s+off\s+\w+\s+(for\s+\d+\s*(min|minutes|hours?))\s+(oops|lol|nope)\b/i', $text)
         // "talked myself out of X again"
         || preg_match('/\btalked\s+myself\s+out\s+of\s+(the\s+)?(\w+)\s+(again|today)\b/i', $text)
         // "the goal was X goal got crushed by Y"
         || preg_match('/\bthe\s+goal\s+was\s+\w+\s+(\w+\s+){0,3}goal\s+(got\s+crushed|died|never\s+came|failed)\s+(\w+\s+){0,3}(by|at)\s+(\w+\s+){0,2}(reels|tiktok|netflix|youtube|reddit|gaming|scrolling|cs2|csgo|valorant)\b/i', $text)
         // "the idea died at hour N of X"
         || preg_match('/\bthe\s+(idea|plan|goal|hope|intention)\s+died\s+(\w+\s+){0,3}(at\s+hour\s+\d|in\s+the\s+\w+|after\s+\d+)\s+of\s+(\w+\s+){0,2}(cs2|csgo|valorant|fortnite|netflix|youtube|tiktok|gaming|scrolling)\b/i', $text)
         // "the whole day passed and i did nothing"
         || preg_match('/\bthe\s+whole\s+day\s+passed\s+(and|but)\s+i\s+did\s+nothing\b/i', $text)
         // "thinking about productivity but only thinking"
         || preg_match('/\bthinking\s+about\s+\w+\s+(\w+\s+){0,2}(but|and)\s+only\s+thinking\b/i', $text)
         // "thought id just check twitter once turned into 2 hours"
         || preg_match('/\bthought\s+id\s+(just\s+)?check\s+\w+\s+once\s+(\w+\s+){0,3}turned\s+into\s+\d+\s*(hr|hrs|hour|hours)\b/i', $text)
         // "threw away another saturday" / "threw the entire day away"
         || preg_match('/\bthrew\s+(away\s+)?(another|the\s+(entire|whole))\s+\w+\s*(away)?\b/i', $text)
         // "took five/N breaks before starting work"
         || preg_match('/\btook\s+(five|\d+|several)\s+breaks\s+(\w+\s+){0,2}before\s+(starting|even|i)\b/i', $text)
         // "took a nap that turned into N hours"
         || preg_match('/\btook\s+a\s+nap\s+that\s+turned\s+into\s+\d+\s*(h|hr|hrs|hour|hours)\b/i', $text)
         // "twelve hours of phone today" / "20 mins on tiktok actually 2 hours"
         || preg_match('/\b(twelve|\d+|several)\s+hours?\s+of\s+phone\s+today\b/i', $text)
         || preg_match('/\b\d+\s*mins?\s+on\s+(tiktok|reels|reddit|netflix|youtube|instagram)\s+actually\s+\d+\s*(hr|hrs|hour|hours)\b/i', $text)
         // "useless day from start to end" / "useless full week" / "useless thursday gone"
         || preg_match('/\buseless\s+(day|full\s+week|thursday|monday|tuesday|wednesday|friday|saturday|sunday|morning|afternoon|evening)\s*(from\s+start|gone|overall|completely)?\b/i', $text)
         // "vowed to be productive vowed wrong"
         || preg_match('/\bvowed\s+to\s+be\s+\w+\s+vowed\s+wrong\b/i', $text)
         // "wanna X wanna Y more apparently" — slang failed intent
         || preg_match('/\b(wanna|gonna|finna)\s+\w+\s+(wanna|gonna|finna)\s+(nap|scroll|game|watch|tiktok|reels|netflix|youtube)\s+(\w+\s+){0,2}(more|apparently|instead|tho)\b/i', $text)
         // "really wanted to X really watched Y"
         || preg_match('/\breally\s+wanted\s+to\s+\w+\s+really\s+(watched|scrolled|gamed|binged|napped|did)\s+(\w+\s+){0,3}(anime|netflix|youtube|tiktok|reels|reddit|instagram|gaming|nothing)\b/i', $text)
         // "wated/wasted X hours on Y" with typo "wated"
         || preg_match('/\b(wated|waste|wasted)\s+\d+\s*(h|hr|hrs|hour|hours)\s+on\s+(\w+\s+){0,2}(tiktok|reels|reddit|netflix|youtube|twitter|instagram|gaming|scrolling)\b/i', $text)
         // "went through X box/pack during binge"
         || preg_match('/\bwent\s+through\s+(\w+\s+){0,3}(box|pack|bag|tub|tray|sleeve)\s+(\w+\s+){0,2}during\s+(\w+\s+){0,2}(binge|tv|netflix|youtube|tiktok|reels|movie|episode|stream|marathon)\b/i', $text)
         // "went through the day with nothing done"
         || preg_match('/\bwent\s+through\s+the\s+day\s+with\s+nothing\s+(done|productive|achieved)\b/i', $text)
         // "yt deep dive cant remember what for"
         || preg_match('/\b(yt|youtube|reddit|tiktok|netflix)\s+deep\s+dive\s+(\w+\s+){0,3}(cant\s+(remember|recall)|for\s+nothing|wasted|nothing)\b/i', $text)
         // "zero hours of focused work" / "zero output today" / "zero progress on anything"
         || preg_match('/\bzero\s+(hours?\s+of\s+\w+|output|pomodoros?|progress|focused|productivity|tasks?|wins?)\s+(\w+\s+){0,3}(today|completed|on\s+\w+|on\s+anything|on\s+the\s+\w+)?\b/i', $text)
         // "saturday a write off" / "sunday a write off"
         || preg_match('/\b(saturday|sunday|monday|tuesday|wednesday|thursday|friday)\s+a\s+write[\s\-]?off\b/i', $text)
         // "scheduled study time scheduled myself a nap apparently"
         || preg_match('/\bscheduled\s+(\w+\s+){0,3}(scheduled\s+myself|got\s+myself|gave\s+myself)\s+(a\s+)?(nap|scroll|game|break)\s+(apparently|instead|in\s+the\s+end)\b/i', $text)
         // "supposed to do X but binged Y"
         || preg_match('/\bsupposed\s+to\s+do\s+\w+\s+(\w+\s+){0,3}but\s+(binged|scrolled|gamed|watched|napped)\s+(\w+\s+){0,2}(netflix|netfix|youtube|tiktok|reels|reddit|instagram|gaming|csgo|pubg|valorant|fortnite|anime|kdrama)\b/i', $text)
         // "wanted to do bhajan/puja/X (cultural ritual) but watched Y instead"
         || preg_match('/\b(wanted|planned|meant|intended|tried)\s+to\s+do\s+(my\s+)?(bhajan|puja|prayer|prayers|namaz|tafseer|kirtan|qawwali|bhakti)\s+(\w+\s+){0,3}but\s+(\w+\s+){0,3}(watched|scrolled|gamed|binged|napped)\s+(\w+\s+){0,2}(netflix|netfix|youtube|tiktok|reels|reddit|instead|all)\b/i', $text)
         // "no homework completed" / "no problems solved today" / "no real productivity"
         || preg_match('/\bno\s+(homework|problems?|wins?|tasks?|productivity|work|sleep|study|reading|writing)\s+(completed|solved|done|today|achieved|happened|all\s+day|on\s+the\s+project|on\s+anything|real)\b/i', $text)
         // "nothing happened all day"
         || preg_match('/\bnothing\s+(happened|got\s+done|achieved)\s+(all\s+day|today)\b/i', $text)
         // "spent ages picking the right pen" / "stared at todo list for an hour"
         || preg_match('/\b(spent\s+(an\s+hour|hours|ages|two\s+hours|three\s+hours|\d+\s+hours)\s+(\w+\s+){0,3}(picking|choosing|deciding|finding|configuring|customizing|tweaking|fiddling|sorting|arranging)|stared\s+at\s+(the\s+|my\s+)?(todo|the\s+todo|my\s+todo|the\s+list|my\s+list|the\s+\w+\s+list|todo\s+list|cursor|page|screen|laptop|book|textbook)\s+(\w+\s+){0,2}(for\s+(an\s+hour|hours|\d+))?)\b/i', $text)
         // "no real productivity today" / "no real X today"
         || preg_match('/\bno\s+(real|actual|genuine|true|substantive)\s+(productivity|progress|work|achievement|wins?|tasks?|output)\s+(today|all\s+day|on\s+\w+|this\s+(morning|afternoon|day|week))?\b/i', $text)
         // "notification swipe down loop" / "toggled airplane mode pointlessly"
         || preg_match('/\b(notification\s+(swipe\s+down|swipe|pull\s+down)\s+loop|toggled\s+airplane\s+mode\s+(pointlessly|aimlessly|mindlessly))\s+(\w+\s+){0,3}(for\s+(an\s+hour|hours|\d+)|all\s+\w+)?\b/i', $text)
         // "napped through important hours of the day"
         || preg_match('/\bnapped\s+through\s+(important\s+(hours|times)|the\s+(day|morning|afternoon|evening)|\w+\s+hours\s+of\s+the\s+day)\b/i', $text)
         // "couch potato all day" — recovery couch
         || preg_match('/\b(couch\s+potato\s+(all\s+\w+|today)|day\s+wasted\s+on\s+couch|recovering\s+(from\s+\w+\s+)?on\s+couch|party\s+recovery\s+on\s+couch)\b/i', $text)
         // "emotional snacking through" / "stress snacking"
         || preg_match('/\b(emotional\s+(snacking|eating|chewing)|stress\s+(eating|snacking|chewing))\s+(through|while|during|all|the\s+(whole|entire))\s+(\w+\s+){0,3}(episode|movie|show|tv|netflix|youtube|tiktok|reels|stream|binge|marathon|day|afternoon|morning|evening)\b/i', $text)
         // "rip" / "smh" / "sigh" + productivity-fail
         || preg_match('/\b(rip|smh|sigh|oof|womp\s+womp)\s+(\w+\s+){0,4}(productivity|today|day|wasted|nothing|scrolling|gaming|netflix)\b/i', $text)) {
            return self::UNPRODUCTIVE;
        }
        return null;
    }

    /**
     * Intent-vs-action conflict pattern. Captures the user's reference
     * example "wanted to study but played game for hours so whole day got"
     * along with sibling forms.
     *
     * Returns a short human-readable reason if the pattern matches and
     * there is NO explicit resolving verdict (finished/shipped/wasted/etc),
     * otherwise null.
     */
    private function detectConflict(string $text): ?string
    {
        // Pattern A: stated intent ("wanted to X" / "tried to X" / ...) followed
        // by a contrastive connector. The verdict is open unless an explicit
        // resolution word closes it out.
        $intent = '/\b(wanted|tried|planned|meant|intended|hoped|aimed|going|gonna|supposed|thought i would|decided|told myself|said i would|set out|aiming|aimed)\s+(to\s+)?\w+/i';
        $contrast = '/\b(but|however|instead|then|ended up|wound up|kept|couldn\'?t|couldnt|didn\'?t|didnt)\b/i';
        $clearProductive = '/\b(finished|completed|shipped|delivered|nailed|crushed|got it done|done with|finished it|got the\s+\w+\s+done|powered through and|pushed through and|wrote\s+\d+|coded\s+(for|all)|completed every|completed all|crushed it|knocked out|wrapped up|finalized|submitted|got my|did the entire|did all|hit my|went and|pushed through|got everything done|attended and|made it through|resolved\s+(the|my|all|every)|replied\s+(to|and)|answered\s+\w+\s+(questions?|emails?)|fixed\s+(the|all|every)|handled\s+(the|all|every|it)|read\s+\d+\s+(pages?|chapters?)|read\s+(for\s+\d+|three|four|five|six|seven|eight|nine|ten|all|the\s+entire)|reviewed\s+(\d+|three|four|five)\s+(chapters?|sections?|files?|prs?|pull|pages?)|cooked\s+(a|the|breakfast|lunch|dinner|brunch)|practiced\s+(\w+\s+){0,3}for\s+\d+|wrote\s+(a|the|an|three|two|four|five|\d+)\s+(\w+\s+){0,4}(words|pages|chapters|articles|emails|essays?|posts?|\d+\s+words)|made\s+(a|the)\s+\w+\s+(meal|dinner|lunch|breakfast|prep|recipe)|did\s+the\s+(workout|run|gym|laundry|dishes|cleaning|prep|cook|meal\s+prep)\s+(anyway|anyhow)?|joined\s+(and|the)\s+\w+|got\s+(my|the)\s+\w+\s+(checkup|cleaning|done)|went\s+(to|in|for)\s+\w+\s+and\s+(\w+\s+){0,3}(crushed|finished|shipped|completed|did|got)|did\s+a\s+\d+\s*(h|hr|hour|hours?|min|minutes?|km|mile)\s+\w+|did\s+(\d+|three|four|five|six|seven|eight|nine|ten|two|an?)\s+hours?\s+of\s+(focused|deep|intensive|productive|study|work|coding|reading|writing|practice|exercise|gym|yoga|meditation|leetcode|dsa|review|revision)|did\s+a\s+(focused|deep|intensive|productive)\s+(\d+\s*(hour|hr|h)|\w+\s+hour)\s+(\w+\s+){0,5}(study|work|session|block|sprint|writing|coding|reading|practice|exercise|review|gym)|ran\s+(a\s+\d+\s*(km|mile)|\d+\s*(km|mile)|for\s+\d+\s*\w+)|went\s+(to\s+the\s+gym|on\s+a\s+\d+|for\s+a\s+\w+\s+(run|bike|hike|walk|swim))|watched\s+a\s+(\d+\s+hour|stanford|harvard|mit|cs\d+|productivity|technical|documentary|coding\s+tutorial|ml\s+course|bar\s+prep|cpa\s+exam|gre|mcat|interview\s+prep|live\s+coding|focused|deep\s+dive|3\s+hour|4\s+hour|5\s+hour|6\s+hour)\s+\w+|read\s+(\w+\s+){0,3}(research\s+papers?|the\s+textbook|technical\s+blog|\d+\s+pages|a\s+textbook|papers|the\s+novel)|tutored\s+\w+\s+(in|for)|mentored|taught\s+\w+|cleaned\s+(the\s+(entire|whole)|my\s+(entire|whole)|all\s+my)|cooked\s+(all|every|the\s+(week|whole))|meal\s+prepped|locked\s+in|deep\s+work\s+(block|session|sprint)|focused\s+(\d+\s*hour|study|work|coding|writing)|crushed\s+(\d+|the|every)|solved\s+\d+\s+\w+|practiced\s+(piano|guitar|violin|cello|drums|chess|leetcode|dsa)|ground\s+(out|through)|knocked\s+out\s+\d+|sat\s+down\s+and\s+(finished|crushed|did|wrote)|locked\s+in\s+(for|and)|jumped\s+into\s+a\s+\d+|dove\s+into|live\s+coding|side\s+project\s+(and|for|to))\b/i';
        // Action-side decisive unproductive markers — extended-duration
        // entertainment, "wasted/got wasted", or unambiguous time-suck
        // verbs after the contrast word. When the conflict pattern fires
        // AND any of these resolutions appear, the day was decisively
        // unproductive — return UNPRODUCTIVE rather than AMBIGUOUS.
        // Decisive unproductive markers — only patterns where the action
        // half is clearly time-suck. WEAKER signals like "stared at the
        // page" or "kept refreshing" stay ambiguous — the user can't tell
        // how the day actually went, so we shouldn't either.
        $clearUnproductive = '/\b(wasted|complete\s+waste|got\s+wasted|whole\s+day\s+wasted|nothing\s+got\s+done|did\s+(nothing\s+(useful|all\s+day|today)|absolutely\s+nothing)|all\s+day\s+on\s+(\w+)|stayed\s+up\s+(gaming|scrolling)|gamed\s+til|scrolled\s+til|binged.*for\s+\d|doomscrolled.*for\s+(hours|the|\d)|slept\s+(in\s+)?til\s+(noon|\d+|the\s+afternoon|the\s+evening|late|midnight|dawn|sunrise)|napped\s+(for\s+\d+\s*\w+|all\s+\w+|the\s+(whole|entire|afternoon|morning|evening))|laid\s+in\s+bed\s+(\w+\s+){0,3}(scrolling|watching|all\s+\w+|for\s+\d|for\s+hours|the\s+(whole|entire))|stayed\s+in\s+bed\s+(\w+\s+){0,3}(scrolling|watching|all|for\s+(hours|the))|hit\s+snooze\s+\d+\s+times|binged\s+(netflix|youtube|kdrama|anime|shows|tutorials)\s+(\w+\s+){0,3}(for\s+\d+|all|the\s+(whole|entire))|ordered\s+(takeout|food|uber\s+eats)\s+(and|then)\s+(watched|scrolled|gamed|binged))\b/i';
        // Reuse the same entertainment lexicons for in-conflict detection.
        $unprodAction = '/\b(scrolled|scrolling|doomscrolled|doomscrolling|watched|watching|binged|binging|gamed|gaming|played|playing|napped|napping|laid|lying)\s+(\w+\s+){0,5}(for\s+(hours|ages|the\s+whole|the\s+entire|\d+|one|two|three|four|five|six|seven|eight|nine|ten)|all\s+(day|night|afternoon|evening|morning|weekend)|til\s+\d|until\s+\d|the\s+(whole|entire)\s+(morning|afternoon|evening|day|night|week|weekend))\b/i';
        $unprodPlatform = '/\b(tiktok|reels|instagram|twitter|netflix|youtube|reddit|twitch|cod|pubg|fortnite|valorant|league|dota|csgo|minecraft|roblox|fifa)\b/i';
        $hasExtendedUnprodAction = preg_match($unprodAction, $text)
            || (preg_match($unprodPlatform, $text) && preg_match('/\bfor\s+(hours|ages|\d|one|two|three|four|five|six|seven|eight|nine|ten|the\s+(whole|entire))\b/i', $text));

        if (preg_match($intent, $text) && preg_match($contrast, $text)) {
            // Productive resolution beats AMBIGUOUS — the action half
            // contains a definitive completion verdict.
            if (preg_match($clearProductive, $text)) {
                return null; // detectClearVerdict will pick this up next
            }
            // Unproductive resolution: explicit waste verdict OR extended
            // unproductive activity. detectClearVerdict already catches
            // "got wasted" and most extended-entertainment patterns; for
            // the in-conflict variants (e.g. "tried to focus but
            // doomscrolled twitter for two hours"), defer so detectClear-
            // Verdict's broader regex set can fire.
            if (preg_match($clearUnproductive, $text) || $hasExtendedUnprodAction) {
                return null;
            }
            return 'You stated an intent that was contradicted by your action without a clear outcome — productive or wasted?';
        }

        // Pattern B: started/began X then Y, no clear winner.
        $startedRegex = '/\b(started|began|got into|opened|sat down to|was)\s+\w+/i';
        $shifted = '/\b(then|but|switched to|jumped to|drifted to|ended up|moved to|got on|got pulled into)\b/i';
        if (preg_match($startedRegex, $text) && preg_match($shifted, $text)) {
            if (preg_match($clearProductive, $text) || preg_match($clearUnproductive, $text)) {
                return null;
            }
            // Also defer if the "then" continuation has a strong productive
            // tail: "...then coded for 4 hours" / "...then finished the report"
            // — the productive part is large and decisive.
            if (preg_match('/\bthen\s+\w+\s+(for|the)\s+\w+\s+hour/i', $text)
             || preg_match('/\bthen\s+(coded|studied|finished|wrote|built|shipped)\b/i', $text)) {
                return null;
            }
            return 'Your activity shifted mid-flow without a clear outcome — was the day productive or wasted?';
        }

        return null;
    }

    /**
     * Phrases that trail off without a verdict ("whole day got", "ended up",
     * "kind of just"). The verdict word is literally missing — we can't
     * guess; we have to ask.
     */
    private function detectTruncation(string $text): ?string
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        // Exact half-thought phrases (short and definitively incomplete).
        $halfThoughts = [
            'whole day got', 'ended up', 'kind of just', 'spent the day',
            'the morning was', 'the afternoon was', 'mostly', 'sort of',
            'basically just', 'i guess i', 'the afternoon went',
            'today i kind of', 'not really sure what', 'just sort of',
            'didnt really', 'didn\'t really', 'ended up just',
        ];
        foreach ($halfThoughts as $h) {
            if ($text === $h) {
                return 'The phrase ends mid-thought — please describe how it actually went.';
            }
        }

        // Phrases ending on a dangling word that begs for a verdict.
        $tokens = preg_split('/\s+/', $text) ?: [];
        $last = end($tokens);
        $orphans = ['got', 'ended', 'kind', 'sort', 'mostly', 'maybe', 'somewhat', 'kinda', 'kindof'];
        if (count($tokens) <= 12 && in_array($last, $orphans, true)) {
            return 'The phrase trails off without saying how it went — please clarify.';
        }

        return null;
    }

    /**
     * Hedged self-reports — the user is explicitly UNSURE in their own
     * description ("not sure if", "kind of productive", "halfway"). We
     * mirror that uncertainty back as ambiguous.
     */
    /**
     * Vague short phrases with no decisive activity word. Captures things
     * like "the day kind of", "spent the morning", "sort of worked on" —
     * the speaker hasn't given enough signal to classify. Without this,
     * NB forces a binary guess and is wrong about half the time.
     */
    private function detectVagueShortPhrase(string $text): ?string
    {
        $tokens = preg_split('/\s+/', trim($text)) ?: [];
        $count = count($tokens);
        if ($count < 2 || $count > 8) {
            return null;
        }

        // Strategy: a phrase is vague when it's mostly filler/hedge words
        // with NO concrete activity. Rather than trying to enumerate every
        // possible content verb, we count tokens that are NOT in a small
        // closed set of fillers. If the phrase has at least 1 content
        // token, NB/verdict/lexicon get a shot; otherwise it's too vague.
        static $fillers = [
            'a', 'an', 'the', 'of', 'and', 'or', 'but', 'so',
            'i', 'me', 'my', 'we', 'you', 'it', 'this', 'that', 'today',
            'is', 'was', 'were', 'are', 'be', 'been', 'being', 'had',
            'have', 'has', 'do', 'did', 'done', 'doing', 'sat', 'sit',
            'sitting', 'stood', 'standing', 'lay', 'laid', 'lying',
            'kind', 'sort', 'mostly', 'maybe', 'somewhat', 'kinda',
            'kindof', 'just', 'really', 'pretty', 'fairly', 'quite',
            'spent', 'spending', 'time', 'while', 'awhile', 'moment',
            'moments', 'momentarily',
            'morning', 'afternoon', 'evening', 'day', 'night', 'today',
            'hour', 'hours', 'minute', 'minutes', 'mins', 'min',
            'thing', 'things', 'stuff', 'something', 'someday',
            'around', 'about', 'with', 'on', 'at', 'in', 'to', 'for',
            'some', 'few', 'lot', 'lots', 'bit', 'piece', 'couple',
            'guess', 'think', 'feel', 'felt', 'seems', 'seemed',
            'yesterday', 'tonight',
            'half', 'whole', 'entire', 'all',
            'ended', 'wound', 'turned', 'went', 'going',
            'sorta', 'kinda', 'somehow',
            'worked', 'working', 'work',
            'having', 'getting', 'going', 'coming', 'staying',
            'mostly', 'partly', 'partially',
            'up', 'down', 'over', 'across', 'through',
            'felt', 'seemed', 'looked', 'sounded',
            'noticed', 'realized', 'figured',
            // Time-passage / dissolving verbs and nouns ("the night kind of
            // slipped", "the morning was a blur"). These describe nothing
            // specific — pure time-passage or fog descriptors.
            'slipped', 'slid', 'drifted', 'wandered', 'dragged',
            'blurred', 'disappeared', 'melted', 'flew', 'floated',
            'dissolved', 'evaporated', 'vanished', 'faded', 'bled',
            'seeped', 'crept', 'snuck', 'oozed',
            'blur', 'haze', 'hazy', 'fuzzy', 'foggy', 'fog', 'vague',
            'dim', 'dull', 'mush', 'mushy', 'fluffy', 'wispy',
            'chunk', 'span', 'stretch', 'block', 'window',
            'happen', 'happened', 'happening', 'occur', 'occurred',
            'passing', 'passed', 'passes', 'pass',
            'stuff', 'things', 'whatever', 'something', 'anything', 'nothing',
            'meh', 'whatever',
            'ok', 'okay', 'fine', 'alright', 'decent', 'mid',
            'this', 'these', 'those',
            'between', 'among',
        ];

        $contentTokens = 0;
        foreach ($tokens as $t) {
            $clean = preg_replace('/[^a-z0-9]/', '', strtolower($t));
            if ($clean === '') continue;
            if (! in_array($clean, $fillers, true)) {
                $contentTokens++;
            }
        }

        // Even one content token is enough to let NB/verdict take a shot.
        if ($contentTokens >= 1) {
            return null;
        }

        return 'Your phrase doesn\'t describe a specific activity — please add detail.';

        // Legacy decisive lexicon (unused, kept for reference). Inverted
        // filler-based logic above proved more robust.
        $unusedDecisive = '/\b('
            // Productive verbs
            .'finished|completed|completing|shipped|shipping|delivered|delivering|nailed|crushed|deployed|deploying|deploy|implemented|implementing|implement|ran|running|run|submitted|submitting|submit|filed|filing|file|sent|sending|send|attended|attending|attend|led|leading|lead|called|calling|call|hosted|hosting|host|organized|organizing|organize|prepared|preparing|prepare|refactored|refactoring|refactor|debugged|debugging|debug|built|building|build|designed|designing|design|configured|configuring|configure|fixed|fixing|fix|optimized|optimizing|optimize|taught|teaching|teach|mentored|mentoring|mentor|drafted|drafting|draft|recorded|recording|record|edited|editing|edit|painted|painting|paint|sketched|sketching|sketch|sculpted|sculpting|animated|animating|illustrated|illustrating|coded|coding|code|studied|studying|study|reviewed|reviewing|review|wrote|writing|write|read|reading|practiced|practicing|practice|gym|workout|exercise|exercised|exercising|jog|jogging|jogged|cycling|cycled|swim|swimming|swam|leetcode|meditate|meditated|meditating|meditation|journaled|journaling|journal|cleaned|cleaning|clean|cooked|cooking|cook|prepped|prepping|prep|baked|baking|bake|hiked|hiking|swam|biked|biking|stretched|stretching|wrote|reviewed|attended|finished|booked|booking|book|renewed|renewing|renew|signed|signing|sign|completed|filed|paid|paying|pay|saved|saving|save|invested|investing|invest|interviewed|interviewing|interview|negotiated|negotiating|negotiate|presented|presenting|present|published|publishing|publish|launched|launching|launch|released|releasing|release|merged|merging|merge|pushed|pushing|push|pulled|pulling|pull|tested|testing|test|deployed|debugged|fixed|optimized|profiled|profiling|profile|migrated|migrating|migrate|upgraded|upgrading|upgrade|installed|installing|install|setup|configured|tuned|tuning|tune|invoiced|invoicing|invoice|tracked|tracking|track|measured|measuring|measure|analyzed|analyzing|analyze|researched|researching|research|wrote|tutored|tutoring|tutor|coached|coaching|coach|volunteered|volunteering|volunteer|served|serving|serve|welded|welding|weld|carved|carving|carve|knitted|knitting|knit|sewed|sewing|sew|crocheted|crocheting|crochet|sculpted|sculpting|sculpt|composed|composing|compose|chopped|chopping|chop|brewed|brewing|brew|fermented|fermenting|ferment|distilled|distilling|distill|installed|installing|repaired|repairing|repair|fitted|fitting|fit|painted|painted|sanded|sanding|sand|stained|staining|stain|polished|polishing|polish|tiled|tiling|tile|grouted|grouting|grout|caulked|caulking|caulk|mowed|mowing|mow|raked|raking|rake|weeded|weeding|weed|mulched|mulching|mulch|planted|planting|plant|harvested|harvesting|harvest|pruned|pruning|prune|composted|composting|compost|trained|training|train|squatted|squatting|deadlifted|deadlifting|benched|benching|pressed|pressing|drilled|drilling|drill'
            // Unproductive nouns / verbs
            .'|tiktok|reels|instagram|twitter|facebook|snapchat|discord|netflix|youtube|twitch|reddit|cod|pubg|fortnite|valorant|csgo|minecraft|roblox|fifa|gta|league|dota|hearthstone|genshin|hulu|prime|funimation|crunchyroll|hbo|disney|weibo|mastodon|bluesky|tumblr|9gag|imgur|threads|telegram|whatsapp|spotify|imdb|goodreads|amazon|aliexpress|shein|ebay|etsy|zillow|airbnb|kdrama|webtoon|stockx|grailed|depop|temu|wayfair|target|walmart|costco|ikea|sephora|ulta|nordstrom|asos|boohoo|shein|romwe|lululemon|nike|adidas|stockx|pinterest|linkedin|threads|qq|wechat|line|kakao|doomscrolled|doomscrolling|doomscroll|scrolled|scrolling|scroll|binged|binging|binge|gamed|gaming|game|wasted|hungover|napped|napping|nap|productive|unproductive|hopping|lurking|lurked|spiraled|spiraling|spiral|refreshing|refreshed|stalked|stalking|stalk|hangover|recovery'
            .')\b/i';
        if (preg_match($decisive, $text)) {
            return null;
        }

        // Phrase has no decisive content — it's vague. Filler words like
        // "kind of / sort of / mostly / spent / the day / the morning"
        // are common in this bucket; ANY 2-8 word phrase without
        // decisive content is too vague to classify.
        return 'Your phrase doesn\'t describe a specific activity — please add detail.';
    }

    private function detectHedge(string $text): ?string
    {
        if (preg_match('/\bnot sure if\b/i', $text)
         || preg_match('/\bkind of productive but\b/i', $text)
         || preg_match('/\bhalfway productive\b/i', $text)
         || preg_match('/\bcould have been (worse|better)\b/i', $text)
         || preg_match('/\bhard to (say|tell|gauge|read|score)\b/i', $text)
         || preg_match('/\bbit of \w+ and bit of\b/i', $text)
         || preg_match('/\bsome \w+ some \w+\b/i', $text)
         || preg_match('/\bhalf \w+ half \w+\b/i', $text)
         || preg_match('/\b(mid|medium|moderate|borderline|fuzzy|quasi|loose|soft|fairly|moderately|slightly)\s+(range|level|kind|sort|productive|day)\b/i', $text)
         || preg_match('/\b(decent|meh|ok|okay|fine|alright|so so)[\s\-]?ish\b/i', $text)
         || preg_match('/\b(might|maybe) have (done|been)\b/i', $text)
         || preg_match('/\b(neither|not) (here|exactly|quite) (nor|productive|either)\b/i', $text)
         || preg_match('/\b(mostly|kinda|sort of) (ok|okay|fine|alright|productive|useful)\b/i', $text)
         // Split-effort patterns — productive AND unproductive activity
         // each modified with a "small portion" qualifier and joined by
         // "and". Captures "studied a little and scrolled a little",
         // "coded some and gamed some", "worked a bit and slacked a bit".
         || preg_match('/\b(studied|coded|worked|read|wrote|practiced|exercised|ran|trained|focused)\s+(a\s+(little|bit)|some|a\s+while|briefly)\s+and\s+(scrolled|gamed|watched|browsed|napped|slacked|binged|doomscrolled|loafed)\s+(a\s+(little|bit)|some|a\s+while|briefly)\b/i', $text)
         || preg_match('/\b(scrolled|gamed|watched|browsed|napped|slacked|binged|doomscrolled|loafed)\s+(a\s+(little|bit)|some|a\s+while|briefly)\s+and\s+(studied|coded|worked|read|wrote|practiced|exercised|ran|trained|focused)\s+(a\s+(little|bit)|some|a\s+while|briefly)\b/i', $text)
         // Broader split-effort: any productive verb + "and" + clearly
         // unproductive activity OR vice versa. Catches "studied math and
         // watched memes", "did chores and watched videos", "wrote some
         // emails and watched videos".
         || preg_match('/\b(studied|coded|worked|read|wrote|writing|written|practiced|practicing|exercised|exercising|ran|running|trained|training|focused|focusing|journaled|journaling|cooked|cooking|baked|did|done|doing|completed|finished|reviewed|drafted|got|attended|joined|cleaned|prepped|revised|revising|edited|editing|stretched|stretching|wrote\s+\w+|wrote\s+a)\s+(\w+\s+){0,5}and\s+(\w+\s+){0,3}(scrolled|scrolling|gamed|gaming|played\s+a\s+game|watched|watching|browsed|browsing|napped|napping|binged|binging|doomscrolled|doomscrolling|stalked|stalking|texted|texting|procrastinated|procrastinating|ignored|opened\s+\w*\s*twitter|opened\s+\w*\s*reddit|opened\s+\w*\s*instagram|opened\s+\w*\s*tiktok|on\s+(tiktok|reddit|instagram|twitter|facebook|youtube|netflix|reels|shorts)|watched\s+(tv|netflix|shows|videos|reels|shorts|memes|tiktok|youtube|reddit|instagram|tweets?|reviews?|drama|comments?)|read\s+(tweets?|drama|memes?|comments?|reviews?|reels?|other\s+\w+|stack\s+overflow|forums?|threads?)|gamed|checked\s+(twitter|reddit|instagram|tiktok|github|email|phone|notifications)|scrolled\s+(tiktok|reels|reddit|instagram|twitter)|then\s+(scrolled|watched|gamed|nothing|napped|phone))\b/i', $text)
         // Reverse direction: unproductive then productive
         || preg_match('/\b(scrolled|scrolling|gamed|gaming|watched|watching|browsed|browsing|napped|napping|binged|binging|doomscrolled|doomscrolling)\s+(\w+\s+){0,4}and\s+(\w+\s+){0,2}(studied|coded|worked|read|wrote|practiced|exercised|ran|trained|focused|journaled|cooked|baked|did|completed|finished|reviewed|revised)\b/i', $text)
         // Mixed without specific verbs: "mixed practicing with videos"
         || preg_match('/\b(mixed|combined|alternated)\s+(\w+\s+){0,3}(with|and)\s+(\w+\s+){0,3}(videos?|reels?|shows?|tiktoks?|netflix|youtube|scrolling|gaming|watching|browsing)\b/i', $text)
         // "X and Y in turns" — alternation
         || preg_match('/\b(stretched|practiced|coded|studied|worked|wrote|read|did|exercised)\s+(\w+\s+){0,3}and\s+(watched|scrolled|gamed|browsed)\s+(\w+\s+){0,3}(in\s+turns|alternately|equally)\b/i', $text)
         // "practiced and procrastinated equally" — explicit balance signal
         || preg_match('/\b(\w+(ed|ing))\s+and\s+(\w+(ed|ing))\s+(equally|in\s+(equal|the\s+same)|side\s+by\s+side)\b/i', $text)
         // "felt iffy" / "iffy about" / "maybe i was X"
         || preg_match('/\b(felt\s+iffy|iffy\s+about|maybe\s+i\s+(was|did)|hopefully\s+\w+|could\s+go\s+either\s+way|one\s+of\s+those\s+(days|mornings|afternoons|evenings))\b/i', $text)
         // "felt X" hedge patterns — blurry/mixed/unclear/scattered etc.
         || preg_match('/\bfelt\s+(blurry|borderline|fuzzy|half[\s\-]?(assembled|clear|done|lived|productive|progress)?|indeterminate|indistinct|jumbled|mid|mixed|scattered|unclear|unresolved|unsure|undetermined|like\s+a\s+placeholder|like\s+productive\s+but|like\s+progress\s+but|fine\s+but\s+unsure|up\s+in\s+the\s+air|vague|productive\s+but\s+(maybe|not)|productive\s+but)\b/i', $text)
         // "hard to X" verdict-uncertainty patterns
         || preg_match('/\bhard\s+to\s+(assess|call|characterize|commit|decide|define|describe|evaluate|feel|give\s+(a\s+)?(number|verdict|grade)|grade|know|label|land|measure|put\s+(a|my)|verdict|tell|say|gauge|read|score|determine|judge|rate)\b/i', $text)
         // Short productive effort then unproductive switch — ambiguous
         // because the productive intent is real but brief.
         || preg_match('/\b(studied|read|coded|wrote|practiced|exercised|ran|trained|focused|journaled|did|gym|meditated|stretched|revised|reviewed|worked|swapped|toggled|alternated|drilled|tried|attempted)\s+(for\s+)?(\d+\s*(min|mins|minute|minutes|seconds?)|a\s+(minute|moment|second))\s+(\w+\s+){0,3}(then|but|and)\s+(\w+\s+){0,3}(phone|tiktok|reels|reddit|instagram|twitter|youtube|netflix|tv|scrolled|scrolling|gaming|gamed|sat\s+down|sat\s+for|napped|nap|nothing|memes|snacks?|snack|sofa|couch|bed|sleep|movies?|videos?|video|streams?|shorts?|game|break|reels|reddit|discord|chat|social|walked|walk)\b/i', $text)
         // "swapped between X and Y" — alternation
         || preg_match('/\b(swapped|alternated|toggled|switched|flipped)\s+between\s+(\w+ing|\w+)\s+and\s+(\w+ing|\w+)\b/i', $text)
         // "sort of half X" / "sort of vaguely X" / "sort of meh"
         || preg_match('/\bsort\s+of\s+(half\s+(a\s+)?(productive|wasted|done|useful|good|bad|unproductive|day)|vaguely|meh|maybe|done\s+sort|happened\s+sort|hovering|did\s+sort\s+of|accomplished\s+(a\s+tad|sort\s+of)|off\s+all|did)\b/i', $text)
         // "not totally sure / a wash / a win"
         || preg_match('/\bnot\s+totally\s+(sure|a\s+(wash|win)|certain)\b/i', $text)
         // "not sure today X" generic
         || preg_match('/\bnot\s+sure\s+today\s+(happened|landed|counted|stuck)\b/i', $text)
         // "opened the X then closed it" — abandonment
         || preg_match('/\bopened\s+the\s+(kata|planner|textbook|book|laptop|notes?|todo|inbox|email|cookbook|calendar|app|spreadsheet|doc|file|playlist)\s+then\s+closed\s+it\b/i', $text)
         // Single-word "spent" alone
         || (trim($text) === 'spent')
         // "wrote a poem and read others" / "X and read Y" mixed-output
         || preg_match('/\bwrote\s+a\s+(poem|verse|paragraph|chapter|line|draft)\s+and\s+(read|skimmed)\s+(others|tweets?|reels?|memes?|reviews?|drama|comments?)\b/i', $text)
         // "X some hours and Y some hours"
         || preg_match('/\b(\w+ed|\w+ing)\s+some\s+hours\s+and\s+(lost|wasted|gave|spent)\s+some\s+hours\b/i', $text)
         // "ran a bit and walked a bit" — non-decisive split
         || preg_match('/\b(\w+ed|\w+ing)\s+a\s+bit\s+and\s+(\w+ed|\w+ing)\s+a\s+bit\b/i', $text)
         // "intended to call mom but scrolled instead" / "intended to study but stared"
         // — these have intent + weak resolution markers; conflict already
         // returns ambiguous for these by default, but some have escape
         // patterns. Make sure these reach hedge as fallback if conflict
         // mis-routes them.
         || preg_match('/\b(intended|wanted|planned|meant|tried|told\s+myself|set\s+out)\s+to\s+(call|gym|study|work|read|write|code|focus|meditate|run|exercise)\s+(\w+\s+){0,5}(but|then|instead)\s+(\w+\s+){0,3}(scrolled\s+instead|stared\s+at\s+the|stared\s+at|opened\s+the\s+\w+\s+then|stayed\s+in\s+bed\s+\w+|kept\s+\w+\s+\w+|wandered)\b/i', $text)
         // "began writing then opened twitter for inspiration"
         || preg_match('/\bbegan\s+\w+ing\s+then\s+opened\s+(twitter|reddit|instagram|tiktok|youtube|netflix|reels)\s+for\s+(inspiration|ideas|a\s+break|rest)\b/i', $text)
         // "X with breaks and breaks with X" — circular split-effort
         || preg_match('/\b(\w+)\s+with\s+breaks\s+and\s+breaks\s+with\s+(\w+ing|\w+)/i', $text)
         // "X some and Y some" / "focused some and zoned out some"
         || preg_match('/\b(focused|coded|studied|worked|practiced|read|wrote|exercised|ran|trained|journaled)\s+(some|a\s+(little|bit))\s+and\s+(\w+\s+){0,2}(zoned\s+out|drifted|wandered|scrolled|gamed|watched|napped|loafed)\s+(some|a\s+(little|bit))\b/i', $text)
         // "focused on X and on Y" where Y is unproductive
         || preg_match('/\bfocused\s+on\s+(\w+\s+){0,2}and\s+on\s+(\w+\s+){0,2}(memes|reels|tiktok|reddit|netflix|youtube|twitter|instagram|nothing|distractions?|games)\b/i', $text)
         // "maybe productive maybe X" — uncertainty
         || preg_match('/\bmaybe\s+(productive|\w+)\s+maybe\s+(not|\w+)\b/i', $text)
         // "kind of mid in terms of productivity"
         || preg_match('/\bkind\s+of\s+(mid|mixed|nebulous|unclear|okay|fine|productive|done)\s+(\w+\s+){0,3}(in\s+terms|today|maybe|sort\s+of|wise)\b/i', $text)
         // "kind of nebulous" / "kind of unclear" / "kind of okay output"
         || preg_match('/\bkind\s+of\s+(nebulous|unclear|okay|mid|maybe|sort\s+of|productive\s+sort)\b/i', $text)
         // "maybe a bit productive" / "maybe got some stuff done"
         || preg_match('/\bmaybe\s+(a\s+bit|got\s+some|productive\s+in|productive\s+or|today\s+wasnt|it\s+was)\b/i', $text)
         // "5 out of 10" / "4 out of 10" — uncertain numeric grading
         || preg_match('/\b(maybe\s+)?(it\s+was\s+)?(a\s+)?(\d+|four|five|six|seven|eight)\s+out\s+of\s+(\d+|five|ten)\s+(day)?\b/i', $text)
         // "mixed X with Y" — split-effort with "with" connector
         || preg_match('/\bmixed\s+(\w+\s+){0,3}with\s+(\w+\s+){0,2}(games|memes|napping|chatting|scrolling|tiktok|reels|reddit|netflix|youtube|twitter|videos|tv|breaks?|reels|nothing|phone|distract)\b/i', $text)
         // "X minutes and Y minutes" — split time
         || preg_match('/\b(meditated|coded|studied|practiced|read|wrote|focused|exercised|ran|worked|journaled)\s+minutes\s+and\s+(scrolled|gamed|watched|napped|browsed|texted)\s+minutes\b/i', $text)
         // "X and Y in turns" / "in turns" alternation
         || preg_match('/\b(\w+ed)\s+and\s+(\w+ed)\s+(in\s+turns|alternately|equally)\b/i', $text)
         // "did some X for an hour with the family" — neutral mixed
         || preg_match('/\b(did|had)\s+some\s+(\w+\s+){0,3}(for\s+(an\s+hour|hours)|with\s+(the\s+)?(family|friends))\b/i', $text)
         // "began writing then opened X for inspiration" — creative-block ambiguous
         || preg_match('/\b(began|started|opened|sat\s+down\s+to)\s+\w+\s+(\w+\s+){0,3}(then\s+opened|then\s+\w+ed|then\s+sat)\s+(\w+\s+){0,2}(for\s+inspiration|for\s+ideas|for\s+a\s+break|for\s+rest)\b/i', $text)
         // "not really sure / not really clear" — hedge uncertainty
         || preg_match('/\bnot\s+(really\s+)?(sure|clear|certain|confident|able\s+to\s+say|able\s+to\s+tell)\s+(about|how|if|whether|i)\b/i', $text)
         // "not sure i can say/tell" / "not sure i counted today"
         || preg_match('/\bnot\s+sure\s+(i\s+can|i\s+counted|i\s+made|how|if|whether|of|about)\b/i', $text)
         // "mixed X with Y" — ANY mixed-with pattern (no need for Y to be unproductive)
         || preg_match('/\bmixed\s+(\w+\s+){1,3}with\s+(\w+\s+){0,2}\w+\b/i', $text)
         // "did some X for an hour with Y" — culturally-named neutral
         || preg_match('/\bdid\s+some\s+\w+\s+pe\s+\w+\s+(for|with)\s+(an\s+hour|hours|the\s+family|friends)\b/i', $text)
         // "did some X and some Y" where X/Y differ in productivity
         || preg_match('/\b(did|had)\s+(\w+\s+){0,2}(studying|coding|reading|practicing|exercise|gym|writing|focus|work)\s+and\s+(\w+\s+){0,2}(scrolling|gaming|watching|browsing|napping|binging|doomscrolling|tv|tiktok|reels|youtube|reddit|instagram)\b/i', $text)
         // "did some studying and some scrolling" / "did some X and some Y" symmetric
         || preg_match('/\bdid\s+some\s+\w+(ing)?\s+and\s+some\s+\w+(ing)?\b/i', $text)
         // "X for an hour and Y for an hour" — equal time productive/unproductive
         || preg_match('/\b(studied|coded|worked|read|wrote|practiced|exercised|ran|trained)\s+for\s+(an\s+hour|\d+\s+\w+|a\s+while)\s+and\s+(scrolled|gamed|watched|browsed|napped)\s+for\s+(an\s+hour|\d+\s+\w+|a\s+while)\b/i', $text)
         // Time-passage descriptors with no specific activity
         || preg_match('/\bthe\s+(morning|afternoon|evening|day|night|hours|time)\s+(was|got|just|kind of|sort of)\s+(\w+\s+){0,2}(blur|hazy|fuzzy|foggy|vague|fog|haze|mushy|chunk|span|stretch)\b/i', $text)
         || preg_match('/\bthe\s+(morning|afternoon|evening|day|night)\s+(kind\s+of|sort\s+of|just|mostly)?\s*(slipped|slid|drifted|wandered|dragged|blurred|disappeared|got\s+away|melted|flew|floated|dissolved|evaporated|vanished|faded|bled|crept|passed)\b/i', $text)
         // "in between" / "in the middle of productive and unproductive"
         || preg_match('/\b(in\s+between|in\s+the\s+middle|on\s+the\s+(fence|bubble))\b/i', $text)
         // "spent a chunk just being" / "spent some time"
         || preg_match('/\bspent\s+(a\s+(chunk|bit|while|moment|few)|some|the)\s+(\w+\s+){0,2}(just|kind\s+of|sort\s+of|mostly)?\s*(being|doing|having|sitting|sat|lying)\b/i', $text)
        ) {
            return 'Your description is hedged or undecided — please pick a label.';
        }
        return null;
    }

    /**
     * For 1–3 token inputs, NB's per-feature Gaussian variance washes out
     * the per-class mean, so we use a curated lexicon. If the short input
     * has tokens from BOTH lists, return AMBIGUOUS.
     */
    private function shortInputLexicon(array $tokens): ?string
    {
        static $productive = [
            'studied','studying','study','finished','completed','shipped',
            'deployed','coded','coding','code','debugged','reviewed',
            'planning','planned','journaling','journaled','meditate',
            'meditated','meditation','learn','learning','learnt','learned',
            'practice','practiced','practising','practicing','reading','read',
            'write','writing','wrote','built','build','designed',
            'organized','organised','mentored','taught','created',
            'researching','researched','worked','practised',
            'gym','workout','exercise','yoga','pilates','run','running',
            'jog','jogging','cycling','cycle','swimming','swim','leetcode',
            'focus','productive',
        ];
        static $unproductive = [
            'tiktok','reels','instagram','twitter','facebook','snapchat',
            'discord','doomscrolling','doomscroll','scroll','scrolled',
            'scrolling','binge','binged','binging','lazy','wasted',
            'procrastinated','procrastinating','hungover','netflix',
            'youtube','twitch','cod','pubg','unproductive','reddit',
        ];
        static $negators = ['no','not','never','didnt','skipped','skip','missed','avoided','instead','failed'];

        foreach ($tokens as $t) {
            if (in_array($t, $negators, true)) {
                return null;
            }
        }

        $hitsP = false; $hitsU = false;
        foreach ($tokens as $t) {
            if (in_array($t, $productive,   true)) $hitsP = true;
            if (in_array($t, $unproductive, true)) $hitsU = true;
        }

        if ($hitsP && $hitsU) return self::AMBIGUOUS;
        if ($hitsP) return self::PRODUCTIVE;
        if ($hitsU) return self::UNPRODUCTIVE;
        return null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Feedback loop
    // ─────────────────────────────────────────────────────────────────────────

    public function recordFeedback(string $text, string $expectedLabel): void
    {
        if (! in_array($expectedLabel, [self::PRODUCTIVE, self::UNPRODUCTIVE, self::AMBIGUOUS], true)) {
            throw new \InvalidArgumentException("Label must be productive, unproductive, or ambiguous.");
        }

        $extra = $this->loadFeedbackCorpus();
        $extra['samples'][] = (string) $text;
        $extra['labels'][]  = $expectedLabel;
        $this->saveFeedbackCorpus($extra);

        $this->train();
    }

    public function corpusStats(): array
    {
        $default = self::defaultCorpus();
        $extra   = $this->loadFeedbackCorpus();

        $byClass = ['productive' => 0, 'unproductive' => 0, 'ambiguous' => 0];
        foreach ($default['labels'] as $l) { $byClass[$l] = ($byClass[$l] ?? 0) + 1; }
        foreach ($extra['labels'] as $l)   { $byClass[$l] = ($byClass[$l] ?? 0) + 1; }

        return [
            'default_count'  => count($default['samples']),
            'feedback_count' => count($extra['samples']),
            'total'          => count($default['samples']) + count($extra['samples']),
            'by_class'       => $byClass,
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

        // Weight user feedback: each correction is duplicated 25× during
        // training so it can actually overcome a single misleading match
        // in the ~1900-entry base corpus. Without weighting, one feedback
        // sample is statistically invisible to Gaussian NB.
        $weight = 25;
        $samples = $base['samples'];
        $labels  = $base['labels'];
        foreach ($extra['samples'] as $i => $s) {
            for ($j = 0; $j < $weight; $j++) {
                $samples[] = $s;
                $labels[]  = $extra['labels'][$i];
            }
        }

        return [$samples, $labels];
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
