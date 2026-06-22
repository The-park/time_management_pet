@extends('layouts.app')

@section('page_title', $goal->title)

@section('content')
    @php
        $tier = $result['tier'];
        $details = $result['details'];
        $remaining = (int) ($details['days_remaining'] ?? 0);
        $deadlineIso = $goal->target_date->copy()->endOfDay()->toIso8601String();
        $progress = max(0, min(100, (float) $result['percent']));
        $probDisplay = rtrim(rtrim(number_format($result['percent'], 1), '0'), '.');
        $maxSpark = max(0.5, collect($sparkline)->max('hours') ?: 0.5);
        $isPast = $remaining === 0 && $goal->status !== 'completed';
        $hasKeywords = ! empty($goal->keywords) && is_array($goal->keywords);
        $keywords = $hasKeywords ? $goal->keywords : [];
        $matchedCount = $attribution['blocks']->count();
        $competing = (int) ($details['competing_goals_count'] ?? 0);
        $matchedReasons = collect($matchedBlocks)->pluck('reason')->map(fn ($reason) => trim((string) $reason))->all();
        $unmatchedSuggestions = $reasonSuggestions->reject(fn ($reason) => in_array($reason, $matchedReasons, true))->values();
        $hasUnmatched = $totalBlocksInWindow > $matchedBlocks->count() && $unmatchedSuggestions->isNotEmpty();
        $alertLevel = null;
        if ($goal->status === 'active') {
            if ($isPast || $result['percent'] < 20) {
                $alertLevel = 'critical';
            } elseif ($result['percent'] < 30 || ($remaining <= 7 && $result['percent'] < 60)) {
                $alertLevel = 'warning';
            }
        }
        $statusInfo = match ($goal->status) {
            'completed' => ['label' => 'Completed', 'class' => 'border-emerald-500/30 bg-emerald-500/10 text-emerald-200', 'dot' => 'bg-emerald-400'],
            'abandoned' => ['label' => 'Abandoned', 'class' => 'border-slate-600 bg-slate-800/50 text-slate-300', 'dot' => 'bg-slate-400'],
            'missed' => ['label' => 'Missed', 'class' => 'border-rose-500/30 bg-rose-500/10 text-rose-200', 'dot' => 'bg-rose-400'],
            default => ['label' => 'Active', 'class' => 'border-sky-500/30 bg-sky-500/10 text-sky-200', 'dot' => 'bg-sky-400'],
        };
        $timeAnalysis = $timeAnalysis;
        $activity = $activityBreakdown;
        $activityTotal = max(0.001, $activity['productive_hours'] + $activity['wasted_hours'] + $activity['neutral_hours'] + $activity['unlogged_awake_hours']);
        $productivePct = (int) round(($activity['productive_hours'] / $activityTotal) * 100);
        $wastedPct = (int) round(($activity['wasted_hours'] / $activityTotal) * 100);
        $neutralPct = (int) round(($activity['neutral_hours'] / $activityTotal) * 100);
        $unloggedPct = max(0, 100 - $productivePct - $wastedPct - $neutralPct);
    @endphp

    <div class="mx-auto max-w-7xl space-y-5">
        <header class="border-b border-slate-800/70 pb-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0">
                    <a href="{{ route('goals.index') }}" class="inline-flex items-center gap-1 text-xs text-slate-500 hover:text-slate-200">
                        <span aria-hidden="true">&larr;</span>
                        All goals
                    </a>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <h1 class="min-w-0 break-words text-2xl font-semibold text-slate-100 md:text-3xl">{{ $goal->title }}</h1>
                        <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full border px-2 py-1 text-xs {{ $statusInfo['class'] }}">
                            <span class="h-1.5 w-1.5 rounded-full {{ $statusInfo['dot'] }}"></span>
                            {{ $statusInfo['label'] }}
                        </span>
                        <span class="rounded-full border border-slate-700 bg-slate-900/60 px-2 py-1 text-xs capitalize text-slate-400">{{ $goal->category }}</span>
                    </div>
                    @if ($goal->description)
                        <p class="mt-2 max-w-3xl whitespace-pre-line text-sm leading-6 text-slate-400">{{ $goal->description }}</p>
                    @endif
                </div>

                <div class="flex shrink-0 flex-wrap items-center gap-2">
                    @if ($goal->status === 'active')
                        <a href="{{ route('goals.edit', $goal) }}" class="rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-200 transition-colors hover:border-slate-500 hover:bg-slate-800/50">
                            Edit
                        </a>
                        <form method="POST" action="{{ route('goals.complete', $goal) }}">
                            @csrf
                            <button type="submit" class="rounded-lg border border-emerald-500/40 bg-emerald-500/10 px-3 py-2 text-sm text-emerald-200 transition-colors hover:bg-emerald-500/20">
                                Mark complete
                            </button>
                        </form>
                    @else
                        <a href="{{ route('goals.logs', $goal) }}" class="rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-200 transition-colors hover:border-slate-500 hover:bg-slate-800/50">
                            View log
                        </a>
                    @endif
                </div>
            </div>
        </header>

        @if ($alertLevel)
            <section class="border-l-2 {{ $alertLevel === 'critical' ? 'border-rose-400 bg-rose-500/5' : 'border-amber-400 bg-amber-500/5' }} px-4 py-3">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-medium {{ $alertLevel === 'critical' ? 'text-rose-200' : 'text-amber-200' }}">
                            @if ($isPast)
                                Target date reached
                            @elseif ($alertLevel === 'critical')
                                This goal needs attention
                            @else
                                Your pace is slipping
                            @endif
                        </p>
                        <p class="mt-0.5 text-xs text-slate-400">{{ $narrative }}</p>
                    </div>
                    @if ($goal->status === 'active')
                        <button type="button" data-toggle="extend" class="self-start rounded-lg border border-amber-500/40 px-3 py-1.5 text-xs text-amber-200 transition-colors hover:bg-amber-500/10 sm:self-auto">
                            Extend deadline
                        </button>
                    @endif
                </div>
            </section>
        @endif

        <section class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_19rem]">
            <div class="border border-slate-800/70 bg-slate-950/20 p-5">
                <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-xs text-slate-500">Completion outlook</p>
                        <div class="mt-1 flex items-baseline gap-3">
                            <span class="font-digital text-5xl" style="color: {{ $tier['hex'] }}">{{ $probDisplay }}%</span>
                            <span class="text-sm" style="color: {{ $tier['hex'] }}">{{ $tier['label'] }}</span>
                        </div>
                    </div>
                    <p class="max-w-md text-sm leading-6 text-slate-400">{{ $narrative }}</p>
                </div>
                <div class="mt-5 h-2 overflow-hidden rounded-full bg-slate-800">
                    <div class="h-full rounded-full transition-[width] duration-700" style="width: {{ max(2, $progress) }}%; background-color: {{ $tier['hex'] }}"></div>
                </div>
                <div class="mt-5 grid grid-cols-2 gap-x-5 gap-y-4 sm:grid-cols-4">
                    <div>
                        <p class="text-xs text-slate-500">Target</p>
                        <p class="mt-1 text-sm font-medium text-slate-100">{{ $goal->target_date->format('M j, Y') }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">Time left</p>
                        <p class="mt-1 font-digital text-lg {{ $isPast ? 'text-rose-300' : 'text-slate-100' }}" data-goal-countdown data-deadline="{{ $deadlineIso }}">--</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">Logged</p>
                        <p class="mt-1 font-digital text-lg text-slate-100">{{ $details['hours_done'] ?? 0 }}h</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">Recent pace</p>
                        <p class="mt-1 font-digital text-lg text-slate-100">{{ $details['avg_recent_hours_per_day'] ?? '--' }}<span class="text-xs text-slate-500">h/day</span></p>
                    </div>
                </div>
            </div>

            <aside class="border border-slate-800/70 bg-slate-950/20 p-5">
                <p class="text-xs font-medium text-slate-300">Next step</p>
                @if (! $hasKeywords)
                    <p class="mt-2 text-sm leading-6 text-slate-400">Add matching words so dashboard logs can be credited to this goal.</p>
                    <a href="{{ route('goals.edit', $goal) }}" class="mt-4 inline-flex rounded-lg border border-sky-500/40 px-3 py-2 text-sm text-sky-200 transition-colors hover:bg-sky-500/10">Add keywords</a>
                @elseif ($matchedCount === 0 && ($details['days_passed'] ?? 0) > 0)
                    <p class="mt-2 text-sm leading-6 text-slate-400">No logs match this goal yet. Refine the keywords or log a matching activity.</p>
                    <a href="{{ route('goals.edit', $goal) }}" class="mt-4 inline-flex rounded-lg border border-sky-500/40 px-3 py-2 text-sm text-sky-200 transition-colors hover:bg-sky-500/10">Refine keywords</a>
                @elseif ($goal->status === 'active')
                    <p class="mt-2 text-sm leading-6 text-slate-400">Keep the next logged block specific to this goal so its pace remains accurate.</p>
                    <a href="{{ route('dashboard') }}" class="mt-4 inline-flex rounded-lg border border-sky-500/40 px-3 py-2 text-sm text-sky-200 transition-colors hover:bg-sky-500/10">Log time</a>
                @else
                    <p class="mt-2 text-sm leading-6 text-slate-400">This goal is archived. Its evidence and change history remain available below.</p>
                @endif
            </aside>
        </section>

        <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_19rem]">
            <main class="min-w-0 space-y-5">
                <section class="border border-slate-800/70 bg-slate-950/20 p-5">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <h2 class="text-base font-semibold text-slate-100">Momentum</h2>
                            <p class="mt-1 text-xs text-slate-500">Attributed time across the last 14 days</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-slate-500">Consistency</p>
                            <p class="mt-1 font-digital text-lg text-slate-100">
                                @if (isset($details['days_with_logs'], $details['days_passed']) && $details['days_passed'] > 0)
                                    {{ round(($details['days_with_logs'] / $details['days_passed']) * 100) }}%
                                @else
                                    --
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="mt-5 flex h-28 items-end gap-1" aria-label="Last fourteen days of attributed time">
                        @foreach ($sparkline as $day)
                            @php
                                $hours = $day['hours'];
                                $height = $maxSpark > 0 ? round(($hours / $maxSpark) * 100) : 0;
                            @endphp
                            <div class="flex h-full flex-1 items-end" title="{{ $day['label'] }}: {{ round($hours, 2) }}h">
                                <div class="w-full rounded-sm" style="height: {{ max(3, $height) }}%; background-color: {{ $hours > 0 ? $tier['hex'] : '#263244' }}; opacity: {{ $hours > 0 ? 0.85 : 0.45 }}"></div>
                            </div>
                        @endforeach
                    </div>
                    @if (! empty($sparkline))
                        <div class="mt-2 flex justify-between text-xs text-slate-600">
                            <span>{{ $sparkline[0]['label'] }}</span>
                            <span>{{ end($sparkline)['label'] }}</span>
                        </div>
                    @endif
                </section>

                <section class="border border-slate-800/70 bg-slate-950/20 p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-base font-semibold text-slate-100">Match your logs</h2>
                            <p class="mt-1 text-xs text-slate-500">These keywords connect dashboard activities to this goal.</p>
                        </div>
                        <a href="{{ route('goals.edit', $goal) }}" class="text-sm text-sky-300 hover:text-sky-200">Edit keywords</a>
                    </div>

                    @if ($hasKeywords)
                        <div class="mt-4 flex flex-wrap gap-2">
                            @foreach ($keywords as $keyword)
                                <span class="rounded-full border border-sky-500/25 bg-sky-500/5 px-2.5 py-1 text-xs text-sky-200">{{ $keyword }}</span>
                            @endforeach
                        </div>
                        @if ($competing > 0)
                            <p class="mt-4 text-xs leading-5 text-slate-500">{{ $competing }} other active {{ Str::plural('goal', $competing) }} can match the same logs. Shared matches are split instead of double-counted.</p>
                        @endif
                    @else
                        <p class="mt-4 text-sm text-amber-200">No keywords are set, so this goal cannot receive time from your dashboard logs.</p>
                    @endif

                    @if ($hasUnmatched)
                        <div class="mt-5 border-t border-slate-800 pt-4">
                            <p class="text-sm text-slate-300">Suggested matches</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $totalBlocksInWindow - $matchedBlocks->count() }} logged {{ Str::plural('block', $totalBlocksInWindow - $matchedBlocks->count()) }} in this window are not credited here.</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach ($unmatchedSuggestions->take(10) as $reason)
                                    @php
                                        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($reason));
                                        $candidate = collect($words)->first(fn ($word) => mb_strlen((string) $word) >= 3);
                                    @endphp
                                    @if ($candidate)
                                        <form method="POST" action="{{ route('goals.keywords.add', $goal) }}">
                                            @csrf
                                            <input type="hidden" name="keyword" value="{{ $candidate }}">
                                            <button type="submit" class="rounded-lg border border-slate-700 px-2.5 py-1.5 text-xs text-slate-300 transition-colors hover:border-sky-500/50 hover:text-sky-200" title="Add {{ $candidate }} as a keyword">
                                                + {{ Str::limit($reason, 34) }}
                                            </button>
                                        </form>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endif
                </section>

                <section class="border border-slate-800/70 bg-slate-950/20">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-800/70 px-5 py-4">
                        <div>
                            <h2 class="text-base font-semibold text-slate-100">Recent credited activity</h2>
                            <p class="mt-1 text-xs text-slate-500">The most recent blocks attributed to this goal.</p>
                        </div>
                        <a href="{{ route('history.index') }}" class="text-sm text-sky-300 hover:text-sky-200">Open history</a>
                    </div>
                    @if ($matchedBlocks->isEmpty())
                        <p class="px-5 py-8 text-sm text-slate-500">
                            @if (! $hasKeywords)
                                Add keywords to begin matching dashboard logs.
                            @elseif ($totalBlocksInWindow === 0)
                                No time blocks have been logged in this goal's window yet.
                            @else
                                None of the logged blocks match this goal's keywords yet.
                            @endif
                        </p>
                    @else
                        <ul class="divide-y divide-slate-800/70">
                            @foreach ($matchedBlocks as $entry)
                                @php
                                    $block = $entry['block'];
                                    $sharePct = round($entry['share'] * 100);
                                    $scorePct = round($entry['score'] * 100);
                                    $category = $block->category ?? 'productive';
                                    $categoryClass = $category === 'wasted' ? 'text-rose-300' : ($category === 'neutral' ? 'text-slate-300' : 'text-emerald-300');
                                @endphp
                                <li class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="min-w-0">
                                        <p class="text-sm text-slate-100">{{ $block->start_time->format('D, M j g:i A') }} <span class="ml-2 font-digital text-sky-200">{{ $entry['attributed_hours'] }}h</span></p>
                                        <p class="mt-1 truncate text-xs text-slate-500">{{ $block->reason ?: 'No activity label' }}</p>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-3 text-xs">
                                        <span class="capitalize {{ $categoryClass }}">{{ $category }}</span>
                                        <span class="text-slate-500">{{ $sharePct }}% share</span>
                                        <span class="text-slate-500">{{ $scorePct }}% match</span>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </main>

            <aside class="space-y-5">
                <section class="border border-slate-800/70 bg-slate-950/20 p-5">
                    <h2 class="text-base font-semibold text-slate-100">Goal details</h2>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex items-baseline justify-between gap-4"><dt class="text-slate-500">Start</dt><dd class="text-right text-slate-200">{{ $goal->start_date->format('M j, Y') }}</dd></div>
                        <div class="flex items-baseline justify-between gap-4"><dt class="text-slate-500">Target</dt><dd class="text-right text-slate-200">{{ $goal->target_date->format('M j, Y') }}</dd></div>
                        <div class="flex items-baseline justify-between gap-4"><dt class="text-slate-500">Days remaining</dt><dd class="text-right font-digital {{ $isPast ? 'text-rose-300' : 'text-slate-200' }}">{{ $remaining === 0 ? 'Today' : $remaining }}</dd></div>
                        <div class="flex items-baseline justify-between gap-4"><dt class="text-slate-500">Log entries</dt><dd class="text-right font-digital text-slate-200">{{ $logCount }}</dd></div>
                    </dl>
                    @if ($goal->status === 'active')
                        <div class="mt-5 border-t border-slate-800 pt-4">
                            <button type="button" data-toggle="extend" class="w-full rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-200 transition-colors hover:border-slate-500 hover:bg-slate-800/50">Extend deadline</button>
                        </div>
                    @endif
                </section>

                <details class="group border border-slate-800/70 bg-slate-950/20">
                    <summary class="flex cursor-pointer list-none items-center justify-between px-5 py-4 text-sm font-medium text-slate-200">
                        Project history
                        <span class="text-slate-500 transition-transform group-open:rotate-45">+</span>
                    </summary>
                    <div class="border-t border-slate-800/70 px-5 py-4">
                        <dl class="space-y-3 text-sm">
                            <div class="flex justify-between gap-4"><dt class="text-slate-500">Created</dt><dd class="text-right text-slate-200">{{ $lifecycle['created_at']?->format('M j, Y') ?? '--' }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-slate-500">Original target</dt><dd class="text-right text-slate-200">{{ $lifecycle['original_target']->format('M j, Y') }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-slate-500">Extensions</dt><dd class="text-right text-slate-200">{{ $lifecycle['extension_count'] }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-slate-500">Changes</dt><dd class="text-right text-slate-200">{{ $lifecycle['change_count'] }}</dd></div>
                        </dl>
                        <a href="{{ route('goals.logs', $goal) }}" class="mt-4 inline-flex text-sm text-sky-300 hover:text-sky-200">View full change log</a>
                    </div>
                </details>

                <details class="group border border-slate-800/70 bg-slate-950/20">
                    <summary class="flex cursor-pointer list-none items-center justify-between px-5 py-4 text-sm font-medium text-slate-200">
                        Time and score details
                        <span class="text-slate-500 transition-transform group-open:rotate-45">+</span>
                    </summary>
                    <div class="space-y-5 border-t border-slate-800/70 px-5 py-4">
                        <div>
                            <p class="text-xs font-medium text-slate-300">Time in this goal window</p>
                            <div class="mt-3 grid grid-cols-2 gap-3 text-xs">
                                <div><p class="text-slate-500">Awake elapsed</p><p class="mt-1 font-digital text-slate-100">{{ $timeAnalysis['elapsed']['awake_hours'] }}h</p></div>
                                <div><p class="text-slate-500">Scheduled sleep</p><p class="mt-1 font-digital text-slate-100">{{ $timeAnalysis['elapsed']['sleep_hours'] }}h</p></div>
                                <div><p class="text-slate-500">Awake remaining</p><p class="mt-1 font-digital text-slate-100">{{ $timeAnalysis['remaining']['awake_hours'] }}h</p></div>
                                <div><p class="text-slate-500">Sleep schedule</p><p class="mt-1 text-slate-200">{{ $timeAnalysis['sleep']['end_of_day'] }} - {{ $timeAnalysis['sleep']['wake_time'] }}</p></div>
                            </div>
                        </div>

                        <div>
                            <p class="text-xs font-medium text-slate-300">Activity mix</p>
                            <div class="mt-3 flex h-2 overflow-hidden rounded-full bg-slate-800">
                                <span class="bg-emerald-400" style="width: {{ $productivePct }}%"></span><span class="bg-rose-400" style="width: {{ $wastedPct }}%"></span><span class="bg-slate-400" style="width: {{ $neutralPct }}%"></span><span class="bg-yellow-400" style="width: {{ $unloggedPct }}%"></span>
                            </div>
                            <dl class="mt-3 space-y-2 text-xs">
                                <div class="flex justify-between"><dt class="text-emerald-300">Productive</dt><dd class="font-digital text-slate-200">{{ $activity['productive_hours'] }}h</dd></div>
                                <div class="flex justify-between"><dt class="text-rose-300">Wasted</dt><dd class="font-digital text-slate-200">{{ $activity['wasted_hours'] }}h</dd></div>
                                <div class="flex justify-between"><dt class="text-slate-300">Neutral</dt><dd class="font-digital text-slate-200">{{ $activity['neutral_hours'] }}h</dd></div>
                                <div class="flex justify-between"><dt class="text-yellow-300">Unlogged awake</dt><dd class="font-digital text-slate-200">{{ $activity['unlogged_awake_hours'] }}h</dd></div>
                            </dl>
                        </div>

                        <div class="border-t border-slate-800 pt-4 text-xs">
                            <p class="font-medium text-slate-300">Outlook inputs</p>
                            <dl class="mt-3 space-y-2">
                                @foreach (['consistency' => 'Consistency', 'recent_activity' => 'Recent activity', 'pace_signal' => 'Pace signal', 'time_buffer' => 'Time buffer'] as $key => $label)
                                    @if (isset($details[$key]))
                                        <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ $label }}</dt><dd class="font-digital text-slate-200">{{ round($details[$key] * 100) }}%</dd></div>
                                    @endif
                                @endforeach
                                @if (($details['extension_penalty'] ?? 0) > 0)
                                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Extension penalty</dt><dd class="font-digital text-rose-300">-{{ $details['extension_penalty'] }}</dd></div>
                                @endif
                            </dl>
                        </div>
                    </div>
                </details>

                @if ($goal->status === 'active')
                    <details class="group border border-slate-800/70 bg-slate-950/20">
                        <summary class="flex cursor-pointer list-none items-center justify-between px-5 py-4 text-sm text-slate-500">
                            More actions
                            <span class="transition-transform group-open:rotate-45">+</span>
                        </summary>
                        <div class="space-y-3 border-t border-slate-800/70 px-5 py-4">
                            <button type="button" data-toggle="abandon" class="w-full rounded-lg border border-rose-500/30 px-3 py-2 text-sm text-rose-300 transition-colors hover:bg-rose-500/10">Abandon goal</button>
                            <form method="POST" action="{{ route('goals.destroy', $goal) }}" onsubmit="return confirm('Delete this goal and all its logs? This cannot be undone.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="w-full rounded-lg border border-slate-800 px-3 py-2 text-xs text-slate-500 transition-colors hover:border-rose-500/50 hover:text-rose-300">Delete permanently</button>
                            </form>
                        </div>
                    </details>
                @endif
            </aside>
        </div>
    </div>

    @if ($goal->status === 'active')
        <div data-modal="extend" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 p-4">
            <div class="w-full max-w-md rounded-lg border border-slate-700 bg-[var(--chrono-bg)] p-6 shadow-2xl">
                <h2 class="text-lg font-semibold text-slate-100">Extend deadline</h2>
                <p class="mt-2 text-sm text-slate-400">Each extension affects the outlook. Add a short reason so the history remains useful.</p>
                <form method="POST" action="{{ route('goals.extend', $goal) }}" class="mt-5 space-y-4">
                    @csrf
                    <div>
                        <label class="mb-1 block text-xs text-slate-400" for="extend_target">New target date</label>
                        <input id="extend_target" name="target_date" type="date" required min="{{ $goal->target_date->copy()->addDay()->toDateString() }}" value="{{ $goal->target_date->copy()->addDays(7)->toDateString() }}" class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-slate-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-slate-400" for="extend_reason">Reason</label>
                        <textarea id="extend_reason" name="reason" rows="3" maxlength="500" required class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-slate-100" placeholder="What changed?"></textarea>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" data-modal-close class="rounded-lg border border-slate-600 px-3 py-2 text-sm text-slate-200">Cancel</button>
                        <button type="submit" class="rounded-lg border border-amber-400/50 bg-amber-500/15 px-3 py-2 text-sm text-amber-100 hover:bg-amber-500/25">Extend</button>
                    </div>
                </form>
            </div>
        </div>

        <div data-modal="abandon" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 p-4">
            <div class="w-full max-w-md rounded-lg border border-slate-700 bg-[var(--chrono-bg)] p-6 shadow-2xl">
                <h2 class="text-lg font-semibold text-slate-100">Abandon goal</h2>
                <p class="mt-2 text-sm text-slate-400">The goal and its full history will remain in your archive.</p>
                <form method="POST" action="{{ route('goals.abandon', $goal) }}" class="mt-5 space-y-4">
                    @csrf
                    <div>
                        <label class="mb-1 block text-xs text-slate-400" for="abandon_reason">Reason</label>
                        <textarea id="abandon_reason" name="reason" rows="3" maxlength="500" required class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-slate-100" placeholder="What changed?"></textarea>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" data-modal-close class="rounded-lg border border-slate-600 px-3 py-2 text-sm text-slate-200">Cancel</button>
                        <button type="submit" class="rounded-lg border border-rose-400/50 bg-rose-500/15 px-3 py-2 text-sm text-rose-100 hover:bg-rose-500/25">Abandon</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @push('scripts')
        <script>
            (() => {
                const countdown = document.querySelector('[data-goal-countdown]');
                if (countdown) {
                    const deadline = new Date(countdown.dataset.deadline).getTime();
                    const pad = (value) => String(value).padStart(2, '0');
                    const refreshCountdown = () => {
                        let remaining = Math.max(0, deadline - Date.now());
                        const days = Math.floor(remaining / 86400000);
                        remaining -= days * 86400000;
                        const hours = Math.floor(remaining / 3600000);
                        remaining -= hours * 3600000;
                        const minutes = Math.floor(remaining / 60000);
                        countdown.textContent = `${days}d ${pad(hours)}:${pad(minutes)}`;
                    };
                    refreshCountdown();
                    setInterval(refreshCountdown, 30000);
                }

                document.querySelectorAll('[data-toggle]').forEach((button) => {
                    button.addEventListener('click', () => {
                        const modal = document.querySelector(`[data-modal="${button.dataset.toggle}"]`);
                        if (!modal) return;
                        modal.classList.remove('hidden');
                        modal.classList.add('flex');
                    });
                });
                document.querySelectorAll('[data-modal]').forEach((modal) => {
                    const close = () => {
                        modal.classList.add('hidden');
                        modal.classList.remove('flex');
                    };
                    modal.querySelector('[data-modal-close]')?.addEventListener('click', close);
                    modal.addEventListener('click', (event) => {
                        if (event.target === modal) close();
                    });
                });
            })();
        </script>
    @endpush
@endsection
