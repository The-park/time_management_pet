@php
    /** @var \App\Services\GoalProbabilityService $probabilityService */
    $probabilityService = app(\App\Services\GoalProbabilityService::class);
    $activeGoals = \App\Models\Goal::query()
        ->where('status', 'active')
        ->orderBy('target_date')
        ->get();

    $summaries = [];
    foreach ($activeGoals as $g) {
        $r = $probabilityService->compute($g);
        $probabilityService->persist($g, (float) $r['percent']);
        $summaries[] = [
            'goal' => $g,
            'result' => $r,
            'alert' => $probabilityService->alertLevel($g, $r),
            'narrative' => $probabilityService->narrative($g, $r),
        ];
    }

    $alerts = array_values(array_filter($summaries, fn ($s) => $s['alert'] !== null));
@endphp

@if (! empty($alerts))
    <div class="mb-6 space-y-3">
        @foreach ($alerts as $a)
            @php
                $tier = $a['result']['tier'];
                $isCritical = $a['alert'] === 'critical';
                $remaining = $a['result']['details']['days_remaining'] ?? null;
            @endphp
            <a href="{{ route('goals.show', $a['goal']) }}"
                class="block rounded-2xl border {{ $isCritical ? 'border-rose-500/40 bg-rose-900/20 hover:bg-rose-900/30' : 'border-amber-500/40 bg-amber-900/20 hover:bg-amber-900/30' }} p-4 transition-colors">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <div class="flex items-start gap-3">
                        <div class="font-display text-2xl {{ $isCritical ? 'text-rose-300' : 'text-amber-300' }}">⚠</div>
                        <div>
                            <div class="text-[0.6rem] uppercase tracking-wider {{ $isCritical ? 'text-rose-300' : 'text-amber-300' }}">
                                @if ($isCritical) Time is running out @else Pace slipping @endif · {{ $a['goal']->category }}
                            </div>
                            <div class="mt-0.5 font-semibold text-slate-100">{{ $a['goal']->title }}</div>
                            <div class="mt-1 text-xs text-slate-300">{{ $a['narrative'] }}</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 md:gap-6">
                        <div class="text-right">
                            <div class="text-[0.6rem] uppercase tracking-wider text-slate-400">Probability</div>
                            <div class="font-digital text-2xl" style="color: {{ $tier['hex'] }}">
                                {{ rtrim(rtrim(number_format($a['result']['percent'], 1), '0'), '.') }}%
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-[0.6rem] uppercase tracking-wider text-slate-400">Remaining</div>
                            <div class="font-digital text-2xl text-slate-100">
                                @if ($remaining === 0)
                                    <span class="text-rose-300">today</span>
                                @else
                                    {{ $remaining }}d
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        @endforeach
    </div>
@endif

@if (! empty($summaries))
    <section class="chrono-panel rounded-2xl p-6 md:p-8 mb-10">
        <div class="flex items-baseline justify-between gap-3 mb-1">
            <h2 class="font-display text-sm uppercase tracking-[0.3em] text-slate-300">Active goals</h2>
            <a href="{{ route('goals.index') }}" class="text-xs uppercase tracking-[0.2em] text-[var(--chrono-blue)] hover:underline">
                Manage →
            </a>
        </div>
        <p class="text-xs text-slate-500 mb-5">
            Hours you log on this dashboard automatically count toward each goal whose window covers the day.
        </p>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            @foreach ($summaries as $s)
                @include('goals.partials.card', ['s' => $s])
            @endforeach
        </div>
    </section>
@else
    <section class="rounded-2xl border border-dashed border-slate-700/60 bg-slate-900/20 p-6 mb-10">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div>
                <h2 class="font-display text-sm uppercase tracking-[0.3em] text-slate-300">No active goals</h2>
                <p class="text-xs text-slate-400 mt-1">
                    Set a long-range deadline and we'll calculate the probability of hitting it from your daily logs.
                </p>
            </div>
            <a href="{{ route('goals.create') }}"
                class="rounded-lg bg-[var(--chrono-blue)] text-slate-950 font-semibold px-4 py-2 text-sm">
                + Create goal
            </a>
        </div>
    </section>
@endif
