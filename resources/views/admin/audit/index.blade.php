@extends('layouts.admin')

@section('page_title', 'Audit log')

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <div class="text-xs uppercase tracking-[0.2em] text-slate-500">
                <a href="{{ route('admin.dashboard') }}" class="hover:text-slate-300">Admin</a>
                <span class="mx-1.5 text-slate-700">/</span>
                <span>Audit log</span>
            </div>
            <h1 class="font-display text-2xl md:text-3xl tracking-[0.2em] uppercase mt-1 text-slate-100">Audit Log</h1>
            <p class="text-sm text-slate-400 mt-1">Every admin action — who, what, when, from where.</p>
        </div>
        <div class="text-xs text-slate-500">
            Showing {{ $entries->firstItem() ?? 0 }}–{{ $entries->lastItem() ?? 0 }} of {{ number_format($entries->total()) }}
        </div>
    </div>

    {{-- ─── Filters + Prune ─────────────────────────────────────── --}}
    <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3 mb-5 flex flex-wrap items-center gap-2">
        <form method="GET" action="{{ route('admin.audit.index') }}"
            class="flex flex-wrap items-center gap-2">
            <select name="action"
                class="rounded-lg bg-slate-950/60 border border-slate-800 px-3 py-2 text-sm text-slate-100 focus:border-rose-500/40 focus:outline-none focus:ring-1 focus:ring-rose-500/20">
                <option value="">All actions</option>
                @foreach ($actions as $a)
                    <option value="{{ $a }}" @selected($filterAction === $a)>{{ $a }}</option>
                @endforeach
            </select>
            <select name="admin_id"
                class="rounded-lg bg-slate-950/60 border border-slate-800 px-3 py-2 text-sm text-slate-100 focus:border-rose-500/40 focus:outline-none focus:ring-1 focus:ring-rose-500/20">
                <option value="">All admins</option>
                @foreach ($admins as $a)
                    <option value="{{ $a->id }}" @selected((int) $filterAdminId === $a->id)>{{ $a->name }}</option>
                @endforeach
            </select>
            <button type="submit"
                class="rounded-lg bg-rose-500 hover:bg-rose-400 text-white font-semibold px-4 py-2 text-sm transition-colors">
                Apply
            </button>
            @if ($filterAction || $filterAdminId)
                <a href="{{ route('admin.audit.index') }}" class="text-xs text-slate-400 hover:text-slate-200 px-2">Clear</a>
            @endif
        </form>

        <details class="ml-auto rounded-lg border border-slate-800/60 bg-slate-950/30 px-3 py-1.5">
            <summary class="cursor-pointer flex items-center gap-1.5 text-xs uppercase tracking-[0.15em] text-slate-300 hover:text-slate-100">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                Prune old logs
            </summary>
            <form method="POST" action="{{ route('admin.audit.prune') }}"
                class="mt-3 flex items-center gap-2"
                onsubmit="return confirm('Delete audit entries older than the specified number of days? This is irreversible.');">
                @csrf
                <label class="text-xs text-slate-400">Older than</label>
                <input type="number" name="days" value="90" min="1" max="3650" required
                    class="w-20 rounded-md bg-slate-950/60 border border-slate-800 px-2 py-1 text-sm text-slate-100">
                <span class="text-xs text-slate-400">days</span>
                <button type="submit" class="rounded-md bg-rose-500 hover:bg-rose-400 text-white font-semibold px-3 py-1 text-xs transition-colors">
                    Prune
                </button>
            </form>
            <p class="mt-2 text-[0.6rem] text-slate-500 leading-relaxed">
                Auto-pruned daily at 03:30 (90-day retention). Adjust in <code class="text-slate-400">routes/console.php</code>.
            </p>
        </details>
    </div>

    {{-- ─── Table ──────────────────────────────────────────────── --}}
    <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 overflow-hidden">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b border-slate-800/60 bg-slate-950/40">
                    <th class="text-left px-5 py-2.5 text-[0.65rem] uppercase tracking-[0.15em] text-slate-400 font-semibold">When</th>
                    <th class="text-left px-3 py-2.5 text-[0.65rem] uppercase tracking-[0.15em] text-slate-400 font-semibold">Admin</th>
                    <th class="text-left px-3 py-2.5 text-[0.65rem] uppercase tracking-[0.15em] text-slate-400 font-semibold">Action</th>
                    <th class="text-left px-3 py-2.5 text-[0.65rem] uppercase tracking-[0.15em] text-slate-400 font-semibold">Target</th>
                    <th class="text-left px-5 py-2.5 text-[0.65rem] uppercase tracking-[0.15em] text-slate-400 font-semibold">From</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/40">
                @forelse ($entries as $e)
                    <tr class="hover:bg-slate-800/30 transition-colors">
                        <td class="px-5 py-2.5 text-slate-200 text-xs whitespace-nowrap tabular-nums">
                            {{ $e->viewed_at?->format('M j, Y g:i A') }}
                        </td>
                        <td class="px-3 py-2.5 text-slate-100 text-sm">{{ $e->admin?->name ?? '—' }}</td>
                        <td class="px-3 py-2.5">
                            <code class="rounded bg-slate-800/60 px-1.5 py-0.5 text-xs text-rose-300 border border-slate-800/60">{{ $e->action }}</code>
                            @if (! empty($e->metadata))
                                @foreach ($e->metadata as $k => $v)
                                    <span class="ml-1 inline-flex items-center rounded border border-slate-700/60 bg-slate-950/40 px-1.5 py-0.5 text-[0.6rem] text-slate-300">
                                        <span class="text-slate-500">{{ $k }}:</span>&nbsp;<span class="text-slate-200">{{ is_scalar($v) ? $v : json_encode($v) }}</span>
                                    </span>
                                @endforeach
                            @endif
                        </td>
                        <td class="px-3 py-2.5 text-sm">
                            @if ($e->viewedUser)
                                <a href="{{ route('admin.users.show', $e->viewedUser->id) }}" class="text-rose-300 hover:text-rose-200 transition-colors">
                                    {{ $e->viewedUser->email }}
                                </a>
                            @else
                                <span class="text-slate-600">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-2.5 text-slate-500 text-xs font-mono">{{ $e->ip_address }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-12 text-center text-sm text-slate-500">No audit entries match this filter.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $entries->links() }}
    </div>
@endsection
