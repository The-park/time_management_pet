<?php

namespace App\Services;

use App\Models\Goal;
use App\Models\TimeBlock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Attributes time-block hours to the goals their reason text matches.
 *
 * The user records hours once on the dashboard with a free-text reason
 * (e.g. "AWS IAM policies", "metasploit lab"). For every active goal we
 * compute a relevance score against that text and split the block's
 * hours proportionally across the goals that score above the threshold.
 *
 * Scoring (0..1, picks the best keyword for that pair):
 *   - whole-word phrase match in reason       → 1.00
 *   - substring match (no word boundary)      → 0.70
 *   - fraction of keyword tokens found in
 *     reason tokens (with single-edit fuzzy
 *     matching for typos on len≥4 tokens)     → 0..1
 *
 * Attribution rule:
 *   For each block, sum the qualifying scores across goals; each goal
 *   gets `(its_score / sum_of_scores) * block_hours`. Blocks that match
 *   no goal contribute to "unattributed" and are not credited.
 */
class GoalAttributionService
{
    public const SCORE_THRESHOLD = 0.4;

    /**
     * Compute the attribution map for one goal across its full window.
     *
     * Returns:
     *   hours_done                    : float
     *   days_with_logs                : int
     *   recent_hours                  : float (last 7 days inside window)
     *   recent_days_with_logs         : int
     *   blocks                        : Collection of [block, score, share, hours]
     *   competing_goals_count         : int (other active goals visible to the user)
     */
    public function forGoal(Goal $goal, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $today = $now->startOfDay();

        $start = CarbonImmutable::parse($goal->start_date)->startOfDay();
        $target = CarbonImmutable::parse($goal->target_date)->endOfDay();
        $windowEnd = $target->lt($today->endOfDay()) ? $target : $today->endOfDay();

        $blocks = TimeBlock::query()
            ->where('user_id', $goal->user_id)
            ->where('duration_seconds', '>', 0)
            ->whereBetween('start_time', [$start->toDateTimeString(), $windowEnd->toDateTimeString()])
            ->orderByDesc('start_time')
            ->get();

        // All active competing goals belonging to the same user — needed
        // because a block's hours are split across every goal it matches.
        $allGoals = Goal::query()
            ->where('user_id', $goal->user_id)
            ->where('status', 'active')
            ->get();

        $totalHours = 0.0;
        $totalRecent = 0.0;
        $datesWithHours = [];
        $recentDates = [];
        $detailed = collect();
        $sevenDaysAgo = $today->subDays(6);

        foreach ($blocks as $block) {
            $shares = $this->splitBlock($block, $allGoals);
            if (! isset($shares[$goal->id])) continue;

            $share = $shares[$goal->id];               // 0..1
            $blockHours = $block->duration_seconds / 3600.0;
            $attributedHours = $blockHours * $share['share'];

            $totalHours += $attributedHours;
            $datesWithHours[$block->start_time->toDateString()] = true;

            if ($block->start_time->gte($sevenDaysAgo)) {
                $totalRecent += $attributedHours;
                $recentDates[$block->start_time->toDateString()] = true;
            }

            $detailed->push([
                'block' => $block,
                'reason' => $block->reason,
                'score' => $share['score'],
                'share' => $share['share'],
                'attributed_hours' => round($attributedHours, 3),
                'block_hours' => round($blockHours, 3),
            ]);
        }

        return [
            'hours_done' => round($totalHours, 3),
            'days_with_logs' => count($datesWithHours),
            'recent_hours' => round($totalRecent, 3),
            'recent_days_with_logs' => count($recentDates),
            'blocks' => $detailed,
            'competing_goals_count' => max(0, $allGoals->count() - 1),
        ];
    }

    /**
     * Split a single block's hours across the goals it matches.
     *
     * @return array<int, array{score: float, share: float}>
     *         Keyed by goal_id. Empty if the block matches no goal.
     */
    public function splitBlock(TimeBlock $block, Collection $goals): array
    {
        $reason = (string) ($block->reason ?? '');
        if (trim($reason) === '') return [];

        $scores = [];
        foreach ($goals as $g) {
            $kw = is_array($g->keywords) ? $g->keywords : [];
            if (empty($kw)) continue;

            $s = $this->scoreReasonAgainstKeywords($reason, $kw);
            if ($s >= self::SCORE_THRESHOLD) {
                $scores[$g->id] = $s;
            }
        }

        if (empty($scores)) return [];

        $sum = array_sum($scores);
        $out = [];
        foreach ($scores as $goalId => $score) {
            $out[$goalId] = [
                'score' => round($score, 3),
                'share' => round($score / $sum, 4),
            ];
        }
        return $out;
    }

    /**
     * Reason-vs-keywords matching. Returns the BEST keyword's score (0..1).
     */
    public function scoreReasonAgainstKeywords(string $reason, array $keywords): float
    {
        $reason = mb_strtolower(trim($reason));
        if ($reason === '' || empty($keywords)) return 0.0;

        $reasonTokens = preg_split('/\s+/u', preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $reason) ?? '');
        $reasonTokens = array_values(array_filter($reasonTokens, fn ($t) => $t !== ''));

        $best = 0.0;
        foreach ($keywords as $kw) {
            $kw = mb_strtolower(trim((string) $kw));
            if ($kw === '') continue;

            $kwTokens = preg_split('/\s+/u', preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $kw) ?? '');
            $kwTokens = array_values(array_filter($kwTokens, fn ($t) => $t !== ''));
            if (empty($kwTokens)) continue;

            $score = 0.0;

            // 1. Whole-word phrase match → 1.0
            if (preg_match('/\b'.preg_quote($kw, '/').'\b/u', $reason)) {
                $score = 1.0;
            }
            // 2. Substring match → 0.7
            elseif (str_contains($reason, $kw)) {
                $score = 0.7;
            }
            // 3. Token-overlap with optional single-edit fuzzy match
            else {
                $hits = 0.0;
                foreach ($kwTokens as $kt) {
                    $bestTokenHit = 0.0;
                    foreach ($reasonTokens as $rt) {
                        if ($rt === $kt) {
                            $bestTokenHit = 1.0;
                            break;
                        }
                        if (mb_strlen($kt) >= 4 && mb_strlen($rt) >= 4
                            && abs(mb_strlen($kt) - mb_strlen($rt)) <= 2
                            && levenshtein($kt, $rt) <= 1) {
                            $bestTokenHit = max($bestTokenHit, 0.6);
                        }
                    }
                    $hits += $bestTokenHit;
                }
                $score = $hits / count($kwTokens);
            }

            if ($score > $best) $best = $score;
            if ($best >= 1.0) break;
        }
        return $best;
    }
}
