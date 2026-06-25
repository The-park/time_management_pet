<?php

namespace App\Http\Controllers;

use App\Models\TimeBlock;
use App\Services\SleepScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Read-only daily report. Past days' logs aren't editable — this page
 * exists to *show* what happened on a given calendar day with
 * productive / wasted / sleep / unlogged / efficiency math, plus a
 * read-only block list (no Edit / Delete buttons).
 */
class HistoryController extends Controller
{
    public function __construct(private SleepScheduleService $sleepSchedule)
    {
    }

    public function index()
    {
        return view('history.index');
    }

    public function day(Request $request, string $date)
    {
        // Defensive parse — the route regex only checks shape, not validity.
        // Carbon overflow-parses things like 2026-13-40 silently, so use
        // checkdate() for a proper calendar check.
        $parts = explode('-', $date);
        if (count($parts) !== 3
            || ! ctype_digit($parts[0]) || ! ctype_digit($parts[1]) || ! ctype_digit($parts[2])
            || ! checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0])) {
            return redirect()->route('history.index')
                ->with('toast', 'That date doesn\'t look right.');
        }
        $user = $request->user();
        $timezone = $user->timezone ?: config('app.timezone', 'UTC');
        $today = CarbonImmutable::today($timezone);
        $target = CarbonImmutable::parse($date, $timezone)->startOfDay();

        $blocks = TimeBlock::query()
            ->where('user_id', $user->id)
            ->where('duration_seconds', '>', 0)
            ->whereDate('start_time', $target->toDateString())
            ->orderBy('start_time')
            ->get();

        $productiveMs = 0;
        $wastedMs = 0;
        $neutralMs = 0;
        $rows = [];
        foreach ($blocks as $b) {
            $durationMs = max(0, (int) $b->duration_seconds * 1000);
            $isNeutral = $this->isNeutral($b);
            $isWasted = ! $isNeutral && $this->isWasted($b);
            if ($isWasted) {
                $wastedMs += $durationMs;
                $rowCat = 'wasted';
            } elseif ($isNeutral) {
                $neutralMs += $durationMs;
                $rowCat = 'neutral';
            } else {
                $productiveMs += $durationMs;
                $rowCat = 'productive';
            }
            $rows[] = [
                'start' => $b->start_time->format('g:i A'),
                'end' => $b->end_time?->format('g:i A') ?: '—',
                'durationMs' => $durationMs,
                'durationLabel' => $this->formatDuration($durationMs),
                'reason' => (string) ($b->reason ?? ''),
                'category' => $rowCat,
            ];
        }

        // Sleep math: same model used elsewhere in the app — count one
        // bedtime per calendar day, multiply by sleep_per_night.
        $schedule = $this->sleepSchedule->forUser($user);
        $sleepPerNightMin = (int) ($schedule['per_night_seconds'] / 60);
        $endOfDayLabel = $schedule['end_label'];
        $wakeLabel = $schedule['wake_label'];
        $sleepWindowLabel = $endOfDayLabel.' → '.$wakeLabel;
        $sleepMs = $sleepPerNightMin * 60 * 1000;
        $awakeMs = max(0, (24 * 60 * 60 * 1000) - $sleepMs);

        // Current-day stats use only elapsed time. Sleep intervals that
        // started before midnight are included, so pre-wake hours never
        // become false "unlogged" time.
        $isCurrentDay = $target->isSameDay($today);
        $isFuture = $target->gt($today);
        $dayEnd = $target->addDay();
        $now = CarbonImmutable::now($timezone);
        $effectiveEnd = $isFuture
            ? $target
            : ($isCurrentDay && $now->lt($dayEnd) ? $now : $dayEnd);
        $effectiveStart = $target;
        $signupAt = $user->created_at?->copy()->setTimezone($timezone);
        if ($isCurrentDay && $signupAt && $signupAt->gt($effectiveStart)) {
            $effectiveStart = $signupAt;
        }
        $elapsedMs = max(0, $effectiveStart->diffInMilliseconds($effectiveEnd, false));
        $sleepElapsedMs = $this->sleepSchedule->overlapSeconds($effectiveStart, $effectiveEnd, $user) * 1000;
        $awakeElapsedMs = max(0, $elapsedMs - $sleepElapsedMs);
        $awakeForRatio = max(1, ($isCurrentDay || $isFuture) ? $awakeElapsedMs : $awakeMs);

        $loggedMs = $productiveMs + $wastedMs + $neutralMs;
        $unloggedMs = max(0, $awakeForRatio - $loggedMs);
        // Efficiency = productive ÷ (productive + wasted + unlogged).
        // Neutral time (eating, transit, chores) is excluded from BOTH sides
        // of the ratio so it neither helps nor hurts the score.
        $effDenomMs = $productiveMs + $wastedMs + $unloggedMs;
        $efficiencyPct = $effDenomMs > 0
            ? (int) round(($productiveMs / $effDenomMs) * 100)
            : 0;
        $efficiencyPct = max(0, min(100, $efficiencyPct));

        return view('history.day', [
            'date' => $target,
            'dateLabel' => $target->format('l, F j, Y'),
            'isCurrentDay' => $isCurrentDay,
            'isFuture' => $isFuture,
            'rows' => $rows,
            'productiveMs' => $productiveMs,
            'wastedMs' => $wastedMs,
            'neutralMs' => $neutralMs,
            'loggedMs' => $loggedMs,
            'unloggedMs' => $unloggedMs,
            'sleepMs' => $isCurrentDay || $isFuture ? $sleepElapsedMs : $sleepMs,
            'awakeMs' => $isCurrentDay || $isFuture ? $awakeElapsedMs : $awakeMs,
            'awakeLabel' => $isCurrentDay ? 'awake elapsed' : ($isFuture ? 'not started yet' : '24h - sleep'),
            'sleepLabel' => $isCurrentDay ? 'scheduled sleep elapsed' : $sleepWindowLabel,
            'efficiencyPct' => $efficiencyPct,
            'sleepPerNightLabel' => $this->formatHourMinute($sleepPerNightMin),
            'sleepWindowLabel' => $sleepWindowLabel,
            'totalDayMs' => 24 * 60 * 60 * 1000,
        ]);
    }

    private function isWasted(TimeBlock $b): bool
    {
        if ($b->category === 'wasted') return true;
        if ($b->category === 'productive') return false;
        if ($b->category === 'neutral') return false;
        // No category stored (older rows): re-classify by reason text.
        return $this->scoreReason($b->reason ?? '') >= 2;
    }

    private function isNeutral(TimeBlock $b): bool
    {
        return $b->category === 'neutral';
    }

    private function scheduledSleepMsInRange(CarbonImmutable $rangeStart, CarbonImmutable $rangeEnd, $user): int
    {
        if ($rangeEnd->lte($rangeStart)) return 0;

        $end = $this->timeStr($user->end_of_day_time ?? null, '22:00');
        $wake = $this->timeStr($user->wake_up_time ?? null, '07:00');
        [$endH, $endM] = array_map('intval', explode(':', $end));
        [$wakeH, $wakeM] = array_map('intval', explode(':', $wake));

        $cursor = $rangeStart->startOfDay()->subDay();
        $lastDay = $rangeEnd->startOfDay();
        $totalMs = 0;
        while ($cursor->lte($lastDay)) {
            $sleepStart = $cursor->setTime($endH, $endM);
            $sleepEnd = $cursor->setTime($wakeH, $wakeM);
            if ($sleepEnd->lte($sleepStart)) $sleepEnd = $sleepEnd->addDay();

            $overlapStart = $sleepStart->gt($rangeStart) ? $sleepStart : $rangeStart;
            $overlapEnd = $sleepEnd->lt($rangeEnd) ? $sleepEnd : $rangeEnd;
            if ($overlapEnd->gt($overlapStart)) {
                $totalMs += (int) $overlapStart->diffInMilliseconds($overlapEnd);
            }
            $cursor = $cursor->addDay();
        }

        return $totalMs;
    }

    private function scoreReason(string $reason): int
    {
        $tokens = preg_split('/[^a-z0-9]+/', mb_strtolower($reason)) ?: [];
        $kw = ['wasted','waste','wasting','scroll','scrolling','procrastinate',
               'procrastinating','idle','binge','timepass','mindless','unproductive',
               'lazy','sleep','sleeping','slept','nap','napping','youtube',
               'instagram','tiktok','twitter','reddit','facebook','snapchat','netflix'];
        $score = 0;
        foreach ($tokens as $t) {
            foreach ($kw as $k) {
                if ($t === $k) { $score += 3; continue 2; }
                if (str_contains($t, $k)) { $score += 1; continue 2; }
            }
        }
        return $score;
    }

    private function sleepWindow($user): array
    {
        $end = $this->timeStr($user->end_of_day_time ?? null, '22:00');
        $wake = $this->timeStr($user->wake_up_time ?? null, '07:00');
        [$endH, $endM] = array_map('intval', explode(':', $end));
        [$wakeH, $wakeM] = array_map('intval', explode(':', $wake));
        $endMins = $endH * 60 + $endM;
        $wakeMins = $wakeH * 60 + $wakeM;
        $sleepMins = $wakeMins > $endMins
            ? $wakeMins - $endMins
            : (24 * 60) - $endMins + $wakeMins;
        return [$sleepMins, $this->display12($endH, $endM), $this->display12($wakeH, $wakeM)];
    }

    private function timeStr(?string $raw, string $fallback): string
    {
        $raw = $raw ?: $fallback;
        return substr($raw, 0, 5);
    }

    private function display12(int $h, int $m): string
    {
        $period = $h >= 12 ? 'PM' : 'AM';
        $h12 = $h % 12;
        if ($h12 === 0) $h12 = 12;
        return sprintf('%d:%02d %s', $h12, $m, $period);
    }

    private function formatDuration(int $ms): string
    {
        $totalMin = max(0, (int) round($ms / 60000));
        if ($totalMin === 0) return '0m';
        if ($totalMin < 60) return $totalMin.'m';
        $h = intdiv($totalMin, 60);
        $m = $totalMin % 60;
        return $m === 0 ? $h.'h' : $h.'h '.$m.'m';
    }

    private function formatHourMinute(int $mins): string
    {
        $h = intdiv($mins, 60);
        $m = $mins % 60;
        if ($m === 0) return $h.'h';
        return $h.'h '.$m.'m';
    }
}
