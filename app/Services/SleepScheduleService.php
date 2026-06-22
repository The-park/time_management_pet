<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Calculates scheduled sleep as the actual overlap with a date range.
 *
 * A sleep window can begin on the previous calendar day, so simply counting
 * bedtimes inside a range makes midnight-to-wake time look falsely awake.
 */
class SleepScheduleService
{
    public function forUser(User $user): array
    {
        [$endHour, $endMinute] = $this->parseTime($user->end_of_day_time, '22:00');
        [$wakeHour, $wakeMinute] = $this->parseTime($user->wake_up_time, '07:00');

        $endMinutes = ($endHour * 60) + $endMinute;
        $wakeMinutes = ($wakeHour * 60) + $wakeMinute;
        $perNightMinutes = $wakeMinutes > $endMinutes
            ? $wakeMinutes - $endMinutes
            : (24 * 60) - $endMinutes + $wakeMinutes;

        return [
            'end_hour' => $endHour,
            'end_minute' => $endMinute,
            'wake_hour' => $wakeHour,
            'wake_minute' => $wakeMinute,
            'per_night_seconds' => $perNightMinutes * 60,
            'end_label' => $this->display12($endHour, $endMinute),
            'wake_label' => $this->display12($wakeHour, $wakeMinute),
        ];
    }

    public function overlapSeconds(CarbonImmutable $rangeStart, CarbonImmutable $rangeEnd, User $user): int
    {
        if ($rangeEnd->lte($rangeStart)) {
            return 0;
        }

        $schedule = $this->forUser($user);
        $cursor = $rangeStart->startOfDay()->subDay();
        $lastDay = $rangeEnd->startOfDay();
        $totalSeconds = 0;

        while ($cursor->lte($lastDay)) {
            [$sleepStart, $sleepEnd] = $this->windowForDay($cursor, $schedule);
            $overlapStart = $sleepStart->gt($rangeStart) ? $sleepStart : $rangeStart;
            $overlapEnd = $sleepEnd->lt($rangeEnd) ? $sleepEnd : $rangeEnd;

            if ($overlapEnd->gt($overlapStart)) {
                $totalSeconds += $overlapStart->diffInSeconds($overlapEnd);
            }

            $cursor = $cursor->addDay();
        }

        return $totalSeconds;
    }

    public function overlappingWindowCount(CarbonImmutable $rangeStart, CarbonImmutable $rangeEnd, User $user): int
    {
        if ($rangeEnd->lte($rangeStart)) {
            return 0;
        }

        $schedule = $this->forUser($user);
        $cursor = $rangeStart->startOfDay()->subDay();
        $lastDay = $rangeEnd->startOfDay();
        $count = 0;

        while ($cursor->lte($lastDay)) {
            [$sleepStart, $sleepEnd] = $this->windowForDay($cursor, $schedule);
            if ($sleepEnd->gt($rangeStart) && $sleepStart->lt($rangeEnd)) {
                $count++;
            }
            $cursor = $cursor->addDay();
        }

        return $count;
    }

    private function windowForDay(CarbonImmutable $day, array $schedule): array
    {
        $sleepStart = $day->setTime($schedule['end_hour'], $schedule['end_minute']);
        $sleepEnd = $day->setTime($schedule['wake_hour'], $schedule['wake_minute']);
        if ($sleepEnd->lte($sleepStart)) {
            $sleepEnd = $sleepEnd->addDay();
        }

        return [$sleepStart, $sleepEnd];
    }

    private function parseTime(?string $raw, string $fallback): array
    {
        $parts = explode(':', substr($raw ?: $fallback, 0, 5));

        return [(int) ($parts[0] ?? 0), (int) ($parts[1] ?? 0)];
    }

    private function display12(int $hour, int $minute): string
    {
        $period = $hour >= 12 ? 'PM' : 'AM';
        $hour12 = $hour % 12;
        if ($hour12 === 0) {
            $hour12 = 12;
        }

        return sprintf('%d:%02d %s', $hour12, $minute, $period);
    }
}
