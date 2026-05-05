@extends('layouts.admin')

@section('page_title', 'Admins')

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <div class="text-xs uppercase tracking-[0.2em] text-slate-500">
                <a href="{{ route('admin.dashboard') }}" class="hover:text-slate-300">Admin</a>
                <span class="mx-1.5 text-slate-700">/</span>
                <span>Admins</span>
            </div>
            <h1 class="font-display text-2xl md:text-3xl tracking-[0.2em] uppercase mt-1 text-slate-100">Administrators</h1>
            <p class="text-sm text-slate-400 mt-1">Members with admin-console access. The current admin can't be removed.</p>
        </div>
        <a href="{{ route('admin.administrators.create') }}"
            class="rounded-lg bg-rose-500 hover:bg-rose-400 text-white font-semibold px-4 py-2 text-sm transition-colors inline-flex items-center gap-1.5">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            New admin
        </a>
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-rose-500/40 bg-rose-500/5 px-4 py-3 text-sm text-rose-200">
            @foreach ($errors->all() as $err)
                <div>{{ $err }}</div>
            @endforeach
        </div>
    @endif

    <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 overflow-hidden">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b border-slate-800/60 bg-slate-950/40">
                    <th class="text-left px-5 py-2.5 text-[0.65rem] uppercase tracking-[0.15em] text-slate-400 font-semibold">Admin</th>
                    <th class="text-left px-3 py-2.5 text-[0.65rem] uppercase tracking-[0.15em] text-slate-400 font-semibold">Created</th>
                    <th class="text-right px-5 py-2.5 text-[0.65rem] uppercase tracking-[0.15em] text-slate-400 font-semibold"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/40">
                @forelse ($admins as $a)
                    @php
                        $initials = collect(preg_split('/\s+/', trim($a->name ?? '?')))
                            ->filter()->take(2)->map(fn($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
                        $isSelf = $currentId === (int) $a->id;
                    @endphp
                    <tr class="hover:bg-slate-800/30 transition-colors">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-rose-500/30 bg-rose-500/10 text-xs font-semibold tracking-wide text-rose-200">
                                    {{ $initials ?: '?' }}
                                </div>
                                <div class="min-w-0">
                                    <div class="text-sm text-slate-100 truncate flex items-center gap-2">
                                        {{ $a->name }}
                                        @if ($isSelf)
                                            <span class="rounded-full bg-rose-500/15 text-rose-300 border border-rose-500/30 px-2 py-0.5 text-[0.6rem] uppercase tracking-wider">you</span>
                                        @endif
                                    </div>
                                    <div class="text-xs text-slate-500 truncate">{{ $a->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-3 text-xs text-slate-400 whitespace-nowrap">{{ $a->created_at?->format('M j, Y') }}</td>
                        <td class="px-5 py-3 text-right">
                            <div class="flex items-center justify-end gap-3 text-xs">
                                <a href="{{ route('admin.administrators.edit', $a->id) }}"
                                    class="text-slate-300 hover:text-white transition-colors">Edit</a>
                                @if (! $isSelf)
                                    <form method="POST" action="{{ route('admin.administrators.destroy', $a->id) }}"
                                        onsubmit="return confirm('Delete admin {{ addslashes($a->email) }}? They will lose console access immediately.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-rose-300 hover:text-rose-200 transition-colors">Delete</button>
                                    </form>
                                @else
                                    <span class="text-slate-600 cursor-not-allowed" title="You can't delete your own account">Delete</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-5 py-12 text-center text-sm text-slate-500">
                            No admins found. (You shouldn't see this — there must be at least one to view this page.)
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $admins->links() }}
    </div>
@endsection
