@extends('layouts.app')

@section('page_title', 'Settings')

@section('content')
    @php
        $user = $user ?? auth()->user();
        $timezone = $user?->timezone ?? 'UTC';
        $endTimeValue = old('end_of_day_time', $user?->end_of_day_time
            ? substr($user->end_of_day_time, 0, 5) : '22:00');
        $wakeTimeValue = old('wake_up_time', $user?->wake_up_time
            ? substr($user->wake_up_time, 0, 5) : '07:00');
        $endTimeDisplay = \Carbon\Carbon::createFromFormat('H:i', $endTimeValue)->format('g:i A');
        $wakeTimeDisplay = \Carbon\Carbon::createFromFormat('H:i', $wakeTimeValue)->format('g:i A');
        $selectedTz = old('timezone', $timezone);
        $gap = old('gap_threshold_minutes', $user?->gap_threshold_minutes ?? 30);
    @endphp

    <div class="relative overflow-hidden rounded-2xl border border-slate-800/60 bg-[radial-gradient(circle_at_top,_rgba(0,224,255,0.15),_transparent_45%)] p-8 mb-8">
        <div class="absolute -right-24 -top-24 h-56 w-56 rounded-full bg-[radial-gradient(circle,_rgba(255,107,26,0.35),_transparent_70%)] blur-2xl"></div>
        <div class="relative">
            <h1 class="font-display text-3xl tracking-[0.3em] uppercase">Settings</h1>
            <p class="text-slate-300 text-sm mt-2">Daily schedule and detection preferences.</p>
        </div>
    </div>

    <div class="space-y-6">
        <form method="POST" action="{{ route('settings.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            {{-- ─── Schedule ────────────────────────────────────────────── --}}
            <section class="chrono-panel rounded-2xl p-6 md:p-8">
                <h2 class="font-display text-sm uppercase tracking-[0.3em] text-slate-300 mb-1">Schedule</h2>
                <p class="text-xs text-slate-500 mb-5">Defines your active waking window and when "today" ends for the dashboard countdown.</p>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs uppercase tracking-[0.2em] text-slate-400 mb-1" for="timezone">Timezone</label>
                        @include('partials.timezone-select', [
                            'selected'   => $selectedTz,
                            'autodetect' => false,
                            'extraClass' => 'w-full rounded-md bg-slate-900 border border-slate-700 px-3 py-2 text-slate-100',
                        ])
                        @error('timezone')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs uppercase tracking-[0.2em] text-slate-400 mb-1" for="end_of_day_time_display">End of day</label>
                            <input id="end_of_day_time_display" type="text" inputmode="numeric" placeholder="10:00 PM"
                                value="{{ $endTimeDisplay }}" required
                                data-time12
                                data-time12-hidden-id="end_of_day_time"
                                data-time12-error-id="end_of_day_time_error"
                                data-time12-label="End of day"
                                data-time12-min="18:00"
                                data-time12-max="23:59"
                                data-time12-example="10:00 PM"
                                data-time12-group="settings_form"
                                class="w-full rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 text-slate-100">
                            <input id="end_of_day_time" name="end_of_day_time" type="hidden" value="{{ $endTimeValue }}">
                            <p id="end_of_day_time_error" class="mt-1 text-xs text-rose-400 hidden" aria-live="polite"></p>
                            @error('end_of_day_time')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="block text-xs uppercase tracking-[0.2em] text-slate-400 mb-1" for="wake_up_time_display">Wake-up</label>
                            <input id="wake_up_time_display" type="text" inputmode="numeric" placeholder="7:00 AM"
                                value="{{ $wakeTimeDisplay }}" required
                                data-time12
                                data-time12-hidden-id="wake_up_time"
                                data-time12-error-id="wake_up_time_error"
                                data-time12-label="Wake-up"
                                data-time12-min="04:00"
                                data-time12-max="11:00"
                                data-time12-example="7:00 AM"
                                data-time12-group="settings_form"
                                class="w-full rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 text-slate-100">
                            <input id="wake_up_time" name="wake_up_time" type="hidden" value="{{ $wakeTimeValue }}">
                            <p id="wake_up_time_error" class="mt-1 text-xs text-rose-400 hidden" aria-live="polite"></p>
                            @error('wake_up_time')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <p class="text-xs text-slate-500">
                        End of day must fall between <strong>6:00 PM and 11:59 PM</strong>. Wake-up must fall between <strong>4:00 AM and 11:00 AM</strong>. 12-hour format with AM/PM only — flexible casing and spacing are accepted (<span class="text-slate-300">10pm</span>, <span class="text-slate-300">7 a.m.</span>, <span class="text-slate-300">10:30 PM</span>).
                    </p>
                </div>
            </section>

            {{-- ─── Detection ───────────────────────────────────────────── --}}
            <section class="chrono-panel rounded-2xl p-6 md:p-8">
                <h2 class="font-display text-sm uppercase tracking-[0.3em] text-slate-300 mb-1">Gap detection</h2>
                <p class="text-xs text-slate-500 mb-5">How long an unaccounted gap between time blocks should be before it counts as "unlogged" rather than a normal break.</p>

                <div>
                    <label class="block text-xs uppercase tracking-[0.2em] text-slate-400 mb-1" for="gap_threshold_minutes">Gap threshold (minutes)</label>
                    <div class="flex items-center gap-3">
                        <input id="gap_threshold_minutes" name="gap_threshold_minutes" type="number" min="15" max="240" step="5"
                            value="{{ $gap }}" required
                            class="w-32 rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 text-slate-100">
                        <span class="text-xs text-slate-500">15 – 240 minutes</span>
                    </div>
                    @error('gap_threshold_minutes')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                </div>
            </section>

            <div>
                <button id="settings_save_button" type="submit" data-time12-gate="settings_form"
                    class="rounded-lg bg-[var(--chrono-blue)] text-slate-950 font-semibold px-4 py-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    Save settings
                </button>
            </div>
        </form>

        {{-- ─── Email backup ─────────────────────────────────────────────
             Only rendered when an admin has flipped backup_email_enabled
             on the user record. The admin can disable the gate at any
             time, which also force-resets auto-daily server-side. --}}
        @if ($user->backup_email_enabled)
            @php
                $defaultEmail = old('email', $user->backup_email_address ?: $user->email);
                $today = \Carbon\CarbonImmutable::now($user->timezone ?: 'UTC')->toDateString();
                $signupDate = \Carbon\CarbonImmutable::parse($user->created_at ?? now())->toDateString();
            @endphp
            <section class="chrono-panel rounded-2xl p-6 md:p-8">
                <div class="flex flex-wrap items-baseline justify-between gap-2 mb-4">
                    <h2 class="font-display text-sm uppercase tracking-[0.3em] text-slate-300">Email backup</h2>
                    <span class="text-[0.65rem] uppercase tracking-[0.2em] text-slate-500">
                        @if (($user->backup_count ?? 0) > 0)
                            Sent {{ (int) $user->backup_count }} time{{ ((int) $user->backup_count) === 1 ? '' : 's' }}
                            @if ($user->backup_last_sent_at)
                                · last {{ $user->backup_last_sent_at->diffForHumans() }}
                            @endif
                        @else
                            No backups yet
                        @endif
                    </span>
                </div>
                <p class="text-xs text-slate-400 mb-5">
                    Email yourself a JSON snapshot of your time blocks and goals. Pick a date range,
                    or grab everything since you signed up ({{ $signupDate }}).
                </p>

                {{-- ── Manual send form ── --}}
                <form method="POST" action="{{ route('backup.send') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label class="block text-xs uppercase tracking-[0.2em] text-slate-400 mb-1" for="backup_email">Send to email</label>
                        <input id="backup_email" name="email" type="email" required maxlength="254"
                            value="{{ $defaultEmail }}"
                            class="w-full md:w-96 rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 text-slate-100">
                        @error('email')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                    </div>

                    <div class="space-y-2">
                        <span class="block text-xs uppercase tracking-[0.2em] text-slate-400 mb-1">What to include</span>
                        <label class="flex items-start gap-2.5 cursor-pointer rounded-lg border border-slate-700 hover:border-slate-500 bg-slate-900/40 p-3" data-backup-mode-label="complete">
                            <input type="radio" name="mode" value="complete" @checked(old('mode', 'complete') === 'complete')
                                class="mt-0.5 h-4 w-4 border-slate-600 bg-slate-950 text-[var(--chrono-blue)] focus:ring-[var(--chrono-blue)]/40">
                            <span class="text-sm text-slate-100">
                                Complete data
                                <span class="block text-xs text-slate-400 mt-0.5">From {{ $signupDate }} to today.</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-2.5 cursor-pointer rounded-lg border border-slate-700 hover:border-slate-500 bg-slate-900/40 p-3" data-backup-mode-label="range">
                            <input type="radio" name="mode" value="range" @checked(old('mode') === 'range')
                                class="mt-0.5 h-4 w-4 border-slate-600 bg-slate-950 text-[var(--chrono-blue)] focus:ring-[var(--chrono-blue)]/40">
                            <span class="text-sm text-slate-100 flex-1">
                                Date range
                                <span class="block text-xs text-slate-400 mt-0.5">Pick start and end dates.</span>
                                <div class="mt-3 grid grid-cols-2 gap-3 max-w-md" data-backup-range-fields aria-hidden="true">
                                    <div>
                                        <label class="block text-[0.6rem] uppercase tracking-[0.2em] text-slate-500 mb-1" for="backup_from">From</label>
                                        <input id="backup_from" name="from" type="date"
                                            min="{{ $signupDate }}" max="{{ $today }}"
                                            value="{{ old('from') }}" style="color-scheme: dark"
                                            class="w-full rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 text-slate-100">
                                        @error('from')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label class="block text-[0.6rem] uppercase tracking-[0.2em] text-slate-500 mb-1" for="backup_to">To</label>
                                        <input id="backup_to" name="to" type="date"
                                            min="{{ $signupDate }}" max="{{ $today }}"
                                            value="{{ old('to') }}" style="color-scheme: dark"
                                            class="w-full rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 text-slate-100">
                                        @error('to')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                                    </div>
                                </div>
                            </span>
                        </label>
                    </div>

                    <button type="submit"
                        class="rounded-lg bg-[var(--chrono-orange)] text-slate-950 font-semibold px-4 py-2 text-sm">
                        Email me my data
                    </button>
                </form>

                {{-- ── Auto-daily config (separate form) ── --}}
                <form method="POST" action="{{ route('backup.config') }}" class="mt-8 pt-6 border-t border-slate-800/60 space-y-3">
                    @csrf
                    @method('PUT')

                    <h3 class="font-display text-xs uppercase tracking-[0.3em] text-slate-400">Daily auto-backup</h3>
                    <p class="text-xs text-slate-500">
                        We'll email you on your first dashboard visit each day with everything from signup to yesterday.
                    </p>

                    <div>
                        <label class="block text-xs uppercase tracking-[0.2em] text-slate-400 mb-1" for="backup_auto_email">Email address for auto-backup</label>
                        <input id="backup_auto_email" name="backup_email_address" type="email" maxlength="254"
                            value="{{ old('backup_email_address', $user->backup_email_address ?: '') }}"
                            placeholder="{{ $user->email }}"
                            class="w-full md:w-96 rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 text-slate-100">
                        @error('backup_email_address')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                    </div>

                    <label class="inline-flex items-start gap-2.5 cursor-pointer">
                        <input type="checkbox" name="backup_auto_daily" value="1"
                            @checked(old('backup_auto_daily', $user->backup_auto_daily))
                            class="mt-1 h-4 w-4 rounded border-slate-600 bg-slate-950 text-emerald-500 focus:ring-emerald-500/40">
                        <span class="text-sm text-slate-200">
                            Send me a daily backup automatically
                            <span class="block text-xs text-slate-500 mt-0.5">
                                Idempotent — only fires once per day on the first time you open the dashboard.
                            </span>
                        </span>
                    </label>

                    <div class="pt-1">
                        <button type="submit"
                            class="rounded-lg border border-slate-600 hover:border-slate-400 text-slate-100 px-4 py-2 text-sm">
                            Save daily-backup settings
                        </button>
                    </div>
                </form>
            </section>

            @push('scripts')
                <script>
                    // Tiny enhancement: the date-range inputs are disabled
                    // (and visually dimmed) unless the "Date range" radio is
                    // selected. Server still enforces the rule via
                    // required_if:mode,range, so this is pure UX polish.
                    (() => {
                        const radios = document.querySelectorAll('input[name="mode"]');
                        const fields = document.querySelector('[data-backup-range-fields]');
                        if (!fields || !radios.length) return;
                        const refresh = () => {
                            const isRange = document.querySelector('input[name="mode"]:checked')?.value === 'range';
                            fields.style.opacity = isRange ? '1' : '0.45';
                            fields.setAttribute('aria-hidden', isRange ? 'false' : 'true');
                            fields.querySelectorAll('input').forEach(i => { i.disabled = !isRange; });
                        };
                        radios.forEach(r => r.addEventListener('change', refresh));
                        refresh();
                    })();
                </script>
            @endpush
        @endif

        {{-- ─── Account ─────────────────────────────────────────────────── --}}
        <section class="chrono-panel rounded-2xl p-6 md:p-8">
            <h2 class="font-display text-sm uppercase tracking-[0.3em] text-slate-300 mb-1">Account</h2>
            <p class="text-xs text-slate-500 mb-4">
                Identity and password changes live on the
                <a class="text-[var(--chrono-blue)] hover:underline" href="{{ route('profile.show') }}">Profile page</a>.
            </p>
        </section>

        {{-- ─── Danger zone ─────────────────────────────────────────────── --}}
        <section class="rounded-2xl border border-rose-700/50 bg-rose-950/20 p-6 md:p-8">
            <h2 class="font-display text-sm uppercase tracking-[0.3em] text-rose-300 mb-1">Danger zone</h2>
            <p class="text-xs text-slate-400 mb-5">
                Permanently delete your account. Your name, email, and saved preferences are removed.
                Time blocks stored in your browser are not touched by this action — clear them from the
                History page's test-data tools if you also want a clean slate.
            </p>

            <details class="rounded-xl border border-rose-800/40 bg-rose-950/30 p-4">
                <summary class="cursor-pointer text-sm text-rose-300 hover:text-rose-200">
                    Delete my account
                </summary>
                <form method="POST" action="{{ route('account.destroy') }}" class="mt-4 space-y-3">
                    @csrf
                    @method('DELETE')

                    <p class="text-xs text-slate-300">
                        Confirm by entering your password and typing
                        <code class="text-rose-300">delete my account</code> below.
                    </p>

                    <div>
                        <label class="block text-xs uppercase tracking-[0.2em] text-slate-400 mb-1" for="delete_password">Password</label>
                        <input id="delete_password" name="password" type="password" autocomplete="current-password" required
                            class="w-full rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 text-slate-100">
                        @error('password')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-xs uppercase tracking-[0.2em] text-slate-400 mb-1" for="delete_confirm">Type "delete my account"</label>
                        <input id="delete_confirm" name="confirm_text" type="text" required autocomplete="off"
                            class="w-full rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 text-slate-100">
                        @error('confirm_text')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                    </div>

                    <div class="pt-1">
                        <button type="submit"
                            class="rounded-lg bg-rose-500 hover:bg-rose-400 text-white font-semibold px-4 py-2">
                            Delete account permanently
                        </button>
                    </div>
                </form>
            </details>
        </section>
    </div>
@endsection
