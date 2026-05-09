<?php

namespace App\Services;

/**
 * The training corpus for ActivityClassifierService.
 *
 * Targets ~500 productive + ~500 unproductive examples across every
 * realistic activity scenario a user might log:
 *   coding, studying, reading, writing, meetings, exercise, cooking,
 *   chores, hobbies, meditation, social media doomscrolling, video
 *   binging, gaming, procrastination, failed-intention patterns,
 *   double-negatives, mixed-sentiment sentences, and "wasted day"
 *   full-day reviews.
 *
 * Built as a mix of hand-crafted unique examples (for tricky patterns
 * the classifier MUST get right) and programmatic combinations across
 * verb/object vocab (for breadth and frequency mass).
 */
class ActivityClassifierCorpus
{
    public const PRODUCTIVE = 'productive';
    public const UNPRODUCTIVE = 'unproductive';
    public const AMBIGUOUS = 'ambiguous';

    /**
     * Returns the merged corpus in php-ml's expected shape:
     *   ['samples' => [...texts...], 'labels' => [...]]
     *
     * Three classes — productive / unproductive / ambiguous — each
     * de-duplicated case-insensitively. A phrase that ended up in more
     * than one class is dropped from all of them as contradictory data.
     */
    public static function all(): array
    {
        $productive   = self::dedupe(self::productive());
        $unproductive = self::dedupe(self::unproductive());
        $ambiguous    = self::dedupe(self::ambiguous());

        // Drop any phrase that appears in more than one class.
        $allClasses = [
            self::PRODUCTIVE   => $productive,
            self::UNPRODUCTIVE => $unproductive,
            self::AMBIGUOUS    => $ambiguous,
        ];
        $seen = [];
        $duplicated = [];
        foreach ($allClasses as $class) {
            foreach ($class as $text) {
                if (isset($seen[$text])) {
                    $duplicated[$text] = true;
                } else {
                    $seen[$text] = true;
                }
            }
        }
        if (! empty($duplicated)) {
            $bad = array_keys($duplicated);
            $productive   = array_values(array_diff($productive,   $bad));
            $unproductive = array_values(array_diff($unproductive, $bad));
            $ambiguous    = array_values(array_diff($ambiguous,    $bad));
        }

        return [
            'samples' => array_merge($productive, $unproductive, $ambiguous),
            'labels'  => array_merge(
                array_fill(0, count($productive),   self::PRODUCTIVE),
                array_fill(0, count($unproductive), self::UNPRODUCTIVE),
                array_fill(0, count($ambiguous),    self::AMBIGUOUS),
            ),
        ];
    }

    public static function counts(): array
    {
        $merged = self::all();
        $byClass = array_count_values($merged['labels']);
        return [
            'productive'   => $byClass[self::PRODUCTIVE]   ?? 0,
            'unproductive' => $byClass[self::UNPRODUCTIVE] ?? 0,
            'ambiguous'    => $byClass[self::AMBIGUOUS]    ?? 0,
            'total'        => count($merged['samples']),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Disk-backed seed loader
    //
    // The supplement seed files live in storage/app/classifier_corpus_seeds/
    // and are flat newline-separated lists. Keeping them on disk (rather
    // than inlined here) lets the corpus grow without bloating this file
    // beyond review-friendly size, and the dedupe logic in all() handles
    // any overlap with the inline lists below.
    // ─────────────────────────────────────────────────────────────────────

    private static function loadSeedFile(string $name): array
    {
        // storage_path() is unavailable when this class is loaded outside
        // a Laravel context (e.g. unit-style usage), so fall back to a
        // path relative to the app root.
        $base = function_exists('storage_path')
            ? storage_path('app/classifier_corpus_seeds')
            : __DIR__.'/../../storage/app/classifier_corpus_seeds';
        $path = $base.DIRECTORY_SEPARATOR.$name;
        if (! is_file($path)) {
            return [];
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            return [];
        }
        $lines = preg_split('/\r?\n/', $raw) ?: [];
        return array_values(array_filter(
            array_map('trim', $lines),
            fn ($l) => $l !== '' && ! str_starts_with($l, '#'),
        ));
    }

    private static function dedupe(array $texts): array
    {
        $normalised = array_map(fn ($t) => trim(mb_strtolower((string) $t)), $texts);
        return array_values(array_unique($normalised));
    }

    // ─────────────────────────────────────────────────────────────────────
    // PRODUCTIVE
    // ─────────────────────────────────────────────────────────────────────

    public static function productive(): array
    {
        return array_merge(
            self::pCoding(),
            self::pStudying(),
            self::pReading(),
            self::pWriting(),
            self::pMeetings(),
            self::pExercise(),
            self::pCooking(),
            self::pChores(),
            self::pPractice(),
            self::pMindfulness(),
            self::pCareer(),
            self::pHobbiesProductive(),
            self::pPersonalAdmin(),
            self::pSocialMeaningful(),
            self::pMixedPositive(),
            self::pDoubleNegativesPositive(),
            self::pShortPhrases(),
            self::pBareAnchors(),
            self::pSpentPatterns(),
            self::pAcademicPrep(),
            self::loadSeedFile('productive_supplement.txt'),
        );
    }

    /**
     * Ambiguous samples — entries the classifier should NOT decide
     * binarily. Sourced from a curated seed file covering 10 sub-buckets:
     * intent-vs-action contradictions without resolution, started-then-
     * shifted with unclear outcome, truncated half-thoughts, mixed-bag
     * without a clear ratio, hedged self-reports, productive-part-too-
     * short-to-count, vague self-reports, X-but-Y without a clear winner,
     * time-relative ambiguity, result-uncertain self-reflection.
     */
    public static function ambiguous(): array
    {
        return self::loadSeedFile('ambiguous_supplement.txt');
    }

    /**
     * Bare token anchors. Each high-signal productive verb/noun is repeated
     * standalone or near-standalone many times so the per-feature Gaussian
     * mean for that token in the productive class is firmly above the
     * unproductive class mean. Solves the "single-word input goes
     * unproductive" failure mode of Gaussian Naive Bayes on text.
     */
    private static function pBareAnchors(): array
    {
        $anchors = [
            'gym', 'studied', 'study', 'reading', 'writing', 'meditation',
            'leetcode', 'workout', 'running', 'jogging', 'cycling', 'swimming',
            'cooking', 'cleaning', 'practicing', 'learning', 'coding',
            'programming', 'reviewing', 'planning', 'journaling', 'focus',
            'shipped', 'finished', 'completed', 'practiced', 'meditated',
            'exercised', 'researched', 'documented', 'organized', 'mentored',
            'volunteered', 'taught', 'wrote', 'built', 'designed',
        ];
        // Each anchor appears 3 times in different short framings to push
        // the per-class mean for that token unambiguously toward productive.
        $framings = [
            '%s',                       // bare token — exactly the failure mode
            '%s today',                 // common log style
            '%s session',               // common log style
        ];
        $out = [];
        foreach ($anchors as $a) {
            foreach ($framings as $f) {
                $out[] = sprintf($f, $a);
            }
        }
        // Plus more verbose productive single-domain logs that re-emphasize
        // the same anchors with surrounding productive context.
        return array_merge($out, [
            'gym for an hour, full upper body routine',
            'studied for three hours uninterrupted',
            'studied chapter four with full attention',
            'reading session went well, finished a long article',
            'long writing session, three pages of progress',
            'meditation for thirty minutes followed by journaling',
            'meditation morning ritual feels grounding',
            'leetcode this morning, two hard problems solved',
            'workout was tough but rewarding',
            'workout completed, pushed past last week\'s numbers',
            'morning run, six kilometres in flow',
            'cycling along the canal, head clear',
            'cooking from scratch, used real ingredients',
            'cleaning the apartment thoroughly today',
            'practicing the new song over and over until clean',
            'learning the new framework fundamentals today',
            'coding in deep flow for two hours',
            'programming the side project after work',
            'reviewing the team\'s pull requests carefully',
            'planning the week with intention this morning',
            'journaling helped me process the busy week',
            'focus block from nine to noon, no interruptions',
            'shipped a clean change to production',
            'finished the difficult task ahead of schedule',
            'completed the chore list before lunch',
            'practiced typing accuracy and speed for half an hour',
            'meditated and felt much calmer afterwards',
            'exercised harder than usual, feeling proud',
            'researched the new vendor before the meeting',
            'documented the API changes thoroughly',
            'organized the digital filing system finally',
            'mentored a coworker through a tough debug',
            'volunteered at the soup kitchen this morning',
            'taught the kids how to code a simple game',
            'wrote a long heartfelt letter to a friend',
            'built the bookshelf and mounted it properly',
            'designed the new logo concept after research',
        ]);
    }

    /**
     * Productive variants of "spent X" patterns that previously bled
     * unproductive signal because the unproductive set has many
     * "spent the morning on tiktok" / "spent the day on netflix" forms.
     * Adding equally numerous "spent X productively" variants balances
     * the per-token signal for "spent", "morning", "afternoon", "hour".
     */
    private static function pSpentPatterns(): array
    {
        return [
            'spent the morning revising for the certification',
            'spent the morning studying linear algebra',
            'spent the morning writing the report',
            'spent the morning at the gym and then at the office',
            'spent the morning on the side project',
            'spent the morning meal prepping for the week',
            'spent the morning planning the next sprint',
            'spent the morning in deep work on the proposal',
            'spent the morning reading the new book',
            'spent the morning preparing for the exam',
            'spent the afternoon coding the new feature',
            'spent the afternoon studying for the midterm',
            'spent the afternoon at the library finishing the paper',
            'spent the afternoon cleaning and organizing',
            'spent the afternoon teaching the kids math',
            'spent the afternoon on a long focused run',
            'spent the afternoon writing the chapter',
            'spent the afternoon reviewing the architecture diagrams',
            'spent the afternoon practicing the speech',
            'spent the afternoon mentoring the junior',
            'spent the evening reading two long chapters',
            'spent the evening on the home repair project',
            'spent the evening practicing piano scales',
            'spent the evening journaling and reflecting',
            'spent the evening cooking a real dinner',
            'spent an hour with the textbook on probability',
            'spent an hour writing the cover letter',
            'spent an hour at the gym, full routine',
            'spent an hour debugging the production issue',
            'spent an hour reading the recommended paper',
            'spent an hour studying flashcards',
            'spent an hour practicing the new song',
            'spent an hour meditating before bed',
            'spent an hour planning the trip carefully',
            'spent an hour reviewing my finances',
            'spent two hours on the difficult algorithm',
            'spent two hours studying for the cybersecurity exam',
            'spent two hours writing the technical spec',
            'spent two hours at the gym for the workout class',
            'spent two hours practicing the presentation',
            'spent the day in deep work on the launch',
            'spent the day at the conference learning',
            'spent the day building the prototype',
            'spent the day with the team planning q2',
            'spent the day cooking and meal prepping for the week',
        ];
    }

    /**
     * Academic and exam-prep vocabulary. The held-out test exposed that
     * words like "gre", "mcat", "exam", "revising", "textbook",
     * "probability", "biology" weren't anchored to productive — Gaussian
     * NB couldn't lean on them because they were rare or absent.
     */
    private static function pAcademicPrep(): array
    {
        return [
            'revising for the gre exam',
            'revising for the mcat all morning',
            'revising for finals at the library',
            'revising biology for tomorrow\'s test',
            'revising chemistry for the upcoming exam',
            'revising calculus for the midterm',
            'revising physics from the textbook',
            'revising the lecture notes carefully',
            'revising for the certification before the exam',
            'gre prep — practice problems',
            'gre prep — vocabulary flashcards',
            'mcat prep with the official guide',
            'sat prep for the english section',
            'lsat prep — logic games practice',
            'gmat prep — quant section drills',
            'final exam prep all afternoon',
            'midterm prep with study group',
            'exam preparation before tomorrow',
            'prep session for the certification exam',
            'studied biology chapter on cell biology',
            'studied chemistry stoichiometry problems',
            'studied calculus integration techniques',
            'studied physics mechanics chapter',
            'studied probability and statistics homework',
            'studied linear algebra eigenvalues',
            'studied discrete math proofs',
            'studied microeconomics chapter four',
            'studied macroeconomics gdp section',
            'studied psychology cognition chapter',
            'studied history world war two unit',
            'textbook reading for tomorrow\'s class',
            'textbook problems for the homework set',
            'textbook chapter summary completed',
            'flashcards for the spanish vocabulary unit',
            'flashcards review on anki for thirty minutes',
            'review session for the upcoming probability test',
            'review session at the library before exams',
            'review session with study partner for finals',
            'practice problems for the chemistry exam',
            'practice problems for the math midterm',
            'practice problems on integration by parts',
            'practice exam attempted under timed conditions',
            'mock exam for the certification, scored well',
            'pomodoro session studying for the bar exam',
            'study group for the cpa preparation',
            'office hours with the professor on the proof',
            'tutoring session went well, finished the topic',
            'lecture review on the difficult section',
            'class notes review before tomorrow',
            'homework done before dinner',
            'assignment completed on time',
        ];
    }

    private static function pShortPhrases(): array
    {
        // Short logs balance out the unproductive short phrases so the
        // model doesn't learn that "any short input is unproductive".
        // Each high-signal productive token (gym, study, focus, code,
        // workout, run, learn, ship, build, write, read, practice, meditate)
        // is repeated in multiple short variants so the per-token posterior
        // for that word leans firmly productive.
        $singles = [
            'gym', 'gym today', 'gym done', 'gym session', 'morning gym', 'gym workout', 'gym for an hour',
            'study', 'studied', 'studied hard', 'studied a lot', 'study session', 'study time', 'studied today',
            'focus', 'focus day', 'focus time', 'focused', 'deep focus', 'focus mode',
            'code', 'coded', 'coding', 'coding session', 'wrote code', 'shipped code',
            'workout', 'workout done', 'good workout', 'morning workout', 'evening workout',
            'run', 'morning run', 'evening run', 'long run', 'easy run', 'recovery run',
            'reading', 'reading time', 'read book', 'book read', 'read a chapter',
            'writing', 'writing time', 'wrote', 'wrote draft', 'wrote a chapter',
            'practice', 'practiced', 'practice session', 'piano practice', 'guitar practice',
            'meditation', 'meditated', 'meditation time', 'morning meditation',
            'meal prep', 'meal prepped', 'cooked dinner', 'cooked lunch',
            'cleaned', 'cleaned room', 'cleaned the house', 'tidied up',
            'learning', 'learnt today', 'learned new thing', 'self study',
            'shipped', 'shipped today', 'shipped feature', 'shipped fix',
            'built', 'built today', 'built something', 'built feature',
            'productive', 'productive day', 'productive morning', 'productive evening',
            'great day', 'good day', 'solid day', 'win day', 'on track',
            'finished', 'finished work', 'finished task', 'finished homework',
            'workout day', 'training day', 'gym day',
            'leetcode', 'leetcode practice', 'leetcode session', 'solved leetcode',
            'journaled', 'journal entry', 'morning pages',
            'deep work', 'deep work block', 'deep work session',
            'crushed it', 'nailed it', 'great session',
            'goals hit', 'goals done', 'goals progress', 'goal achieved',
            'work done', 'task done', 'task complete',
            'focused work', 'real work', 'meaningful work',
        ];
        return array_values(array_unique($singles));
    }

    private static function pCoding(): array
    {
        $verbs = [
            'wrote', 'shipped', 'refactored', 'fixed', 'deployed', 'tested',
            'optimized', 'reviewed', 'merged', 'documented', 'debugged',
            'rolled out', 'migrated', 'scaffolded',
        ];
        $things = [
            'the api endpoint', 'unit tests for the user service',
            'the database migration', 'the auth middleware', 'the frontend component',
            'the payment integration', 'the search feature', 'the deploy pipeline',
            'a critical bug in production', 'the dashboard refactor',
            'the goal attribution service', 'a new admin page',
            'the email verification flow', 'the timezone selector',
            'the queue worker', 'the rate limiter',
        ];
        $out = [];
        foreach ($verbs as $v) {
            foreach ($things as $t) {
                $out[] = "$v $t";
            }
        }
        // Pick a manageable subset and add hand-crafted examples
        $out = array_slice($out, 0, 50);
        return array_merge($out, [
            'pair programmed with the team on the oauth flow',
            'deep work session on the quarterly planning doc',
            'spent two hours debugging the production cache issue',
            'finally figured out the race condition in the worker',
            'shipped a feature behind a flag and tested it on staging',
            'rolled out the security patch ahead of schedule',
            'wrote integration tests for the billing service',
            'cleaned up the legacy spaghetti in the user repository',
            'finished the weekly code review batch',
            'built a small cli tool to automate the release notes',
            'fixed two flaky tests that have been bugging the team',
            'paired on a tough type error for an hour and resolved it',
            'reviewed three pull requests with thoughtful comments',
            'worked through the architecture decision record draft',
        ]);
    }

    private static function pStudying(): array
    {
        $subjects = [
            'aws solutions architect', 'ceh module 12', 'system design',
            'discrete math', 'organic chemistry', 'linear algebra',
            'statistics homework', 'machine learning lecture notes',
            'cybersecurity reading', 'networking fundamentals',
            'algorithms chapter 4', 'database internals',
            'operating systems concepts', 'computer architecture',
            'software engineering principles',
        ];
        $verbs = ['studied', 'reviewed', 'practiced', 'worked through', 'finished', 'completed'];
        $out = [];
        foreach ($verbs as $v) {
            foreach ($subjects as $s) {
                $out[] = "$v $s";
            }
        }
        $out = array_slice($out, 0, 40);
        return array_merge($out, [
            'studied for the certification exam scheduled next week',
            'attended the online linear algebra lecture and took notes',
            'did the practice problems for chapter five',
            'rewatched the difficult section of the machine learning course',
            'flashcards for spanish vocab over morning coffee',
            'review session for the upcoming midterm',
            'finished the homework assignment ahead of the deadline',
            'worked through five leetcode mediums before lunch',
            'read the recommended paper for the journal club',
            'did labwork for the chemistry course',
            'finished the section on data structures and ran the exercises',
            'took the practice exam, scored well, reviewed the misses',
            'office hours with the professor on the proof',
            'study group session on calculus',
        ]);
    }

    private static function pReading(): array
    {
        return [
            'read three chapters of the system design book',
            'finished the philosophy paper i started yesterday',
            'read 40 pages of the biography on richard feynman',
            'morning reading session with the new architecture book',
            'read the latest technology newsletter',
            'finished the chapter on monetary policy',
            'read a long form article on distributed databases',
            'spent an hour with the deep work book',
            'reading session with atomic habits before bed',
            'read the case study on netflix migration',
            'caught up on the weekend reading list',
            'finished the book on stoicism i had been putting off',
            'read peer reviewed articles for the literature review',
            'morning paper read with coffee',
            'read three short stories from the collection',
            'long reading session on a quiet sunday afternoon',
            'finished a thoughtful essay on the future of ai',
            'read the user manual cover to cover before the trip',
            'caught up on the technical blog posts in my reader',
            'an hour with a book on cognitive psychology',
            'finished a chapter of the great gatsby',
            'read up on best practices for code review',
            'reading the manuscript draft and leaving margin notes',
            'spent the afternoon with a difficult math text and a notebook',
            'read 30 pages of the design patterns book',
            'finished the introduction and chapter one of pragmatic programmer',
            'read the latest issue of the magazine cover to cover',
            'reading session in the park during the lunch break',
            'finished the historical fiction novel finally',
            'one chapter of the biography before sleep',
        ];
    }

    private static function pWriting(): array
    {
        return [
            'wrote a draft of the proposal for the new project',
            'finished the weekly journal entry',
            'updated the project documentation with the new endpoints',
            'wrote a long form blog post on async patterns',
            'drafted the email to the stakeholders',
            'wrote tomorrow\'s priorities in the planner',
            'morning pages — three pages of stream of consciousness',
            'finished the technical spec for the new feature',
            'wrote the meeting notes and shared with the team',
            'drafted the resignation letter and reviewed twice',
            'wrote a thank you note to the mentor',
            'updated the readme with proper installation steps',
            'wrote the postmortem for the incident',
            'finished the cover letter for the new role',
            'long journaling session about the career pivot',
            'drafted the architecture decision record',
            'wrote a clear status update for the leadership team',
            'finished editing the chapter for the book',
            'wrote release notes for the v2 launch',
            'drafted the slide deck for the conference talk',
            'wrote a reflection on the past month',
            'wrote a detailed bug report with reproduction steps',
            'finished my response to the customer feedback survey',
            'drafted the rfc for the migration plan',
            'wrote the onboarding doc for new joiners',
        ];
    }

    private static function pMeetings(): array
    {
        return [
            'attended the design review and took action items',
            'one on one with my manager — productive conversation about goals',
            'team standup, all blockers cleared',
            'sprint planning meeting, scoped the next two weeks',
            'retro with the team, identified two real improvements',
            'discovery call with a potential customer, took good notes',
            'interviewed a strong candidate for the senior role',
            'mentoring session with the junior engineer',
            'cross team sync on the migration project',
            'product review with engineering leadership',
            'architecture meeting on the new service boundaries',
            'all hands meeting with the q3 results',
            'demoed the feature to the customer success team',
            'hiring panel interview, made a hire recommendation',
            'oncall handoff meeting, walked through open incidents',
            'technical interview with a strong candidate',
            'ux research interview with a real user',
            'meeting with the security team on threat modeling',
            'quarterly business review with the cfo',
            'whiteboard session on the new data model',
            'incident postmortem meeting, agreed on follow ups',
            'roadmap planning session for next quarter',
            'budget review with the team leads',
            'meeting with the legal team on the contract terms',
            'product strategy session with the founders',
            'kickoff meeting for the redesign project',
            'design crit on the new dashboard mockups',
            'security review for the new feature',
            'standup but i actually unblocked two teammates',
            'grooming session, refined the next sprint backlog',
            'syncing with the sre team on the slo definitions',
            'check in with the intern about their project',
        ];
    }

    private static function pExercise(): array
    {
        $verbs = ['ran', 'walked', 'cycled', 'swam', 'did', 'completed'];
        $things = [
            'a 5k run', 'a 10k run', 'a long bike ride',
            '40 lap swim', '30 minute walk', 'an hour of yoga',
            'leg day at the gym', 'arm day workout', 'chest and back routine',
            'core workout', 'hiit session', 'mobility flow',
        ];
        $out = [];
        foreach ($verbs as $v) {
            foreach ($things as $t) {
                $out[] = "$v $t";
            }
        }
        $out = array_slice($out, 0, 25);
        return array_merge($out, [
            'morning run before work, felt great',
            'gym session leg day, full routine completed',
            'swim laps at the community pool for 45 minutes',
            'long hike with friends on saturday',
            'pilates class at the studio',
            'cycled to work and back',
            'strength training session, all sets logged',
            'sunrise yoga on the balcony',
            'tennis match with the league',
            'badminton with friends in the morning',
            'climbed at the bouldering gym',
            'martial arts practice for two hours',
            'deadlift session with proper warmup',
            'physical therapy exercises as prescribed',
            'long walk around the lake after dinner',
            'sprint intervals on the track',
            'core circuit at home before breakfast',
            'crossfit class — all reps completed',
            'ran an easy recovery 3k',
            'kettlebell session in the garage',
            'swim drills focused on technique',
            'completed the half marathon training run',
            'evening walk with the dog and a podcast',
            'gym, focused on form not weight today',
            'morning stretch and foam rolling',
        ]);
    }

    private static function pCooking(): array
    {
        return [
            'cooked a proper dinner instead of ordering takeout',
            'meal prepped lunches for the entire week',
            'tried a new recipe and it actually worked out',
            'baked a loaf of bread from scratch',
            'made breakfast at home and packed lunch',
            'cooked a healthy stir fry with vegetables from the market',
            'spent the morning making homemade pasta',
            'prepped overnight oats for the week',
            'cooked the family recipe my grandma sent',
            'tried fermenting kimchi for the first time',
            'baked cookies for the office team',
            'made a giant pot of chili for the week',
            'sunday meal prep — five lunches and three dinners',
            'cooked a proper indian curry from scratch',
            'made breakfast for the family on saturday morning',
            'tried a new asian dish and nailed it',
            'baked a birthday cake for my kid',
            'cooked dinner with my partner together',
            'made a salad from greens i grew myself',
            'tried sourdough starter, day one of the experiment',
            'roasted a whole chicken for sunday dinner',
            'spent an hour learning a new knife technique',
            'cooked all my meals from scratch today',
            'cleaned out the fridge and cooked everything before it spoiled',
            'made a healthy smoothie and a real breakfast bowl',
        ];
    }

    private static function pChores(): array
    {
        return [
            'cleaned the apartment top to bottom',
            'did the laundry properly, folded and put away',
            'washed all the dishes and reorganized the kitchen',
            'mopped the floors and vacuumed the rugs',
            'cleaned the bathroom thoroughly',
            'reorganized the closet and donated old clothes',
            'put up the new shelves in the living room',
            'fixed the leaky faucet that has been bothering me',
            'mowed the lawn and trimmed the hedges',
            'washed the car at home',
            'tidied my desk and threw out old papers',
            'sorted through the mail and paid the bills',
            'cleaned out the inbox to zero',
            'organized the digital photo library',
            'changed the lightbulbs in the hallway',
            'did the grocery shopping for the week',
            'took the recycling out and broke down the boxes',
            'washed bedding and remade the bed',
            'replaced the air filter in the hvac',
            'cleaned the windows inside and out',
            'organized the garage finally',
            'fixed the squeaky door hinge with wd40',
            'set up the new bookshelf according to instructions',
            'cleaned the gutters before the rainy season',
            'cleared out the spam folder in my email',
            'updated my password manager and rotated weak ones',
            'backed up my computer and verified the backup',
            'cleaned the laptop screen and keyboard',
            'sorted through the boxes in the basement',
            'fixed the broken handle on the kitchen drawer',
        ];
    }

    private static function pPractice(): array
    {
        return [
            'practiced guitar scales for thirty minutes',
            'piano practice — etudes for an hour',
            'language practice on duolingo, finished the unit',
            'spanish practice with a tutor on italki',
            'mandarin tones drill with the textbook',
            'practiced the new song on bass',
            'voice practice — vocal warmups and a song',
            'sketching practice, gesture drawings for an hour',
            'painting practice with acrylics today',
            'wrote calligraphy practice sheets',
            'pottery class at the local studio',
            'photography practice — composition exercises',
            'leetcode practice — three problems before work',
            'whiteboard practice for the system design interview',
            'drum practice — rudiments for thirty minutes',
            'violin scales and one piece, full concentration',
            'practiced cooking knife skills with onions',
            'typing practice on keybr to improve speed',
            'practiced the speech for the wedding toast',
            'mock interview with a friend over zoom',
            'practiced the demo for tomorrow until it was smooth',
            'language practice — conversation with a partner',
            'public speaking practice in front of the mirror',
            'practiced the new yoga pose carefully',
            'puzzle practice — sudoku and logic puzzles',
            'chess study with a coach',
            'practiced the magic tricks for the kids',
            'pencil sketching from a still life setup',
            'practiced playing scales in all twelve keys',
            'hand lettering practice with the brush pens',
        ];
    }

    private static function pMindfulness(): array
    {
        return [
            'meditation for twenty minutes after waking up',
            'evening meditation session before bed',
            'guided meditation on the headspace app',
            'silent meditation for an hour',
            'breathwork session in the morning',
            'mindful walk in the park without my phone',
            'gratitude journaling — three things this morning',
            'reflection journaling about the week',
            'quiet time without any screens',
            'planned the week and set three priorities',
            'long therapy session with my counselor',
            'spent thirty minutes just thinking, no screens',
            'deep breathing exercises after a stressful call',
            'yoga and meditation combined for the morning routine',
            'wrote my goals for the next quarter',
            'reviewed the past month and noted lessons learned',
            'made a long term plan for my career',
            'self assessment with my mentor',
            'reflected on what i want to improve next',
            'started the day with intention setting',
            'evening reflection on what went well',
            'unstructured thinking time about a problem',
            'sat with a difficult emotion and journaled about it',
            'breathing exercise during a tough moment',
            'gratitude practice — wrote ten things i am thankful for',
            'mindfulness session with the partner',
            'long bath without my phone',
            'spent a quiet morning reading and reflecting',
            'planned tomorrow before going to bed',
        ];
    }

    private static function pCareer(): array
    {
        return [
            'updated my resume with the latest projects',
            'applied to three jobs that match my skillset',
            'reached out to a recruiter about a senior role',
            'networking call with a former colleague',
            'finished the take home assignment for the interview',
            'prepped for tomorrow\'s technical interview',
            'updated my linkedin with recent achievements',
            'asked for feedback from my manager and acted on it',
            'wrote a strong cover letter for the new role',
            'researched the company before the interview',
            'practiced behavioral interview questions out loud',
            'sent thank you emails after all my interviews',
            'finished the certification exam — passed',
            'enrolled in a course relevant to my growth area',
            'mentored a junior on their career path',
            'finished a difficult conversation about a raise',
            'long career planning session with my coach',
            'wrote my self review for the performance cycle',
            'salary negotiation conversation, came out positive',
            'set up a learning roadmap for the next year',
        ];
    }

    private static function pHobbiesProductive(): array
    {
        return [
            'worked on the side project for two hours',
            'made progress on the novel i am writing',
            'wrote a chapter of my book on the train',
            'soldered together the kit i bought last month',
            'built the model airplane and painted it',
            'finished the woodworking project, sanded and stained',
            'gardened — planted the new vegetables',
            'pruned the rose bushes properly',
            'restored the old bicycle in the garage',
            'fixed the watch i had been meaning to repair',
            'sewed the buttons back on three shirts',
            'knitted another row on the scarf',
            'crochet practice with the new yarn',
            'painted the bedroom wall the new color',
            'designed a new logo concept for the side project',
            'recorded a track for the home music studio',
            'mixed the song i wrote last week',
            '3d printed the case for the project',
            'worked on the mechanical keyboard build',
            'assembled the new desk and cleaned up',
            'finished the puzzle that has been on the table',
            'rebuilt the carburetor on the old motorbike',
            'started a new sourdough loaf, kneaded properly',
            'started building the treehouse for the kids',
            'soldered the led panel for the art piece',
            'photo editing session, processed the trip pictures',
            'recorded a podcast episode with a friend',
            'worked on the open source project i maintain',
            'built a chess opening repertoire over the morning',
            'finished a new digital painting in procreate',
        ];
    }

    private static function pPersonalAdmin(): array
    {
        return [
            'filed taxes for the year',
            'reconciled the monthly budget',
            'reviewed all bank statements for the month',
            'sorted through paperwork and shredded what i don\'t need',
            'set up automatic bill pay for utilities',
            'reviewed insurance policies and renewed the auto plan',
            'set up new investment account for the kids',
            'rebalanced my retirement portfolio',
            'wrote my will draft and sent to lawyer',
            'set up a savings goal for the trip next year',
            'reviewed last quarter\'s spending against the budget',
            'paid off the credit card balance in full',
            'set up the new health savings account',
            'updated emergency contacts in all the apps',
            'organized all medical records into one folder',
            'scheduled the dentist appointment i had been delaying',
            'made the dr appointment for the annual checkup',
            'renewed the passport before it expired',
            'updated my address with all relevant accounts',
            'scheduled the car service before the long road trip',
        ];
    }

    private static function pSocialMeaningful(): array
    {
        return [
            'long phone call with my parents, caught up properly',
            'dinner with old friends, no phones at the table',
            'volunteered at the food bank for three hours',
            'mentored a student through a tough problem',
            'helped my neighbor with their home repair',
            'taught my kid how to ride a bike',
            'read with my child for an hour before bed',
            'helped a friend move into their new place',
            'donated time at the animal shelter',
            'wrote a long letter to a friend overseas',
            'family game night, no screens, lots of laughter',
            'walked with my partner without phones',
            'helped a coworker prepare for their interview',
            'organized a study group for the certification exam',
            'cooked a meal for a friend who is going through a tough time',
            'spent quality time with my grandma',
            'hosted a thoughtful dinner with neighbors',
            'visited a sick relative at the hospital',
            'attended the parent teacher conference fully present',
            'long heartfelt conversation with my partner',
        ];
    }

    private static function pMixedPositive(): array
    {
        return [
            'played pubg for 20 mins as a break then coded for 4 hours',
            'scrolled instagram for 5 mins then finished the report',
            'watched one episode of netflix then went back to studying',
            'started doomscrolling but caught myself and went for a run',
            'distracted by twitter for 10 minutes then deep work for two hours',
            'morning was slow but got a solid afternoon of focused work',
            'lazy start to the day but ended with a productive evening session',
            'youtube for half an hour while eating then back to writing',
            'broke for tiktok briefly but finished the assignment on time',
            'commute scrolling but at home shipped the feature',
            'gaming break for 15 minutes then back to debugging',
            'reels at lunch but nailed the afternoon presentation',
            'bad morning, recovered with a focused four hour study block',
            'snacking and scrolling but finished the hard task before bed',
            'started by watching a movie then transitioned to coding the side project',
            'phone for an hour in the morning but a great gym session followed',
            'mindless youtube during breakfast then full attention on the proposal',
            'scrolled while waking up then meditated and started the day right',
            'bit of facebook then shipped the patch on time',
            'lazy breakfast watching tv then a productive afternoon',
            'distracted easily today but the deep work block delivered',
            'snacks and netflix until noon, then a strong evening study session',
            'broke discipline for an hour but recovered and got the work done',
            'almost gave up but got back to the task and finished it',
            'a slow start does not mean a wasted day — finished strong',
            'fell into reels for 20 mins then straight back to the report',
            'commute on the phone but at the office crushed the sprint goal',
            'tv on in the background but i was knitting and reading the manual',
            'phone broke my flow once, regained it and finished the chapter',
            'slipped into youtube but only for a moment, the rest was work',
        ];
    }

    private static function pDoubleNegativesPositive(): array
    {
        return [
            'not wasted, actually finished the assignment',
            'far from a wasted day — shipped two features and worked out',
            'no doomscrolling today, all focused work',
            'avoided the phone all morning and got real work done',
            'didn\'t waste a minute, every hour accounted for',
            'didn\'t binge anything, finished the project early',
            'no random youtube, just the lecture and notes',
            'not idle — read three chapters and journaled',
            'kept tiktok closed, finished the report on time',
            'no wasted hours today, fully focused',
            'instead of doomscrolling i read a real book for an hour',
            'didn\'t even open instagram today',
            'no procrastination — started the hardest task first',
            'didn\'t default to netflix, watched a documentary instead',
            'didn\'t game today, finished the side project milestone',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // UNPRODUCTIVE
    // ─────────────────────────────────────────────────────────────────────

    public static function unproductive(): array
    {
        return array_merge(
            self::uSocialMedia(),
            self::uVideoBinge(),
            self::uMindlessGaming(),
            self::uAimlessBrowsing(),
            self::uProcrastination(),
            self::uPhone(),
            self::uOnlineDrama(),
            self::uFailedIntentions(),
            self::uWastedDay(),
            self::uIdleNapping(),
            self::uMindlessSnacking(),
            self::uPartyingExcessive(),
            self::uImpulseShopping(),
            self::uComplaining(),
            self::uTrickyDeceptive(),
            self::uExtraDoomscroll(),
            self::uExtraMindless(),
            self::uShortPhrases(),
            self::loadSeedFile('unproductive_supplement.txt'),
        );
    }

    private static function uExtraDoomscroll(): array
    {
        return [
            'spent the morning watching reaction videos',
            'fell into the comments section of a random video for an hour',
            'watched the same drama clips over and over',
            'kept opening tiktok every five minutes',
            'instagram explore page rabbit hole',
            'youtube recommendations took me down a stupid path',
            'kept refreshing the feed waiting for new posts',
            'lurked on twitter without reading anything substantial',
            'watched dancing videos on tiktok all morning',
            'pranks compilation for two hours',
            'random fail compilation while ignoring my work',
            'opened reddit, three hours later i still hadn\'t done anything',
            'celebrity gossip rabbit hole on twitter',
            'kept opening snapchat with nothing to see',
            'watched the same meme account for an hour',
            'random youtube channel rabbit hole, no purpose',
            'lurked in a niche subreddit for hours',
            'instagram stories on autoplay for the morning',
            'every five minutes another tiktok',
            'spent the bus ride doomscrolling and arrived dazed',
            'kept hitting next on instagram reels for the entire commute',
            'watched the same shorts repeat in different forms',
            'fell into a comments black hole on a random article',
            'kept seeking the next dopamine hit on the feed',
            'mindless reels marathon while pretending to relax',
            'spent the lunch hour deep in social feeds',
            'kept tapping through stories with no interest',
            'tiktok fyp for the whole afternoon',
            'youtube live chat lurking with nothing to say',
            'spent the morning on twitter trends i didn\'t care about',
        ];
    }

    private static function uExtraMindless(): array
    {
        return [
            'just zoned out on the couch for hours',
            'stared at the ceiling unable to start anything',
            'lay in bed thinking about everything i should be doing',
            'kept turning the tv on and off, never watching anything fully',
            'mindlessly clicked around the desktop for an hour',
            'watched the cursor blink while the to do list stared back',
            'tabbed between netflix and youtube unable to decide',
            'opened and closed the same five apps in rotation',
            'paced around the apartment without purpose',
            'sat at the desk doing absolutely nothing for hours',
            'flipped through tv channels for an hour',
            'opened a book, read one paragraph, opened my phone',
            'just dissociated through the whole afternoon',
            'lay in the dark listening to background noise',
            'wandered the kitchen looking for snacks i didn\'t need',
            'kept opening the laptop without doing anything',
            'sat in the car staring at the steering wheel',
            'kept toggling between rooms doing nothing',
            'opened the fridge ten times for no reason',
            'looked out the window for an hour',
            'spaced out at my desk for the whole morning',
            'too tired to start anything but too restless to rest',
            'kept telling myself i\'d start in five minutes for hours',
            'fell into a haze of nothingness for the day',
            'low energy lethargy all day, unproductive',
            'in autopilot mode all day, accomplished nothing',
            'kept standing up to do something then sitting back down',
            'mindless habits all morning, no real activity',
            'made the same coffee three times to delay starting',
            'reorganized the same drawer twice as a stalling tactic',
        ];
    }

    private static function uShortPhrases(): array
    {
        // Very short logs — common when users are tired and just type
        // a word or two. The classifier should still pick up the signal.
        return [
            'wasted',
            'wasted day',
            'wasted morning',
            'wasted afternoon',
            'wasted evening',
            'lazy day',
            'lazy morning',
            'doomscroll',
            'doomscrolling',
            'tiktok',
            'instagram',
            'reels',
            'youtube binge',
            'netflix binge',
            'gaming all day',
            'mindless scrolling',
            'phone all day',
            'in bed all day',
            'napping',
            'long nap',
            'hungover',
            'procrastinated',
            'just procrastinated',
            'distracted all day',
            'browsing aimlessly',
            'twitter scroll',
            'reddit hole',
            'youtube hole',
            'tv all day',
            'movie marathon',
            'gaming bender',
            'binge',
            'binge watched',
            'hours on the phone',
            'screen time too high',
            'no work done',
            'unproductive',
            'completely unproductive',
            'totally wasted',
            'distraction all day',
        ];
    }

    private static function uSocialMedia(): array
    {
        $platforms = [
            'instagram', 'tiktok', 'reels', 'twitter', 'x', 'reddit',
            'facebook', 'snapchat', 'youtube shorts', 'discord',
        ];
        $verbs = [
            'doomscrolling', 'mindlessly scrolling', 'wasted hours on',
            'lost in', 'scrolled', 'spent the morning on',
            'spent the evening on', 'rabbit hole on', 'down the rabbit hole on',
            'all afternoon on', 'three hours of',
        ];
        $out = [];
        foreach ($verbs as $v) {
            foreach ($platforms as $p) {
                $out[] = "$v $p";
            }
        }
        $out = array_slice($out, 0, 70);
        return array_merge($out, [
            'doomscrolling reels for hours after waking up',
            'lost track of time on instagram, two hours gone',
            'tiktok all morning, did nothing else',
            'reddit rabbit hole, lost the entire afternoon',
            'kept refreshing twitter for no reason',
            'three hours on youtube shorts, can\'t recall any of them',
            'facebook feed for the whole evening',
            'fell into the discord drama all night',
            'instagram stories one after another for an hour',
            'snapchat all day with no purpose',
        ]);
    }

    private static function uVideoBinge(): array
    {
        return [
            'binge watched netflix all afternoon',
            'six episodes of the new series back to back',
            'rewatched the entire season instead of working',
            'youtube autoplay for three hours',
            'twitch stream for the whole evening',
            'watched random anime episodes one after another',
            'movie marathon on saturday, lost the day',
            'binged the entire show in one sitting',
            'youtube videos in bed until 2am',
            'kept clicking on suggested videos for hours',
            'watched random gameplay videos for the morning',
            'reaction videos rabbit hole all night',
            'documentary marathon while doing nothing else',
            'rewatched friends episodes for the fifth time',
            'watched a four hour video essay i didn\'t need',
            'twitch from breakfast through dinner',
            'kept watching the next episode autoplay',
            'three movies in a row instead of studying',
            'random vlogs on the channel for two hours',
            'watched the same show on repeat in the background',
            'long live stream that i didn\'t actually care about',
            'fell asleep with youtube playing on autoplay',
            'kept watching even though i was bored',
            'movie night turned into a five hour session',
            'watched random tv all afternoon and felt empty',
            'serial killer documentary marathon',
            'whole sunday lost to a netflix binge',
            'kept hitting next episode well past bedtime',
            'rewatched a movie i\'ve seen a dozen times',
            'random gameplay walkthroughs for hours',
        ];
    }

    private static function uMindlessGaming(): array
    {
        return [
            'gaming session on cod, six hours straight',
            'pubg all night until 4am',
            'fortnite for the whole afternoon',
            'random match after match on valorant',
            'genshin impact daily turned into 5 hours',
            'mobile game ranked grind, no end in sight',
            'apex legends from morning to evening',
            'mindless rocket league grind',
            'spent the day grinding levels in the rpg',
            'gaming bender on the weekend, did nothing else',
            'lol queue all night, lost track of time',
            'started one game, ended up playing five hours',
            'minecraft session that turned into the whole evening',
            'dota match after match, way too long',
            'stardew valley until the sun came up',
            'cs2 grind for ranks all afternoon',
            'mobile gacha pulls and grinding for hours',
            'spent the morning on a mobile game i don\'t even like',
            'open world game side quests instead of real work',
            'replayed the same level in the platformer for hours',
            'stayed up gaming until 3am on a work night',
            'kept playing even after i wasn\'t enjoying it',
            'one more match turned into ten more matches',
            'gaming all weekend, didn\'t leave the house',
            'pvp grind from breakfast through midnight',
            'started a new save and lost the whole day to it',
            'random co op session that ate the evening',
            'mobile game that just kept asking for daily check ins',
            'slot machine vibes on the gacha all morning',
            'auto battler for hours, can\'t remember a single match',
        ];
    }

    private static function uAimlessBrowsing(): array
    {
        return [
            'aimless browsing of news sites for an hour',
            'kept clicking random wikipedia links',
            'browsed shopping sites with nothing to buy',
            'forum lurking with no contribution',
            'spent the morning on hacker news comments',
            'random clicking through news headlines',
            'lost time looking at memes',
            'just refreshing reddit out of habit',
            'aimless wandering through the app store',
            'browsed real estate listings i can\'t afford',
            'looked at flights to places i\'m not going',
            'kept refreshing the same email page',
            'flipped through online catalogs for nothing',
            'looked at car listings i\'m not buying',
            'browsed forums about a hobby i don\'t even have',
            'youtube comments rabbit hole on a random video',
            'spent the afternoon on niche subreddit i barely care about',
            'looked at houses on zillow for hours',
            'aimless scroll through reviews for products i won\'t buy',
            'tab hoarding that i never came back to',
            'browsed reddit for hours about something irrelevant',
            'amazon window shopping for an hour',
            'pinterest aimlessly for half the morning',
            'kept hitting next on a random photo gallery',
            'wandered through random forums on the internet',
            'browsed celebrity news sites for an hour',
            'random ebay listings for stuff i don\'t want',
            'spent the day looking at travel blogs but not booking anything',
            'aimless on youtube even after i found what i wanted',
            'looked at github profiles of strangers for an hour',
        ];
    }

    private static function uProcrastination(): array
    {
        return [
            'procrastinated on the report all day',
            'kept finding new tasks instead of doing the important one',
            'reorganized the desk to avoid actual work',
            'cleaned the kitchen instead of writing the essay',
            'made tea four times to avoid starting',
            'opened the document and immediately closed it',
            'kept switching tabs to avoid the hard problem',
            'planned the planning instead of doing the planning',
            'researched productivity instead of being productive',
            'watched videos about how to focus instead of focusing',
            'rewrote my todo list for the third time today',
            'set up a new app instead of doing the work',
            'kept saying i would start in five minutes',
            'put off the email for the entire day',
            'avoided the difficult task with busywork',
            'spent the morning on tiny easy tasks instead of the big one',
            'opened the assignment and stared at it for an hour',
            'browsed productivity apps instead of being productive',
            'made coffee three times before sitting down',
            'kept doing dishes to avoid writing the report',
            'snack runs to avoid the spreadsheet',
            'reorganized the bookshelf instead of starting the book',
            'spent two hours preparing to start',
            'researched the perfect setup instead of working',
            'switched note apps for the third time this week',
            'walked around the house pretending to be busy',
            'every time i sat down i found something else to do',
            'avoided the hardest meeting prep by answering trivial emails',
            'tried to start but kept finding excuses',
            'opened the file every hour without making progress',
        ];
    }

    private static function uPhone(): array
    {
        return [
            'on the phone for hours with nothing to show',
            'screen time hit eight hours and i don\'t know how',
            'phone in hand all day, no productive use',
            'mindless phone unlocks every five minutes',
            'kept reaching for the phone every minute',
            'phone first thing in the morning, lost an hour',
            'in bed scrolling for ninety minutes before getting up',
            'lay in bed scrolling instead of working',
            'long shower, then phone for hours, basically a wasted day',
            'phone glued to my hand from morning to evening',
            'checked notifications obsessively all day',
            'kept reading the same instagram stories on loop',
            'phone in the bathroom for forty minutes',
            'fell asleep with the phone on my chest',
            'doomscrolled for two hours straight',
            'lockscreen check turned into half an hour',
            'phone usage today felt completely out of control',
            'screen time exceeded the daily limit i set',
            'opened apps with no real purpose all day',
            'app switching with no goal for the whole afternoon',
        ];
    }

    private static function uOnlineDrama(): array
    {
        return [
            'argued with strangers in twitter replies for two hours',
            'discord drama for the entire night',
            'reddit comments fight that ate the morning',
            'long emotional argument in the comments section',
            'getting baited into political fights online',
            'stayed up arguing with someone i\'ll never meet',
            'kept refreshing the comment thread to see new replies',
            'engaged with a troll for too long',
            'spent the day arguing about a tv show online',
            'wasted an hour responding to a hate comment',
            'fell into a flame war on a forum',
            'engaged in pointless debate on twitter all morning',
            'replied to a hostile comment three times',
            'argued in a fandom subreddit for hours',
            'kept hitting refresh waiting for new replies to my hot take',
        ];
    }

    private static function uFailedIntentions(): array
    {
        return [
            'planned to study but ended up napping and binging youtube',
            'thought i would code today, just procrastinated and watched twitch',
            'meant to gym, lay in bed scrolling instead, complete waste',
            'was going to read but ended up on tiktok',
            'opened the laptop to work, three hours later still on instagram',
            'said i would clean, never started',
            'planned a productive morning, lost it to phone',
            'wanted to write, ended up watching anime',
            'set out to run, never left the house',
            'meant to focus on the report, watched random videos instead',
            'was going to call my parents, scrolled through reels instead',
            'tried to study, ended up on youtube',
            'said today would be different, it wasn\'t',
            'morning intention was to deep work, became a phone day',
            'wanted to make progress, made none',
            'was going to meditate, doomscrolled instead',
            'meant to take a 15 minute break, gone for 3 hours',
            'planned to journal, just rewatched old videos',
            'set the timer to start, never started',
            'opened the textbook, closed it, opened netflix',
            'thought i would meal prep, ate cereal and watched tv',
            'was going to do laundry, lay on the couch',
            'told myself one episode, watched five',
            'wrote a perfect schedule, followed none of it',
            'planned a morning workout, slept through',
            'meant to wake up early, slept until noon',
            'said i would only check messages, kept scrolling',
            'planned a focus block, opened twitter instead',
            'wanted to learn the new framework, watched gameplay videos',
            'was supposed to read, mindlessly browsed instead',
        ];
    }

    private static function uWastedDay(): array
    {
        return [
            'wake up and eat breakfast and roaming in the end whole day got wasted',
            'whole day got wasted on nothing productive',
            'looking back the day was a complete waste',
            'just a wasted sunday',
            'entire saturday gone with nothing to show',
            'today was a wasted day',
            'completely unproductive day',
            'a day i\'ll never get back',
            'felt empty all day, did nothing meaningful',
            'wasted afternoon on nothing in particular',
            'morning gone to phone, afternoon gone to tv, complete waste',
            'this whole weekend was a write off',
            'lost the day to one thing after another',
            'somehow it\'s already evening and i did nothing',
            'feels like i wasted today',
            'unproductive from start to finish',
            'a whole day of bad habits',
            'lost the entire week to lazy mornings',
            'today is just another wasted day to add to the pile',
            'didn\'t accomplish a single thing today',
            'felt useless all day',
            'looking at my screen time, the day was a waste',
            'started the day with phone, never recovered',
            'didn\'t even get dressed today, no productivity',
            'completely lazy day, regret how i spent it',
            'a sunday lost to bad habits',
            'all i did today was scroll and snack',
            'today was a series of distractions',
            'really wish i had spent today better',
            'felt like a zombie all day, no work done',
        ];
    }

    private static function uIdleNapping(): array
    {
        return [
            'napped for four hours in the middle of the day',
            'fell asleep on the couch and lost the afternoon',
            'unplanned 3 hour nap, woke up groggy',
            'lay on the bed for hours doing nothing',
            'kept hitting snooze until noon',
            'in bed all day, no excuse',
            'napping on and off for the entire afternoon',
            'long nap that became a sleep, ruined my night',
            'lay around the house all morning',
            'on the couch staring at the ceiling for hours',
            'too tired to do anything, just lay there',
            'morning nap turned into a 4 hour sleep',
            'fell asleep watching tv, woke up at midnight',
            'extended my nap and missed the afternoon',
            'in bed scrolling, fell asleep, woke up scrolling',
            'an hour nap that became three',
            'napped on the couch with the show playing',
            'didn\'t even mean to nap, lost the day',
            'kept lying on the bed, never got up',
            'all day in pyjamas on the couch',
        ];
    }

    private static function uMindlessSnacking(): array
    {
        return [
            'mindless snacking in front of the tv all evening',
            'ate a whole bag of chips while scrolling',
            'kept eating without thinking',
            'three sodas and a bag of cookies in front of the show',
            'binge ate during the show',
            'ate junk food the whole afternoon',
            'walked to the kitchen for snacks every twenty minutes',
            'ate way too much delivery food while watching tv',
            'snacked through the entire workday',
            'ordered takeout three times in one day',
            'kept opening the fridge with no purpose',
            'ate ice cream at midnight while doomscrolling',
            'whole pizza alone watching netflix',
            'kept munching while playing the game',
            'snack runs every commercial break',
        ];
    }

    private static function uPartyingExcessive(): array
    {
        return [
            'hungover from too much drinking last night',
            'whole sunday wasted recovering from saturday',
            'drank too much, did nothing the next day',
            'lost the day to a hangover',
            'partied all night, lost the next day',
            'too tired from the bender to function',
            'couldn\'t do anything because of last night',
            'fell asleep at noon because of the late night out',
            'wasted weekend on bars and recovery',
            'three days lost to the festival aftermath',
            'still recovering from the bachelor party',
            'too drunk to do anything useful',
            'spent the whole day in bed because of last night',
            'felt awful all day after the party',
            'unproductive due to last night\'s drinking',
        ];
    }

    private static function uImpulseShopping(): array
    {
        return [
            'impulse spent two hours on amazon buying junk',
            'wasted the morning ordering things i don\'t need',
            'spent the afternoon comparing things i won\'t buy',
            'kept adding to cart and checking out random stuff',
            'doom shopping on temu for hours',
            'aliexpress rabbit hole for the evening',
            'three hours on shein with nothing meaningful',
            'flash sale browsing that ate the day',
            'window shopping online for stuff i can\'t afford',
            'kept getting suckered by ads and clicking through',
            'spent two hours looking at watches i won\'t buy',
            'shopping dopamine spiral all evening',
            'amazon recommendations rabbit hole',
            'wishlist grew but nothing useful happened',
            'late night impulse buys i\'ll regret',
        ];
    }

    private static function uComplaining(): array
    {
        return [
            'complained on the phone for two hours',
            'long ranting session about a coworker, no resolution',
            'vented to my partner for an hour and went in circles',
            'gossiped about colleagues all afternoon',
            'rant texts back and forth all day',
            'long phone call where we just complained',
            'spiraled about news for two hours',
            'doom and gloom phone calls all evening',
            'rage scrolling through political twitter',
            'complained loudly without taking action',
            'argued in the family group chat all afternoon',
            'long complaint thread with friends online',
            'spent the morning whining about work',
            'fed into someone else\'s drama for hours',
            'pity party with no resolution',
        ];
    }

    private static function uTrickyDeceptive(): array
    {
        // Examples that look productive but are actually unproductive,
        // or that contain productive-sounding words while describing waste.
        return [
            'reorganized my todo list for the third time and did nothing on it',
            'set a timer to focus and immediately checked instagram',
            'opened the textbook and watched gameplay videos for two hours',
            'started a new bullet journal instead of doing the assignment',
            'spent the day researching productivity instead of being productive',
            'watched a four hour video about discipline while procrastinating',
            'long shower thinking about the work without doing it',
            'made a perfect schedule, followed none of it',
            'cleaned the desk to avoid sitting at it',
            'researched the best note app instead of taking notes',
            'wrote a beautiful plan for tomorrow, today is wasted',
            'studied how to study, didn\'t actually study',
            'spent two hours building a habit tracker, broke every habit',
            'organized my notes to avoid reading them',
            'wrote a meditation reminder, then doomscrolled',
        ];
    }
}
