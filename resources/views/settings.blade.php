@extends('layouts.app')

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

        $regionLabels = [
            'Africa' => 'Africa',
            'America' => 'America',
            'Antarctica' => 'Antarctica',
            'Arctic' => 'Arctic',
            'Asia' => 'Asia',
            'Atlantic' => 'Atlantic',
            'Australia' => 'Australia',
            'Europe' => 'Europe',
            'Indian' => 'Indian Ocean',
            'Pacific' => 'Pacific',
        ];
        $tzLabels = [
            'Asia/Kolkata' => 'Asia/Kolkata — India Standard Time (Chennai, Mumbai, Delhi · IST, +5:30)',
        ];
        $grouped = [];
        foreach (DateTimeZone::listIdentifiers() as $tz) {
            $region = str_contains($tz, '/') ? explode('/', $tz, 2)[0] : 'Other';
            $grouped[$region][] = $tz;
        }
    @endphp

    <div class="relative overflow-hidden rounded-2xl border border-slate-800/60 bg-[radial-gradient(circle_at_top,_rgba(0,224,255,0.15),_transparent_45%)] p-8 mb-8">
        <div class="absolute -right-24 -top-24 h-56 w-56 rounded-full bg-[radial-gradient(circle,_rgba(255,107,26,0.35),_transparent_70%)] blur-2xl"></div>
        <div class="relative">
            <h1 class="font-display text-3xl tracking-[0.3em] uppercase">Settings</h1>
            <p class="text-slate-300 text-sm mt-2">Daily schedule and detection preferences.</p>
        </div>
    </div>

    @if (session('status') === 'settings-updated')
        <div class="mb-6 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">
            Settings saved.
        </div>
    @endif

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
                        <select id="timezone" name="timezone" required
                            class="w-full rounded-md bg-slate-900 border border-slate-700 px-3 py-2 text-slate-100">
                            <option value="UTC" @selected($selectedTz === 'UTC')>UTC</option>
                            @foreach ($regionLabels as $region => $label)
                                @if (!empty($grouped[$region]))
                                    <optgroup label="{{ $label }}">
                                        @foreach ($grouped[$region] as $tz)
                                            <option value="{{ $tz }}" @selected($selectedTz === $tz)>{{ $tzLabels[$tz] ?? $tz }}</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                            @endforeach
                        </select>
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

        {{-- ─── Account info / dangerous actions placeholder ────────────── --}}
        <section class="chrono-panel rounded-2xl p-6 md:p-8">
            <h2 class="font-display text-sm uppercase tracking-[0.3em] text-slate-300 mb-1">Account</h2>
            <p class="text-xs text-slate-500 mb-4">Identity and password changes live on the <a class="text-[var(--chrono-blue)] hover:underline" href="{{ route('profile.show') }}">Profile page</a>.</p>
        </section>
    </div>
@endsection
