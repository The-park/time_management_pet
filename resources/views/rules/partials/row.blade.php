<li data-rule-row data-rule-id="{{ $rule->id }}"
    class="group relative overflow-hidden rounded-xl border border-slate-800/60 bg-slate-900/40 hover:border-slate-700 transition-colors">
    <span class="absolute inset-y-0 left-0 w-1 {{ $rule->is_active ? 'bg-[var(--chrono-blue)]/70' : 'bg-slate-700/40' }}"></span>

    <div class="pl-5 pr-3 py-3 flex items-center gap-3">
        @if ($rule->is_active)
            <div class="flex flex-col gap-0.5 shrink-0">
                <button type="button" data-rule-up
                    class="text-slate-500 hover:text-[var(--chrono-blue)] transition-colors h-4 w-4 leading-none flex items-center justify-center"
                    aria-label="Move up">▲</button>
                <button type="button" data-rule-down
                    class="text-slate-500 hover:text-[var(--chrono-blue)] transition-colors h-4 w-4 leading-none flex items-center justify-center"
                    aria-label="Move down">▼</button>
            </div>
        @endif

        <div class="flex-1 min-w-0">
            <div data-rule-view class="flex items-center gap-2 text-slate-100">
                <span class="text-[var(--chrono-blue)] text-base leading-none">•</span>
                <span class="text-sm {{ $rule->is_active ? '' : 'text-slate-500 line-through' }}">{{ $rule->text }}</span>
            </div>

            <form data-rule-form method="POST" action="{{ route('rules.update', $rule->id) }}" class="hidden flex items-center gap-2">
                @csrf
                @method('PUT')
                <input type="text" name="text" maxlength="255" minlength="3" required
                    data-rule-input data-original="{{ $rule->text }}" value="{{ $rule->text }}"
                    class="flex-1 rounded-md bg-slate-950/60 border border-slate-700 px-3 py-1.5 text-sm text-slate-100 focus:border-[var(--chrono-blue)] focus:outline-none focus:ring-1 focus:ring-[var(--chrono-blue)]/40">
                <button type="submit"
                    class="rounded-md bg-[var(--chrono-blue)] text-slate-950 font-semibold px-3 py-1.5 text-xs hover:opacity-90">Save</button>
                <button type="button" data-rule-cancel
                    class="rounded-md border border-slate-600 text-slate-300 px-3 py-1.5 text-xs hover:border-slate-400">Cancel</button>
            </form>
        </div>

        <div class="shrink-0 flex items-center gap-1.5">
            <button type="button" data-rule-edit
                class="inline-flex items-center justify-center h-8 w-8 rounded-md border border-slate-700/60 hover:border-[var(--chrono-blue)]/60 hover:text-[var(--chrono-blue)] text-slate-400 transition-colors"
                aria-label="Edit rule" title="Edit">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
            </button>

            <form method="POST" action="{{ route('rules.toggle', $rule->id) }}" class="inline">
                @csrf
                <button type="submit"
                    class="inline-flex items-center justify-center h-8 w-8 rounded-md border border-slate-700/60 {{ $rule->is_active ? 'hover:border-amber-400/60 hover:text-amber-300' : 'hover:border-emerald-400/60 hover:text-emerald-300' }} text-slate-400 transition-colors"
                    aria-label="{{ $rule->is_active ? 'Pause rule' : 'Resume rule' }}"
                    title="{{ $rule->is_active ? 'Pause' : 'Resume' }}">
                    @if ($rule->is_active)
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

            <form method="POST" action="{{ route('rules.destroy', $rule->id) }}" class="inline"
                onsubmit="return confirm('Delete this rule? This cannot be undone.');">
                @csrf
                @method('DELETE')
                <button type="submit"
                    class="inline-flex items-center justify-center h-8 w-8 rounded-md border border-slate-700/60 hover:border-rose-500/60 hover:text-rose-300 text-slate-400 transition-colors"
                    aria-label="Delete rule" title="Delete">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/>
                    </svg>
                </button>
            </form>
        </div>
    </div>
</li>
