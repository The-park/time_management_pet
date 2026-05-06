<?php

namespace App\Services;

/**
 * Auto-seeds a list of keywords from a goal's title (and optional
 * description). The user can edit the result, but most goals match
 * against the title alone and don't need manual tuning.
 *
 * Strategy:
 *   1. Lowercase + strip punctuation, then tokenize.
 *   2. Drop stopwords + generic goal-shell words ("complete", "exam"…).
 *   3. Keep both single tokens and high-signal bigrams (e.g.
 *      "ethical hacker" survives even though "ethical" alone might
 *      otherwise be dropped).
 *   4. Deduplicate, preserve original ordering.
 */
class GoalKeywordExtractor
{
    private const STOPWORDS = [
        // generic English
        'a','an','the','of','in','on','at','for','to','with','by','from','and',
        'or','but','if','then','else','as','is','are','was','were','be','been',
        'being','do','does','did','have','has','had','will','would','should',
        'can','could','my','your','our','their','his','her','its','i','you',
        'we','they','this','that','these','those','it','so','not','no','yes',
        // generic goal-shell words
        'complete','finish','achieve','get','take','pass','reach','hit','want',
        'need','plan','aim','target','goal','study','prepare','learn','build',
        'make','do','review','revise','practice',
        // generic credential nouns the user almost certainly does not log
        'certification','certificate','certified','exam','test','course','class',
        'lesson','module','chapter',
    ];

    /**
     * @return array<int, string> Lowercase, deduplicated keywords.
     */
    public function extract(string $title, ?string $description = null): array
    {
        $text = $title.' '.($description ?? '');
        $text = mb_strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? '';
        $tokens = preg_split('/\s+/u', trim($text)) ?: [];
        $tokens = array_values(array_filter($tokens, fn ($t) => $t !== ''));

        $kept = [];
        foreach ($tokens as $i => $tok) {
            if (mb_strlen($tok) < 2) continue;
            if (in_array($tok, self::STOPWORDS, true)) continue;
            $kept[] = $tok;
        }

        // Add bigrams of consecutive non-stopword tokens — e.g. "ethical hacker".
        $bigrams = [];
        for ($i = 0, $n = count($tokens) - 1; $i < $n; $i++) {
            $a = $tokens[$i];
            $b = $tokens[$i + 1];
            if (in_array($a, self::STOPWORDS, true)) continue;
            if (in_array($b, self::STOPWORDS, true)) continue;
            if (mb_strlen($a) < 2 || mb_strlen($b) < 2) continue;
            $bigrams[] = $a.' '.$b;
        }

        $all = array_merge($bigrams, $kept);
        return array_values(array_unique($all));
    }
}
