<?php

namespace App\Services;

use App\Models\Goal;
use Carbon\CarbonImmutable;

/**
 * Computes the probability that a Goal will be achieved on time.
 *
 * Inputs come from the user's existing time_blocks via GoalAttributionService.
 * Each block's hours are attributed to one or more goals based on whether
 * the block's reason text matches a goal's keywords. A block matching no
 * goal is unattributed; a block matching multiple goals is split
 * proportionally to the per-goal scores.
 *
 * Components (all bounded 0..1 unless noted):
 *   consistency      = days_with_attributed_logs / days_passed
 *   recent_activity  = days_with_attributed_logs_last_7 / min(7, days_passed)
 *   pace_signal      = tanh(avg_recent_attributed_hours_per_day / 4)
 *   time_buffer      = days_remaining / total_days
 *   ext_penalty      = 0.20 * extension_count
 *
 *   score = 1.5*(2c-1) + 1.2*(2r-1) + 0.8*(2*pace-1) + 0.6*(2b-1) - ext
 *   probability = sigmoid(score)
 */
class GoalProbabilityService
{
    public function __construct(private GoalAttributionService $attribution)
    {
    }

    public function compute(Goal $goal, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $today = $now->startOfDay();

        if ($goal->status === 'completed') {
            return $this->finalResult(100.0, [
                'reason' => 'Goal marked completed.',
            ]);
        }

        $start = CarbonImmutable::parse($goal->start_date)->startOfDay();
        $target = CarbonImmutable::parse($goal->target_date)->startOfDay();

        $totalDays = max(1, $start->diffInDays($target));
        $daysPassed = max(0, $start->diffInDays($today));
        $daysRemaining = $today->lte($target) ? $today->diffInDays($target) : 0;

        if ($today->gt($target) && $goal->status !== 'completed') {
            return $this->finalResult(1.5, [
                'reason' => 'Deadline has passed without completion.',
                'days_remaining' => 0,
                'days_passed' => $daysPassed,
                'total_days' => $totalDays,
            ]);
        }

        // Always run attribution — even on day 0 we want hours_done to
        // reflect what the user has actually logged today.
        $attribution = $this->attribution->forGoal($goal, $now);

        $hoursDone = (float) $attribution['hours_done'];
        $daysWithLogs = (int) $attribution['days_with_logs'];
        $recentHours = (float) $attribution['recent_hours'];
        $recentDaysWithLogs = (int) $attribution['recent_days_with_logs'];

        // Truly empty case — goal created today AND nothing logged yet —
        // gets a neutral 50% baseline. Once anything is logged, we drop
        // out of this branch so the user sees their work credited.
        if ($daysPassed === 0 && $hoursDone <= 0) {
            return $this->finalResult(50.0, [
                'reason' => 'Brand new goal — neutral baseline until you log any hours.',
                'days_remaining' => $daysRemaining,
                'days_passed' => 0,
                'total_days' => $totalDays,
                'hours_done' => 0,
                'days_with_logs' => 0,
                'has_keywords' => is_array($goal->keywords) && count($goal->keywords) > 0,
                'matched_block_count' => 0,
                'competing_goals_count' => $attribution['competing_goals_count'],
            ]);
        }

        // Treat the day the goal was created as day 1 — today's logs
        // count, and ratios stay defined. Without this, days_passed=0
        // would zero out consistency / recent_activity / pace.
        $effectiveDaysPassed = max(1, $daysPassed);
        $recentDaysObserved = max(1, min(7, $effectiveDaysPassed));

        $consistency = min(1.0, $daysWithLogs / $effectiveDaysPassed);
        $recentActivity = min(1.0, $recentDaysWithLogs / $recentDaysObserved);
        $avgRecentHoursPerDay = $recentHours / $recentDaysObserved;
        $paceSignal = tanh($avgRecentHoursPerDay / 4);
        $timeBuffer = $daysRemaining / $totalDays;
        $extPenalty = 0.20 * (int) $goal->extension_count;

        $score = 1.5 * (2 * $consistency - 1)
               + 1.2 * (2 * $recentActivity - 1)
               + 0.8 * (2 * $paceSignal - 1)
               + 0.6 * (2 * $timeBuffer - 1)
               - $extPenalty;

        $p = 1.0 / (1.0 + exp(-$score));
        $p = 0.02 + ($p * 0.96);
        $percent = round($p * 100, 1);

        $hasKeywords = is_array($goal->keywords) && count($goal->keywords) > 0;

        return $this->finalResult($percent, [
            // For UI consistency math (X / Y days) we report the
            // effective denominator so day-0 reads as "1 / 1 days".
            'days_passed' => $effectiveDaysPassed,
            'raw_days_passed' => $daysPassed,
            'days_remaining' => $daysRemaining,
            'total_days' => $totalDays,
            'days_with_logs' => $daysWithLogs,
            'recent_days_with_logs' => $recentDaysWithLogs,
            'hours_done' => round($hoursDone, 2),
            'recent_hours' => round($recentHours, 2),
            'avg_recent_hours_per_day' => round($avgRecentHoursPerDay, 2),
            'consistency' => round($consistency, 3),
            'recent_activity' => round($recentActivity, 3),
            'pace_signal' => round($paceSignal, 3),
            'time_buffer' => round($timeBuffer, 3),
            'extension_penalty' => round($extPenalty, 3),
            'score' => round($score, 3),
            'has_keywords' => $hasKeywords,
            'matched_block_count' => $attribution['blocks']->count(),
            'competing_goals_count' => $attribution['competing_goals_count'],
        ]);
    }

    public function persist(Goal $goal, float $percent): void
    {
        $goal->forceFill([
            'last_probability' => $percent,
            'last_probability_at' => now(),
        ])->saveQuietly();
    }

    public function tier(float $percent): array
    {
        if ($percent >= 80) return ['key' => 'high', 'label' => 'On track', 'color' => 'emerald', 'hex' => '#10b981'];
        if ($percent >= 60) return ['key' => 'good', 'label' => 'Looking good', 'color' => 'sky', 'hex' => '#38bdf8'];
        if ($percent >= 40) return ['key' => 'caution', 'label' => 'Caution', 'color' => 'amber', 'hex' => '#f59e0b'];
        if ($percent >= 20) return ['key' => 'warning', 'label' => 'Warning', 'color' => 'orange', 'hex' => '#fb923c'];
        return ['key' => 'critical', 'label' => 'Critical', 'color' => 'rose', 'hex' => '#f43f5e'];
    }

    public function alertLevel(Goal $goal, array $result): ?string
    {
        if ($goal->status !== 'active') return null;
        $percent = (float) $result['percent'];
        $remaining = $result['details']['days_remaining'] ?? null;

        if ($remaining === 0) return 'critical';
        if ($percent < 20) return 'critical';
        if ($percent < 30) return 'warning';
        if ($remaining !== null && $remaining <= 7 && $percent < 60) return 'warning';
        if ($remaining !== null && $remaining <= 3 && $percent < 75) return 'warning';
        return null;
    }

    public function narrative(Goal $goal, array $result): string
    {
        $details = $result['details'];
        $remaining = $details['days_remaining'] ?? null;
        $percent = $result['percent'];
        $hasKeywords = $details['has_keywords'] ?? false;

        if ($goal->status === 'completed') return 'Completed. Nice work.';
        if ($goal->status === 'abandoned') return 'Abandoned.';
        if ($remaining === 0 && $goal->status !== 'completed') {
            return 'Deadline reached. Mark complete or extend with a reason.';
        }
        if (! $hasKeywords) {
            return 'Add keywords to this goal so your dashboard logs can be attributed to it.';
        }
        if (($details['matched_block_count'] ?? 0) === 0 && ($details['days_passed'] ?? 0) > 0) {
            return 'No logged hours match this goal yet. Mention a keyword in your dashboard log reasons or refine the keyword list.';
        }
        if ($percent >= 80) return "You're logging consistent hours on this goal — keep this rhythm and you'll land it.";
        if ($percent >= 60) return 'Steady. A couple more consistent days will lock this in.';
        if ($percent >= 40) return 'Pace is slipping. Schedule deeper sessions or trim scope.';
        if ($percent >= 20) return 'Behind schedule. Recovery still possible, but it requires consistent daily work now.';
        return 'Critical. At current pace this goal will be missed — recalibrate or extend.';
    }

    private function finalResult(float $percent, array $details): array
    {
        $details['has_keywords'] = $details['has_keywords'] ?? null;
        $details['matched_block_count'] = $details['matched_block_count'] ?? 0;
        return [
            'percent' => $percent,
            'tier' => $this->tier($percent),
            'details' => $details,
        ];
    }
}
