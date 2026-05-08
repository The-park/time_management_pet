@extends('layouts.admin')

@section('page_title', $user->name.' · '.$dateLabel)

@section('content')
    @php
        $fmt = function (int $sec) {
            $totalMin = max(0, (int) round($sec / 60));
            if ($totalMin === 0) return '0m';
            if ($totalMin < 60) return $totalMin.'m';
            $h = intdiv($totalMin, 60);
            $m = $totalMin % 60;
            return $m === 0 ? $h.'h' : $h.'h '.$m.'m';
        };
        if ($efficiencyPct >= 70) {
            $tierBigText = 'text-emerald-300';
            $tierBoxBorder = 'border-emerald-500/30';
            $tierBoxBg = 'bg-emerald-500/5';
            $tierBoxLabel = 'text-emerald-300';
            $tierBoxValue = 'text-emerald-200';
        } elseif ($efficiencyPct >= 40) {
            $tierBigText = 'text-amber-300';
            $tierBoxBorder = 'border-amber-500/30';
            $tierBoxBg = 'bg-amber-500/5';
            $tierBoxLabel = 'text-amber-300';
            $tierBoxValue = 'text-amber-200';
        } else {
            $tierBigText = 'text-rose-300';
            $tierBoxBorder = 'border-rose-500/30';
            $tierBoxBg = 'bg-rose-500/5';
            $tierBoxLabel = 'text-rose-300';
            $tierBoxValue = 'text-rose-200';
        }
        $totalForBar = max(1, $productiveSec + $wastedSec + $unloggedSec);
        $prodPct = (int) round(($productiveSec / $totalForBar) * 100);
        $wastedPct = (int) round(($wastedSec / $totalForBar) * 100);
        $unloggedPct = max(0, 100 - $prodPct - $wastedPct);
        $totalDaySec = 24 * 3600;
        $initials = collect(preg_split('/\s+/', trim($user->name ?? '?')))
            ->filter()->take(2)->map(fn($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
    @endphp

    {{-- Breadcrumb --}}
    <div class="mb-6 text-xs uppercase tracking-[0.2em] text-slate-500">
        <a href="{{ route('admin.dashboard') }}" class="hover:text-slate-300">Admin</a>
        <span class="mx-1.5 text-slate-700">/</span>
        <a href="{{ route('admin.users.index') }}" class="hover:text-slate-300">Users</a>
        <span class="mx-1.5 text-slate-700">/</span>
        <a href="{{ route('admin.users.show', $user->id) }}" class="hover:text-slate-300">#{{ $user->id }}</a>
        <span class="mx-1.5 text-slate-700">/</span>
        <span class="text-slate-300">{{ $date->format('M j, Y') }}</span>
    </div>

    {{-- Hero --}}
    <section class="rounded-xl border border-slate-800/60 bg-gradient-to-br from-slate-900/60 to-slate-950/60 p-6 mb-6">
        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-5">
            <div class="flex items-start gap-4 min-w-0">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border border-slate-800 bg-slate-950/80 text-sm font-display tracking-[0.15em] text-slate-200">
                    {{ $initials ?: '?' }}
                </div>
                <div class="min-w-0">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Day report · admin view</div>
                    <h1 class="font-display text-2xl tracking-[0.15em] uppercase text-slate-100 mt-1">{{ $dateLabel }}</h1>
                    <p class="text-sm text-slate-400 mt-1">
                        {{ $user->name }} · <span class="text-slate-300">{{ $user->email }}</span>
                    </p>
                    <p class="text-xs text-slate-500 mt-1">
                        @if ($isFuture)
                            Future date — no logs yet.
                        @elseif ($isCurrentDay)
                            User's current day (still in progress).
                        @else
                            Read-only historical report.
                        @endif
                    </p>
                </div>
            </div>
            <div class="text-right">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Efficiency</div>
                <div class="font-display text-4xl {{ $tierBigText }}">{{ $efficiencyPct }}%</div>
                <div class="text-[0.65rem] text-slate-500 mt-0.5">productive ÷ (prod + wasted + unlogged)</div>
            </div>
        </div>
    </section>

    {{-- Stat tiles --}}
    <section class="rounded-xl border border-slate-800/60 bg-slate-900/40 overflow-hidden mb-6">
        <header class="px-5 py-3 border-b border-slate-800/60">
            <h2 class="font-display text-xs uppercase tracking-[0.2em] text-slate-300">Time breakdown</h2>
        </header>
        <div class="p-5 space-y-5">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
                    <div class="text-[0.6rem] uppercase tracking-wider text-slate-500">Total in day</div>
                    <div class="mt-1 text-lg tabular-nums text-slate-100">{{ $fmt($totalDaySec) }}</div>
                    <div class="text-[0.65rem] text-slate-500 mt-0.5">24h calendar</div>
                </div>
                <div class="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
                    <div class="text-[0.6rem] uppercase tracking-wider text-slate-500">Sleep</div>
                    <div class="mt-1 text-lg tabular-nums text-slate-300">{{ $fmt($sleepSec) }}</div>
                    <div class="text-[0.65rem] text-slate-500 mt-0.5">{{ $sleepWindowLabel }}</div>
                </div>
                <div class="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
                    <div class="text-[0.6rem] uppercase tracking-wider text-slate-500">Awake</div>
                    <div class="mt-1 text-lg tabular-nums text-slate-100">{{ $fmt($awakeSec) }}</div>
                    <div class="text-[0.65rem] text-slate-500 mt-0.5">24h − sleep</div>
                </div>
                <div class="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
                    <div class="text-[0.6rem] uppercase tracking-wider text-slate-500">Logged</div>
                    <div class="mt-1 text-lg tabular-nums text-slate-100">{{ $fmt($loggedSec) }}</div>
                    <div class="text-[0.65rem] text-slate-500 mt-0.5">{{ $blocks->count() }} {{ Str::plural('block', $blocks->count()) }}</div>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="rounded-lg border border-emerald-500/30 bg-emerald-500/5 p-3">
                    <div class="text-[0.6rem] uppercase tracking-wider text-emerald-300">Productive</div>
                    <div class="mt-1 text-lg tabular-nums text-emerald-200">{{ $fmt($productiveSec) }}</div>
                </div>
                <div class="rounded-lg border border-rose-500/30 bg-rose-500/5 p-3">
                    <div class="text-[0.6rem] uppercase tracking-wider text-rose-300">Wasted</div>
                    <div class="mt-1 text-lg tabular-nums text-rose-200">{{ $fmt($wastedSec) }}</div>
                </div>
                <div class="rounded-lg border border-yellow-500/30 bg-yellow-500/5 p-3">
                    <div class="text-[0.6rem] uppercase tracking-wider text-yellow-300">Unlogged (awake)</div>
                    <div class="mt-1 text-lg tabular-nums text-yellow-200">{{ $fmt($unloggedSec) }}</div>
                    <div class="text-[0.6rem] text-slate-500 mt-0.5">counts as non-productive</div>
                </div>
                <div class="rounded-lg border {{ $tierBoxBorder }} {{ $tierBoxBg }} p-3">
                    <div class="text-[0.6rem] uppercase tracking-wider {{ $tierBoxLabel }}">Efficiency</div>
                    <div class="mt-1 text-lg tabular-nums {{ $tierBoxValue }}">{{ $efficiencyPct }}%</div>
                </div>
            </div>

            {{-- Segmented bar over the awake window --}}
            <div>
                <div class="flex items-center justify-between text-[0.65rem] uppercase tracking-wider text-slate-500 mb-1.5">
                    <span>Awake-window breakdown</span>
                    <span>{{ $fmt($awakeSec) }} awake</span>
                </div>
                <div class="h-2.5 rounded-full bg-slate-800/80 overflow-hidden flex">
                    <div class="h-full bg-emerald-400" style="width: {{ $prodPct }}%"></div>
                    <div class="h-full bg-rose-400" style="width: {{ $wastedPct }}%"></div>
                    <div class="h-full bg-yellow-400" style="width: {{ $unloggedPct }}%"></div>
                </div>
                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-[0.65rem] uppercase tracking-wider text-slate-500">
                    <span class="inline-flex items-center gap-1.5">
                        <span class="inline-block h-2 w-2 rounded-full bg-emerald-400"></span>
                        Productive {{ $prodPct }}%
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="inline-block h-2 w-2 rounded-full bg-rose-400"></span>
                        Wasted {{ $wastedPct }}%
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="inline-block h-2 w-2 rounded-full bg-yellow-400"></span>
                        Unlogged {{ $unloggedPct }}%
                    </span>
                </div>
                @php $nonProdSec = $wastedSec + $unloggedSec; @endphp
                <p class="mt-2 text-[0.65rem] text-slate-500">
                    <span class="text-rose-300">Wasted</span> +
                    <span class="text-yellow-300">Unlogged</span> = Non-productive
                    <span class="text-slate-200 tabular-nums">{{ $fmt($nonProdSec) }}</span>
                    — both reduce efficiency.
                </p>
            </div>
        </div>
    </section>

    {{-- Block list --}}
    <section class="rounded-xl border border-slate-800/60 bg-slate-900/40 overflow-hidden">
        <header class="flex items-baseline justify-between gap-3 px-5 py-3 border-b border-slate-800/60">
            <h2 class="font-display text-xs uppercase tracking-[0.2em] text-slate-300">Time blocks</h2>
            <span class="text-[0.65rem] uppercase tracking-wider text-slate-500">read-only · admin view</span>
        </header>
        @if ($blocks->isEmpty())
            <div class="px-5 py-12 text-center text-sm text-slate-500">No time blocks logged on this day.</div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-800/60 bg-slate-950/30 text-[0.65rem] uppercase tracking-wider text-slate-400">
                            <th class="text-left px-5 py-2.5 font-semibold">Start</th>
                            <th class="text-left px-3 py-2.5 font-semibold">End</th>
                            <th class="text-right px-3 py-2.5 font-semibold">Duration</th>
                            <th class="text-left px-3 py-2.5 font-semibold">Reason / Activity</th>
                            <th class="text-left px-5 py-2.5 font-semibold">Category</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/30">
                        @foreach ($blocks as $b)
                            @php
                                $isWasted = $b->category === 'wasted';
                                $chipClass = $isWasted
                                    ? 'bg-rose-500/15 text-rose-200 border-rose-500/40'
                                    : 'bg-emerald-500/15 text-emerald-300 border-emerald-500/40';
                                $chipText = $isWasted ? 'Wasted' : 'Productive';
                                $dotClass = $isWasted ? 'bg-rose-400' : 'bg-emerald-400';
                            @endphp
                            <tr class="hover:bg-slate-800/30 transition-colors">
                                <td class="px-5 py-2 text-slate-200 whitespace-nowrap">{{ $b->start_time->format('g:i A') }}</td>
                                <td class="px-3 py-2 text-slate-300 whitespace-nowrap">{{ $b->end_time?->format('g:i A') ?: '—' }}</td>
                                <td class="px-3 py-2 text-right text-slate-200 tabular-nums">{{ $fmt((int) $b->duration_seconds) }}</td>
                                <td class="px-3 py-2 text-slate-300">
                                    <span class="inline-block h-2 w-2 rounded-full {{ $dotClass }} mr-2 align-middle"></span>
                                    {{ $b->reason ?: '—' }}
                                </td>
                                <td class="px-5 py-2">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[0.65rem] uppercase tracking-wider border {{ $chipClass }}">
                                        {{ $chipText }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
