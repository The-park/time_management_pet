<?php

namespace App\Services;

use App\Models\Goal;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Breaks down the wall-clock time inside a goal's window into:
 *   - elapsed (start → now)   ─ total / sleep / awake / logged / unlogged
 *   - remaining (now → target) ─ total / sleep / awake / weeks
 *
 * Sleep math uses the user's existing dashboard settings:
 *   end_of_day_time  → bedtime (e.g. 22:00)
 *   wake_up_time     → morning (e.g. 07:00)
 *   sleep_per_night  = wake - bedtime (handles past-midnight rollover)
 *
 * "Nights in window" is the count of bedtime instants that fall within
 * the window — each bedtime contributes one full sleep block.
 */
class GoalTimeAnalysisService
{
    public function __construct(private GoalAttributionService $attribution)
    {
    }

    public function analyze(Goal $goal, User $user, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();

        $end = $this->parseHourMinute($user->end_of_day_time, '22:00');     // bedtime
        $wake = $this->parseHourMinute($user->wake_up_time, '07:00');       // morning
        $endMins = $end[0] * 60 + $end[1];
        $wakeMins = $wake[0] * 60 + $wake[1];
        $sleepMinsPerNight = $wakeMins > $endMins
            ? $wakeMins - $endMins
            : (24 * 60) - $endMins + $wakeMins;
        $sleepHoursPerNight = $sleepMinsPerNight / 60.0;

        $start = CarbonImmutable::parse($goal->start_date)->startOfDay();
        $target = CarbonImmutable::parse($goal->target_date)->endOfDay();

        // Clamp "now" within the window for the elapsed/remaining split.
        $cursor = $now->lt($start) ? $start : ($now->gt($target) ? $target : $now);

        $elapsedHours = max(0.0, $start->diffInSeconds($cursor) / 3600.0);
        $remainingHours = max(0.0, $cursor->diffInSeconds($target) / 3600.0);

        $elapsedNights = $this->countNights($start, $cursor, $endMins);
        $remainingNights = $this->countNights($cursor, $target, $endMins);

        $elapsedSleep = $elapsedNights * $sleepHoursPerNight;
        $remainingSleep = $remainingNights * $sleepHoursPerNight;

        $elapsedAwake = max(0.0, $elapsedHours - $elapsedSleep);
        $remainingAwake = max(0.0, $remainingHours - $remainingSleep);

        // Hours actually attributed to this goal so far.
        $attribution = $this->attribution->forGoal($goal, $now);
        $loggedOnGoal = (float) $attribution['hours_done'];
        $unloggedAwake = max(0.0, $elapsedAwake - $loggedOnGoal);

        return [
            'sleep' => [
                'per_night_hours' => round($sleepHoursPerNight, 2),
                'per_night_label' => $this->formatHourLabel($sleepHoursPerNight),
                'end_of_day' => $this->display12Hour($end[0], $end[1]),
                'wake_time' => $this->display12Hour($wake[0], $wake[1]),
            ],
            'elapsed' => [
                'total_hours' => round($elapsedHours, 1),
                'total_label' => $this->formatDurationLabel($elapsedHours),
                'nights' => $elapsedNights,
                'sleep_hours' => round($elapsedSleep, 1),
                'awake_hours' => round($elapsedAwake, 1),
                'logged_hours' => round($loggedOnGoal, 2),
                'unlogged_awake_hours' => round($unloggedAwake, 1),
            ],
            'remaining' => [
                'total_hours' => round($remainingHours, 1),
                'total_label' => $this->formatDurationLabel($remainingHours),
                'weeks_label' => $this->formatWeeksLabel($remainingHours / 24),
                'days' => (int) floor($remainingHours / 24),
                'nights' => $remainingNights,
                'sleep_hours' => round($remainingSleep, 1),
                'awake_hours' => round($remainingAwake, 1),
            ],
        ];
    }

    /**
     * Count bedtime instants (end_of_day_time on each calendar day) that
     * fall strictly within [start, end]. Each one represents one night
     * of sleep credited to the elapsed or remaining bucket.
     */
    private function countNights(CarbonImmutable $start, CarbonImmutable $end, int $endOfDayMinutes): int
    {
        if ($start->gte($end)) return 0;

        $count = 0;
        // Iterate over each calendar day touched by the window.
        $day = $start->startOfDay();
        $endDay = $end->startOfDay();

        while ($day->lte($endDay)) {
            $bedtime = $day->setTime(intdiv($endOfDayMinutes, 60), $endOfDayMinutes % 60);
            if ($bedtime->gte($start) && $bedtime->lte($end)) {
                $count++;
            }
            $day = $day->addDay();
        }
        return $count;
    }

    private function parseHourMinute(?string $raw, string $fallback): array
    {
        $raw = $raw ?: $fallback;
        $hm = substr($raw, 0, 5);
        $parts = explode(':', $hm);
        return [(int) ($parts[0] ?? 0), (int) ($parts[1] ?? 0)];
    }

    private function display12Hour(int $h, int $m): string
    {
        $period = $h >= 12 ? 'PM' : 'AM';
        $h12 = $h % 12;
        if ($h12 === 0) $h12 = 12;
        return $m === 0
            ? sprintf('%d:%02d %s', $h12, $m, $period)
            : sprintf('%d:%02d %s', $h12, $m, $period);
    }

    private function formatHourLabel(float $hours): string
    {
        $whole = (int) floor($hours);
        $minutes = (int) round(($hours - $whole) * 60);
        if ($minutes === 0) return $whole.'h';
        if ($minutes === 60) return ($whole + 1).'h';
        return $whole.'h '.$minutes.'m';
    }

    private function formatDurationLabel(float $hours): string
    {
        if ($hours < 1) {
            return max(0, (int) round($hours * 60)).'m';
        }
        if ($hours < 24) {
            return $this->formatHourLabel($hours);
        }
        $days = (int) floor($hours / 24);
        $remH = $hours - ($days * 24);
        $rem = (int) floor($remH);
        return $rem === 0 ? $days.'d' : $days.'d '.$rem.'h';
    }

    private function formatWeeksLabel(float $days): string
    {
        $days = max(0, (int) floor($days));
        $weeks = intdiv($days, 7);
        $extra = $days % 7;
        if ($weeks === 0) {
            return $days.' '.($days === 1 ? 'day' : 'days');
        }
        $weekLabel = $weeks.' '.($weeks === 1 ? 'week' : 'weeks');
        if ($extra === 0) return $weekLabel;
        return $weekLabel.' '.$extra.' '.($extra === 1 ? 'day' : 'days');
    }
}
