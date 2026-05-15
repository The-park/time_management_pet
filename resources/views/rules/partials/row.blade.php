@php
    // $palette comes from the parent (rules/index.blade.php). For the
    // inactive list we still receive a palette but desaturate visually
    // by composing slate utilities on top.
    $p = $palette ?? [
        'name' => 'slate', 'border' => 'border-slate-700/40', 'soft' => 'bg-slate-800/30',
        'glow' => 'shadow-slate-700/10', 'dot' => 'bg-slate-500', 'text' => 'text-slate-300',
        'ring' => 'ring-slate-500/20',
    ];
    $isActive = (bool) $rule->is_active;
    $num = $number ?? null;
@endphp

<li data-rule-row data-rule-id="{{ $rule->id }}"
    class="group relative overflow-hidden rounded-2xl border {{ $isActive ? $p['border'] : 'border-slate-800/50' }}
           {{ $isActive ? $p['soft'] : 'bg-slate-900/30' }}
           hover:-translate-y-0.5 hover:shadow-lg {{ $isActive ? $p['glow'] : '' }}
           transition-all duration-200">

    {{-- Coloured left rail. Wider + softer than before. --}}
    <span class="absolute inset-y-0 left-0 w-1.5 {{ $isActive ? $p['dot'] : 'bg-slate-700/50' }}
                 {{ $isActive ? 'opacity-80' : 'opacity-40' }}"></span>

    <div class="pl-6 pr-4 py-4 flex items-center gap-4">
        {{-- Numbered colour-badge. Replaces the bare bullet for a clearer
             ordered-list feel. Hidden on inactive rules (they're not
             numbered because order doesn't matter while paused). --}}
        @if ($isActive && $num !== null)
            <div class="shrink-0 flex flex-col items-center gap-1">
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl
                             {{ $p['soft'] }} ring-1 {{ $p['ring'] }} font-display text-sm font-bold {{ $p['text'] }}">
                    {{ $num }}
                </span>
                <div class="flex flex-col gap-0.5">
                    <button type="button" data-rule-up
                        class="text-slate-500 hover:text-slate-100 transition-colors h-3.5 w-3.5 leading-none flex items-center justify-center text-[10px]"
                        aria-label="Move up">▲</button>
                    <button type="button" data-rule-down
                        class="text-slate-500 hover:text-slate-100 transition-colors h-3.5 w-3.5 leading-none flex items-center justify-center text-[10px]"
                        aria-label="Move down">▼</button>
                </div>
            </div>
        @else
            {{-- Pause icon on inactive rules so the user can see at a
                 glance why the row looks subdued. --}}
            <div class="shrink-0 flex h-9 w-9 items-center justify-center rounded-xl bg-slate-800/50 text-slate-500">
                <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24" class="h-4 w-4">
                    <rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/>
                </svg>
            </div>
        @endif

        <div class="flex-1 min-w-0">
            <div data-rule-view class="leading-snug">
                <p class="{{ $isActive ? 'text-slate-50' : 'text-slate-500 line-through' }} text-[15px] md:text-base font-medium break-words">
                    {{ $rule->text }}
                </p>
            </div>

            <form data-rule-form method="POST" action="{{ route('rules.update', $rule->id) }}" class="hidden items-center gap-2 flex-wrap sm:flex-nowrap">
                @csrf
                @method('PUT')
                <input type="text" name="text" maxlength="255" minlength="3" required
                    data-rule-input data-original="{{ $rule->text }}" value="{{ $rule->text }}"
                    class="flex-1 min-w-0 rounded-lg bg-slate-950/70 border border-slate-700 px-3 py-2 text-sm text-slate-100
                           focus:border-sky-400/70 focus:outline-none focus:ring-2 focus:ring-sky-400/30 transition-colors">
                <div class="flex items-center gap-2">
                    <button type="submit"
                        class="rounded-lg bg-slate-100 text-slate-950 font-semibold px-3 py-2 text-xs hover:bg-white transition-colors">Save</button>
                    <button type="button" data-rule-cancel
                        class="rounded-lg border border-slate-600 text-slate-300 px-3 py-2 text-xs hover:border-slate-400 transition-colors">Cancel</button>
                </div>
            </form>
        </div>

        <div class="shrink-0 flex items-center gap-1">
            <button type="button" data-rule-edit
                class="inline-flex items-center justify-center h-9 w-9 rounded-lg
                       text-slate-400 hover:text-slate-100 hover:bg-slate-800/60 transition-all"
                aria-label="Edit rule" title="Edit">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zM19.5 19.5h-15"/>
                </svg>
            </button>

            <form method="POST" action="{{ route('rules.toggle', $rule->id) }}" class="inline">
                @csrf
                <button type="submit"
                    class="inline-flex items-center justify-center h-9 w-9 rounded-lg
                           text-slate-400 hover:bg-slate-800/60 transition-all
                           {{ $isActive ? 'hover:text-amber-300' : 'hover:text-emerald-300' }}"
                    aria-label="{{ $isActive ? 'Pause rule' : 'Resume rule' }}"
                    title="{{ $isActive ? 'Pause' : 'Resume' }}">
                    @if ($isActive)
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                            <rect x="6" y="5" width="4" height="14" rx="1"/>
                            <rect x="14" y="5" width="4" height="14" rx="1"/>
                        </svg>
                    @else
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
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
                    class="inline-flex items-center justify-center h-9 w-9 rounded-lg
                           text-slate-400 hover:text-rose-300 hover:bg-rose-500/10 transition-all"
                    aria-label="Delete rule" title="Delete">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                    </svg>
                </button>
            </form>
        </div>
    </div>
</li>
