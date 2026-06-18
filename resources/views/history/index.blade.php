@extends('layouts.app')

@section('page_title', 'History')

@section('content')
    @php
        $user = auth()->user();
        $timezone = $user?->timezone ?? 'UTC';
        $signupAt = $user?->created_at?->copy()->setTimezone($timezone);
        $signupTimestamp = $signupAt?->toIso8601String();
        $signupDateLabel = $signupAt?->format('M j, Y');
        $endTime = $user?->end_of_day_time ? substr($user->end_of_day_time, 0, 5) : '22:00';
        $wakeTime = $user?->wake_up_time ? substr($user->wake_up_time, 0, 5) : '07:00';
    @endphp

    <div class="relative overflow-hidden rounded-2xl border border-slate-800/60 bg-[radial-gradient(circle_at_top,_rgba(0,224,255,0.15),_transparent_45%)] p-8 mb-8">
        <div class="absolute -right-24 -top-24 h-56 w-56 rounded-full bg-[radial-gradient(circle,_rgba(255,107,26,0.35),_transparent_70%)] blur-2xl"></div>
        <div class="relative">
            <h1 class="font-display text-3xl tracking-[0.3em] uppercase">History</h1>
            <p class="text-slate-300 text-sm mt-2">Browse your time month by month, drill into weeks and days.</p>
        </div>
    </div>

    <div class="space-y-6">
        <section class="chrono-panel rounded-2xl p-6 md:p-8" aria-labelledby="history_search_heading">
            <div class="flex flex-wrap items-baseline justify-between gap-3 mb-4">
                <h2 id="history_search_heading" class="font-display text-sm uppercase tracking-[0.3em] text-slate-300">
                    Search history
                </h2>
                <button type="button" data-search-clear
                    class="hidden text-xs uppercase tracking-[0.2em] text-[var(--chrono-blue)] hover:text-cyan-200">
                    Clear
                </button>
            </div>

            <div class="relative mb-4">
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-500" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                        <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd"/>
                    </svg>
                </span>
                <input type="search" data-search-q autocomplete="off" spellcheck="false"
                    placeholder="Search activities, reasons, keywords…"
                    aria-label="Search activities"
                    class="w-full rounded-lg border border-slate-700/60 bg-slate-900/60 pl-9 pr-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:border-[var(--chrono-blue)]/60 focus:outline-none focus:ring-1 focus:ring-[var(--chrono-blue)]/40"/>
            </div>

            <div class="flex flex-wrap items-center gap-2 mb-4" role="group" aria-label="Filter by category">
                @php
                    $catChips = [
                        ['all', 'All'],
                        ['productive', 'Productive'],
                        ['wasted', 'Wasted'],
                        ['neutral', 'Neutral'],
                    ];
                @endphp
                @foreach ($catChips as [$val, $label])
                    <button type="button" data-search-cat="{{ $val }}"
                        class="rounded-full border px-3 py-1 text-xs uppercase tracking-[0.2em] transition-colors border-slate-700/60 bg-slate-900/40 text-slate-300 hover:border-slate-500 hover:text-slate-100">
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <label class="flex items-center gap-2 text-xs uppercase tracking-[0.2em] text-slate-400">
                    <span class="w-10">From</span>
                    <input type="date" data-search-from
                        class="flex-1 rounded-md border border-slate-700/60 bg-slate-900/60 px-2 py-1.5 text-sm text-slate-100 focus:border-[var(--chrono-blue)]/60 focus:outline-none"/>
                </label>
                <label class="flex items-center gap-2 text-xs uppercase tracking-[0.2em] text-slate-400">
                    <span class="w-10">To</span>
                    <input type="date" data-search-to
                        class="flex-1 rounded-md border border-slate-700/60 bg-slate-900/60 px-2 py-1.5 text-sm text-slate-100 focus:border-[var(--chrono-blue)]/60 focus:outline-none"/>
                </label>
            </div>
        </section>

        <section class="chrono-panel rounded-2xl p-6 md:p-8">
            <div class="flex flex-wrap items-baseline justify-between gap-3 mb-5">
                <div class="flex items-baseline gap-3">
                    <button type="button" data-history-back
                        class="hidden inline-flex items-center gap-1 rounded-md border border-slate-700/60 px-2 py-1 text-xs uppercase tracking-[0.2em] text-slate-300 hover:border-slate-500 hover:text-slate-100">
                        ← Back
                    </button>
                    <h2 class="font-display text-sm uppercase tracking-[0.3em] text-slate-300" data-history-title>
                        Months
                    </h2>
                </div>
                <div class="flex flex-wrap items-center gap-2" data-history-filters></div>
            </div>

            <div data-history-content>
                <p class="text-slate-500 text-sm">Loading history…</p>
            </div>
        </section>

    </div>

    @push('scripts')
        <script>
            window.ChronoHistoryConfig = {
                timezone: @json($timezone),
                signupTimestamp: @json($signupTimestamp),
                signupDateLabel: @json($signupDateLabel),
                wakeTime: @json($wakeTime),
                endTime: @json($endTime),
                // URL template for the read-only day-detail page; the
                // month-view day rows link here (replacing the previous
                // inline-expand <details> behaviour). __DATE__ is replaced
                // client-side per row.
                dayDetailUrl: @json(route('history.day', ['date' => '__DATE__'])),
            };
        </script>

        <script>
            (() => {
                const BLOCKS_KEY = 'chrono.timeBlocks.v1';
                const cfg = window.ChronoHistoryConfig || {};
                const filtersEl = document.querySelector('[data-history-filters]');
                const contentEl = document.querySelector('[data-history-content]');
                const titleEl = document.querySelector('[data-history-title]');
                const backBtn = document.querySelector('[data-history-back]');
                if (!contentEl) return;

                const pad = (n) => String(n).padStart(2, '0');
                const escapeHtml = (str) => String(str ?? '').replace(/[&<>"']/g, (c) => ({
                    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
                }[c]));
                const localDateString = (d) =>
                    `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
                const formatTime12 = (hhmm) => {
                    if (!hhmm) return '';
                    const [h, m] = hhmm.split(':').map(Number);
                    const period = h >= 12 ? 'PM' : 'AM';
                    const hour12 = h === 0 ? 12 : (h > 12 ? h - 12 : h);
                    return `${hour12}:${pad(m)} ${period}`;
                };
                const formatHours = (ms) => {
                    if (ms <= 0) return '0h';
                    const hours = ms / 3600000;
                    if (hours < 1) return `${Math.round(ms / 60000)}m`;
                    if (hours < 10) return `${hours.toFixed(1)}h`;
                    return `${Math.round(hours)}h`;
                };
                const formatDuration = (ms) => {
                    const totalMin = Math.max(0, Math.round(ms / 60000));
                    if (totalMin === 0) return '0m';
                    if (totalMin < 60) return `${totalMin}m`;
                    const h = Math.floor(totalMin / 60);
                    const m = totalMin % 60;
                    return m === 0 ? `${h}h` : `${h}h ${m}m`;
                };
                const csvEscape = (val) => {
                    const s = String(val ?? '');
                    return /[",\n\r]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
                };
                const copyTextToClipboard = async (text) => {
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
                const blockCategory = (block) =>
                    block?.category === 'wasted'
                        ? 'wasted'
                        : (block?.category === 'neutral' ? 'neutral' : 'productive');
                const MONTH_NAMES = ['January', 'February', 'March', 'April', 'May', 'June',
                    'July', 'August', 'September', 'October', 'November', 'December'];
                const MONTH_SHORT = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                    'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

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
                        if (blocks.length === 0) localStorage.removeItem(BLOCKS_KEY);
                        else localStorage.setItem(BLOCKS_KEY, JSON.stringify(blocks));
                    } catch {}
                };

                let signupTs = null;
                if (cfg.signupTimestamp) {
                    const d = new Date(cfg.signupTimestamp);
                    if (!isNaN(d.getTime())) signupTs = d;
                }

                const today = new Date();
                const state = {
                    view: 'overview',     // 'overview' | 'month'
                    year: today.getFullYear(),
                    month: null,          // 1..12 when in month view
                };

                // ──────────────────────── Aggregations ────────────────────────

                const aggregateMonthBlocks = (blocks, year, month) => {
                    // month is 1..12
                    const prefix = `${year}-${pad(month)}-`;
                    const inMonth = blocks.filter((b) =>
                        b.status === 'completed' && b.date && b.date.startsWith(prefix)
                    );
                    let productive = 0, wasted = 0, neutral = 0;
                    const dayMap = new Map();   // date -> { productive, wasted, neutral, blocks: [] }
                    for (const b of inMonth) {
                        const ms = b.durationMs || 0;
                        const cat = blockCategory(b);
                        if (cat === 'wasted') wasted += ms;
                        else if (cat === 'neutral') neutral += ms;
                        else productive += ms;
                        if (!dayMap.has(b.date)) dayMap.set(b.date, { productive: 0, wasted: 0, neutral: 0, blocks: [] });
                        const d = dayMap.get(b.date);
                        if (cat === 'wasted') d.wasted += ms;
                        else if (cat === 'neutral') d.neutral += ms;
                        else d.productive += ms;
                        d.blocks.push(b);
                    }
                    return { productive, wasted, neutral, dayMap, total: productive + wasted + neutral };
                };

                const aggregateYearBlocks = (blocks, year) => {
                    const out = {};
                    for (let m = 1; m <= 12; m++) out[m] = aggregateMonthBlocks(blocks, year, m);
                    return out;
                };

                const isLeapYear = (y) => (y % 4 === 0 && y % 100 !== 0) || y % 400 === 0;
                const daysInMonth = (year, month) => new Date(year, month, 0).getDate();
                const totalDaysInYear = (y) => isLeapYear(y) ? 366 : 365;
                const monthCopyEndDay = (year, month) => {
                    const now = new Date();
                    if (year > now.getFullYear() || (year === now.getFullYear() && month > now.getMonth() + 1)) {
                        return 0;
                    }
                    if (year === now.getFullYear() && month === now.getMonth() + 1) {
                        return now.getDate();
                    }
                    return daysInMonth(year, month);
                };
                const buildMonthCsv = (year, month) => {
                    const header = ['Date', 'Start', 'End', 'Duration', 'Reason', 'Category'];
                    const lines = [header.join(',')];
                    const blocks = loadBlocks()
                        .filter((b) => b && b.status === 'completed' && b.date && b.date.startsWith(`${year}-${pad(month)}-`))
                        .slice()
                        .sort((a, b) => {
                            if (a.date !== b.date) return a.date < b.date ? -1 : 1;
                            return (a.start || '').localeCompare(b.start || '');
                        });
                    const byDate = new Map();
                    for (const b of blocks) {
                        if (!byDate.has(b.date)) byDate.set(b.date, []);
                        byDate.get(b.date).push(b);
                    }

                    let copiedBlockRows = 0;
                    let emptyDayRows = 0;
                    const endDay = monthCopyEndDay(year, month);
                    for (let day = 1; day <= endDay; day++) {
                        const date = `${year}-${pad(month)}-${pad(day)}`;
                        const dayBlocks = byDate.get(date) || [];
                        if (dayBlocks.length === 0) {
                            lines.push([date, '', '', '', 'No blocks logged', ''].map(csvEscape).join(','));
                            emptyDayRows += 1;
                            continue;
                        }
                        for (const b of dayBlocks) {
                            const cat = blockCategory(b);
                            const category = cat === 'wasted'
                                ? 'Wasted'
                                : (cat === 'neutral' ? 'Neutral' : 'Productive');
                            lines.push([
                                date,
                                b.start ? formatTime12(b.start) : '',
                                b.end ? formatTime12(b.end) : '',
                                formatDuration(b.durationMs || 0),
                                b.label || 'Time block',
                                category,
                            ].map(csvEscape).join(','));
                            copiedBlockRows += 1;
                        }
                    }

                    return {
                        csv: lines.join('\n'),
                        blockRows: copiedBlockRows,
                        emptyDayRows,
                        days: endDay,
                    };
                };

                const aggregateYearOverview = (blocks, year) => {
                    const totalDays = totalDaysInYear(year);
                    const totalHoursMs = totalDays * 24 * 3600 * 1000;
                    const prefix = `${year}-`;
                    let productive = 0, wasted = 0, neutral = 0;
                    const daySet = new Set();
                    const monthSet = new Set();
                    for (const b of blocks) {
                        if (b.status !== 'completed') continue;
                        if (!b.date || !b.date.startsWith(prefix)) continue;
                        const ms = b.durationMs || 0;
                        const cat = blockCategory(b);
                        if (cat === 'wasted') wasted += ms;
                        else if (cat === 'neutral') neutral += ms;
                        else productive += ms;
                        daySet.add(b.date);
                        monthSet.add(b.date.slice(0, 7));
                    }
                    return {
                        totalDays,
                        totalHoursMs,
                        productive,
                        wasted,
                        neutral,
                        logged: productive + wasted + neutral,
                        daysLogged: daySet.size,
                        monthsLogged: monthSet.size,
                    };
                };

                const yearsAvailable = (blocks) => {
                    const set = new Set([today.getFullYear()]);
                    for (const b of blocks) {
                        if (b.date) set.add(Number(b.date.slice(0, 4)));
                    }
                    if (signupTs) set.add(signupTs.getFullYear());
                    return Array.from(set).filter((y) => Number.isFinite(y)).sort((a, b) => b - a);
                };

                // ──────────────────────── Filter visibility ────────────────────────
                // Year filter shows when the user has data in 2+ years OR has been on the
                // platform for at least a year. Month filter shows when there's data in
                // 2+ months OR signup was at least 30 days ago.

                const filterVisibility = (blocks) => {
                    const monthSet = new Set();
                    const yearSet = new Set();
                    for (const b of blocks) {
                        if (!b.date) continue;
                        monthSet.add(b.date.slice(0, 7));
                        yearSet.add(b.date.slice(0, 4));
                    }
                    const ageDays = signupTs ? (Date.now() - signupTs.getTime()) / 86400000 : 0;
                    return {
                        showYear: yearSet.size > 1 || ageDays >= 365,
                        showMonth: monthSet.size > 1 || ageDays >= 30,
                    };
                };

                // ──────────────────────── Filter rendering ────────────────────────

                const renderFilters = () => {
                    if (!filtersEl) return;
                    const blocks = loadBlocks();
                    const vis = filterVisibility(blocks);
                    const years = yearsAvailable(blocks);

                    const parts = [];
                    if (vis.showYear) {
                        const yearOpts = years.map(
                            (y) => `<option value="${y}" ${y === state.year ? 'selected' : ''}>${y}</option>`
                        ).join('');
                        parts.push(
                            `<label class="flex items-center gap-2 text-xs uppercase tracking-[0.2em] text-slate-400">` +
                            `Year` +
                            `<select data-filter-year class="rounded-md bg-slate-900/70 border border-slate-700 px-2 py-1 text-sm text-slate-100">${yearOpts}</select>` +
                            `</label>`
                        );
                    }
                    if (vis.showMonth) {
                        const monthOpts = ['<option value="">All</option>'].concat(
                            MONTH_NAMES.map((name, i) => {
                                const v = i + 1;
                                const sel = state.view === 'month' && state.month === v ? 'selected' : '';
                                return `<option value="${v}" ${sel}>${escapeHtml(name)}</option>`;
                            })
                        ).join('');
                        parts.push(
                            `<label class="flex items-center gap-2 text-xs uppercase tracking-[0.2em] text-slate-400">` +
                            `Month` +
                            `<select data-filter-month class="rounded-md bg-slate-900/70 border border-slate-700 px-2 py-1 text-sm text-slate-100">${monthOpts}</select>` +
                            `</label>`
                        );
                    }
                    filtersEl.innerHTML = parts.join('');
                };

                // ──────────────────────── Overview (months grid) ────────────────────────

                const buildMonthCard = (year, month, agg) => {
                    const monthName = MONTH_NAMES[month - 1];
                    const total = agg.total;
                    const days = agg.dayMap.size;
                    const calendarHoursMs = daysInMonth(year, month) * 24 * 3600 * 1000;
                    const isFuture = year > today.getFullYear() ||
                        (year === today.getFullYear() && month > today.getMonth() + 1);
                    const isPreSignup = signupTs && (
                        year < signupTs.getFullYear() ||
                        (year === signupTs.getFullYear() && month < signupTs.getMonth() + 1)
                    );

                    const header = `<div class="text-xs uppercase tracking-[0.2em] text-slate-400">${escapeHtml(monthName)} ${year}</div>`;

                    // Empty card paths — only when there's actually no data for this month.
                    if (total === 0) {
                        const emptyLabel = isFuture
                            ? 'Not yet'
                            : isPreSignup
                                ? 'Pre-signup'
                                : 'No blocks';
                        const dimmed = isFuture || isPreSignup;
                        const cls = dimmed
                            ? 'border-slate-800/30 bg-slate-900/20 opacity-60 cursor-not-allowed'
                            : 'border-slate-800/60 bg-slate-900/40 hover:bg-slate-800/60 hover:border-slate-700 cursor-pointer';
                        const attr = dimmed
                            ? 'disabled'
                            : `data-history-month="${month}"`;
                        return `<button type="button" ${attr}
                            class="rounded-xl border ${cls} p-4 text-left transition">
                            ${header}
                            <div class="mt-2 text-sm text-slate-500">${escapeHtml(emptyLabel)}</div>
                            <div class="mt-1 text-xs text-slate-600">${daysInMonth(year, month) * 24}h calendar · ${daysInMonth(year, month)} days</div>
                        </button>`;
                    }

                    // Populated card — render full breakdown regardless of pre-signup
                    // (data exists, so prioritise showing it).
                    const productivePct = total > 0 ? (agg.productive / total) * 100 : 0;
                    const wastedPct = total > 0 ? (agg.wasted / total) * 100 : 0;
                    const neutralPct = total > 0 ? (agg.neutral / total) * 100 : 0;
                    const loggedShare = calendarHoursMs > 0 ? (total / calendarHoursMs) * 100 : 0;
                    const calendarHoursLabel = `${daysInMonth(year, month) * 24}h`;

                    const bar = `<div class="mt-3 flex h-1.5 rounded-full bg-slate-800 overflow-hidden">` +
                        `<div class="bg-emerald-400" style="width: ${productivePct.toFixed(2)}%"></div>` +
                        `<div class="bg-rose-400" style="width: ${wastedPct.toFixed(2)}%"></div>` +
                        `<div class="bg-slate-400" style="width: ${neutralPct.toFixed(2)}%"></div>` +
                        `</div>`;

                    const note = isPreSignup
                        ? `<div class="mt-2 text-[0.65rem] uppercase tracking-wider text-slate-500">Before signup</div>`
                        : '';

                    return `<button type="button" data-history-month="${month}"
                        class="chrono-lift rounded-xl border border-slate-800/60 bg-slate-900/40 hover:bg-slate-800/60 hover:border-slate-700 cursor-pointer p-4 text-left">
                        ${header}
                        <div class="mt-2 text-sm text-slate-200">
                            <span class="text-slate-100">${escapeHtml(formatHours(total))}</span>
                            <span class="text-slate-500"> of ${escapeHtml(calendarHoursLabel)} logged</span>
                            <span class="text-slate-500"> (${loggedShare.toFixed(1)}%)</span>
                        </div>
                        <div class="text-xs text-slate-500 mt-1">
                            <span class="text-emerald-300">${escapeHtml(formatHours(agg.productive))}</span> productive ·
                            <span class="text-rose-300">${escapeHtml(formatHours(agg.wasted))}</span> wasted ·
                            <span class="text-slate-300">${escapeHtml(formatHours(agg.neutral))}</span> neutral -
                            ${days} ${days === 1 ? 'day' : 'days'}
                        </div>
                        ${bar}
                        ${note}
                    </button>`;
                };

                const buildYearHeader = (year, overview) => {
                    const loggedPct = overview.totalHoursMs > 0
                        ? (overview.logged / overview.totalHoursMs) * 100
                        : 0;
                    const productivePct = overview.logged > 0
                        ? Math.round((overview.productive / overview.logged) * 100)
                        : 0;
                    const wastedPct = overview.logged > 0
                        ? Math.round((overview.wasted / overview.logged) * 100)
                        : 0;
                    const neutralPct = overview.logged > 0
                        ? Math.max(0, 100 - productivePct - wastedPct)
                        : 0;

                    return `
                        <div class="rounded-xl border border-slate-800/60 bg-slate-900/30 p-4 mb-4">
                            <div class="text-xs uppercase tracking-[0.2em] text-slate-400 mb-3">${year} at a glance</div>
                            <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
                                <div class="rounded-lg bg-slate-900/40 border border-slate-800/40 p-3">
                                    <div class="text-[0.65rem] uppercase tracking-wider text-slate-500">Calendar</div>
                                    <div class="mt-1 text-lg text-slate-100">${overview.totalDays * 24}h</div>
                                    <div class="text-xs text-slate-500">${overview.totalDays} days</div>
                                </div>
                                <div class="rounded-lg bg-slate-900/40 border border-slate-800/40 p-3">
                                    <div class="text-[0.65rem] uppercase tracking-wider text-slate-500">Logged</div>
                                    <div class="mt-1 text-lg text-slate-100">${escapeHtml(formatHours(overview.logged))}</div>
                                    <div class="text-xs text-slate-500">${loggedPct.toFixed(1)}% of year</div>
                                </div>
                                <div class="rounded-lg bg-slate-900/40 border border-slate-800/40 p-3">
                                    <div class="text-[0.65rem] uppercase tracking-wider text-slate-500">Productive</div>
                                    <div class="mt-1 text-lg text-emerald-300">${escapeHtml(formatHours(overview.productive))}</div>
                                    <div class="text-xs text-slate-500">${overview.logged > 0 ? productivePct + '% of logged' : '—'}</div>
                                </div>
                                <div class="rounded-lg bg-slate-900/40 border border-slate-800/40 p-3">
                                    <div class="text-[0.65rem] uppercase tracking-wider text-slate-500">Wasted</div>
                                    <div class="mt-1 text-lg text-rose-300">${escapeHtml(formatHours(overview.wasted))}</div>
                                    <div class="text-xs text-slate-500">${overview.logged > 0 ? wastedPct + '% of logged' : '—'}</div>
                                </div>
                                <div class="rounded-lg bg-slate-900/40 border border-slate-800/40 p-3">
                                    <div class="text-[0.65rem] uppercase tracking-wider text-slate-500">Neutral</div>
                                    <div class="mt-1 text-lg text-slate-300">${escapeHtml(formatHours(overview.neutral))}</div>
                                    <div class="text-xs text-slate-500">${overview.logged > 0 ? neutralPct + '% of logged' : '0% of logged'}</div>
                                </div>
                                <div class="rounded-lg bg-slate-900/40 border border-slate-800/40 p-3">
                                    <div class="text-[0.65rem] uppercase tracking-wider text-slate-500">Coverage</div>
                                    <div class="mt-1 text-lg text-slate-100">${overview.daysLogged}<span class="text-slate-500"> / ${overview.totalDays}</span></div>
                                    <div class="text-xs text-slate-500">${overview.monthsLogged} / 12 months</div>
                                </div>
                            </div>
                        </div>
                    `;
                };

                const renderOverview = () => {
                    titleEl.textContent = `Months in ${state.year}`;
                    backBtn.classList.add('hidden');

                    const blocks = loadBlocks();
                    const yearAgg = aggregateYearBlocks(blocks, state.year);
                    const yearOverview = aggregateYearOverview(blocks, state.year);

                    const yearHeader = buildYearHeader(state.year, yearOverview);

                    const cards = [];
                    for (let m = 1; m <= 12; m++) cards.push(buildMonthCard(state.year, m, yearAgg[m]));

                    contentEl.innerHTML = yearHeader +
                        `<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">${cards.join('')}</div>`;
                };

                // ──────────────────────── Month detail ────────────────────────

                const groupDaysIntoWeeks = (year, month) => {
                    const firstDay = new Date(year, month - 1, 1);
                    const lastDayNum = new Date(year, month, 0).getDate();
                    const weeks = [];
                    let current = null;
                    for (let day = 1; day <= lastDayNum; day++) {
                        const d = new Date(year, month - 1, day);
                        const dow = d.getDay();
                        if (current === null || dow === 1) {
                            current = { startDate: d, endDate: d, days: [] };
                            weeks.push(current);
                        }
                        current.days.push(d);
                        current.endDate = d;
                    }
                    return weeks;
                };

                const renderBlocksList = (blocks) => {
                    if (blocks.length === 0) {
                        return '<li class="text-slate-500 text-xs">No blocks</li>';
                    }
                    return blocks
                        .slice()
                        .sort((a, b) => (a.start || '').localeCompare(b.start || ''))
                        .map((b) => {
                            const cat = blockCategory(b);
                            const chip = cat === 'wasted'
                                ? '<span class="ml-2 inline-flex items-center rounded-full px-2 py-0.5 text-[0.65rem] uppercase tracking-wider bg-rose-500/15 text-rose-300 border border-rose-500/30">Wasted</span>'
                                : (cat === 'neutral'
                                    ? '<span class="ml-2 inline-flex items-center rounded-full px-2 py-0.5 text-[0.65rem] uppercase tracking-wider bg-slate-500/15 text-slate-300 border border-slate-500/40">Neutral</span>'
                                    : '<span class="ml-2 inline-flex items-center rounded-full px-2 py-0.5 text-[0.65rem] uppercase tracking-wider bg-emerald-500/15 text-emerald-300 border border-emerald-500/40">Productive</span>');
                            return `<li class="flex flex-wrap items-baseline gap-x-2 text-sm py-1">` +
                                `<span class="text-slate-100 tabular-nums">${escapeHtml(formatTime12(b.start))} – ${escapeHtml(formatTime12(b.end))}</span>` +
                                `<span class="text-slate-500 tabular-nums">(${escapeHtml(formatDuration(b.durationMs || 0))})</span>` +
                                `<span class="text-slate-300">${escapeHtml(b.label || 'Time block')}</span>` +
                                chip +
                                `</li>`;
                        }).join('');
                };

                const renderMonth = () => {
                    const { year, month } = state;
                    const blocks = loadBlocks();
                    const agg = aggregateMonthBlocks(blocks, year, month);
                    const weeks = groupDaysIntoWeeks(year, month);

                    titleEl.textContent = `${MONTH_NAMES[month - 1]} ${year}`;
                    backBtn.classList.remove('hidden');

                    const totalProductive = agg.productive;
                    const totalWasted = agg.wasted;
                    const totalNeutral = agg.neutral;
                    const daysLogged = agg.dayMap.size;

                    // Top stats
                    const totalLogged = totalProductive + totalWasted + totalNeutral;
                    const calendarHoursMonth = daysInMonth(year, month) * 24;
                    const calendarHoursMs = calendarHoursMonth * 3600 * 1000;
                    const loggedShare = calendarHoursMs > 0
                        ? (totalLogged / calendarHoursMs) * 100
                        : 0;

                    // ── Efficiency (matches dashboard formula) ────────────
                    // productive ÷ (productive + wasted + unlogged)
                    // = productive ÷ awake_elapsed_in_period
                    // Wasted AND unlogged time both reduce efficiency.
                    const hhmmToMin = (hhmm) => {
                        if (!hhmm) return null;
                        const [h, m] = hhmm.split(':').map(Number);
                        return h * 60 + m;
                    };
                    const wakeMins = hhmmToMin(cfg.wakeTime || '07:00') ?? 420;
                    const endMins = hhmmToMin(cfg.endTime || '22:00') ?? 1320;
                    const sleepPerNightMin = wakeMins > endMins
                        ? wakeMins - endMins
                        : (24 * 60) - endMins + wakeMins;
                    const awakePerDayMs = (24 * 60 - sleepPerNightMin) * 60 * 1000;

                    // Effective elapsed range for this month: clamp to signup
                    // start and current "now", so partial months reflect only
                    // the days we could realistically have logged.
                    const monthStart = new Date(year, month - 1, 1);
                    const monthEnd = new Date(year, month, 1);
                    const signup = cfg.signupTimestamp ? new Date(cfg.signupTimestamp) : null;
                    const effStart = signup && signup > monthStart ? signup : monthStart;
                    const now = new Date();
                    const effEnd = now < monthEnd ? now : monthEnd;
                    let elapsedAwakeMs = 0;
                    if (effEnd > effStart) {
                        // Walk calendar days inside the elapsed window and
                        // count one awake-day per full day, plus a partial
                        // awake slice for boundary days.
                        const cursor = new Date(effStart);
                        cursor.setHours(0, 0, 0, 0);
                        const finalDay = new Date(effEnd);
                        finalDay.setHours(0, 0, 0, 0);
                        while (cursor.getTime() <= finalDay.getTime()) {
                            elapsedAwakeMs += awakePerDayMs;
                            cursor.setDate(cursor.getDate() + 1);
                        }
                    }
                    const unloggedMs = Math.max(0, elapsedAwakeMs - totalLogged);
                    const denomMs = totalProductive + totalWasted + unloggedMs;
                    const ratio = denomMs > 0
                        ? Math.min(100, Math.round((totalProductive / denomMs) * 100))
                        : 0;
                    const copyDays = monthCopyEndDay(year, month);
                    const monthActions = `
                        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-800/60 bg-slate-900/30 px-4 py-3">
                            <div>
                                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Month export</div>
                                <div class="mt-1 text-sm text-slate-300">
                                    Copies ${copyDays > 0 ? copyDays : 0} ${copyDays === 1 ? 'day' : 'days'} as CSV with Date, Start, End, Duration, Reason, Category.
                                </div>
                            </div>
                            <button type="button" data-copy-month-csv
                                class="inline-flex items-center gap-2 rounded-md border border-[var(--chrono-blue)]/50 px-3 py-2 text-xs uppercase tracking-[0.2em] text-[var(--chrono-blue)] hover:bg-[var(--chrono-blue)]/10 disabled:cursor-not-allowed disabled:opacity-50"
                                ${copyDays > 0 ? '' : 'disabled'}>
                                Copy month
                            </button>
                        </div>
                    `;
                    const topStats = `
                        <div class="grid grid-cols-2 lg:grid-cols-6 gap-3">
                            <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3">
                                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Calendar</div>
                                <div class="mt-1 text-lg text-slate-100">${calendarHoursMonth}h</div>
                                <div class="text-xs text-slate-500">${daysInMonth(year, month)} days</div>
                            </div>
                            <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3">
                                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Logged</div>
                                <div class="mt-1 text-lg text-slate-100">${escapeHtml(formatHours(totalLogged))}</div>
                                <div class="text-xs text-slate-500">${daysLogged} ${daysLogged === 1 ? 'day' : 'days'} · ${loggedShare.toFixed(1)}%</div>
                            </div>
                            <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3">
                                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Productive</div>
                                <div class="mt-1 text-lg text-emerald-300">${escapeHtml(formatHours(totalProductive))}</div>
                            </div>
                            <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3">
                                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Wasted</div>
                                <div class="mt-1 text-lg text-rose-300">${escapeHtml(formatHours(totalWasted))}</div>
                            </div>
                            <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3">
                                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Neutral</div>
                                <div class="mt-1 text-lg text-slate-300">${escapeHtml(formatHours(totalNeutral))}</div>
                                <div class="text-[0.6rem] text-slate-500 mt-0.5">logged, score-neutral</div>
                            </div>
                            <div class="rounded-xl border border-[var(--chrono-blue)]/30 bg-[var(--chrono-blue)]/5 p-3"
                                title="Productive ÷ (Productive + Wasted + Unlogged). Wasted AND unlogged time both reduce efficiency.">
                                <div class="text-xs uppercase tracking-[0.2em] text-[var(--chrono-blue)]">Efficiency</div>
                                <div class="mt-1 text-lg text-[var(--chrono-blue)]">${denomMs > 0 ? ratio + '%' : '—'}</div>
                                <div class="text-[0.6rem] text-slate-500 mt-0.5">prod ÷ (prod + wasted + unlogged)</div>
                            </div>
                        </div>
                    `;

                    // Weeks summary
                    const weekSections = weeks.map((w, idx) => {
                        let weekProductive = 0, weekWasted = 0, weekNeutral = 0, weekDays = 0;
                        const dayDetails = [];
                        for (const dDate of w.days) {
                            const key = localDateString(dDate);
                            const d = agg.dayMap.get(key);
                            const dProd = d?.productive || 0;
                            const dWaste = d?.wasted || 0;
                            const dNeutral = d?.neutral || 0;
                            const dBlocks = d?.blocks || [];
                            weekProductive += dProd;
                            weekWasted += dWaste;
                            weekNeutral += dNeutral;
                            if (dBlocks.length > 0) weekDays++;

                            const weekday = dDate.toLocaleDateString('en-US', { weekday: 'short' });
                            const dayLabel = `${MONTH_SHORT[dDate.getMonth()]} ${dDate.getDate()}, ${weekday}`;
                            const summary = (dProd + dWaste + dNeutral) > 0
                                ? `<span class="text-emerald-300">${escapeHtml(formatDuration(dProd))}</span>` +
                                  (dWaste > 0
                                      ? ` · <span class="text-rose-300">${escapeHtml(formatDuration(dWaste))} wasted</span>`
                                      : '') +
                                  (dNeutral > 0
                                      ? ` - <span class="text-slate-300">${escapeHtml(formatDuration(dNeutral))} neutral</span>`
                                      : '')
                                : '<span class="text-slate-600">No blocks</span>';

                            // Click a day → navigate to the read-only day
                            // report. Replaces the previous inline-expanding
                            // <details> behaviour so the History page only
                            // shows month/week summary rows.
                            const dayUrlTemplate = (window.ChronoHistoryConfig?.dayDetailUrl) || '';
                            const dayUrl = dayUrlTemplate
                                ? dayUrlTemplate.replace('__DATE__', key)
                                : '#';
                            dayDetails.push(
                                `<a href="${dayUrl}" ` +
                                `class="block rounded-lg border border-slate-800/60 bg-slate-900/30 hover:border-[var(--chrono-blue)]/60 hover:bg-slate-800/40 transition-colors px-3 py-2 flex flex-wrap items-baseline justify-between gap-2" ` +
                                `title="Click to see detailed report">` +
                                `<span class="text-sm text-slate-200">${escapeHtml(dayLabel)}</span>` +
                                `<span class="text-sm">${summary}</span>` +
                                `</a>`
                            );
                        }

                        const range = w.startDate.getDate() === w.endDate.getDate()
                            ? `${MONTH_SHORT[w.startDate.getMonth()]} ${w.startDate.getDate()}`
                            : `${MONTH_SHORT[w.startDate.getMonth()]} ${w.startDate.getDate()} – ${MONTH_SHORT[w.endDate.getMonth()]} ${w.endDate.getDate()}`;
                        const weekLogged = weekProductive + weekWasted + weekNeutral;
                        const weekScoreTotal = weekProductive + weekWasted;
                        const weekRatio = weekScoreTotal > 0
                            ? Math.round((weekProductive / weekScoreTotal) * 100)
                            : 0;

                        return `
                            <section class="rounded-xl border border-slate-800/60 bg-slate-900/20 p-4">
                                <header class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
                                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Week ${idx + 1} · ${escapeHtml(range)}</div>
                                    <div class="text-xs text-slate-500">
                                        ${weekLogged > 0
                                            ? `<span class="text-emerald-300">${escapeHtml(formatHours(weekProductive))}</span> productive` +
                                              (weekWasted > 0 ? ` · <span class="text-rose-300">${escapeHtml(formatHours(weekWasted))}</span> wasted` : '') +
                                              (weekNeutral > 0 ? ` - <span class="text-slate-300">${escapeHtml(formatHours(weekNeutral))}</span> neutral` : '') +
                                              ` · ${weekDays} ${weekDays === 1 ? 'day' : 'days'} (${weekRatio}%)`
                                            : '<span class="text-slate-600">No blocks this week</span>'}
                                    </div>
                                </header>
                                <div class="space-y-2">${dayDetails.join('')}</div>
                            </section>
                        `;
                    }).join('');

                    contentEl.innerHTML = monthActions + topStats + '<div class="mt-6 space-y-4">' + weekSections + '</div>';
                };

                // ──────────────────────── Search ────────────────────────

                const FILTERS_KEY = 'chrono.historyFilters.v1';
                const searchQEl = document.querySelector('[data-search-q]');
                const searchFromEl = document.querySelector('[data-search-from]');
                const searchToEl = document.querySelector('[data-search-to]');
                const searchClearEl = document.querySelector('[data-search-clear]');
                const searchCatBtns = Array.from(document.querySelectorAll('[data-search-cat]'));

                const filters = { q: '', category: 'all', from: '', to: '' };
                try {
                    const raw = sessionStorage.getItem(FILTERS_KEY);
                    if (raw) {
                        const parsed = JSON.parse(raw);
                        if (parsed && typeof parsed === 'object') {
                            if (typeof parsed.q === 'string') filters.q = parsed.q;
                            if (typeof parsed.category === 'string') filters.category = parsed.category;
                            if (typeof parsed.from === 'string') filters.from = parsed.from;
                            if (typeof parsed.to === 'string') filters.to = parsed.to;
                        }
                    }
                } catch {}

                const persistFilters = () => {
                    try { sessionStorage.setItem(FILTERS_KEY, JSON.stringify(filters)); } catch {}
                };

                const isSearchActive = () =>
                    (filters.q && filters.q.trim() !== '') ||
                    filters.category !== 'all' ||
                    !!filters.from ||
                    !!filters.to;

                const syncSearchUI = () => {
                    if (searchQEl && searchQEl.value !== filters.q) searchQEl.value = filters.q;
                    if (searchFromEl && searchFromEl.value !== filters.from) searchFromEl.value = filters.from;
                    if (searchToEl && searchToEl.value !== filters.to) searchToEl.value = filters.to;
                    for (const btn of searchCatBtns) {
                        const active = btn.dataset.searchCat === filters.category;
                        btn.classList.toggle('border-[var(--chrono-blue)]/60', active);
                        btn.classList.toggle('bg-[var(--chrono-blue)]/10', active);
                        btn.classList.toggle('text-cyan-200', active);
                        btn.classList.toggle('border-slate-700/60', !active);
                        btn.classList.toggle('bg-slate-900/40', !active);
                        btn.classList.toggle('text-slate-300', !active);
                        btn.setAttribute('aria-pressed', active ? 'true' : 'false');
                    }
                    if (searchClearEl) searchClearEl.classList.toggle('hidden', !isSearchActive());
                };

                // Wrap case-insensitive matches of `q` inside escaped text with
                // <mark>. Caller passes the raw label; we escape ourselves so
                // the search term never bleeds into HTML.
                const highlight = (text, q) => {
                    const escaped = escapeHtml(text);
                    if (!q) return escaped;
                    const needle = q.trim();
                    if (!needle) return escaped;
                    const lowerText = text.toLowerCase();
                    const lowerNeedle = needle.toLowerCase();
                    let out = '';
                    let cursor = 0;
                    while (cursor < text.length) {
                        const found = lowerText.indexOf(lowerNeedle, cursor);
                        if (found === -1) {
                            out += escapeHtml(text.slice(cursor));
                            break;
                        }
                        out += escapeHtml(text.slice(cursor, found));
                        const matchSlice = text.slice(found, found + needle.length);
                        out += `<mark class="bg-[var(--chrono-blue)]/20 text-cyan-200 rounded px-0.5">${escapeHtml(matchSlice)}</mark>`;
                        cursor = found + needle.length;
                    }
                    return out;
                };

                const formatDateHeading = (yyyyMmDd) => {
                    // Parse y-m-d as a local date to avoid TZ drift on the
                    // group heading vs. the stored `date` string.
                    const [y, m, d] = yyyyMmDd.split('-').map(Number);
                    const dt = new Date(y, (m || 1) - 1, d || 1);
                    if (isNaN(dt.getTime())) return yyyyMmDd;
                    return dt.toLocaleDateString('en-US', {
                        weekday: 'short', month: 'short', day: 'numeric', year: 'numeric',
                    });
                };

                const runSearch = () => {
                    const blocks = loadBlocks();
                    const q = (filters.q || '').trim().toLowerCase();
                    const cat = filters.category;
                    const from = filters.from || '';
                    const to = filters.to || '';

                    const results = [];
                    for (const b of blocks) {
                        if (b.status !== 'completed') continue;
                        if (!b.date) continue;
                        if (from && b.date < from) continue;
                        if (to && b.date > to) continue;
                        if (cat !== 'all') {
                            const bc = b.category;
                            if (cat === 'productive') {
                                // Legacy/default blocks (no category) historically
                                // count as productive in this codebase.
                                if (bc !== 'productive' && bc != null) continue;
                            } else {
                                if (bc !== cat) continue;
                            }
                        }
                        const label = b.label || '';
                        if (q) {
                            if (label.toLowerCase().indexOf(q) === -1) continue;
                        }
                        results.push(b);
                    }

                    results.sort((a, b) => {
                        if (a.date !== b.date) return a.date < b.date ? 1 : -1;
                        const sa = a.start || '';
                        const sb = b.start || '';
                        if (sa !== sb) return sa < sb ? 1 : -1;
                        return 0;
                    });

                    return results;
                };

                const renderSearchResults = () => {
                    titleEl.textContent = 'Search results';
                    backBtn.classList.add('hidden');

                    const results = runSearch();
                    const q = (filters.q || '').trim();

                    if (results.length === 0) {
                        contentEl.innerHTML =
                            `<div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-6 text-center">
                                <p class="text-sm text-slate-300">No matching activities found.</p>
                                <p class="mt-1 text-xs text-slate-500">Try a different keyword or widen the date range.</p>
                            </div>`;
                        return;
                    }

                    const groups = new Map();
                    for (const b of results) {
                        if (!groups.has(b.date)) groups.set(b.date, []);
                        groups.get(b.date).push(b);
                    }

                    const summary = `<p class="text-xs text-slate-400 mb-4">Showing ${results.length} ${results.length === 1 ? 'match' : 'matches'} across ${groups.size} ${groups.size === 1 ? 'day' : 'days'}</p>`;

                    const sections = [];
                    for (const [date, items] of groups) {
                        const heading = formatDateHeading(date);
                        const dayUrlTemplate = (window.ChronoHistoryConfig?.dayDetailUrl) || '';
                        const dayUrl = dayUrlTemplate ? dayUrlTemplate.replace('__DATE__', date) : '#';
                        const rows = items.map((b) => {
                            const cat = b.category;
                            let chipClass, chipText, dotClass;
                            if (cat === 'wasted') {
                                chipClass = 'bg-rose-500/15 text-rose-300 border-rose-500/30';
                                chipText = 'Wasted';
                                dotClass = 'bg-rose-400';
                            } else if (cat === 'neutral') {
                                chipClass = 'bg-slate-700/40 text-slate-300 border-slate-600/40';
                                chipText = 'Neutral';
                                dotClass = 'bg-slate-400';
                            } else {
                                chipClass = 'bg-emerald-500/15 text-emerald-300 border-emerald-500/40';
                                chipText = 'Productive';
                                dotClass = 'bg-emerald-400';
                            }
                            const labelHtml = highlight(b.label || 'Time block', q);
                            return `<a href="${dayUrl}" tabindex="0" data-search-row class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg border border-slate-800/60 bg-slate-900/40 hover:border-[var(--chrono-blue)]/60 hover:bg-slate-800/40 focus:outline-none focus:border-[var(--chrono-blue)]/80 focus:ring-1 focus:ring-[var(--chrono-blue)]/40 transition-colors px-3 py-2 text-sm">
                                <span class="inline-flex items-center rounded-md border border-slate-700/60 bg-slate-900/60 px-2 py-0.5 text-xs text-slate-100 tabular-nums">${escapeHtml(formatTime12(b.start))} – ${escapeHtml(formatTime12(b.end))}</span>
                                <span class="inline-flex items-center rounded-md bg-slate-800/60 px-2 py-0.5 text-[0.65rem] uppercase tracking-wider text-slate-300 tabular-nums">${escapeHtml(formatDuration(b.durationMs || 0))}</span>
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[0.65rem] uppercase tracking-wider border ${chipClass}">
                                    <span class="inline-block h-1.5 w-1.5 rounded-full ${dotClass} mr-1.5"></span>${chipText}
                                </span>
                                <span class="text-slate-200 break-words">${labelHtml}</span>
                            </a>`;
                        }).join('');

                        sections.push(`
                            <section class="rounded-xl border border-slate-800/60 bg-slate-900/20 p-4">
                                <header class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
                                    <a href="${dayUrl}" class="text-xs uppercase tracking-[0.2em] text-slate-300 hover:text-[var(--chrono-blue)]">${escapeHtml(heading)}</a>
                                    <span class="text-xs text-slate-500">(${items.length} ${items.length === 1 ? 'entry' : 'entries'})</span>
                                </header>
                                <div class="space-y-2">${rows}</div>
                            </section>
                        `);
                    }

                    contentEl.innerHTML = summary + '<div class="space-y-4">' + sections.join('') + '</div>';
                };

                // ──────────────────────── Render dispatcher ────────────────────────

                const render = () => {
                    syncSearchUI();
                    if (isSearchActive()) {
                        // Hide the year/month select while in search mode — those
                        // controls don't apply to the flat results view.
                        if (filtersEl) filtersEl.innerHTML = '';
                        renderSearchResults();
                        return;
                    }
                    renderFilters();
                    if (state.view === 'month' && state.month) renderMonth();
                    else { state.view = 'overview'; renderOverview(); }
                };

                // ──────────────────────── Search event wiring ────────────────────────

                let qDebounce = null;
                searchQEl?.addEventListener('input', (e) => {
                    const val = e.target.value;
                    if (qDebounce) clearTimeout(qDebounce);
                    qDebounce = setTimeout(() => {
                        filters.q = val;
                        persistFilters();
                        render();
                    }, 150);
                });
                searchQEl?.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') {
                        filters.q = '';
                        if (searchQEl) searchQEl.value = '';
                        persistFilters();
                        render();
                        return;
                    }
                    if (e.key === 'ArrowDown') {
                        const firstRow = contentEl.querySelector('[data-search-row]');
                        if (firstRow) {
                            e.preventDefault();
                            firstRow.focus();
                        }
                    }
                });

                searchFromEl?.addEventListener('change', (e) => {
                    filters.from = e.target.value || '';
                    persistFilters();
                    render();
                });
                searchToEl?.addEventListener('change', (e) => {
                    filters.to = e.target.value || '';
                    persistFilters();
                    render();
                });

                for (const btn of searchCatBtns) {
                    btn.addEventListener('click', () => {
                        filters.category = btn.dataset.searchCat || 'all';
                        persistFilters();
                        render();
                    });
                }

                searchClearEl?.addEventListener('click', () => {
                    filters.q = '';
                    filters.category = 'all';
                    filters.from = '';
                    filters.to = '';
                    persistFilters();
                    render();
                });

                // ──────────────────────── Event wiring ────────────────────────

                contentEl.addEventListener('click', (e) => {
                    const copyMonthBtn = e.target.closest('[data-copy-month-csv]');
                    if (copyMonthBtn) {
                        e.preventDefault();
                        if (copyMonthBtn.disabled) return;
                        if (state.view !== 'month' || !state.month) return;

                        const payload = buildMonthCsv(state.year, state.month);
                        if (payload.days <= 0) {
                            window.showToast?.('No days to copy for this month yet.', { tone: 'warn' });
                            return;
                        }
                        copyMonthBtn.disabled = true;
                        copyTextToClipboard(payload.csv).then((ok) => {
                            if (ok) {
                                const monthLabel = `${MONTH_NAMES[state.month - 1]} ${state.year}`;
                                const totalRows = payload.blockRows + payload.emptyDayRows;
                                window.showToast?.(
                                    `Copied ${totalRows} ${totalRows === 1 ? 'row' : 'rows'} from ${monthLabel} as CSV.`,
                                    { tone: 'success' }
                                );
                            } else {
                                window.showToast?.('Copy failed - your browser blocked clipboard access.', { tone: 'error' });
                            }
                        }).finally(() => {
                            copyMonthBtn.disabled = false;
                        });
                        return;
                    }

                    const monthBtn = e.target.closest('[data-history-month]');
                    if (monthBtn && !monthBtn.disabled) {
                        const m = Number(monthBtn.dataset.historyMonth);
                        if (Number.isFinite(m)) {
                            state.view = 'month';
                            state.month = m;
                            render();
                            window.scrollTo({ top: 0, behavior: 'smooth' });
                        }
                    }
                });

                backBtn?.addEventListener('click', () => {
                    state.view = 'overview';
                    state.month = null;
                    render();
                });

                filtersEl?.addEventListener('change', (e) => {
                    const yearSel = e.target.closest('[data-filter-year]');
                    if (yearSel) {
                        state.year = Number(yearSel.value);
                        // Stay in current view if month view, else overview
                        if (state.view === 'month' && state.month) render();
                        else { state.view = 'overview'; render(); }
                        return;
                    }
                    const monthSel = e.target.closest('[data-filter-month]');
                    if (monthSel) {
                        const v = monthSel.value;
                        if (v === '') {
                            state.view = 'overview';
                            state.month = null;
                        } else {
                            state.view = 'month';
                            state.month = Number(v);
                        }
                        render();
                    }
                });

                window.addEventListener('chrono:blocks:changed', render);

                render();
            })();
        </script>
    @endpush
@endsection
