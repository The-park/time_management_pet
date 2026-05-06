@extends('layouts.app')

@section('page_title', 'Goals')

@section('content')
    @php
        $active = $summaries->filter(fn ($s) => $s['goal']->status === 'active')->values();
        $other = $summaries->filter(fn ($s) => $s['goal']->status !== 'active')->values();
        $criticalCount = $summaries->filter(fn ($s) => $s['alert'] === 'critical')->count();
        $warningCount = $summaries->filter(fn ($s) => $s['alert'] === 'warning')->count();
    @endphp

    <div class="relative overflow-hidden rounded-2xl border border-slate-800/60 bg-[radial-gradient(circle_at_top,_rgba(0,224,255,0.15),_transparent_45%)] p-8 mb-8">
        <div class="absolute -right-24 -top-24 h-56 w-56 rounded-full bg-[radial-gradient(circle,_rgba(255,107,26,0.35),_transparent_70%)] blur-2xl"></div>
        <div class="relative flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="font-display text-3xl tracking-[0.3em] uppercase">Goals</h1>
                <p class="text-slate-300 text-sm mt-2">Long-range targets with live probability of success.</p>
            </div>
            <a href="{{ route('goals.create') }}"
                class="inline-flex items-center justify-center rounded-lg bg-[var(--chrono-blue)] text-slate-950 font-semibold px-4 py-2">
                + New goal
            </a>
        </div>
    </div>

    @if ($criticalCount > 0 || $warningCount > 0)
        <div class="mb-6 rounded-2xl border {{ $criticalCount > 0 ? 'border-rose-500/40 bg-rose-900/20' : 'border-amber-500/40 bg-amber-900/20' }} p-4">
            <div class="flex items-start gap-3">
                <div class="font-display text-2xl {{ $criticalCount > 0 ? 'text-rose-300' : 'text-amber-300' }}">⚠</div>
                <div class="text-sm">
                    <div class="font-semibold {{ $criticalCount > 0 ? 'text-rose-200' : 'text-amber-200' }}">
                        Time is running out on {{ $criticalCount + $warningCount }} {{ Str::plural('goal', $criticalCount + $warningCount) }}.
                    </div>
                    <div class="mt-1 text-slate-300">
                        @if ($criticalCount > 0)
                            <span class="text-rose-300">{{ $criticalCount }} critical</span>{{ $warningCount > 0 ? ' · ' : '' }}
                        @endif
                        @if ($warningCount > 0)
                            <span class="text-amber-300">{{ $warningCount }} warning</span>
                        @endif
                        — review them and either pick up the pace, extend with a reason, or trim scope.
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="space-y-6">
        <section class="space-y-4">
            <h2 class="font-display text-sm uppercase tracking-[0.3em] text-slate-300">Active</h2>

            @if ($active->isEmpty())
                <div class="chrono-panel rounded-2xl p-8 text-center">
                    <p class="text-slate-400 text-sm">No active goals yet.</p>
                    <a href="{{ route('goals.create') }}"
                        class="mt-4 inline-flex items-center justify-center rounded-lg bg-[var(--chrono-blue)] text-slate-950 font-semibold px-4 py-2">
                        Create your first goal
                    </a>
                </div>
            @else
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    @foreach ($active as $s)
                        @include('goals.partials.card', ['s' => $s])
                    @endforeach
                </div>
            @endif
        </section>

        @if ($other->isNotEmpty())
            <section class="space-y-4">
                <h2 class="font-display text-sm uppercase tracking-[0.3em] text-slate-300">Archive</h2>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    @foreach ($other as $s)
                        @include('goals.partials.card', ['s' => $s])
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
