@extends('layouts.app')

@section('page_title', $goal->title)

@section('content')
    @php
        $tier = $result['tier'];
        $details = $result['details'];
        $remaining = $details['days_remaining'] ?? 0;
        $deadlineIso = $goal->target_date->copy()->endOfDay()->toIso8601String();
        $maxSpark = max(0.5, collect($sparkline)->max('hours') ?: 0.5);
        $probDisplay = rtrim(rtrim(number_format($result['percent'], 1), '0'), '.');
        $isPast = $remaining === 0 && $goal->status !== 'completed';
        $alertLevel = null;
        if ($goal->status === 'active') {
            if ($isPast) $alertLevel = 'critical';
            elseif ($result['percent'] < 20) $alertLevel = 'critical';
            elseif ($result['percent'] < 30) $alertLevel = 'warning';
            elseif ($remaining <= 7 && $result['percent'] < 60) $alertLevel = 'warning';
        }
    @endphp

    <div class="relative overflow-hidden rounded-2xl border border-slate-800/60 p-6 md:p-8 mb-6"
        style="background: radial-gradient(circle at top, {{ $tier['hex'] }}26, transparent 45%);">
        <div class="absolute -right-24 -top-24 h-56 w-56 rounded-full blur-2xl"
            style="background: radial-gradient(circle, {{ $tier['hex'] }}59, transparent 70%);"></div>
        <div class="relative flex flex-col md:flex-row md:items-start md:justify-between gap-4">
            <div class="min-w-0">
                <a href="{{ route('goals.index') }}" class="inline-flex items-center gap-1 text-xs uppercase tracking-[0.2em] text-slate-400 hover:text-slate-100 transition-colors">
                    <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    Goals
                </a>
                <h1 class="mt-2 font-display text-2xl md:text-3xl tracking-[0.2em] uppercase">{{ $goal->title }}</h1>
                <div class="mt-2 flex flex-wrap items-center gap-1.5 text-xs">
                    {{-- Status pill with colored dot — clearer than a plain word --}}
                    @php
                        $statusInfo = match ($goal->status) {
                            'completed' => ['cls' => 'border-emerald-500/40 bg-emerald-500/10 text-emerald-300', 'dot' => 'bg-emerald-400', 'label' => 'Completed'],
                            'abandoned' => ['cls' => 'border-slate-500/40 bg-slate-500/10 text-slate-300', 'dot' => 'bg-slate-400', 'label' => 'Abandoned'],
                            'missed' => ['cls' => 'border-rose-500/40 bg-rose-500/10 text-rose-300', 'dot' => 'bg-rose-400', 'label' => 'Missed'],
                            default => ['cls' => 'border-sky-500/40 bg-sky-500/10 text-sky-300', 'dot' => 'bg-sky-400', 'label' => 'Active'],
                        };
                    @endphp
                    <span class="inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 uppercase tracking-wider {{ $statusInfo['cls'] }}">
                        <span class="h-1.5 w-1.5 rounded-full {{ $statusInfo['dot'] }}"></span>
                        {{ $statusInfo['label'] }}
                    </span>
                    <span class="inline-flex items-center rounded-full border border-slate-700/60 bg-slate-900/60 px-2 py-0.5 text-slate-300 uppercase tracking-wider">
                        {{ $goal->category }}
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-700/60 bg-slate-900/60 px-2 py-0.5 text-slate-300">
                        <svg class="h-3 w-3 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        Target {{ $goal->target_date->format('D, M j, Y') }}
                    </span>
                    @if ($goal->extension_count > 0)
                        <span class="inline-flex items-center rounded-full border border-amber-500/30 bg-amber-500/10 px-2 py-0.5 text-amber-300">
                            Extended {{ $goal->extension_count }}×
                        </span>
                    @endif
                    @if ($goal->status === 'completed' && $goal->completed_at)
                        <span class="inline-flex items-center rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2 py-0.5 text-emerald-300">
                            Done {{ $goal->completed_at->format('M j, Y') }}
                        </span>
                    @endif
                </div>
                @if ($goal->description)
                    <p class="mt-4 text-sm text-slate-300 max-w-2xl whitespace-pre-line">{{ $goal->description }}</p>
                @endif
            </div>

            <div class="flex flex-col items-end gap-2">
                <div class="text-right">
                    <div class="font-digital text-5xl chrono-pulse" style="color: {{ $tier['hex'] }}">
                        {{ $probDisplay }}%
                    </div>
                    <div class="text-xs uppercase tracking-[0.2em] mt-1" style="color: {{ $tier['hex'] }}">
                        {{ $tier['label'] }}
                    </div>
                </div>
            </div>
        </div>

        <div class="relative mt-6 grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3">
                <div class="text-[0.6rem] uppercase tracking-wider text-slate-500">Time remaining</div>
                <div class="mt-1 font-digital text-xl chrono-glow-blue" data-goal-countdown data-deadline="{{ $deadlineIso }}">—</div>
                <div class="text-[0.65rem] text-slate-500 mt-0.5">until {{ $goal->target_date->format('M j, Y') }}</div>
            </div>
            <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3">
                <div class="text-[0.6rem] uppercase tracking-wider text-slate-500">Hours logged</div>
                <div class="mt-1 text-xl text-slate-100">{{ $details['hours_done'] ?? 0 }}h</div>
                <div class="text-[0.65rem] text-slate-500 mt-0.5">in this goal's window</div>
            </div>
            <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3">
                <div class="text-[0.6rem] uppercase tracking-wider text-slate-500">Recent pace</div>
                <div class="mt-1 text-xl text-slate-100">
                    {{ $details['avg_recent_hours_per_day'] ?? '—' }}h<span class="text-xs text-slate-500">/day</span>
                </div>
                <div class="text-[0.65rem] text-slate-500 mt-0.5">last 7 days</div>
            </div>
            <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3">
                <div class="text-[0.6rem] uppercase tracking-wider text-slate-500">Consistency</div>
                <div class="mt-1 text-xl text-slate-100">
                    @if (isset($details['days_with_logs'], $details['days_passed']) && $details['days_passed'] > 0)
                        {{ round(($details['days_with_logs'] / $details['days_passed']) * 100) }}%
                    @else
                        —
                    @endif
                </div>
                <div class="text-[0.65rem] text-slate-500 mt-0.5">
                    {{ $details['days_with_logs'] ?? 0 }}/{{ $details['days_passed'] ?? 0 }} days
                </div>
            </div>
        </div>

        <div class="relative mt-4 h-2 rounded-full bg-slate-800/80 overflow-hidden">
            <div class="h-full transition-[width] duration-700"
                style="width: {{ max(2, min(100, $result['percent'])) }}%; background-color: {{ $tier['hex'] }}"></div>
        </div>
        <p class="relative mt-3 text-sm text-slate-300">{{ $narrative }}</p>
    </div>

    {{-- Goal at a glance — lifecycle facts. Created date, age, days
         active vs remaining, original vs current target (highlights any
         extension), how many times the goal has been edited, log
         entries. --}}
    @php
        $totalSpan = max(1, (int) $lifecycle['original_target']->diffInDays(\Carbon\CarbonImmutable::parse($goal->start_date)));
        $hasExtension = $lifecycle['extension_days'] > 0;
    @endphp
    <section class="rounded-2xl border border-slate-800/60 bg-slate-900/40 overflow-hidden mb-6">
        <header class="flex flex-wrap items-baseline justify-between gap-3 px-5 py-3 border-b border-slate-800/60">
            <div class="flex items-center gap-2">
                <h2 class="font-display text-xs uppercase tracking-[0.2em] text-slate-300">Goal at a glance</h2>
                <span class="text-[0.65rem] uppercase tracking-wider text-slate-500">lifecycle</span>
            </div>
            <span class="text-xs text-slate-500">
                Created {{ $lifecycle['created_at']?->format('D, M j, Y · g:i A') ?? '—' }}
                @if ($lifecycle['created_age_for_humans'])
                    <span class="text-slate-600"> · {{ $lifecycle['created_age_for_humans'] }}</span>
                @endif
            </span>
        </header>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 p-5">
            <div class="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
                <div class="text-[0.6rem] uppercase tracking-wider text-slate-500">Days active</div>
                <div class="mt-1 font-digital text-lg text-slate-100">{{ $lifecycle['days_active'] }}</div>
                <div class="text-[0.65rem] text-slate-500 mt-0.5">since start</div>
            </div>
            <div class="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
                <div class="text-[0.6rem] uppercase tracking-wider text-slate-500">Days remaining</div>
                <div class="mt-1 font-digital text-lg {{ $lifecycle['days_remaining'] === 0 ? 'text-rose-300' : 'text-slate-100' }}">
                    {{ $lifecycle['days_remaining'] === 0 ? 'today' : $lifecycle['days_remaining'] }}
                </div>
                <div class="text-[0.65rem] text-slate-500 mt-0.5">until target</div>
            </div>
            <div class="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
                <div class="text-[0.6rem] uppercase tracking-wider text-slate-500">Original target</div>
                <div class="mt-1 text-sm text-slate-200">{{ $lifecycle['original_target']->format('M j, Y') }}</div>
                <div class="text-[0.65rem] text-slate-500 mt-0.5">{{ $lifecycle['original_target']->format('D') }}</div>
            </div>
            <div class="rounded-lg border {{ $hasExtension ? 'border-amber-500/30 bg-amber-500/5' : 'border-slate-800 bg-slate-950/40' }} p-3">
                <div class="text-[0.6rem] uppercase tracking-wider {{ $hasExtension ? 'text-amber-300' : 'text-slate-500' }}">Current target</div>
                <div class="mt-1 text-sm {{ $hasExtension ? 'text-amber-200' : 'text-slate-200' }}">{{ $lifecycle['current_target']->format('M j, Y') }}</div>
                <div class="text-[0.65rem] {{ $hasExtension ? 'text-amber-400/80' : 'text-slate-500' }} mt-0.5">
                    @if ($hasExtension)
                        +{{ $lifecycle['extension_days'] }} {{ Str::plural('day', $lifecycle['extension_days']) }} added
                    @else
                        unchanged
                    @endif
                </div>
            </div>
            <div class="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
                <div class="text-[0.6rem] uppercase tracking-wider text-slate-500">Extensions</div>
                <div class="mt-1 font-digital text-lg {{ $lifecycle['extension_count'] > 0 ? 'text-amber-300' : 'text-slate-100' }}">
                    {{ $lifecycle['extension_count'] }}
                </div>
                <div class="text-[0.65rem] text-slate-500 mt-0.5">{{ $lifecycle['change_count'] }} {{ Str::plural('change', $lifecycle['change_count']) }} total</div>
            </div>
            <div class="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
                <div class="text-[0.6rem] uppercase tracking-wider text-slate-500">Log entries</div>
                <div class="mt-1 font-digital text-lg text-slate-100">{{ $lifecycle['log_entries'] }}</div>
                @if ($lifecycle['log_entries'] > 0)
                    <a href="{{ route('goals.logs', $goal) }}" class="text-[0.65rem] text-[var(--chrono-blue)] hover:underline">
                        View full log →
                    </a>
                @else
                    <div class="text-[0.65rem] text-slate-500 mt-0.5">no edits yet</div>
                @endif
            </div>
        </div>
    </section>

    {{-- Time analysis: weeks/hours left, sleep, awake, logged, unlogged --}}
    @php
        $ta = $timeAnalysis;
        $sleepNote = $ta['sleep']['end_of_day'].' → '.$ta['sleep']['wake_time']
            .' = '.$ta['sleep']['per_night_label'].'/night';
    @endphp
    <section class="chrono-panel rounded-2xl p-6 md:p-8 mb-6">
        <div class="flex items-baseline justify-between gap-3 mb-1">
            <h2 class="font-display text-sm uppercase tracking-[0.3em] text-slate-300">Time analysis</h2>
            <span class="text-[0.65rem] uppercase tracking-wider text-slate-500" title="From your Settings">
                Sleep: {{ $sleepNote }}
            </span>
        </div>
        <p class="text-xs text-slate-500 mb-5">
            Wall-clock time inside this goal's window, with sleep subtracted using your dashboard
            <a href="{{ route('settings.show') }}" class="underline hover:text-slate-200">schedule</a>.
        </p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- ELAPSED --}}
            <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-4">
                <div class="flex items-baseline justify-between gap-2 mb-3">
                    <h3 class="text-xs uppercase tracking-[0.2em] text-slate-400">Elapsed</h3>
                    <span class="font-digital text-xl text-slate-100">{{ $ta['elapsed']['total_label'] }}</span>
                </div>
                <dl class="space-y-1.5 text-sm">
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Wall-clock total</dt>
                        <dd class="text-slate-200 font-digital">{{ $ta['elapsed']['total_hours'] }}h</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Scheduled sleep elapsed</dt>
                        <dd class="text-slate-400 font-digital">−{{ $ta['elapsed']['sleep_hours'] }}h</dd>
                    </div>
                    <div class="flex justify-between gap-2 border-t border-slate-800/60 pt-1.5">
                        <dt class="text-slate-300">Awake hours</dt>
                        <dd class="text-slate-100 font-digital">{{ $ta['elapsed']['awake_hours'] }}h</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Logged on this goal</dt>
                        <dd class="text-emerald-300 font-digital">{{ $ta['elapsed']['logged_hours'] }}h</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500" title="Awake time elapsed that wasn't credited to this goal — could be other goals or unlogged">
                            Awake but unlogged on this goal
                        </dt>
                        <dd class="text-amber-300 font-digital">{{ $ta['elapsed']['unlogged_awake_hours'] }}h</dd>
                    </div>
                </dl>
            </div>

            {{-- REMAINING --}}
            <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-4">
                <div class="flex items-baseline justify-between gap-2 mb-3">
                    <h3 class="text-xs uppercase tracking-[0.2em] text-slate-400">Remaining until target</h3>
                    <span class="font-digital text-xl text-slate-100">{{ $ta['remaining']['total_label'] }}</span>
                </div>
                <dl class="space-y-1.5 text-sm">
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Calendar</dt>
                        <dd class="text-slate-200">{{ $ta['remaining']['weeks_label'] }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Wall-clock total</dt>
                        <dd class="text-slate-200 font-digital">{{ $ta['remaining']['total_hours'] }}h</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Scheduled sleep remaining</dt>
                        <dd class="text-slate-400 font-digital">−{{ $ta['remaining']['sleep_hours'] }}h</dd>
                    </div>
                    <div class="flex justify-between gap-2 border-t border-slate-800/60 pt-1.5">
                        <dt class="text-slate-300">Awake hours available</dt>
                        <dd class="text-[var(--chrono-blue)] font-digital text-base">{{ $ta['remaining']['awake_hours'] }}h</dd>
                    </div>
                </dl>
            </div>
        </div>

        {{-- Activity breakdown for THIS goal — productive vs wasted vs
             unlogged-awake, with a segmented bar so the proportions are
             readable at a glance. --}}
        @php
            $ab = $activityBreakdown;
            $abTotal = max(0.001, $ab['productive_hours'] + $ab['wasted_hours'] + $ab['neutral_hours'] + $ab['unlogged_awake_hours']);
            $abProdPct = (int) round(($ab['productive_hours'] / $abTotal) * 100);
            $abWastedPct = (int) round(($ab['wasted_hours'] / $abTotal) * 100);
            $abNeutralPct = (int) round(($ab['neutral_hours'] / $abTotal) * 100);
            $abUnloggedPct = max(0, 100 - $abProdPct - $abWastedPct - $abNeutralPct);
        @endphp
        <div class="mt-5 rounded-xl border border-slate-800/60 bg-slate-900/40 p-4">
            <div class="flex items-baseline justify-between gap-2 mb-3">
                <h3 class="text-xs uppercase tracking-[0.2em] text-slate-400">Activity breakdown</h3>
                <span class="text-[0.65rem] uppercase tracking-wider text-slate-500">awake elapsed: {{ $ta['elapsed']['awake_hours'] }}h</span>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                <div class="rounded-lg border border-emerald-500/30 bg-emerald-500/5 p-3">
                    <div class="text-[0.6rem] uppercase tracking-wider text-emerald-300">Productive</div>
                    <div class="mt-1 font-digital text-lg text-emerald-200">{{ $ab['productive_hours'] }}h</div>
                    <div class="text-[0.6rem] text-slate-500 mt-0.5">attributed to this goal</div>
                </div>
                <div class="rounded-lg border border-rose-500/30 bg-rose-500/5 p-3">
                    <div class="text-[0.6rem] uppercase tracking-wider text-rose-300">Wasted</div>
                    <div class="mt-1 font-digital text-lg text-rose-200">{{ $ab['wasted_hours'] }}h</div>
                    <div class="text-[0.6rem] text-slate-500 mt-0.5">attributed but flagged wasted</div>
                </div>
                <div class="rounded-lg border border-slate-500/30 bg-slate-500/5 p-3">
                    <div class="text-[0.6rem] uppercase tracking-wider text-slate-300">Neutral</div>
                    <div class="mt-1 font-digital text-lg text-slate-200">{{ $ab['neutral_hours'] }}h</div>
                    <div class="text-[0.6rem] text-slate-500 mt-0.5">attributed, score-neutral</div>
                </div>
                <div class="rounded-lg border border-yellow-500/30 bg-yellow-500/5 p-3">
                    <div class="text-[0.6rem] uppercase tracking-wider text-yellow-300">Unlogged (awake)</div>
                    <div class="mt-1 font-digital text-lg text-yellow-200">{{ $ab['unlogged_awake_hours'] }}h</div>
                    <div class="text-[0.6rem] text-slate-500 mt-0.5">counts as non-productive</div>
                </div>
                <div class="rounded-lg border border-slate-700/60 bg-slate-900/40 p-3">
                    <div class="text-[0.6rem] uppercase tracking-wider text-slate-400">Non-productive total</div>
                    <div class="mt-1 font-digital text-lg text-slate-200">{{ $ab['non_productive_hours'] }}h</div>
                    <div class="text-[0.6rem] text-slate-500 mt-0.5">wasted + unlogged</div>
                </div>
            </div>

            {{-- Segmented bar --}}
            <div class="mt-4">
                <div class="h-2 rounded-full bg-slate-800/80 overflow-hidden flex">
                    <div class="h-full bg-emerald-400 transition-[width] duration-500" style="width: {{ $abProdPct }}%"></div>
                    <div class="h-full bg-rose-400 transition-[width] duration-500" style="width: {{ $abWastedPct }}%"></div>
                    <div class="h-full bg-slate-400 transition-[width] duration-500" style="width: {{ $abNeutralPct }}%"></div>
                    <div class="h-full bg-yellow-400 transition-[width] duration-500" style="width: {{ $abUnloggedPct }}%"></div>
                </div>
                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-[0.6rem] uppercase tracking-wider text-slate-500">
                    <span class="inline-flex items-center gap-1.5"><span class="inline-block h-2 w-2 rounded-full bg-emerald-400"></span> Productive {{ $abProdPct }}%</span>
                    <span class="inline-flex items-center gap-1.5"><span class="inline-block h-2 w-2 rounded-full bg-rose-400"></span> Wasted {{ $abWastedPct }}%</span>
                    <span class="inline-flex items-center gap-1.5"><span class="inline-block h-2 w-2 rounded-full bg-slate-400"></span> Neutral {{ $abNeutralPct }}%</span>
                    <span class="inline-flex items-center gap-1.5"><span class="inline-block h-2 w-2 rounded-full bg-yellow-400"></span> Unlogged {{ $abUnloggedPct }}%</span>
                </div>
            </div>
        </div>

        <p class="mt-4 text-[0.65rem] text-slate-500 leading-relaxed">
            <strong class="text-slate-400">Sleep formula:</strong>
            bedtime <span class="text-slate-300">{{ $ta['sleep']['end_of_day'] }}</span>
            → wake <span class="text-slate-300">{{ $ta['sleep']['wake_time'] }}</span>
            = <span class="text-slate-300">{{ $ta['sleep']['per_night_label'] }}/night</span>.
            Sleep is calculated from the scheduled sleep overlap in each window
            ({{ $ta['elapsed']['nights'] }} overlapping windows elapsed, {{ $ta['remaining']['nights'] }} remaining).
            <strong class="text-slate-400">Awake</strong> = wall-clock − sleep.
            <strong class="text-slate-400">Unlogged on this goal</strong> = awake elapsed − hours attributed to this goal.
            <span class="block mt-1">
                <strong class="text-slate-400">Wasted + Unlogged = Non-productive</strong> — both reduce efficiency.
            </span>
        </p>
    </section>

    @if ($alertLevel)
        <div class="mb-6 rounded-2xl border {{ $alertLevel === 'critical' ? 'border-rose-500/40 bg-rose-900/20' : 'border-amber-500/40 bg-amber-900/20' }} p-4">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div class="flex items-start gap-3 text-sm">
                    <div class="font-display text-2xl {{ $alertLevel === 'critical' ? 'text-rose-300' : 'text-amber-300' }}">⚠</div>
                    <div>
                        <div class="font-semibold {{ $alertLevel === 'critical' ? 'text-rose-200' : 'text-amber-200' }}">
                            @if ($isPast)
                                Deadline reached.
                            @elseif ($alertLevel === 'critical')
                                Time is running out.
                            @else
                                Pace is slipping.
                            @endif
                        </div>
                        <div class="text-slate-300 mt-0.5">{{ $narrative }}</div>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" data-toggle="extend"
                        class="rounded-lg border border-amber-500/50 hover:border-amber-300 text-amber-200 px-3 py-1.5 text-xs uppercase tracking-wider">
                        Extend deadline
                    </button>
                    @if ($goal->status !== 'completed')
                        <form method="POST" action="{{ route('goals.complete', $goal) }}">
                            @csrf
                            <button type="submit"
                                class="rounded-lg bg-emerald-500/20 hover:bg-emerald-500/30 border border-emerald-500/40 text-emerald-200 px-3 py-1.5 text-xs uppercase tracking-wider">
                                Mark complete
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            {{-- Keywords + how attribution works --}}
            @php
                $keywords = is_array($goal->keywords) ? $goal->keywords : [];
                $hasKeywords = ! empty($keywords);
                $competing = $result['details']['competing_goals_count'] ?? 0;
                $matchedCount = $attribution['blocks']->count();
            @endphp
            <section class="rounded-2xl border {{ $hasKeywords ? 'border-dashed border-slate-700/60 bg-slate-900/20' : 'border-amber-500/40 bg-amber-900/15' }} p-4 md:p-5">
                <div class="flex items-start gap-3">
                    <div class="font-display text-2xl {{ $hasKeywords ? 'text-[var(--chrono-blue)]' : 'text-amber-300' }}">{{ $hasKeywords ? 'i' : '!' }}</div>
                    <div class="text-sm text-slate-300 min-w-0 flex-1">
                        <div class="font-semibold text-slate-100">
                            @if ($hasKeywords)
                                Hours are attributed to this goal by keyword match.
                            @else
                                No keywords yet — this goal won't pick up any of your logs.
                            @endif
                        </div>
                        <p class="mt-1 text-xs text-slate-400">
                            When you log a time block on the
                            <a href="{{ route('dashboard') }}" class="underline text-[var(--chrono-blue)]">dashboard</a>
                            with a reason like <em>"{{ $hasKeywords ? ($keywords[0] ?? 'aws') : 'aws iam' }} review"</em>,
                            it's matched against this goal's keywords. A block matching multiple goals
                            is split proportionally so the same hour is never double-counted.
                            @if ($competing > 0)
                                You currently have <strong class="text-slate-200">{{ $competing }} other active {{ Str::plural('goal', $competing) }}</strong> competing for the same logs.
                            @endif
                        </p>
                        @if ($hasKeywords)
                            <div class="mt-3 flex flex-wrap gap-1.5">
                                @foreach ($keywords as $kw)
                                    <span class="inline-flex items-center rounded-full border border-[var(--chrono-blue)]/30 bg-[var(--chrono-blue)]/10 px-2 py-0.5 text-xs text-[var(--chrono-blue)]">
                                        {{ $kw }}
                                    </span>
                                @endforeach
                            </div>
                            @if ($matchedCount === 0 && ($result['details']['days_passed'] ?? 0) > 0)
                                <p class="mt-3 text-xs text-amber-300">
                                    No logged blocks match these keywords yet. Either mention one of these words in your dashboard log reasons, or
                                    <a href="{{ route('goals.edit', $goal) }}" class="underline">refine the keyword list</a>.
                                </p>
                            @endif
                        @else
                            <a href="{{ route('goals.edit', $goal) }}"
                                class="mt-3 inline-flex items-center rounded-lg border border-amber-500/50 hover:border-amber-300 px-3 py-1.5 text-xs uppercase tracking-wider text-amber-200">
                                Add keywords
                            </a>
                        @endif
                    </div>
                </div>
            </section>

            {{-- 14-day sparkline --}}
            <section class="chrono-panel rounded-2xl p-6 md:p-8">
                <h2 class="font-display text-sm uppercase tracking-[0.3em] text-slate-300 mb-3">Last 14 days</h2>
                <div class="flex items-end gap-1 h-24">
                    @foreach ($sparkline as $day)
                        @php
                            $h = $day['hours'];
                            $heightPct = $maxSpark > 0 ? round(($h / $maxSpark) * 100) : 0;
                            $color = $h <= 0 ? '#1f2937' : $tier['hex'];
                        @endphp
                        <div class="flex-1 flex flex-col items-center justify-end h-full" title="{{ $day['label'] }}: {{ round($h, 2) }}h">
                            <div class="w-full rounded-t transition-all"
                                style="height: {{ max(2, $heightPct) }}%; background-color: {{ $color }}; opacity: {{ $h > 0 ? 0.9 : 0.3 }};"></div>
                        </div>
                    @endforeach
                </div>
                @if (! empty($sparkline))
                    <div class="mt-2 flex justify-between text-[0.6rem] uppercase tracking-wider text-slate-500">
                        <span>{{ $sparkline[0]['label'] }}</span>
                        <span>{{ end($sparkline)['label'] }}</span>
                    </div>
                @endif
            </section>

            {{-- Suggested keywords from logged reasons (shown when there are
                 unattributed blocks in the window) --}}
            @php
                $matchedReasons = collect($matchedBlocks)->pluck('reason')->map(fn ($r) => trim((string) $r))->all();
                $unmatchedSuggestions = $reasonSuggestions->reject(fn ($r) => in_array($r, $matchedReasons, true))->values();
                $hasUnmatched = $totalBlocksInWindow > $matchedBlocks->count() && $unmatchedSuggestions->isNotEmpty();
            @endphp
            @if ($hasUnmatched)
                <section class="rounded-2xl border border-amber-500/30 bg-amber-900/10 p-4 md:p-5">
                    <div class="flex items-start gap-3">
                        <div class="font-display text-2xl text-amber-300">?</div>
                        <div class="text-sm text-slate-300 min-w-0 flex-1">
                            <div class="font-semibold text-amber-200">
                                {{ $totalBlocksInWindow - $matchedBlocks->count() }} logged {{ Str::plural('block', $totalBlocksInWindow - $matchedBlocks->count()) }} in this window aren't credited to this goal.
                            </div>
                            <p class="mt-1 text-xs text-slate-400">
                                Either they belong to a different goal, or this goal's keywords don't cover them yet.
                                Click any reason below to add a keyword from it.
                            </p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach ($unmatchedSuggestions->take(12) as $reason)
                                    @php
                                        // Pick the first non-trivial word from the reason as the candidate keyword.
                                        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($reason));
                                        $candidate = collect($words)->first(fn ($w) => mb_strlen((string) $w) >= 3);
                                    @endphp
                                    @if ($candidate)
                                        <form method="POST" action="{{ route('goals.keywords.add', $goal) }}" class="inline">
                                            @csrf
                                            <input type="hidden" name="keyword" value="{{ $candidate }}">
                                            <button type="submit"
                                                class="inline-flex items-center gap-1 rounded-full border border-slate-700/60 bg-slate-900/60 hover:border-[var(--chrono-blue)] hover:text-[var(--chrono-blue)] px-2.5 py-1 text-xs text-slate-300"
                                                title="Add &quot;{{ $candidate }}&quot; as a keyword">
                                                <span class="text-[var(--chrono-blue)]">+</span>
                                                <span>{{ Str::limit($reason, 40) }}</span>
                                            </button>
                                        </form>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </div>
                </section>
            @endif

            {{-- Attributed blocks --}}
            <section class="chrono-panel rounded-2xl p-6 md:p-8">
                <div class="flex items-baseline justify-between gap-3 mb-3">
                    <h2 class="font-display text-sm uppercase tracking-[0.3em] text-slate-300">Attributed blocks</h2>
                    <a href="{{ route('history.index') }}" class="text-xs uppercase tracking-[0.2em] text-[var(--chrono-blue)] hover:underline">
                        Full history →
                    </a>
                </div>
                <p class="text-xs text-slate-500 mb-4">
                    Time blocks whose reason matched this goal's keywords. <strong>Match</strong> is the keyword score (0–1).
                    <strong>Share</strong> is how much of that block was credited here vs other goals that also matched.
                </p>

                @if ($matchedBlocks->isEmpty())
                    <p class="text-sm text-slate-500">
                        @if (empty($keywords))
                            Add keywords to this goal first.
                        @elseif ($totalBlocksInWindow === 0)
                            No time blocks logged in this window yet — log some on the
                            <a href="{{ route('dashboard') }}" class="underline text-[var(--chrono-blue)]">dashboard</a>.
                        @else
                            None of the {{ $totalBlocksInWindow }} logged {{ Str::plural('block', $totalBlocksInWindow) }} match this goal's keywords. Use the suggestions above to fix this.
                        @endif
                    </p>
                @else
                    <ul class="divide-y divide-slate-800/60 text-sm">
                        @foreach ($matchedBlocks as $entry)
                            @php
                                $block = $entry['block'];
                                $sharePct = round($entry['share'] * 100);
                                $scorePct = round($entry['score'] * 100);
                                // Static class strings so Tailwind's JIT
                                // scanner finds them.
                                $shareColorClass = $sharePct >= 90
                                    ? 'text-emerald-300'
                                    : ($sharePct >= 50 ? 'text-sky-300' : 'text-amber-300');
                            @endphp
                            <li class="py-2.5 flex items-center justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="text-slate-100">
                                        {{ $block->start_time->format('D, M j · g:i A') }}
                                        <span class="text-slate-500">· {{ $entry['attributed_hours'] }}h</span>
                                        @if ($entry['share'] < 1.0)
                                            <span class="text-slate-600 text-xs">({{ $entry['block_hours'] }}h × {{ $sharePct }}%)</span>
                                        @endif
                                    </div>
                                    @if ($block->reason)
                                        <div class="mt-0.5 text-xs text-slate-400 truncate">{{ $block->reason }}</div>
                                    @endif
                                </div>
                                <div class="flex flex-col items-end gap-0.5 text-[0.65rem] uppercase tracking-wider">
                                    <span class="text-slate-400">Match {{ $scorePct }}%</span>
                                    <span class="{{ $shareColorClass }}">Share {{ $sharePct }}%</span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        <div class="space-y-6">
            {{-- Probability breakdown --}}
            <section class="chrono-panel rounded-2xl p-6 md:p-8">
                <h2 class="font-display text-sm uppercase tracking-[0.3em] text-slate-300 mb-3">How this is calculated</h2>
                <dl class="space-y-2 text-xs">
                    @if (isset($details['consistency']))
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500">Consistency</dt>
                            <dd class="text-slate-200 font-digital">{{ round($details['consistency'] * 100) }}%</dd>
                        </div>
                    @endif
                    @if (isset($details['recent_activity']))
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500">Recent activity (7d)</dt>
                            <dd class="text-slate-200 font-digital">{{ round($details['recent_activity'] * 100) }}%</dd>
                        </div>
                    @endif
                    @if (isset($details['pace_signal']))
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500">Pace signal</dt>
                            <dd class="text-slate-200 font-digital">{{ round($details['pace_signal'] * 100) }}%</dd>
                        </div>
                    @endif
                    @if (isset($details['time_buffer']))
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500">Time buffer</dt>
                            <dd class="text-slate-200 font-digital">{{ round($details['time_buffer'] * 100) }}%</dd>
                        </div>
                    @endif
                    @if (isset($details['extension_penalty']) && $details['extension_penalty'] > 0)
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500">Extension penalty</dt>
                            <dd class="text-rose-300 font-digital">−{{ $details['extension_penalty'] }}</dd>
                        </div>
                    @endif
                    @if (isset($details['score']))
                        <div class="flex justify-between gap-2 pt-2 border-t border-slate-800/60">
                            <dt class="text-slate-400">Score → sigmoid</dt>
                            <dd class="text-slate-100 font-digital">{{ $details['score'] }}</dd>
                        </div>
                    @endif
                </dl>
                <p class="mt-3 text-[0.65rem] text-slate-500 leading-relaxed">
                    P = σ(1.5·(2c−1) + 1.2·(2r−1) + 0.8·(2·tanh(h/4)−1) + 0.6·(2b−1) − 0.20·ext)<br>
                    where c = consistency, r = recent activity, h = avg hrs/day (last 7d), b = time buffer, ext = extensions.
                </p>
            </section>

            {{-- Actions --}}
            <section class="chrono-panel rounded-2xl p-6 md:p-8 space-y-3">
                <h2 class="font-display text-sm uppercase tracking-[0.3em] text-slate-300 mb-1">Actions</h2>

                @if ($goal->status === 'active')
                    <button type="button" data-toggle="extend"
                        class="w-full rounded-lg border border-slate-700 hover:border-slate-500 text-slate-100 px-4 py-2 text-sm">
                        Extend deadline
                    </button>
                    <a href="{{ route('goals.edit', $goal) }}"
                        class="block text-center w-full rounded-lg border border-slate-700 hover:border-slate-500 text-slate-100 px-4 py-2 text-sm">
                        Edit goal
                    </a>
                    <form method="POST" action="{{ route('goals.complete', $goal) }}">
                        @csrf
                        <button type="submit"
                            class="w-full rounded-lg bg-emerald-500/20 hover:bg-emerald-500/30 border border-emerald-500/40 text-emerald-200 px-4 py-2 text-sm">
                            Mark complete
                        </button>
                    </form>
                    <button type="button" data-toggle="abandon"
                        class="w-full rounded-lg border border-rose-500/40 hover:border-rose-300 text-rose-300 px-4 py-2 text-sm">
                        Abandon goal
                    </button>
                @else
                    <a href="{{ route('goals.logs', $goal) }}"
                        class="block text-center w-full rounded-lg border border-slate-700 hover:border-slate-500 text-slate-100 px-4 py-2 text-sm">
                        View full log
                    </a>
                @endif

                <form method="POST" action="{{ route('goals.destroy', $goal) }}"
                    onsubmit="return confirm('Delete this goal and all its logs? This cannot be undone.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                        class="w-full rounded-lg border border-slate-700/40 hover:border-rose-500/60 text-slate-500 hover:text-rose-300 px-4 py-2 text-xs uppercase tracking-wider">
                        Delete permanently
                    </button>
                </form>
            </section>

            <section class="chrono-panel rounded-2xl p-6 md:p-8">
                <h2 class="font-display text-sm uppercase tracking-[0.3em] text-slate-300 mb-3">Goal log</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Total log entries</dt>
                        <dd class="text-slate-100 font-digital">{{ $logCount }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Times extended</dt>
                        <dd class="text-slate-100 font-digital">{{ $goal->extension_count }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Times changed</dt>
                        <dd class="text-slate-100 font-digital">{{ $goal->change_count }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Original target</dt>
                        <dd class="text-slate-100">{{ $goal->original_target_date->format('M j, Y') }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Current target</dt>
                        <dd class="text-slate-100">{{ $goal->target_date->format('M j, Y') }}</dd>
                    </div>
                </dl>
                <a href="{{ route('goals.logs', $goal) }}"
                    class="mt-4 inline-block text-xs uppercase tracking-[0.2em] text-[var(--chrono-blue)] hover:underline">
                    View full log →
                </a>
            </section>
        </div>
    </div>

    {{-- Extend modal --}}
    @if ($goal->status === 'active')
        <div data-modal="extend" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 backdrop-blur-sm p-4">
            <div class="w-full max-w-md rounded-2xl border border-slate-700/60 bg-[var(--chrono-bg)] p-6 shadow-2xl">
                <h3 class="font-display text-base uppercase tracking-[0.2em] text-slate-100">Extend deadline</h3>
                <p class="mt-2 text-xs text-slate-400">
                    Each extension applies a probability penalty so the number stays honest. Reason is required.
                </p>
                <form method="POST" action="{{ route('goals.extend', $goal) }}" class="mt-4 space-y-3">
                    @csrf
                    <div>
                        <label class="block text-[0.65rem] uppercase tracking-wider text-slate-500 mb-1" for="extend_target">New target date</label>
                        <input id="extend_target" name="target_date" type="date" required
                            min="{{ $goal->target_date->copy()->addDay()->toDateString() }}"
                            value="{{ $goal->target_date->copy()->addDays(7)->toDateString() }}"
                            class="w-full rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 text-slate-100">
                    </div>
                    <div>
                        <label class="block text-[0.65rem] uppercase tracking-wider text-slate-500 mb-1" for="extend_reason">Why are you extending?</label>
                        <textarea id="extend_reason" name="reason" rows="3" maxlength="500" required
                            class="w-full rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 text-slate-100"
                            placeholder="What changed?"></textarea>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" data-modal-close
                            class="rounded-lg border border-slate-600 hover:border-slate-400 px-4 py-2 text-sm text-slate-200">
                            Cancel
                        </button>
                        <button type="submit"
                            class="rounded-lg bg-amber-500/30 hover:bg-amber-500/50 border border-amber-400/60 text-amber-100 px-4 py-2 text-sm">
                            Extend
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div data-modal="abandon" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 backdrop-blur-sm p-4">
            <div class="w-full max-w-md rounded-2xl border border-slate-700/60 bg-[var(--chrono-bg)] p-6 shadow-2xl">
                <h3 class="font-display text-base uppercase tracking-[0.2em] text-slate-100">Abandon goal</h3>
                <p class="mt-2 text-xs text-slate-400">
                    The goal stays in your archive with the full log preserved. Reason is required.
                </p>
                <form method="POST" action="{{ route('goals.abandon', $goal) }}" class="mt-4 space-y-3">
                    @csrf
                    <div>
                        <label class="block text-[0.65rem] uppercase tracking-wider text-slate-500 mb-1" for="abandon_reason">Reason</label>
                        <textarea id="abandon_reason" name="reason" rows="3" maxlength="500" required
                            class="w-full rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 text-slate-100"
                            placeholder="What changed?"></textarea>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" data-modal-close
                            class="rounded-lg border border-slate-600 hover:border-slate-400 px-4 py-2 text-sm text-slate-200">
                            Cancel
                        </button>
                        <button type="submit"
                            class="rounded-lg bg-rose-500/30 hover:bg-rose-500/50 border border-rose-400/60 text-rose-100 px-4 py-2 text-sm">
                            Abandon
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @push('scripts')
        <script>
            (() => {
                const el = document.querySelector('[data-goal-countdown]');
                if (el) {
                    const deadline = new Date(el.dataset.deadline).getTime();
                    const fmt = (n) => String(n).padStart(2, '0');
                    const tick = () => {
                        const now = Date.now();
                        let diff = Math.max(0, deadline - now);
                        if (diff === 0) {
                            el.textContent = '00d 00:00:00';
                            el.classList.add('text-rose-400');
                            return;
                        }
                        const days = Math.floor(diff / 86400000); diff -= days * 86400000;
                        const hours = Math.floor(diff / 3600000); diff -= hours * 3600000;
                        const mins = Math.floor(diff / 60000); diff -= mins * 60000;
                        const secs = Math.floor(diff / 1000);
                        el.textContent = `${days}d ${fmt(hours)}:${fmt(mins)}:${fmt(secs)}`;
                    };
                    tick();
                    setInterval(tick, 1000);
                }

                document.querySelectorAll('[data-toggle]').forEach((btn) => {
                    btn.addEventListener('click', () => {
                        const target = document.querySelector(`[data-modal="${btn.dataset.toggle}"]`);
                        if (!target) return;
                        target.classList.remove('hidden');
                        target.classList.add('flex');
                    });
                });
                document.querySelectorAll('[data-modal]').forEach((modal) => {
                    const close = () => {
                        modal.classList.add('hidden');
                        modal.classList.remove('flex');
                    };
                    modal.querySelector('[data-modal-close]')?.addEventListener('click', close);
                    modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
                });
            })();
        </script>
    @endpush
@endsection
