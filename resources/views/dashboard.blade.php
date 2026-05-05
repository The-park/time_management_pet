@extends('layouts.app')

@section('content')
    @php
        $user = auth()->user();
        $endTime = $user?->end_of_day_time ? substr($user->end_of_day_time, 0, 5) : '22:00';
        $endTimeDisplay = \Carbon\Carbon::createFromFormat('H:i', $endTime)->format('g:i A');
        $wakeTime = $user?->wake_up_time ? substr($user->wake_up_time, 0, 5) : '07:00';
        $wakeTimeDisplay = \Carbon\Carbon::createFromFormat('H:i', $wakeTime)->format('g:i A');
        $timezone = $user?->timezone ?? 'UTC';
        [$endH, $endM] = array_map('intval', explode(':', $endTime));
        [$wakeH, $wakeM] = array_map('intval', explode(':', $wakeTime));
        $endMinsRef = $endH * 60 + $endM;
        $wakeMinsRef = $wakeH * 60 + $wakeM;
        $sleepMins = $wakeMinsRef > $endMinsRef
            ? $wakeMinsRef - $endMinsRef
            : (24 * 60 - $endMinsRef + $wakeMinsRef);
        $sleepH = intdiv($sleepMins, 60);
        $sleepM = $sleepMins % 60;
        $sleepLabel = $sleepM === 0 ? "{$sleepH}h" : "{$sleepH}h {$sleepM}m";
    @endphp
    <div class="relative overflow-hidden rounded-2xl border border-slate-800/60 bg-[radial-gradient(circle_at_top,_rgba(0,224,255,0.15),_transparent_45%)] p-8 mb-10">
        <div class="absolute -right-24 -top-24 h-56 w-56 rounded-full bg-[radial-gradient(circle,_rgba(255,107,26,0.35),_transparent_70%)] blur-2xl"></div>
        <div class="relative">
            <h1 class="font-display text-3xl tracking-[0.3em] uppercase">Dashboard</h1>
            <p class="text-slate-300 text-sm mt-2">Track today, close the loops, and beat the deadline.</p>
        </div>
    </div>

    <div class="space-y-8">
        <section class="chrono-panel rounded-2xl p-6 md:p-8">
            <div class="flex items-baseline justify-between gap-4 mb-5">
                <h2 class="font-display text-sm uppercase tracking-[0.3em] text-slate-300">Today</h2>
                <span class="text-xs text-slate-500" data-today-date></span>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                <input id="todays_goal_input" type="text" maxlength="240" placeholder="Today's goal — ship the MVP before 6pm"
                    class="flex-1 rounded-lg bg-slate-900/70 border border-slate-700 px-4 py-3 text-slate-100">
                <label class="flex items-center gap-2 text-sm text-slate-300 select-none">
                    <input id="todays_goal_done" type="checkbox"
                        class="h-4 w-4 rounded border-slate-600 bg-slate-900 accent-[var(--chrono-blue)]">
                    Done
                </label>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mt-6">
                <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-4">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">End of day</div>
                    <div class="mt-2 font-digital text-2xl chrono-glow-blue">
                        <span data-remaining-time>00:00:00</span>
                    </div>
                    <div class="text-xs text-slate-500 mt-1">
                        Until <span data-until-time>{{ $endTimeDisplay }}</span>
                        · <span data-until-zone>{{ $timezone }}</span>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-4">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Sleep window</div>
                    <div class="mt-2 text-lg text-slate-100">
                        {{ $endTimeDisplay }} → {{ $wakeTimeDisplay }}
                    </div>
                    <div class="text-xs text-slate-500 mt-1">{{ $sleepLabel }} scheduled</div>
                </div>

                <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-4">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Logged today</div>
                    <div class="mt-2 text-2xl text-slate-100" data-logged-today>0m</div>
                    <div class="text-xs text-slate-500 mt-1" data-logged-count>0 blocks</div>
                </div>

                <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-4">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Unlogged today</div>
                    <div class="mt-2 text-2xl text-slate-100" data-unlogged-today>0m</div>
                    <div class="text-xs text-slate-500 mt-1" data-unlogged-context>since wake-up</div>
                </div>
            </div>

            <div class="mt-5 flex flex-wrap items-center gap-3 text-xs">
                <label class="font-display uppercase tracking-[0.3em] text-slate-500" for="dashboard_end_time_display">
                    Edit end time
                </label>
                <input id="dashboard_end_time_display" type="text" inputmode="numeric"
                    placeholder="10:00 PM" value="{{ $endTimeDisplay }}"
                    data-time12
                    data-time12-hidden-id="dashboard_end_time_value"
                    data-time12-error-id="dashboard_end_time_error"
                    data-time12-label="End of day"
                    data-time12-min="18:00"
                    data-time12-max="23:59"
                    data-time12-example="10:00 PM"
                    class="w-32 rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 text-slate-100">
                <input id="dashboard_end_time_value" type="hidden" value="{{ $endTime }}"
                    data-end-time-input data-timezone="{{ $timezone }}">
                <p id="dashboard_end_time_error" class="text-rose-400 hidden" aria-live="polite"></p>
            </div>

            <div class="mt-6">
                <h3 class="text-xs uppercase tracking-[0.2em] text-slate-400">Top blocks today</h3>
                <ul class="mt-2 space-y-1 text-sm" data-top-blocks>
                    <li class="text-slate-500">No blocks logged yet today.</li>
                </ul>
            </div>
        </section>

        <section class="chrono-panel rounded-2xl p-6 md:p-8" data-period-section="week">
            <div class="flex items-baseline justify-between gap-4">
                <h2 class="font-display text-sm uppercase tracking-[0.3em] text-slate-300">This week</h2>
                <span class="text-xs text-slate-500" data-period-range></span>
            </div>
            <div class="mt-4 h-2 rounded-full bg-slate-800/80 overflow-hidden">
                <div class="h-full bg-[var(--chrono-blue)] transition-[width] duration-500" data-period-progress style="width: 0%"></div>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-4">
                <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Passed</div>
                    <div class="mt-1 text-lg text-slate-100" data-period-passed>—</div>
                </div>
                <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Left</div>
                    <div class="mt-1 text-lg text-slate-100" data-period-left>—</div>
                </div>
                <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Productive</div>
                    <div class="mt-1 text-lg text-slate-100" data-period-productive>—</div>
                </div>
                <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Utilization</div>
                    <div class="mt-1 text-lg text-slate-100" data-period-ratio>—</div>
                </div>
            </div>
            <div class="mt-5">
                <h3 class="text-xs uppercase tracking-[0.2em] text-slate-400">Last 7 days</h3>
                <div class="mt-2 grid grid-cols-7 gap-2" data-last-7-days></div>
            </div>
        </section>

        <section class="chrono-panel rounded-2xl p-6 md:p-8" data-period-section="month">
            <div class="flex items-baseline justify-between gap-4">
                <h2 class="font-display text-sm uppercase tracking-[0.3em] text-slate-300">This month</h2>
                <span class="text-xs text-slate-500" data-period-range></span>
            </div>
            <div class="mt-4 h-2 rounded-full bg-slate-800/80 overflow-hidden">
                <div class="h-full bg-[var(--chrono-orange)] transition-[width] duration-500" data-period-progress style="width: 0%"></div>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-4">
                <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Passed</div>
                    <div class="mt-1 text-lg text-slate-100" data-period-passed>—</div>
                </div>
                <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Left</div>
                    <div class="mt-1 text-lg text-slate-100" data-period-left>—</div>
                </div>
                <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Productive</div>
                    <div class="mt-1 text-lg text-slate-100" data-period-productive>—</div>
                </div>
                <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Utilization</div>
                    <div class="mt-1 text-lg text-slate-100" data-period-ratio>—</div>
                </div>
            </div>
        </section>

        <section class="chrono-panel rounded-2xl p-6 md:p-8" data-period-section="year">
            <div class="flex items-baseline justify-between gap-4">
                <h2 class="font-display text-sm uppercase tracking-[0.3em] text-slate-300">This year</h2>
                <span class="text-xs text-slate-500" data-period-range></span>
            </div>
            <div class="mt-4 h-2 rounded-full bg-slate-800/80 overflow-hidden">
                <div class="h-full bg-emerald-400 transition-[width] duration-500" data-period-progress style="width: 0%"></div>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-4">
                <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Passed</div>
                    <div class="mt-1 text-lg text-slate-100" data-period-passed>—</div>
                </div>
                <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Left</div>
                    <div class="mt-1 text-lg text-slate-100" data-period-left>—</div>
                </div>
                <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Productive</div>
                    <div class="mt-1 text-lg text-slate-100" data-period-productive>—</div>
                </div>
                <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Utilization</div>
                    <div class="mt-1 text-lg text-slate-100" data-period-ratio>—</div>
                </div>
            </div>
        </section>

        <section class="chrono-panel rounded-2xl p-6 md:p-8">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                <div class="flex-1">
                    <h2 class="font-display text-sm uppercase tracking-[0.3em] text-slate-300">Custom countdown</h2>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-4">
                        <input type="number" min="0" placeholder="Days" data-cc-days
                            class="rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 disabled:opacity-50">
                        <input type="number" min="0" placeholder="Hours" data-cc-hours
                            class="rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 disabled:opacity-50">
                        <input type="number" min="0" placeholder="Minutes" data-cc-minutes
                            class="rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 disabled:opacity-50">
                        <input type="number" min="0" placeholder="Seconds" data-cc-seconds
                            class="rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 disabled:opacity-50">
                    </div>
                    <input type="text" placeholder="Label (optional)" maxlength="120" data-cc-label
                        class="mt-3 w-full rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 disabled:opacity-50">
                    <div class="mt-4 flex gap-3">
                        <button type="button" data-cc-start
                            class="rounded-lg bg-[var(--chrono-blue)] text-slate-950 font-semibold px-4 py-2 disabled:opacity-50 disabled:cursor-not-allowed">Start</button>
                        <button type="button" data-cc-pause
                            class="rounded-lg border border-slate-600 px-4 py-2 disabled:opacity-50 disabled:cursor-not-allowed">Pause</button>
                        <button type="button" data-cc-reset
                            class="rounded-lg border border-slate-600 px-4 py-2 disabled:opacity-50 disabled:cursor-not-allowed">Reset</button>
                    </div>
                    <p class="mt-3 text-xs text-slate-400">
                        Each countdown logs a block in <em>Today's time blocks</em>. Maximum duration is <strong>1 hour</strong>.
                    </p>
                    <p data-cc-error class="mt-1 text-xs text-rose-400 hidden" aria-live="polite"></p>
                </div>
                <div class="flex-1">
                    <h2 class="font-display text-sm uppercase tracking-[0.3em] text-slate-300">Timer</h2>
                    <div class="mt-3 font-digital text-4xl md:text-5xl tracking-[0.2em] chrono-glow-orange" data-cc-display>
                        <span data-cc-time>00:00:00</span>
                        <span class="text-base tracking-[0.4em] ml-2 text-slate-300" data-cc-status>READY</span>
                    </div>
                    <div class="text-sm text-slate-400 mt-2 min-h-[1.25rem]" data-cc-display-label></div>
                </div>
            </div>
        </section>

        <section class="chrono-panel rounded-2xl p-6 md:p-8">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <h2 class="font-display text-sm uppercase tracking-[0.3em] text-slate-300">Today's time blocks</h2>
                <button class="rounded-lg border border-slate-600 px-4 py-2 text-sm">Add new block</button>
            </div>

            <div class="mt-6 grid md:grid-cols-4 gap-3">
                <div>
                    <input id="block_start_display" type="text" inputmode="numeric" placeholder="9:00 AM" value="9:00 AM"
                        data-time12
                        data-time12-hidden-id="block_start_value"
                        data-time12-error-id="block_start_error"
                        data-time12-label="Start time"
                        data-time12-example="9:00 AM"
                        data-time12-group="block_form"
                        class="w-full rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2">
                    <input id="block_start_value" type="hidden" value="09:00">
                    <p id="block_start_error" class="mt-1 text-xs text-rose-400 hidden" aria-live="polite"></p>
                </div>
                <input id="block_duration_input" type="number" min="1" placeholder="Duration (min)"
                    class="rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 self-start">
                <div>
                    <input id="block_end_display" type="text" inputmode="numeric" placeholder="10:00 AM" value="10:00 AM"
                        data-time12
                        data-time12-hidden-id="block_end_value"
                        data-time12-error-id="block_end_error"
                        data-time12-label="End time"
                        data-time12-example="10:00 AM"
                        data-time12-group="block_form"
                        class="w-full rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2">
                    <input id="block_end_value" type="hidden" value="10:00">
                    <p id="block_end_error" class="mt-1 text-xs text-rose-400 hidden" aria-live="polite"></p>
                </div>
                <textarea id="block_reason_input" rows="1" placeholder="Reason / Activity"
                    class="rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 md:col-span-4 self-start"></textarea>
            </div>
            <p class="mt-3 text-xs text-slate-400">
                Times use <strong>12-hour format with AM/PM</strong> (e.g. <span class="text-slate-300">9:00 AM</span>, <span class="text-slate-300">2:30pm</span>, <span class="text-slate-300">11 p.m.</span>). 24-hour input is not accepted — Log block stays disabled until both times are valid. End time can be at most <strong>1 hour ahead</strong> of the current time.
            </p>
            <div class="mt-4">
                <button data-time12-gate="block_form"
                    class="rounded-lg bg-[var(--chrono-orange)] text-slate-950 font-semibold px-4 py-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    Log block
                </button>
                <p id="block_form_error" class="mt-2 text-xs text-rose-400 hidden" aria-live="polite"></p>
            </div>

            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-slate-400">
                        <tr>
                            <th class="text-left py-2">Start</th>
                            <th class="text-left py-2">End</th>
                            <th class="text-left py-2">Duration</th>
                            <th class="text-left py-2">Reason / Activity</th>
                            <th class="text-left py-2">Actions</th>
                        </tr>
                    </thead>
                    <tbody data-blocks-tbody></tbody>
                </table>
            </div>
        </section>
    </div>

    <div id="hourly_modal" role="dialog" aria-modal="true" aria-labelledby="hourly_modal_title" aria-hidden="true"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 backdrop-blur-sm p-4">
        <div class="w-full max-w-md rounded-2xl border border-slate-700/60 bg-[var(--chrono-bg)] p-6 shadow-2xl">
            <h3 id="hourly_modal_title" class="font-display text-base uppercase tracking-[0.2em] text-slate-100">Hourly check-in</h3>
            <p class="mt-2 text-sm text-slate-300">
                What did you do between
                <span class="font-medium text-slate-100" data-hourly-from></span>
                and
                <span class="font-medium text-slate-100" data-hourly-to></span>?
            </p>
            <textarea id="hourly_modal_input" rows="3" maxlength="240" placeholder="e.g. Reviewed pull requests, helped on Slack"
                class="mt-3 w-full rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 text-slate-100"></textarea>
            <p class="mt-1 text-xs text-slate-500">Saving creates a completed time block for that hour.</p>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" id="hourly_modal_skip"
                    class="rounded-lg border border-slate-600 px-4 py-2 text-sm">Skip</button>
                <button type="button" id="hourly_modal_save" disabled
                    class="rounded-lg bg-[var(--chrono-blue)] text-slate-950 font-semibold px-4 py-2 text-sm disabled:opacity-50 disabled:cursor-not-allowed">Save block</button>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            window.ChronoDashboardConfig = {
                endTime: @json($endTime),
                wakeTime: @json($wakeTime),
                timezone: @json($timezone),
            };
        </script>
        <script>
            (() => {
                const BLOCKS_KEY = 'chrono.timeBlocks.v1';
                const tbody = document.querySelector('[data-blocks-tbody]');
                if (!tbody) return;

                const pad = (n) => String(n).padStart(2, '0');
                const formatTime12 = (hhmm) => {
                    if (!hhmm) return '';
                    const [h, m] = hhmm.split(':').map(Number);
                    const period = h >= 12 ? 'PM' : 'AM';
                    const hour12 = h === 0 ? 12 : (h > 12 ? h - 12 : h);
                    return `${hour12}:${pad(m)} ${period}`;
                };
                const dateToHHMM = (d) => `${pad(d.getHours())}:${pad(d.getMinutes())}`;
                const hhmmToMinutes = (hhmm) => {
                    const [h, m] = hhmm.split(':').map(Number);
                    return h * 60 + m;
                };
                const msToDurationLabel = (ms) => {
                    const totalMin = Math.max(0, Math.round(ms / 60000));
                    if (totalMin === 0) return '< 1m';
                    if (totalMin < 60) return `${totalMin}m`;
                    const hours = Math.floor(totalMin / 60);
                    const mins = totalMin % 60;
                    return mins === 0 ? `${hours}h` : `${hours}h ${mins}m`;
                };
                const escapeHtml = (str) => String(str ?? '').replace(/[&<>"']/g, (c) => ({
                    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
                }[c]));

                const localDateString = (d = new Date()) =>
                    `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

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
                        if (blocks.length === 0) {
                            localStorage.removeItem(BLOCKS_KEY);
                        } else {
                            localStorage.setItem(BLOCKS_KEY, JSON.stringify(blocks));
                        }
                    } catch {
                        /* storage may be disabled */
                    }
                };
                const dispatchChange = () => {
                    window.dispatchEvent(new CustomEvent('chrono:blocks:changed'));
                };

                // One-time migration: stamp date-less blocks as today so period roll-ups work.
                (() => {
                    const blocks = loadBlocks();
                    let dirty = false;
                    const today = localDateString();
                    for (const block of blocks) {
                        if (!block.date) {
                            block.date = today;
                            dirty = true;
                        }
                    }
                    if (dirty) saveBlocks(blocks);
                })();

                const render = () => {
                    const blocks = loadBlocks().slice().sort((a, b) => {
                        const aMin = hhmmToMinutes(a.start || '00:00');
                        const bMin = hhmmToMinutes(b.start || '00:00');
                        if (aMin !== bMin) return aMin - bMin;
                        return (a.id || '').localeCompare(b.id || '');
                    });
                    tbody.innerHTML = '';

                    if (blocks.length === 0) {
                        const tr = document.createElement('tr');
                        tr.innerHTML = '<td class="py-3 text-slate-500" colspan="5">No time blocks yet — start a countdown or log one manually.</td>';
                        tbody.appendChild(tr);
                        return;
                    }

                    for (const block of blocks) {
                        const tr = document.createElement('tr');
                        tr.className = 'border-t border-slate-800/60';
                        tr.dataset.blockId = block.id;

                        let badge = '';
                        if (block.status === 'active') {
                            badge = '<span class="text-xs uppercase tracking-wider text-[var(--chrono-blue)] mr-2">Running</span>';
                        } else if (block.status === 'paused') {
                            badge = '<span class="text-xs uppercase tracking-wider text-amber-300 mr-2">Paused</span>';
                        }

                        const endText = block.status === 'paused'
                            ? '<span class="text-slate-500">— paused —</span>'
                            : escapeHtml(formatTime12(block.end));

                        const labelText = block.label
                            || (block.source === 'countdown' ? 'Custom countdown' : 'Time block');

                        tr.innerHTML = `
                            <td class="py-3">${escapeHtml(formatTime12(block.start))}</td>
                            <td class="py-3">${endText}</td>
                            <td class="py-3">${escapeHtml(msToDurationLabel(block.durationMs))}</td>
                            <td class="py-3">${badge}${escapeHtml(labelText)}</td>
                            <td class="py-3">
                                <div class="flex gap-2">
                                    <button class="text-[var(--chrono-red)]" data-block-delete>Delete</button>
                                </div>
                            </td>
                        `;
                        tbody.appendChild(tr);
                    }
                };

                const add = (data) => {
                    const block = {
                        id: `${Date.now()}_${Math.random().toString(36).slice(2, 8)}`,
                        ...data,
                        date: data.date || localDateString(),
                    };
                    const blocks = loadBlocks();
                    blocks.push(block);
                    saveBlocks(blocks);
                    render();
                    dispatchChange();
                    return block;
                };
                const update = (id, updates) => {
                    const blocks = loadBlocks();
                    const block = blocks.find((b) => b.id === id);
                    if (!block) return;
                    Object.assign(block, updates);
                    saveBlocks(blocks);
                    render();
                    dispatchChange();
                };
                const remove = (id) => {
                    const blocks = loadBlocks().filter((b) => b.id !== id);
                    saveBlocks(blocks);
                    render();
                    dispatchChange();
                };
                const get = (id) => loadBlocks().find((b) => b.id === id) || null;

                tbody.addEventListener('click', (e) => {
                    const btn = e.target.closest('[data-block-delete]');
                    if (!btn) return;
                    const tr = btn.closest('tr');
                    const id = tr?.dataset.blockId;
                    if (!id) return;
                    const block = get(id);
                    if (block && block.source === 'countdown'
                        && (block.status === 'active' || block.status === 'paused')) {
                        const resetBtn = document.querySelector('[data-cc-reset]');
                        if (resetBtn) {
                            resetBtn.click();
                            return;
                        }
                    }
                    remove(id);
                });

                const ONE_HOUR_MS = 60 * 60 * 1000;
                const logBlockBtn = document.querySelector('[data-time12-gate="block_form"]');
                const startHidden = document.getElementById('block_start_value');
                const endHidden = document.getElementById('block_end_value');
                const startDisplay = document.getElementById('block_start_display');
                const endDisplay = document.getElementById('block_end_display');
                const reasonInput = document.getElementById('block_reason_input');
                const durationInput = document.getElementById('block_duration_input');
                const blockFormError = document.getElementById('block_form_error');

                const showBlockFormError = (msg) => {
                    if (!blockFormError) return;
                    blockFormError.textContent = msg;
                    blockFormError.classList.remove('hidden');
                };
                const clearBlockFormError = () => {
                    if (blockFormError) blockFormError.classList.add('hidden');
                };

                for (const el of [startDisplay, endDisplay, durationInput, reasonInput]) {
                    el?.addEventListener('input', clearBlockFormError);
                }

                if (logBlockBtn && startHidden && endHidden) {
                    logBlockBtn.addEventListener('click', () => {
                        const start = startHidden.value;
                        const end = endHidden.value;
                        if (!start || !end) return;

                        const startMin = hhmmToMinutes(start);
                        const endMin = hhmmToMinutes(end);
                        let derivedMs = (endMin - startMin) * 60 * 1000;
                        const typedDuration = Math.max(0, Math.floor(Number(durationInput?.value) || 0));
                        let durationMs = typedDuration > 0 ? typedDuration * 60 * 1000 : derivedMs;
                        if (durationMs <= 0) {
                            showBlockFormError('End time must be after start time (or set a positive duration).');
                            return;
                        }

                        const now = new Date();
                        const todayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime();
                        const blockEndMs = todayStart + endMin * 60 * 1000;
                        const futureLimit = now.getTime() + ONE_HOUR_MS;
                        if (blockEndMs > futureLimit) {
                            const limitDate = new Date(futureLimit);
                            showBlockFormError(`End time can be at most 1 hour ahead of now (≤ ${formatTime12(dateToHHMM(limitDate))}).`);
                            return;
                        }

                        clearBlockFormError();
                        const label = (reasonInput?.value || '').trim() || 'Time block';
                        add({
                            source: 'manual',
                            start,
                            end,
                            durationMs,
                            label,
                            status: 'completed',
                        });

                        if (reasonInput) reasonInput.value = '';
                        if (durationInput) durationInput.value = '';
                    });
                }

                window.ChronoBlocks = { add, update, remove, render, get, dateToHHMM };
                render();
            })();
        </script>

        <script>
            (() => {
                const input = document.querySelector('[data-end-time-input]');
                const remainingEl = document.querySelector('[data-remaining-time]');
                const untilEl = document.querySelector('[data-until-time]');
                const zoneEl = document.querySelector('[data-until-zone]');

                if (!input || !remainingEl || !untilEl) {
                    return;
                }

                const timeZone = input.dataset.timezone || 'UTC';
                const formatTime12 = (hhmm) => {
                    if (!hhmm) return '';
                    const [h, m] = hhmm.split(':').map(Number);
                    const period = h >= 12 ? 'PM' : 'AM';
                    const hour12 = h === 0 ? 12 : (h > 12 ? h - 12 : h);
                    return `${hour12}:${String(m).padStart(2, '0')} ${period}`;
                };
                const pad = (value) => String(value).padStart(2, '0');
                const formatter = new Intl.DateTimeFormat('en-US', {
                    timeZone,
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: false,
                });

                const getZonedParts = (date) => {
                    const parts = formatter.formatToParts(date);
                    const data = {};

                    for (const part of parts) {
                        if (part.type !== 'literal') {
                            data[part.type] = part.value;
                        }
                    }

                    return {
                        year: Number(data.year),
                        month: Number(data.month),
                        day: Number(data.day),
                        hour: Number(data.hour),
                        minute: Number(data.minute),
                        second: Number(data.second),
                    };
                };

                const getTimeZoneOffsetMs = (date) => {
                    const parts = getZonedParts(date);
                    const asUtc = Date.UTC(
                        parts.year,
                        parts.month - 1,
                        parts.day,
                        parts.hour,
                        parts.minute,
                        parts.second
                    );

                    return asUtc - date.getTime();
                };

                const zonedTimeToUtc = (year, month, day, hour, minute, second) => {
                    const utcGuess = Date.UTC(year, month - 1, day, hour, minute, second);
                    const offset = getTimeZoneOffsetMs(new Date(utcGuess));

                    return utcGuess - offset;
                };

                const getTargetUtc = () => {
                    const timeValue = input.value || '22:00';
                    const [hours, minutes] = timeValue.split(':').map(Number);
                    const now = new Date();
                    const nowParts = getZonedParts(now);
                    let targetUtc = zonedTimeToUtc(
                        nowParts.year,
                        nowParts.month,
                        nowParts.day,
                        hours || 0,
                        minutes || 0,
                        0
                    );

                    if (targetUtc <= now.getTime()) {
                        const localMidnightUtc = zonedTimeToUtc(
                            nowParts.year,
                            nowParts.month,
                            nowParts.day,
                            0,
                            0,
                            0
                        );
                        const nextDay = new Date(localMidnightUtc + 86400000);
                        const nextParts = getZonedParts(nextDay);
                        targetUtc = zonedTimeToUtc(
                            nextParts.year,
                            nextParts.month,
                            nextParts.day,
                            hours || 0,
                            minutes || 0,
                            0
                        );
                    }

                    return targetUtc;
                };

                const setZoneLabel = () => {
                    if (!zoneEl) {
                        return;
                    }

                    const parts = new Intl.DateTimeFormat('en-US', {
                        timeZone,
                        timeZoneName: 'short',
                    }).formatToParts(new Date());
                    const zonePart = parts.find((part) => part.type === 'timeZoneName');

                    zoneEl.textContent = zonePart?.value || timeZone;
                };

                const updateCountdown = () => {
                    const now = new Date();
                    const targetUtc = getTargetUtc();
                    const diff = Math.max(0, targetUtc - now.getTime());
                    const totalSeconds = Math.floor(diff / 1000);
                    const hours = Math.floor(totalSeconds / 3600);
                    const minutes = Math.floor((totalSeconds % 3600) / 60);
                    const seconds = totalSeconds % 60;

                    remainingEl.textContent = `${pad(hours)}:${pad(minutes)}:${pad(seconds)}`;
                    untilEl.textContent = formatTime12(input.value || '22:00');
                };

                input.addEventListener('time12:change', updateCountdown);
                setZoneLabel();
                updateCountdown();
                setInterval(updateCountdown, 1000);
            })();
        </script>

        <script>
            (() => {
                const STORAGE_KEY = 'chrono.customCountdown.v1';

                const daysInput = document.querySelector('[data-cc-days]');
                const hoursInput = document.querySelector('[data-cc-hours]');
                const minutesInput = document.querySelector('[data-cc-minutes]');
                const secondsInput = document.querySelector('[data-cc-seconds]');
                const labelInput = document.querySelector('[data-cc-label]');
                const startBtn = document.querySelector('[data-cc-start]');
                const pauseBtn = document.querySelector('[data-cc-pause]');
                const resetBtn = document.querySelector('[data-cc-reset]');
                const timeEl = document.querySelector('[data-cc-time]');
                const statusEl = document.querySelector('[data-cc-status]');
                const labelDisplay = document.querySelector('[data-cc-display-label]');
                const displayWrap = document.querySelector('[data-cc-display]');

                if (!daysInput || !startBtn || !timeEl) return;

                const numericInputs = [daysInput, hoursInput, minutesInput, secondsInput];
                const pad = (n) => String(n).padStart(2, '0');

                let audioCtx = null;
                let audioUnlocked = false;

                const ensureAudio = () => {
                    const Ctx = window.AudioContext || window.webkitAudioContext;
                    if (!Ctx) return null;
                    if (!audioCtx) {
                        try {
                            audioCtx = new Ctx();
                        } catch {
                            audioCtx = null;
                            return null;
                        }
                    }
                    if (audioCtx.state === 'suspended') {
                        audioCtx.resume().catch(() => {});
                    }
                    audioUnlocked = true;
                    return audioCtx;
                };

                const unlockOnFirstGesture = () => {
                    if (audioUnlocked) return;
                    ensureAudio();
                };
                ['pointerdown', 'keydown', 'touchstart'].forEach((evt) => {
                    window.addEventListener(evt, unlockOnFirstGesture, { once: true, passive: true });
                });

                const CHIME_NOTES = [
                    { freq: 523.25, start: 0.00 }, // C5
                    { freq: 659.25, start: 0.25 }, // E5
                    { freq: 783.99, start: 0.50 }, // G5
                ];
                const CHIME_BURST_MS = 3500;

                let chimeMaster = null;
                let chimeTimeoutId = null;

                const playOneChimeBurst = () => {
                    if (!audioCtx || !chimeMaster) return;
                    const now = audioCtx.currentTime;
                    for (const note of CHIME_NOTES) {
                        const osc = audioCtx.createOscillator();
                        const gain = audioCtx.createGain();
                        osc.type = 'sine';
                        osc.frequency.value = note.freq;
                        const t0 = now + note.start;
                        gain.gain.setValueAtTime(0.0001, t0);
                        gain.gain.exponentialRampToValueAtTime(0.55, t0 + 0.18);
                        gain.gain.exponentialRampToValueAtTime(0.0001, t0 + 2.6);
                        osc.connect(gain).connect(chimeMaster);
                        osc.start(t0);
                        osc.stop(t0 + 2.7);
                    }
                };

                const stopChimeLoop = () => {
                    if (chimeTimeoutId !== null) {
                        clearTimeout(chimeTimeoutId);
                        chimeTimeoutId = null;
                    }
                    if (chimeMaster && audioCtx) {
                        const node = chimeMaster;
                        chimeMaster = null;
                        const now = audioCtx.currentTime;
                        try {
                            node.gain.cancelScheduledValues(now);
                            node.gain.setValueAtTime(node.gain.value, now);
                            node.gain.linearRampToValueAtTime(0.0001, now + 0.15);
                        } catch {}
                        setTimeout(() => {
                            try { node.disconnect(); } catch {}
                        }, 250);
                    } else {
                        chimeMaster = null;
                    }
                };

                const startChimeLoop = () => {
                    const ctx = ensureAudio();
                    if (!ctx) return;
                    stopChimeLoop();
                    chimeMaster = ctx.createGain();
                    chimeMaster.gain.setValueAtTime(0.0001, ctx.currentTime);
                    chimeMaster.gain.linearRampToValueAtTime(0.22, ctx.currentTime + 0.05);
                    chimeMaster.connect(ctx.destination);

                    const loop = () => {
                        playOneChimeBurst();
                        chimeTimeoutId = setTimeout(loop, CHIME_BURST_MS);
                    };
                    loop();
                };

                let state = { mode: 'idle', deadline: null, remainingMs: 0, label: '', blockId: null };
                let tickHandle = null;

                const pad2 = (n) => String(n).padStart(2, '0');
                const dateToHHMM = (d) => `${pad2(d.getHours())}:${pad2(d.getMinutes())}`;

                const loadState = () => {
                    try {
                        const raw = localStorage.getItem(STORAGE_KEY);
                        if (!raw) return null;
                        const parsed = JSON.parse(raw);
                        if (!parsed || typeof parsed !== 'object') return null;
                        return parsed;
                    } catch {
                        return null;
                    }
                };

                const saveState = () => {
                    try {
                        if (state.mode === 'idle') {
                            localStorage.removeItem(STORAGE_KEY);
                        } else {
                            localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
                        }
                    } catch {
                        /* storage may be disabled — proceed without persistence */
                    }
                };

                const readInputDuration = () => {
                    const days = Math.max(0, Math.floor(Number(daysInput.value) || 0));
                    const hours = Math.max(0, Math.floor(Number(hoursInput.value) || 0));
                    const minutes = Math.max(0, Math.floor(Number(minutesInput.value) || 0));
                    const seconds = Math.max(0, Math.floor(Number(secondsInput.value) || 0));
                    return ((days * 24 + hours) * 60 + minutes) * 60 * 1000 + seconds * 1000;
                };

                const formatDuration = (ms) => {
                    const totalSeconds = Math.max(0, Math.ceil(ms / 1000));
                    const days = Math.floor(totalSeconds / 86400);
                    const hours = Math.floor((totalSeconds % 86400) / 3600);
                    const minutes = Math.floor((totalSeconds % 3600) / 60);
                    const seconds = totalSeconds % 60;
                    const time = `${pad(hours)}:${pad(minutes)}:${pad(seconds)}`;
                    return days > 0 ? `${days}d ${time}` : time;
                };

                const getRemainingMs = () => {
                    if (state.mode === 'running') {
                        return Math.max(0, state.deadline - Date.now());
                    }
                    if (state.mode === 'paused' || state.mode === 'finished') {
                        return state.remainingMs;
                    }
                    return readInputDuration();
                };

                const render = () => {
                    const remaining = getRemainingMs();
                    timeEl.textContent = formatDuration(remaining);

                    statusEl.classList.remove('text-rose-400', 'text-amber-300', 'text-slate-300');
                    displayWrap.classList.remove('text-rose-400', 'animate-pulse');

                    if (state.mode === 'running') {
                        statusEl.textContent = 'LEFT';
                        statusEl.classList.add('text-slate-300');
                    } else if (state.mode === 'paused') {
                        statusEl.textContent = 'PAUSED';
                        statusEl.classList.add('text-amber-300');
                    } else if (state.mode === 'finished') {
                        statusEl.textContent = "TIME'S UP";
                        statusEl.classList.add('text-rose-400');
                        displayWrap.classList.add('text-rose-400');
                    } else {
                        statusEl.textContent = remaining > 0 ? 'READY' : 'IDLE';
                        statusEl.classList.add('text-slate-300');
                    }

                    labelDisplay.textContent = state.label || '';

                    const hasInput = readInputDuration() > 0;
                    startBtn.disabled =
                        state.mode === 'running' ||
                        state.mode === 'finished' ||
                        (state.mode === 'idle' && !hasInput);
                    startBtn.textContent = state.mode === 'paused' ? 'Resume' : 'Start';
                    pauseBtn.disabled = state.mode !== 'running';
                    resetBtn.disabled = state.mode === 'idle';
                    resetBtn.textContent = state.mode === 'finished' ? 'Stop' : 'Reset';

                    const lock = state.mode === 'running' || state.mode === 'paused';
                    for (const el of numericInputs) el.disabled = lock;
                    labelInput.disabled = lock;
                };

                const stopTicking = () => {
                    if (tickHandle !== null) {
                        clearInterval(tickHandle);
                        tickHandle = null;
                    }
                };

                const tick = () => {
                    if (state.mode !== 'running') {
                        stopTicking();
                        return;
                    }
                    if (state.deadline - Date.now() <= 0) {
                        if (window.ChronoBlocks && state.blockId) {
                            window.ChronoBlocks.update(state.blockId, {
                                status: 'completed',
                                end: dateToHHMM(new Date()),
                            });
                        }
                        state = { mode: 'finished', deadline: null, remainingMs: 0, label: state.label, blockId: state.blockId };
                        saveState();
                        stopTicking();
                        startChimeLoop();
                        render();
                        return;
                    }
                    render();
                };

                const startTicking = () => {
                    stopTicking();
                    tickHandle = setInterval(tick, 250);
                };

                const ONE_HOUR_MS = 60 * 60 * 1000;
                const ccErrorEl = document.querySelector('[data-cc-error]');
                const showCcError = (msg) => {
                    if (!ccErrorEl) return;
                    ccErrorEl.textContent = msg;
                    ccErrorEl.classList.remove('hidden');
                };
                const clearCcError = () => {
                    if (ccErrorEl) ccErrorEl.classList.add('hidden');
                };

                const handleStart = () => {
                    ensureAudio();
                    let durationMs;
                    let label;
                    let blockId = state.blockId;

                    if (state.mode === 'paused') {
                        durationMs = state.remainingMs;
                        label = state.label;
                        if (window.ChronoBlocks && blockId) {
                            const newEnd = new Date(Date.now() + durationMs);
                            window.ChronoBlocks.update(blockId, {
                                status: 'active',
                                end: dateToHHMM(newEnd),
                            });
                        }
                    } else {
                        durationMs = readInputDuration();
                        if (durationMs <= 0) return;
                        if (durationMs > ONE_HOUR_MS) {
                            showCcError('Custom countdown is capped at 1 hour. Reduce the inputs and try again.');
                            return;
                        }
                        clearCcError();
                        label = (labelInput.value || '').trim();
                        if (window.ChronoBlocks) {
                            const startDate = new Date();
                            const endDate = new Date(startDate.getTime() + durationMs);
                            const block = window.ChronoBlocks.add({
                                source: 'countdown',
                                start: dateToHHMM(startDate),
                                end: dateToHHMM(endDate),
                                durationMs,
                                label: label || 'Custom countdown',
                                status: 'active',
                            });
                            blockId = block.id;
                        }
                    }

                    if (durationMs <= 0) return;
                    state = {
                        mode: 'running',
                        deadline: Date.now() + durationMs,
                        remainingMs: 0,
                        label,
                        blockId,
                    };
                    saveState();
                    startTicking();
                    render();
                };

                const handlePause = () => {
                    if (state.mode !== 'running') return;
                    if (window.ChronoBlocks && state.blockId) {
                        window.ChronoBlocks.update(state.blockId, { status: 'paused' });
                    }
                    state = {
                        mode: 'paused',
                        deadline: null,
                        remainingMs: Math.max(0, state.deadline - Date.now()),
                        label: state.label,
                        blockId: state.blockId,
                    };
                    saveState();
                    stopTicking();
                    render();
                };

                const handleReset = () => {
                    const wasUncompleted = state.mode === 'running' || state.mode === 'paused';
                    stopChimeLoop();
                    if (window.ChronoBlocks && state.blockId && wasUncompleted) {
                        window.ChronoBlocks.remove(state.blockId);
                    }
                    state = { mode: 'idle', deadline: null, remainingMs: 0, label: '', blockId: null };
                    saveState();
                    stopTicking();
                    render();
                };

                startBtn.addEventListener('click', handleStart);
                pauseBtn.addEventListener('click', handlePause);
                resetBtn.addEventListener('click', handleReset);

                for (const el of numericInputs) {
                    el.addEventListener('input', () => {
                        clearCcError();
                        if (state.mode === 'idle') render();
                    });
                }
                labelInput?.addEventListener('input', clearCcError);

                const stored = loadState();
                if (stored && stored.mode === 'running' && typeof stored.deadline === 'number') {
                    if (stored.deadline - Date.now() <= 0) {
                        if (window.ChronoBlocks && stored.blockId) {
                            window.ChronoBlocks.update(stored.blockId, {
                                status: 'completed',
                                end: dateToHHMM(new Date(stored.deadline)),
                            });
                        }
                        state = { mode: 'finished', deadline: null, remainingMs: 0, label: stored.label || '', blockId: stored.blockId || null };
                        saveState();
                    } else {
                        state = {
                            mode: 'running',
                            deadline: stored.deadline,
                            remainingMs: 0,
                            label: stored.label || '',
                            blockId: stored.blockId || null,
                        };
                        startTicking();
                    }
                } else if (stored && stored.mode === 'paused' && typeof stored.remainingMs === 'number') {
                    state = {
                        mode: 'paused',
                        deadline: null,
                        remainingMs: Math.max(0, stored.remainingMs),
                        label: stored.label || '',
                        blockId: stored.blockId || null,
                    };
                } else if (stored && stored.mode === 'finished') {
                    state = { mode: 'finished', deadline: null, remainingMs: 0, label: stored.label || '', blockId: stored.blockId || null };
                }

                render();
            })();
        </script>

        <script>
            (() => {
                const modal = document.getElementById('hourly_modal');
                const fromEl = modal?.querySelector('[data-hourly-from]');
                const toEl = modal?.querySelector('[data-hourly-to]');
                const inputEl = document.getElementById('hourly_modal_input');
                const saveBtn = document.getElementById('hourly_modal_save');
                const skipBtn = document.getElementById('hourly_modal_skip');
                if (!modal || !fromEl || !toEl || !inputEl || !saveBtn || !skipBtn) return;

                const PROMPTED_KEY = 'chrono.hourlyPrompted.v1';
                const pad = (n) => String(n).padStart(2, '0');
                const formatTime12 = (date) => {
                    const h = date.getHours();
                    const m = date.getMinutes();
                    const period = h >= 12 ? 'PM' : 'AM';
                    const hour12 = h === 0 ? 12 : (h > 12 ? h - 12 : h);
                    return `${hour12}:${pad(m)} ${period}`;
                };
                const dateToHHMM = (d) => `${pad(d.getHours())}:${pad(d.getMinutes())}`;
                const hourKeyFor = (d) =>
                    `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}-${pad(d.getHours())}`;

                const loadPrompted = () => {
                    try {
                        const raw = localStorage.getItem(PROMPTED_KEY);
                        return new Set(raw ? JSON.parse(raw) : []);
                    } catch {
                        return new Set();
                    }
                };
                const markPrompted = (hourKey) => {
                    try {
                        const set = loadPrompted();
                        set.add(hourKey);
                        const arr = [...set];
                        if (arr.length > 240) arr.splice(0, arr.length - 240);
                        localStorage.setItem(PROMPTED_KEY, JSON.stringify(arr));
                    } catch {}
                };

                let currentKey = null;
                let currentStart = null;
                let currentEnd = null;
                let modalOpen = false;

                const onKey = (e) => {
                    if (e.key === 'Escape') {
                        e.preventDefault();
                        handleSkip();
                    } else if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                        e.preventDefault();
                        handleSave();
                    }
                };

                const closeModal = () => {
                    modal.classList.remove('flex');
                    modal.classList.add('hidden');
                    modal.setAttribute('aria-hidden', 'true');
                    modalOpen = false;
                    currentKey = null;
                    currentStart = null;
                    currentEnd = null;
                    document.removeEventListener('keydown', onKey);
                };

                const openModal = (start, end, hourKey) => {
                    if (modalOpen) return;
                    currentKey = hourKey;
                    currentStart = start;
                    currentEnd = end;
                    fromEl.textContent = formatTime12(start);
                    toEl.textContent = formatTime12(end);
                    inputEl.value = '';
                    saveBtn.disabled = true;
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                    modal.setAttribute('aria-hidden', 'false');
                    modalOpen = true;
                    setTimeout(() => inputEl.focus(), 50);
                    document.addEventListener('keydown', onKey);
                };

                const handleSkip = () => {
                    if (currentKey) markPrompted(currentKey);
                    closeModal();
                };

                const handleSave = () => {
                    const text = inputEl.value.trim();
                    if (!text) return;
                    if (window.ChronoBlocks && currentStart && currentEnd) {
                        window.ChronoBlocks.add({
                            source: 'manual',
                            start: dateToHHMM(currentStart),
                            end: dateToHHMM(currentEnd),
                            durationMs: currentEnd.getTime() - currentStart.getTime(),
                            label: text,
                            status: 'completed',
                        });
                    }
                    if (currentKey) markPrompted(currentKey);
                    closeModal();
                };

                saveBtn.addEventListener('click', handleSave);
                skipBtn.addEventListener('click', handleSkip);
                inputEl.addEventListener('input', () => {
                    saveBtn.disabled = inputEl.value.trim() === '';
                });

                const checkPrompt = () => {
                    if (modalOpen) return;
                    const now = new Date();
                    const prevHourEnd = new Date(
                        now.getFullYear(),
                        now.getMonth(),
                        now.getDate(),
                        now.getHours(),
                        0, 0, 0
                    );
                    if (prevHourEnd.getTime() > now.getTime()) return;
                    const prevHourStart = new Date(prevHourEnd.getTime() - 60 * 60 * 1000);
                    const hourKey = hourKeyFor(prevHourStart);
                    if (loadPrompted().has(hourKey)) return;
                    openModal(prevHourStart, prevHourEnd, hourKey);
                };

                setTimeout(checkPrompt, 5000);
                const now = new Date();
                const msUntilNextMinute = 60000 - (now.getTime() % 60000);
                setTimeout(() => {
                    checkPrompt();
                    setInterval(checkPrompt, 60000);
                }, msUntilNextMinute);
            })();
        </script>

        <script>
            (() => {
                const BLOCKS_KEY = 'chrono.timeBlocks.v1';
                const GOAL_KEY_PREFIX = 'chrono.todayGoal.';
                const cfg = window.ChronoDashboardConfig || {};
                const endTime = cfg.endTime || '22:00';
                const wakeTime = cfg.wakeTime || '07:00';

                const pad = (n) => String(n).padStart(2, '0');
                const localDateString = (d = new Date()) =>
                    `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
                const hhmmToMins = (hhmm) => {
                    const [h, m] = hhmm.split(':').map(Number);
                    return h * 60 + m;
                };
                const formatDuration = (ms) => {
                    const totalMin = Math.max(0, Math.round(ms / 60000));
                    if (totalMin === 0) return '0m';
                    if (totalMin < 60) return `${totalMin}m`;
                    const h = Math.floor(totalMin / 60);
                    const m = totalMin % 60;
                    return m === 0 ? `${h}h` : `${h}h ${m}m`;
                };
                const formatHours = (ms) => {
                    const hours = ms / 3600000;
                    if (hours < 1) return `${Math.round(ms / 60000)}m`;
                    if (hours < 10) return `${hours.toFixed(1)}h`;
                    return `${Math.round(hours)}h`;
                };
                const escapeHtml = (str) => String(str ?? '').replace(/[&<>"']/g, (c) => ({
                    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
                }[c]));

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

                const startOfWeek = (d) => {
                    const r = new Date(d.getFullYear(), d.getMonth(), d.getDate());
                    const day = r.getDay();
                    const diff = day === 0 ? 6 : day - 1;
                    r.setDate(r.getDate() - diff);
                    return r;
                };
                const startOfMonth = (d) => new Date(d.getFullYear(), d.getMonth(), 1);
                const startOfYear = (d) => new Date(d.getFullYear(), 0, 1);
                const endOfWeek = (d) => {
                    const s = startOfWeek(d);
                    return new Date(s.getFullYear(), s.getMonth(), s.getDate() + 7);
                };
                const endOfMonth = (d) => new Date(d.getFullYear(), d.getMonth() + 1, 1);
                const endOfYear = (d) => new Date(d.getFullYear() + 1, 0, 1);
                const formatRange = (start, endExclusive) => {
                    const opt = { month: 'short', day: 'numeric' };
                    const lastDay = new Date(endExclusive.getTime() - 1);
                    return `${start.toLocaleDateString('en-US', opt)} – ${lastDay.toLocaleDateString('en-US', opt)}`;
                };

                // Today's goal — per-date persistence + done checkbox
                const goalInput = document.getElementById('todays_goal_input');
                const goalDone = document.getElementById('todays_goal_done');
                const goalKeyFor = (date) => `${GOAL_KEY_PREFIX}${date}`;

                const loadGoal = () => {
                    try {
                        const raw = localStorage.getItem(goalKeyFor(localDateString()));
                        return raw ? JSON.parse(raw) : { text: '', done: false };
                    } catch {
                        return { text: '', done: false };
                    }
                };
                const saveGoal = () => {
                    try {
                        localStorage.setItem(goalKeyFor(localDateString()), JSON.stringify({
                            text: goalInput?.value || '',
                            done: !!goalDone?.checked,
                        }));
                    } catch {}
                };
                const applyDoneStyle = () => {
                    if (!goalInput || !goalDone) return;
                    if (goalDone.checked) {
                        goalInput.classList.add('line-through', 'opacity-60');
                    } else {
                        goalInput.classList.remove('line-through', 'opacity-60');
                    }
                };

                if (goalInput && goalDone) {
                    const stored = loadGoal();
                    goalInput.value = stored.text || '';
                    goalDone.checked = !!stored.done;
                    applyDoneStyle();
                    goalInput.addEventListener('input', saveGoal);
                    goalDone.addEventListener('change', () => {
                        saveGoal();
                        applyDoneStyle();
                    });
                }

                // DOM refs for stats
                const todayDateEl = document.querySelector('[data-today-date]');
                const loggedTodayEl = document.querySelector('[data-logged-today]');
                const loggedCountEl = document.querySelector('[data-logged-count]');
                const unloggedTodayEl = document.querySelector('[data-unlogged-today]');
                const unloggedContextEl = document.querySelector('[data-unlogged-context]');
                const topBlocksEl = document.querySelector('[data-top-blocks]');
                const last7DaysEl = document.querySelector('[data-last-7-days]');
                const periodSections = {
                    week: document.querySelector('[data-period-section="week"]'),
                    month: document.querySelector('[data-period-section="month"]'),
                    year: document.querySelector('[data-period-section="year"]'),
                };

                const updatePeriod = (section, startDate, endDate, now, blocks) => {
                    if (!section) return;
                    const passedMs = Math.max(0, now.getTime() - startDate.getTime());
                    const totalMs = endDate.getTime() - startDate.getTime();
                    const leftMs = Math.max(0, endDate.getTime() - now.getTime());
                    const startKey = localDateString(startDate);
                    const endKey = localDateString(endDate);
                    const productiveMs = blocks
                        .filter((b) => b.status === 'completed' && b.date && b.date >= startKey && b.date < endKey)
                        .reduce((s, b) => s + (b.durationMs || 0), 0);
                    const ratio = passedMs > 0
                        ? Math.min(100, Math.round((productiveMs / passedMs) * 100))
                        : 0;
                    const progressPct = totalMs > 0
                        ? Math.min(100, (passedMs / totalMs) * 100)
                        : 0;

                    const range = section.querySelector('[data-period-range]');
                    const passed = section.querySelector('[data-period-passed]');
                    const left = section.querySelector('[data-period-left]');
                    const productive = section.querySelector('[data-period-productive]');
                    const ratioEl = section.querySelector('[data-period-ratio]');
                    const progressEl = section.querySelector('[data-period-progress]');

                    if (range) range.textContent = formatRange(startDate, endDate);
                    if (passed) passed.textContent = formatHours(passedMs);
                    if (left) left.textContent = formatHours(leftMs);
                    if (productive) productive.textContent = productiveMs > 0 ? formatHours(productiveMs) : '—';
                    if (ratioEl) ratioEl.textContent = productiveMs > 0 ? `${ratio}%` : '—';
                    if (progressEl) progressEl.style.width = `${progressPct.toFixed(2)}%`;
                };

                const renderTopBlocks = (todayBlocks) => {
                    if (!topBlocksEl) return;
                    if (todayBlocks.length === 0) {
                        topBlocksEl.innerHTML = '<li class="text-slate-500">No blocks logged yet today.</li>';
                        return;
                    }
                    const top = todayBlocks
                        .filter((b) => b.status === 'completed')
                        .slice()
                        .sort((a, b) => (b.durationMs || 0) - (a.durationMs || 0))
                        .slice(0, 3);
                    if (top.length === 0) {
                        topBlocksEl.innerHTML = '<li class="text-slate-500">No completed blocks yet today.</li>';
                        return;
                    }
                    topBlocksEl.innerHTML = top.map((b) =>
                        `<li class="text-slate-300"><span class="text-slate-100 font-medium">${escapeHtml(formatDuration(b.durationMs || 0))}</span> · ${escapeHtml(b.label || 'Time block')}</li>`
                    ).join('');
                };

                const renderLast7Days = (blocks, now) => {
                    if (!last7DaysEl) return;
                    const todayKey = localDateString(now);
                    const tiles = [];
                    for (let i = 6; i >= 0; i--) {
                        const d = new Date(now);
                        d.setDate(d.getDate() - i);
                        const dateStr = localDateString(d);
                        const totalMs = blocks
                            .filter((b) => b.status === 'completed' && b.date === dateStr)
                            .reduce((s, b) => s + (b.durationMs || 0), 0);
                        const dayName = d.toLocaleDateString('en-US', { weekday: 'short' });
                        const isToday = dateStr === todayKey;
                        const cls = isToday
                            ? 'border-[var(--chrono-blue)] bg-slate-800/60'
                            : 'border-slate-800/60 bg-slate-900/40';
                        const valueCls = totalMs > 0 ? 'text-slate-100' : 'text-slate-600';
                        tiles.push(
                            `<div class="rounded-lg border ${cls} p-2 text-center">` +
                            `<div class="text-[0.65rem] uppercase tracking-wider text-slate-400">${escapeHtml(dayName)}</div>` +
                            `<div class="text-[0.65rem] text-slate-500">${d.getDate()}</div>` +
                            `<div class="mt-1 text-sm ${valueCls}">${totalMs > 0 ? escapeHtml(formatDuration(totalMs)) : '—'}</div>` +
                            '</div>'
                        );
                    }
                    last7DaysEl.innerHTML = tiles.join('');
                };

                const updateAll = () => {
                    const now = new Date();
                    const todayStr = localDateString(now);
                    const blocks = loadBlocks();

                    if (todayDateEl) {
                        todayDateEl.textContent = now.toLocaleDateString('en-US', {
                            weekday: 'long', month: 'long', day: 'numeric', year: 'numeric',
                        });
                    }

                    const todayBlocks = blocks.filter((b) => b.date === todayStr);
                    const completedToday = todayBlocks.filter((b) => b.status === 'completed');
                    const loggedTodayMs = completedToday.reduce((s, b) => s + (b.durationMs || 0), 0);
                    if (loggedTodayEl) loggedTodayEl.textContent = formatDuration(loggedTodayMs);
                    if (loggedCountEl) {
                        const n = completedToday.length;
                        loggedCountEl.textContent = `${n} ${n === 1 ? 'block' : 'blocks'}`;
                    }

                    const wakeMins = hhmmToMins(wakeTime);
                    const endMins = hhmmToMins(endTime);
                    const nowMins = now.getHours() * 60 + now.getMinutes();
                    let elapsedActiveMins;
                    let context;
                    if (nowMins < wakeMins) {
                        elapsedActiveMins = 0;
                        context = 'before wake-up';
                    } else if (nowMins > endMins) {
                        elapsedActiveMins = endMins - wakeMins;
                        context = 'past end of day';
                    } else {
                        elapsedActiveMins = nowMins - wakeMins;
                        context = 'since wake-up';
                    }
                    const loggedTodayMins = Math.round(loggedTodayMs / 60000);
                    const unloggedMins = Math.max(0, elapsedActiveMins - loggedTodayMins);
                    if (unloggedTodayEl) unloggedTodayEl.textContent = formatDuration(unloggedMins * 60000);
                    if (unloggedContextEl) unloggedContextEl.textContent = context;

                    renderTopBlocks(todayBlocks);

                    updatePeriod(periodSections.week, startOfWeek(now), endOfWeek(now), now, blocks);
                    updatePeriod(periodSections.month, startOfMonth(now), endOfMonth(now), now, blocks);
                    updatePeriod(periodSections.year, startOfYear(now), endOfYear(now), now, blocks);

                    renderLast7Days(blocks, now);
                };

                window.addEventListener('chrono:blocks:changed', updateAll);
                setInterval(updateAll, 30000);
                updateAll();
            })();
        </script>
    @endpush
@endsection
