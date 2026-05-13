@extends('layouts.admin')

@section('page_title', 'Edit · '.$user->name)

@section('content')
    @php
        $endTimeValue = old('end_of_day_time', $user->end_of_day_time
            ? substr($user->end_of_day_time, 0, 5) : '22:00');
        $wakeTimeValue = old('wake_up_time', $user->wake_up_time
            ? substr($user->wake_up_time, 0, 5) : '07:00');
        $selectedTz = old('timezone', $user->timezone ?? 'UTC');

        $regions = [
            'Africa' => 'Africa', 'America' => 'America', 'Antarctica' => 'Antarctica',
            'Arctic' => 'Arctic', 'Asia' => 'Asia', 'Atlantic' => 'Atlantic',
            'Australia' => 'Australia', 'Europe' => 'Europe', 'Indian' => 'Indian Ocean',
            'Pacific' => 'Pacific',
        ];
        $grouped = [];
        foreach (DateTimeZone::listIdentifiers() as $tz) {
            $region = str_contains($tz, '/') ? explode('/', $tz, 2)[0] : 'Other';
            $grouped[$region][] = $tz;
        }
    @endphp

    <div class="mb-6 text-xs uppercase tracking-[0.2em] text-slate-500">
        <a href="{{ route('admin.dashboard') }}" class="hover:text-slate-300">Admin</a>
        <span class="mx-1.5 text-slate-700">/</span>
        <a href="{{ route('admin.users.index') }}" class="hover:text-slate-300">Users</a>
        <span class="mx-1.5 text-slate-700">/</span>
        <a href="{{ route('admin.users.show', $user->id) }}" class="hover:text-slate-300">#{{ $user->id }}</a>
        <span class="mx-1.5 text-slate-700">/</span>
        <span class="text-slate-300">Edit</span>
    </div>

    <h1 class="font-display text-2xl md:text-3xl tracking-[0.2em] uppercase text-slate-100">Edit user</h1>
    <p class="text-sm text-slate-400 mt-1 mb-6">
        Changes are recorded in the audit log. Email changes clear the verification timestamp.
    </p>

    <form method="POST" action="{{ route('admin.users.update', $user->id) }}" class="space-y-6 max-w-3xl">
        @csrf
        @method('PUT')

        {{-- Identity --}}
        <section class="rounded-xl border border-slate-800/60 bg-slate-900/40 overflow-hidden">
            <header class="px-5 py-3 border-b border-slate-800/60">
                <h2 class="font-display text-xs uppercase tracking-[0.2em] text-slate-300">Identity</h2>
            </header>
            <div class="px-5 py-5 space-y-4">
                <div>
                    <label class="block text-xs uppercase tracking-[0.2em] text-slate-400 mb-1.5" for="name">Name</label>
                    <input id="name" name="name" type="text" required maxlength="255"
                        value="{{ old('name', $user->name) }}"
                        class="w-full rounded-lg bg-slate-950/60 border border-slate-800 px-3 py-2 text-slate-100 focus:border-rose-500/40 focus:outline-none focus:ring-1 focus:ring-rose-500/20">
                    @error('name')<p class="mt-1 text-xs text-rose-300">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs uppercase tracking-[0.2em] text-slate-400 mb-1.5" for="email">Email</label>
                    <input id="email" name="email" type="email" required maxlength="255"
                        value="{{ old('email', $user->email) }}"
                        class="w-full rounded-lg bg-slate-950/60 border border-slate-800 px-3 py-2 text-slate-100 focus:border-rose-500/40 focus:outline-none focus:ring-1 focus:ring-rose-500/20">
                    @error('email')<p class="mt-1 text-xs text-rose-300">{{ $message }}</p>@enderror
                    <p class="mt-1 text-[0.65rem] text-slate-500">Changing the email clears the verification timestamp.</p>
                </div>
            </div>
        </section>

        {{-- Schedule --}}
        <section class="rounded-xl border border-slate-800/60 bg-slate-900/40 overflow-hidden">
            <header class="px-5 py-3 border-b border-slate-800/60">
                <h2 class="font-display text-xs uppercase tracking-[0.2em] text-slate-300">Schedule</h2>
            </header>
            <div class="px-5 py-5 space-y-4">
                <div>
                    <label class="block text-xs uppercase tracking-[0.2em] text-slate-400 mb-1.5" for="timezone">Timezone</label>
                    <select id="timezone" name="timezone" required
                        class="w-full rounded-lg bg-slate-950/60 border border-slate-800 px-3 py-2 text-slate-100 focus:border-rose-500/40 focus:outline-none focus:ring-1 focus:ring-rose-500/20">
                        <option value="UTC" @selected($selectedTz === 'UTC')>UTC</option>
                        @foreach ($regions as $reg => $label)
                            @if (! empty($grouped[$reg]))
                                <optgroup label="{{ $label }}">
                                    @foreach ($grouped[$reg] as $tz)
                                        <option value="{{ $tz }}" @selected($selectedTz === $tz)>{{ $tz }}</option>
                                    @endforeach
                                </optgroup>
                            @endif
                        @endforeach
                    </select>
                    @error('timezone')<p class="mt-1 text-xs text-rose-300">{{ $message }}</p>@enderror
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs uppercase tracking-[0.2em] text-slate-400 mb-1.5" for="end_of_day_time">End of day</label>
                        <input id="end_of_day_time" name="end_of_day_time" type="time" required
                            min="18:00" max="23:59" value="{{ $endTimeValue }}"
                            class="w-full rounded-lg bg-slate-950/60 border border-slate-800 px-3 py-2 text-slate-100" style="color-scheme: dark">
                        @error('end_of_day_time')<p class="mt-1 text-xs text-rose-300">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-xs uppercase tracking-[0.2em] text-slate-400 mb-1.5" for="wake_up_time">Wake-up</label>
                        <input id="wake_up_time" name="wake_up_time" type="time" required
                            min="04:00" max="11:00" value="{{ $wakeTimeValue }}"
                            class="w-full rounded-lg bg-slate-950/60 border border-slate-800 px-3 py-2 text-slate-100" style="color-scheme: dark">
                        @error('wake_up_time')<p class="mt-1 text-xs text-rose-300">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-xs uppercase tracking-[0.2em] text-slate-400 mb-1.5" for="gap_threshold_minutes">Gap threshold (min)</label>
                        <input id="gap_threshold_minutes" name="gap_threshold_minutes" type="number"
                            min="15" max="240" step="5" required
                            value="{{ old('gap_threshold_minutes', $user->gap_threshold_minutes ?? 30) }}"
                            class="w-full rounded-lg bg-slate-950/60 border border-slate-800 px-3 py-2 text-slate-100">
                        @error('gap_threshold_minutes')<p class="mt-1 text-xs text-rose-300">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>
        </section>

        {{-- ── Email-backup feature gate ────────────────────────────────
             Admin-controlled toggle. When ON, the user sees a new
             "Email backup" section in their Settings page where they
             can manually email themselves a JSON export and configure
             daily auto-backups. When OFF, the section is hidden and
             auto-daily is force-disabled at save time. --}}
        <section class="rounded-2xl border border-slate-800 bg-slate-900/40 p-6">
            <header class="mb-5">
                <h2 class="font-display text-sm uppercase tracking-[0.2em] text-slate-200">Email backup feature</h2>
                <p class="text-xs text-slate-400 mt-1">
                    User-facing data export. With this on, the user can email a JSON snapshot of their
                    time blocks + goals from <em>Settings</em>, and optionally schedule a daily
                    auto-backup that fires on their first login each day.
                </p>
            </header>
            <label class="inline-flex items-start gap-3 cursor-pointer">
                <input type="checkbox" name="backup_email_enabled" value="1"
                    @checked(old('backup_email_enabled', $user->backup_email_enabled))
                    class="mt-1 h-4 w-4 rounded border-slate-600 bg-slate-950 text-emerald-500 focus:ring-emerald-500/40">
                <span class="text-sm text-slate-200">
                    Enable email backup for this user
                    <span class="block text-xs text-slate-500 mt-0.5">
                        Sent {{ (int) ($user->backup_count ?? 0) }} time{{ ((int)($user->backup_count ?? 0)) === 1 ? '' : 's' }} so far.
                        @if ($user->backup_last_sent_at)
                            Last: {{ $user->backup_last_sent_at->diffForHumans() }}
                        @endif
                    </span>
                </span>
            </label>
            @error('backup_email_enabled')<p class="mt-1 text-xs text-rose-300">{{ $message }}</p>@enderror
        </section>

        <div class="flex items-center gap-3">
            <button type="submit"
                class="rounded-lg bg-rose-500 hover:bg-rose-400 text-white font-semibold px-4 py-2 text-sm transition-colors">
                Save changes
            </button>
            <a href="{{ route('admin.users.show', $user->id) }}"
                class="text-sm text-slate-400 hover:text-slate-200 transition-colors">
                Cancel
            </a>
        </div>
    </form>
@endsection
