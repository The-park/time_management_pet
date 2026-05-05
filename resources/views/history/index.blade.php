@extends('layouts.app')

@section('content')
    @php
        $user = auth()->user();
        $timezone = $user?->timezone ?? 'UTC';
        $signupAt = $user?->created_at?->copy()->setTimezone($timezone);
        $signupTimestamp = $signupAt?->toIso8601String();
        $signupDateLabel = $signupAt?->format('M j, Y');
    @endphp

    <div class="relative overflow-hidden rounded-2xl border border-slate-800/60 bg-[radial-gradient(circle_at_top,_rgba(0,224,255,0.15),_transparent_45%)] p-8 mb-8">
        <div class="absolute -right-24 -top-24 h-56 w-56 rounded-full bg-[radial-gradient(circle,_rgba(255,107,26,0.35),_transparent_70%)] blur-2xl"></div>
        <div class="relative">
            <h1 class="font-display text-3xl tracking-[0.3em] uppercase">History</h1>
            <p class="text-slate-300 text-sm mt-2">Browse your time month by month, drill into weeks and days.</p>
        </div>
    </div>

    <div class="space-y-6">
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

        <section class="rounded-2xl border border-dashed border-slate-700/40 bg-slate-900/20 p-4 md:p-5">
            <details>
                <summary class="cursor-pointer text-xs uppercase tracking-[0.2em] text-slate-400 hover:text-slate-200">
                    Test data tools
                </summary>
                <div class="mt-3 space-y-3">
                    <p class="text-xs text-slate-500">
                        These buttons synthesize realistic blocks across past dates so you can exercise the history views.
                        Generated blocks have IDs prefixed <code class="text-slate-300">test_</code> so they can be cleared without touching real entries.
                    </p>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" data-testdata-generate="120"
                            class="rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-100 px-3 py-2 text-sm">
                            Generate 120 days
                        </button>
                        <button type="button" data-testdata-generate="400"
                            class="rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-100 px-3 py-2 text-sm">
                            Generate 400 days (year-spanning)
                        </button>
                        <button type="button" data-testdata-clear-test
                            class="rounded-lg border border-slate-600 hover:border-slate-400 text-slate-200 px-3 py-2 text-sm">
                            Remove sample data only
                        </button>
                        <button type="button" data-testdata-clear-all
                            class="rounded-lg border border-rose-700/60 hover:border-rose-500 text-rose-300 px-3 py-2 text-sm">
                            Clear ALL blocks
                        </button>
                    </div>
                    <p class="text-xs text-slate-400" data-testdata-status></p>
                </div>
            </details>
        </section>
    </div>

    @push('scripts')
        <script>
            window.ChronoHistoryConfig = {
                timezone: @json($timezone),
                signupTimestamp: @json($signupTimestamp),
                signupDateLabel: @json($signupDateLabel),
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
                const statusEl = document.querySelector('[data-testdata-status]');
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
                    let productive = 0, wasted = 0;
                    const dayMap = new Map();   // date → { productive, wasted, blocks: [] }
                    for (const b of inMonth) {
                        const ms = b.durationMs || 0;
                        if (b.category === 'wasted') wasted += ms; else productive += ms;
                        if (!dayMap.has(b.date)) dayMap.set(b.date, { productive: 0, wasted: 0, blocks: [] });
                        const d = dayMap.get(b.date);
                        if (b.category === 'wasted') d.wasted += ms; else d.productive += ms;
                        d.blocks.push(b);
                    }
                    return { productive, wasted, dayMap, total: productive + wasted };
                };

                const aggregateYearBlocks = (blocks, year) => {
                    const out = {};
                    for (let m = 1; m <= 12; m++) out[m] = aggregateMonthBlocks(blocks, year, m);
                    return out;
                };

                const isLeapYear = (y) => (y % 4 === 0 && y % 100 !== 0) || y % 400 === 0;
                const daysInMonth = (year, month) => new Date(year, month, 0).getDate();
                const totalDaysInYear = (y) => isLeapYear(y) ? 366 : 365;

                const aggregateYearOverview = (blocks, year) => {
                    const totalDays = totalDaysInYear(year);
                    const totalHoursMs = totalDays * 24 * 3600 * 1000;
                    const prefix = `${year}-`;
                    let productive = 0, wasted = 0;
                    const daySet = new Set();
                    const monthSet = new Set();
                    for (const b of blocks) {
                        if (b.status !== 'completed') continue;
                        if (!b.date || !b.date.startsWith(prefix)) continue;
                        const ms = b.durationMs || 0;
                        if (b.category === 'wasted') wasted += ms; else productive += ms;
                        daySet.add(b.date);
                        monthSet.add(b.date.slice(0, 7));
                    }
                    return {
                        totalDays,
                        totalHoursMs,
                        productive,
                        wasted,
                        logged: productive + wasted,
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
                    const loggedShare = calendarHoursMs > 0 ? (total / calendarHoursMs) * 100 : 0;
                    const calendarHoursLabel = `${daysInMonth(year, month) * 24}h`;

                    const bar = `<div class="mt-3 flex h-1.5 rounded-full bg-slate-800 overflow-hidden">` +
                        `<div class="bg-emerald-400" style="width: ${productivePct.toFixed(2)}%"></div>` +
                        `<div class="bg-rose-400" style="width: ${wastedPct.toFixed(2)}%"></div>` +
                        `</div>`;

                    const note = isPreSignup
                        ? `<div class="mt-2 text-[0.65rem] uppercase tracking-wider text-slate-500">Before signup</div>`
                        : '';

                    return `<button type="button" data-history-month="${month}"
                        class="rounded-xl border border-slate-800/60 bg-slate-900/40 hover:bg-slate-800/60 hover:border-slate-700 cursor-pointer p-4 text-left transition">
                        ${header}
                        <div class="mt-2 text-sm text-slate-200">
                            <span class="text-slate-100">${escapeHtml(formatHours(total))}</span>
                            <span class="text-slate-500"> of ${escapeHtml(calendarHoursLabel)} logged</span>
                            <span class="text-slate-500"> (${loggedShare.toFixed(1)}%)</span>
                        </div>
                        <div class="text-xs text-slate-500 mt-1">
                            <span class="text-emerald-300">${escapeHtml(formatHours(agg.productive))}</span> productive ·
                            <span class="text-rose-300">${escapeHtml(formatHours(agg.wasted))}</span> wasted ·
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
                        ? 100 - productivePct
                        : 0;

                    return `
                        <div class="rounded-xl border border-slate-800/60 bg-slate-900/30 p-4 mb-4">
                            <div class="text-xs uppercase tracking-[0.2em] text-slate-400 mb-3">${year} at a glance</div>
                            <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
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
                            const isWasted = b.category === 'wasted';
                            const chip = isWasted
                                ? '<span class="ml-2 inline-flex items-center rounded-full px-2 py-0.5 text-[0.65rem] uppercase tracking-wider bg-rose-500/15 text-rose-300 border border-rose-500/30">Wasted</span>'
                                : '<span class="ml-2 inline-flex items-center rounded-full px-2 py-0.5 text-[0.65rem] uppercase tracking-wider bg-slate-700/40 text-slate-400 border border-slate-600/40">Productive</span>';
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
                    const daysLogged = agg.dayMap.size;
                    const ratio = (totalProductive + totalWasted) > 0
                        ? Math.round((totalProductive / (totalProductive + totalWasted)) * 100)
                        : 0;

                    // Top stats
                    const totalLogged = totalProductive + totalWasted;
                    const calendarHoursMonth = daysInMonth(year, month) * 24;
                    const calendarHoursMs = calendarHoursMonth * 3600 * 1000;
                    const loggedShare = calendarHoursMs > 0
                        ? (totalLogged / calendarHoursMs) * 100
                        : 0;
                    const topStats = `
                        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
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
                                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Productive %</div>
                                <div class="mt-1 text-lg text-slate-100">${totalLogged > 0 ? ratio + '%' : '—'}</div>
                            </div>
                        </div>
                    `;

                    // Weeks summary
                    const weekSections = weeks.map((w, idx) => {
                        let weekProductive = 0, weekWasted = 0, weekDays = 0;
                        const dayDetails = [];
                        for (const dDate of w.days) {
                            const key = localDateString(dDate);
                            const d = agg.dayMap.get(key);
                            const dProd = d?.productive || 0;
                            const dWaste = d?.wasted || 0;
                            const dBlocks = d?.blocks || [];
                            weekProductive += dProd;
                            weekWasted += dWaste;
                            if (dBlocks.length > 0) weekDays++;

                            const weekday = dDate.toLocaleDateString('en-US', { weekday: 'short' });
                            const dayLabel = `${MONTH_SHORT[dDate.getMonth()]} ${dDate.getDate()}, ${weekday}`;
                            const summary = (dProd + dWaste) > 0
                                ? `<span class="text-emerald-300">${escapeHtml(formatDuration(dProd))}</span>` +
                                  (dWaste > 0
                                      ? ` · <span class="text-rose-300">${escapeHtml(formatDuration(dWaste))} wasted</span>`
                                      : '')
                                : '<span class="text-slate-600">No blocks</span>';

                            dayDetails.push(
                                `<details class="rounded-lg border border-slate-800/60 bg-slate-900/30">` +
                                `<summary class="cursor-pointer px-3 py-2 flex flex-wrap items-baseline justify-between gap-2 hover:bg-slate-800/40">` +
                                `<span class="text-sm text-slate-200">${escapeHtml(dayLabel)}</span>` +
                                `<span class="text-sm">${summary}</span>` +
                                `</summary>` +
                                `<ul class="px-3 py-2 border-t border-slate-800/60 space-y-1">` +
                                renderBlocksList(dBlocks) +
                                `</ul>` +
                                `</details>`
                            );
                        }

                        const range = w.startDate.getDate() === w.endDate.getDate()
                            ? `${MONTH_SHORT[w.startDate.getMonth()]} ${w.startDate.getDate()}`
                            : `${MONTH_SHORT[w.startDate.getMonth()]} ${w.startDate.getDate()} – ${MONTH_SHORT[w.endDate.getMonth()]} ${w.endDate.getDate()}`;
                        const weekTotal = weekProductive + weekWasted;
                        const weekRatio = weekTotal > 0
                            ? Math.round((weekProductive / weekTotal) * 100)
                            : 0;

                        return `
                            <section class="rounded-xl border border-slate-800/60 bg-slate-900/20 p-4">
                                <header class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
                                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Week ${idx + 1} · ${escapeHtml(range)}</div>
                                    <div class="text-xs text-slate-500">
                                        ${weekTotal > 0
                                            ? `<span class="text-emerald-300">${escapeHtml(formatHours(weekProductive))}</span> productive` +
                                              (weekWasted > 0 ? ` · <span class="text-rose-300">${escapeHtml(formatHours(weekWasted))}</span> wasted` : '') +
                                              ` · ${weekDays} ${weekDays === 1 ? 'day' : 'days'} (${weekRatio}%)`
                                            : '<span class="text-slate-600">No blocks this week</span>'}
                                    </div>
                                </header>
                                <div class="space-y-2">${dayDetails.join('')}</div>
                            </section>
                        `;
                    }).join('');

                    contentEl.innerHTML = topStats + '<div class="mt-6 space-y-4">' + weekSections + '</div>';
                };

                // ──────────────────────── Render dispatcher ────────────────────────

                const render = () => {
                    renderFilters();
                    if (state.view === 'month' && state.month) renderMonth();
                    else { state.view = 'overview'; renderOverview(); }
                };

                // ──────────────────────── Event wiring ────────────────────────

                contentEl.addEventListener('click', (e) => {
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

                // ──────────────────────── Test data tools ────────────────────────

                const PRODUCTIVE_LABELS = [
                    'Standup meeting', 'Code review', 'Feature implementation',
                    'Bug fixing', 'Documentation', 'Email triage',
                    'Pair programming', 'Design review', 'Lunch break',
                    'Coffee break', 'Reading docs', 'Studying',
                    'Architecture discussion', 'PR review', 'Testing',
                    'Deep work', 'Focused coding', 'Refactoring',
                    '1:1 meeting', 'Planning', 'Retrospective',
                ];
                const WASTED_LABELS = [
                    'Scrolling youtube', 'Reddit browsing', 'Social media catch-up',
                    'Procrastinating on twitter', 'Idle browsing',
                    'Wasted on instagram', 'Doomscrolling tiktok',
                    'Mindless reddit', 'Unproductive scrolling',
                ];

                const rand = (min, max) => min + Math.floor(Math.random() * (max - min + 1));
                const minToHHMM = (m) => `${pad(Math.floor(m / 60))}:${pad(m % 60)}`;

                const generateSampleData = (daysBack) => {
                    const existing = loadBlocks();
                    const generated = [];
                    const now = new Date();
                    for (let i = daysBack; i >= 1; i--) {
                        const d = new Date(now);
                        d.setDate(d.getDate() - i);
                        const dateStr = localDateString(d);
                        const isWeekend = d.getDay() === 0 || d.getDay() === 6;
                        let cursor = (isWeekend ? 10 : 9) * 60;
                        const endLimit = 18 * 60;
                        const numBlocks = isWeekend ? rand(2, 5) : rand(4, 8);
                        for (let j = 0; j < numBlocks && cursor + 30 <= endLimit; j++) {
                            const duration = [30, 45, 60, 90, 120][rand(0, 4)];
                            const blockEnd = Math.min(cursor + duration, endLimit);
                            const wastedRoll = isWeekend ? 0.35 : 0.15;
                            const isWasted = Math.random() < wastedRoll;
                            const labels = isWasted ? WASTED_LABELS : PRODUCTIVE_LABELS;
                            const label = labels[rand(0, labels.length - 1)];
                            generated.push({
                                id: `test_${dateStr}_${j}_${Math.random().toString(36).slice(2, 8)}`,
                                source: 'manual',
                                date: dateStr,
                                start: minToHHMM(cursor),
                                end: minToHHMM(blockEnd),
                                durationMs: (blockEnd - cursor) * 60000,
                                label,
                                status: 'completed',
                                category: isWasted ? 'wasted' : 'productive',
                            });
                            cursor = blockEnd + rand(0, 4) * 15;
                        }
                    }
                    saveBlocks([...existing, ...generated]);
                    window.dispatchEvent(new CustomEvent('chrono:blocks:changed'));
                    return generated.length;
                };

                const setStatus = (msg) => {
                    if (!statusEl) return;
                    statusEl.textContent = msg;
                };

                document.querySelectorAll('[data-testdata-generate]').forEach((btn) => {
                    btn.addEventListener('click', () => {
                        if (!window.ChronoAuthRequire?.('generate sample data')) return;
                        const days = Number(btn.dataset.testdataGenerate) || 120;
                        if (!confirm(`Generate ~${days} days of synthetic time blocks? Existing blocks won't be touched.`)) return;
                        const n = generateSampleData(days);
                        setStatus(`Generated ${n} sample blocks across ${days} days.`);
                    });
                });

                document.querySelector('[data-testdata-clear-test]')?.addEventListener('click', () => {
                    if (!window.ChronoAuthRequire?.('clear sample data')) return;
                    if (!confirm('Remove only sample (test_*) blocks? Real blocks stay.')) return;
                    const before = loadBlocks();
                    const after = before.filter((b) => !String(b.id || '').startsWith('test_'));
                    saveBlocks(after);
                    window.dispatchEvent(new CustomEvent('chrono:blocks:changed'));
                    setStatus(`Removed ${before.length - after.length} sample blocks.`);
                });

                document.querySelector('[data-testdata-clear-all]')?.addEventListener('click', () => {
                    if (!window.ChronoAuthRequire?.('clear all blocks')) return;
                    if (!confirm('Permanently delete ALL time blocks (real and sample)? This cannot be undone.')) return;
                    const n = loadBlocks().length;
                    saveBlocks([]);
                    window.dispatchEvent(new CustomEvent('chrono:blocks:changed'));
                    setStatus(`Cleared ${n} blocks.`);
                });

                render();
            })();
        </script>
    @endpush
@endsection
