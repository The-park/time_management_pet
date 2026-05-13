@extends('layouts.admin')

@section('page_title', 'Users')

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <div class="text-xs uppercase tracking-[0.2em] text-slate-500">
                <a href="{{ route('admin.dashboard') }}" class="hover:text-slate-300">Admin</a>
                <span class="mx-1.5 text-slate-700">/</span>
                <span>Users</span>
            </div>
            <h1 class="font-display text-2xl md:text-3xl tracking-[0.2em] uppercase mt-1 text-slate-100">Users</h1>
            <p class="text-sm text-slate-400 mt-1">Search, filter, and manage user accounts.</p>
        </div>
        <div class="text-xs text-slate-500">
            Showing {{ $users->firstItem() ?? 0 }}–{{ $users->lastItem() ?? 0 }} of {{ number_format($users->total()) }}
        </div>
    </div>

    {{-- ─── At-a-glance KPI strip ──────────────────────────────────── --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
        <a href="{{ route('admin.users.index') }}"
            class="group rounded-xl border border-slate-800/60 bg-slate-900/40 hover:bg-slate-800/60 hover:border-slate-700 transition-colors p-4">
            <div class="text-[0.6rem] uppercase tracking-wider text-slate-500">Total users</div>
            <div class="mt-1 text-2xl font-display tabular-nums text-slate-100">{{ number_format($statusCounts['all']) }}</div>
            <div class="text-[0.65rem] text-slate-500 mt-0.5">across all states</div>
        </a>
        <a href="{{ route('admin.users.index', ['status' => 'active']) }}"
            class="group rounded-xl border border-emerald-500/30 bg-emerald-500/5 hover:bg-emerald-500/10 transition-colors p-4">
            <div class="text-[0.6rem] uppercase tracking-wider text-emerald-300">Active</div>
            <div class="mt-1 text-2xl font-display tabular-nums text-emerald-200">{{ number_format($statusCounts['active']) }}</div>
            <div class="text-[0.65rem] text-emerald-300/70 mt-0.5">can sign in</div>
        </a>
        <a href="{{ route('admin.users.index', ['status' => 'suspended']) }}"
            class="group rounded-xl border border-amber-500/30 bg-amber-500/5 hover:bg-amber-500/10 transition-colors p-4">
            <div class="text-[0.6rem] uppercase tracking-wider text-amber-300">Suspended</div>
            <div class="mt-1 text-2xl font-display tabular-nums text-amber-200">{{ number_format($statusCounts['suspended']) }}</div>
            <div class="text-[0.65rem] text-amber-300/70 mt-0.5">blocked from login</div>
        </a>
        <a href="{{ route('admin.users.index', ['status' => 'deleted']) }}"
            class="group rounded-xl border border-rose-500/30 bg-rose-500/5 hover:bg-rose-500/10 transition-colors p-4">
            <div class="text-[0.6rem] uppercase tracking-wider text-rose-300">Soft-deleted</div>
            <div class="mt-1 text-2xl font-display tabular-nums text-rose-200">{{ number_format($statusCounts['deleted']) }}</div>
            <div class="text-[0.65rem] text-rose-300/70 mt-0.5">restorable</div>
        </a>
    </div>

    {{-- ─── Filter bar ──────────────────────────────────────────────── --}}
    <form method="GET" action="{{ route('admin.users.index') }}"
        class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3 mb-5 flex flex-wrap items-center gap-2">
        <div class="relative flex-1 min-w-[14rem]">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
            </svg>
            <input type="text" name="q" value="{{ $search }}" placeholder="Search by name or email…"
                class="w-full rounded-lg bg-slate-950/60 border border-slate-800 pl-9 pr-3 py-2 text-sm text-slate-100 placeholder-slate-500 focus:border-rose-500/40 focus:outline-none focus:ring-1 focus:ring-rose-500/20">
        </div>
        <select name="status"
            class="rounded-lg bg-slate-950/60 border border-slate-800 px-3 py-2 text-sm text-slate-100 focus:border-rose-500/40 focus:outline-none focus:ring-1 focus:ring-rose-500/20">
            <option value="all" @selected($status === 'all')>All ({{ $statusCounts['all'] }})</option>
            <option value="active" @selected($status === 'active')>Active ({{ $statusCounts['active'] }})</option>
            <option value="suspended" @selected($status === 'suspended')>Suspended ({{ $statusCounts['suspended'] }})</option>
            <option value="deleted" @selected($status === 'deleted')>Deleted ({{ $statusCounts['deleted'] }})</option>
        </select>
        <button type="submit"
            class="rounded-lg bg-rose-500 hover:bg-rose-400 text-white font-semibold px-4 py-2 text-sm transition-colors">
            Apply
        </button>
        @if ($search !== '' || $status !== 'all')
            <a href="{{ route('admin.users.index') }}" class="text-xs text-slate-400 hover:text-slate-200 px-2">Clear</a>
        @endif
    </form>

    <form method="POST" action="{{ route('admin.users.bulk') }}" id="bulk-form">
        @csrf

        {{-- Bulk action bar --}}
        <div id="bulk-bar"
            class="hidden mb-3 flex flex-wrap items-center gap-3 rounded-xl border border-rose-500/30 bg-rose-500/5 px-4 py-2.5 text-sm text-slate-100">
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center justify-center h-6 min-w-6 rounded-full bg-rose-500 text-white text-xs font-semibold px-1.5" id="bulk-count">0</span>
                <span>selected</span>
            </div>
            <span class="text-slate-600">·</span>
            <button type="submit" name="action" value="suspend"
                onclick="return confirm('Suspend the selected users?');"
                class="rounded-md bg-amber-500/15 hover:bg-amber-500/25 text-amber-200 border border-amber-500/30 px-3 py-1 text-xs font-semibold transition-colors">
                Suspend
            </button>
            <button type="submit" name="action" value="unsuspend"
                onclick="return confirm('Reactivate the selected users?');"
                class="rounded-md bg-emerald-500/15 hover:bg-emerald-500/25 text-emerald-200 border border-emerald-500/30 px-3 py-1 text-xs font-semibold transition-colors">
                Reactivate
            </button>
            <button type="submit" name="action" value="delete"
                onclick="return confirm('Soft-delete the selected users? They can be restored from the Deleted filter.');"
                class="rounded-md bg-rose-500 hover:bg-rose-400 text-white px-3 py-1 text-xs font-semibold transition-colors">
                Soft-delete
            </button>
            <span class="text-slate-700">|</span>
            {{-- Email-backup bulk grants. The controller validates the
                 backup_email_enabled column exists before applying, so
                 these buttons are safe to expose pre-migration too — the
                 user just gets a "migrate first" toast. --}}
            <button type="submit" name="action" value="enable_backup"
                onclick="return confirm('Grant email-backup access to the selected users? They\'ll see the new Email backup section in their Settings.');"
                class="rounded-md bg-cyan-500/15 hover:bg-cyan-500/25 text-cyan-200 border border-cyan-500/30 px-3 py-1 text-xs font-semibold transition-colors">
                Enable backup
            </button>
            <button type="submit" name="action" value="disable_backup"
                onclick="return confirm('Revoke email-backup access from the selected users? Daily auto-backup is also force-off.');"
                class="rounded-md bg-slate-500/15 hover:bg-slate-500/25 text-slate-200 border border-slate-500/30 px-3 py-1 text-xs font-semibold transition-colors">
                Disable backup
            </button>
            <button type="button" id="bulk-clear" class="ml-auto text-xs text-slate-400 hover:text-slate-100 transition-colors">
                Clear selection
            </button>
        </div>

        {{-- Table --}}
        <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 overflow-hidden">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-800/60 bg-slate-950/40">
                        <th class="w-10 px-4 py-2.5">
                            <input type="checkbox" id="bulk-select-all"
                                class="h-4 w-4 rounded border-slate-700 bg-slate-950 accent-rose-500"
                                aria-label="Select all on this page">
                        </th>
                        <th class="text-left px-4 py-2.5 text-[0.65rem] uppercase tracking-[0.15em] text-slate-400 font-semibold">User</th>
                        <th class="text-left px-4 py-2.5 text-[0.65rem] uppercase tracking-[0.15em] text-slate-400 font-semibold">Status</th>
                        <th class="text-left px-4 py-2.5 text-[0.65rem] uppercase tracking-[0.15em] text-slate-400 font-semibold">Joined</th>
                        <th class="text-right px-4 py-2.5 text-[0.65rem] uppercase tracking-[0.15em] text-slate-400 font-semibold">Blocks</th>
                        <th class="text-center px-4 py-2.5 text-[0.65rem] uppercase tracking-[0.15em] text-slate-400 font-semibold" title="Email backup feature status">Backup</th>
                        <th class="text-right px-4 py-2.5 text-[0.65rem] uppercase tracking-[0.15em] text-slate-400 font-semibold"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/40">
                    @forelse ($users as $u)
                        <tr class="hover:bg-slate-800/30 transition-colors">
                            <td class="px-4 py-3">
                                @if (! $u->trashed())
                                    <input type="checkbox" name="ids[]" value="{{ $u->id }}"
                                        class="bulk-check h-4 w-4 rounded border-slate-700 bg-slate-950 accent-rose-500"
                                        aria-label="Select {{ $u->email }}">
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    @php
                                        $initials = collect(preg_split('/\s+/', trim($u->name ?? '?')))
                                            ->filter()->take(2)->map(fn($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
                                    @endphp
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-slate-800 bg-slate-950/80 text-xs font-semibold tracking-wide text-slate-300">
                                        {{ $initials ?: '?' }}
                                    </div>
                                    <div class="min-w-0">
                                        <a href="{{ route('admin.users.show', $u->id) }}" class="text-sm text-slate-100 hover:text-rose-300 transition-colors truncate block">
                                            {{ $u->name }}
                                        </a>
                                        <div class="text-xs text-slate-500 truncate">{{ $u->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                @if ($u->trashed())
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[0.65rem] uppercase tracking-wider bg-rose-500/10 text-rose-300 border border-rose-500/30">
                                        <span class="h-1.5 w-1.5 rounded-full bg-rose-400"></span>
                                        deleted
                                    </span>
                                @elseif ($u->status === 'suspended')
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[0.65rem] uppercase tracking-wider bg-amber-500/10 text-amber-200 border border-amber-500/30">
                                        <span class="h-1.5 w-1.5 rounded-full bg-amber-400"></span>
                                        suspended
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[0.65rem] uppercase tracking-wider bg-emerald-500/10 text-emerald-200 border border-emerald-500/30">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                                        {{ $u->status ?? 'active' }}
                                    </span>
                                @endif
                                @if (! $u->email_verified_at)
                                    <span class="ml-1 inline-flex rounded-full px-2 py-0.5 text-[0.65rem] uppercase tracking-wider bg-amber-500/10 text-amber-300 border border-amber-500/20">unverified</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-400 whitespace-nowrap">
                                {{ $u->created_at?->format('M j, Y') }}
                            </td>
                            <td class="px-4 py-3 text-right text-sm tabular-nums text-slate-200">
                                {{ number_format($u->time_blocks_count ?? 0) }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                {{-- Backup status chip. Three states: enabled+auto, enabled-only, disabled. --}}
                                @if (! empty($u->backup_email_enabled) && ! empty($u->backup_auto_daily))
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[0.6rem] uppercase tracking-wider bg-emerald-500/15 text-emerald-200 border border-emerald-500/30"
                                        title="Backup enabled, daily auto-send ON · {{ (int) ($u->backup_count ?? 0) }} sent">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                                        Auto
                                    </span>
                                @elseif (! empty($u->backup_email_enabled))
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[0.6rem] uppercase tracking-wider bg-cyan-500/15 text-cyan-200 border border-cyan-500/30"
                                        title="Backup feature enabled (manual only) · {{ (int) ($u->backup_count ?? 0) }} sent">
                                        <span class="h-1.5 w-1.5 rounded-full bg-cyan-400"></span>
                                        On
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[0.6rem] uppercase tracking-wider bg-slate-700/40 text-slate-400 border border-slate-700/60"
                                        title="Backup feature disabled">
                                        <span class="h-1.5 w-1.5 rounded-full bg-slate-500"></span>
                                        Off
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.users.show', $u->id) }}"
                                    class="text-xs text-rose-300 hover:text-rose-200 inline-flex items-center gap-1 transition-colors">
                                    View
                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center">
                                <div class="text-sm text-slate-500">No users match this filter.</div>
                                @if ($search !== '' || $status !== 'all')
                                    <a href="{{ route('admin.users.index') }}" class="mt-2 inline-block text-xs text-rose-300 hover:text-rose-200">Clear filters</a>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </form>

    <div class="mt-4">
        {{ $users->links() }}
    </div>

    <script>
        (() => {
            const form = document.getElementById('bulk-form');
            const bar = document.getElementById('bulk-bar');
            const countEl = document.getElementById('bulk-count');
            const selectAll = document.getElementById('bulk-select-all');
            const clearBtn = document.getElementById('bulk-clear');
            if (!form || !bar) return;

            const checkboxes = () => Array.from(form.querySelectorAll('.bulk-check'));

            const refresh = () => {
                const cs = checkboxes();
                const selected = cs.filter((c) => c.checked).length;
                countEl.textContent = String(selected);
                bar.classList.toggle('hidden', selected === 0);
                if (selectAll) {
                    selectAll.checked = selected > 0 && selected === cs.length;
                    selectAll.indeterminate = selected > 0 && selected < cs.length;
                }
            };

            selectAll?.addEventListener('change', () => {
                checkboxes().forEach((c) => { c.checked = selectAll.checked; });
                refresh();
            });
            clearBtn?.addEventListener('click', () => {
                checkboxes().forEach((c) => { c.checked = false; });
                refresh();
            });
            form.addEventListener('change', (e) => {
                if (e.target?.classList?.contains('bulk-check')) refresh();
            });
            refresh();
        })();
    </script>
@endsection
