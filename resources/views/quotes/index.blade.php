@extends('layouts.app')

@section('page_title', 'My quotes')

@section('content')
    @php
        $active = $quotes->where('is_active', true)->values();
        $inactive = $quotes->where('is_active', false)->values();
        $oldCategory = old('category', 'productive');
        $oldIsActive = old('is_active', true);
    @endphp

    <div class="relative overflow-hidden rounded-2xl border border-slate-800/60 bg-[radial-gradient(circle_at_top,_rgba(251,191,36,0.18),_transparent_45%)] p-8 mb-8">
        <div class="absolute -right-24 -top-24 h-56 w-56 rounded-full bg-[radial-gradient(circle,_rgba(251,191,36,0.30),_transparent_70%)] blur-2xl"></div>
        <div class="relative">
            <h1 class="font-display text-3xl tracking-[0.3em] uppercase">My quotes</h1>
            <p class="text-slate-300 text-sm mt-2">Your own motivational lines — they'll join the flying-quote bubble whenever you've picked "My quotes only" or "Mixed" in <a href="{{ route('settings.show') }}" class="text-amber-300 hover:underline">settings</a>.</p>
        </div>
    </div>

    <section class="chrono-panel rounded-2xl p-6 md:p-8 mb-6">
        <h2 class="font-display text-sm uppercase tracking-[0.3em] text-slate-300 mb-4">Add a new quote</h2>
        <form method="POST" action="{{ route('quotes.store') }}" class="space-y-3">
            @csrf

            <div>
                <label class="block text-[0.65rem] uppercase tracking-[0.2em] text-slate-400 mb-1" for="quote_text">Quote</label>
                <textarea id="quote_text" name="text" required minlength="5" maxlength="500" rows="2"
                    placeholder="e.g. The pain you feel today will be the strength you feel tomorrow."
                    class="w-full rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 text-slate-100 placeholder-slate-500 focus:border-amber-400 focus:outline-none focus:ring-1 focus:ring-amber-400/40">{{ old('text') }}</textarea>
                @error('text')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-[0.65rem] uppercase tracking-[0.2em] text-slate-400 mb-1" for="quote_author">Author <span class="text-slate-600 normal-case tracking-normal">(optional)</span></label>
                    <input id="quote_author" name="author" type="text" maxlength="120" value="{{ old('author') }}"
                        placeholder="Eren Yeager"
                        class="w-full rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 text-slate-100 placeholder-slate-500 focus:border-amber-400 focus:outline-none focus:ring-1 focus:ring-amber-400/40">
                    @error('author')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-[0.65rem] uppercase tracking-[0.2em] text-slate-400 mb-1" for="quote_source">Source <span class="text-slate-600 normal-case tracking-normal">(optional)</span></label>
                    <input id="quote_source" name="source" type="text" maxlength="120" value="{{ old('source') }}"
                        placeholder="Attack on Titan"
                        class="w-full rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 text-slate-100 placeholder-slate-500 focus:border-amber-400 focus:outline-none focus:ring-1 focus:ring-amber-400/40">
                    @error('source')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-[0.65rem] uppercase tracking-[0.2em] text-slate-400 mb-1" for="quote_category">Category</label>
                    <select id="quote_category" name="category" required
                        class="w-full rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 text-slate-100 focus:border-amber-400 focus:outline-none focus:ring-1 focus:ring-amber-400/40">
                        @foreach ($categories as $c)
                            <option value="{{ $c }}" @selected($oldCategory === $c)>{{ \App\Models\Quote::categoryLabel($c) }}</option>
                        @endforeach
                    </select>
                    @error('category')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="flex items-center justify-between gap-3 pt-1">
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" @checked($oldIsActive)
                        class="h-4 w-4 rounded border-slate-600 bg-slate-950 text-amber-400 focus:ring-amber-400/40">
                    <span class="text-sm text-slate-200">Active <span class="text-xs text-slate-500">(eligible for the bubble)</span></span>
                </label>
                <button type="submit"
                    class="inline-flex items-center justify-center rounded-lg bg-amber-400 text-slate-950 font-semibold px-5 py-2 hover:opacity-90 transition-opacity">
                    + Add quote
                </button>
            </div>
        </form>
    </section>

    @if ($active->isEmpty() && $inactive->isEmpty())
        <div class="chrono-panel rounded-2xl p-8 text-center">
            <p class="text-slate-200 text-base font-semibold">No quotes yet — add your first above.</p>
            <p class="text-slate-400 text-sm mt-2">Tip: short, punchy lines work best in the floating bubble.</p>
        </div>
    @else
        <section class="space-y-3">
            <div class="flex items-baseline justify-between gap-4">
                <h2 class="font-display text-sm uppercase tracking-[0.3em] text-slate-300">Active</h2>
                <span class="text-xs text-slate-500">{{ $active->count() }} {{ Str::plural('quote', $active->count()) }}</span>
            </div>

            @if ($active->isEmpty())
                <div class="chrono-panel rounded-2xl p-6 text-center text-sm text-slate-400">
                    All your quotes are paused. Resume one below to bring it back.
                </div>
            @else
                <ul class="space-y-2">
                    @foreach ($active as $quote)
                        @include('quotes.partials.row', ['quote' => $quote, 'categories' => $categories])
                    @endforeach
                </ul>
            @endif
        </section>

        @if ($inactive->isNotEmpty())
            <section class="mt-8">
                <details class="group">
                    <summary class="cursor-pointer list-none flex items-center justify-between gap-4 px-4 py-3 rounded-xl border border-slate-800/60 bg-slate-900/40 hover:border-slate-700 transition-colors">
                        <span class="font-display text-xs uppercase tracking-[0.3em] text-slate-300">
                            {{ $inactive->count() }} paused {{ Str::plural('quote', $inactive->count()) }}
                        </span>
                        <span class="text-slate-500 group-open:rotate-180 transition-transform">▾</span>
                    </summary>
                    <ul class="mt-3 space-y-2">
                        @foreach ($inactive as $quote)
                            @include('quotes.partials.row', ['quote' => $quote, 'categories' => $categories])
                        @endforeach
                    </ul>
                </details>
            </section>
        @endif
    @endif

    <p class="mt-6 text-center text-xs text-slate-500">
        <span class="text-slate-300">{{ $adminCount }}</span> curated {{ Str::plural('quote', $adminCount) }} available
        · <span class="text-slate-300">{{ $myCount }}</span> of your own
    </p>

    @push('scripts')
        <script>
            // Inline edit: swap the row's static view for an editable form
            // on pencil-click, restore on cancel. Same pattern as Rules.
            (() => {
                document.addEventListener('click', (e) => {
                    const editBtn = e.target.closest('[data-quote-edit]');
                    if (editBtn) {
                        const row = editBtn.closest('[data-quote-row]');
                        if (!row) return;
                        row.querySelector('[data-quote-view]')?.classList.add('hidden');
                        row.querySelector('[data-quote-form]')?.classList.remove('hidden');
                        row.querySelector('[data-quote-text-input]')?.focus();
                        return;
                    }
                    const cancelBtn = e.target.closest('[data-quote-cancel]');
                    if (cancelBtn) {
                        const row = cancelBtn.closest('[data-quote-row]');
                        if (!row) return;
                        row.querySelector('[data-quote-view]')?.classList.remove('hidden');
                        row.querySelector('[data-quote-form]')?.classList.add('hidden');
                        // Reset inputs to their data-original values.
                        row.querySelectorAll('[data-original]').forEach((el) => {
                            el.value = el.dataset.original ?? '';
                        });
                    }
                });
            })();
        </script>
    @endpush
@endsection
