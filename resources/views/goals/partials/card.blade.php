@php
    $goal = $s['goal'];
    $result = $s['result'];
    $tier = $result['tier'];
    $details = $result['details'];
    $remaining = $details['days_remaining'] ?? null;
    // Full class strings (Tailwind JIT cannot resolve "text-{{ $x }}-300";
    // every variant must appear literally somewhere it scans).
    $statusBadge = match ($goal->status) {
        'completed' => ['Completed', 'text-emerald-300'],
        'abandoned' => ['Abandoned', 'text-slate-300'],
        'missed' => ['Missed', 'text-rose-300'],
        default => ['Active', 'text-sky-300'],
    };
    $hoverBorder = match ($tier['key']) {
        'high' => 'hover:border-emerald-500/40',
        'good' => 'hover:border-sky-500/40',
        'caution' => 'hover:border-amber-500/40',
        'warning' => 'hover:border-orange-500/40',
        'critical' => 'hover:border-rose-500/40',
        default => 'hover:border-slate-500/40',
    };
    $consistencyPct = (isset($details['days_with_logs'], $details['days_passed']) && $details['days_passed'] > 0)
        ? round(($details['days_with_logs'] / $details['days_passed']) * 100)
        : null;
@endphp

<a href="{{ route('goals.show', $goal) }}"
    class="chrono-panel block rounded-2xl p-5 {{ $hoverBorder }} transition-colors">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <div class="flex items-center gap-2 text-[0.65rem] uppercase tracking-wider text-slate-500">
                <span>{{ $goal->category }}</span>
                <span>·</span>
                <span class="{{ $statusBadge[1] }}">{{ $statusBadge[0] }}</span>
            </div>
            <h3 class="mt-1 truncate text-base text-slate-100">{{ $goal->title }}</h3>
            <p class="mt-1 text-xs text-slate-400">
                Target: {{ $goal->target_date->format('M j, Y') }}
                @if ($goal->original_target_date && ! $goal->original_target_date->isSameDay($goal->target_date))
                    <span class="text-amber-300">(extended {{ $goal->extension_count }}×)</span>
                @endif
            </p>
        </div>
        <div class="text-right">
            <div class="font-digital text-3xl chrono-glow-blue" style="color: {{ $tier['hex'] }}">
                {{ rtrim(rtrim(number_format($result['percent'], 1), '0'), '.') }}%
            </div>
            <div class="text-[0.65rem] uppercase tracking-wider mt-0.5" style="color: {{ $tier['hex'] }}">
                {{ $tier['label'] }}
            </div>
        </div>
    </div>

    <div class="mt-4 h-2 rounded-full bg-slate-800/80 overflow-hidden">
        <div class="h-full transition-[width] duration-500"
            style="width: {{ max(2, min(100, $result['percent'])) }}%; background-color: {{ $tier['hex'] }}"></div>
    </div>

    <div class="mt-3 grid grid-cols-3 gap-2 text-xs">
        <div class="rounded-lg border border-slate-800/60 bg-slate-900/40 p-2">
            <div class="text-[0.6rem] uppercase tracking-wider text-slate-500">Remaining</div>
            <div class="text-slate-100 mt-0.5">
                @if ($remaining === null)
                    —
                @elseif ($remaining === 0)
                    <span class="text-rose-300">today</span>
                @else
                    {{ $remaining }} {{ Str::plural('day', $remaining) }}
                @endif
            </div>
        </div>
        <div class="rounded-lg border border-slate-800/60 bg-slate-900/40 p-2">
            <div class="text-[0.6rem] uppercase tracking-wider text-slate-500">Logged</div>
            <div class="text-slate-100 mt-0.5">{{ $details['hours_done'] ?? 0 }}h</div>
        </div>
        <div class="rounded-lg border border-slate-800/60 bg-slate-900/40 p-2">
            <div class="text-[0.6rem] uppercase tracking-wider text-slate-500">Consistency</div>
            <div class="text-slate-100 mt-0.5">
                {{ $consistencyPct === null ? '—' : $consistencyPct.'%' }}
            </div>
        </div>
    </div>

    @if ($s['alert'])
        <div class="mt-3 rounded-lg border {{ $s['alert'] === 'critical' ? 'border-rose-500/40 bg-rose-900/20 text-rose-200' : 'border-amber-500/40 bg-amber-900/20 text-amber-200' }} p-2 text-xs">
            {{ $s['narrative'] }}
        </div>
    @endif
</a>
