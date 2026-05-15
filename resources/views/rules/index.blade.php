@extends('layouts.app')

@section('page_title', 'Rules')

@section('content')
    @php
        $active = $rules->where('is_active', true)->values();
        $inactive = $rules->where('is_active', false)->values();
        // Rotating, eye-soothing palette. Each rule picks a tone by its
        // position in the active list, so adjacent rules never share a hue.
        // Kept low-saturation against the slate background to stay calm.
        $rulePalette = [
            ['name' => 'emerald', 'border' => 'border-emerald-400/40', 'soft' => 'bg-emerald-400/10', 'glow' => 'shadow-emerald-500/20', 'dot' => 'bg-emerald-400', 'text' => 'text-emerald-200', 'ring' => 'ring-emerald-400/30'],
            ['name' => 'sky',     'border' => 'border-sky-400/40',     'soft' => 'bg-sky-400/10',     'glow' => 'shadow-sky-500/20',     'dot' => 'bg-sky-400',     'text' => 'text-sky-200',     'ring' => 'ring-sky-400/30'],
            ['name' => 'violet',  'border' => 'border-violet-400/40',  'soft' => 'bg-violet-400/10',  'glow' => 'shadow-violet-500/20',  'dot' => 'bg-violet-400',  'text' => 'text-violet-200',  'ring' => 'ring-violet-400/30'],
            ['name' => 'amber',   'border' => 'border-amber-400/40',   'soft' => 'bg-amber-400/10',   'glow' => 'shadow-amber-500/20',   'dot' => 'bg-amber-400',   'text' => 'text-amber-200',   'ring' => 'ring-amber-400/30'],
            ['name' => 'rose',    'border' => 'border-rose-400/40',    'soft' => 'bg-rose-400/10',    'glow' => 'shadow-rose-500/20',    'dot' => 'bg-rose-400',    'text' => 'text-rose-200',    'ring' => 'ring-rose-400/30'],
            ['name' => 'teal',    'border' => 'border-teal-400/40',    'soft' => 'bg-teal-400/10',    'glow' => 'shadow-teal-500/20',    'dot' => 'bg-teal-400',    'text' => 'text-teal-200',    'ring' => 'ring-teal-400/30'],
        ];
    @endphp

    {{-- Hero: a soft multi-stop radial gradient that hints at the palette
         used below, without being loud. --}}
    <div class="relative overflow-hidden rounded-3xl border border-slate-800/60 p-8 md:p-10 mb-8
                bg-[radial-gradient(circle_at_15%_20%,_rgba(52,211,153,0.18),_transparent_45%),radial-gradient(circle_at_85%_30%,_rgba(167,139,250,0.18),_transparent_50%),radial-gradient(circle_at_50%_100%,_rgba(56,189,248,0.15),_transparent_60%)]">
        <div class="relative flex flex-col md:flex-row md:items-center gap-6">
            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-slate-900/60 border border-slate-700/60 text-emerald-300 shadow-inner">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="h-7 w-7">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                          d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div class="flex-1">
                <h1 class="font-display text-3xl md:text-4xl tracking-[0.18em] uppercase text-slate-50">Rules I follow</h1>
                <p class="text-slate-300/90 text-sm md:text-base mt-2 max-w-xl leading-relaxed">
                    Small, kind reminders of the principles you've chosen to live by — they appear gently throughout your day.
                </p>
            </div>
            @if ($active->isNotEmpty())
                <div class="flex items-center gap-2 self-start md:self-auto">
                    <span class="inline-flex items-center gap-2 rounded-full border border-emerald-400/30 bg-emerald-400/10 text-emerald-200 px-3.5 py-1.5 text-xs font-semibold tracking-wide">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        {{ $active->count() }} active
                    </span>
                </div>
            @endif
        </div>
    </div>

    {{-- Add-a-rule card. Bigger input, friendlier copy. --}}
    <section class="rounded-2xl border border-slate-800/60 bg-slate-900/40 p-5 md:p-6 mb-8">
        <div class="flex items-center gap-2 mb-3">
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-sky-400/15 text-sky-300">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="h-4 w-4" stroke-width="2.2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                </svg>
            </span>
            <h2 class="font-display text-xs uppercase tracking-[0.3em] text-slate-300">Add a new rule</h2>
        </div>
        <form method="POST" action="{{ route('rules.store') }}" class="flex flex-col sm:flex-row gap-3">
            @csrf
            <input type="text" name="text" maxlength="255" required minlength="3"
                placeholder="e.g. No screens during the first hour after waking"
                value="{{ old('text') }}"
                class="flex-1 rounded-xl bg-slate-950/60 border border-slate-700/80 px-4 py-3 text-base text-slate-100 placeholder-slate-500
                       focus:border-sky-400/60 focus:outline-none focus:ring-2 focus:ring-sky-400/30 transition-colors">
            <button type="submit"
                class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-gradient-to-br from-emerald-400 to-sky-400 text-slate-950 font-semibold px-6 py-3 text-sm
                       hover:from-emerald-300 hover:to-sky-300 active:scale-[0.98] shadow-lg shadow-emerald-500/20 transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="h-4 w-4" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                </svg>
                Add rule
            </button>
        </form>
        @error('text')
            <p class="mt-3 text-sm text-rose-300 flex items-center gap-1.5">
                <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24" class="h-4 w-4"><path d="M12 2L1 21h22L12 2zm0 6l7.5 13h-15L12 8zm-1 4v4h2v-4h-2zm0 5v2h2v-2h-2z"/></svg>
                {{ $message }}
            </p>
        @enderror
    </section>

    @if ($active->isEmpty() && $inactive->isEmpty())
        <div class="rounded-2xl border border-slate-700/40 bg-slate-900/30 p-10 text-center">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-800/60 mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="h-7 w-7 text-slate-400" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z"/>
                </svg>
            </div>
            <p class="text-slate-100 text-base font-semibold">No rules yet — add your first principle above.</p>
            <p class="text-slate-400 text-sm mt-2 max-w-md mx-auto">
                Try: <span class="text-emerald-300">"Sleep before midnight."</span> ·
                <span class="text-sky-300">"Deep work before email."</span> ·
                <span class="text-violet-300">"No phone at meals."</span>
            </p>
        </div>
    @else
        @if ($active->isNotEmpty())
            <section class="mb-6">
                <div class="flex items-baseline justify-between gap-4 mb-3 px-1">
                    <h2 class="font-display text-xs uppercase tracking-[0.3em] text-slate-300">Active rules</h2>
                    <span class="text-xs text-slate-500">drag the arrows to reorder</span>
                </div>

                <ul id="rules_list" class="space-y-3">
                    @foreach ($active as $i => $rule)
                        @include('rules.partials.row', [
                            'rule' => $rule,
                            'index' => $i,
                            'last' => $i === $active->count() - 1,
                            'palette' => $rulePalette[$i % count($rulePalette)],
                            'number' => $i + 1,
                        ])
                    @endforeach
                </ul>
            </section>
        @else
            <div class="rounded-2xl border border-slate-700/40 bg-slate-900/30 p-6 text-center text-sm text-slate-400 mb-6">
                All your rules are paused. Resume one below to bring it back.
            </div>
        @endif

        @if ($inactive->isNotEmpty())
            <section>
                <details class="group rounded-2xl border border-slate-800/60 bg-slate-900/20 open:bg-slate-900/30 transition-colors">
                    <summary class="cursor-pointer list-none flex items-center justify-between gap-4 px-5 py-4 hover:bg-slate-900/30 transition-colors rounded-2xl">
                        <span class="flex items-center gap-3">
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-slate-800/60 text-slate-400">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24" class="h-4 w-4"><rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/></svg>
                            </span>
                            <span class="font-display text-xs uppercase tracking-[0.3em] text-slate-300">
                                {{ $inactive->count() }} paused {{ Str::plural('rule', $inactive->count()) }}
                            </span>
                        </span>
                        <span class="text-slate-500 group-open:rotate-180 transition-transform">▾</span>
                    </summary>
                    <ul class="px-3 pb-3 pt-1 space-y-2">
                        @foreach ($inactive as $i => $rule)
                            @include('rules.partials.row', [
                                'rule' => $rule,
                                'index' => $i,
                                'last' => true,
                                'palette' => $rulePalette[$i % count($rulePalette)],
                                'number' => null,
                            ])
                        @endforeach
                    </ul>
                </details>
            </section>
        @endif
    @endif

    @push('scripts')
        <script>
            (() => {
                const list = document.getElementById('rules_list');
                if (!list) return;

                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

                list.addEventListener('click', (e) => {
                    const editBtn = e.target.closest('[data-rule-edit]');
                    if (editBtn) {
                        const row = editBtn.closest('[data-rule-row]');
                        row.querySelector('[data-rule-view]').classList.add('hidden');
                        row.querySelector('[data-rule-form]').classList.remove('hidden');
                        row.querySelector('[data-rule-input]').focus();
                        return;
                    }
                    const cancelBtn = e.target.closest('[data-rule-cancel]');
                    if (cancelBtn) {
                        const row = cancelBtn.closest('[data-rule-row]');
                        row.querySelector('[data-rule-view]').classList.remove('hidden');
                        row.querySelector('[data-rule-form]').classList.add('hidden');
                        const input = row.querySelector('[data-rule-input]');
                        input.value = input.dataset.original || '';
                        return;
                    }
                    const upBtn = e.target.closest('[data-rule-up]');
                    const downBtn = e.target.closest('[data-rule-down]');
                    if (upBtn || downBtn) {
                        const row = (upBtn || downBtn).closest('[data-rule-row]');
                        if (upBtn && row.previousElementSibling) {
                            list.insertBefore(row, row.previousElementSibling);
                            persistOrder();
                        } else if (downBtn && row.nextElementSibling) {
                            list.insertBefore(row.nextElementSibling, row);
                            persistOrder();
                        }
                    }
                });

                const persistOrder = () => {
                    const ids = Array.from(list.querySelectorAll('[data-rule-row]'))
                        .map(r => Number(r.dataset.ruleId))
                        .filter(n => Number.isFinite(n));
                    fetch(@json(route('rules.reorder')), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ ids }),
                    }).catch(() => {});
                };
            })();
        </script>
    @endpush
@endsection
