<li data-quote-row data-quote-id="{{ $quote->id }}"
    class="group relative overflow-hidden rounded-xl border border-slate-800/60 bg-slate-900/40 hover:border-slate-700 transition-colors">
    <span class="absolute inset-y-0 left-0 w-1 {{ $quote->is_active ? 'bg-amber-400/70' : 'bg-slate-700/40' }}"></span>

    <div class="pl-5 pr-3 py-3 flex items-start gap-3">
        <div class="flex-1 min-w-0">
            {{-- ── view mode ────────────────────────────────────── --}}
            <div data-quote-view>
                <p class="text-sm {{ $quote->is_active ? 'text-slate-100' : 'text-slate-500 line-through' }}">
                    <span class="text-amber-300/80 mr-1">"</span>{{ $quote->text }}<span class="text-amber-300/80 ml-1">"</span>
                </p>
                <p class="mt-1 text-[0.7rem] text-slate-500 flex flex-wrap gap-x-2 gap-y-0.5">
                    @if ($quote->author)<span>— {{ $quote->author }}</span>@endif
                    @if ($quote->source)<span class="text-slate-600">· {{ $quote->source }}</span>@endif
                    <span class="text-slate-600">· {{ \App\Models\Quote::categoryLabel($quote->category) }}</span>
                </p>
            </div>

            {{-- ── edit mode ────────────────────────────────────── --}}
            <form data-quote-form method="POST" action="{{ route('quotes.update', $quote->id) }}" class="hidden space-y-2">
                @csrf
                @method('PUT')

                <textarea name="text" required minlength="5" maxlength="500" rows="2"
                    data-quote-text-input data-original="{{ $quote->text }}"
                    class="w-full rounded-md bg-slate-950/60 border border-slate-700 px-3 py-1.5 text-sm text-slate-100 focus:border-amber-400 focus:outline-none focus:ring-1 focus:ring-amber-400/40">{{ $quote->text }}</textarea>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                    <input type="text" name="author" maxlength="120" value="{{ $quote->author }}"
                        data-original="{{ $quote->author }}"
                        placeholder="Author (optional)"
                        class="rounded-md bg-slate-950/60 border border-slate-700 px-3 py-1.5 text-sm text-slate-100 focus:border-amber-400 focus:outline-none focus:ring-1 focus:ring-amber-400/40">
                    <input type="text" name="source" maxlength="120" value="{{ $quote->source }}"
                        data-original="{{ $quote->source }}"
                        placeholder="Source (optional)"
                        class="rounded-md bg-slate-950/60 border border-slate-700 px-3 py-1.5 text-sm text-slate-100 focus:border-amber-400 focus:outline-none focus:ring-1 focus:ring-amber-400/40">
                    <select name="category" required
                        data-original="{{ $quote->category }}"
                        class="rounded-md bg-slate-950/60 border border-slate-700 px-3 py-1.5 text-sm text-slate-100 focus:border-amber-400 focus:outline-none focus:ring-1 focus:ring-amber-400/40">
                        @foreach ($categories as $c)
                            <option value="{{ $c }}" @selected($quote->category === $c)>{{ \App\Models\Quote::categoryLabel($c) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center justify-between gap-2 pt-1">
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" @checked($quote->is_active)
                            class="h-4 w-4 rounded border-slate-600 bg-slate-950 text-amber-400 focus:ring-amber-400/40">
                        <span class="text-xs text-slate-300">Active</span>
                    </label>
                    <div class="flex gap-2">
                        <button type="submit"
                            class="rounded-md bg-amber-400 text-slate-950 font-semibold px-3 py-1.5 text-xs hover:opacity-90">Save</button>
                        <button type="button" data-quote-cancel
                            class="rounded-md border border-slate-600 text-slate-300 px-3 py-1.5 text-xs hover:border-slate-400">Cancel</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="shrink-0 flex items-center gap-1.5">
            <button type="button" data-quote-edit
                class="inline-flex items-center justify-center h-8 w-8 rounded-md border border-slate-700/60 hover:border-amber-400/60 hover:text-amber-300 text-slate-400 transition-colors"
                aria-label="Edit quote" title="Edit">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
            </button>

            <form method="POST" action="{{ route('quotes.toggle', $quote->id) }}" class="inline">
                @csrf
                <button type="submit"
                    class="inline-flex items-center justify-center h-8 w-8 rounded-md border border-slate-700/60 {{ $quote->is_active ? 'hover:border-amber-400/60 hover:text-amber-300' : 'hover:border-emerald-400/60 hover:text-emerald-300' }} text-slate-400 transition-colors"
                    aria-label="{{ $quote->is_active ? 'Pause quote' : 'Resume quote' }}"
                    title="{{ $quote->is_active ? 'Pause' : 'Resume' }}">
                    @if ($quote->is_active)
                        <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 24 24">
                            <rect x="6" y="5" width="4" height="14" rx="1"/>
                            <rect x="14" y="5" width="4" height="14" rx="1"/>
                        </svg>
                    @else
                        <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M8 5v14l11-7L8 5z"/>
                        </svg>
                    @endif
                </button>
            </form>

            <form method="POST" action="{{ route('quotes.destroy', $quote->id) }}" class="inline"
                onsubmit="return confirm('Delete this quote? This cannot be undone.');">
                @csrf
                @method('DELETE')
                <button type="submit"
                    class="inline-flex items-center justify-center h-8 w-8 rounded-md border border-slate-700/60 hover:border-rose-500/60 hover:text-rose-300 text-slate-400 transition-colors"
                    aria-label="Delete quote" title="Delete">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/>
                    </svg>
                </button>
            </form>
        </div>
    </div>
</li>
