@extends('layouts.admin')

@section('page_title', 'Domains')

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <div class="text-xs uppercase tracking-[0.2em] text-slate-500">
                <a href="{{ route('admin.dashboard') }}" class="hover:text-slate-300">Admin</a>
                <span class="mx-1.5 text-slate-700">/</span>
                <span>Domains</span>
            </div>
            <h1 class="font-display text-2xl md:text-3xl tracking-[0.2em] uppercase mt-1 text-slate-100">Disposable domains</h1>
            <p class="text-sm text-slate-400 mt-1">
                <span class="tabular-nums text-slate-200">{{ number_format($total) }}</span> domains blocked at signup.
            </p>
        </div>
        <form method="POST" action="{{ route('admin.domains.refresh') }}"
            onsubmit="return confirm('Re-import the disposable domain list from the configured source URL? This replaces the existing list.');">
            @csrf
            <button type="submit"
                class="rounded-lg border border-slate-700 hover:border-slate-500 text-slate-200 px-3 py-2 text-sm transition-colors inline-flex items-center gap-1.5">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/>
                </svg>
                Refresh from source
            </button>
        </form>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Add new --}}
        <section class="rounded-xl border border-slate-800/60 bg-slate-900/40 overflow-hidden">
            <header class="px-5 py-3 border-b border-slate-800/60">
                <h2 class="font-display text-xs uppercase tracking-[0.2em] text-slate-300">Add domain</h2>
            </header>
            <form method="POST" action="{{ route('admin.domains.store') }}" class="px-5 py-4 space-y-3.5">
                @csrf
                <div>
                    <label for="domain" class="block text-xs uppercase tracking-[0.15em] text-slate-400 mb-1.5">Domain</label>
                    <input id="domain" name="domain" type="text" required value="{{ old('domain') }}"
                        placeholder="example.com"
                        class="w-full rounded-lg bg-slate-950/60 border border-slate-800 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 focus:border-rose-500/40 focus:outline-none focus:ring-1 focus:ring-rose-500/20">
                    @error('domain')<p class="mt-1 text-xs text-rose-300">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="source" class="block text-xs uppercase tracking-[0.15em] text-slate-400 mb-1.5">Source <span class="text-slate-600 normal-case tracking-normal">(optional)</span></label>
                    <input id="source" name="source" type="text" maxlength="255" value="{{ old('source') }}"
                        placeholder="manual / report / list-name"
                        class="w-full rounded-lg bg-slate-950/60 border border-slate-800 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 focus:border-rose-500/40 focus:outline-none focus:ring-1 focus:ring-rose-500/20">
                </div>
                <button type="submit"
                    class="w-full rounded-lg bg-rose-500 hover:bg-rose-400 text-white font-semibold px-3 py-2 text-sm transition-colors">
                    Add to blocklist
                </button>
            </form>
        </section>

        {{-- List --}}
        <section class="lg:col-span-2 rounded-xl border border-slate-800/60 bg-slate-900/40 overflow-hidden">
            <header class="px-5 py-3 border-b border-slate-800/60">
                <form method="GET" action="{{ route('admin.domains.index') }}"
                    class="flex items-center gap-2">
                    <div class="relative flex-1">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                        </svg>
                        <input type="text" name="q" value="{{ $search }}" placeholder="Search domain…"
                            class="w-full rounded-lg bg-slate-950/60 border border-slate-800 pl-9 pr-3 py-1.5 text-sm text-slate-100 placeholder-slate-500 focus:border-rose-500/40 focus:outline-none focus:ring-1 focus:ring-rose-500/20">
                    </div>
                    <button type="submit" class="rounded-lg bg-rose-500 hover:bg-rose-400 text-white font-semibold px-3 py-1.5 text-sm transition-colors">Search</button>
                    @if ($search !== '')
                        <a href="{{ route('admin.domains.index') }}" class="text-xs text-slate-400 hover:text-slate-200">Clear</a>
                    @endif
                </form>
            </header>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-800/60 bg-slate-950/30">
                            <th class="text-left px-5 py-2 text-[0.65rem] uppercase tracking-[0.15em] text-slate-400 font-semibold">Domain</th>
                            <th class="text-left px-3 py-2 text-[0.65rem] uppercase tracking-[0.15em] text-slate-400 font-semibold">Source</th>
                            <th class="text-left px-3 py-2 text-[0.65rem] uppercase tracking-[0.15em] text-slate-400 font-semibold">Added</th>
                            <th class="text-right px-5 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/40">
                        @forelse ($domains as $d)
                            <tr class="hover:bg-slate-800/30 transition-colors">
                                <td class="px-5 py-2 text-slate-100 font-mono text-xs">{{ $d->domain }}</td>
                                <td class="px-3 py-2 text-slate-400 text-xs">{{ $d->source ?? '—' }}</td>
                                <td class="px-3 py-2 text-slate-500 text-xs whitespace-nowrap">{{ $d->created_at?->format('M j, Y') }}</td>
                                <td class="px-5 py-2 text-right">
                                    <form method="POST" action="{{ route('admin.domains.destroy', $d->id) }}"
                                        onsubmit="return confirm('Remove {{ addslashes($d->domain) }} from the disposable list?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs text-rose-300 hover:text-rose-200 transition-colors">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-10 text-center text-sm text-slate-500">No domains match this search.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-5 py-3 border-t border-slate-800/60">
                {{ $domains->links() }}
            </div>
        </section>
    </div>
@endsection
