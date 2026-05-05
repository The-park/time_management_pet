@extends('layouts.admin')

@section('page_title', 'Dashboard')

@section('content')
    <div class="mb-8">
        <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Overview</div>
        <h1 class="font-display text-2xl md:text-3xl tracking-[0.2em] uppercase mt-1 text-slate-100">Admin Dashboard</h1>
        <p class="text-sm text-slate-400 mt-1.5">Operational health at a glance · {{ now()->format('l, F j, Y') }}</p>
    </div>

    {{-- ─── Metric tiles (grouped) ──────────────────────────────────────── --}}
    @php
        $tileGroups = [
            'Users' => [
                ['Total', $metrics['users_total'], 'text-slate-100'],
                ['Active', $metrics['users_active'], 'text-emerald-300'],
                ['Suspended', $metrics['users_suspended'], 'text-amber-300'],
                ['Deleted', $metrics['users_deleted'], 'text-rose-300'],
                ['Unverified email', $metrics['users_unverified'], 'text-amber-300'],
                ['New (7d)', $metrics['users_signups_7d'], 'text-slate-100'],
            ],
            'Activity' => [
                ['Time blocks', $metrics['time_blocks'], 'text-slate-100'],
                ['Daily goals', $metrics['daily_goals'], 'text-slate-100'],
                ['Countdowns', $metrics['countdowns'], 'text-slate-100'],
                ['New (30d)', $metrics['users_signups_30d'], 'text-slate-100'],
                ['Audit entries', $metrics['audit_entries'], 'text-slate-100'],
                ['Disposable domains', $metrics['disposable_domains'], 'text-slate-100'],
            ],
        ];
    @endphp
    <div class="space-y-6 mb-8">
        @foreach ($tileGroups as $groupName => $tiles)
            <div>
                <div class="text-[0.65rem] uppercase tracking-[0.2em] text-slate-500 mb-2">{{ $groupName }}</div>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                    @foreach ($tiles as [$label, $value, $color])
                        <div class="chrono-lift rounded-xl border border-slate-800/60 bg-slate-900/40 p-3.5">
                            <div class="text-[0.65rem] uppercase tracking-wider text-slate-500">{{ $label }}</div>
                            <div class="mt-1 text-2xl font-semibold tabular-nums {{ $color }}">{{ number_format($value) }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    {{-- ─── 30-day sparklines ──────────────────────────────────────────── --}}
    @php
        $renderSparkline = function (array $points, string $colorClass) {
            $max = max(1, max(array_column($points, 'count')));
            $svg = '<svg viewBox="0 0 90 30" preserveAspectRatio="none" class="w-full h-14 mt-3 ' . $colorClass . '">';
            foreach ($points as $i => $p) {
                $h = max(0.6, ($p['count'] / $max) * 28);
                $y = 30 - $h;
                $x = $i * 3;
                $svg .= '<rect x="' . $x . '" y="' . number_format($y, 2, '.', '') . '" width="2" height="' . number_format($h, 2, '.', '') . '" fill="currentColor" rx="0.3"><title>' . e($p['label']) . ': ' . $p['count'] . '</title></rect>';
            }
            $svg .= '</svg>';
            return $svg;
        };
        $charts = [
            ['label' => 'Signups', 'series' => $series['signups'], 'color' => 'text-rose-400'],
            ['label' => 'Admin actions', 'series' => $series['audits'], 'color' => 'text-amber-300'],
            ['label' => 'Time blocks', 'series' => $series['time_blocks'], 'color' => 'text-emerald-400'],
        ];
    @endphp
    <div class="mb-8">
        <div class="text-[0.65rem] uppercase tracking-[0.2em] text-slate-500 mb-2">Last 30 days</div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            @foreach ($charts as $cfg)
                <div class="chrono-lift rounded-xl border border-slate-800/60 bg-slate-900/40 p-4">
                    <div class="flex items-baseline justify-between gap-2">
                        <div class="text-xs uppercase tracking-[0.15em] text-slate-400">{{ $cfg['label'] }}</div>
                        <div class="text-xl font-semibold tabular-nums text-slate-100">
                            {{ number_format(array_sum(array_column($cfg['series'], 'count'))) }}
                        </div>
                    </div>
                    {!! $renderSparkline($cfg['series'], $cfg['color']) !!}
                    <div class="flex justify-between text-[0.6rem] text-slate-600 mt-1">
                        <span>{{ $cfg['series'][0]['label'] ?? '' }}</span>
                        <span>{{ $cfg['series'][count($cfg['series']) - 1]['label'] ?? '' }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ─── Recent feeds ──────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <section class="rounded-xl border border-slate-800/60 bg-slate-900/40 overflow-hidden">
            <header class="flex items-baseline justify-between gap-2 px-5 py-4 border-b border-slate-800/60">
                <h2 class="font-display text-xs uppercase tracking-[0.2em] text-slate-300">Recent signups</h2>
                <a href="{{ route('admin.users.index') }}" class="text-[0.65rem] uppercase tracking-[0.15em] text-rose-300 hover:text-rose-200">All users →</a>
            </header>
            @if ($recentSignups->isEmpty())
                <div class="px-5 py-8 text-center text-sm text-slate-500">No signups yet.</div>
            @else
                <ul class="divide-y divide-slate-800/60">
                    @foreach ($recentSignups as $u)
                        <li>
                            <a href="{{ route('admin.users.show', $u->id) }}"
                                class="block px-5 py-3 hover:bg-slate-800/40 transition-colors">
                                <div class="flex items-baseline justify-between gap-3">
                                    <div class="text-sm text-slate-100 truncate">{{ $u->name }}</div>
                                    <div class="text-xs text-slate-500 whitespace-nowrap">{{ $u->created_at?->diffForHumans() }}</div>
                                </div>
                                <div class="text-xs text-slate-400 truncate">{{ $u->email }}</div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="rounded-xl border border-slate-800/60 bg-slate-900/40 overflow-hidden">
            <header class="flex items-baseline justify-between gap-2 px-5 py-4 border-b border-slate-800/60">
                <h2 class="font-display text-xs uppercase tracking-[0.2em] text-slate-300">Recent admin activity</h2>
                <a href="{{ route('admin.audit.index') }}" class="text-[0.65rem] uppercase tracking-[0.15em] text-rose-300 hover:text-rose-200">Full log →</a>
            </header>
            @if ($recentAudits->isEmpty())
                <div class="px-5 py-8 text-center text-sm text-slate-500">No audit entries yet.</div>
            @else
                <ul class="divide-y divide-slate-800/60">
                    @foreach ($recentAudits as $a)
                        <li class="px-5 py-3">
                            <div class="flex items-baseline justify-between gap-3">
                                <div class="flex items-baseline gap-2 min-w-0">
                                    <span class="text-sm text-slate-200 truncate">{{ $a->admin?->name ?? '—' }}</span>
                                    <code class="rounded bg-slate-800/60 px-1.5 py-0.5 text-[0.65rem] text-rose-300 truncate">{{ $a->action }}</code>
                                </div>
                                <div class="text-xs text-slate-500 whitespace-nowrap">{{ $a->viewed_at?->diffForHumans() }}</div>
                            </div>
                            @if ($a->viewedUser)
                                <div class="text-xs text-slate-400 truncate mt-0.5">
                                    →
                                    <a href="{{ route('admin.users.show', $a->viewedUser->id) }}" class="text-rose-300 hover:text-rose-200">{{ $a->viewedUser->email }}</a>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
@endsection
