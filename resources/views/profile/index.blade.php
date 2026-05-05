@extends('layouts.app')

@section('content')
    @php
        $user = $user ?? auth()->user();
        $timezone = $user?->timezone ?? 'UTC';
        $signupAt = $user?->created_at?->copy()->setTimezone($timezone);
        $endTime = $user?->end_of_day_time ? substr($user->end_of_day_time, 0, 5) : '22:00';
        $wakeTime = $user?->wake_up_time ? substr($user->wake_up_time, 0, 5) : '07:00';
        $endTimeDisplay = \Carbon\Carbon::createFromFormat('H:i', $endTime)->format('g:i A');
        $wakeTimeDisplay = \Carbon\Carbon::createFromFormat('H:i', $wakeTime)->format('g:i A');
        $initials = collect(preg_split('/\s+/', trim($user?->name ?? '?')))
            ->filter()
            ->take(2)
            ->map(fn($p) => mb_strtoupper(mb_substr($p, 0, 1)))
            ->implode('');
        if ($initials === '') {
            $initials = '?';
        }
        $emailVerified = (bool) $user?->email_verified_at;
    @endphp

    <div class="relative overflow-hidden rounded-2xl border border-slate-800/60 bg-[radial-gradient(circle_at_top,_rgba(0,224,255,0.15),_transparent_45%)] p-8 mb-8">
        <div class="absolute -right-24 -top-24 h-56 w-56 rounded-full bg-[radial-gradient(circle,_rgba(255,107,26,0.35),_transparent_70%)] blur-2xl"></div>
        <div class="relative flex flex-col md:flex-row md:items-center gap-5">
            <div class="flex h-20 w-20 items-center justify-center rounded-full border border-slate-700 bg-slate-900/70 text-2xl font-display tracking-[0.2em] text-[var(--chrono-blue)]">
                {{ $initials }}
            </div>
            <div class="flex-1">
                <h1 class="font-display text-3xl tracking-[0.3em] uppercase">{{ $user?->name ?? 'You' }}</h1>
                <p class="text-slate-300 text-sm mt-2">{{ $user?->email ?? '—' }}</p>
                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                    @if ($emailVerified)
                        <span class="inline-flex items-center rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2 py-0.5 text-emerald-300">Email verified</span>
                    @else
                        <span class="inline-flex items-center rounded-full border border-amber-500/30 bg-amber-500/10 px-2 py-0.5 text-amber-300">Email not verified</span>
                    @endif
                    @if ($signupAt)
                        <span class="inline-flex items-center rounded-full border border-slate-700/60 bg-slate-900/60 px-2 py-0.5 text-slate-300">
                            Joined {{ $signupAt->format('M j, Y') }}
                        </span>
                    @endif
                    <span class="inline-flex items-center rounded-full border border-slate-700/60 bg-slate-900/60 px-2 py-0.5 text-slate-300">
                        {{ $timezone }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    @if (session('status') === 'profile-updated' || session('status') === 'profile-updated-email-changed')
        <div class="mb-6 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">
            Profile updated.
            @if (session('status') === 'profile-updated-email-changed')
                A verification link has been sent to your new email.
            @endif
        </div>
    @endif
    @if (session('status') === 'password-updated')
        <div class="mb-6 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">
            Password updated.
        </div>
    @endif

    <div class="space-y-6">
        {{-- ─── Personal info form ──────────────────────────────────────── --}}
        <section class="chrono-panel rounded-2xl p-6 md:p-8">
            <div class="flex items-baseline justify-between gap-4 mb-1">
                <h2 class="font-display text-sm uppercase tracking-[0.3em] text-slate-300">Personal information</h2>
            </div>
            <p class="text-xs text-slate-500 mb-5">Your name and email. Changing your email triggers re-verification.</p>

            <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label class="block text-xs uppercase tracking-[0.2em] text-slate-400 mb-1" for="profile_name">Name</label>
                    <input id="profile_name" name="name" type="text" required maxlength="255"
                        value="{{ old('name', $user?->name) }}"
                        class="w-full rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 text-slate-100">
                    @error('name')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-xs uppercase tracking-[0.2em] text-slate-400 mb-1" for="profile_email">Email</label>
                    <input id="profile_email" name="email" type="email" required maxlength="255"
                        value="{{ old('email', $user?->email) }}"
                        class="w-full rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 text-slate-100">
                    @error('email')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                </div>

                <div class="pt-2">
                    <button type="submit"
                        class="rounded-lg bg-[var(--chrono-blue)] text-slate-950 font-semibold px-4 py-2">
                        Save changes
                    </button>
                </div>
            </form>
        </section>

        {{-- ─── Password form ───────────────────────────────────────────── --}}
        <section class="chrono-panel rounded-2xl p-6 md:p-8">
            <h2 class="font-display text-sm uppercase tracking-[0.3em] text-slate-300 mb-1">Password</h2>
            <p class="text-xs text-slate-500 mb-5">Use a strong password you don't reuse anywhere else.</p>

            <form method="POST" action="/user/password" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label class="block text-xs uppercase tracking-[0.2em] text-slate-400 mb-1" for="current_password">Current password</label>
                    <input id="current_password" name="current_password" type="password" required autocomplete="current-password"
                        class="w-full rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 text-slate-100">
                    @error('current_password', 'updatePassword')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-xs uppercase tracking-[0.2em] text-slate-400 mb-1" for="password">New password</label>
                    <input id="password" name="password" type="password" required autocomplete="new-password"
                        class="w-full rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 text-slate-100">
                    @error('password', 'updatePassword')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-xs uppercase tracking-[0.2em] text-slate-400 mb-1" for="password_confirmation">Confirm new password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                        class="w-full rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 text-slate-100">
                </div>

                <div class="pt-2">
                    <button type="submit"
                        class="rounded-lg bg-[var(--chrono-blue)] text-slate-950 font-semibold px-4 py-2">
                        Update password
                    </button>
                </div>
            </form>
        </section>

        {{-- ─── Snapshot of preferences (read-only, link to Settings) ───── --}}
        <section class="chrono-panel rounded-2xl p-6 md:p-8">
            <div class="flex items-baseline justify-between gap-4 mb-4">
                <h2 class="font-display text-sm uppercase tracking-[0.3em] text-slate-300">Schedule snapshot</h2>
                <a href="{{ route('settings.show') }}"
                    class="text-xs uppercase tracking-[0.2em] text-[var(--chrono-blue)] hover:underline">
                    Edit in Settings →
                </a>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3">
                    <div class="text-[0.65rem] uppercase tracking-wider text-slate-500">Timezone</div>
                    <div class="mt-1 text-sm text-slate-100">{{ $timezone }}</div>
                </div>
                <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3">
                    <div class="text-[0.65rem] uppercase tracking-wider text-slate-500">End of day</div>
                    <div class="mt-1 text-sm text-slate-100">{{ $endTimeDisplay }}</div>
                </div>
                <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3">
                    <div class="text-[0.65rem] uppercase tracking-wider text-slate-500">Wake-up</div>
                    <div class="mt-1 text-sm text-slate-100">{{ $wakeTimeDisplay }}</div>
                </div>
                <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3">
                    <div class="text-[0.65rem] uppercase tracking-wider text-slate-500">Gap threshold</div>
                    <div class="mt-1 text-sm text-slate-100">{{ $user?->gap_threshold_minutes ?? 30 }} min</div>
                </div>
            </div>
        </section>
    </div>
@endsection
