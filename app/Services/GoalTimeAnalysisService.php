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
 * Sleep is measured only where scheduled sleep overlaps the goal window.
 */
class GoalTimeAnalysisService
{
    public function __construct(
        private GoalAttributionService $attribution,
        private SleepScheduleService $sleepSchedule,
    )
    {
    }

    public function analyze(Goal $goal, User $user, ?CarbonImmutable $now = null): array
    {
        $timezone = $user->timezone ?: config('app.timezone', 'UTC');
        $now = ($now ?? CarbonImmutable::now($timezone))->setTimezone($timezone);
        $schedule = $this->sleepSchedule->forUser($user);
        $sleepHoursPerNight = $schedule['per_night_seconds'] / 3600.0;

        $start = CarbonImmutable::parse($goal->start_date, $timezone)->startOfDay();
        $target = CarbonImmutable::parse($goal->target_date, $timezone)->endOfDay();

        // Clamp "now" within the window for the elapsed/remaining split.
        $cursor = $now->lt($start) ? $start : ($now->gt($target) ? $target : $now);

        $elapsedHours = max(0.0, $start->diffInSeconds($cursor) / 3600.0);
        $remainingHours = max(0.0, $cursor->diffInSeconds($target) / 3600.0);

        $elapsedNights = $this->sleepSchedule->overlappingWindowCount($start, $cursor, $user);
        $remainingNights = $this->sleepSchedule->overlappingWindowCount($cursor, $target, $user);

        $elapsedSleep = $this->sleepSchedule->overlapSeconds($start, $cursor, $user) / 3600.0;
        $remainingSleep = $this->sleepSchedule->overlapSeconds($cursor, $target, $user) / 3600.0;

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
                'end_of_day' => $schedule['end_label'],
                'wake_time' => $schedule['wake_label'],
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
