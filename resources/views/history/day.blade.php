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

    {{-- Day's goals — populated client-side from localStorage. Today's
         multi-goal panel persists per-date entries; here we surface them
         read-only for any past date. The whole section hides itself if
         no goals were recorded for this date. --}}
    <section id="day_goals_section" class="chrono-panel rounded-2xl p-6 md:p-8 mb-6 hidden">
        <div class="flex items-baseline justify-between gap-3 mb-4">
            <h2 class="font-display text-sm uppercase tracking-[0.3em] text-slate-300">Day's goals</h2>
            <span class="text-xs text-slate-500" data-day-goals-summary>—</span>
        </div>
        <ul class="space-y-2.5" data-day-goals-list></ul>
        <p class="mt-3 text-[0.65rem] text-slate-500">
            Goals are stored in your browser. They appear here only when the same browser viewed them on that date.
        </p>
    </section>

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
        <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
            <h2 class="font-display text-sm uppercase tracking-[0.3em] text-slate-300">Time blocks</h2>
            <div class="flex flex-wrap items-center gap-3">
                @if (!empty($rows))
                    {{-- Copy this day's blocks as CSV (importable to Sheets/Excel). --}}
                    <button id="day_copy_csv" type="button"
                        title="Copy this day's blocks as CSV to your clipboard"
                        aria-label="Copy this day's blocks as CSV"
                        class="inline-flex items-center gap-1.5 rounded-md border border-slate-700 hover:border-[var(--chrono-orange)]/60 hover:text-[var(--chrono-orange)] px-3 py-1.5 text-xs text-slate-200 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
                            <path d="M7 3.5A1.5 1.5 0 018.5 2h3.879a1.5 1.5 0 011.06.44l3.122 3.12A1.5 1.5 0 0117 6.622V12.5a1.5 1.5 0 01-1.5 1.5h-1v-3.379a3 3 0 00-.879-2.121L10.5 5.379A3 3 0 008.379 4.5H7v-1z"/>
                            <path d="M4.5 6A1.5 1.5 0 003 7.5v9A1.5 1.5 0 004.5 18h7a1.5 1.5 0 001.5-1.5v-5.879a1.5 1.5 0 00-.44-1.06L9.44 6.439A1.5 1.5 0 008.378 6H4.5z"/>
                        </svg>
                        Copy to CSV
                    </button>
                @endif
                <span class="text-xs text-slate-500">Read-only — old logs can't be edited</span>
            </div>
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

    @push('scripts')
        <script>
            (() => {
                // Read multi-goal panel data from localStorage and surface
                // the goals recorded on this date (if any). Falls back to the
                // legacy single-goal v1 key for older entries. Pure read —
                // never mutates anything.
                const date = @json($date->toDateString());
                const v2Key = 'chrono.todayGoals.v2.' + date;
                const v1Key = 'chrono.todayGoal.' + date;
                const section = document.getElementById('day_goals_section');
                const listEl = section?.querySelector('[data-day-goals-list]');
                const summaryEl = section?.querySelector('[data-day-goals-summary]');
                if (!section || !listEl) return;

                const escapeHtml = (str) => String(str ?? '').replace(/[&<>"']/g, (c) => ({
                    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
                }[c]));
                const fmt12 = (hhmm) => {
                    if (!hhmm) return '';
                    const [h, m] = hhmm.split(':').map(Number);
                    const period = h >= 12 ? 'PM' : 'AM';
                    const h12 = h === 0 ? 12 : (h > 12 ? h - 12 : h);
                    return `${h12}:${String(m).padStart(2, '0')} ${period}`;
                };

                let goals = [];
                try {
                    const rawV2 = localStorage.getItem(v2Key);
                    if (rawV2) {
                        const arr = JSON.parse(rawV2);
                        if (Array.isArray(arr)) goals = arr;
                    }
                } catch {}
                if (goals.length === 0) {
                    // Fall back to v1 (legacy single-goal storage).
                    try {
                        const rawV1 = localStorage.getItem(v1Key);
                        if (rawV1) {
                            const obj = JSON.parse(rawV1);
                            if (obj && obj.text) {
                                goals = [{ text: obj.text, done: !!obj.done }];
                            }
                        }
                    } catch {}
                }
                if (goals.length === 0) return;        // section stays hidden

                const total = goals.length;
                const done = goals.filter((g) => g.done).length;
                if (summaryEl) {
                    summaryEl.textContent = `${done}/${total} completed`;
                }

                listEl.innerHTML = goals.map((g, i) => {
                    const text = (g.text || '').trim() || '(no text)';
                    const isDone = !!g.done;
                    const dotClass = isDone ? 'bg-emerald-400' : 'bg-slate-600';
                    const tagClass = isDone ? 'text-emerald-300 border-emerald-500/40 bg-emerald-500/10' : 'text-slate-400 border-slate-700 bg-slate-900/40';
                    const tagText = isDone ? 'Completed' : 'Pending';
                    const window = (isDone && g.completedFrom && g.completedTo)
                        ? `<span class="text-[0.65rem] text-emerald-300/80 ml-2">${escapeHtml(fmt12(g.completedFrom))} – ${escapeHtml(fmt12(g.completedTo))}</span>`
                        : '';
                    return `
                        <li class="rounded-lg border border-slate-800/60 bg-slate-900/40 p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-start gap-2.5 min-w-0">
                                    <span class="inline-block h-2 w-2 mt-1.5 shrink-0 rounded-full ${dotClass}"></span>
                                    <div class="min-w-0">
                                        <div class="text-[0.6rem] uppercase tracking-wider text-slate-500">Goal ${i + 1}</div>
                                        <div class="mt-0.5 text-sm whitespace-pre-line ${isDone ? 'text-slate-300 line-through opacity-70' : 'text-slate-100'}">${escapeHtml(text)}</div>
                                    </div>
                                </div>
                                <div class="flex flex-col items-end gap-1 shrink-0">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[0.6rem] uppercase tracking-wider border ${tagClass}">${tagText}</span>
                                    ${window}
                                </div>
                            </div>
                        </li>
                    `;
                }).join('');

                section.classList.remove('hidden');
            })();
        </script>

        <script>
            // Copy this day's blocks as CSV. Rows are taken straight from the
            // server-rendered $rows so the clipboard output mirrors exactly
            // what the page shows (already-formatted Start/End strings,
            // human-readable duration, and the same category labels).
            (() => {
                const btn = document.getElementById('day_copy_csv');
                if (!btn) return;

                const rows = @json($rows ?? []);
                const dateLabel = @json($dateLabel ?? '');

                const csvEscape = (val) => {
                    const s = String(val ?? '');
                    return /[",\n\r]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
                };

                const buildCsv = () => {
                    const header = ['Start', 'End', 'Duration', 'Reason', 'Category'];
                    const lines = [header.join(',')];
                    for (const r of rows) {
                        const cat = r.category === 'wasted' ? 'Wasted'
                            : (r.category === 'neutral' ? 'Neutral' : 'Productive');
                        lines.push([
                            r.start ?? '',
                            r.end ?? '',
                            r.durationLabel ?? '',
                            r.reason ?? '',
                            cat,
                        ].map(csvEscape).join(','));
                    }
                    return lines.join('\n');
                };

                const copy = async (text) => {
                    try {
                        if (navigator.clipboard && window.isSecureContext !== false) {
                            await navigator.clipboard.writeText(text);
                            return true;
                        }
                    } catch (e) { /* fall through */ }
                    try {
                        const ta = document.createElement('textarea');
                        ta.value = text;
                        ta.setAttribute('readonly', '');
                        ta.style.position = 'fixed';
                        ta.style.top = '0';
                        ta.style.left = '0';
                        ta.style.opacity = '0';
                        document.body.appendChild(ta);
                        ta.select();
                        const ok = document.execCommand('copy');
                        document.body.removeChild(ta);
                        return ok;
                    } catch (e) {
                        return false;
                    }
                };

                btn.addEventListener('click', async () => {
                    if (!rows || rows.length === 0) {
                        window.showToast?.('No blocks to copy for this day.', { tone: 'warn' });
                        return;
                    }
                    const ok = await copy(buildCsv());
                    if (ok) {
                        window.showToast?.(
                            `Copied ${rows.length} ${rows.length === 1 ? 'row' : 'rows'} from ${dateLabel} as CSV.`,
                            { tone: 'success' }
                        );
                    } else {
                        window.showToast?.('Copy failed — your browser blocked clipboard access.', { tone: 'error' });
                    }
                });
            })();
        </script>
    @endpush
@endsection
