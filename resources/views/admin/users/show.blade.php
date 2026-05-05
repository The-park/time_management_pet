@extends('layouts.admin')

@section('page_title', $user->name)

@section('content')
    @php
        $totalDurationLabel = function ($seconds) {
            $hours = (int) floor($seconds / 3600);
            $mins = (int) floor(($seconds % 3600) / 60);
            return $hours > 0 ? "{$hours}h ".($mins > 0 ? "{$mins}m" : '') : "{$mins}m";
        };
        $initials = collect(preg_split('/\s+/', trim($user->name ?? '?')))
            ->filter()->take(2)->map(fn($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
    @endphp

    {{-- ─── Breadcrumb ──────────────────────────────────────────────── --}}
    <div class="mb-6 text-xs uppercase tracking-[0.2em] text-slate-500">
        <a href="{{ route('admin.dashboard') }}" class="hover:text-slate-300">Admin</a>
        <span class="mx-1.5 text-slate-700">/</span>
        <a href="{{ route('admin.users.index') }}" class="hover:text-slate-300">Users</a>
        <span class="mx-1.5 text-slate-700">/</span>
        <span class="text-slate-300">#{{ $user->id }}</span>
    </div>

    {{-- ─── Hero ──────────────────────────────────────────────────── --}}
    <section class="rounded-xl border border-slate-800/60 bg-gradient-to-br from-slate-900/60 to-slate-950/60 p-6 mb-6">
        <div class="flex flex-col md:flex-row md:items-center gap-5">
            <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl border border-slate-800 bg-slate-950/80 text-xl font-display tracking-[0.15em] text-slate-200">
                {{ $initials ?: '?' }}
            </div>
            <div class="min-w-0 flex-1">
                <h1 class="font-display text-2xl tracking-[0.15em] uppercase text-slate-100 truncate">{{ $user->name }}</h1>
                <p class="text-sm text-slate-400 truncate">{{ $user->email }}</p>
                <div class="mt-3 flex flex-wrap items-center gap-1.5 text-[0.65rem]">
                    @if ($user->trashed())
                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 uppercase tracking-wider bg-rose-500/10 text-rose-300 border border-rose-500/30">
                            <span class="h-1.5 w-1.5 rounded-full bg-rose-400"></span>
                            deleted {{ $user->deleted_at?->format('M j, Y') }}
                        </span>
                    @elseif ($user->status === 'suspended')
                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 uppercase tracking-wider bg-amber-500/10 text-amber-200 border border-amber-500/30">
                            <span class="h-1.5 w-1.5 rounded-full bg-amber-400"></span>
                            suspended
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 uppercase tracking-wider bg-emerald-500/10 text-emerald-200 border border-emerald-500/30">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                            {{ $user->status ?? 'active' }}
                        </span>
                    @endif

                    @if ($user->email_verified_at)
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 uppercase tracking-wider bg-slate-800/60 text-slate-300 border border-slate-700">email verified</span>
                    @else
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 uppercase tracking-wider bg-amber-500/10 text-amber-300 border border-amber-500/30">unverified</span>
                    @endif

                    <span class="inline-flex items-center rounded-full px-2 py-0.5 uppercase tracking-wider bg-slate-800/60 text-slate-400 border border-slate-700">
                        joined {{ $user->created_at?->format('M j, Y') }}
                    </span>
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 uppercase tracking-wider bg-slate-800/60 text-slate-400 border border-slate-700">
                        ID #{{ $user->id }}
                    </span>
                </div>
            </div>

            {{-- Inline KPIs --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 md:max-w-md">
                <div class="rounded-lg border border-slate-800 bg-slate-950/40 px-3 py-2">
                    <div class="text-[0.6rem] uppercase tracking-wider text-slate-500">Time blocks</div>
                    <div class="text-lg font-semibold tabular-nums text-slate-100">{{ number_format($totals['time_blocks']) }}</div>
                </div>
                <div class="rounded-lg border border-slate-800 bg-slate-950/40 px-3 py-2">
                    <div class="text-[0.6rem] uppercase tracking-wider text-slate-500">Logged</div>
                    <div class="text-lg font-semibold tabular-nums text-slate-100">{{ $totalDurationLabel($totals['duration_seconds']) }}</div>
                </div>
                <div class="rounded-lg border border-slate-800 bg-slate-950/40 px-3 py-2">
                    <div class="text-[0.6rem] uppercase tracking-wider text-slate-500">Goals</div>
                    <div class="text-lg font-semibold tabular-nums text-slate-100">{{ number_format($totals['daily_goals']) }}</div>
                </div>
                <div class="rounded-lg border border-slate-800 bg-slate-950/40 px-3 py-2">
                    <div class="text-[0.6rem] uppercase tracking-wider text-slate-500">Countdowns</div>
                    <div class="text-lg font-semibold tabular-nums text-slate-100">{{ number_format($totals['countdowns']) }}</div>
                </div>
            </div>
        </div>
    </section>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- ─── Left rail ─────────────────────────────────────────── --}}
        <div class="space-y-4">
            <section class="rounded-xl border border-slate-800/60 bg-slate-900/40 overflow-hidden">
                <header class="px-5 py-3 border-b border-slate-800/60">
                    <h2 class="font-display text-xs uppercase tracking-[0.2em] text-slate-300">Profile</h2>
                </header>
                <dl class="divide-y divide-slate-800/40 text-sm">
                    @php
                        $rows = [
                            ['Name', $user->name],
                            ['Email', $user->email],
                            ['Joined', $user->created_at?->format('M j, Y · g:i A')],
                            ['Verified', $user->email_verified_at?->format('M j, Y') ?? '—'],
                        ];
                    @endphp
                    @foreach ($rows as [$k, $v])
                        <div class="flex items-baseline justify-between gap-3 px-5 py-2.5">
                            <dt class="text-xs uppercase tracking-wider text-slate-500">{{ $k }}</dt>
                            <dd class="text-slate-100 truncate">{{ $v }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            <section class="rounded-xl border border-slate-800/60 bg-slate-900/40 overflow-hidden">
                <header class="px-5 py-3 border-b border-slate-800/60">
                    <h2 class="font-display text-xs uppercase tracking-[0.2em] text-slate-300">Schedule</h2>
                </header>
                <dl class="divide-y divide-slate-800/40 text-sm">
                    @php
                        $sched = [
                            ['Timezone', $user->timezone ?? '—'],
                            ['End of day', $user->end_of_day_time ? \Carbon\Carbon::createFromFormat('H:i:s', $user->end_of_day_time)->format('g:i A') : '—'],
                            ['Wake-up', $user->wake_up_time ? \Carbon\Carbon::createFromFormat('H:i:s', $user->wake_up_time)->format('g:i A') : '—'],
                            ['Gap threshold', ($user->gap_threshold_minutes ?? '—').' min'],
                        ];
                    @endphp
                    @foreach ($sched as [$k, $v])
                        <div class="flex items-baseline justify-between gap-3 px-5 py-2.5">
                            <dt class="text-xs uppercase tracking-wider text-slate-500">{{ $k }}</dt>
                            <dd class="text-slate-100">{{ $v }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            {{-- Profile / state actions --}}
            <section class="rounded-xl border border-slate-800/60 bg-slate-900/40 overflow-hidden">
                <header class="px-5 py-3 border-b border-slate-800/60">
                    <h2 class="font-display text-xs uppercase tracking-[0.2em] text-slate-300">Manage</h2>
                </header>
                <div class="px-5 py-4 space-y-2.5">
                    <a href="{{ route('admin.users.edit', $user->id) }}"
                        class="block w-full rounded-lg bg-slate-800/60 hover:bg-slate-800 text-slate-100 text-center font-semibold px-3 py-2 text-sm transition-colors">
                        Edit profile
                    </a>

                    @if (! $user->trashed())
                        @if (! $user->email_verified_at)
                            <form method="POST" action="{{ route('admin.users.verify-email', $user->id) }}"
                                onsubmit="return confirm('Mark {{ addslashes($user->email) }} as email-verified? This skips the verification email step.');">
                                @csrf
                                <button type="submit" class="w-full rounded-lg border border-emerald-500/40 hover:bg-emerald-500/10 text-emerald-200 px-3 py-2 text-sm transition-colors">
                                    Mark email verified
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.users.resend-verification', $user->id) }}">
                                @csrf
                                <button type="submit" class="w-full rounded-lg border border-slate-700 hover:bg-slate-800/40 text-slate-200 px-3 py-2 text-sm transition-colors">
                                    Resend verification email
                                </button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('admin.users.send-password-reset', $user->id) }}"
                            onsubmit="return confirm('Send a password reset link to {{ addslashes($user->email) }}?');">
                            @csrf
                            <button type="submit" class="w-full rounded-lg border border-slate-700 hover:bg-slate-800/40 text-slate-200 px-3 py-2 text-sm transition-colors">
                                Send password reset
                            </button>
                        </form>

                        <div class="pt-2 mt-2 border-t border-slate-800/60"></div>

                        @if (($user->status ?? 'active') === 'suspended')
                            <form method="POST" action="{{ route('admin.users.unsuspend', $user->id) }}">
                                @csrf
                                <button type="submit" class="w-full rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white font-semibold px-3 py-2 text-sm transition-colors">
                                    Reactivate account
                                </button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('admin.users.suspend', $user->id) }}"
                                onsubmit="return confirm('Suspend {{ addslashes($user->email) }}? They will not be able to use their account until reactivated.');">
                                @csrf
                                <button type="submit" class="w-full rounded-lg bg-amber-500 hover:bg-amber-400 text-white font-semibold px-3 py-2 text-sm transition-colors">
                                    Suspend account
                                </button>
                            </form>
                        @endif
                    @endif
                </div>
            </section>

            {{-- Danger zone --}}
            <section class="rounded-xl border border-rose-700/40 bg-rose-500/5 overflow-hidden">
                <header class="px-5 py-3 border-b border-rose-700/30 flex items-center gap-2">
                    <svg class="h-4 w-4 text-rose-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                    <h2 class="font-display text-xs uppercase tracking-[0.2em] text-rose-200">Danger zone</h2>
                </header>
                <div class="px-5 py-4 space-y-2.5">
                    @if ($user->trashed())
                        <form method="POST" action="{{ route('admin.users.restore', $user->id) }}">
                            @csrf
                            <button type="submit" class="w-full rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white font-semibold px-3 py-2 text-sm transition-colors">
                                Restore from soft-delete
                            </button>
                        </form>
                        <p class="text-[0.65rem] text-slate-500 leading-relaxed">
                            This user is currently soft-deleted. Restore brings them back fully. Permanent delete removes their data forever.
                        </p>
                    @else
                        <form method="POST" action="{{ route('admin.users.destroy', $user->id) }}"
                            onsubmit="return confirm('Soft-delete {{ addslashes($user->email) }}? They can be restored from the Deleted filter.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="w-full rounded-lg border border-rose-500/60 hover:bg-rose-500/10 text-rose-200 px-3 py-2 text-sm transition-colors">
                                Soft-delete (reversible)
                            </button>
                        </form>
                    @endif

                    {{-- Permanent delete: available regardless of trashed state --}}
                    <details class="rounded-lg border border-rose-500/40 bg-rose-500/10">
                        <summary class="cursor-pointer px-3 py-2 text-sm text-rose-200 hover:text-white">
                            Permanently delete user
                        </summary>
                        <form method="POST" action="{{ route('admin.users.force-destroy', $user->id) }}"
                            id="force-delete-form"
                            data-target-email="{{ mb_strtolower($user->email) }}"
                            class="px-3 py-3 space-y-2 border-t border-rose-500/30">
                            @csrf
                            @method('DELETE')
                            <p class="text-xs text-rose-200 leading-relaxed">
                                Removes the user, their time blocks, daily goals, and countdowns. Cannot be undone.
                                You'll be asked to type the email to confirm.
                            </p>
                            <button type="submit"
                                class="w-full rounded-lg bg-rose-500 hover:bg-rose-400 text-white font-semibold px-3 py-2 text-sm transition-colors">
                                I understand — delete permanently
                            </button>
                        </form>
                    </details>
                    <script>
                        (() => {
                            const form = document.getElementById('force-delete-form');
                            if (!form) return;
                            form.addEventListener('submit', (e) => {
                                if (form.dataset.confirmed === '1') return;
                                e.preventDefault();
                                const target = (form.dataset.targetEmail || '').trim().toLowerCase();
                                const typed = window.prompt('Type the email to confirm permanent deletion:\n\n' + target);
                                if (typed === null) return;
                                if (typed.trim().toLowerCase() !== target) {
                                    window.alert('Email did not match. Aborted.');
                                    return;
                                }
                                form.dataset.confirmed = '1';
                                form.submit();
                            });
                        })();
                    </script>

                    <p class="pt-1 text-[0.65rem] text-slate-500 leading-relaxed">
                        Suspend prevents the user from logging in. Soft-delete is reversible. Permanent delete is the final action and cascades to all of their data.
                    </p>
                </div>
            </section>
        </div>

        {{-- ─── Right column: activity ────────────────────────────── --}}
        <div class="lg:col-span-2 space-y-4">
            <section class="rounded-xl border border-slate-800/60 bg-slate-900/40 overflow-hidden">
                <header class="flex items-baseline justify-between gap-3 px-5 py-3 border-b border-slate-800/60">
                    <h2 class="font-display text-xs uppercase tracking-[0.2em] text-slate-300">Recent time blocks</h2>
                    <span class="text-[0.65rem] uppercase tracking-wider text-slate-500">{{ $timeBlocks->count() }} of {{ number_format($totals['time_blocks']) }}</span>
                </header>
                @if ($timeBlocks->isEmpty())
                    <div class="px-5 py-8 text-center text-sm text-slate-500">No time blocks recorded.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-800/60 bg-slate-950/30 text-[0.65rem] uppercase tracking-wider text-slate-400">
                                    <th class="text-left px-5 py-2.5 font-semibold">Start</th>
                                    <th class="text-left px-3 py-2.5 font-semibold">End</th>
                                    <th class="text-right px-3 py-2.5 font-semibold">Duration</th>
                                    <th class="text-left px-3 py-2.5 font-semibold">Reason</th>
                                    <th class="text-left px-5 py-2.5 font-semibold">Auto</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/30">
                                @foreach ($timeBlocks as $b)
                                    <tr class="hover:bg-slate-800/30 transition-colors">
                                        <td class="px-5 py-2 text-slate-200 whitespace-nowrap">{{ $b->start_time?->format('M j · g:i A') }}</td>
                                        <td class="px-3 py-2 text-slate-300 whitespace-nowrap">{{ $b->end_time?->format('g:i A') }}</td>
                                        <td class="px-3 py-2 text-right text-slate-200 tabular-nums">{{ $totalDurationLabel((int) $b->duration_seconds) }}</td>
                                        <td class="px-3 py-2 text-slate-300">{{ $b->reason ?: '—' }}</td>
                                        <td class="px-5 py-2 text-xs">
                                            @if ($b->auto_filled)
                                                <span class="rounded-full px-2 py-0.5 text-[0.6rem] uppercase tracking-wider bg-amber-500/10 text-amber-300 border border-amber-500/30">auto</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="rounded-xl border border-slate-800/60 bg-slate-900/40 overflow-hidden">
                <header class="flex items-baseline justify-between gap-3 px-5 py-3 border-b border-slate-800/60">
                    <h2 class="font-display text-xs uppercase tracking-[0.2em] text-slate-300">Recent daily goals</h2>
                    <span class="text-[0.65rem] uppercase tracking-wider text-slate-500">{{ $dailyGoals->count() }}</span>
                </header>
                @if ($dailyGoals->isEmpty())
                    <div class="px-5 py-8 text-center text-sm text-slate-500">No goals recorded.</div>
                @else
                    <ul class="divide-y divide-slate-800/40">
                        @foreach ($dailyGoals as $g)
                            <li class="px-5 py-2.5 flex items-baseline gap-3 text-sm">
                                <span class="text-xs text-slate-500 whitespace-nowrap w-24">{{ $g->date?->format('M j, Y') }}</span>
                                <span class="text-slate-200 truncate">{{ $g->goal_text ?: '—' }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="rounded-xl border border-slate-800/60 bg-slate-900/40 overflow-hidden">
                <header class="flex items-baseline justify-between gap-3 px-5 py-3 border-b border-slate-800/60">
                    <h2 class="font-display text-xs uppercase tracking-[0.2em] text-slate-300">Recent countdowns</h2>
                    <span class="text-[0.65rem] uppercase tracking-wider text-slate-500">{{ $countdowns->count() }}</span>
                </header>
                @if ($countdowns->isEmpty())
                    <div class="px-5 py-8 text-center text-sm text-slate-500">No countdowns recorded.</div>
                @else
                    <ul class="divide-y divide-slate-800/40">
                        @foreach ($countdowns as $c)
                            <li class="px-5 py-2.5 flex items-baseline gap-3 text-sm">
                                <span class="text-xs text-slate-500 whitespace-nowrap w-32">{{ $c->started_at?->format('M j · g:i A') }}</span>
                                <span class="text-slate-200 truncate flex-1">{{ $c->label ?: '—' }}</span>
                                <span class="text-xs text-slate-400 whitespace-nowrap">
                                    {{ $totalDurationLabel((int) $c->duration_seconds) }}
                                    <span class="text-slate-600">·</span>
                                    {{ $c->state ?? '—' }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </div>
@endsection
