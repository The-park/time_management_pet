@extends('layouts.admin')

@section('page_title', 'Quotes')

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <div class="text-xs uppercase tracking-[0.2em] text-slate-500">
                <a href="{{ route('admin.dashboard') }}" class="hover:text-slate-300">Admin</a>
                <span class="mx-1.5 text-slate-700">/</span>
                <span>Quotes</span>
            </div>
            <h1 class="font-display text-2xl md:text-3xl tracking-[0.2em] uppercase mt-1 text-slate-100">Motivational quotes</h1>
            <p class="text-sm text-slate-400 mt-1">
                <span class="tabular-nums text-slate-200">{{ number_format($activeCount) }}</span> active
                · <span class="tabular-nums text-slate-200">{{ number_format($total) }}</span> total.
                Shown to users in the flying-quote bubble.
            </p>
        </div>
        <a href="{{ route('admin.quotes.create') }}"
            class="rounded-lg bg-rose-500 hover:bg-rose-400 text-white font-semibold px-3 py-2 text-sm transition-colors inline-flex items-center gap-1.5">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
            </svg>
            New quote
        </a>
    </div>

    <section class="rounded-xl border border-slate-800/60 bg-slate-900/40 overflow-hidden">
        <header class="px-5 py-3 border-b border-slate-800/60">
            <form method="GET" action="{{ route('admin.quotes.index') }}"
                class="flex flex-wrap items-center gap-2">
                <div class="relative flex-1 min-w-[200px]">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                    </svg>
                    <input type="text" name="q" value="{{ $search }}" placeholder="Search text, author, source…"
                        class="w-full rounded-lg bg-slate-950/60 border border-slate-800 pl-9 pr-3 py-1.5 text-sm text-slate-100 placeholder-slate-500 focus:border-rose-500/40 focus:outline-none focus:ring-1 focus:ring-rose-500/20">
                </div>
                <select name="category"
                    class="rounded-lg bg-slate-950/60 border border-slate-800 px-3 py-1.5 text-sm text-slate-100 focus:border-rose-500/40 focus:outline-none focus:ring-1 focus:ring-rose-500/20">
                    <option value="">All categories</option>
                    @foreach ($categories as $c)
                        <option value="{{ $c }}" @selected($category === $c)>{{ ucfirst($c) }}</option>
                    @endforeach
                </select>
                <button type="submit" class="rounded-lg bg-rose-500 hover:bg-rose-400 text-white font-semibold px-3 py-1.5 text-sm transition-colors">Search</button>
                @if ($search !== '' || $category !== '')
                    <a href="{{ route('admin.quotes.index') }}" class="text-xs text-slate-400 hover:text-slate-200">Clear</a>
                @endif
            </form>
        </header>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-800/60 bg-slate-950/30">
                        <th class="text-left px-5 py-2 text-[0.65rem] uppercase tracking-[0.15em] text-slate-400 font-semibold">Text</th>
                        <th class="text-left px-3 py-2 text-[0.65rem] uppercase tracking-[0.15em] text-slate-400 font-semibold">Attribution</th>
                        <th class="text-left px-3 py-2 text-[0.65rem] uppercase tracking-[0.15em] text-slate-400 font-semibold">Category</th>
                        <th class="text-left px-3 py-2 text-[0.65rem] uppercase tracking-[0.15em] text-slate-400 font-semibold">Status</th>
                        <th class="text-right px-5 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/40">
                    @forelse ($quotes as $q)
                        <tr class="hover:bg-slate-800/30 transition-colors align-top">
                            <td class="px-5 py-3 text-slate-100 text-xs max-w-xl">
                                <span class="line-clamp-3">{{ $q->text }}</span>
                            </td>
                            <td class="px-3 py-3 text-slate-400 text-xs">
                                @if ($q->author || $q->source)
                                    {{ $q->author ?? '—' }}
                                    @if ($q->source)<span class="block text-slate-600">{{ $q->source }}</span>@endif
                                @else
                                    <span class="text-slate-600">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-xs">
                                <span class="inline-block rounded-full border border-slate-700 bg-slate-950/40 px-2 py-0.5 text-slate-300 uppercase tracking-[0.1em] text-[0.6rem]">
                                    {{ $q->category }}
                                </span>
                            </td>
                            <td class="px-3 py-3 text-xs">
                                @if ($q->is_active)
                                    <span class="inline-block rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2 py-0.5 text-emerald-200 uppercase tracking-[0.1em] text-[0.6rem]">Active</span>
                                @else
                                    <span class="inline-block rounded-full border border-slate-600 bg-slate-800/40 px-2 py-0.5 text-slate-400 uppercase tracking-[0.1em] text-[0.6rem]">Disabled</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right whitespace-nowrap">
                                <form method="POST" action="{{ route('admin.quotes.toggle', $q->id) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="text-xs text-slate-300 hover:text-slate-100 transition-colors">
                                        {{ $q->is_active ? 'Disable' : 'Enable' }}
                                    </button>
                                </form>
                                <span class="text-slate-700 mx-1">·</span>
                                <a href="{{ route('admin.quotes.edit', $q->id) }}" class="text-xs text-slate-300 hover:text-slate-100 transition-colors">Edit</a>
                                <span class="text-slate-700 mx-1">·</span>
                                <form method="POST" action="{{ route('admin.quotes.destroy', $q->id) }}" class="inline"
                                    onsubmit="return confirm('Delete this quote permanently?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-rose-300 hover:text-rose-200 transition-colors">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-10 text-center text-sm text-slate-500">No quotes match.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-5 py-3 border-t border-slate-800/60">
            {{ $quotes->links() }}
        </div>
    </section>
@endsection
