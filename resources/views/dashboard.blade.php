@extends('layouts.app')

@section('page_title', auth()->check() ? 'Dashboard' : 'Track Your Time')

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
        $signupAt = $user?->created_at?->copy()->setTimezone($timezone);
        $signupTimestamp = $signupAt?->toIso8601String();
        $signupDateLabel = $signupAt?->format('M j, Y');
    @endphp
    @auth
        <div class="relative overflow-hidden rounded-2xl border border-slate-800/60 bg-[radial-gradient(circle_at_top,_rgba(0,224,255,0.15),_transparent_45%)] p-8 mb-10">
            <div class="absolute -right-24 -top-24 h-56 w-56 rounded-full bg-[radial-gradient(circle,_rgba(255,107,26,0.35),_transparent_70%)] blur-2xl"></div>
            <div class="relative">
                <h1 class="font-display text-3xl tracking-[0.3em] uppercase">Dashboard</h1>
                <p class="text-slate-300 text-sm mt-2">Track today, close the loops, and beat the deadline.</p>
            </div>
        </div>
    @else
        @include('partials.guest-hero')
    @endauth

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
                    <div class="mt-2 font-digital text-2xl chrono-glow-blue chrono-pulse">
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
            <div class="mt-2 text-xs space-y-1 hidden" data-period-note></div>
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mt-4">
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
                    <div class="mt-1 text-lg text-emerald-300" data-period-productive>—</div>
                </div>
                <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Wasted</div>
                    <div class="mt-1 text-lg text-rose-300" data-period-wasted>—</div>
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
            <div class="mt-2 text-xs space-y-1 hidden" data-period-note></div>
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mt-4">
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
                    <div class="mt-1 text-lg text-emerald-300" data-period-productive>—</div>
                </div>
                <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Wasted</div>
                    <div class="mt-1 text-lg text-rose-300" data-period-wasted>—</div>
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
            <div class="mt-2 text-xs space-y-1 hidden" data-period-note></div>
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mt-4">
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
                    <div class="mt-1 text-lg text-emerald-300" data-period-productive>—</div>
                </div>
                <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Wasted</div>
                    <div class="mt-1 text-lg text-rose-300" data-period-wasted>—</div>
                </div>
                <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Utilization</div>
                    <div class="mt-1 text-lg text-slate-100" data-period-ratio>—</div>
                </div>
            </div>
        </section>

        <section id="custom-countdown" class="chrono-panel rounded-2xl p-6 md:p-8">
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

            <div data-edit-banner
                class="hidden mt-6 rounded-lg border border-[var(--chrono-blue)]/40 bg-[var(--chrono-blue)]/10 px-3 py-2 text-sm text-[var(--chrono-blue)]">
                Editing block <span data-edit-banner-range class="font-semibold"></span> — your changes will replace the original. Click <strong>Cancel</strong> to discard.
            </div>
            <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs uppercase tracking-[0.2em] text-slate-400 mb-1" for="block_start_display">Start</label>
                    <input id="block_start_display" type="text" inputmode="numeric" placeholder="9:00 AM"
                        data-time12
                        data-time12-hidden-id="block_start_value"
                        data-time12-error-id="block_start_error"
                        data-time12-label="Start time"
                        data-time12-example="9:00 AM"
                        data-time12-group="block_form"
                        class="w-full rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 text-slate-100">
                    <input id="block_start_value" type="hidden" value="">
                    <p id="block_start_error" class="mt-1 text-xs text-rose-400 hidden" aria-live="polite"></p>
                </div>
                <div>
                    <label class="block text-xs uppercase tracking-[0.2em] text-slate-400 mb-1" for="block_end_display">End</label>
                    <input id="block_end_display" type="text" inputmode="numeric" placeholder="10:00 AM"
                        data-time12
                        data-time12-hidden-id="block_end_value"
                        data-time12-error-id="block_end_error"
                        data-time12-label="End time"
                        data-time12-example="10:00 AM"
                        data-time12-group="block_form"
                        class="w-full rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 text-slate-100">
                    <input id="block_end_value" type="hidden" value="">
                    <p id="block_end_error" class="mt-1 text-xs text-rose-400 hidden" aria-live="polite"></p>
                </div>
                <div class="md:col-span-1">
                    <label class="block text-xs uppercase tracking-[0.2em] text-slate-400 mb-1" for="block_reason_input">Reason / Activity</label>
                    <textarea id="block_reason_input" rows="3" maxlength="500"
                        placeholder="What did you do? Add as much detail as you'd like — auto-grows as you type."
                        style="color-scheme: dark"
                        class="w-full rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 text-slate-100 placeholder-slate-500 leading-relaxed resize-none min-h-[5rem] overflow-hidden focus:border-[var(--chrono-blue)] focus:outline-none focus:ring-1 focus:ring-[var(--chrono-blue)]/40"></textarea>
                    <p class="mt-1 text-[0.65rem] text-slate-500"><span data-reason-count>0</span> / 500 characters</p>
                </div>
            </div>
            <p class="mt-3 text-xs text-slate-400">
                Times use <strong>12-hour format with AM/PM</strong> (e.g. <span class="text-slate-300">9:00 AM</span>, <span class="text-slate-300">2:30pm</span>, <span class="text-slate-300">11 p.m.</span>). 24-hour input is not accepted. <strong>Start must be before End</strong>, end can be at most <strong>1 hour ahead</strong> of now, and blocks <strong>can't overlap</strong> each other.
            </p>
            <p class="mt-1 text-xs text-slate-500">
                Words like <span class="text-rose-300/80">wasted</span>, <span class="text-rose-300/80">scrolling</span>, <span class="text-rose-300/80">youtube</span>, <span class="text-rose-300/80">social media</span>, <span class="text-rose-300/80">procrastinating</span> auto-flag the block as <span class="text-rose-300/80">Wasted</span> — even when run together (e.g. <span class="text-rose-300/80">seenyoutube</span>, <span class="text-rose-300/80">sotimegotwasted</span>). Click the chip in the table to flip a classification.
            </p>
            <div class="mt-4 flex flex-wrap items-center gap-2">
                <button id="block_save_button" type="button" data-time12-gate="block_form"
                    class="rounded-lg bg-[var(--chrono-orange)] text-slate-950 font-semibold px-4 py-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    Log block
                </button>
                <button id="block_cancel_button" type="button"
                    class="hidden rounded-lg border border-slate-600 px-4 py-2 text-sm text-slate-200">
                    Cancel
                </button>
                <p id="block_form_error" class="text-xs text-rose-400 hidden" aria-live="polite"></p>
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

    <div id="confirm_modal" role="dialog" aria-modal="true" aria-labelledby="confirm_modal_title" aria-hidden="true"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 backdrop-blur-sm p-4">
        <div class="w-full max-w-md rounded-2xl border border-slate-700/60 bg-[var(--chrono-bg)] p-6 shadow-2xl">
            <h3 id="confirm_modal_title"
                class="font-display text-base uppercase tracking-[0.2em] text-slate-100"
                data-confirm-title>Confirm</h3>
            <div class="mt-3 text-sm text-slate-300 space-y-1" data-confirm-body></div>
            <div class="mt-5 flex justify-end gap-2">
                <button type="button" data-confirm-cancel
                    class="rounded-lg border border-slate-600 hover:border-slate-400 px-4 py-2 text-sm text-slate-200">Cancel</button>
                <button type="button" data-confirm-ok
                    class="rounded-lg px-4 py-2 text-sm font-semibold">Confirm</button>
            </div>
        </div>
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
                signupTimestamp: @json($signupTimestamp),
                signupDateLabel: @json($signupDateLabel),
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

                // Single-word triggers — scored at the token level so they catch
                // concatenated forms like "seenyoutube" or "sotimegotwasted".
                const WASTED_TOKENS = [
                    'wasted', 'waste', 'wasting', 'wastes',
                    'scroll', 'scrolling', 'scrolled', 'scrolls',
                    'doomscroll', 'doomscrolling',
                    'procrastinate', 'procrastinating', 'procrastinated', 'procrastination',
                    'distract', 'distracted', 'distracting', 'distraction',
                    'idle', 'idling', 'idled',
                    'binge', 'binging', 'binged', 'bingewatch',
                    'timepass',
                    'mindless', 'mindlessly',
                    'unproductive',
                    'lazy', 'laziness',
                    'youtube', 'instagram', 'tiktok', 'twitter', 'reddit',
                    'facebook', 'snapchat', 'netflix',
                    'aimless', 'aimlessly',
                    'useless',
                ];
                // Multi-word phrases — scanned against the whole lowercased label.
                const WASTED_PHRASES = [
                    'social media',
                    'time pass',
                    'binge watch',
                    'binge-watch',
                ];
                const SCORE_THRESHOLD = 2;

                const scoreTokenAgainstKeyword = (token, kw) => {
                    if (token === kw) return 3;
                    if (token.length > kw.length && (token.startsWith(kw) || token.endsWith(kw))) return 2;
                    if (token.includes(kw)) return 1;
                    return 0;
                };

                const categorizeLabel = (label) => {
                    if (!label) return 'productive';
                    const text = String(label).toLowerCase();
                    let score = 0;

                    // Phrase pass — works on raw text since multi-word phrases
                    // already include their own internal word boundaries.
                    for (const phrase of WASTED_PHRASES) {
                        if (text.includes(phrase)) {
                            score += 3;
                            if (score >= SCORE_THRESHOLD) return 'wasted';
                        }
                    }

                    // Token pass — splits on every non-alphanumeric boundary, then
                    // each token contributes at most one match per keyword. The
                    // strongest keyword match for a token wins, so a token like
                    // 'sotimegotwasted' scores once for "wasted" rather than three
                    // times for "wasted"/"waste"/"wasting".
                    const tokens = text.split(/[^a-z0-9]+/).filter((t) => t.length > 0);
                    for (const token of tokens) {
                        let best = 0;
                        for (const kw of WASTED_TOKENS) {
                            const s = scoreTokenAgainstKeyword(token, kw);
                            if (s > best) best = s;
                            if (best === 3) break;
                        }
                        score += best;
                        if (score >= SCORE_THRESHOLD) return 'wasted';
                    }

                    return 'productive';
                };

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

                // Migration / re-classification: stamp date-less blocks as today, and re-run
                // the classifier on every block whose category was *not* manually overridden
                // (categoryManual !== true). This way improvements to the algorithm propagate
                // to existing blocks, while user clicks on the chip stick.
                (() => {
                    const blocks = loadBlocks();
                    let dirty = false;
                    const today = localDateString();
                    for (const block of blocks) {
                        if (!block.date) {
                            block.date = today;
                            dirty = true;
                        }
                        if (block.categoryManual !== true) {
                            const next = categorizeLabel(block.label);
                            if (block.category !== next) {
                                block.category = next;
                                dirty = true;
                            }
                        } else if (!block.category) {
                            block.category = 'productive';
                            dirty = true;
                        }
                    }
                    if (dirty) saveBlocks(blocks);
                })();

                const render = () => {
                    // Strict calendar-day scope: only blocks whose date stamp matches the
                    // browser's current local date. A block logged at 11:30 PM is dated to
                    // that calendar day and disappears from this table once the clock crosses
                    // midnight; a block created at 12:30 AM is tagged to the new day. The
                    // 10 PM sleep / 6 AM wake schedule does not affect this — calendar day
                    // is the boundary.
                    const todayKey = localDateString();
                    const blocks = loadBlocks()
                        .filter((b) => b.date === todayKey)
                        .slice()
                        .sort((a, b) => {
                            const aMin = hhmmToMinutes(a.start || '00:00');
                            const bMin = hhmmToMinutes(b.start || '00:00');
                            if (aMin !== bMin) return aMin - bMin;
                            return (a.id || '').localeCompare(b.id || '');
                        });
                    tbody.innerHTML = '';

                    if (blocks.length === 0) {
                        const tr = document.createElement('tr');
                        tr.innerHTML = '<td class="py-3 text-slate-500" colspan="5">No blocks logged for today yet — start a countdown or log one manually.</td>';
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

                        const isWasted = block.category === 'wasted';
                        const categoryChip = `<button type="button" data-block-category` +
                            ` class="ml-2 inline-flex items-center rounded-full px-2 py-0.5 text-[0.65rem] uppercase tracking-wider hover:opacity-80 ${isWasted ? 'bg-rose-500/15 text-rose-300 border border-rose-500/30' : 'bg-slate-700/40 text-slate-400 border border-slate-600/40'}"` +
                            ` title="Click to toggle productive / wasted">${isWasted ? 'Wasted' : 'Productive'}</button>`;

                        const editButton = block.status === 'completed'
                            ? '<button class="text-[var(--chrono-blue)]" data-block-edit>Edit</button>'
                            : '';

                        tr.innerHTML = `
                            <td class="py-3">${escapeHtml(formatTime12(block.start))}</td>
                            <td class="py-3">${endText}</td>
                            <td class="py-3">${escapeHtml(msToDurationLabel(block.durationMs))}</td>
                            <td class="py-3">${badge}${escapeHtml(labelText)}${categoryChip}</td>
                            <td class="py-3">
                                <div class="flex gap-2">
                                    ${editButton}
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
                        category: data.category || categorizeLabel(data.label),
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

                const ONE_HOUR_MS = 60 * 60 * 1000;
                const startDisplay = document.getElementById('block_start_display');
                const endDisplay = document.getElementById('block_end_display');
                const startHidden = document.getElementById('block_start_value');
                const endHidden = document.getElementById('block_end_value');
                const reasonInput = document.getElementById('block_reason_input');
                const saveBtn = document.getElementById('block_save_button');
                const cancelBtn = document.getElementById('block_cancel_button');
                const blockFormError = document.getElementById('block_form_error');

                const showBlockFormError = (msg) => {
                    if (!blockFormError) return;
                    blockFormError.textContent = msg;
                    blockFormError.classList.remove('hidden');
                };
                const clearBlockFormError = () => {
                    if (blockFormError) blockFormError.classList.add('hidden');
                };

                for (const el of [startDisplay, endDisplay, reasonInput]) {
                    el?.addEventListener('input', clearBlockFormError);
                }

                // Reason textarea auto-grow + character counter.
                const reasonCount = document.querySelector('[data-reason-count]');
                const autoGrowReason = () => {
                    if (!reasonInput) return;
                    reasonInput.style.height = 'auto';
                    reasonInput.style.height = reasonInput.scrollHeight + 'px';
                    if (reasonCount) reasonCount.textContent = String(reasonInput.value.length);
                };
                reasonInput?.addEventListener('input', autoGrowReason);

                // Edit-mode banner refs.
                const editBanner = document.querySelector('[data-edit-banner]');
                const editBannerRange = document.querySelector('[data-edit-banner-range]');

                // ─── Custom confirm modal (replaces native confirm()) ──────────────
                const confirmModal = document.getElementById('confirm_modal');
                const confirmTitleEl = confirmModal?.querySelector('[data-confirm-title]');
                const confirmBodyEl = confirmModal?.querySelector('[data-confirm-body]');
                const confirmOkBtn = confirmModal?.querySelector('[data-confirm-ok]');
                const confirmCancelBtn = confirmModal?.querySelector('[data-confirm-cancel]');
                let confirmBusy = false;

                const TONES = {
                    blue: 'bg-[var(--chrono-blue)] text-slate-950 hover:opacity-90',
                    red: 'bg-rose-500 text-white hover:bg-rose-400',
                    orange: 'bg-[var(--chrono-orange)] text-slate-950 hover:opacity-90',
                };

                const showConfirmModal = ({ title, lines = [], confirmText = 'Confirm', cancelText = 'Cancel', tone = 'blue' }) => {
                    return new Promise((resolve) => {
                        // Hard fallback only if the modal markup is missing.
                        if (!confirmModal || !confirmOkBtn || !confirmCancelBtn) {
                            const text = `${title}\n\n${lines.map((l) => typeof l === 'string' ? l : l.text).join('\n')}`;
                            resolve(window.confirm(text));
                            return;
                        }
                        // Already showing a modal — drop the duplicate call without action.
                        if (confirmBusy) { resolve(false); return; }
                        confirmBusy = true;

                        confirmTitleEl.textContent = title;
                        confirmBodyEl.innerHTML = lines.map((item) => {
                            const text = typeof item === 'string' ? item : item.text;
                            const muted = typeof item === 'object' && item.muted;
                            const cls = muted ? 'class="text-slate-500"' : '';
                            return `<p ${cls}>${escapeHtml(text)}</p>`;
                        }).join('');

                        confirmOkBtn.textContent = confirmText;
                        confirmOkBtn.className = 'rounded-lg px-4 py-2 text-sm font-semibold ' + (TONES[tone] || TONES.blue);
                        confirmCancelBtn.textContent = cancelText;

                        const close = (result) => {
                            confirmModal.classList.remove('flex');
                            confirmModal.classList.add('hidden');
                            confirmModal.setAttribute('aria-hidden', 'true');
                            confirmOkBtn.removeEventListener('click', onOk);
                            confirmCancelBtn.removeEventListener('click', onCancel);
                            confirmModal.removeEventListener('click', onBackdrop);
                            document.removeEventListener('keydown', onKey);
                            confirmBusy = false;
                            resolve(result);
                        };
                        const onOk = () => close(true);
                        const onCancel = () => close(false);
                        const onBackdrop = (e) => { if (e.target === confirmModal) close(false); };
                        const onKey = (e) => {
                            if (e.key === 'Escape') { e.preventDefault(); close(false); }
                            else if (e.key === 'Enter') { e.preventDefault(); close(true); }
                        };

                        confirmOkBtn.addEventListener('click', onOk);
                        confirmCancelBtn.addEventListener('click', onCancel);
                        confirmModal.addEventListener('click', onBackdrop);
                        document.addEventListener('keydown', onKey);

                        confirmModal.classList.remove('hidden');
                        confirmModal.classList.add('flex');
                        confirmModal.setAttribute('aria-hidden', 'false');
                        setTimeout(() => confirmOkBtn.focus(), 50);
                    });
                };

                // Programmatic value-set helper: updates the visible display field and
                // fires 'input' so the time12 module reparses and refreshes the hidden value
                // and the gate state on the Save button. Also writes to the linked hidden
                // field directly so the form is correct even before the time12 module has
                // had a chance to bind its listeners (page-load race).
                const setTimeFieldFromHHMM = (display, hhmm) => {
                    if (!display) return;
                    display.value = hhmm ? formatTime12(hhmm) : '';
                    const hiddenId = display.dataset.time12HiddenId;
                    if (hiddenId) {
                        const hidden = document.getElementById(hiddenId);
                        if (hidden) hidden.value = hhmm || '';
                    }
                    display.dispatchEvent(new Event('input', { bubbles: true }));
                };

                const defaultSlots = () => {
                    const now = new Date();
                    const nowMin = now.getHours() * 60 + now.getMinutes();
                    const startMin = Math.max(0, Math.floor(nowMin / 15) * 15);
                    const endMin = Math.min(23 * 60 + 45, startMin + 60);
                    return {
                        start: `${pad(Math.floor(startMin / 60))}:${pad(startMin % 60)}`,
                        end: `${pad(Math.floor(endMin / 60))}:${pad(endMin % 60)}`,
                    };
                };

                let editingBlockId = null;

                const setFormMode = (mode, block = null) => {
                    if (mode === 'edit' && block) {
                        editingBlockId = block.id;
                        setTimeFieldFromHHMM(startDisplay, block.start);
                        setTimeFieldFromHHMM(endDisplay, block.end);
                        if (reasonInput) reasonInput.value = block.label || '';
                        if (saveBtn) saveBtn.textContent = 'Update block';
                        if (cancelBtn) cancelBtn.classList.remove('hidden');
                        if (editBanner) {
                            if (editBannerRange) {
                                editBannerRange.textContent = `${formatTime12(block.start)} – ${formatTime12(block.end)}`;
                            }
                            editBanner.classList.remove('hidden');
                        }
                        clearBlockFormError();
                        autoGrowReason();
                        startDisplay?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    } else {
                        editingBlockId = null;
                        const d = defaultSlots();
                        setTimeFieldFromHHMM(startDisplay, d.start);
                        setTimeFieldFromHHMM(endDisplay, d.end);
                        if (reasonInput) reasonInput.value = '';
                        if (saveBtn) saveBtn.textContent = 'Log block';
                        if (cancelBtn) cancelBtn.classList.add('hidden');
                        if (editBanner) editBanner.classList.add('hidden');
                        clearBlockFormError();
                        autoGrowReason();
                    }
                };

                // Find an overlapping same-date block. excludeId skips the block being edited.
                const findOverlap = (date, startMin, endMin, excludeId) => {
                    const blocks = loadBlocks();
                    for (const b of blocks) {
                        if (b.id === excludeId) continue;
                        if (b.date !== date) continue;
                        if (!b.start || !b.end) continue;
                        const bStart = hhmmToMinutes(b.start);
                        const bEnd = hhmmToMinutes(b.end);
                        if (startMin < bEnd && endMin > bStart) return b;
                    }
                    return null;
                };

                const handleSave = () => {
                    if (!window.ChronoAuthRequire?.('log a time block')) return;
                    if (!startHidden || !endHidden) return;
                    const start = startHidden.value;
                    const end = endHidden.value;
                    if (!start || !end) {
                        showBlockFormError('Enter valid Start and End times in 12-hour format.');
                        return;
                    }
                    const startMin = hhmmToMinutes(start);
                    const endMin = hhmmToMinutes(end);
                    if (endMin <= startMin) {
                        showBlockFormError(`Start time (${formatTime12(start)}) must be earlier than End time (${formatTime12(end)}).`);
                        return;
                    }
                    const durationMs = (endMin - startMin) * 60 * 1000;

                    const now = new Date();
                    const todayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime();
                    const futureLimit = now.getTime() + ONE_HOUR_MS;
                    const blockEndMs = todayStart + endMin * 60 * 1000;
                    if (blockEndMs > futureLimit) {
                        const limitDate = new Date(futureLimit);
                        showBlockFormError(`End time can be at most 1 hour ahead of now (≤ ${formatTime12(dateToHHMM(limitDate))}).`);
                        return;
                    }

                    const editingBlock = editingBlockId ? get(editingBlockId) : null;
                    const date = editingBlock?.date || localDateString();

                    const conflict = findOverlap(date, startMin, endMin, editingBlockId);
                    if (conflict) {
                        showBlockFormError(
                            `Overlaps with ${formatTime12(conflict.start)}–${formatTime12(conflict.end)} · "${conflict.label || 'Time block'}". Pick a different slot.`
                        );
                        return;
                    }

                    clearBlockFormError();
                    const label = (reasonInput?.value || '').trim() || 'Time block';

                    if (editingBlock) {
                        const updates = { start, end, durationMs, label };
                        if (!editingBlock.categoryManual) {
                            updates.category = categorizeLabel(label);
                        }
                        update(editingBlock.id, updates);
                    } else {
                        add({
                            source: 'manual',
                            start,
                            end,
                            durationMs,
                            label,
                            status: 'completed',
                        });
                    }

                    setFormMode('add');
                };

                const handleCancel = () => setFormMode('add');

                if (saveBtn) saveBtn.addEventListener('click', handleSave);
                if (cancelBtn) cancelBtn.addEventListener('click', handleCancel);

                tbody.addEventListener('click', (e) => {
                    const categoryBtn = e.target.closest('[data-block-category]');
                    if (categoryBtn) {
                        if (!window.ChronoAuthRequire?.('change a block category')) return;
                        const tr = categoryBtn.closest('tr');
                        const id = tr?.dataset.blockId;
                        if (!id) return;
                        const block = get(id);
                        if (!block) return;
                        update(id, {
                            category: block.category === 'wasted' ? 'productive' : 'wasted',
                            categoryManual: true,
                        });
                        return;
                    }

                    const editBtn = e.target.closest('[data-block-edit]');
                    if (editBtn) {
                        if (!window.ChronoAuthRequire?.('edit a time block')) return;
                        const tr = editBtn.closest('tr');
                        const id = tr?.dataset.blockId;
                        if (!id) return;
                        const block = get(id);
                        if (!block) return;
                        const range = `${formatTime12(block.start)} – ${formatTime12(block.end)}`;
                        const reasonExcerpt = (block.label || 'Time block').slice(0, 120);
                        showConfirmModal({
                            title: 'Edit this block?',
                            lines: [
                                { text: range },
                                { text: reasonExcerpt, muted: true },
                                { text: 'The form will populate with the current values. Your changes only save when you click Update block.', muted: true },
                            ],
                            confirmText: 'Edit block',
                            tone: 'blue',
                        }).then((ok) => {
                            if (ok) setFormMode('edit', block);
                        });
                        return;
                    }

                    const deleteBtn = e.target.closest('[data-block-delete]');
                    if (!deleteBtn) return;
                    if (!window.ChronoAuthRequire?.('delete a time block')) return;
                    const tr = deleteBtn.closest('tr');
                    const id = tr?.dataset.blockId;
                    if (!id) return;
                    const block = get(id);
                    const range = block ? `${formatTime12(block.start)} – ${formatTime12(block.end)}` : '';
                    const reasonExcerpt = block ? (block.label || 'Time block').slice(0, 120) : '';
                    const isLiveCountdown = block && block.source === 'countdown'
                        && (block.status === 'active' || block.status === 'paused');

                    const lines = [];
                    if (range) lines.push({ text: range });
                    if (reasonExcerpt) lines.push({ text: reasonExcerpt, muted: true });
                    lines.push({
                        text: isLiveCountdown
                            ? 'The timer will stop and the block will be deleted. This cannot be undone.'
                            : 'This cannot be undone.',
                        muted: true,
                    });

                    showConfirmModal({
                        title: isLiveCountdown ? 'Cancel and remove this countdown?' : 'Delete this block?',
                        lines,
                        confirmText: isLiveCountdown ? 'Cancel countdown' : 'Delete',
                        tone: 'red',
                    }).then((ok) => {
                        if (!ok) return;
                        if (isLiveCountdown) {
                            const resetBtn = document.querySelector('[data-cc-reset]');
                            if (resetBtn) {
                                resetBtn.click();
                                return;
                            }
                        }
                        if (id === editingBlockId) setFormMode('add');
                        remove(id);
                    });
                });

                setFormMode('add');

                // Re-run on every local-midnight crossing so a tab left open across midnight
                // rolls yesterday's blocks out of the today-table without needing a manual
                // refresh. Form defaults also reset (when not mid-edit) to the new day's
                // current quarter-hour. Reschedules itself for the next midnight.
                const scheduleMidnightRollover = () => {
                    const now = new Date();
                    const nextMidnight = new Date(
                        now.getFullYear(),
                        now.getMonth(),
                        now.getDate() + 1,
                        0, 0, 1, 0
                    );
                    setTimeout(() => {
                        render();
                        if (editingBlockId === null) setFormMode('add');
                        scheduleMidnightRollover();
                    }, Math.max(1000, nextMidnight.getTime() - now.getTime()));
                };
                scheduleMidnightRollover();

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
                // Hourly check-in only runs for signed-in users — guests would otherwise
                // be nagged every hour to log time they can't actually save.
                if (!window.ChronoAuth?.isAuthenticated) return;

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
                let signupTs = null;
                if (cfg.signupTimestamp) {
                    const d = new Date(cfg.signupTimestamp);
                    if (!isNaN(d.getTime())) signupTs = d;
                }
                const signupDateLabel = cfg.signupDateLabel || '';

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

                    const isAuthed = () => !!window.ChronoAuth?.isAuthenticated;

                    // Guests: focus shows the sign-in prompt and the field stays unfocused so
                    // the visual stays clean. Once signed in, normal save-on-input works.
                    goalInput.addEventListener('focus', (e) => {
                        if (!isAuthed()) {
                            e.target.blur();
                            window.ChronoAuthRequire?.('save your daily goal');
                        }
                    });
                    goalInput.addEventListener('input', () => {
                        if (!isAuthed()) return;
                        saveGoal();
                    });
                    goalDone.addEventListener('click', (e) => {
                        if (!isAuthed()) {
                            e.preventDefault();
                            window.ChronoAuthRequire?.('mark your goal as done');
                        }
                    });
                    goalDone.addEventListener('change', () => {
                        if (!isAuthed()) return;
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

                const calcCalendarMonths = (start, end) => {
                    let months = (end.getFullYear() - start.getFullYear()) * 12
                        + (end.getMonth() - start.getMonth());
                    if (end.getDate() < start.getDate()) months -= 1;
                    return Math.max(0, months);
                };

                const formatPreSignupSpan = (preStart, preEnd) => {
                    const ms = preEnd.getTime() - preStart.getTime();
                    if (ms <= 0) return null;
                    const days = Math.floor(ms / 86400000);
                    const weeks = Math.floor(days / 7);
                    const months = calcCalendarMonths(preStart, preEnd);
                    const parts = [];
                    if (months >= 1) parts.push(`${months} ${months === 1 ? 'month' : 'months'}`);
                    if (weeks >= 1) parts.push(`${weeks} ${weeks === 1 ? 'week' : 'weeks'}`);
                    parts.push(`${days} ${days === 1 ? 'day' : 'days'}`);
                    return parts.join(' · ');
                };

                const updatePeriod = (section, periodName, startDate, endDate, now, blocks) => {
                    if (!section) return;

                    // Signup-aware effective start: don't count pre-signup time as "passed".
                    // After the period that contains signup ends, this clamp becomes a no-op.
                    const signupClamped = signupTs && signupTs > startDate;
                    const effectiveStart = signupClamped ? signupTs : startDate;

                    const passedMs = Math.max(0, now.getTime() - effectiveStart.getTime());
                    const totalMs = Math.max(1, endDate.getTime() - effectiveStart.getTime());
                    const leftMs = Math.max(0, endDate.getTime() - now.getTime());
                    const startKey = localDateString(effectiveStart);
                    const endKey = localDateString(endDate);
                    const completedInRange = blocks.filter((b) =>
                        b.status === 'completed' && b.date && b.date >= startKey && b.date < endKey
                    );
                    const productiveMs = completedInRange
                        .filter((b) => b.category !== 'wasted')
                        .reduce((s, b) => s + (b.durationMs || 0), 0);
                    const wastedMs = completedInRange
                        .filter((b) => b.category === 'wasted')
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
                    const wastedEl = section.querySelector('[data-period-wasted]');
                    const ratioEl = section.querySelector('[data-period-ratio]');
                    const progressEl = section.querySelector('[data-period-progress]');
                    const noteEl = section.querySelector('[data-period-note]');

                    if (range) range.textContent = formatRange(effectiveStart, endDate);
                    if (passed) passed.textContent = formatHours(passedMs);
                    if (left) left.textContent = formatHours(leftMs);
                    if (productive) productive.textContent = productiveMs > 0 ? formatHours(productiveMs) : '—';
                    if (wastedEl) wastedEl.textContent = wastedMs > 0 ? formatHours(wastedMs) : '—';
                    if (ratioEl) ratioEl.textContent = productiveMs > 0 ? `${ratio}%` : '—';
                    if (progressEl) progressEl.style.width = `${progressPct.toFixed(2)}%`;
                    if (noteEl) {
                        if (signupClamped && signupDateLabel) {
                            const preLabel = formatPreSignupSpan(startDate, signupTs);
                            const mainLine = `Counting since your signup on ${signupDateLabel} — pre-signup time isn't included.`;
                            const preLine = preLabel
                                ? `Time doesn't stop for anyone — ${preLabel} of this ${periodName} passed before you joined. Make the rest count.`
                                : '';
                            noteEl.innerHTML =
                                `<p class="text-slate-500">${escapeHtml(mainLine)}</p>` +
                                (preLine ? `<p class="text-slate-400 italic">${escapeHtml(preLine)}</p>` : '');
                            noteEl.classList.remove('hidden');
                        } else {
                            noteEl.classList.add('hidden');
                            noteEl.innerHTML = '';
                        }
                    }
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
                    topBlocksEl.innerHTML = top.map((b) => {
                        const wastedTag = b.category === 'wasted'
                            ? ' <span class="ml-1 text-[0.65rem] uppercase tracking-wider text-rose-300">Wasted</span>'
                            : '';
                        return `<li class="text-slate-300"><span class="text-slate-100 font-medium">${escapeHtml(formatDuration(b.durationMs || 0))}</span> · ${escapeHtml(b.label || 'Time block')}${wastedTag}</li>`;
                    }).join('');
                };

                const renderLast7Days = (blocks, now) => {
                    if (!last7DaysEl) return;
                    const todayKey = localDateString(now);
                    const signupKey = signupTs ? localDateString(signupTs) : null;
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
                        const isPreSignup = signupKey && dateStr < signupKey;
                        let cls;
                        if (isPreSignup) {
                            cls = 'border-slate-800/30 bg-slate-900/20 opacity-50';
                        } else if (isToday) {
                            cls = 'border-[var(--chrono-blue)] bg-slate-800/60';
                        } else {
                            cls = 'border-slate-800/60 bg-slate-900/40';
                        }
                        const valueCls = totalMs > 0 ? 'text-slate-100' : 'text-slate-600';
                        const valueText = isPreSignup
                            ? '<span class="text-slate-600 italic">pre-signup</span>'
                            : (totalMs > 0 ? escapeHtml(formatDuration(totalMs)) : '—');
                        const titleAttr = isPreSignup ? ' title="Before your signup"' : '';
                        tiles.push(
                            `<div class="rounded-lg border ${cls} p-2 text-center"${titleAttr}>` +
                            `<div class="text-[0.65rem] uppercase tracking-wider text-slate-400">${escapeHtml(dayName)}</div>` +
                            `<div class="text-[0.65rem] text-slate-500">${d.getDate()}</div>` +
                            `<div class="mt-1 text-sm ${valueCls}">${valueText}</div>` +
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
                    const wastedTodayMs = completedToday
                        .filter((b) => b.category === 'wasted')
                        .reduce((s, b) => s + (b.durationMs || 0), 0);
                    if (loggedTodayEl) loggedTodayEl.textContent = formatDuration(loggedTodayMs);
                    if (loggedCountEl) {
                        const n = completedToday.length;
                        const blockText = `${n} ${n === 1 ? 'block' : 'blocks'}`;
                        const wastedHtml = wastedTodayMs > 0
                            ? ` · <span class="text-rose-300">${escapeHtml(formatDuration(wastedTodayMs))} wasted</span>`
                            : '';
                        loggedCountEl.innerHTML = `${escapeHtml(blockText)}${wastedHtml}`;
                    }

                    const wakeMins = hhmmToMins(wakeTime);
                    const endMins = hhmmToMins(endTime);
                    const wakeToday = new Date(now);
                    wakeToday.setHours(Math.floor(wakeMins / 60), wakeMins % 60, 0, 0);
                    const endToday = new Date(now);
                    endToday.setHours(Math.floor(endMins / 60), endMins % 60, 0, 0);

                    // Clamp the start of today's active window to signup if they joined today
                    // after wake-up. Past today, this clamp is a no-op.
                    const signupIsToday = signupTs && localDateString(signupTs) === todayStr;
                    const effectiveTodayStart =
                        signupIsToday && signupTs.getTime() > wakeToday.getTime()
                            ? signupTs
                            : wakeToday;

                    const activeWindowEnd = Math.min(now.getTime(), endToday.getTime());
                    const elapsedActiveMs = Math.max(0, activeWindowEnd - effectiveTodayStart.getTime());
                    const elapsedActiveMins = Math.floor(elapsedActiveMs / 60000);

                    let context;
                    if (now.getTime() < effectiveTodayStart.getTime()) {
                        context = signupIsToday && signupTs.getTime() > wakeToday.getTime()
                            ? 'before signup'
                            : 'before wake-up';
                    } else if (signupIsToday && signupTs.getTime() > wakeToday.getTime()) {
                        context = 'since signup';
                    } else if (now.getTime() > endToday.getTime()) {
                        context = 'past end of day';
                    } else {
                        context = 'since wake-up';
                    }

                    const loggedTodayMins = Math.round(loggedTodayMs / 60000);
                    const unloggedMins = Math.max(0, elapsedActiveMins - loggedTodayMins);
                    if (unloggedTodayEl) unloggedTodayEl.textContent = formatDuration(unloggedMins * 60000);
                    if (unloggedContextEl) unloggedContextEl.textContent = context;

                    renderTopBlocks(todayBlocks);

                    updatePeriod(periodSections.week, 'week', startOfWeek(now), endOfWeek(now), now, blocks);
                    updatePeriod(periodSections.month, 'month', startOfMonth(now), endOfMonth(now), now, blocks);
                    updatePeriod(periodSections.year, 'year', startOfYear(now), endOfYear(now), now, blocks);

                    renderLast7Days(blocks, now);
                };

                window.addEventListener('chrono:blocks:changed', updateAll);
                setInterval(updateAll, 30000);
                updateAll();
            })();
        </script>
    @endpush
@endsection
