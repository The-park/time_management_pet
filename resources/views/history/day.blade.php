@extends('layouts.app')

@section('page_title', 'Day · '.$dateLabel)

@section('content')
    @php
        $fmt = function (int $ms) {
            $totalMin = max(0, (int) round($ms / 60000));
            if ($totalMin === 0) return '0m';
            if ($totalMin < 60) return $totalMin.'m';
            $h = intdiv($totalMin, 60);
            $m = $totalMin % 60;
            return $m === 0 ? $h.'h' : $h.'h '.$m.'m';
        };
        $effPct = $efficiencyPct;
        // Static class strings so Tailwind's JIT scanner picks them up.
        if ($effPct >= 70) {
            $tierBigText = 'text-emerald-300';
            $tierBoxBorder = 'border-emerald-500/30';
            $tierBoxBg = 'bg-emerald-500/5';
            $tierBoxLabel = 'text-emerald-300';
            $tierBoxValue = 'text-emerald-200';
        } elseif ($effPct >= 40) {
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
        // Compute % widths for the segmented bar (productive | wasted | unlogged).
        $totalForBar = max(1, $productiveMs + $wastedMs + $unloggedMs);
        $prodPct = round(($productiveMs / $totalForBar) * 100);
        $wastedBarPct = round(($wastedMs / $totalForBar) * 100);
        $unloggedBarPct = max(0, 100 - $prodPct - $wastedBarPct);
    @endphp

    <div class="relative overflow-hidden rounded-2xl border border-slate-800/60 bg-[radial-gradient(circle_at_top,_rgba(0,224,255,0.15),_transparent_45%)] p-8 mb-6">
        <div class="absolute -right-24 -top-24 h-56 w-56 rounded-full bg-[radial-gradient(circle,_rgba(255,107,26,0.35),_transparent_70%)] blur-2xl"></div>
        <div class="relative flex flex-col md:flex-row md:items-start md:justify-between gap-4">
            <div>
                <a href="{{ route('history.index') }}" class="text-xs uppercase tracking-[0.2em] text-slate-400 hover:text-slate-100">← History</a>
                <h1 class="mt-2 font-display text-3xl tracking-[0.3em] uppercase">{{ $dateLabel }}</h1>
                <p class="text-slate-300 text-sm mt-2">
                    @if ($isFuture)
                        Future date — no logs yet.
                    @elseif ($isCurrentDay)
                        Today's report (still in progress). Read-only snapshot.
                    @else
                        Read-only day report. Old logs can't be edited.
                    @endif
                </p>
            </div>
            <div class="text-right">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Efficiency</div>
                <div class="font-digital text-4xl {{ $tierBigText }}">{{ $effPct }}%</div>
                <div class="text-[0.65rem] text-slate-500 mt-0.5"
                    title="Wasted and unlogged time both reduce efficiency. Only productive logged blocks build it up.">
                    productive ÷ (prod + wasted + unlogged)
                </div>
            </div>
        </div>
    </div>

    {{-- Stat tiles --}}
    <section class="chrono-panel rounded-2xl p-6 md:p-8 mb-6">
        <h2 class="font-display text-sm uppercase tracking-[0.3em] text-slate-300 mb-4">Time breakdown</h2>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
            <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3">
                <div class="text-[0.6rem] uppercase tracking-wider text-slate-500">Total in day</div>
                <div class="mt-1 font-digital text-xl text-slate-100">{{ $fmt($totalDayMs) }}</div>
                <div class="text-[0.65rem] text-slate-500 mt-0.5">24h calendar</div>
            </div>
            <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3">
                <div class="text-[0.6rem] uppercase tracking-wider text-slate-500">Sleep</div>
                <div class="mt-1 font-digital text-xl text-slate-300">{{ $fmt($sleepMs) }}</div>
                <div class="text-[0.65rem] text-slate-500 mt-0.5">{{ $sleepWindowLabel }}</div>
            </div>
            <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3">
                <div class="text-[0.6rem] uppercase tracking-wider text-slate-500">Awake (waking hours)</div>
                <div class="mt-1 font-digital text-xl text-slate-100">{{ $fmt($awakeMs) }}</div>
                <div class="text-[0.65rem] text-slate-500 mt-0.5">24h − sleep</div>
            </div>
            <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3">
                <div class="text-[0.6rem] uppercase tracking-wider text-slate-500">Logged</div>
                <div class="mt-1 font-digital text-xl text-slate-100">{{ $fmt($loggedMs) }}</div>
                <div class="text-[0.65rem] text-slate-500 mt-0.5">{{ count($rows) }} {{ Str::plural('block', count($rows)) }}</div>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/5 p-3">
                <div class="text-[0.6rem] uppercase tracking-wider text-emerald-300">Productive</div>
                <div class="mt-1 font-digital text-xl text-emerald-200">{{ $fmt($productiveMs) }}</div>
            </div>
            <div class="rounded-xl border border-rose-500/30 bg-rose-500/5 p-3">
                <div class="text-[0.6rem] uppercase tracking-wider text-rose-300">Wasted</div>
                <div class="mt-1 font-digital text-xl text-rose-200">{{ $fmt($wastedMs) }}</div>
            </div>
            <div class="rounded-xl border border-yellow-500/30 bg-yellow-500/5 p-3">
                <div class="text-[0.6rem] uppercase tracking-wider text-yellow-300">Unlogged (awake)</div>
                <div class="mt-1 font-digital text-xl text-yellow-200">{{ $fmt($unloggedMs) }}</div>
                <div class="text-[0.6rem] text-slate-500 mt-0.5">counts as non-productive</div>
            </div>
            <div class="rounded-xl border {{ $tierBoxBorder }} {{ $tierBoxBg }} p-3">
                <div class="text-[0.6rem] uppercase tracking-wider {{ $tierBoxLabel }}">Efficiency</div>
                <div class="mt-1 font-digital text-xl {{ $tierBoxValue }}">{{ $effPct }}%</div>
                <div class="text-[0.6rem] text-slate-500 mt-0.5">prod ÷ (prod + wasted + unlogged)</div>
            </div>
        </div>

        {{-- Segmented bar over the awake window --}}
        <div class="mt-6">
            <div class="flex items-center justify-between text-[0.65rem] uppercase tracking-wider text-slate-500 mb-1.5">
                <span>Awake-window breakdown</span>
                <span>{{ $fmt($awakeMs) }} awake</span>
            </div>
            <div class="h-2.5 rounded-full bg-slate-800/80 overflow-hidden flex">
                <div class="h-full bg-emerald-400 transition-[width]" style="width: {{ $prodPct }}%"></div>
                <div class="h-full bg-rose-400 transition-[width]" style="width: {{ $wastedBarPct }}%"></div>
                <div class="h-full bg-yellow-400 transition-[width]" style="width: {{ $unloggedBarPct }}%"></div>
            </div>
            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-[0.65rem] uppercase tracking-wider text-slate-500">
                <span class="inline-flex items-center gap-1.5">
                    <span class="inline-block h-2 w-2 rounded-full bg-emerald-400"></span>
                    Productive {{ $prodPct }}%
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="inline-block h-2 w-2 rounded-full bg-rose-400"></span>
                    Wasted {{ $wastedBarPct }}%
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="inline-block h-2 w-2 rounded-full bg-yellow-400"></span>
                    Unlogged {{ $unloggedBarPct }}%
                </span>
            </div>
            @php $nonProdMs = $wastedMs + $unloggedMs; @endphp
            <p class="mt-2 text-[0.65rem] text-slate-500">
                <span class="text-rose-300">Wasted</span> +
                <span class="text-yellow-300">Unlogged</span> = Non-productive total
                <span class="font-digital text-slate-200">{{ $fmt($nonProdMs) }}</span>
                — both reduce efficiency.
            </p>
        </div>
    </section>

    {{-- Block list (read-only) --}}
    <section class="chrono-panel rounded-2xl p-6 md:p-8">
        <div class="flex items-baseline justify-between gap-3 mb-3">
            <h2 class="font-display text-sm uppercase tracking-[0.3em] text-slate-300">Time blocks</h2>
            <span class="text-xs text-slate-500">Read-only — old logs can't be edited</span>
        </div>

        @if (empty($rows))
            <p class="text-sm text-slate-500">No time blocks logged on this day.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wider text-slate-400">
                            <th class="py-2 pr-4">Start</th>
                            <th class="py-2 pr-4">End</th>
                            <th class="py-2 pr-4">Duration</th>
                            <th class="py-2 pr-4">Reason / Activity</th>
                            <th class="py-2">Category</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php
                                $isWasted = $row['category'] === 'wasted';
                                $chipClass = $isWasted
                                    ? 'bg-rose-500/20 text-rose-200 border-rose-500/50'
                                    : 'bg-emerald-500/15 text-emerald-300 border-emerald-500/40';
                                $chipText = $isWasted ? 'Wasted' : 'Productive';
                                $dotClass = $isWasted ? 'bg-rose-400' : 'bg-emerald-400';
                            @endphp
                            <tr class="border-t border-slate-800/60">
                                <td class="py-3 pr-4 text-slate-100">{{ $row['start'] }}</td>
                                <td class="py-3 pr-4 text-slate-100">{{ $row['end'] }}</td>
                                <td class="py-3 pr-4 text-slate-100">{{ $row['durationLabel'] }}</td>
                                <td class="py-3 pr-4 text-slate-300">
                                    <span class="inline-block h-2 w-2 rounded-full {{ $dotClass }} mr-2 align-middle"></span>
                                    {{ $row['reason'] ?: '—' }}
                                </td>
                                <td class="py-3">
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
