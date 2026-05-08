@extends('layouts.app')

@section('page_title', auth()->check() ? 'Dashboard' : 'Track Your Time')

@section('content')
    @php
        $user = auth()->user();
        $endTime = $user?->end_of_day_time ? substr($user->end_of_day_time, 0, 5) : '22:00';
        $endTimeDisplay = \Carbon\Carbon::createFromFormat('H:i', $endTime)->format('g:i A');
        $wakeTime = $user?->wake_up_time ? substr($user->wake_up_time, 0, 5) : '07:00';
        $wakeTimeDisplay = \Carbon\Carbon::createFromFormat('H:i', $wakeTime)->format('g:i A');
        $timezone = $user?->timezone ?? 'UTC';
        [$endH, $endM] = array_map('intval', explode(':', $endTime));
        [$wakeH, $wakeM] = array_map('intval', explode(':', $wakeTime));
        $endMinsRef = $endH * 60 + $endM;
        $wakeMinsRef = $wakeH * 60 + $wakeM;
        $sleepMins = $wakeMinsRef > $endMinsRef
            ? $wakeMinsRef - $endMinsRef
            : (24 * 60 - $endMinsRef + $wakeMinsRef);
        $sleepH = intdiv($sleepMins, 60);
        $sleepM = $sleepMins % 60;
        $sleepLabel = $sleepM === 0 ? "{$sleepH}h" : "{$sleepH}h {$sleepM}m";
        $signupAt = $user?->created_at?->copy()->setTimezone($timezone);
        $signupTimestamp = $signupAt?->toIso8601String();
        $signupDateLabel = $signupAt?->format('M j, Y');

        // Server-side Last-7-Days tile data so the tiles always appear on
        // first paint, regardless of JS state. JS can still refresh them
        // when localStorage changes (existing renderLast7Days handles that).
        $serverLast7 = collect();
        if ($user) {
            $today = \Carbon\Carbon::today($timezone);
            $signupDay = $signupAt ? $signupAt->copy()->startOfDay() : null;
            $start = $today->copy()->subDays(6);
            $blocksByDate = \App\Models\TimeBlock::query()
                ->where('user_id', $user->id)
                ->where('duration_seconds', '>', 0)
                ->whereBetween('start_time', [$start->copy()->startOfDay(), $today->copy()->endOfDay()])
                ->get()
                ->groupBy(fn ($b) => $b->start_time->toDateString());
            for ($i = 0; $i < 7; $i++) {
                $d = $start->copy()->addDays($i);
                $key = $d->toDateString();
                $blocksForDay = $blocksByDate[$key] ?? collect();
                $productiveSec = (int) $blocksForDay->where('category', '!=', 'wasted')->sum('duration_seconds');
                $wastedSec = (int) $blocksForDay->where('category', 'wasted')->sum('duration_seconds');
                $serverLast7->push([
                    'date' => $key,
                    'day_short' => $d->format('D'),
                    'day_num' => $d->day,
                    'is_today' => $d->isSameDay($today),
                    'is_pre_signup' => $signupDay && $d->lt($signupDay),
                    'productive_ms' => $productiveSec * 1000,
                    'wasted_ms' => $wastedSec * 1000,
                ]);
            }
        }

        $fmtDuration = function (int $ms) {
            $totalMin = max(0, (int) round($ms / 60000));
            if ($totalMin === 0) return '0m';
            if ($totalMin < 60) return $totalMin.'m';
            $h = intdiv($totalMin, 60);
            $m = $totalMin % 60;
            return $m === 0 ? $h.'h' : $h.'h '.$m.'m';
        };
        $fmtHours = function (int $ms) {
            $h = $ms / 3600000;
            if ($h < 1) return max(0, (int) round($ms / 60000)).'m';
            if ($h < 10) return number_format($h, 1).'h';
            return (int) round($h).'h';
        };

        // Server-side initial stats for Month and Year so the tiles always
        // show data on first paint, even if JS hasn't run yet (or hits a
        // browser cache issue). updatePeriod() in JS still refreshes these
        // when localStorage changes.
        $serverPeriodStats = [];
        if ($user) {
            $tz = $timezone;
            $nowDt = \Carbon\Carbon::now($tz);
            $signupClampDt = $signupAt;
            $rangesPhp = [
                'month' => [
                    'start' => $nowDt->copy()->startOfMonth(),
                    'end' => $nowDt->copy()->startOfMonth()->addMonth(),
                ],
                'year' => [
                    'start' => $nowDt->copy()->startOfYear(),
                    'end' => $nowDt->copy()->startOfYear()->addYear(),
                ],
            ];

            // Pull all blocks in the largest range (year) once, reuse for month.
            $yearStart = $rangesPhp['year']['start'];
            $yearEnd = $rangesPhp['year']['end'];
            $allYearBlocks = \App\Models\TimeBlock::query()
                ->where('user_id', $user->id)
                ->where('duration_seconds', '>', 0)
                ->whereBetween('start_time', [$yearStart, $yearEnd])
                ->get(['start_time', 'duration_seconds', 'category']);

            foreach ($rangesPhp as $key => $range) {
                $startDt = $range['start'];
                $endDt = $range['end'];
                $effectiveStart = ($signupClampDt && $signupClampDt->gt($startDt)) ? $signupClampDt : $startDt;
                $passedMs = max(0, $effectiveStart->diffInRealMilliseconds($nowDt, false));
                $leftMs = max(0, $nowDt->diffInRealMilliseconds($endDt, false));
                $totalMs = max(1, $effectiveStart->diffInRealMilliseconds($endDt, false));

                $blocksInRange = $allYearBlocks->filter(function ($b) use ($effectiveStart, $endDt) {
                    return $b->start_time->gte($effectiveStart) && $b->start_time->lt($endDt);
                });
                $productiveMs = (int) ($blocksInRange->where('category', '!=', 'wasted')->sum('duration_seconds') * 1000);
                $wastedMs = (int) ($blocksInRange->where('category', 'wasted')->sum('duration_seconds') * 1000);

                // Sleep math (mirrors JS): one bedtime per calendar day in elapsed window.
                $endMins = $endH * 60 + $endM;
                $sleepNights = 0;
                if ($passedMs > 0) {
                    $cursor = $effectiveStart->copy()->startOfDay();
                    $lastDay = $nowDt->copy()->startOfDay();
                    while ($cursor->lte($lastDay)) {
                        $bedtime = $cursor->copy()->setTime(intdiv($endMins, 60), $endMins % 60);
                        if ($bedtime->gte($effectiveStart) && $bedtime->lte($nowDt)) $sleepNights++;
                        $cursor->addDay();
                    }
                }
                $sleepElapsedMs = $sleepNights * $sleepMins * 60 * 1000;
                $awakeElapsedMs = max(0, $passedMs - $sleepElapsedMs);
                $unloggedAwakeMs = max(0, $awakeElapsedMs - $productiveMs - $wastedMs);
                // Efficiency = productive ÷ (productive + wasted + unlogged).
                // Wasted AND unlogged time both count against the user, so the
                // only path to 100% is logging productive blocks across the
                // full awake window.
                $effDenomMs = $productiveMs + $wastedMs + $unloggedAwakeMs;
                $efficiencyPct = $effDenomMs > 0
                    ? min(100, (int) round(($productiveMs / $effDenomMs) * 100))
                    : 0;
                $progressPct = min(100, ($passedMs / $totalMs) * 100);

                // Awake-window segmented bar percentages (mirrors JS).
                $awakeForBar = max(1, $awakeElapsedMs);
                $prodPct = (int) round(($productiveMs / $awakeForBar) * 100);
                $wastedBarPct = (int) round(($wastedMs / $awakeForBar) * 100);
                $unloggedBarPct = max(0, 100 - $prodPct - $wastedBarPct);

                // Joined-mid-period detection. When the user signed up
                // *after* this period started, we display a callout so it's
                // obvious why their stats are clamped. The moment the period
                // boundary moves past their signup (e.g. new calendar year
                // starts), this flips off and the callout auto-hides.
                $signupClampedHere = $signupClampDt && $signupClampDt->gt($startDt);
                $preSignupLabel = null;
                if ($signupClampedHere) {
                    // Build a human-readable gap like "4 months · 3 days" or
                    // "12 days" describing how much of the period was already
                    // gone before signup. Carbon's diff* methods can return
                    // floats in newer versions — floor to whole months and
                    // recompute the day remainder explicitly.
                    $gapDays = max(0, (int) floor($startDt->diffInDays($signupClampDt)));
                    if ($key === 'year') {
                        $months = (int) floor($startDt->diffInMonths($signupClampDt));
                        $afterMonths = $startDt->copy()->addMonths($months);
                        $extraDays = max(0, (int) floor($afterMonths->diffInDays($signupClampDt)));
                        $parts = [];
                        if ($months >= 1) $parts[] = $months.' '.($months === 1 ? 'month' : 'months');
                        if ($extraDays >= 1) $parts[] = $extraDays.' '.($extraDays === 1 ? 'day' : 'days');
                        if (empty($parts)) $parts[] = $gapDays.' '.($gapDays === 1 ? 'day' : 'days');
                        $preSignupLabel = implode(' · ', $parts);
                    } else {
                        $preSignupLabel = $gapDays.' '.($gapDays === 1 ? 'day' : 'days');
                    }
                }

                $serverPeriodStats[$key] = [
                    'passed_ms' => (int) $passedMs,
                    'left_ms' => (int) $leftMs,
                    'total_ms' => (int) $totalMs,
                    'productive_ms' => $productiveMs,
                    'wasted_ms' => $wastedMs,
                    'sleep_ms' => (int) $sleepElapsedMs,
                    'sleep_nights' => $sleepNights,
                    'sleep_per_night_ms' => (int) ($sleepMins * 60 * 1000),
                    'awake_ms' => (int) $awakeElapsedMs,
                    'unlogged_ms' => (int) $unloggedAwakeMs,
                    'efficiency_pct' => $efficiencyPct,
                    'progress_pct' => round($progressPct, 2),
                    'bar_productive_pct' => $prodPct,
                    'bar_wasted_pct' => $wastedBarPct,
                    'bar_unlogged_pct' => $unloggedBarPct,
                    'range_label' => $effectiveStart->format('M j').' – '.$endDt->copy()->subDay()->format('M j'),
                    'signup_clamped' => $signupClampedHere,
                    'pre_signup_label' => $preSignupLabel,
                    'signup_date_label' => $signupClampedHere ? $signupClampDt->format('M j, Y') : null,
                ];
            }
        }
    @endphp
    @auth
        <div class="relative overflow-hidden rounded-2xl border border-slate-800/60 bg-[radial-gradient(circle_at_top,_rgba(0,224,255,0.15),_transparent_45%)] p-8 mb-10">
            <div class="absolute -right-24 -top-24 h-56 w-56 rounded-full bg-[radial-gradient(circle,_rgba(255,107,26,0.35),_transparent_70%)] blur-2xl"></div>
            <div class="relative">
                <h1 class="font-display text-3xl tracking-[0.3em] uppercase">Dashboard</h1>
                <p class="text-slate-300 text-sm mt-2">Track today, close the loops, and beat the deadline.</p>
            </div>
        </div>

        @include('partials.goal-alerts')
    @else
        @include('partials.guest-hero')
    @endauth

    <div class="space-y-8">
        <section class="chrono-panel rounded-2xl p-6 md:p-8">
            <div class="flex items-baseline justify-between gap-4 mb-5">
                <h2 class="font-display text-sm uppercase tracking-[0.3em] text-slate-300">Today</h2>
                <span class="text-xs text-slate-500" data-today-date></span>
            </div>

            {{-- Today's goals — multi-goal panel. Each goal is a card with
                 an auto-growing textarea, a Done checkbox, and a delete (×)
                 button. Empty cards drop on blur. Press Enter for new lines.
                 The reminder banner above shows pending vs completed counts
                 and how many hours remain until the user's bedtime. --}}
            <div class="space-y-3" id="todays_goals_panel">
                {{-- Reminder banner --}}
                <div data-goals-reminder
                    class="hidden rounded-lg border px-4 py-2.5 text-sm flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <span data-goals-reminder-icon class="font-display text-base leading-none"></span>
                        <span data-goals-reminder-text></span>
                    </div>
                    <span class="text-xs" data-goals-bedtime></span>
                </div>

                {{-- Goal cards stack --}}
                <div data-goals-list class="space-y-2.5"></div>

                {{-- Hidden template — cloned by JS for each goal card.
                     A 4px left-edge accent strip turns emerald when the goal
                     is done, slate otherwise. Done is a real button (not
                     a bare checkbox) for a more professional feel. --}}
                <template id="todays_goal_template">
                    <div class="relative overflow-hidden rounded-xl border border-slate-700/60 bg-slate-900/60 transition-colors" data-goal-card>
                        <span class="absolute inset-y-0 left-0 w-1 bg-slate-700/60" data-goal-accent></span>
                        <div class="pl-5 pr-4 py-3.5">
                            <div class="flex items-center justify-between gap-3 mb-2">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="inline-flex items-center justify-center h-5 px-2 rounded-full bg-slate-800/60 border border-slate-700/60 text-[0.6rem] uppercase tracking-wider text-slate-300 font-semibold" data-goal-index>Goal 1</span>
                                    <span class="hidden inline-flex items-center gap-1 rounded-full px-2 py-0.5 bg-emerald-500/10 border border-emerald-500/40 text-emerald-300 text-[0.6rem] uppercase tracking-wider" data-goal-completed-chip>
                                        <svg class="h-2.5 w-2.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        <span data-goal-completed-window></span>
                                    </span>
                                </div>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <button type="button" data-goal-done
                                        class="group inline-flex items-center gap-1.5 rounded-md border border-slate-700 hover:border-emerald-500/60 hover:bg-emerald-500/10 hover:text-emerald-300 text-slate-300 px-2.5 py-1 text-xs font-medium transition-colors"
                                        title="Mark this goal complete">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        <span data-goal-done-label>Done</span>
                                    </button>
                                    <button type="button" data-goal-delete
                                        class="inline-flex items-center justify-center h-7 w-7 rounded-md border border-slate-700/60 hover:border-rose-500/50 hover:bg-rose-500/10 text-slate-500 hover:text-rose-300 transition-colors"
                                        aria-label="Remove goal" title="Remove goal">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/></svg>
                                    </button>
                                </div>
                            </div>
                            <textarea data-goal-text rows="2" maxlength="2000"
                                placeholder="Written the detailed description for the day and act accordingly"
                                class="w-full rounded-md bg-slate-950/60 border border-slate-700/60 px-3 py-2 text-slate-100 placeholder-slate-500 leading-relaxed resize-none overflow-hidden focus:border-[var(--chrono-blue)] focus:outline-none focus:ring-1 focus:ring-[var(--chrono-blue)]/40 transition-colors"
                                style="color-scheme: dark; min-height: 2.75rem"></textarea>
                            <div class="mt-1.5 flex items-center justify-between gap-3 text-[0.6rem] text-slate-500">
                                <span><span data-goal-count>0</span> / 2000 · Enter for new line</span>
                                <span data-goal-empty-hint class="hidden text-amber-300">
                                    Write a description before marking it done.
                                </span>
                            </div>
                        </div>
                    </div>
                </template>

                <div class="flex items-center gap-3">
                    <button type="button" data-goal-add
                        class="inline-flex items-center gap-1.5 rounded-md border border-slate-700 hover:border-[var(--chrono-blue)]/60 hover:text-[var(--chrono-blue)] text-slate-300 px-3 py-1.5 text-xs transition-colors">
                        <span class="font-display text-base leading-none">+</span>
                        Add another goal
                    </button>
                    <span class="text-[0.65rem] text-slate-500" data-goals-stats>—</span>
                </div>
            </div>

            {{-- Goal-completion modal — opens when the user ticks Done on a
                 goal. Asks for the time frame so we can also log a time
                 block for the work. Cancel keeps the goal undone. --}}
            <div id="goal_complete_modal" role="dialog" aria-modal="true" aria-hidden="true"
                aria-labelledby="goal_complete_title"
                class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 backdrop-blur-sm p-4">
                <div class="w-full max-w-md rounded-2xl border border-emerald-500/30 bg-[var(--chrono-bg)] shadow-2xl overflow-hidden">
                    {{-- Header — emerald hero with check icon --}}
                    <div class="px-6 pt-5 pb-4 border-b border-slate-800/60 bg-gradient-to-br from-emerald-500/10 to-transparent">
                        <div class="flex items-center gap-3">
                            <span class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-emerald-500/40 bg-emerald-500/10 text-emerald-300">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            </span>
                            <div>
                                <h3 id="goal_complete_title" class="font-display text-sm uppercase tracking-[0.2em] text-emerald-200">
                                    When did you complete it?
                                </h3>
                                <p class="text-xs text-slate-400 mt-0.5">Tell us the time window — we'll log a productive block.</p>
                            </div>
                        </div>
                        <p class="mt-3 text-sm text-slate-200 italic" data-goal-complete-text></p>
                    </div>

                    {{-- Body --}}
                    <div class="px-6 py-5">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[0.65rem] uppercase tracking-wider text-slate-500 mb-1.5" for="goal_complete_from">From</label>
                                <input type="text" id="goal_complete_from" inputmode="numeric"
                                    placeholder="9:00 AM"
                                    data-time12 data-time12-hidden-id="goal_complete_from_value" data-time12-error-id="goal_complete_error"
                                    data-time12-label="Start" data-time12-example="9:00 AM"
                                    class="w-full rounded-md bg-slate-950/60 border border-slate-700 px-3 py-2.5 text-slate-100 font-digital text-base focus:border-emerald-500/60 focus:outline-none focus:ring-1 focus:ring-emerald-500/30">
                                <input type="hidden" id="goal_complete_from_value">
                            </div>
                            <div>
                                <label class="block text-[0.65rem] uppercase tracking-wider text-slate-500 mb-1.5" for="goal_complete_to">To</label>
                                <input type="text" id="goal_complete_to" inputmode="numeric"
                                    placeholder="10:30 AM"
                                    data-time12 data-time12-hidden-id="goal_complete_to_value" data-time12-error-id="goal_complete_error"
                                    data-time12-label="End" data-time12-example="10:30 AM"
                                    class="w-full rounded-md bg-slate-950/60 border border-slate-700 px-3 py-2.5 text-slate-100 font-digital text-base focus:border-emerald-500/60 focus:outline-none focus:ring-1 focus:ring-emerald-500/30">
                                <input type="hidden" id="goal_complete_to_value">
                            </div>
                        </div>
                        <p id="goal_complete_error" class="mt-2 hidden text-xs text-rose-400" aria-live="polite"></p>
                    </div>

                    {{-- Footer --}}
                    <div class="px-6 py-4 bg-slate-950/40 border-t border-slate-800/60 flex justify-end gap-2">
                        <button type="button" id="goal_complete_cancel"
                            class="rounded-md border border-slate-700 hover:border-slate-500 hover:text-slate-100 text-slate-300 px-4 py-2 text-sm transition-colors">
                            Cancel
                        </button>
                        <button type="button" id="goal_complete_save"
                            class="inline-flex items-center gap-2 rounded-md bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-semibold px-4 py-2 text-sm transition-colors">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            Mark complete
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mt-6">
                <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-4">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">End of day</div>
                    <div class="mt-2 font-digital text-2xl chrono-glow-blue chrono-pulse">
                        <span data-remaining-time>00:00:00</span>
                    </div>
                    <div class="text-xs text-slate-500 mt-1">
                        Until <span data-until-time>{{ $endTimeDisplay }}</span>
                        · <span data-until-zone>{{ $timezone }}</span>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-4">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Sleep window</div>
                    <div class="mt-2 text-lg text-slate-100">
                        {{ $endTimeDisplay }} → {{ $wakeTimeDisplay }}
                    </div>
                    <div class="text-xs text-slate-500 mt-1">{{ $sleepLabel }} scheduled</div>
                </div>

                <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-4">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Logged today</div>
                    <div class="mt-2 text-2xl text-slate-100" data-logged-today>0m</div>
                    <div class="text-xs text-slate-500 mt-1" data-logged-count>0 blocks</div>
                </div>

                <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-4">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Unlogged today</div>
                    <div class="mt-2 text-2xl text-slate-300" data-unlogged-today>0m</div>
                    <div class="text-xs text-slate-500 mt-1" data-unlogged-context>since wake-up</div>
                </div>
            </div>

            {{-- Day efficiency: at-a-glance breakdown of how the elapsed
                 waking window has been spent. Productive vs Wasted vs
                 Unlogged on a single segmented bar. --}}
            <div class="mt-5">
                <div class="flex items-center justify-between text-xs uppercase tracking-[0.2em] text-slate-400 mb-1">
                    <span>Day efficiency</span>
                    <span>
                        <span class="text-emerald-300 font-digital text-base" data-day-effective-pct>—</span>
                        <span class="text-slate-500 ml-1">effective</span>
                    </span>
                </div>
                <p class="text-[0.65rem] text-slate-500 normal-case tracking-normal mb-2">
                    Productive ÷ (Productive + Non-productive),
                    where <span class="text-rose-300">Wasted</span> +
                    <span class="text-yellow-300">Unlogged</span> = Non-productive
                    (unlogged time counts against efficiency too).
                </p>
                <div class="h-2 rounded-full bg-slate-800/80 overflow-hidden flex">
                    <div class="h-full bg-emerald-400 transition-[width] duration-500" data-day-productive-bar style="width: 0%"></div>
                    <div class="h-full bg-rose-400 transition-[width] duration-500" data-day-wasted-bar style="width: 0%"></div>
                    <div class="h-full bg-yellow-400 transition-[width] duration-500" data-day-unlogged-bar style="width: 0%"></div>
                </div>
                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-[0.65rem] uppercase tracking-wider text-slate-500">
                    <span class="inline-flex items-center gap-1.5">
                        <span class="inline-block h-2 w-2 rounded-full bg-emerald-400"></span>
                        Productive <span data-day-productive-time class="text-emerald-300 normal-case font-digital">—</span>
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="inline-block h-2 w-2 rounded-full bg-rose-400"></span>
                        Wasted <span data-day-wasted-time class="text-rose-300 normal-case font-digital">—</span>
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="inline-block h-2 w-2 rounded-full bg-yellow-400"></span>
                        Unlogged <span data-day-unlogged-time class="text-yellow-300 normal-case font-digital">—</span>
                    </span>
                    <span class="inline-flex items-center gap-1.5 ml-auto">
                        <span class="text-slate-400">Non-productive</span>
                        <span data-day-nonproductive-time class="text-slate-200 normal-case font-digital">—</span>
                    </span>
                </div>
            </div>

            <div class="mt-5 flex flex-wrap items-center gap-3 text-xs">
                <label class="font-display uppercase tracking-[0.3em] text-slate-500" for="dashboard_end_time_display">
                    Edit end time
                </label>
                <input id="dashboard_end_time_display" type="text" inputmode="numeric"
                    placeholder="10:00 PM" value="{{ $endTimeDisplay }}"
                    data-time12
                    data-time12-hidden-id="dashboard_end_time_value"
                    data-time12-error-id="dashboard_end_time_error"
                    data-time12-label="End of day"
                    data-time12-min="18:00"
                    data-time12-max="23:59"
                    data-time12-example="10:00 PM"
                    class="w-32 rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 text-slate-100">
                <input id="dashboard_end_time_value" type="hidden" value="{{ $endTime }}"
                    data-end-time-input data-timezone="{{ $timezone }}">
                <p id="dashboard_end_time_error" class="text-rose-400 hidden" aria-live="polite"></p>
            </div>

            <div class="mt-6">
                <h3 class="text-xs uppercase tracking-[0.2em] text-slate-400">Top blocks today</h3>
                <ul class="mt-2 space-y-1 text-sm" data-top-blocks>
                    <li class="text-slate-500">No blocks logged yet today.</li>
                </ul>
            </div>
        </section>

        <section class="chrono-panel rounded-2xl p-6 md:p-8" data-period-section="week">
            <div class="flex items-baseline justify-between gap-4">
                <h2 class="font-display text-sm uppercase tracking-[0.3em] text-slate-300">This week</h2>
                <span class="text-xs text-slate-500" data-period-range></span>
            </div>
            <div class="mt-4 h-2 rounded-full bg-slate-800/80 overflow-hidden">
                <div class="h-full bg-[var(--chrono-blue)] transition-[width] duration-500" data-period-progress style="width: 0%"></div>
            </div>

            {{-- Joined-mid-period callout — shown only when signup falls
                 inside this period. Auto-hides when the period boundary
                 moves past the signup date (new week / month / year). --}}
            <div class="mt-3 hidden rounded-lg border border-[var(--chrono-blue)]/30 bg-[var(--chrono-blue)]/5 p-3" data-period-joined-note>
                <div class="flex items-start gap-2.5">
                    <span class="font-display text-base text-[var(--chrono-blue)] leading-none">i</span>
                    <div class="flex-1 min-w-0 text-xs">
                        <p class="text-slate-200">
                            <strong class="text-[var(--chrono-blue)]">Calculating from your signup</strong> on
                            <span data-period-joined-date class="text-slate-100 font-medium">—</span>
                            — pre-signup time isn't included.
                        </p>
                        <p class="mt-0.5 text-slate-400">
                            <span data-period-joined-gap class="font-digital text-slate-300">—</span>
                            of this week passed before you joined our community.
                            We'll start showing the full week once a new one begins.
                        </p>
                    </div>
                </div>
            </div>
            <div class="mt-2 text-xs space-y-1 hidden" data-period-note></div>

            {{-- Window row: total, sleep, awake, elapsed --}}
            <div class="mt-4">
                <div class="text-[0.65rem] uppercase tracking-wider text-slate-500 mb-1.5">The week</div>
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3">
                        <div class="text-[0.6rem] uppercase tracking-wider text-slate-500">Total hours</div>
                        <div class="mt-1 font-digital text-lg text-slate-100" data-period-total>—</div>
                        <div class="text-[0.65rem] text-slate-500 mt-0.5">since signup, capped at week</div>
                    </div>
                    <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3">
                        <div class="text-[0.6rem] uppercase tracking-wider text-slate-500">Sleep</div>
                        <div class="mt-1 font-digital text-lg text-slate-300" data-period-sleep>—</div>
                        <div class="text-[0.65rem] text-slate-500 mt-0.5" data-period-sleep-note>—</div>
                    </div>
                    <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3">
                        <div class="text-[0.6rem] uppercase tracking-wider text-slate-500">Awake hours</div>
                        <div class="mt-1 font-digital text-lg text-slate-100" data-period-awake>—</div>
                        <div class="text-[0.65rem] text-slate-500 mt-0.5">total − sleep</div>
                    </div>
                    <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3">
                        <div class="text-[0.6rem] uppercase tracking-wider text-slate-500">Time left</div>
                        <div class="mt-1 font-digital text-lg text-slate-100" data-period-left>—</div>
                        <div class="text-[0.65rem] text-slate-500 mt-0.5">until end of week</div>
                    </div>
                </div>
            </div>

            {{-- Activity row: productive, wasted, unlogged, efficiency --}}
            <div class="mt-4">
                <div class="text-[0.65rem] uppercase tracking-wider text-slate-500 mb-1.5">How you spent it</div>
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/5 p-3">
                        <div class="text-[0.6rem] uppercase tracking-wider text-emerald-300">Productive</div>
                        <div class="mt-1 font-digital text-lg text-emerald-200" data-period-productive>—</div>
                    </div>
                    <div class="rounded-xl border border-rose-500/30 bg-rose-500/5 p-3">
                        <div class="text-[0.6rem] uppercase tracking-wider text-rose-300">Wasted</div>
                        <div class="mt-1 font-digital text-lg text-rose-200" data-period-wasted>—</div>
                    </div>
                    <div class="rounded-xl border border-yellow-500/30 bg-yellow-500/5 p-3">
                        <div class="text-[0.6rem] uppercase tracking-wider text-yellow-300">Unlogged (awake)</div>
                        <div class="mt-1 font-digital text-lg text-yellow-200" data-period-unlogged>—</div>
                        <div class="text-[0.6rem] text-slate-500 mt-0.5">counts as non-productive</div>
                    </div>
                    <div class="rounded-xl border border-[var(--chrono-blue)]/30 bg-[var(--chrono-blue)]/5 p-3">
                        <div class="text-[0.6rem] uppercase tracking-wider text-[var(--chrono-blue)]">Efficiency</div>
                        <div class="mt-1 font-digital text-lg text-[var(--chrono-blue)]" data-period-ratio>—</div>
                        <div class="text-[0.6rem] text-slate-500 mt-0.5">productive ÷ (prod + non-productive)</div>
                    </div>
                </div>
                <p class="mt-2 text-[0.65rem] text-slate-500">
                    <span class="text-rose-300">Wasted</span> +
                    <span class="text-yellow-300">Unlogged</span> = Non-productive total
                    <span class="font-digital text-slate-200" data-period-nonproductive>—</span>
                    — both reduce efficiency.
                </p>
            </div>

            {{-- Awake-window segmented bar --}}
            <div class="mt-4">
                <div class="flex items-center justify-between text-[0.65rem] uppercase tracking-wider text-slate-500 mb-1.5">
                    <span>Awake-window breakdown</span>
                    <span data-period-awake-label>—</span>
                </div>
                <div class="h-2 rounded-full bg-slate-800/80 overflow-hidden flex">
                    <div class="h-full bg-emerald-400 transition-[width] duration-500" data-period-bar-productive style="width: 0%"></div>
                    <div class="h-full bg-rose-400 transition-[width] duration-500" data-period-bar-wasted style="width: 0%"></div>
                    <div class="h-full bg-yellow-400 transition-[width] duration-500" data-period-bar-unlogged style="width: 0%"></div>
                </div>
                <div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-[0.65rem] uppercase tracking-wider text-slate-500">
                    <span class="inline-flex items-center gap-1.5"><span class="inline-block h-2 w-2 rounded-full bg-emerald-400"></span> Productive</span>
                    <span class="inline-flex items-center gap-1.5"><span class="inline-block h-2 w-2 rounded-full bg-rose-400"></span> Wasted</span>
                    <span class="inline-flex items-center gap-1.5"><span class="inline-block h-2 w-2 rounded-full bg-yellow-400"></span> Unlogged</span>
                </div>
            </div>

            <div class="mt-5">
                <h3 class="text-xs uppercase tracking-[0.2em] text-slate-400">Last 7 days</h3>
                <p class="text-[0.65rem] text-slate-500 mt-0.5">Click any past day for a read-only report.</p>
                <div class="mt-2 grid grid-cols-7 gap-2" data-last-7-days>
                    {{-- Server-side initial render so the tiles always show
                         on first paint. JS (renderLast7Days) replaces this
                         markup when localStorage changes. --}}
                    @foreach ($serverLast7 as $tile)
                        @php
                            if ($tile['is_pre_signup']) {
                                $cls = 'rounded-lg border border-slate-800/30 bg-slate-900/20 opacity-50 p-2 text-center';
                            } elseif ($tile['is_today']) {
                                $cls = 'rounded-lg border border-[var(--chrono-blue)] bg-slate-800/60 p-2 text-center';
                            } else {
                                $cls = 'block rounded-lg border border-slate-800/60 bg-slate-900/40 hover:border-[var(--chrono-blue)]/60 transition-colors cursor-pointer p-2 text-center';
                            }
                            $clickable = ! $tile['is_pre_signup'] && ! $tile['is_today'];
                        @endphp
                        @if ($clickable)
                            <a href="{{ route('history.day', ['date' => $tile['date']]) }}"
                                class="{{ $cls }}" title="Click to see detailed report">
                                <div class="text-[0.65rem] uppercase tracking-wider text-slate-400">{{ $tile['day_short'] }}</div>
                                <div class="text-[0.65rem] text-slate-500">{{ $tile['day_num'] }}</div>
                                <div class="mt-1 text-sm">
                                    @if ($tile['productive_ms'] > 0)
                                        <span class="text-emerald-300 font-medium">{{ $fmtDuration($tile['productive_ms']) }}</span>
                                    @else
                                        <span class="text-rose-400 font-medium">0</span>
                                    @endif
                                </div>
                                @if ($tile['wasted_ms'] > 0)
                                    <div class="text-[0.6rem] text-rose-400/80 mt-0.5">{{ $fmtDuration($tile['wasted_ms']) }} wasted</div>
                                @endif
                            </a>
                        @else
                            <div class="{{ $cls }}"
                                title="{{ $tile['is_pre_signup'] ? 'Before your signup' : 'Current day — see Today section above' }}">
                                <div class="text-[0.65rem] uppercase tracking-wider text-slate-400">{{ $tile['day_short'] }}</div>
                                <div class="text-[0.65rem] text-slate-500">{{ $tile['day_num'] }}</div>
                                <div class="mt-1 text-sm">
                                    @if ($tile['is_pre_signup'])
                                        <span class="text-slate-600 italic">pre-signup</span>
                                    @elseif ($tile['productive_ms'] > 0)
                                        <span class="text-emerald-300 font-medium">{{ $fmtDuration($tile['productive_ms']) }}</span>
                                    @else
                                        <span class="text-rose-400 font-medium">0</span>
                                    @endif
                                </div>
                                @if (! $tile['is_pre_signup'] && $tile['wasted_ms'] > 0)
                                    <div class="text-[0.6rem] text-rose-400/80 mt-0.5">{{ $fmtDuration($tile['wasted_ms']) }} wasted</div>
                                @endif
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </section>

        @php $monthStats = $serverPeriodStats['month'] ?? null; @endphp
        <section class="chrono-panel rounded-2xl p-6 md:p-8" data-period-section="month">
            <div class="flex items-baseline justify-between gap-4">
                <h2 class="font-display text-sm uppercase tracking-[0.3em] text-slate-300">This month</h2>
                <span class="text-xs text-slate-500" data-period-range>{{ $monthStats['range_label'] ?? '' }}</span>
            </div>
            <div class="mt-4 h-2 rounded-full bg-slate-800/80 overflow-hidden">
                <div class="h-full bg-[var(--chrono-orange)] transition-[width] duration-500" data-period-progress
                    style="width: {{ $monthStats['progress_pct'] ?? 0 }}%"></div>
            </div>

            {{-- Joined-mid-month callout: server-rendered. Auto-hides next
                 month because the @if condition flips off as soon as
                 signup is no longer inside the current month. --}}
            <div class="mt-3 rounded-lg border border-[var(--chrono-orange)]/30 bg-[var(--chrono-orange)]/5 p-3 {{ ($monthStats['signup_clamped'] ?? false) ? '' : 'hidden' }}" data-period-joined-note>
                <div class="flex items-start gap-2.5">
                    <span class="font-display text-base text-[var(--chrono-orange)] leading-none">i</span>
                    <div class="flex-1 min-w-0 text-xs">
                        <p class="text-slate-200">
                            <strong class="text-[var(--chrono-orange)]">Calculating from your signup</strong> on
                            <span data-period-joined-date class="text-slate-100 font-medium">{{ $monthStats['signup_date_label'] ?? '—' }}</span>
                            — pre-signup time isn't included.
                        </p>
                        <p class="mt-0.5 text-slate-400">
                            <span data-period-joined-gap class="font-digital text-slate-300">{{ $monthStats['pre_signup_label'] ?? '—' }}</span>
                            of this month passed before you joined our community.
                            We'll start showing the full month once a new one begins.
                        </p>
                    </div>
                </div>
            </div>
            <div class="mt-2 text-xs space-y-1 hidden" data-period-note></div>

            {{-- Window row: total, sleep, awake, time left --}}
            <div class="mt-4">
                <div class="text-[0.65rem] uppercase tracking-wider text-slate-500 mb-1.5">The month</div>
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3">
                        <div class="text-[0.6rem] uppercase tracking-wider text-slate-500">Total hours</div>
                        <div class="mt-1 font-digital text-lg text-slate-100" data-period-total>{{ $monthStats ? $fmtHours($monthStats['total_ms']) : '—' }}</div>
                        <div class="text-[0.65rem] text-slate-500 mt-0.5">since signup, capped at month</div>
                    </div>
                    <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3">
                        <div class="text-[0.6rem] uppercase tracking-wider text-slate-500">Sleep</div>
                        <div class="mt-1 font-digital text-lg text-slate-300" data-period-sleep>{{ $monthStats ? $fmtHours($monthStats['sleep_ms']) : '—' }}</div>
                        <div class="text-[0.65rem] text-slate-500 mt-0.5" data-period-sleep-note>{{ $monthStats ? $monthStats['sleep_nights'].' '.($monthStats['sleep_nights'] === 1 ? 'night' : 'nights').' × '.$fmtHours($monthStats['sleep_per_night_ms']) : '—' }}</div>
                    </div>
                    <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3">
                        <div class="text-[0.6rem] uppercase tracking-wider text-slate-500">Awake hours</div>
                        <div class="mt-1 font-digital text-lg text-slate-100" data-period-awake>{{ $monthStats ? $fmtHours($monthStats['awake_ms']) : '—' }}</div>
                        <div class="text-[0.65rem] text-slate-500 mt-0.5">total − sleep</div>
                    </div>
                    <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3">
                        <div class="text-[0.6rem] uppercase tracking-wider text-slate-500">Time left</div>
                        <div class="mt-1 font-digital text-lg text-slate-100" data-period-left>{{ $monthStats ? $fmtHours($monthStats['left_ms']) : '—' }}</div>
                        <div class="text-[0.65rem] text-slate-500 mt-0.5">until end of month</div>
                    </div>
                </div>
            </div>

            {{-- Activity row: productive, wasted, unlogged, efficiency --}}
            <div class="mt-4">
                <div class="text-[0.65rem] uppercase tracking-wider text-slate-500 mb-1.5">How you spent it</div>
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/5 p-3">
                        <div class="text-[0.6rem] uppercase tracking-wider text-emerald-300">Productive</div>
                        <div class="mt-1 font-digital text-lg text-emerald-200" data-period-productive>{{ $monthStats && $monthStats['productive_ms'] > 0 ? $fmtHours($monthStats['productive_ms']) : '—' }}</div>
                    </div>
                    <div class="rounded-xl border border-rose-500/30 bg-rose-500/5 p-3">
                        <div class="text-[0.6rem] uppercase tracking-wider text-rose-300">Wasted</div>
                        <div class="mt-1 font-digital text-lg text-rose-200" data-period-wasted>{{ $monthStats && $monthStats['wasted_ms'] > 0 ? $fmtHours($monthStats['wasted_ms']) : '—' }}</div>
                    </div>
                    <div class="rounded-xl border border-yellow-500/30 bg-yellow-500/5 p-3">
                        <div class="text-[0.6rem] uppercase tracking-wider text-yellow-300">Unlogged (awake)</div>
                        <div class="mt-1 font-digital text-lg text-yellow-200" data-period-unlogged>{{ $monthStats && $monthStats['unlogged_ms'] > 0 ? $fmtHours($monthStats['unlogged_ms']) : '—' }}</div>
                        <div class="text-[0.6rem] text-slate-500 mt-0.5">counts as non-productive</div>
                    </div>
                    <div class="rounded-xl border border-[var(--chrono-blue)]/30 bg-[var(--chrono-blue)]/5 p-3">
                        <div class="text-[0.6rem] uppercase tracking-wider text-[var(--chrono-blue)]">Efficiency</div>
                        <div class="mt-1 font-digital text-lg text-[var(--chrono-blue)]" data-period-ratio>{{ $monthStats && $monthStats['passed_ms'] > 0 ? $monthStats['efficiency_pct'].'%' : '—' }}</div>
                        <div class="text-[0.6rem] text-slate-500 mt-0.5">productive ÷ (prod + non-productive)</div>
                    </div>
                </div>
                @php
                    $monthNonProd = ($monthStats['wasted_ms'] ?? 0) + ($monthStats['unlogged_ms'] ?? 0);
                @endphp
                <p class="mt-2 text-[0.65rem] text-slate-500">
                    <span class="text-rose-300">Wasted</span> +
                    <span class="text-yellow-300">Unlogged</span> = Non-productive total
                    <span class="font-digital text-slate-200" data-period-nonproductive>{{ $monthNonProd > 0 ? $fmtHours($monthNonProd) : '0h' }}</span>
                    — both reduce efficiency.
                </p>
            </div>

            {{-- Awake-window segmented bar --}}
            <div class="mt-4">
                <div class="flex items-center justify-between text-[0.65rem] uppercase tracking-wider text-slate-500 mb-1.5">
                    <span>Awake-window breakdown</span>
                    <span data-period-awake-label>{{ $monthStats ? $fmtHours($monthStats['awake_ms']).' awake elapsed' : '—' }}</span>
                </div>
                <div class="h-2 rounded-full bg-slate-800/80 overflow-hidden flex">
                    <div class="h-full bg-emerald-400 transition-[width] duration-500" data-period-bar-productive style="width: {{ $monthStats['bar_productive_pct'] ?? 0 }}%"></div>
                    <div class="h-full bg-rose-400 transition-[width] duration-500" data-period-bar-wasted style="width: {{ $monthStats['bar_wasted_pct'] ?? 0 }}%"></div>
                    <div class="h-full bg-yellow-400 transition-[width] duration-500" data-period-bar-unlogged style="width: {{ $monthStats['bar_unlogged_pct'] ?? 0 }}%"></div>
                </div>
                <div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-[0.65rem] uppercase tracking-wider text-slate-500">
                    <span class="inline-flex items-center gap-1.5"><span class="inline-block h-2 w-2 rounded-full bg-emerald-400"></span> Productive</span>
                    <span class="inline-flex items-center gap-1.5"><span class="inline-block h-2 w-2 rounded-full bg-rose-400"></span> Wasted</span>
                    <span class="inline-flex items-center gap-1.5"><span class="inline-block h-2 w-2 rounded-full bg-yellow-400"></span> Unlogged</span>
                </div>
            </div>
        </section>

        @php $yearStats = $serverPeriodStats['year'] ?? null; @endphp
        <section class="chrono-panel rounded-2xl p-6 md:p-8" data-period-section="year">
            <div class="flex items-baseline justify-between gap-4">
                <h2 class="font-display text-sm uppercase tracking-[0.3em] text-slate-300">This year</h2>
                <span class="text-xs text-slate-500" data-period-range>{{ $yearStats['range_label'] ?? '' }}</span>
            </div>
            <div class="mt-4 h-2 rounded-full bg-slate-800/80 overflow-hidden">
                <div class="h-full bg-emerald-400 transition-[width] duration-500" data-period-progress
                    style="width: {{ $yearStats['progress_pct'] ?? 0 }}%"></div>
            </div>

            {{-- Joined-mid-year callout: most relevant for users who join
                 partway through the calendar. Auto-hides on Jan 1 of the
                 next year because signup falls *before* the new year
                 start, so signup_clamped becomes false server-side. --}}
            <div class="mt-3 rounded-lg border border-emerald-400/30 bg-emerald-400/5 p-3 {{ ($yearStats['signup_clamped'] ?? false) ? '' : 'hidden' }}" data-period-joined-note>
                <div class="flex items-start gap-2.5">
                    <span class="font-display text-base text-emerald-300 leading-none">i</span>
                    <div class="flex-1 min-w-0 text-xs">
                        <p class="text-slate-200">
                            <strong class="text-emerald-300">Calculating from your signup</strong> on
                            <span data-period-joined-date class="text-slate-100 font-medium">{{ $yearStats['signup_date_label'] ?? '—' }}</span>
                            — pre-signup time isn't counted in this year's stats.
                        </p>
                        <p class="mt-0.5 text-slate-400">
                            <span data-period-joined-gap class="font-digital text-slate-300">{{ $yearStats['pre_signup_label'] ?? '—' }}</span>
                            of {{ now()->year }} passed before you joined our community.
                            Starting next January, you'll see the full year unrestricted.
                        </p>
                    </div>
                </div>
            </div>
            <div class="mt-2 text-xs space-y-1 hidden" data-period-note></div>

            {{-- Window row: total, sleep, awake, time left --}}
            <div class="mt-4">
                <div class="text-[0.65rem] uppercase tracking-wider text-slate-500 mb-1.5">The year</div>
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3">
                        <div class="text-[0.6rem] uppercase tracking-wider text-slate-500">Total hours</div>
                        <div class="mt-1 font-digital text-lg text-slate-100" data-period-total>{{ $yearStats ? $fmtHours($yearStats['total_ms']) : '—' }}</div>
                        <div class="text-[0.65rem] text-slate-500 mt-0.5">since signup, capped at year</div>
                    </div>
                    <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3">
                        <div class="text-[0.6rem] uppercase tracking-wider text-slate-500">Sleep</div>
                        <div class="mt-1 font-digital text-lg text-slate-300" data-period-sleep>{{ $yearStats ? $fmtHours($yearStats['sleep_ms']) : '—' }}</div>
                        <div class="text-[0.65rem] text-slate-500 mt-0.5" data-period-sleep-note>{{ $yearStats ? $yearStats['sleep_nights'].' '.($yearStats['sleep_nights'] === 1 ? 'night' : 'nights').' × '.$fmtHours($yearStats['sleep_per_night_ms']) : '—' }}</div>
                    </div>
                    <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3">
                        <div class="text-[0.6rem] uppercase tracking-wider text-slate-500">Awake hours</div>
                        <div class="mt-1 font-digital text-lg text-slate-100" data-period-awake>{{ $yearStats ? $fmtHours($yearStats['awake_ms']) : '—' }}</div>
                        <div class="text-[0.65rem] text-slate-500 mt-0.5">total − sleep</div>
                    </div>
                    <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3">
                        <div class="text-[0.6rem] uppercase tracking-wider text-slate-500">Time left</div>
                        <div class="mt-1 font-digital text-lg text-slate-100" data-period-left>{{ $yearStats ? $fmtHours($yearStats['left_ms']) : '—' }}</div>
                        <div class="text-[0.65rem] text-slate-500 mt-0.5">until end of year</div>
                    </div>
                </div>
            </div>

            {{-- Activity row: productive, wasted, unlogged, efficiency --}}
            <div class="mt-4">
                <div class="text-[0.65rem] uppercase tracking-wider text-slate-500 mb-1.5">How you spent it</div>
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/5 p-3">
                        <div class="text-[0.6rem] uppercase tracking-wider text-emerald-300">Productive</div>
                        <div class="mt-1 font-digital text-lg text-emerald-200" data-period-productive>{{ $yearStats && $yearStats['productive_ms'] > 0 ? $fmtHours($yearStats['productive_ms']) : '—' }}</div>
                    </div>
                    <div class="rounded-xl border border-rose-500/30 bg-rose-500/5 p-3">
                        <div class="text-[0.6rem] uppercase tracking-wider text-rose-300">Wasted</div>
                        <div class="mt-1 font-digital text-lg text-rose-200" data-period-wasted>{{ $yearStats && $yearStats['wasted_ms'] > 0 ? $fmtHours($yearStats['wasted_ms']) : '—' }}</div>
                    </div>
                    <div class="rounded-xl border border-yellow-500/30 bg-yellow-500/5 p-3">
                        <div class="text-[0.6rem] uppercase tracking-wider text-yellow-300">Unlogged (awake)</div>
                        <div class="mt-1 font-digital text-lg text-yellow-200" data-period-unlogged>{{ $yearStats && $yearStats['unlogged_ms'] > 0 ? $fmtHours($yearStats['unlogged_ms']) : '—' }}</div>
                        <div class="text-[0.6rem] text-slate-500 mt-0.5">counts as non-productive</div>
                    </div>
                    <div class="rounded-xl border border-[var(--chrono-blue)]/30 bg-[var(--chrono-blue)]/5 p-3">
                        <div class="text-[0.6rem] uppercase tracking-wider text-[var(--chrono-blue)]">Efficiency</div>
                        <div class="mt-1 font-digital text-lg text-[var(--chrono-blue)]" data-period-ratio>{{ $yearStats && $yearStats['passed_ms'] > 0 ? $yearStats['efficiency_pct'].'%' : '—' }}</div>
                        <div class="text-[0.6rem] text-slate-500 mt-0.5">productive ÷ (prod + non-productive)</div>
                    </div>
                </div>
                @php
                    $yearNonProd = ($yearStats['wasted_ms'] ?? 0) + ($yearStats['unlogged_ms'] ?? 0);
                @endphp
                <p class="mt-2 text-[0.65rem] text-slate-500">
                    <span class="text-rose-300">Wasted</span> +
                    <span class="text-yellow-300">Unlogged</span> = Non-productive total
                    <span class="font-digital text-slate-200" data-period-nonproductive>{{ $yearNonProd > 0 ? $fmtHours($yearNonProd) : '0h' }}</span>
                    — both reduce efficiency.
                </p>
            </div>

            {{-- Awake-window segmented bar --}}
            <div class="mt-4">
                <div class="flex items-center justify-between text-[0.65rem] uppercase tracking-wider text-slate-500 mb-1.5">
                    <span>Awake-window breakdown</span>
                    <span data-period-awake-label>{{ $yearStats ? $fmtHours($yearStats['awake_ms']).' awake elapsed' : '—' }}</span>
                </div>
                <div class="h-2 rounded-full bg-slate-800/80 overflow-hidden flex">
                    <div class="h-full bg-emerald-400 transition-[width] duration-500" data-period-bar-productive style="width: {{ $yearStats['bar_productive_pct'] ?? 0 }}%"></div>
                    <div class="h-full bg-rose-400 transition-[width] duration-500" data-period-bar-wasted style="width: {{ $yearStats['bar_wasted_pct'] ?? 0 }}%"></div>
                    <div class="h-full bg-yellow-400 transition-[width] duration-500" data-period-bar-unlogged style="width: {{ $yearStats['bar_unlogged_pct'] ?? 0 }}%"></div>
                </div>
                <div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-[0.65rem] uppercase tracking-wider text-slate-500">
                    <span class="inline-flex items-center gap-1.5"><span class="inline-block h-2 w-2 rounded-full bg-emerald-400"></span> Productive</span>
                    <span class="inline-flex items-center gap-1.5"><span class="inline-block h-2 w-2 rounded-full bg-rose-400"></span> Wasted</span>
                    <span class="inline-flex items-center gap-1.5"><span class="inline-block h-2 w-2 rounded-full bg-yellow-400"></span> Unlogged</span>
                </div>
            </div>
        </section>

        <section id="custom-countdown" class="chrono-panel rounded-2xl p-6 md:p-8">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                <div class="flex-1">
                    <h2 class="font-display text-sm uppercase tracking-[0.3em] text-slate-300">Custom countdown</h2>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-4">
                        <input type="number" min="0" placeholder="Days" data-cc-days
                            class="rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 disabled:opacity-50">
                        <input type="number" min="0" placeholder="Hours" data-cc-hours
                            class="rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 disabled:opacity-50">
                        <input type="number" min="0" placeholder="Minutes" data-cc-minutes
                            class="rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 disabled:opacity-50">
                        <input type="number" min="0" placeholder="Seconds" data-cc-seconds
                            class="rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 disabled:opacity-50">
                    </div>
                    <input type="text" placeholder="Label (optional)" maxlength="120" data-cc-label
                        class="mt-3 w-full rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 disabled:opacity-50">
                    <div class="mt-4 flex gap-3">
                        <button type="button" data-cc-start
                            class="rounded-lg bg-[var(--chrono-blue)] text-slate-950 font-semibold px-4 py-2 disabled:opacity-50 disabled:cursor-not-allowed">Start</button>
                        <button type="button" data-cc-pause
                            class="rounded-lg border border-slate-600 px-4 py-2 disabled:opacity-50 disabled:cursor-not-allowed">Pause</button>
                        <button type="button" data-cc-reset
                            class="rounded-lg border border-slate-600 px-4 py-2 disabled:opacity-50 disabled:cursor-not-allowed">Reset</button>
                    </div>
                    <p class="mt-3 text-xs text-slate-400">
                        Each countdown logs a block in <em>Today's time blocks</em>. Maximum duration is <strong>1 hour</strong>.
                    </p>
                    <p data-cc-error class="mt-1 text-xs text-rose-400 hidden" aria-live="polite"></p>
                </div>
                <div class="flex-1">
                    <h2 class="font-display text-sm uppercase tracking-[0.3em] text-slate-300">Timer</h2>
                    <div class="mt-3 font-digital text-4xl md:text-5xl tracking-[0.2em] chrono-glow-orange" data-cc-display>
                        <span data-cc-time>00:00:00</span>
                        <span class="text-base tracking-[0.4em] ml-2 text-slate-300" data-cc-status>READY</span>
                    </div>
                    <div class="text-sm text-slate-400 mt-2 min-h-[1.25rem]" data-cc-display-label></div>
                </div>
            </div>
        </section>

        <section class="chrono-panel rounded-2xl p-6 md:p-8">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <h2 class="font-display text-sm uppercase tracking-[0.3em] text-slate-300">Today's time blocks</h2>
                <button class="rounded-lg border border-slate-600 px-4 py-2 text-sm">Add new block</button>
            </div>

            <div data-edit-banner
                class="hidden mt-6 rounded-lg border border-[var(--chrono-blue)]/40 bg-[var(--chrono-blue)]/10 px-3 py-2 text-sm text-[var(--chrono-blue)]">
                Editing block <span data-edit-banner-range class="font-semibold"></span> — your changes will replace the original. Click <strong>Cancel</strong> to discard.
            </div>
            <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs uppercase tracking-[0.2em] text-slate-400 mb-1" for="block_start_display">Start</label>
                    <input id="block_start_display" type="text" inputmode="numeric" placeholder="9:00 AM"
                        data-time12
                        data-time12-hidden-id="block_start_value"
                        data-time12-error-id="block_start_error"
                        data-time12-label="Start time"
                        data-time12-example="9:00 AM"
                        data-time12-group="block_form"
                        class="w-full rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 text-slate-100">
                    <input id="block_start_value" type="hidden" value="">
                    <p id="block_start_error" class="mt-1 text-xs text-rose-400 hidden" aria-live="polite"></p>
                </div>
                <div>
                    <label class="block text-xs uppercase tracking-[0.2em] text-slate-400 mb-1" for="block_end_display">End</label>
                    <input id="block_end_display" type="text" inputmode="numeric" placeholder="10:00 AM"
                        data-time12
                        data-time12-hidden-id="block_end_value"
                        data-time12-error-id="block_end_error"
                        data-time12-label="End time"
                        data-time12-example="10:00 AM"
                        data-time12-group="block_form"
                        class="w-full rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 text-slate-100">
                    <input id="block_end_value" type="hidden" value="">
                    <p id="block_end_error" class="mt-1 text-xs text-rose-400 hidden" aria-live="polite"></p>
                </div>
                <div class="md:col-span-1">
                    <label class="block text-xs uppercase tracking-[0.2em] text-slate-400 mb-1" for="block_reason_input">Reason / Activity</label>
                    <textarea id="block_reason_input" rows="3" maxlength="500"
                        placeholder="What did you do? Add as much detail as you'd like — auto-grows as you type."
                        style="color-scheme: dark"
                        class="w-full rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 text-slate-100 placeholder-slate-500 leading-relaxed resize-none min-h-[5rem] overflow-hidden focus:border-[var(--chrono-blue)] focus:outline-none focus:ring-1 focus:ring-[var(--chrono-blue)]/40"></textarea>
                    <p class="mt-1 text-[0.65rem] text-slate-500"><span data-reason-count>0</span> / 500 characters</p>

                    {{-- Real-time classifier hint. Updates as the user types,
                         showing the inferred category, confidence, and any
                         clarification suggestions (e.g. "add 30m durations to
                         split mixed blocks"). --}}
                    <div id="block_reason_hint" class="mt-1.5 hidden rounded-md border px-2 py-1 text-[0.65rem]" role="status" aria-live="polite">
                        <span class="inline-flex items-center gap-1.5">
                            <span data-hint-icon class="font-display text-base"></span>
                            <span data-hint-label class="uppercase tracking-wider"></span>
                            <span data-hint-confidence class="text-slate-500 normal-case tracking-normal"></span>
                        </span>
                        <span data-hint-suggestion class="block mt-0.5 text-slate-400 normal-case tracking-normal"></span>
                    </div>
                </div>
            </div>
            <p class="mt-3 text-xs text-slate-400">
                Times use <strong>12-hour format with AM/PM</strong> (e.g. <span class="text-slate-300">9:00 AM</span>, <span class="text-slate-300">2:30pm</span>, <span class="text-slate-300">11 p.m.</span>). 24-hour input is not accepted. <strong>Start must be before End</strong>, end can be at most <strong>1 hour ahead</strong> of now, and blocks <strong>can't overlap</strong> each other.
            </p>
            <p class="mt-1 text-xs text-slate-500">
                Words like <span class="text-rose-300/80">wasted</span>, <span class="text-rose-300/80">scrolling</span>, <span class="text-rose-300/80">youtube</span>, <span class="text-rose-300/80">social media</span>, <span class="text-rose-300/80">procrastinating</span> auto-flag the block as <span class="text-rose-300/80">Wasted</span> — even when run together (e.g. <span class="text-rose-300/80">seenyoutube</span>, <span class="text-rose-300/80">sotimegotwasted</span>). Click the chip in the table to flip a classification.
            </p>
            <p class="mt-1 text-xs text-slate-500">
                Tip: include durations like <span class="text-slate-300">30m sleep and 30m deep work</span> to auto-split the block.
            </p>
            <div class="mt-4 flex flex-wrap items-center gap-2">
                <button id="block_save_button" type="button" data-time12-gate="block_form"
                    class="rounded-lg bg-[var(--chrono-orange)] text-slate-950 font-semibold px-4 py-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    Log block
                </button>
                <button id="block_cancel_button" type="button"
                    class="hidden rounded-lg border border-slate-600 px-4 py-2 text-sm text-slate-200">
                    Cancel
                </button>
                <p id="block_form_error" class="text-xs text-rose-400 hidden" aria-live="polite"></p>
            </div>

            {{-- Ambiguity-resolution modal. Opens when the user tries to save
                 a block whose label has both productive AND wasted signals
                 without explicit duration markers. Lets the user either:
                  • split the block into two parts (specify minutes for each),
                  • or pick one category and proceed,
                  • or cancel and go back to editing. --}}
            <div id="block_ambiguity_modal"
                role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="block_ambiguity_title"
                class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 backdrop-blur-sm p-4">
                <div class="w-full max-w-lg rounded-2xl border border-amber-500/40 bg-[var(--chrono-bg)] p-6 shadow-2xl">
                    <h3 id="block_ambiguity_title" class="font-display text-base uppercase tracking-[0.2em] text-amber-300">
                        Specify times for this block
                    </h3>
                    <p class="mt-2 text-sm text-slate-300">
                        Your reason mentions <span class="text-rose-300" data-amb-wasted-list></span>
                        and <span class="text-emerald-300" data-amb-productive-list></span> —
                        we can't tell how much time was spent on each.
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        Block duration: <span data-amb-block-duration class="text-slate-300 font-digital"></span>
                    </p>

                    {{-- Mode A: split with explicit minutes --}}
                    <div class="mt-4 rounded-lg border border-slate-700/60 bg-slate-900/40 p-3">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400 mb-2">Split the block</div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[0.65rem] uppercase tracking-wider text-slate-500 mb-1">Wasted (minutes)</label>
                                <div class="flex items-center gap-2">
                                    <input type="number" min="0" max="1440" data-amb-wasted-min
                                        class="w-full rounded-md bg-slate-900/70 border border-rose-500/40 px-2 py-1.5 text-rose-200">
                                    <input type="text" data-amb-wasted-label
                                        placeholder="e.g. sleep"
                                        class="w-full rounded-md bg-slate-900/70 border border-slate-700 px-2 py-1.5 text-slate-100">
                                </div>
                            </div>
                            <div>
                                <label class="block text-[0.65rem] uppercase tracking-wider text-slate-500 mb-1">Productive (minutes)</label>
                                <div class="flex items-center gap-2">
                                    <input type="number" min="0" max="1440" data-amb-productive-min
                                        class="w-full rounded-md bg-slate-900/70 border border-emerald-500/40 px-2 py-1.5 text-emerald-200">
                                    <input type="text" data-amb-productive-label
                                        placeholder="e.g. study"
                                        class="w-full rounded-md bg-slate-900/70 border border-slate-700 px-2 py-1.5 text-slate-100">
                                </div>
                            </div>
                        </div>
                        <p class="mt-2 text-[0.65rem] text-slate-500">
                            Sum: <span data-amb-sum class="font-digital text-slate-300">0m</span>
                            of <span data-amb-block-duration-2 class="font-digital text-slate-300">—</span>
                            <span data-amb-sum-warn class="hidden text-rose-400 ml-2"></span>
                        </p>
                        <button type="button" id="block_ambiguity_split"
                            class="mt-3 w-full rounded-md bg-[var(--chrono-blue)] text-slate-950 font-semibold px-3 py-1.5 text-sm disabled:opacity-50 disabled:cursor-not-allowed">
                            Save as split block
                        </button>
                    </div>

                    {{-- Mode B: pick one category for the whole block --}}
                    <div class="mt-3 rounded-lg border border-slate-700/60 bg-slate-900/40 p-3">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400 mb-2">Or assign the whole block to one category</div>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" data-amb-pick="productive"
                                class="rounded-md border border-emerald-500/40 bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-200 px-3 py-1.5 text-sm">
                                All productive
                            </button>
                            <button type="button" data-amb-pick="wasted"
                                class="rounded-md border border-rose-500/40 bg-rose-500/10 hover:bg-rose-500/20 text-rose-200 px-3 py-1.5 text-sm">
                                All wasted
                            </button>
                        </div>
                    </div>

                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" id="block_ambiguity_cancel"
                            class="rounded-md border border-slate-600 hover:border-slate-400 px-3 py-1.5 text-sm text-slate-200">
                            Back to editing
                        </button>
                    </div>
                </div>
            </div>

            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-slate-400">
                        <tr>
                            <th class="text-left py-2">Start</th>
                            <th class="text-left py-2">End</th>
                            <th class="text-left py-2">Duration</th>
                            <th class="text-left py-2">Reason / Activity</th>
                            <th class="text-left py-2">Actions</th>
                        </tr>
                    </thead>
                    <tbody data-blocks-tbody></tbody>
                </table>
            </div>
        </section>
    </div>

    <div id="confirm_modal" role="dialog" aria-modal="true" aria-labelledby="confirm_modal_title" aria-hidden="true"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 backdrop-blur-sm p-4">
        <div class="w-full max-w-md rounded-2xl border border-slate-700/60 bg-[var(--chrono-bg)] p-6 shadow-2xl">
            <h3 id="confirm_modal_title"
                class="font-display text-base uppercase tracking-[0.2em] text-slate-100"
                data-confirm-title>Confirm</h3>
            <div class="mt-3 text-sm text-slate-300 space-y-1" data-confirm-body></div>
            <div class="mt-5 flex justify-end gap-2">
                <button type="button" data-confirm-cancel
                    class="rounded-lg border border-slate-600 hover:border-slate-400 px-4 py-2 text-sm text-slate-200">Cancel</button>
                <button type="button" data-confirm-ok
                    class="rounded-lg px-4 py-2 text-sm font-semibold">Confirm</button>
            </div>
        </div>
    </div>

    <div id="hourly_modal" role="dialog" aria-modal="true" aria-labelledby="hourly_modal_title" aria-hidden="true"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 backdrop-blur-sm p-4">
        <div class="w-full max-w-md rounded-2xl border border-slate-700/60 bg-[var(--chrono-bg)] p-6 shadow-2xl">
            <h3 id="hourly_modal_title" class="font-display text-base uppercase tracking-[0.2em] text-slate-100">Hourly check-in</h3>
            <p class="mt-2 text-sm text-slate-300">
                We found an unlogged gap from
                <span class="font-medium text-slate-100" data-hourly-from></span>
                to
                <span class="font-medium text-slate-100" data-hourly-to></span>. What were you doing?
            </p>
            <textarea id="hourly_modal_input" rows="3" maxlength="240" placeholder="e.g. Sleep, commute, deep work"
                class="mt-3 w-full rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 text-slate-100"></textarea>
            <p class="mt-1 text-xs text-slate-500">
                We'll keep reminding you until it's logged. Near end of day, reminders speed up; remaining gaps are auto-marked as Wasted.
            </p>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" id="hourly_modal_skip"
                    class="rounded-lg border border-slate-600 px-4 py-2 text-sm">Remind me later</button>
                <button type="button" id="hourly_modal_save" disabled
                    class="rounded-lg bg-[var(--chrono-blue)] text-slate-950 font-semibold px-4 py-2 text-sm disabled:opacity-50 disabled:cursor-not-allowed">Save block</button>
            </div>
        </div>
    </div>

    @php
        // Active goals are surfaced to the dashboard JS so each block row
        // can show whether it's currently tracked toward a goal — and so
        // that "tracked → goal" instantly flips to "untracked" the moment
        // the user deletes the goal (the goal list is rebuilt on every
        // page load).
        $activeGoalsForJs = [];
        if (auth()->check()) {
            $activeGoalsForJs = \App\Models\Goal::query()
                ->where('status', 'active')
                ->orderBy('target_date')
                ->get(['id', 'title', 'keywords'])
                ->map(fn ($g) => [
                    'id' => $g->id,
                    'title' => $g->title,
                    'keywords' => is_array($g->keywords) ? $g->keywords : [],
                ])
                ->values();
        }
    @endphp
    @push('scripts')
        <script>
            window.ChronoDashboardConfig = {
                endTime: @json($endTime),
                wakeTime: @json($wakeTime),
                timezone: @json($timezone),
                signupTimestamp: @json($signupTimestamp),
                signupDateLabel: @json($signupDateLabel),
                activeGoals: @json($activeGoalsForJs),
                // Used by Last-7-Days tiles to link past days to their
                // read-only detail report. {date} is replaced client-side.
                dayDetailUrl: @json(route('history.day', ['date' => '__DATE__'])),
            };
        </script>
        <script>
            (() => {
                const BLOCKS_KEY = 'chrono.timeBlocks.v1';
                const tbody = document.querySelector('[data-blocks-tbody]');
                if (!tbody) return;

                const pad = (n) => String(n).padStart(2, '0');
                const formatTime12 = (hhmm) => {
                    if (!hhmm) return '';
                    const [h, m] = hhmm.split(':').map(Number);
                    const period = h >= 12 ? 'PM' : 'AM';
                    const hour12 = h === 0 ? 12 : (h > 12 ? h - 12 : h);
                    return `${hour12}:${pad(m)} ${period}`;
                };
                const dateToHHMM = (d) => `${pad(d.getHours())}:${pad(d.getMinutes())}`;
                const hhmmToMinutes = (hhmm) => {
                    const [h, m] = hhmm.split(':').map(Number);
                    return h * 60 + m;
                };
                const msToDurationLabel = (ms) => {
                    const totalMin = Math.max(0, Math.round(ms / 60000));
                    if (totalMin === 0) return '< 1m';
                    if (totalMin < 60) return `${totalMin}m`;
                    const hours = Math.floor(totalMin / 60);
                    const mins = totalMin % 60;
                    return mins === 0 ? `${hours}h` : `${hours}h ${mins}m`;
                };
                const escapeHtml = (str) => String(str ?? '').replace(/[&<>"']/g, (c) => ({
                    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
                }[c]));

                const localDateString = (d = new Date()) =>
                    `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

                // Single-word triggers — scored at the token level so they catch
                // concatenated forms like "seenyoutube" or "sotimegotwasted".
                const WASTED_TOKENS = [
                    'wasted', 'waste', 'wasting', 'wastes',
                    'scroll', 'scrolling', 'scrolled', 'scrolls',
                    'doomscroll', 'doomscrolling',
                    'procrastinate', 'procrastinating', 'procrastinated', 'procrastination',
                    'distract', 'distracted', 'distracting', 'distraction',
                    'idle', 'idling', 'idled',
                    'binge', 'binging', 'binged', 'bingewatch',
                    'timepass',
                    'mindless', 'mindlessly',
                    'unproductive',
                    'lazy', 'laziness',
                    'sleep', 'sleeping', 'slept', 'nap', 'napping', 'doze', 'dozing',
                    'youtube', 'instagram', 'tiktok', 'twitter', 'reddit',
                    'facebook', 'snapchat', 'netflix',
                    'aimless', 'aimlessly',
                    'useless',
                ];
                // Multi-word phrases — scanned against the whole lowercased label.
                const WASTED_PHRASES = [
                    'social media',
                    'time pass',
                    'binge watch',
                    'binge-watch',
                ];
                const WASTED_SCORE_THRESHOLD = 2;

                const scoreTokenAgainstKeyword = (token, kw) => {
                    if (token === kw) return 3;
                    if (token.length > kw.length && (token.startsWith(kw) || token.endsWith(kw))) return 2;
                    if (token.includes(kw)) return 1;
                    return 0;
                };

                const categorizeLabel = (label) => {
                    if (!label) return 'productive';
                    const text = String(label).toLowerCase();
                    let score = 0;

                    // Phrase pass — works on raw text since multi-word phrases
                    // already include their own internal word boundaries.
                    for (const phrase of WASTED_PHRASES) {
                        if (text.includes(phrase)) {
                            score += 3;
                            if (score >= WASTED_SCORE_THRESHOLD) return 'wasted';
                        }
                    }

                    // Token pass — splits on every non-alphanumeric boundary, then
                    // each token contributes at most one match per keyword. The
                    // strongest keyword match for a token wins, so a token like
                    // 'sotimegotwasted' scores once for "wasted" rather than three
                    // times for "wasted"/"waste"/"wasting".
                    const tokens = text.split(/[^a-z0-9]+/).filter((t) => t.length > 0);
                    for (const token of tokens) {
                        let best = 0;
                        for (const kw of WASTED_TOKENS) {
                            const s = scoreTokenAgainstKeyword(token, kw);
                            if (s > best) best = s;
                            if (best === 3) break;
                        }
                        score += best;
                        if (score >= WASTED_SCORE_THRESHOLD) return 'wasted';
                    }

                    return 'productive';
                };

                // ──────────────────────────────────────────────────────────
                // Enhanced classifier: rich `analyzeLabel(label)` that returns
                // confidence + ambiguity signals + suggestions. Used by the
                // real-time hint under the reason input and by the save-time
                // interception that asks the user to clarify ambiguous blocks.
                // ──────────────────────────────────────────────────────────

                // Curated productive-activity keywords — anchors for confidence
                // scoring and for distinguishing mixed-content blocks. We never
                // flip a block to wasted on these (the WASTED_TOKENS list is the
                // only thing that does that), but their presence raises the
                // productive-confidence and helps the classifier detect
                // mixed-category blocks like "30m sleep + 30m study".
                const PRODUCTIVE_TOKENS = [
                    // work / study verbs
                    'work', 'working', 'worked', 'works',
                    'study', 'studying', 'studied', 'studies',
                    'learn', 'learning', 'learned', 'learnt',
                    'read', 'reading',
                    'review', 'reviewing', 'reviewed',
                    'practice', 'practicing', 'practiced',
                    'research', 'researching', 'researched',
                    'plan', 'planning', 'planned',
                    'analyze', 'analyzing', 'analyzed',
                    'design', 'designing', 'designed',
                    'develop', 'developing', 'developed',
                    'build', 'building', 'built',
                    'create', 'creating', 'created',
                    'write', 'writing', 'wrote', 'written',
                    'code', 'coding', 'coded',
                    'debug', 'debugging', 'debugged',
                    'refactor', 'refactoring', 'refactored',
                    'test', 'testing', 'tested',
                    'deploy', 'deploying', 'deployed',
                    'fix', 'fixing', 'fixed',
                    'ship', 'shipping', 'shipped',
                    'meeting', 'meetings',
                    'interview', 'interviewing',
                    'standup', 'sync',
                    'workshop', 'training',
                    'lecture', 'class', 'lesson',
                    'homework', 'assignment',
                    'exam', 'quiz', 'revision', 'revising',
                    'lab', 'experiment',
                    'practice', 'rehearse', 'rehearsing',
                    // typical pursuits the dashboard's users do
                    'aws', 'gcp', 'azure', 'kubernetes', 'docker',
                    'react', 'vue', 'angular', 'svelte',
                    'laravel', 'django', 'rails', 'flask', 'spring',
                    'python', 'java', 'javascript', 'typescript', 'rust', 'golang',
                    'sql', 'database', 'devops', 'security', 'pentest',
                    'leetcode', 'algorithm', 'algorithms',
                    'project', 'task', 'tasks', 'feature', 'bug',
                    'document', 'documenting', 'docs',
                    'email', 'emails', 'inbox',
                    // health/skill productive
                    'gym', 'workout', 'exercise', 'exercising',
                    'run', 'running', 'jog', 'jogging', 'cycle', 'cycling',
                    'yoga', 'meditate', 'meditating', 'meditation',
                    'journal', 'journaling',
                    // chores that some users want to count as productive
                    'clean', 'cleaning', 'tidy', 'tidying',
                    'cook', 'cooking', 'meal', 'prep',
                    'errand', 'errands', 'shopping', 'groceries',
                ];

                // Productive multi-word phrases — useful for tighter signals.
                const PRODUCTIVE_PHRASES = [
                    'deep work', 'focus session', 'focus time',
                    'pair programming', 'code review',
                    'study session', 'reading session',
                    'side project', 'personal project',
                    'time management', 'project management',
                    'machine learning', 'data science',
                ];

                // Tokens we treat as conjunctions to detect "X and Y" patterns
                // — used only for ambiguity detection, not for scoring.
                const CONJUNCTION_TOKENS = new Set([
                    'and', '&', 'plus', 'then', 'also', 'or', 'as', 'with',
                    ',', ';', '+',
                ]);

                // Vowel-rich words look more like real English; gibberish like
                // "sjdfhsjhsjdhsjdk" is mostly consonants. We don't reject
                // gibberish — we just lower confidence so the UI can warn.
                const isLikelyGibberishToken = (token) => {
                    if (!token || token.length < 4) return false;       // short words are usually fine
                    if (!/[a-z]/.test(token)) return false;             // ids/numbers are not "gibberish"
                    const vowels = (token.match(/[aeiouy]/g) || []).length;
                    const ratio = vowels / token.length;
                    // English words usually have 30-50% vowels. <10% with
                    // length >= 6 looks like keyboard-mash.
                    return ratio < 0.10 && token.length >= 6;
                };

                // Score one token against a keyword list using the existing
                // matching strategy. Returns 0..3.
                const scoreTokenAgainstList = (token, list) => {
                    let best = 0;
                    for (const kw of list) {
                        const s = scoreTokenAgainstKeyword(token, kw);
                        if (s > best) best = s;
                        if (best === 3) return 3;
                    }
                    return best;
                };

                // Rich label analysis. Doesn't replace categorizeLabel
                // (which is the binary decision used by the rest of the
                // dashboard) — it sits alongside it and powers the realtime
                // hint + ambiguity modal.
                //
                // Returns:
                //   {
                //     category:   'productive' | 'wasted' | 'mixed' | 'ambiguous' | 'unknown',
                //     confidence: 0..1,
                //     productiveScore, wastedScore,
                //     productiveTokens: [...matched tokens for productive...],
                //     wastedTokens:     [...matched tokens for wasted...],
                //     hasDurations:     boolean   (parseDurationSegments yields ≥2),
                //     segments:         [...] | null,
                //     warnings:         [...messages...],
                //     suggestion:       string | null,
                //   }
                const analyzeLabel = (label) => {
                    const text = String(label || '').trim();
                    const result = {
                        category: 'unknown',
                        confidence: 0,
                        productiveScore: 0,
                        wastedScore: 0,
                        productiveTokens: [],
                        wastedTokens: [],
                        hasDurations: false,
                        segments: null,
                        warnings: [],
                        suggestion: null,
                    };
                    if (!text) {
                        result.warnings.push('empty');
                        result.suggestion = 'Add a brief description so the block can be tracked.';
                        return result;
                    }

                    const lower = text.toLowerCase();
                    const tokens = lower.split(/[^a-z0-9]+/).filter(Boolean);

                    // Gibberish detection: most tokens look like keyboard mash
                    const totalTokens = tokens.length;
                    const gibberishHits = tokens.filter(isLikelyGibberishToken).length;
                    const looksLikeGibberish =
                        totalTokens > 0
                        && gibberishHits / totalTokens >= 0.6
                        && totalTokens <= 4;

                    // Wasted scoring (mirrors categorizeLabel logic)
                    let wastedScore = 0;
                    const wastedHitTokens = [];
                    for (const phrase of WASTED_PHRASES) {
                        if (lower.includes(phrase)) {
                            wastedScore += 3;
                            wastedHitTokens.push(phrase);
                        }
                    }
                    for (const tk of tokens) {
                        const s = scoreTokenAgainstList(tk, WASTED_TOKENS);
                        if (s > 0) {
                            wastedScore += s;
                            wastedHitTokens.push(tk);
                        }
                    }

                    // Productive scoring
                    let productiveScore = 0;
                    const productiveHitTokens = [];
                    for (const phrase of PRODUCTIVE_PHRASES) {
                        if (lower.includes(phrase)) {
                            productiveScore += 3;
                            productiveHitTokens.push(phrase);
                        }
                    }
                    for (const tk of tokens) {
                        const s = scoreTokenAgainstList(tk, PRODUCTIVE_TOKENS);
                        if (s > 0) {
                            productiveScore += s;
                            productiveHitTokens.push(tk);
                        }
                    }

                    result.productiveScore = productiveScore;
                    result.wastedScore = wastedScore;
                    result.productiveTokens = [...new Set(productiveHitTokens)];
                    result.wastedTokens = [...new Set(wastedHitTokens)];

                    // Duration segments — already parsed elsewhere as
                    // parseDurationSegments. ≥2 segments means the block is
                    // already explicitly split; not ambiguous.
                    const segments = parseDurationSegments(text);
                    if (segments.length >= 2) {
                        result.hasDurations = true;
                        result.segments = segments;
                    }

                    // ── Decide category ──────────────────────────────────
                    if (looksLikeGibberish) {
                        result.category = 'unknown';
                        result.confidence = 0.15;
                        result.warnings.push('gibberish');
                        result.suggestion = "That doesn't look like a real activity. Add a few clear words about what you did.";
                        return result;
                    }

                    if (result.hasDurations) {
                        result.category = 'mixed';
                        result.confidence = 0.95;
                        result.suggestion = `Will auto-split into ${segments.length} blocks: ${
                            segments.map((s) => `${Math.round(s.minutes)}m ${s.label}`).join(' · ')
                        }`;
                        return result;
                    }

                    // Both productive AND wasted signals present, no durations
                    // → ambiguous: ask the user to clarify.
                    if (productiveScore >= 1 && wastedScore >= 1) {
                        result.category = 'ambiguous';
                        result.confidence = 0.4;
                        result.warnings.push('mixed-without-durations');
                        const pSample = result.productiveTokens[0] || 'work';
                        const wSample = result.wastedTokens[0] || 'sleep';
                        result.suggestion = `Looks like both productive and wasted activities. Add explicit times so we can split — e.g. "30m ${wSample} and 30m ${pSample}".`;
                        return result;
                    }

                    if (wastedScore >= WASTED_SCORE_THRESHOLD) {
                        result.category = 'wasted';
                        result.confidence = Math.min(1, 0.6 + wastedScore * 0.1);
                        return result;
                    }
                    if (wastedScore > 0) {
                        result.category = 'wasted';
                        result.confidence = 0.5;
                        return result;
                    }

                    if (productiveScore >= 3) {
                        result.category = 'productive';
                        result.confidence = Math.min(1, 0.7 + productiveScore * 0.05);
                        return result;
                    }
                    if (productiveScore >= 1) {
                        result.category = 'productive';
                        result.confidence = 0.65;
                        return result;
                    }

                    // No signals — default to productive but flag it as low
                    // confidence so the UI hint suggests adding detail.
                    result.category = 'productive';
                    result.confidence = 0.3;
                    result.warnings.push('no-keywords');
                    result.suggestion = "Couldn't classify automatically — add a recognizable activity (e.g. 'study', 'workout', 'youtube').";
                    return result;
                };

                const minutesToHHMM = (mins) => {
                    const clamped = Math.max(0, Math.min(23 * 60 + 59, Math.round(mins)));
                    const h = Math.floor(clamped / 60);
                    const m = clamped % 60;
                    return `${pad(h)}:${pad(m)}`;
                };

                const DURATION_SEGMENT_RE = /(\d+(?:\.\d+)?)\s*(h|hr|hrs|hour|hours|m|min|mins|minute|minutes)\s+([^,;]+?)(?=\s*(?:and|&|then|,|;)\s+|\s*$)/gi;
                const parseDurationSegments = (label) => {
                    const text = String(label || '').trim();
                    if (!text) return [];
                    const segments = [];
                    let match;
                    while ((match = DURATION_SEGMENT_RE.exec(text)) !== null) {
                        const value = Number(match[1]);
                        const unit = String(match[2] || '').toLowerCase();
                        if (!isFinite(value) || value <= 0) continue;
                        const minutes = unit.startsWith('h') ? value * 60 : value;
                        const segLabel = String(match[3] || '').trim();
                        if (!segLabel || minutes <= 0) continue;
                        segments.push({ minutes, label: segLabel });
                    }
                    return segments;
                };

                const buildSplitPayloads = (data) => {
                    if (!data || !data.allowSplit) return [data];
                    const segments = parseDurationSegments(data.label);
                    if (segments.length < 2) return [{ ...data, allowSplit: false }];

                    const durationMin = Math.round((data.durationMs || 0) / 60000);
                    const sumMinutes = Math.round(segments.reduce((s, seg) => s + seg.minutes, 0));
                    if (!durationMin || Math.abs(sumMinutes - durationMin) > 1) {
                        return [{ ...data, allowSplit: false }];
                    }

                    const startMin = hhmmToMinutes(data.start);
                    if (startMin === null) return [{ ...data, allowSplit: false }];

                    const base = { ...data };
                    delete base.allowSplit;
                    delete base.category;
                    delete base.categoryManual;

                    const payloads = [];
                    let cursor = startMin;
                    for (let i = 0; i < segments.length; i++) {
                        const seg = segments[i];
                        let segMinutes = Math.round(seg.minutes);
                        if (i === segments.length - 1) {
                            segMinutes = Math.max(0, startMin + durationMin - cursor);
                        }
                        if (segMinutes <= 0) continue;
                        payloads.push({
                            ...base,
                            start: minutesToHHMM(cursor),
                            end: minutesToHHMM(cursor + segMinutes),
                            durationMs: segMinutes * 60000,
                            label: seg.label,
                        });
                        cursor += segMinutes;
                    }

                    return payloads.length > 0 ? payloads : [{ ...data, allowSplit: false }];
                };

                const loadBlocks = () => {
                    try {
                        const raw = localStorage.getItem(BLOCKS_KEY);
                        if (!raw) return [];
                        const parsed = JSON.parse(raw);
                        return Array.isArray(parsed) ? parsed : [];
                    } catch {
                        return [];
                    }
                };
                const saveBlocks = (blocks) => {
                    try {
                        if (blocks.length === 0) {
                            localStorage.removeItem(BLOCKS_KEY);
                        } else {
                            localStorage.setItem(BLOCKS_KEY, JSON.stringify(blocks));
                        }
                    } catch {
                        /* storage may be disabled */
                    }
                    scheduleServerSync(blocks);
                };

                // Layout-level hydration (layouts/app.blade.php) populates
                // localStorage from the server BEFORE this script runs, so by
                // the time we get here, localStorage matches the DB. We just
                // gate the outgoing sync on the hydration flag in case the
                // layout script ever fails or is bypassed.
                let serverSyncTimer = null;
                let serverSyncInflight = false;

                const scheduleServerSync = (blocks) => {
                    if (!window.ChronoAuth?.isAuthenticated) return;
                    // If layout hydration didn't run, refuse to push — better
                    // to lose a save than to wipe persisted history.
                    if (!window.ChronoBlocksHydrated) return;
                    if (serverSyncTimer) clearTimeout(serverSyncTimer);
                    serverSyncTimer = setTimeout(() => pushServerSync(blocks), 800);
                };
                const pushServerSync = async (blocks) => {
                    if (serverSyncInflight) {
                        serverSyncTimer = setTimeout(() => pushServerSync(loadBlocks()), 1200);
                        return;
                    }
                    serverSyncInflight = true;
                    try {
                        const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
                        await fetch('/time-blocks/sync', {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': token,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify({ blocks }),
                        });
                    } catch {
                        /* offline or transient — next save will retry */
                    } finally {
                        serverSyncInflight = false;
                    }
                };
                const dispatchChange = () => {
                    window.dispatchEvent(new CustomEvent('chrono:blocks:changed'));
                };

                // Migration / re-classification: stamp date-less blocks as today, and re-run
                // the classifier on every block whose category was *not* manually overridden
                // (categoryManual !== true). This way improvements to the algorithm propagate
                // to existing blocks, while user clicks on the chip stick.
                (() => {
                    const blocks = loadBlocks();
                    let dirty = false;
                    const today = localDateString();
                    for (const block of blocks) {
                        if (!block.date) {
                            block.date = today;
                            dirty = true;
                        }
                        if (block.categoryManual !== true) {
                            const next = categorizeLabel(block.label);
                            if (block.category !== next) {
                                block.category = next;
                                dirty = true;
                            }
                        } else if (!block.category) {
                            block.category = 'productive';
                            dirty = true;
                        }
                    }
                    if (dirty) saveBlocks(blocks);
                })();

                // Retroactive auto-split migration. If a block was logged before
                // this dashboard had auto-split (or before its label was updated
                // to include explicit durations), the block sits as a single
                // row with one mixed label like "30mins sleep and 30mins coding"
                // and inherits whichever category dominated the keyword scan
                // (often Wasted, because "sleep" alone scores 3). Here we walk
                // every existing block, re-parse its label for duration
                // segments, and if it cleanly splits — replace the block with
                // its segments, each carrying its own category.
                //
                // Skipped when:
                //   - categoryManual === true   (user explicitly chose category)
                //   - splitMigrated === true    (already processed)
                //   - parseDurationSegments returns < 2 segments
                //   - segment minutes don't sum to within 1m of block duration
                (() => {
                    const blocks = loadBlocks();
                    const next = [];
                    let changed = false;

                    for (const block of blocks) {
                        if (block.splitMigrated || block.categoryManual === true) {
                            next.push(block);
                            continue;
                        }
                        if (!block.start || !block.durationMs || !block.label) {
                            next.push(block);
                            continue;
                        }
                        const segments = parseDurationSegments(block.label);
                        if (segments.length < 2) {
                            // Mark as processed so we don't reparse forever.
                            block.splitMigrated = true;
                            changed = true;
                            next.push(block);
                            continue;
                        }
                        const durationMin = Math.round(block.durationMs / 60000);
                        const sumMinutes = Math.round(segments.reduce((s, seg) => s + seg.minutes, 0));
                        if (!durationMin || Math.abs(sumMinutes - durationMin) > 1) {
                            block.splitMigrated = true;
                            changed = true;
                            next.push(block);
                            continue;
                        }
                        const startMin = hhmmToMinutes(block.start);
                        if (startMin === null) {
                            block.splitMigrated = true;
                            changed = true;
                            next.push(block);
                            continue;
                        }

                        // Replace the single block with N segment blocks.
                        let cursor = startMin;
                        for (let i = 0; i < segments.length; i++) {
                            const seg = segments[i];
                            let segMinutes = Math.round(seg.minutes);
                            if (i === segments.length - 1) {
                                segMinutes = Math.max(0, startMin + durationMin - cursor);
                            }
                            if (segMinutes <= 0) continue;
                            next.push({
                                ...block,
                                id: `${block.id || Date.now()}_s${i}_${Math.random().toString(36).slice(2, 6)}`,
                                start: minutesToHHMM(cursor),
                                end: minutesToHHMM(cursor + segMinutes),
                                durationMs: segMinutes * 60000,
                                label: seg.label,
                                category: categorizeLabel(seg.label),
                                splitMigrated: true,
                            });
                            cursor += segMinutes;
                        }
                        changed = true;
                    }

                    if (changed) saveBlocks(next);
                })();

                // ── Goal attribution chip ─────────────────────────────────────
                // Mirrors the server-side GoalAttributionService scoring so that
                // each row shows whether the block is tracked toward an active
                // goal or not. When the user deletes a goal, its blocks update
                // to "untracked" on next render (the goal list comes from
                // ChronoDashboardConfig which is rebuilt every page load).
                const ACTIVE_GOALS = (window.ChronoDashboardConfig?.activeGoals) || [];
                const GOAL_MATCH_THRESHOLD = 0.4;

                const scoreReasonAgainstKeywords = (reason, keywords) => {
                    const r = String(reason || '').toLowerCase().trim();
                    if (!r || !keywords || keywords.length === 0) return 0;
                    const reasonTokens = r.replace(/[^\p{L}\p{N}\s]+/gu, ' ').split(/\s+/).filter(Boolean);
                    let best = 0;
                    for (const rawKw of keywords) {
                        const kw = String(rawKw).toLowerCase().trim();
                        if (!kw) continue;
                        const kwTokens = kw.replace(/[^\p{L}\p{N}\s]+/gu, ' ').split(/\s+/).filter(Boolean);
                        if (kwTokens.length === 0) continue;
                        let score = 0;
                        const wholeWord = new RegExp('\\b' + kw.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\b', 'u');
                        if (wholeWord.test(r)) {
                            score = 1.0;
                        } else if (r.includes(kw)) {
                            score = 0.7;
                        } else {
                            let hits = 0;
                            for (const kt of kwTokens) {
                                let bestHit = 0;
                                for (const rt of reasonTokens) {
                                    if (rt === kt) { bestHit = 1.0; break; }
                                }
                                hits += bestHit;
                            }
                            score = hits / kwTokens.length;
                        }
                        if (score > best) best = score;
                        if (best >= 1.0) break;
                    }
                    return best;
                };

                const matchedGoalsFor = (reason) => {
                    if (ACTIVE_GOALS.length === 0) return [];
                    const matches = [];
                    for (const g of ACTIVE_GOALS) {
                        const s = scoreReasonAgainstKeywords(reason, g.keywords);
                        if (s >= GOAL_MATCH_THRESHOLD) matches.push({ id: g.id, title: g.title, score: s });
                    }
                    matches.sort((a, b) => b.score - a.score);
                    return matches;
                };

                const goalChipFor = (block) => {
                    if (block.category === 'wasted') return '';
                    const matches = matchedGoalsFor(block.label || '');
                    if (matches.length === 0) {
                        // Productive but tied to no current goal. Only surface this
                        // when the user actually has goals — otherwise it would
                        // just be noise on every block.
                        if (ACTIVE_GOALS.length === 0) return '';
                        return `<span class="ml-2 inline-flex items-center rounded-full px-2 py-0.5 text-[0.65rem] uppercase tracking-wider bg-slate-700/30 text-slate-500 border border-slate-600/30" title="No active goal matches this block's reason.">Untracked</span>`;
                    }
                    const primary = matches[0];
                    const extra = matches.length > 1 ? ` <span class="text-[var(--chrono-blue)]/60">+${matches.length - 1}</span>` : '';
                    const titleAttr = matches.map(m => '→ ' + m.title).join(' ');
                    return `<span class="ml-2 inline-flex items-center rounded-full px-2 py-0.5 text-[0.65rem] uppercase tracking-wider bg-[color-mix(in_oklab,var(--chrono-blue)_15%,transparent)] text-[var(--chrono-blue)] border border-[var(--chrono-blue)]/30" title="${escapeHtml(titleAttr)}">→ ${escapeHtml(primary.title)}${extra}</span>`;
                };

                const render = () => {
                    // Strict calendar-day scope: only blocks whose date stamp matches the
                    // browser's current local date. A block logged at 11:30 PM is dated to
                    // that calendar day and disappears from this table once the clock crosses
                    // midnight; a block created at 12:30 AM is tagged to the new day. The
                    // 10 PM sleep / 6 AM wake schedule does not affect this — calendar day
                    // is the boundary.
                    const todayKey = localDateString();
                    const blocks = loadBlocks()
                        .filter((b) => b.date === todayKey)
                        .slice()
                        .sort((a, b) => {
                            const aMin = hhmmToMinutes(a.start || '00:00');
                            const bMin = hhmmToMinutes(b.start || '00:00');
                            if (aMin !== bMin) return aMin - bMin;
                            return (a.id || '').localeCompare(b.id || '');
                        });
                    tbody.innerHTML = '';

                    if (blocks.length === 0) {
                        const tr = document.createElement('tr');
                        tr.innerHTML = '<td class="py-3 text-slate-500" colspan="5">No blocks logged for today yet — start a countdown or log one manually.</td>';
                        tbody.appendChild(tr);
                        return;
                    }

                    for (const block of blocks) {
                        const tr = document.createElement('tr');
                        tr.className = 'border-t border-slate-800/60';
                        tr.dataset.blockId = block.id;

                        let badge = '';
                        if (block.status === 'active') {
                            badge = '<span class="text-xs uppercase tracking-wider text-[var(--chrono-blue)] mr-2">Running</span>';
                        } else if (block.status === 'paused') {
                            badge = '<span class="text-xs uppercase tracking-wider text-amber-300 mr-2">Paused</span>';
                        }

                        const endText = block.status === 'paused'
                            ? '<span class="text-slate-500">— paused —</span>'
                            : escapeHtml(formatTime12(block.end));

                        const labelText = block.label
                            || (block.source === 'countdown' ? 'Custom countdown' : 'Time block');

                        const isWasted = block.category === 'wasted';
                        // Strong red/green so each row's category is unmistakable
                        // at a glance. Click still toggles between the two.
                        const chipClasses = isWasted
                            ? 'bg-rose-500/20 text-rose-200 border border-rose-500/50 hover:bg-rose-500/30'
                            : 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/40 hover:bg-emerald-500/25';
                        const categoryChip = `<button type="button" data-block-category` +
                            ` class="ml-2 inline-flex items-center rounded-full px-2 py-0.5 text-[0.65rem] uppercase tracking-wider transition-colors ${chipClasses}"` +
                            ` title="Click to toggle productive / wasted">${isWasted ? 'Wasted' : 'Productive'}</button>`;
                        const goalChip = goalChipFor(block);

                        const editButton = block.status === 'completed'
                            ? '<button class="text-[var(--chrono-blue)]" data-block-edit>Edit</button>'
                            : '';

                        tr.innerHTML = `
                            <td class="py-3">${escapeHtml(formatTime12(block.start))}</td>
                            <td class="py-3">${endText}</td>
                            <td class="py-3">${escapeHtml(msToDurationLabel(block.durationMs))}</td>
                            <td class="py-3">${badge}${escapeHtml(labelText)}${categoryChip}${goalChip}</td>
                            <td class="py-3">
                                <div class="flex gap-2">
                                    ${editButton}
                                    <button class="text-[var(--chrono-red)]" data-block-delete>Delete</button>
                                </div>
                            </td>
                        `;
                        tbody.appendChild(tr);
                    }
                };

                const add = (data) => {
                    const block = {
                        id: `${Date.now()}_${Math.random().toString(36).slice(2, 8)}`,
                        ...data,
                        date: data.date || localDateString(),
                        category: data.category || categorizeLabel(data.label),
                    };
                    const blocks = loadBlocks();
                    blocks.push(block);
                    saveBlocks(blocks);
                    render();
                    dispatchChange();
                    return block;
                };
                const addWithSplit = (data) => {
                    const payloads = buildSplitPayloads(data);
                    const added = [];
                    for (const payload of payloads) {
                        if (!payload) continue;
                        const cleaned = { ...payload };
                        delete cleaned.allowSplit;
                        added.push(add(cleaned));
                    }
                    return added;
                };
                const update = (id, updates) => {
                    const blocks = loadBlocks();
                    const block = blocks.find((b) => b.id === id);
                    if (!block) return;
                    Object.assign(block, updates);
                    saveBlocks(blocks);
                    render();
                    dispatchChange();
                };
                const remove = (id) => {
                    const blocks = loadBlocks().filter((b) => b.id !== id);
                    saveBlocks(blocks);
                    render();
                    dispatchChange();
                };
                const get = (id) => loadBlocks().find((b) => b.id === id) || null;

                const ONE_HOUR_MS = 60 * 60 * 1000;
                const startDisplay = document.getElementById('block_start_display');
                const endDisplay = document.getElementById('block_end_display');
                const startHidden = document.getElementById('block_start_value');
                const endHidden = document.getElementById('block_end_value');
                const reasonInput = document.getElementById('block_reason_input');
                const saveBtn = document.getElementById('block_save_button');
                const cancelBtn = document.getElementById('block_cancel_button');
                const blockFormError = document.getElementById('block_form_error');

                const showBlockFormError = (msg) => {
                    if (!blockFormError) return;
                    blockFormError.textContent = msg;
                    blockFormError.classList.remove('hidden');
                };
                const clearBlockFormError = () => {
                    if (blockFormError) blockFormError.classList.add('hidden');
                };

                for (const el of [startDisplay, endDisplay, reasonInput]) {
                    el?.addEventListener('input', clearBlockFormError);
                }

                // Reason textarea auto-grow + character counter.
                const reasonCount = document.querySelector('[data-reason-count]');
                const autoGrowReason = () => {
                    if (!reasonInput) return;
                    reasonInput.style.height = 'auto';
                    reasonInput.style.height = reasonInput.scrollHeight + 'px';
                    if (reasonCount) reasonCount.textContent = String(reasonInput.value.length);
                };
                reasonInput?.addEventListener('input', autoGrowReason);

                // Edit-mode banner refs.
                const editBanner = document.querySelector('[data-edit-banner]');
                const editBannerRange = document.querySelector('[data-edit-banner-range]');

                // ─── Custom confirm modal (replaces native confirm()) ──────────────
                const confirmModal = document.getElementById('confirm_modal');
                const confirmTitleEl = confirmModal?.querySelector('[data-confirm-title]');
                const confirmBodyEl = confirmModal?.querySelector('[data-confirm-body]');
                const confirmOkBtn = confirmModal?.querySelector('[data-confirm-ok]');
                const confirmCancelBtn = confirmModal?.querySelector('[data-confirm-cancel]');
                let confirmBusy = false;

                const TONES = {
                    blue: 'bg-[var(--chrono-blue)] text-slate-950 hover:opacity-90',
                    red: 'bg-rose-500 text-white hover:bg-rose-400',
                    orange: 'bg-[var(--chrono-orange)] text-slate-950 hover:opacity-90',
                };

                const showConfirmModal = ({ title, lines = [], confirmText = 'Confirm', cancelText = 'Cancel', tone = 'blue' }) => {
                    return new Promise((resolve) => {
                        // Hard fallback only if the modal markup is missing.
                        if (!confirmModal || !confirmOkBtn || !confirmCancelBtn) {
                            const text = `${title}\n\n${lines.map((l) => typeof l === 'string' ? l : l.text).join('\n')}`;
                            resolve(window.confirm(text));
                            return;
                        }
                        // Already showing a modal — drop the duplicate call without action.
                        if (confirmBusy) { resolve(false); return; }
                        confirmBusy = true;

                        confirmTitleEl.textContent = title;
                        confirmBodyEl.innerHTML = lines.map((item) => {
                            const text = typeof item === 'string' ? item : item.text;
                            const muted = typeof item === 'object' && item.muted;
                            const cls = muted ? 'class="text-slate-500"' : '';
                            return `<p ${cls}>${escapeHtml(text)}</p>`;
                        }).join('');

                        confirmOkBtn.textContent = confirmText;
                        confirmOkBtn.className = 'rounded-lg px-4 py-2 text-sm font-semibold ' + (TONES[tone] || TONES.blue);
                        confirmCancelBtn.textContent = cancelText;

                        const close = (result) => {
                            confirmModal.classList.remove('flex');
                            confirmModal.classList.add('hidden');
                            confirmModal.setAttribute('aria-hidden', 'true');
                            confirmOkBtn.removeEventListener('click', onOk);
                            confirmCancelBtn.removeEventListener('click', onCancel);
                            confirmModal.removeEventListener('click', onBackdrop);
                            document.removeEventListener('keydown', onKey);
                            confirmBusy = false;
                            resolve(result);
                        };
                        const onOk = () => close(true);
                        const onCancel = () => close(false);
                        const onBackdrop = (e) => { if (e.target === confirmModal) close(false); };
                        const onKey = (e) => {
                            if (e.key === 'Escape') { e.preventDefault(); close(false); }
                            else if (e.key === 'Enter') { e.preventDefault(); close(true); }
                        };

                        confirmOkBtn.addEventListener('click', onOk);
                        confirmCancelBtn.addEventListener('click', onCancel);
                        confirmModal.addEventListener('click', onBackdrop);
                        document.addEventListener('keydown', onKey);

                        confirmModal.classList.remove('hidden');
                        confirmModal.classList.add('flex');
                        confirmModal.setAttribute('aria-hidden', 'false');
                        setTimeout(() => confirmOkBtn.focus(), 50);
                    });
                };

                // Programmatic value-set helper: updates the visible display field and
                // fires 'input' so the time12 module reparses and refreshes the hidden value
                // and the gate state on the Save button. Also writes to the linked hidden
                // field directly so the form is correct even before the time12 module has
                // had a chance to bind its listeners (page-load race).
                const setTimeFieldFromHHMM = (display, hhmm) => {
                    if (!display) return;
                    display.value = hhmm ? formatTime12(hhmm) : '';
                    const hiddenId = display.dataset.time12HiddenId;
                    if (hiddenId) {
                        const hidden = document.getElementById(hiddenId);
                        if (hidden) hidden.value = hhmm || '';
                    }
                    display.dispatchEvent(new Event('input', { bubbles: true }));
                };

                const defaultSlots = () => {
                    const now = new Date();
                    const nowMin = now.getHours() * 60 + now.getMinutes();
                    const startMin = Math.max(0, Math.floor(nowMin / 15) * 15);
                    const endMin = Math.min(23 * 60 + 45, startMin + 60);
                    return {
                        start: `${pad(Math.floor(startMin / 60))}:${pad(startMin % 60)}`,
                        end: `${pad(Math.floor(endMin / 60))}:${pad(endMin % 60)}`,
                    };
                };

                let editingBlockId = null;

                const setFormMode = (mode, block = null) => {
                    if (mode === 'edit' && block) {
                        editingBlockId = block.id;
                        setTimeFieldFromHHMM(startDisplay, block.start);
                        setTimeFieldFromHHMM(endDisplay, block.end);
                        if (reasonInput) reasonInput.value = block.label || '';
                        if (saveBtn) saveBtn.textContent = 'Update block';
                        if (cancelBtn) cancelBtn.classList.remove('hidden');
                        if (editBanner) {
                            if (editBannerRange) {
                                editBannerRange.textContent = `${formatTime12(block.start)} – ${formatTime12(block.end)}`;
                            }
                            editBanner.classList.remove('hidden');
                        }
                        clearBlockFormError();
                        autoGrowReason();
                        startDisplay?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    } else {
                        editingBlockId = null;
                        const d = defaultSlots();
                        setTimeFieldFromHHMM(startDisplay, d.start);
                        setTimeFieldFromHHMM(endDisplay, d.end);
                        if (reasonInput) reasonInput.value = '';
                        if (saveBtn) saveBtn.textContent = 'Log block';
                        if (cancelBtn) cancelBtn.classList.add('hidden');
                        if (editBanner) editBanner.classList.add('hidden');
                        clearBlockFormError();
                        autoGrowReason();
                    }
                };

                // Find an overlapping same-date block. excludeId skips the block being edited.
                const findOverlap = (date, startMin, endMin, excludeId) => {
                    const blocks = loadBlocks();
                    for (const b of blocks) {
                        if (b.id === excludeId) continue;
                        if (b.date !== date) continue;
                        if (!b.start || !b.end) continue;
                        const bStart = hhmmToMinutes(b.start);
                        const bEnd = hhmmToMinutes(b.end);
                        if (startMin < bEnd && endMin > bStart) return b;
                    }
                    return null;
                };

                const handleSave = () => {
                    if (!window.ChronoAuthRequire?.('log a time block')) return;
                    if (!startHidden || !endHidden) return;
                    const start = startHidden.value;
                    const end = endHidden.value;
                    if (!start || !end) {
                        showBlockFormError('Enter valid Start and End times in 12-hour format.');
                        return;
                    }
                    const startMin = hhmmToMinutes(start);
                    const endMin = hhmmToMinutes(end);
                    if (endMin <= startMin) {
                        showBlockFormError(`Start time (${formatTime12(start)}) must be earlier than End time (${formatTime12(end)}).`);
                        return;
                    }
                    const durationMs = (endMin - startMin) * 60 * 1000;

                    const now = new Date();
                    const todayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime();
                    const futureLimit = now.getTime() + ONE_HOUR_MS;
                    const blockEndMs = todayStart + endMin * 60 * 1000;
                    if (blockEndMs > futureLimit) {
                        const limitDate = new Date(futureLimit);
                        showBlockFormError(`End time can be at most 1 hour ahead of now (≤ ${formatTime12(dateToHHMM(limitDate))}).`);
                        return;
                    }

                    const editingBlock = editingBlockId ? get(editingBlockId) : null;
                    const date = editingBlock?.date || localDateString();

                    const conflict = findOverlap(date, startMin, endMin, editingBlockId);
                    if (conflict) {
                        showBlockFormError(
                            `Overlaps with ${formatTime12(conflict.start)}–${formatTime12(conflict.end)} · "${conflict.label || 'Time block'}". Pick a different slot.`
                        );
                        return;
                    }

                    clearBlockFormError();
                    const label = (reasonInput?.value || '').trim() || 'Time block';

                    // ── Ambiguity interception ───────────────────────────
                    // If the label has both productive AND wasted signals
                    // without explicit duration markers, open the modal and
                    // let the user split it or pick a single category. The
                    // actual save happens inside the modal's resolver.
                    const analysis = analyzeLabel(label);
                    if (! editingBlock && analysis.category === 'ambiguous') {
                        openAmbiguityModal({
                            start, end, durationMs, label, analysis,
                        });
                        return;
                    }

                    if (editingBlock) {
                        const updates = { start, end, durationMs, label };
                        if (!editingBlock.categoryManual) {
                            updates.category = categorizeLabel(label);
                        }
                        update(editingBlock.id, updates);
                    } else {
                        addWithSplit({
                            source: 'manual',
                            start,
                            end,
                            durationMs,
                            label,
                            status: 'completed',
                            allowSplit: true,
                        });
                    }

                    setFormMode('add');
                };

                const handleCancel = () => setFormMode('add');

                // ── Real-time classifier hint ─────────────────────────────
                // Updates as the user types in the reason field. Categorises
                // the partial input + flags ambiguity / gibberish / no-keyword
                // cases so the user can fix things before clicking Save.
                const reasonHintEl = document.getElementById('block_reason_hint');
                const reasonHintIcon = reasonHintEl?.querySelector('[data-hint-icon]');
                const reasonHintLabel = reasonHintEl?.querySelector('[data-hint-label]');
                const reasonHintConf = reasonHintEl?.querySelector('[data-hint-confidence]');
                const reasonHintSuggest = reasonHintEl?.querySelector('[data-hint-suggestion]');

                const HINT_STYLES = {
                    productive: { border: 'border-emerald-500/40 bg-emerald-500/5', text: 'text-emerald-300', icon: '✓', word: 'Productive' },
                    wasted:     { border: 'border-rose-500/40 bg-rose-500/5',       text: 'text-rose-300',    icon: '✕', word: 'Wasted' },
                    mixed:      { border: 'border-sky-500/40 bg-sky-500/5',         text: 'text-sky-300',     icon: '⇆', word: 'Will auto-split' },
                    ambiguous:  { border: 'border-amber-500/50 bg-amber-500/10',    text: 'text-amber-300',   icon: '?', word: 'Needs clarification' },
                    unknown:    { border: 'border-slate-600/60 bg-slate-900/40',    text: 'text-slate-400',   icon: 'i', word: 'Unclassified' },
                };

                const updateReasonHint = () => {
                    if (!reasonHintEl) return;
                    const text = (reasonInput?.value || '').trim();
                    if (!text) {
                        reasonHintEl.classList.add('hidden');
                        return;
                    }
                    const a = analyzeLabel(text);
                    const style = HINT_STYLES[a.category] || HINT_STYLES.unknown;

                    // Reset class list, then apply category-specific colors.
                    reasonHintEl.className = `mt-1.5 rounded-md border px-2 py-1 text-[0.65rem] ${style.border}`;
                    if (reasonHintIcon) {
                        reasonHintIcon.textContent = style.icon;
                        reasonHintIcon.className = `font-display text-base ${style.text}`;
                    }
                    if (reasonHintLabel) {
                        reasonHintLabel.textContent = style.word;
                        reasonHintLabel.className = `uppercase tracking-wider ${style.text}`;
                    }
                    if (reasonHintConf) {
                        const conf = Math.round((a.confidence || 0) * 100);
                        reasonHintConf.textContent = `· ${conf}% confidence`;
                    }
                    if (reasonHintSuggest) {
                        reasonHintSuggest.textContent = a.suggestion || '';
                        reasonHintSuggest.className = a.suggestion
                            ? 'block mt-0.5 text-slate-400 normal-case tracking-normal'
                            : 'hidden';
                    }
                };

                let reasonHintTimer = null;
                reasonInput?.addEventListener('input', () => {
                    if (reasonHintTimer) clearTimeout(reasonHintTimer);
                    reasonHintTimer = setTimeout(updateReasonHint, 120);
                });

                // ── Ambiguity-resolution modal ────────────────────────────
                const ambModal = document.getElementById('block_ambiguity_modal');
                const ambWastedList = ambModal?.querySelector('[data-amb-wasted-list]');
                const ambProductiveList = ambModal?.querySelector('[data-amb-productive-list]');
                const ambBlockDuration = ambModal?.querySelector('[data-amb-block-duration]');
                const ambBlockDuration2 = ambModal?.querySelector('[data-amb-block-duration-2]');
                const ambWastedMin = ambModal?.querySelector('[data-amb-wasted-min]');
                const ambWastedLabel = ambModal?.querySelector('[data-amb-wasted-label]');
                const ambProductiveMin = ambModal?.querySelector('[data-amb-productive-min]');
                const ambProductiveLabel = ambModal?.querySelector('[data-amb-productive-label]');
                const ambSum = ambModal?.querySelector('[data-amb-sum]');
                const ambSumWarn = ambModal?.querySelector('[data-amb-sum-warn]');
                const ambSplitBtn = document.getElementById('block_ambiguity_split');
                const ambCancelBtn = document.getElementById('block_ambiguity_cancel');
                let ambPending = null;

                const closeAmbModal = () => {
                    if (!ambModal) return;
                    ambModal.classList.remove('flex');
                    ambModal.classList.add('hidden');
                    ambModal.setAttribute('aria-hidden', 'true');
                    ambPending = null;
                };

                const openAmbiguityModal = (pending) => {
                    if (!ambModal) {
                        // No modal in DOM (shouldn't happen). Fall back to the
                        // standard save path so we don't lose the user's work.
                        addWithSplit({
                            source: 'manual',
                            start: pending.start, end: pending.end,
                            durationMs: pending.durationMs,
                            label: pending.label, status: 'completed', allowSplit: true,
                        });
                        setFormMode('add');
                        return;
                    }
                    ambPending = pending;
                    const totalMin = Math.round(pending.durationMs / 60000);
                    const halfMin = Math.round(totalMin / 2);

                    if (ambWastedList) ambWastedList.textContent = pending.analysis.wastedTokens.join(', ') || 'wasted activity';
                    if (ambProductiveList) ambProductiveList.textContent = pending.analysis.productiveTokens.join(', ') || 'productive activity';
                    const durLabel = totalMin >= 60
                        ? `${Math.floor(totalMin/60)}h ${totalMin % 60 ? (totalMin % 60) + 'm' : ''}`.trim()
                        : `${totalMin}m`;
                    if (ambBlockDuration) ambBlockDuration.textContent = durLabel;
                    if (ambBlockDuration2) ambBlockDuration2.textContent = durLabel;

                    // Default split: 50/50, with the most prominent token in each side.
                    if (ambWastedMin) ambWastedMin.value = halfMin;
                    if (ambProductiveMin) ambProductiveMin.value = totalMin - halfMin;
                    if (ambWastedLabel) ambWastedLabel.value = pending.analysis.wastedTokens[0] || '';
                    if (ambProductiveLabel) ambProductiveLabel.value = pending.analysis.productiveTokens[0] || '';
                    updateAmbSum();

                    ambModal.classList.remove('hidden');
                    ambModal.classList.add('flex');
                    ambModal.setAttribute('aria-hidden', 'false');
                };

                const updateAmbSum = () => {
                    if (!ambPending) return;
                    const w = parseInt(ambWastedMin?.value || '0', 10) || 0;
                    const p = parseInt(ambProductiveMin?.value || '0', 10) || 0;
                    const totalMin = Math.round(ambPending.durationMs / 60000);
                    const sum = w + p;
                    if (ambSum) ambSum.textContent = `${sum}m`;
                    const off = sum - totalMin;
                    if (ambSumWarn) {
                        if (off === 0) {
                            ambSumWarn.classList.add('hidden');
                            ambSumWarn.textContent = '';
                        } else {
                            ambSumWarn.classList.remove('hidden');
                            ambSumWarn.textContent = off > 0
                                ? `${off}m over — reduce one side`
                                : `${-off}m short — bump one side`;
                        }
                    }
                    if (ambSplitBtn) ambSplitBtn.disabled = off !== 0 || w < 0 || p < 0 || (w === 0 && p === 0);
                };

                ambWastedMin?.addEventListener('input', updateAmbSum);
                ambProductiveMin?.addEventListener('input', updateAmbSum);
                ambCancelBtn?.addEventListener('click', closeAmbModal);
                ambModal?.addEventListener('click', (e) => { if (e.target === ambModal) closeAmbModal(); });

                ambSplitBtn?.addEventListener('click', () => {
                    if (!ambPending) return;
                    const w = parseInt(ambWastedMin?.value || '0', 10) || 0;
                    const p = parseInt(ambProductiveMin?.value || '0', 10) || 0;
                    const wLab = (ambWastedLabel?.value || ambPending.analysis.wastedTokens[0] || 'wasted').trim();
                    const pLab = (ambProductiveLabel?.value || ambPending.analysis.productiveTokens[0] || 'work').trim();

                    // Build a synthetic label that the existing auto-split
                    // pipeline understands: "Xm wasted-label and Ym productive-label".
                    const parts = [];
                    if (w > 0) parts.push(`${w}m ${wLab}`);
                    if (p > 0) parts.push(`${p}m ${pLab}`);
                    const synthLabel = parts.join(' and ');

                    addWithSplit({
                        source: 'manual',
                        start: ambPending.start,
                        end: ambPending.end,
                        durationMs: ambPending.durationMs,
                        label: synthLabel,
                        status: 'completed',
                        allowSplit: true,
                    });
                    closeAmbModal();
                    setFormMode('add');
                });

                ambModal?.querySelectorAll('[data-amb-pick]').forEach((btn) => {
                    btn.addEventListener('click', () => {
                        if (!ambPending) return;
                        const cat = btn.dataset.ambPick;       // 'productive' | 'wasted'
                        // Save as a single block with the original label, but
                        // force the category and mark it as a manual choice
                        // so the migration loop won't reclassify it.
                        const block = add({
                            source: 'manual',
                            start: ambPending.start,
                            end: ambPending.end,
                            durationMs: ambPending.durationMs,
                            label: ambPending.label,
                            status: 'completed',
                            category: cat,
                            categoryManual: true,
                        });
                        closeAmbModal();
                        setFormMode('add');
                    });
                });

                if (saveBtn) saveBtn.addEventListener('click', handleSave);
                if (cancelBtn) cancelBtn.addEventListener('click', handleCancel);

                tbody.addEventListener('click', (e) => {
                    const categoryBtn = e.target.closest('[data-block-category]');
                    if (categoryBtn) {
                        if (!window.ChronoAuthRequire?.('change a block category')) return;
                        const tr = categoryBtn.closest('tr');
                        const id = tr?.dataset.blockId;
                        if (!id) return;
                        const block = get(id);
                        if (!block) return;
                        update(id, {
                            category: block.category === 'wasted' ? 'productive' : 'wasted',
                            categoryManual: true,
                        });
                        return;
                    }

                    const editBtn = e.target.closest('[data-block-edit]');
                    if (editBtn) {
                        if (!window.ChronoAuthRequire?.('edit a time block')) return;
                        const tr = editBtn.closest('tr');
                        const id = tr?.dataset.blockId;
                        if (!id) return;
                        const block = get(id);
                        if (!block) return;
                        const range = `${formatTime12(block.start)} – ${formatTime12(block.end)}`;
                        const reasonExcerpt = (block.label || 'Time block').slice(0, 120);
                        showConfirmModal({
                            title: 'Edit this block?',
                            lines: [
                                { text: range },
                                { text: reasonExcerpt, muted: true },
                                { text: 'The form will populate with the current values. Your changes only save when you click Update block.', muted: true },
                            ],
                            confirmText: 'Edit block',
                            tone: 'blue',
                        }).then((ok) => {
                            if (ok) setFormMode('edit', block);
                        });
                        return;
                    }

                    const deleteBtn = e.target.closest('[data-block-delete]');
                    if (!deleteBtn) return;
                    if (!window.ChronoAuthRequire?.('delete a time block')) return;
                    const tr = deleteBtn.closest('tr');
                    const id = tr?.dataset.blockId;
                    if (!id) return;
                    const block = get(id);
                    const range = block ? `${formatTime12(block.start)} – ${formatTime12(block.end)}` : '';
                    const reasonExcerpt = block ? (block.label || 'Time block').slice(0, 120) : '';
                    const isLiveCountdown = block && block.source === 'countdown'
                        && (block.status === 'active' || block.status === 'paused');

                    const lines = [];
                    if (range) lines.push({ text: range });
                    if (reasonExcerpt) lines.push({ text: reasonExcerpt, muted: true });
                    lines.push({
                        text: isLiveCountdown
                            ? 'The timer will stop and the block will be deleted. This cannot be undone.'
                            : 'This cannot be undone.',
                        muted: true,
                    });

                    showConfirmModal({
                        title: isLiveCountdown ? 'Cancel and remove this countdown?' : 'Delete this block?',
                        lines,
                        confirmText: isLiveCountdown ? 'Cancel countdown' : 'Delete',
                        tone: 'red',
                    }).then((ok) => {
                        if (!ok) return;
                        if (isLiveCountdown) {
                            const resetBtn = document.querySelector('[data-cc-reset]');
                            if (resetBtn) {
                                resetBtn.click();
                                return;
                            }
                        }
                        if (id === editingBlockId) setFormMode('add');
                        remove(id);
                    });
                });

                setFormMode('add');

                // Re-run on every local-midnight crossing so a tab left open across midnight
                // rolls yesterday's blocks out of the today-table without needing a manual
                // refresh. Form defaults also reset (when not mid-edit) to the new day's
                // current quarter-hour. Reschedules itself for the next midnight.
                const scheduleMidnightRollover = () => {
                    const now = new Date();
                    const nextMidnight = new Date(
                        now.getFullYear(),
                        now.getMonth(),
                        now.getDate() + 1,
                        0, 0, 1, 0
                    );
                    setTimeout(() => {
                        render();
                        if (editingBlockId === null) setFormMode('add');
                        scheduleMidnightRollover();
                    }, Math.max(1000, nextMidnight.getTime() - now.getTime()));
                };
                scheduleMidnightRollover();

                window.ChronoBlocks = { add, addWithSplit, update, remove, render, get, dateToHHMM };
                render();
            })();
        </script>

        <script>
            (() => {
                const input = document.querySelector('[data-end-time-input]');
                const remainingEl = document.querySelector('[data-remaining-time]');
                const untilEl = document.querySelector('[data-until-time]');
                const zoneEl = document.querySelector('[data-until-zone]');

                if (!input || !remainingEl || !untilEl) {
                    return;
                }

                const timeZone = input.dataset.timezone || 'UTC';
                const formatTime12 = (hhmm) => {
                    if (!hhmm) return '';
                    const [h, m] = hhmm.split(':').map(Number);
                    const period = h >= 12 ? 'PM' : 'AM';
                    const hour12 = h === 0 ? 12 : (h > 12 ? h - 12 : h);
                    return `${hour12}:${String(m).padStart(2, '0')} ${period}`;
                };
                const pad = (value) => String(value).padStart(2, '0');
                const formatter = new Intl.DateTimeFormat('en-US', {
                    timeZone,
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: false,
                });

                const getZonedParts = (date) => {
                    const parts = formatter.formatToParts(date);
                    const data = {};

                    for (const part of parts) {
                        if (part.type !== 'literal') {
                            data[part.type] = part.value;
                        }
                    }

                    return {
                        year: Number(data.year),
                        month: Number(data.month),
                        day: Number(data.day),
                        hour: Number(data.hour),
                        minute: Number(data.minute),
                        second: Number(data.second),
                    };
                };

                const getTimeZoneOffsetMs = (date) => {
                    const parts = getZonedParts(date);
                    const asUtc = Date.UTC(
                        parts.year,
                        parts.month - 1,
                        parts.day,
                        parts.hour,
                        parts.minute,
                        parts.second
                    );

                    return asUtc - date.getTime();
                };

                const zonedTimeToUtc = (year, month, day, hour, minute, second) => {
                    const utcGuess = Date.UTC(year, month - 1, day, hour, minute, second);
                    const offset = getTimeZoneOffsetMs(new Date(utcGuess));

                    return utcGuess - offset;
                };

                const getTargetUtc = () => {
                    const timeValue = input.value || '22:00';
                    const [hours, minutes] = timeValue.split(':').map(Number);
                    const now = new Date();
                    const nowParts = getZonedParts(now);
                    let targetUtc = zonedTimeToUtc(
                        nowParts.year,
                        nowParts.month,
                        nowParts.day,
                        hours || 0,
                        minutes || 0,
                        0
                    );

                    if (targetUtc <= now.getTime()) {
                        const localMidnightUtc = zonedTimeToUtc(
                            nowParts.year,
                            nowParts.month,
                            nowParts.day,
                            0,
                            0,
                            0
                        );
                        const nextDay = new Date(localMidnightUtc + 86400000);
                        const nextParts = getZonedParts(nextDay);
                        targetUtc = zonedTimeToUtc(
                            nextParts.year,
                            nextParts.month,
                            nextParts.day,
                            hours || 0,
                            minutes || 0,
                            0
                        );
                    }

                    return targetUtc;
                };

                const setZoneLabel = () => {
                    if (!zoneEl) {
                        return;
                    }

                    const parts = new Intl.DateTimeFormat('en-US', {
                        timeZone,
                        timeZoneName: 'short',
                    }).formatToParts(new Date());
                    const zonePart = parts.find((part) => part.type === 'timeZoneName');

                    zoneEl.textContent = zonePart?.value || timeZone;
                };

                const updateCountdown = () => {
                    const now = new Date();
                    const targetUtc = getTargetUtc();
                    const diff = Math.max(0, targetUtc - now.getTime());
                    const totalSeconds = Math.floor(diff / 1000);
                    const hours = Math.floor(totalSeconds / 3600);
                    const minutes = Math.floor((totalSeconds % 3600) / 60);
                    const seconds = totalSeconds % 60;

                    remainingEl.textContent = `${pad(hours)}:${pad(minutes)}:${pad(seconds)}`;
                    untilEl.textContent = formatTime12(input.value || '22:00');
                };

                input.addEventListener('time12:change', updateCountdown);
                setZoneLabel();
                updateCountdown();
                setInterval(updateCountdown, 1000);
            })();
        </script>

        <script>
            (() => {
                const STORAGE_KEY = 'chrono.customCountdown.v1';

                const daysInput = document.querySelector('[data-cc-days]');
                const hoursInput = document.querySelector('[data-cc-hours]');
                const minutesInput = document.querySelector('[data-cc-minutes]');
                const secondsInput = document.querySelector('[data-cc-seconds]');
                const labelInput = document.querySelector('[data-cc-label]');
                const startBtn = document.querySelector('[data-cc-start]');
                const pauseBtn = document.querySelector('[data-cc-pause]');
                const resetBtn = document.querySelector('[data-cc-reset]');
                const timeEl = document.querySelector('[data-cc-time]');
                const statusEl = document.querySelector('[data-cc-status]');
                const labelDisplay = document.querySelector('[data-cc-display-label]');
                const displayWrap = document.querySelector('[data-cc-display]');

                if (!daysInput || !startBtn || !timeEl) return;

                const numericInputs = [daysInput, hoursInput, minutesInput, secondsInput];
                const pad = (n) => String(n).padStart(2, '0');

                let audioCtx = null;
                let audioUnlocked = false;

                const ensureAudio = () => {
                    const Ctx = window.AudioContext || window.webkitAudioContext;
                    if (!Ctx) return null;
                    if (!audioCtx) {
                        try {
                            audioCtx = new Ctx();
                        } catch {
                            audioCtx = null;
                            return null;
                        }
                    }
                    if (audioCtx.state === 'suspended') {
                        audioCtx.resume().catch(() => {});
                    }
                    audioUnlocked = true;
                    return audioCtx;
                };

                const unlockOnFirstGesture = () => {
                    if (audioUnlocked) return;
                    ensureAudio();
                };
                ['pointerdown', 'keydown', 'touchstart'].forEach((evt) => {
                    window.addEventListener(evt, unlockOnFirstGesture, { once: true, passive: true });
                });

                const CHIME_NOTES = [
                    { freq: 523.25, start: 0.00 }, // C5
                    { freq: 659.25, start: 0.25 }, // E5
                    { freq: 783.99, start: 0.50 }, // G5
                ];
                const CHIME_BURST_MS = 3500;

                let chimeMaster = null;
                let chimeTimeoutId = null;

                const playOneChimeBurst = () => {
                    if (!audioCtx || !chimeMaster) return;
                    const now = audioCtx.currentTime;
                    for (const note of CHIME_NOTES) {
                        const osc = audioCtx.createOscillator();
                        const gain = audioCtx.createGain();
                        osc.type = 'sine';
                        osc.frequency.value = note.freq;
                        const t0 = now + note.start;
                        gain.gain.setValueAtTime(0.0001, t0);
                        gain.gain.exponentialRampToValueAtTime(0.55, t0 + 0.18);
                        gain.gain.exponentialRampToValueAtTime(0.0001, t0 + 2.6);
                        osc.connect(gain).connect(chimeMaster);
                        osc.start(t0);
                        osc.stop(t0 + 2.7);
                    }
                };

                const stopChimeLoop = () => {
                    if (chimeTimeoutId !== null) {
                        clearTimeout(chimeTimeoutId);
                        chimeTimeoutId = null;
                    }
                    if (chimeMaster && audioCtx) {
                        const node = chimeMaster;
                        chimeMaster = null;
                        const now = audioCtx.currentTime;
                        try {
                            node.gain.cancelScheduledValues(now);
                            node.gain.setValueAtTime(node.gain.value, now);
                            node.gain.linearRampToValueAtTime(0.0001, now + 0.15);
                        } catch {}
                        setTimeout(() => {
                            try { node.disconnect(); } catch {}
                        }, 250);
                    } else {
                        chimeMaster = null;
                    }
                };

                const startChimeLoop = () => {
                    const ctx = ensureAudio();
                    if (!ctx) return;
                    stopChimeLoop();
                    chimeMaster = ctx.createGain();
                    chimeMaster.gain.setValueAtTime(0.0001, ctx.currentTime);
                    chimeMaster.gain.linearRampToValueAtTime(0.22, ctx.currentTime + 0.05);
                    chimeMaster.connect(ctx.destination);

                    const loop = () => {
                        playOneChimeBurst();
                        chimeTimeoutId = setTimeout(loop, CHIME_BURST_MS);
                    };
                    loop();
                };

                let state = { mode: 'idle', deadline: null, remainingMs: 0, label: '', blockId: null };
                let tickHandle = null;

                const pad2 = (n) => String(n).padStart(2, '0');
                const dateToHHMM = (d) => `${pad2(d.getHours())}:${pad2(d.getMinutes())}`;

                const loadState = () => {
                    try {
                        const raw = localStorage.getItem(STORAGE_KEY);
                        if (!raw) return null;
                        const parsed = JSON.parse(raw);
                        if (!parsed || typeof parsed !== 'object') return null;
                        return parsed;
                    } catch {
                        return null;
                    }
                };

                const saveState = () => {
                    try {
                        if (state.mode === 'idle') {
                            localStorage.removeItem(STORAGE_KEY);
                        } else {
                            localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
                        }
                    } catch {
                        /* storage may be disabled — proceed without persistence */
                    }
                };

                const readInputDuration = () => {
                    const days = Math.max(0, Math.floor(Number(daysInput.value) || 0));
                    const hours = Math.max(0, Math.floor(Number(hoursInput.value) || 0));
                    const minutes = Math.max(0, Math.floor(Number(minutesInput.value) || 0));
                    const seconds = Math.max(0, Math.floor(Number(secondsInput.value) || 0));
                    return ((days * 24 + hours) * 60 + minutes) * 60 * 1000 + seconds * 1000;
                };

                const formatDuration = (ms) => {
                    const totalSeconds = Math.max(0, Math.ceil(ms / 1000));
                    const days = Math.floor(totalSeconds / 86400);
                    const hours = Math.floor((totalSeconds % 86400) / 3600);
                    const minutes = Math.floor((totalSeconds % 3600) / 60);
                    const seconds = totalSeconds % 60;
                    const time = `${pad(hours)}:${pad(minutes)}:${pad(seconds)}`;
                    return days > 0 ? `${days}d ${time}` : time;
                };

                const getRemainingMs = () => {
                    if (state.mode === 'running') {
                        return Math.max(0, state.deadline - Date.now());
                    }
                    if (state.mode === 'paused' || state.mode === 'finished') {
                        return state.remainingMs;
                    }
                    return readInputDuration();
                };

                const render = () => {
                    const remaining = getRemainingMs();
                    timeEl.textContent = formatDuration(remaining);

                    statusEl.classList.remove('text-rose-400', 'text-amber-300', 'text-slate-300');
                    displayWrap.classList.remove('text-rose-400', 'animate-pulse');

                    if (state.mode === 'running') {
                        statusEl.textContent = 'LEFT';
                        statusEl.classList.add('text-slate-300');
                    } else if (state.mode === 'paused') {
                        statusEl.textContent = 'PAUSED';
                        statusEl.classList.add('text-amber-300');
                    } else if (state.mode === 'finished') {
                        statusEl.textContent = "TIME'S UP";
                        statusEl.classList.add('text-rose-400');
                        displayWrap.classList.add('text-rose-400');
                    } else {
                        statusEl.textContent = remaining > 0 ? 'READY' : 'IDLE';
                        statusEl.classList.add('text-slate-300');
                    }

                    labelDisplay.textContent = state.label || '';

                    const hasInput = readInputDuration() > 0;
                    startBtn.disabled =
                        state.mode === 'running' ||
                        state.mode === 'finished' ||
                        (state.mode === 'idle' && !hasInput);
                    startBtn.textContent = state.mode === 'paused' ? 'Resume' : 'Start';
                    pauseBtn.disabled = state.mode !== 'running';
                    resetBtn.disabled = state.mode === 'idle';
                    resetBtn.textContent = state.mode === 'finished' ? 'Stop' : 'Reset';

                    const lock = state.mode === 'running' || state.mode === 'paused';
                    for (const el of numericInputs) el.disabled = lock;
                    labelInput.disabled = lock;
                };

                const stopTicking = () => {
                    if (tickHandle !== null) {
                        clearInterval(tickHandle);
                        tickHandle = null;
                    }
                };

                const tick = () => {
                    if (state.mode !== 'running') {
                        stopTicking();
                        return;
                    }
                    if (state.deadline - Date.now() <= 0) {
                        if (window.ChronoBlocks && state.blockId) {
                            window.ChronoBlocks.update(state.blockId, {
                                status: 'completed',
                                end: dateToHHMM(new Date()),
                            });
                        }
                        state = { mode: 'finished', deadline: null, remainingMs: 0, label: state.label, blockId: state.blockId };
                        saveState();
                        stopTicking();
                        startChimeLoop();
                        render();
                        return;
                    }
                    render();
                };

                const startTicking = () => {
                    stopTicking();
                    tickHandle = setInterval(tick, 250);
                };

                const ONE_HOUR_MS = 60 * 60 * 1000;
                const ccErrorEl = document.querySelector('[data-cc-error]');
                const showCcError = (msg) => {
                    if (!ccErrorEl) return;
                    ccErrorEl.textContent = msg;
                    ccErrorEl.classList.remove('hidden');
                };
                const clearCcError = () => {
                    if (ccErrorEl) ccErrorEl.classList.add('hidden');
                };

                const handleStart = () => {
                    ensureAudio();
                    let durationMs;
                    let label;
                    let blockId = state.blockId;

                    if (state.mode === 'paused') {
                        durationMs = state.remainingMs;
                        label = state.label;
                        if (window.ChronoBlocks && blockId) {
                            const newEnd = new Date(Date.now() + durationMs);
                            window.ChronoBlocks.update(blockId, {
                                status: 'active',
                                end: dateToHHMM(newEnd),
                            });
                        }
                    } else {
                        durationMs = readInputDuration();
                        if (durationMs <= 0) return;
                        if (durationMs > ONE_HOUR_MS) {
                            showCcError('Custom countdown is capped at 1 hour. Reduce the inputs and try again.');
                            return;
                        }
                        clearCcError();
                        label = (labelInput.value || '').trim();
                        if (window.ChronoBlocks) {
                            const startDate = new Date();
                            const endDate = new Date(startDate.getTime() + durationMs);
                            const block = window.ChronoBlocks.add({
                                source: 'countdown',
                                start: dateToHHMM(startDate),
                                end: dateToHHMM(endDate),
                                durationMs,
                                label: label || 'Custom countdown',
                                status: 'active',
                            });
                            blockId = block.id;
                        }
                    }

                    if (durationMs <= 0) return;
                    state = {
                        mode: 'running',
                        deadline: Date.now() + durationMs,
                        remainingMs: 0,
                        label,
                        blockId,
                    };
                    saveState();
                    startTicking();
                    render();
                };

                const handlePause = () => {
                    if (state.mode !== 'running') return;
                    if (window.ChronoBlocks && state.blockId) {
                        window.ChronoBlocks.update(state.blockId, { status: 'paused' });
                    }
                    state = {
                        mode: 'paused',
                        deadline: null,
                        remainingMs: Math.max(0, state.deadline - Date.now()),
                        label: state.label,
                        blockId: state.blockId,
                    };
                    saveState();
                    stopTicking();
                    render();
                };

                const handleReset = () => {
                    const wasUncompleted = state.mode === 'running' || state.mode === 'paused';
                    stopChimeLoop();
                    if (window.ChronoBlocks && state.blockId && wasUncompleted) {
                        window.ChronoBlocks.remove(state.blockId);
                    }
                    state = { mode: 'idle', deadline: null, remainingMs: 0, label: '', blockId: null };
                    saveState();
                    stopTicking();
                    render();
                };

                startBtn.addEventListener('click', handleStart);
                pauseBtn.addEventListener('click', handlePause);
                resetBtn.addEventListener('click', handleReset);

                for (const el of numericInputs) {
                    el.addEventListener('input', () => {
                        clearCcError();
                        if (state.mode === 'idle') render();
                    });
                }
                labelInput?.addEventListener('input', clearCcError);

                const stored = loadState();
                if (stored && stored.mode === 'running' && typeof stored.deadline === 'number') {
                    if (stored.deadline - Date.now() <= 0) {
                        if (window.ChronoBlocks && stored.blockId) {
                            window.ChronoBlocks.update(stored.blockId, {
                                status: 'completed',
                                end: dateToHHMM(new Date(stored.deadline)),
                            });
                        }
                        state = { mode: 'finished', deadline: null, remainingMs: 0, label: stored.label || '', blockId: stored.blockId || null };
                        saveState();
                    } else {
                        state = {
                            mode: 'running',
                            deadline: stored.deadline,
                            remainingMs: 0,
                            label: stored.label || '',
                            blockId: stored.blockId || null,
                        };
                        startTicking();
                    }
                } else if (stored && stored.mode === 'paused' && typeof stored.remainingMs === 'number') {
                    state = {
                        mode: 'paused',
                        deadline: null,
                        remainingMs: Math.max(0, stored.remainingMs),
                        label: stored.label || '',
                        blockId: stored.blockId || null,
                    };
                } else if (stored && stored.mode === 'finished') {
                    state = { mode: 'finished', deadline: null, remainingMs: 0, label: stored.label || '', blockId: stored.blockId || null };
                }

                render();
            })();
        </script>

        <script>
            (() => {
                // Hourly check-in only runs for signed-in users — guests would otherwise
                // be nagged every hour to log time they can't actually save.
                if (!window.ChronoAuth?.isAuthenticated) return;

                const modal = document.getElementById('hourly_modal');
                const fromEl = modal?.querySelector('[data-hourly-from]');
                const toEl = modal?.querySelector('[data-hourly-to]');
                const inputEl = document.getElementById('hourly_modal_input');
                const saveBtn = document.getElementById('hourly_modal_save');
                const skipBtn = document.getElementById('hourly_modal_skip');
                if (!modal || !fromEl || !toEl || !inputEl || !saveBtn || !skipBtn) return;

                const cfg = window.ChronoDashboardConfig || {};
                const BLOCKS_KEY = 'chrono.timeBlocks.v1';
                const PROMPT_STATE_KEY = 'chrono.hourlyPromptState.v1';
                const PROMPT_FOCUS_KEY = 'chrono.hourlyPromptFocus.v1';
                // Min gap that's worth interrupting the user for. Anything shorter
                // is treated as noise (you bouncing between blocks for a minute).
                const MIN_PROMPT_MINUTES = 5;
                // Prompt cadence ramps up near bedtime.
                const DEFAULT_PROMPT_DELAY_MS = 60 * 60 * 1000;
                const BEDTIME_WINDOW_MINUTES = 120;
                const BEDTIME_PROMPT_DELAY_MS = 30 * 60 * 1000;
                const FINAL_WINDOW_MINUTES = 20;
                const FINAL_PROMPT_DELAY_MS = 5 * 60 * 1000;
                // Cap the catch-up queue so a 12-hour absence doesn't queue 12
                // popups; anything older than this is left to manual logging.
                const MAX_QUEUE_DEPTH = 24;

                const pad = (n) => String(n).padStart(2, '0');
                const formatTime12 = (date) => {
                    const h = date.getHours();
                    const m = date.getMinutes();
                    const period = h >= 12 ? 'PM' : 'AM';
                    const hour12 = h === 0 ? 12 : (h > 12 ? h - 12 : h);
                    return `${hour12}:${pad(m)} ${period}`;
                };
                const dateToHHMM = (d) => `${pad(d.getHours())}:${pad(d.getMinutes())}`;
                const hhmmToMinutes = (hhmm) => {
                    if (!hhmm) return null;
                    const [h, m] = hhmm.split(':').map(Number);
                    return h * 60 + m;
                };
                const localDateString = (d) =>
                    `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
                const rangeKey = (date, startMin, endMin) =>
                    `${localDateString(date)}_${startMin}_${endMin}`;

                const getEndOfDayDate = (now) => {
                    const [eh, em] = String(cfg.endTime || '22:00').split(':').map(Number);
                    const end = new Date(now);
                    end.setHours(eh || 0, em || 0, 0, 0);
                    return end;
                };
                const minutesToEndOfDay = (now) =>
                    Math.round((getEndOfDayDate(now).getTime() - now.getTime()) / 60000);
                const getPromptDelayMs = (now) => {
                    const mins = minutesToEndOfDay(now);
                    if (mins <= FINAL_WINDOW_MINUTES) return FINAL_PROMPT_DELAY_MS;
                    if (mins <= BEDTIME_WINDOW_MINUTES) return BEDTIME_PROMPT_DELAY_MS;
                    return DEFAULT_PROMPT_DELAY_MS;
                };

                const loadBlocks = () => {
                    try {
                        const raw = localStorage.getItem(BLOCKS_KEY);
                        if (!raw) return [];
                        const parsed = JSON.parse(raw);
                        return Array.isArray(parsed) ? parsed : [];
                    } catch { return []; }
                };
                const loadPromptState = () => {
                    try {
                        const raw = localStorage.getItem(PROMPT_STATE_KEY);
                        const parsed = raw ? JSON.parse(raw) : {};
                        return parsed && typeof parsed === 'object' ? parsed : {};
                    } catch { return {}; }
                };
                const savePromptState = (state) => {
                    try { localStorage.setItem(PROMPT_STATE_KEY, JSON.stringify(state)); } catch {}
                };
                const markPromptSeen = (key, timestamp = Date.now()) => {
                    if (!key) return;
                    const state = loadPromptState();
                    const prev = state[key] || {};
                    state[key] = {
                        lastPromptAt: timestamp,
                        attempts: (prev.attempts || 0) + 1,
                    };
                    savePromptState(state);
                };
                const touchPromptTimestamp = (key, timestamp = Date.now()) => {
                    if (!key) return;
                    const state = loadPromptState();
                    const prev = state[key] || { attempts: 0 };
                    state[key] = { lastPromptAt: timestamp, attempts: prev.attempts || 0 };
                    savePromptState(state);
                };
                const clearPromptState = (key) => {
                    if (!key) return;
                    const state = loadPromptState();
                    if (state[key]) {
                        delete state[key];
                        savePromptState(state);
                    }
                };
                const getFocusKey = () => {
                    try { return localStorage.getItem(PROMPT_FOCUS_KEY) || null; } catch { return null; }
                };
                const setFocusKey = (key) => {
                    try {
                        if (key) localStorage.setItem(PROMPT_FOCUS_KEY, key);
                        else localStorage.removeItem(PROMPT_FOCUS_KEY);
                    } catch {}
                };

                // Subtract every existing block on `dayKey` from the set of
                // intervals and return the remaining unlogged intervals.
                // Active/paused blocks are treated as covering [start, now] so
                // we don't prompt for time the user is actively timing.
                const subtractBlocks = (intervals, dayKey, now) => {
                    const blocks = loadBlocks().filter(b => b.date === dayKey);
                    const nowMin = now.getHours() * 60 + now.getMinutes();
                    const ranges = blocks.map(b => {
                        const s = hhmmToMinutes(b.start);
                        let e = hhmmToMinutes(b.end);
                        if (e === null || b.status === 'active' || b.status === 'paused') {
                            e = nowMin;
                        }
                        if (s === null || e === null || e <= s) return null;
                        return [s, e];
                    }).filter(Boolean);

                    let remaining = intervals.slice();
                    for (const [bs, be] of ranges) {
                        const next = [];
                        for (const [s, e] of remaining) {
                            if (be <= s || bs >= e) {
                                next.push([s, e]);
                            } else {
                                if (bs > s) next.push([s, bs]);
                                if (be < e) next.push([be, e]);
                            }
                        }
                        remaining = next;
                    }
                    return remaining;
                };

                // Build the queue of unlogged ranges across completed past hours
                // since wake time, capped at MAX_QUEUE_DEPTH (most-recent first).
                const buildPromptQueue = (now) => {
                    const dayKey = localDateString(now);
                    const wakeMin = hhmmToMinutes(cfg.wakeTime || '07:00') ?? 420;
                    const currentHour = now.getHours();
                    const lastCompletedHour = currentHour;   // hour `currentHour-1` is the last fully-elapsed hour
                    const queue = [];

                    // Walk backward from the most recent completed hour so the
                    // most relevant prompt is shown first.
                    for (let h = lastCompletedHour - 1; h >= 0 && queue.length < MAX_QUEUE_DEPTH; h--) {
                        const hourStartMin = h * 60;
                        const hourEndMin = (h + 1) * 60;
                        if (hourEndMin <= wakeMin) break;        // before wake → done
                        const startMin = Math.max(hourStartMin, wakeMin);
                        if (startMin >= hourEndMin) continue;

                        const gaps = subtractBlocks([[startMin, hourEndMin]], dayKey, now);
                        for (const [s, e] of gaps) {
                            if (e - s < MIN_PROMPT_MINUTES) continue;
                            const key = rangeKey(now, s, e);
                            queue.push({
                                key,
                                startMin: s,
                                endMin: e,
                                start: new Date(now.getFullYear(), now.getMonth(), now.getDate(), Math.floor(s/60), s%60, 0, 0),
                                end: new Date(now.getFullYear(), now.getMonth(), now.getDate(), Math.floor(e/60), e%60, 0, 0),
                            });
                        }
                    }
                    // Most recent → oldest is the natural ask order.
                    return queue;
                };

                    const shouldPromptNow = (entry, now, state) => {
                        const meta = state[entry.key];
                        if (!meta || !meta.lastPromptAt) return true;
                        return (now.getTime() - meta.lastPromptAt) >= getPromptDelayMs(now);
                    };

                    const pickNextPrompt = (now) => {
                        const queue = buildPromptQueue(now);
                        if (queue.length === 0) return null;
                        const state = loadPromptState();
                        const focusKey = getFocusKey();

                        if (focusKey) {
                            const focused = queue.find((q) => q.key === focusKey);
                            if (!focused) {
                                setFocusKey(null);
                            } else {
                                return shouldPromptNow(focused, now, state) ? focused : null;
                            }
                        }

                        for (const entry of queue) {
                            if (shouldPromptNow(entry, now, state)) return entry;
                        }
                        return null;
                    };

                    const autoFillUnlogged = (now) => {
                        const endToday = getEndOfDayDate(now);
                        if (now.getTime() < endToday.getTime()) return false;

                        const dayKey = localDateString(now);
                        const wakeMin = hhmmToMinutes(cfg.wakeTime || '07:00') ?? 420;
                        const endMin = hhmmToMinutes(cfg.endTime || '22:00') ?? 1320;
                        if (endMin <= wakeMin) return false;

                        const gaps = subtractBlocks([[wakeMin, endMin]], dayKey, endToday);
                        if (!window.ChronoBlocks || gaps.length === 0) return false;

                        let added = false;
                        for (const [s, e] of gaps) {
                            if (e - s < MIN_PROMPT_MINUTES) continue;
                            const startDate = new Date(now.getFullYear(), now.getMonth(), now.getDate(), Math.floor(s / 60), s % 60, 0, 0);
                            const endDate = new Date(now.getFullYear(), now.getMonth(), now.getDate(), Math.floor(e / 60), e % 60, 0, 0);
                            window.ChronoBlocks.add({
                                source: 'auto',
                                start: dateToHHMM(startDate),
                                end: dateToHHMM(endDate),
                                durationMs: endDate.getTime() - startDate.getTime(),
                                label: 'Unlogged (auto)',
                                status: 'completed',
                                category: 'wasted',
                                categoryManual: true,
                                auto_filled: true,
                                date: dayKey,
                            });
                            added = true;
                        }

                        if (added) {
                            setFocusKey(null);
                        }
                        return added;
                    };

                let currentKey = null;
                let currentStart = null;
                let currentEnd = null;
                let modalOpen = false;
                let nextPromptTimer = null;

                const onKey = (e) => {
                    if (e.key === 'Escape') {
                        e.preventDefault();
                        handleSkip();
                    } else if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                        e.preventDefault();
                        handleSave();
                    }
                };

                const closeModal = () => {
                    modal.classList.remove('flex');
                    modal.classList.add('hidden');
                    modal.setAttribute('aria-hidden', 'true');
                    modalOpen = false;
                    currentKey = null;
                    currentStart = null;
                    currentEnd = null;
                    document.removeEventListener('keydown', onKey);
                };

                const openModal = (entry) => {
                    if (modalOpen) return;
                    currentKey = entry.key;
                    currentStart = entry.start;
                    currentEnd = entry.end;
                    setFocusKey(entry.key);
                    markPromptSeen(entry.key);
                    fromEl.textContent = formatTime12(entry.start);
                    toEl.textContent = formatTime12(entry.end);
                    inputEl.value = '';
                    saveBtn.disabled = true;
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                    modal.setAttribute('aria-hidden', 'false');
                    modalOpen = true;
                    setTimeout(() => inputEl.focus(), 50);
                    document.addEventListener('keydown', onKey);
                };

                const showNextPrompt = () => {
                    if (modalOpen) return;
                    if (nextPromptTimer) {
                        clearTimeout(nextPromptTimer);
                        nextPromptTimer = null;
                    }

                    const now = new Date();
                    if (autoFillUnlogged(now)) return;

                    const entry = pickNextPrompt(now);
                    if (!entry) return;
                    openModal(entry);
                };

                const scheduleNext = (immediate = false) => {
                    if (nextPromptTimer) clearTimeout(nextPromptTimer);
                    const delay = immediate ? 600 : getPromptDelayMs(new Date());
                    nextPromptTimer = setTimeout(showNextPrompt, delay);
                };

                const handleSkip = () => {
                    touchPromptTimestamp(currentKey);
                    closeModal();
                    scheduleNext();              // wait prompt delay, then ask again
                };

                const handleSave = () => {
                    const text = inputEl.value.trim();
                    if (!text) return;
                    if (window.ChronoBlocks && currentStart && currentEnd) {
                        const payload = {
                            source: 'manual',
                            start: dateToHHMM(currentStart),
                            end: dateToHHMM(currentEnd),
                            durationMs: currentEnd.getTime() - currentStart.getTime(),
                            label: text,
                            status: 'completed',
                            allowSplit: true,
                        };
                        if (window.ChronoBlocks.addWithSplit) {
                            window.ChronoBlocks.addWithSplit(payload);
                        } else {
                            window.ChronoBlocks.add(payload);
                        }
                    }
                    clearPromptState(currentKey);
                    setFocusKey(null);
                    closeModal();
                    scheduleNext();              // saving covers this gap; move on after delay
                };

                saveBtn.addEventListener('click', handleSave);
                skipBtn.addEventListener('click', handleSkip);
                inputEl.addEventListener('input', () => {
                    saveBtn.disabled = inputEl.value.trim() === '';
                });

                // Initial fire: a couple of seconds after page load (don't
                // race the rest of the dashboard scripts), then also on a
                // minute-edge tick so newly-completed hours get caught.
                setTimeout(showNextPrompt, 5000);
                const now = new Date();
                const msUntilNextMinute = 60000 - (now.getTime() % 60000);
                setTimeout(() => {
                    showNextPrompt();
                    setInterval(() => {
                        if (!modalOpen) showNextPrompt();
                    }, 60000);
                }, msUntilNextMinute);
            })();
        </script>

        <script>
            (() => {
                const BLOCKS_KEY = 'chrono.timeBlocks.v1';
                const GOAL_KEY_PREFIX = 'chrono.todayGoal.';
                const cfg = window.ChronoDashboardConfig || {};
                const endTime = cfg.endTime || '22:00';
                const wakeTime = cfg.wakeTime || '07:00';
                let signupTs = null;
                if (cfg.signupTimestamp) {
                    const d = new Date(cfg.signupTimestamp);
                    if (!isNaN(d.getTime())) signupTs = d;
                }
                const signupDateLabel = cfg.signupDateLabel || '';

                const pad = (n) => String(n).padStart(2, '0');
                const localDateString = (d = new Date()) =>
                    `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
                const hhmmToMins = (hhmm) => {
                    const [h, m] = hhmm.split(':').map(Number);
                    return h * 60 + m;
                };
                const formatDuration = (ms) => {
                    const totalMin = Math.max(0, Math.round(ms / 60000));
                    if (totalMin === 0) return '0m';
                    if (totalMin < 60) return `${totalMin}m`;
                    const h = Math.floor(totalMin / 60);
                    const m = totalMin % 60;
                    return m === 0 ? `${h}h` : `${h}h ${m}m`;
                };
                const formatHours = (ms) => {
                    const hours = ms / 3600000;
                    if (hours < 1) return `${Math.round(ms / 60000)}m`;
                    if (hours < 10) return `${hours.toFixed(1)}h`;
                    return `${Math.round(hours)}h`;
                };
                const escapeHtml = (str) => String(str ?? '').replace(/[&<>"']/g, (c) => ({
                    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
                }[c]));

                const loadBlocks = () => {
                    try {
                        const raw = localStorage.getItem(BLOCKS_KEY);
                        if (!raw) return [];
                        const parsed = JSON.parse(raw);
                        return Array.isArray(parsed) ? parsed : [];
                    } catch {
                        return [];
                    }
                };

                const startOfWeek = (d) => {
                    const r = new Date(d.getFullYear(), d.getMonth(), d.getDate());
                    const day = r.getDay();
                    const diff = day === 0 ? 6 : day - 1;
                    r.setDate(r.getDate() - diff);
                    return r;
                };
                const startOfMonth = (d) => new Date(d.getFullYear(), d.getMonth(), 1);
                const startOfYear = (d) => new Date(d.getFullYear(), 0, 1);
                const endOfWeek = (d) => {
                    const s = startOfWeek(d);
                    return new Date(s.getFullYear(), s.getMonth(), s.getDate() + 7);
                };
                const endOfMonth = (d) => new Date(d.getFullYear(), d.getMonth() + 1, 1);
                const endOfYear = (d) => new Date(d.getFullYear() + 1, 0, 1);
                const formatRange = (start, endExclusive) => {
                    const opt = { month: 'short', day: 'numeric' };
                    const lastDay = new Date(endExclusive.getTime() - 1);
                    return `${start.toLocaleDateString('en-US', opt)} – ${lastDay.toLocaleDateString('en-US', opt)}`;
                };

                // ── Today's goals: multi-goal panel ───────────────────────
                // Storage:
                //   v1 (legacy):  chrono.todayGoal.{YYYY-MM-DD} = {text, done}
                //   v2 (current): chrono.todayGoals.v2.{YYYY-MM-DD}
                //                 = [{id, text, done, completedFrom, completedTo, completedAt}]
                // v1 is auto-migrated to a single-element v2 array on first
                // load. v1 keys are NOT deleted (so any other view still
                // showing them keeps working) — v2 is the source of truth.
                const GOALS_V1_PREFIX = GOAL_KEY_PREFIX;            // 'chrono.todayGoal.'
                const GOALS_V2_PREFIX = 'chrono.todayGoals.v2.';
                const goalsV1Key = (date) => `${GOALS_V1_PREFIX}${date}`;
                const goalsV2Key = (date) => `${GOALS_V2_PREFIX}${date}`;

                const newGoalId = () =>
                    `g_${Date.now()}_${Math.random().toString(36).slice(2, 7)}`;

                const loadGoals = (date = localDateString()) => {
                    try {
                        const raw = localStorage.getItem(goalsV2Key(date));
                        if (raw) {
                            const arr = JSON.parse(raw);
                            return Array.isArray(arr) ? arr : [];
                        }
                    } catch {}
                    // Fall back to v1 — migrate to v2 immediately so we
                    // never lose user data if the migration runs late.
                    try {
                        const rawV1 = localStorage.getItem(goalsV1Key(date));
                        if (rawV1) {
                            const obj = JSON.parse(rawV1);
                            if (obj && typeof obj === 'object') {
                                const migrated = obj.text
                                    ? [{
                                          id: newGoalId(),
                                          text: obj.text,
                                          done: !!obj.done,
                                          completedFrom: null,
                                          completedTo: null,
                                          completedAt: null,
                                      }]
                                    : [];
                                if (migrated.length > 0) {
                                    localStorage.setItem(goalsV2Key(date), JSON.stringify(migrated));
                                }
                                return migrated;
                            }
                        }
                    } catch {}
                    return [];
                };

                const saveGoals = (goals, date = localDateString()) => {
                    try {
                        // Persist goals as-is. Cleanup of empty cards is the
                        // blur handler's job — filtering here would silently
                        // drop a freshly-added empty card before the user
                        // even types in it (broke "+ Add another goal").
                        const arr = Array.isArray(goals) ? goals.filter(Boolean) : [];
                        if (arr.length === 0) {
                            localStorage.removeItem(goalsV2Key(date));
                        } else {
                            localStorage.setItem(goalsV2Key(date), JSON.stringify(arr));
                        }
                        // Keep v1 mirror updated with the *first* goal so any
                        // legacy display surface keeps working.
                        if (arr.length > 0) {
                            localStorage.setItem(goalsV1Key(date), JSON.stringify({
                                text: arr[0].text || '',
                                done: !!arr[0].done,
                            }));
                        } else {
                            localStorage.removeItem(goalsV1Key(date));
                        }
                    } catch {}
                };

                // ── DOM refs ──────────────────────────────────────────────
                const goalsListEl = document.querySelector('[data-goals-list]');
                const goalsTemplate = document.getElementById('todays_goal_template');
                const goalsAddBtn = document.querySelector('[data-goal-add]');
                const goalsStatsEl = document.querySelector('[data-goals-stats]');
                const goalsReminderEl = document.querySelector('[data-goals-reminder]');
                const goalsReminderIcon = document.querySelector('[data-goals-reminder-icon]');
                const goalsReminderText = document.querySelector('[data-goals-reminder-text]');
                const goalsBedtimeEl = document.querySelector('[data-goals-bedtime]');

                // Done modal refs.
                const completeModal = document.getElementById('goal_complete_modal');
                const completeText = completeModal?.querySelector('[data-goal-complete-text]');
                const completeFromInput = document.getElementById('goal_complete_from');
                const completeToInput = document.getElementById('goal_complete_to');
                const completeFromHidden = document.getElementById('goal_complete_from_value');
                const completeToHidden = document.getElementById('goal_complete_to_value');
                const completeError = document.getElementById('goal_complete_error');
                const completeCancelBtn = document.getElementById('goal_complete_cancel');
                const completeSaveBtn = document.getElementById('goal_complete_save');

                let pendingCompleteId = null;

                if (goalsListEl && goalsTemplate) {
                    const isAuthed = () => !!window.ChronoAuth?.isAuthenticated;

                    // Auto-grow each textarea as content is typed so multi-line
                    // goals always show their full content.
                    const autoGrow = (ta, counter) => {
                        ta.style.height = 'auto';
                        ta.style.height = ta.scrollHeight + 'px';
                        if (counter) counter.textContent = String(ta.value.length);
                    };

                    // Format an "HH:MM" → "9:00 AM"-style label.
                    const fmt12 = (hhmm) => {
                        if (!hhmm) return '';
                        const [h, m] = hhmm.split(':').map(Number);
                        const period = h >= 12 ? 'PM' : 'AM';
                        const h12 = h === 0 ? 12 : (h > 12 ? h - 12 : h);
                        return `${h12}:${pad(m)} ${period}`;
                    };

                    // Apply done-state styling and reveal the completion-window chip.
                    const applyGoalDoneStyle = (card, goal) => {
                        const ta = card.querySelector('[data-goal-text]');
                        const accent = card.querySelector('[data-goal-accent]');
                        const doneBtn = card.querySelector('[data-goal-done]');
                        const doneLabel = card.querySelector('[data-goal-done-label]');
                        const chip = card.querySelector('[data-goal-completed-chip]');
                        const completedWindow = card.querySelector('[data-goal-completed-window]');
                        const isDone = !!goal.done;
                        if (isDone) {
                            ta.classList.add('line-through', 'opacity-60');
                            card.classList.remove('border-slate-700/60');
                            card.classList.add('border-emerald-500/40', 'bg-emerald-500/[0.04]');
                            if (accent) {
                                accent.classList.remove('bg-slate-700/60');
                                accent.classList.add('bg-emerald-400');
                            }
                            if (doneBtn) {
                                doneBtn.classList.remove('border-slate-700', 'text-slate-300', 'hover:border-emerald-500/60', 'hover:bg-emerald-500/10', 'hover:text-emerald-300');
                                doneBtn.classList.add('border-emerald-500/40', 'bg-emerald-500/10', 'text-emerald-300');
                                doneBtn.title = 'Click to undo';
                            }
                            if (doneLabel) doneLabel.textContent = 'Done';
                        } else {
                            ta.classList.remove('line-through', 'opacity-60');
                            card.classList.remove('border-emerald-500/40', 'bg-emerald-500/[0.04]');
                            card.classList.add('border-slate-700/60');
                            if (accent) {
                                accent.classList.remove('bg-emerald-400');
                                accent.classList.add('bg-slate-700/60');
                            }
                            if (doneBtn) {
                                doneBtn.classList.remove('border-emerald-500/40', 'bg-emerald-500/10', 'text-emerald-300');
                                doneBtn.classList.add('border-slate-700', 'text-slate-300', 'hover:border-emerald-500/60', 'hover:bg-emerald-500/10', 'hover:text-emerald-300');
                                doneBtn.title = 'Mark this goal complete';
                            }
                            if (doneLabel) doneLabel.textContent = 'Done';
                        }
                        if (chip && completedWindow) {
                            if (isDone && goal.completedFrom && goal.completedTo) {
                                completedWindow.textContent = `${fmt12(goal.completedFrom)} – ${fmt12(goal.completedTo)}`;
                                chip.classList.remove('hidden');
                                chip.classList.add('inline-flex');
                            } else {
                                chip.classList.add('hidden');
                                chip.classList.remove('inline-flex');
                                completedWindow.textContent = '';
                            }
                        }
                    };

                    const renderGoals = () => {
                        const goals = loadGoals();
                        // Always keep at least one card visible so the panel
                        // doesn't collapse to nothing.
                        if (goals.length === 0) {
                            goals.push({
                                id: newGoalId(), text: '', done: false,
                                completedFrom: null, completedTo: null, completedAt: null,
                            });
                        }
                        goalsListEl.innerHTML = '';
                        goals.forEach((goal, idx) => {
                            const card = goalsTemplate.content.firstElementChild.cloneNode(true);
                            card.dataset.goalId = goal.id;
                            const idxEl = card.querySelector('[data-goal-index]');
                            const ta = card.querySelector('[data-goal-text]');
                            const counter = card.querySelector('[data-goal-count]');
                            const doneBtn = card.querySelector('[data-goal-done]');
                            const deleteBtn = card.querySelector('[data-goal-delete]');
                            const emptyHint = card.querySelector('[data-goal-empty-hint]');

                            if (idxEl) idxEl.textContent = `Goal ${idx + 1}`;
                            ta.value = goal.text || '';
                            applyGoalDoneStyle(card, goal);

                            ta.addEventListener('focus', (e) => {
                                if (!isAuthed()) {
                                    e.target.blur();
                                    window.ChronoAuthRequire?.('save your daily goal');
                                    return;
                                }
                                if (emptyHint) emptyHint.classList.add('hidden');
                            });
                            ta.addEventListener('input', () => {
                                autoGrow(ta, counter);
                                if (emptyHint) emptyHint.classList.add('hidden');
                                if (!isAuthed()) return;
                                const all = loadGoals();
                                const target = all.find((g) => g.id === goal.id);
                                if (target) target.text = ta.value;
                                saveGoals(all);
                                refreshStats();
                            });
                            ta.addEventListener('blur', () => {
                                if (!isAuthed()) return;
                                // Drop empty cards (unless they were marked
                                // done — preserve completed records). Skip
                                // cleanup if the user just clicked the Add
                                // button — the new card needs to stay.
                                const all = loadGoals();
                                const target = all.find((g) => g.id === goal.id);
                                if (target && !target.done && (target.text || '').trim() === '' && all.length > 1) {
                                    const next = all.filter((g) => g.id !== goal.id);
                                    saveGoals(next);
                                    renderGoals();
                                }
                            });

                            doneBtn.addEventListener('click', () => {
                                if (!isAuthed()) {
                                    window.ChronoAuthRequire?.('mark your goal as done');
                                    return;
                                }
                                // Re-read the latest goal from storage in case
                                // the user typed and Done was clicked back-to-back.
                                const all = loadGoals();
                                const target = all.find((g) => g.id === goal.id);
                                const text = (target?.text || ta.value || '').trim();

                                if (target?.done) {
                                    // Currently done → undo. Clear completion data.
                                    target.done = false;
                                    target.completedFrom = null;
                                    target.completedTo = null;
                                    target.completedAt = null;
                                    saveGoals(all);
                                    renderGoals();
                                    refreshStats();
                                    return;
                                }

                                // Empty-goal guard: refuse to open the modal
                                // when there's nothing to mark complete.
                                if (text === '') {
                                    if (emptyHint) emptyHint.classList.remove('hidden');
                                    ta.focus();
                                    return;
                                }
                                openCompleteModal(goal.id, text);
                            });

                            deleteBtn.addEventListener('click', () => {
                                if (!isAuthed()) {
                                    window.ChronoAuthRequire?.('manage your goals');
                                    return;
                                }
                                const all = loadGoals();
                                const next = all.filter((g) => g.id !== goal.id);
                                saveGoals(next);
                                renderGoals();
                                refreshStats();
                            });

                            goalsListEl.appendChild(card);
                            // Initial autogrow once the card is in the DOM.
                            requestAnimationFrame(() => autoGrow(ta, counter));
                        });
                        refreshStats();
                    };

                    const refreshStats = () => {
                        const all = loadGoals();
                        const total = all.length;
                        const done = all.filter((g) => g.done).length;
                        const pending = total - done;
                        if (goalsStatsEl) {
                            goalsStatsEl.textContent = total === 0
                                ? '—'
                                : `${done}/${total} completed${pending > 0 ? ` · ${pending} pending` : ''}`;
                        }
                        renderReminder(pending);
                    };

                    // Reminder banner: live ticker that says "X hours until
                    // bedtime" and turns rose/amber when the user has pending
                    // goals and bedtime is close.
                    const minutesUntilBedtime = () => {
                        const now = new Date();
                        const [bedH, bedM] = (cfg.endTime || '22:00').split(':').map(Number);
                        const bed = new Date(now);
                        bed.setHours(bedH, bedM, 0, 0);
                        if (bed.getTime() <= now.getTime()) bed.setDate(bed.getDate() + 1);
                        return Math.max(0, Math.round((bed.getTime() - now.getTime()) / 60000));
                    };
                    const renderReminder = (pendingCount) => {
                        if (!goalsReminderEl) return;
                        const all = loadGoals();
                        const total = all.length;
                        if (total === 0) {
                            goalsReminderEl.classList.add('hidden');
                            return;
                        }
                        const done = all.filter((g) => g.done).length;
                        const pending = total - done;
                        const mins = minutesUntilBedtime();
                        const h = Math.floor(mins / 60);
                        const m = mins % 60;
                        const timeLabel = h > 0 ? `${h}h ${m}m` : `${m}m`;

                        let tone, icon, msg;
                        if (pending === 0) {
                            tone = 'border-emerald-500/40 bg-emerald-500/5 text-emerald-200';
                            icon = '✓';
                            msg = `All ${total} ${total === 1 ? 'goal' : 'goals'} done — nice work.`;
                        } else if (mins <= 120) {
                            tone = 'border-rose-500/40 bg-rose-500/10 text-rose-100';
                            icon = '⏰';
                            msg = `Time is ticking — ${pending} of ${total} ${pending === 1 ? 'goal' : 'goals'} still pending.`;
                        } else if (mins <= 240) {
                            tone = 'border-amber-500/40 bg-amber-500/10 text-amber-100';
                            icon = '⏳';
                            msg = `${pending} of ${total} pending — keep moving.`;
                        } else {
                            tone = 'border-slate-700/60 bg-slate-900/40 text-slate-300';
                            icon = '◆';
                            msg = `${pending} of ${total} pending.`;
                        }
                        goalsReminderEl.className = `rounded-lg border px-4 py-2.5 text-sm flex flex-wrap items-center justify-between gap-3 ${tone}`;
                        if (goalsReminderIcon) goalsReminderIcon.textContent = icon;
                        if (goalsReminderText) goalsReminderText.textContent = msg;
                        if (goalsBedtimeEl) goalsBedtimeEl.textContent = `${timeLabel} until bed`;
                        goalsReminderEl.classList.remove('hidden');
                    };

                    // Done modal — opens when user ticks Done on a goal.
                    const openCompleteModal = (goalId, label) => {
                        if (!completeModal) return;
                        pendingCompleteId = goalId;
                        if (completeText) completeText.textContent = `"${label.slice(0, 80)}${label.length > 80 ? '…' : ''}"`;
                        // Default: from one hour ago to now (rounded to 5 min).
                        const now = new Date();
                        const round5 = (d) => { d.setSeconds(0, 0); d.setMinutes(Math.round(d.getMinutes() / 5) * 5); return d; };
                        const end = round5(new Date(now));
                        const start = round5(new Date(now.getTime() - 60 * 60 * 1000));
                        const toHHMM = (d) => `${pad(d.getHours())}:${pad(d.getMinutes())}`;
                        if (completeFromHidden) completeFromHidden.value = toHHMM(start);
                        if (completeToHidden) completeToHidden.value = toHHMM(end);
                        if (completeFromInput) completeFromInput.value = fmt12(toHHMM(start));
                        if (completeToInput) completeToInput.value = fmt12(toHHMM(end));
                        if (completeError) {
                            completeError.classList.add('hidden');
                            completeError.textContent = '';
                        }
                        completeModal.classList.remove('hidden');
                        completeModal.classList.add('flex');
                        completeModal.setAttribute('aria-hidden', 'false');
                    };
                    const closeCompleteModal = () => {
                        if (!completeModal) return;
                        completeModal.classList.add('hidden');
                        completeModal.classList.remove('flex');
                        completeModal.setAttribute('aria-hidden', 'true');
                        pendingCompleteId = null;
                    };
                    completeCancelBtn?.addEventListener('click', closeCompleteModal);
                    completeModal?.addEventListener('click', (e) => {
                        if (e.target === completeModal) closeCompleteModal();
                    });

                    completeSaveBtn?.addEventListener('click', () => {
                        if (!pendingCompleteId) return;
                        // The data-time12 helper writes into the hidden field
                        // when the visible input parses cleanly. If it's empty
                        // we read the visible value and try to parse manually.
                        const fromHm = completeFromHidden?.value
                            || (completeFromInput?.value ? parseLooseTime12(completeFromInput.value) : '');
                        const toHm = completeToHidden?.value
                            || (completeToInput?.value ? parseLooseTime12(completeToInput.value) : '');
                        if (!fromHm || !toHm) {
                            if (completeError) {
                                completeError.textContent = 'Enter both Start and End in 12-hour format (e.g. 9:00 AM).';
                                completeError.classList.remove('hidden');
                            }
                            return;
                        }
                        const fromMin = hhmmToMins(fromHm);
                        const toMin = hhmmToMins(toHm);
                        if (toMin <= fromMin) {
                            if (completeError) {
                                completeError.textContent = 'End must be after Start.';
                                completeError.classList.remove('hidden');
                            }
                            return;
                        }
                        const all = loadGoals();
                        const target = all.find((g) => g.id === pendingCompleteId);
                        if (target) {
                            target.done = true;
                            target.completedFrom = fromHm;
                            target.completedTo = toHm;
                            target.completedAt = new Date().toISOString();
                        }
                        saveGoals(all);

                        // Also create a productive time block so the work
                        // counts toward Today's stats.
                        if (window.ChronoBlocks?.add && target) {
                            const text = (target.text || '').trim() || 'Goal';
                            window.ChronoBlocks.add({
                                source: 'manual',
                                start: fromHm,
                                end: toHm,
                                durationMs: (toMin - fromMin) * 60000,
                                label: `Goal: ${text.slice(0, 200)}`,
                                category: 'productive',
                                categoryManual: true,
                                status: 'completed',
                            });
                        }
                        closeCompleteModal();
                        renderGoals();
                    });

                    // Helper: parse '9:00 AM' / '9:00am' / '9 PM' to HH:MM.
                    const parseLooseTime12 = (s) => {
                        const m = String(s).trim().match(/^(\d{1,2}):?(\d{2})?\s*(AM|PM|am|pm|a\.m\.|p\.m\.)$/i);
                        if (!m) return '';
                        let h = parseInt(m[1], 10);
                        const min = parseInt(m[2] || '0', 10);
                        const ampm = (m[3] || '').toLowerCase().replace(/\./g, '');
                        if (h < 1 || h > 12 || min < 0 || min > 59) return '';
                        if (ampm.startsWith('p') && h !== 12) h += 12;
                        if (ampm.startsWith('a') && h === 12) h = 0;
                        return `${pad(h)}:${pad(min)}`;
                    };

                    goalsAddBtn?.addEventListener('click', () => {
                        if (!isAuthed()) {
                            window.ChronoAuthRequire?.('add a goal');
                            return;
                        }
                        const all = loadGoals();
                        all.push({
                            id: newGoalId(), text: '', done: false,
                            completedFrom: null, completedTo: null, completedAt: null,
                        });
                        saveGoals(all);
                        renderGoals();
                        // Focus the newly-added card's textarea.
                        const last = goalsListEl.lastElementChild;
                        last?.querySelector('[data-goal-text]')?.focus();
                    });

                    renderGoals();
                    // Live-tick the bedtime label every 30s so the reminder
                    // tone shifts as the deadline approaches.
                    setInterval(refreshStats, 30000);
                }

                // DOM refs for stats
                const todayDateEl = document.querySelector('[data-today-date]');
                const loggedTodayEl = document.querySelector('[data-logged-today]');
                const loggedCountEl = document.querySelector('[data-logged-count]');
                const unloggedTodayEl = document.querySelector('[data-unlogged-today]');
                const unloggedContextEl = document.querySelector('[data-unlogged-context]');
                const topBlocksEl = document.querySelector('[data-top-blocks]');
                const last7DaysEl = document.querySelector('[data-last-7-days]');
                const periodSections = {
                    week: document.querySelector('[data-period-section="week"]'),
                    month: document.querySelector('[data-period-section="month"]'),
                    year: document.querySelector('[data-period-section="year"]'),
                };

                const calcCalendarMonths = (start, end) => {
                    let months = (end.getFullYear() - start.getFullYear()) * 12
                        + (end.getMonth() - start.getMonth());
                    if (end.getDate() < start.getDate()) months -= 1;
                    return Math.max(0, months);
                };

                const formatPreSignupSpan = (preStart, preEnd) => {
                    const ms = preEnd.getTime() - preStart.getTime();
                    if (ms <= 0) return null;
                    const days = Math.floor(ms / 86400000);
                    const weeks = Math.floor(days / 7);
                    const months = calcCalendarMonths(preStart, preEnd);
                    const parts = [];
                    if (months >= 1) parts.push(`${months} ${months === 1 ? 'month' : 'months'}`);
                    if (weeks >= 1) parts.push(`${weeks} ${weeks === 1 ? 'week' : 'weeks'}`);
                    parts.push(`${days} ${days === 1 ? 'day' : 'days'}`);
                    return parts.join(' · ');
                };

                const updatePeriod = (section, periodName, startDate, endDate, now, blocks) => {
                    if (!section) return;

                    // Signup-aware effective start: don't count pre-signup time as "passed".
                    // After the period that contains signup ends, this clamp becomes a no-op.
                    const signupClamped = signupTs && signupTs > startDate;
                    const effectiveStart = signupClamped ? signupTs : startDate;

                    const passedMs = Math.max(0, now.getTime() - effectiveStart.getTime());
                    const totalMs = Math.max(1, endDate.getTime() - effectiveStart.getTime());
                    const leftMs = Math.max(0, endDate.getTime() - now.getTime());
                    const startKey = localDateString(effectiveStart);
                    const endKey = localDateString(endDate);
                    const completedInRange = blocks.filter((b) =>
                        b.status === 'completed' && b.date && b.date >= startKey && b.date < endKey
                    );
                    const productiveMs = completedInRange
                        .filter((b) => b.category !== 'wasted')
                        .reduce((s, b) => s + (b.durationMs || 0), 0);
                    const wastedMs = completedInRange
                        .filter((b) => b.category === 'wasted')
                        .reduce((s, b) => s + (b.durationMs || 0), 0);
                    const progressPct = totalMs > 0
                        ? Math.min(100, (passedMs / totalMs) * 100)
                        : 0;

                    // ── Sleep / Awake / Unlogged / Efficiency for this period
                    // Count one bedtime per calendar day inside the elapsed
                    // window, multiply by sleep_per_night.
                    const wakeMins = hhmmToMins(wakeTime);
                    const endMins = hhmmToMins(endTime);
                    const sleepPerNightMin = wakeMins > endMins
                        ? wakeMins - endMins
                        : (24 * 60) - endMins + wakeMins;

                    let elapsedNights = 0;
                    if (passedMs > 0) {
                        const cursor = new Date(effectiveStart);
                        cursor.setHours(0, 0, 0, 0);
                        const lastDay = new Date(now);
                        lastDay.setHours(0, 0, 0, 0);
                        while (cursor.getTime() <= lastDay.getTime()) {
                            const bedtime = new Date(cursor);
                            bedtime.setHours(Math.floor(endMins / 60), endMins % 60, 0, 0);
                            if (bedtime.getTime() >= effectiveStart.getTime() &&
                                bedtime.getTime() <= now.getTime()) {
                                elapsedNights++;
                            }
                            cursor.setDate(cursor.getDate() + 1);
                        }
                    }
                    const sleepElapsedMs = elapsedNights * sleepPerNightMin * 60 * 1000;
                    const awakeElapsedMs = Math.max(0, passedMs - sleepElapsedMs);
                    const unloggedAwakeMs = Math.max(0, awakeElapsedMs - productiveMs - wastedMs);

                    // Efficiency = productive ÷ (productive + wasted + unlogged).
                    // Wasted AND unlogged time both count against the user — the
                    // only way to reach 100% is logging productive blocks across
                    // the full awake window.
                    const effDenomMs = productiveMs + wastedMs + unloggedAwakeMs;
                    const efficiencyPct = effDenomMs > 0
                        ? Math.min(100, Math.round((productiveMs / effDenomMs) * 100))
                        : 0;

                    // Segmented bar: percentages over awakeElapsedMs.
                    const awakeForBar = Math.max(1, awakeElapsedMs);
                    const prodPct = Math.round((productiveMs / awakeForBar) * 100);
                    const wastedBarPct = Math.round((wastedMs / awakeForBar) * 100);
                    const unloggedBarPct = Math.max(0, 100 - prodPct - wastedBarPct);

                    const range = section.querySelector('[data-period-range]');
                    const totalEl = section.querySelector('[data-period-total]');
                    const sleepEl = section.querySelector('[data-period-sleep]');
                    const sleepNoteEl = section.querySelector('[data-period-sleep-note]');
                    const awakeEl = section.querySelector('[data-period-awake]');
                    const awakeLabelEl = section.querySelector('[data-period-awake-label]');
                    const left = section.querySelector('[data-period-left]');
                    const productive = section.querySelector('[data-period-productive]');
                    const wastedEl = section.querySelector('[data-period-wasted]');
                    const unloggedEl = section.querySelector('[data-period-unlogged]');
                    const nonProductiveEl = section.querySelector('[data-period-nonproductive]');
                    const ratioEl = section.querySelector('[data-period-ratio]');
                    const progressEl = section.querySelector('[data-period-progress]');
                    const barProductive = section.querySelector('[data-period-bar-productive]');
                    const barWasted = section.querySelector('[data-period-bar-wasted]');
                    const barUnlogged = section.querySelector('[data-period-bar-unlogged]');
                    // Older-style fields (still exposed for month/year sections that
                    // haven't been redesigned yet).
                    const passed = section.querySelector('[data-period-passed]');

                    if (range) range.textContent = formatRange(effectiveStart, endDate);
                    if (totalEl) totalEl.textContent = formatHours(totalMs);
                    if (sleepEl) sleepEl.textContent = formatHours(sleepElapsedMs);
                    if (sleepNoteEl) sleepNoteEl.textContent =
                        `${elapsedNights} ${elapsedNights === 1 ? 'night' : 'nights'} × ${formatHours(sleepPerNightMin * 60 * 1000)}`;
                    if (awakeEl) awakeEl.textContent = formatHours(awakeElapsedMs);
                    if (awakeLabelEl) awakeLabelEl.textContent = `${formatHours(awakeElapsedMs)} awake elapsed`;
                    if (passed) passed.textContent = formatHours(passedMs);
                    if (left) left.textContent = formatHours(leftMs);
                    if (productive) productive.textContent = productiveMs > 0 ? formatHours(productiveMs) : '—';
                    if (wastedEl) wastedEl.textContent = wastedMs > 0 ? formatHours(wastedMs) : '—';
                    if (unloggedEl) unloggedEl.textContent = unloggedAwakeMs > 0 ? formatHours(unloggedAwakeMs) : '—';
                    if (nonProductiveEl) {
                        const np = wastedMs + unloggedAwakeMs;
                        nonProductiveEl.textContent = np > 0 ? formatHours(np) : '0h';
                    }
                    if (ratioEl) ratioEl.textContent = effDenomMs > 0 ? `${efficiencyPct}%` : '—';
                    if (progressEl) progressEl.style.width = `${progressPct.toFixed(2)}%`;
                    if (barProductive) barProductive.style.width = `${prodPct}%`;
                    if (barWasted) barWasted.style.width = `${wastedBarPct}%`;
                    if (barUnlogged) barUnlogged.style.width = `${unloggedBarPct}%`;
                    if (noteEl) {
                        // Legacy text note kept hidden — replaced by the
                        // visible callout below. Empty so screen readers
                        // don't double-announce.
                        noteEl.classList.add('hidden');
                        noteEl.innerHTML = '';
                    }

                    // Joined-mid-period callout. Only visible while signup
                    // is inside this period; the moment the period boundary
                    // moves past signup, signupClamped goes false and the
                    // callout hides — that's the auto-hide behaviour the
                    // user asked for.
                    const joinedNote = section.querySelector('[data-period-joined-note]');
                    const joinedDate = section.querySelector('[data-period-joined-date]');
                    const joinedGap = section.querySelector('[data-period-joined-gap]');
                    if (joinedNote) {
                        if (signupClamped && signupDateLabel) {
                            const preLabel = formatPreSignupSpan(startDate, signupTs);
                            if (joinedDate) joinedDate.textContent = signupDateLabel;
                            if (joinedGap) joinedGap.textContent = preLabel || 'Some';
                            joinedNote.classList.remove('hidden');
                        } else {
                            joinedNote.classList.add('hidden');
                        }
                    }
                };

                const renderTopBlocks = (todayBlocks) => {
                    if (!topBlocksEl) return;
                    if (todayBlocks.length === 0) {
                        topBlocksEl.innerHTML = '<li class="text-slate-500">No blocks logged yet today.</li>';
                        return;
                    }
                    const top = todayBlocks
                        .filter((b) => b.status === 'completed')
                        .slice()
                        .sort((a, b) => (b.durationMs || 0) - (a.durationMs || 0))
                        .slice(0, 3);
                    if (top.length === 0) {
                        topBlocksEl.innerHTML = '<li class="text-slate-500">No completed blocks yet today.</li>';
                        return;
                    }
                    topBlocksEl.innerHTML = top.map((b) => {
                        const isWasted = b.category === 'wasted';
                        const dotClass = isWasted ? 'bg-rose-400' : 'bg-emerald-400';
                        const tagClass = isWasted ? 'text-rose-300' : 'text-emerald-300';
                        const tagText = isWasted ? 'Wasted' : 'Productive';
                        return `<li class="flex items-center gap-2 text-slate-300">` +
                            `<span class="inline-block h-2 w-2 rounded-full ${dotClass} shrink-0"></span>` +
                            `<span class="text-slate-100 font-medium">${escapeHtml(formatDuration(b.durationMs || 0))}</span>` +
                            ` · ${escapeHtml(b.label || 'Time block')}` +
                            ` <span class="ml-1 text-[0.65rem] uppercase tracking-wider ${tagClass}">${tagText}</span>` +
                            `</li>`;
                    }).join('');
                };

                const renderLast7Days = (blocks, now) => {
                    if (!last7DaysEl) return;
                    const todayKey = localDateString(now);
                    const signupKey = signupTs ? localDateString(signupTs) : null;
                    const dayUrlTemplate = (window.ChronoDashboardConfig?.dayDetailUrl) || '';
                    const tiles = [];
                    for (let i = 6; i >= 0; i--) {
                        const d = new Date(now);
                        d.setDate(d.getDate() - i);
                        const dateStr = localDateString(d);

                        // Show *productive* time only — wasted hours don't
                        // count as a logged-success on the tile.
                        const productiveMs = blocks
                            .filter((b) => b.status === 'completed' && b.date === dateStr && b.category !== 'wasted')
                            .reduce((s, b) => s + (b.durationMs || 0), 0);
                        const wastedMs = blocks
                            .filter((b) => b.status === 'completed' && b.date === dateStr && b.category === 'wasted')
                            .reduce((s, b) => s + (b.durationMs || 0), 0);

                        const dayName = d.toLocaleDateString('en-US', { weekday: 'short' });
                        const isToday = dateStr === todayKey;
                        const isPreSignup = signupKey && dateStr < signupKey;

                        let cls;
                        if (isPreSignup) {
                            cls = 'border-slate-800/30 bg-slate-900/20 opacity-50';
                        } else if (isToday) {
                            cls = 'border-[var(--chrono-blue)] bg-slate-800/60';
                        } else {
                            cls = 'border-slate-800/60 bg-slate-900/40 hover:border-[var(--chrono-blue)]/60 transition-colors cursor-pointer';
                        }

                        // Tile value:
                        //   pre-signup → "pre-signup" italics
                        //   productive logged   → green duration label
                        //   nothing logged      → red "0" so it stands out
                        let valueHtml;
                        if (isPreSignup) {
                            valueHtml = '<span class="text-slate-600 italic">pre-signup</span>';
                        } else if (productiveMs > 0) {
                            valueHtml = `<span class="text-emerald-300 font-medium">${escapeHtml(formatDuration(productiveMs))}</span>`;
                        } else {
                            valueHtml = '<span class="text-rose-400 font-medium">0</span>';
                        }

                        // Optional secondary line: small wasted callout if any.
                        const wastedLine = (!isPreSignup && wastedMs > 0)
                            ? `<div class="text-[0.6rem] text-rose-400/80 mt-0.5">${escapeHtml(formatDuration(wastedMs))} wasted</div>`
                            : '';

                        const inner =
                            `<div class="text-[0.65rem] uppercase tracking-wider text-slate-400">${escapeHtml(dayName)}</div>` +
                            `<div class="text-[0.65rem] text-slate-500">${d.getDate()}</div>` +
                            `<div class="mt-1 text-sm">${valueHtml}</div>` +
                            wastedLine;

                        // Past, post-signup days are clickable links to the
                        // read-only day report. Today and pre-signup tiles
                        // stay as plain divs.
                        if (!isPreSignup && !isToday && dayUrlTemplate) {
                            const url = dayUrlTemplate.replace('__DATE__', dateStr);
                            tiles.push(
                                `<a href="${url}" class="block rounded-lg border ${cls} p-2 text-center" title="Click to see detailed report">` +
                                inner +
                                '</a>'
                            );
                        } else {
                            const titleAttr = isPreSignup
                                ? ' title="Before your signup"'
                                : (isToday ? ' title="Current day — see Today section above"' : '');
                            tiles.push(
                                `<div class="rounded-lg border ${cls} p-2 text-center"${titleAttr}>` +
                                inner +
                                '</div>'
                            );
                        }
                    }
                    last7DaysEl.innerHTML = tiles.join('');
                };

                const updateAll = () => {
                    const now = new Date();
                    const todayStr = localDateString(now);
                    const blocks = loadBlocks();

                    if (todayDateEl) {
                        todayDateEl.textContent = now.toLocaleDateString('en-US', {
                            weekday: 'long', month: 'long', day: 'numeric', year: 'numeric',
                        });
                    }

                    const todayBlocks = blocks.filter((b) => b.date === todayStr);
                    const completedToday = todayBlocks.filter((b) => b.status === 'completed');
                    const loggedTodayMs = completedToday.reduce((s, b) => s + (b.durationMs || 0), 0);
                    const wastedTodayMs = completedToday
                        .filter((b) => b.category === 'wasted')
                        .reduce((s, b) => s + (b.durationMs || 0), 0);
                    if (loggedTodayEl) loggedTodayEl.textContent = formatDuration(loggedTodayMs);
                    if (loggedCountEl) {
                        const n = completedToday.length;
                        const blockText = `${n} ${n === 1 ? 'block' : 'blocks'}`;
                        const wastedHtml = wastedTodayMs > 0
                            ? ` · <span class="text-rose-300">${escapeHtml(formatDuration(wastedTodayMs))} wasted</span>`
                            : '';
                        loggedCountEl.innerHTML = `${escapeHtml(blockText)}${wastedHtml}`;
                    }

                    const wakeMins = hhmmToMins(wakeTime);
                    const endMins = hhmmToMins(endTime);
                    const wakeToday = new Date(now);
                    wakeToday.setHours(Math.floor(wakeMins / 60), wakeMins % 60, 0, 0);
                    const endToday = new Date(now);
                    endToday.setHours(Math.floor(endMins / 60), endMins % 60, 0, 0);

                    // Clamp the start of today's active window to signup if they joined today
                    // after wake-up. Past today, this clamp is a no-op.
                    const signupIsToday = signupTs && localDateString(signupTs) === todayStr;
                    const effectiveTodayStart =
                        signupIsToday && signupTs.getTime() > wakeToday.getTime()
                            ? signupTs
                            : wakeToday;

                    const activeWindowEnd = Math.min(now.getTime(), endToday.getTime());
                    const elapsedActiveMs = Math.max(0, activeWindowEnd - effectiveTodayStart.getTime());
                    const elapsedActiveMins = Math.floor(elapsedActiveMs / 60000);

                    let context;
                    if (now.getTime() < effectiveTodayStart.getTime()) {
                        context = signupIsToday && signupTs.getTime() > wakeToday.getTime()
                            ? 'before signup'
                            : 'before wake-up';
                    } else if (signupIsToday && signupTs.getTime() > wakeToday.getTime()) {
                        context = 'since signup';
                    } else if (now.getTime() > endToday.getTime()) {
                        context = 'past end of day';
                    } else {
                        context = 'since wake-up';
                    }

                    const loggedTodayMins = Math.round(loggedTodayMs / 60000);
                    const unloggedMins = Math.max(0, elapsedActiveMins - loggedTodayMins);
                    if (unloggedTodayEl) unloggedTodayEl.textContent = formatDuration(unloggedMins * 60000);
                    if (unloggedContextEl) unloggedContextEl.textContent = context;

                    // ── Day efficiency ────────────────────────────────────
                    // Productive% = productive ÷ (productive + wasted + unlogged).
                    // Wasted AND unlogged time both count against the user, so
                    // the only way to reach 100% is logging productive blocks
                    // across the full waking window so far.
                    const productiveTodayMs = Math.max(0, loggedTodayMs - wastedTodayMs);
                    const elapsedMs = Math.max(0, elapsedActiveMs);
                    const unloggedTodayMs = Math.max(0, elapsedMs - loggedTodayMs);
                    const dayDenomMs = productiveTodayMs + wastedTodayMs + unloggedTodayMs;
                    const productivePct = dayDenomMs > 0
                        ? Math.min(100, Math.round((productiveTodayMs / dayDenomMs) * 100))
                        : 0;
                    const wastedPct = dayDenomMs > 0
                        ? Math.min(100 - productivePct, Math.round((wastedTodayMs / dayDenomMs) * 100))
                        : 0;
                    const unloggedBarPct = Math.max(0, 100 - productivePct - wastedPct);
                    const nonProductiveMs = wastedTodayMs + unloggedTodayMs;
                    const dayPctEl = document.querySelector('[data-day-effective-pct]');
                    const dayProdBar = document.querySelector('[data-day-productive-bar]');
                    const dayWastedBar = document.querySelector('[data-day-wasted-bar]');
                    const dayUnloggedBar = document.querySelector('[data-day-unlogged-bar]');
                    const dayProdTime = document.querySelector('[data-day-productive-time]');
                    const dayWastedTime = document.querySelector('[data-day-wasted-time]');
                    const dayUnloggedTime = document.querySelector('[data-day-unlogged-time]');
                    const dayNonProdTime = document.querySelector('[data-day-nonproductive-time]');
                    if (dayPctEl) dayPctEl.textContent = dayDenomMs > 0 ? `${productivePct}%` : '—';
                    if (dayProdBar) dayProdBar.style.width = `${productivePct}%`;
                    if (dayWastedBar) dayWastedBar.style.width = `${wastedPct}%`;
                    if (dayUnloggedBar) dayUnloggedBar.style.width = `${unloggedBarPct}%`;
                    if (dayProdTime) dayProdTime.textContent = formatDuration(productiveTodayMs);
                    if (dayWastedTime) dayWastedTime.textContent = formatDuration(wastedTodayMs);
                    if (dayUnloggedTime) dayUnloggedTime.textContent = formatDuration(unloggedMins * 60000);
                    if (dayNonProdTime) dayNonProdTime.textContent = formatDuration(nonProductiveMs);

                    renderTopBlocks(todayBlocks);

                    updatePeriod(periodSections.week, 'week', startOfWeek(now), endOfWeek(now), now, blocks);
                    updatePeriod(periodSections.month, 'month', startOfMonth(now), endOfMonth(now), now, blocks);
                    updatePeriod(periodSections.year, 'year', startOfYear(now), endOfYear(now), now, blocks);

                    renderLast7Days(blocks, now);
                };

                window.addEventListener('chrono:blocks:changed', updateAll);
                setInterval(updateAll, 30000);
                updateAll();
            })();
        </script>
    @endpush
@endsection
