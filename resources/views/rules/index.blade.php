@extends('layouts.app')

@section('page_title', 'Rules')

@section('content')
    @php
        $active = $rules->where('is_active', true)->values();
        $inactive = $rules->where('is_active', false)->values();
    @endphp

    <div class="relative overflow-hidden rounded-2xl border border-slate-800/60 bg-[radial-gradient(circle_at_top,_rgba(0,224,255,0.15),_transparent_45%)] p-8 mb-8">
        <div class="absolute -right-24 -top-24 h-56 w-56 rounded-full bg-[radial-gradient(circle,_rgba(0,224,255,0.25),_transparent_70%)] blur-2xl"></div>
        <div class="relative">
            <h1 class="font-display text-3xl tracking-[0.3em] uppercase">Rules I follow</h1>
            <p class="text-slate-300 text-sm mt-2">The principles you've chosen to live by — small reminders that surface throughout the day.</p>
        </div>
    </div>

    <section class="chrono-panel rounded-2xl p-6 md:p-8 mb-6">
        <h2 class="font-display text-sm uppercase tracking-[0.3em] text-slate-300 mb-4">Add a new rule</h2>
        <form method="POST" action="{{ route('rules.store') }}" class="flex flex-col sm:flex-row gap-3">
            @csrf
            <input type="text" name="text" maxlength="255" required minlength="3"
                placeholder="e.g. No screens during the first hour after waking"
                value="{{ old('text') }}"
                class="flex-1 rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 text-slate-100 placeholder-slate-500 focus:border-[var(--chrono-blue)] focus:outline-none focus:ring-1 focus:ring-[var(--chrono-blue)]/40">
            <button type="submit"
                class="inline-flex items-center justify-center rounded-lg bg-[var(--chrono-blue)] text-slate-950 font-semibold px-5 py-2 hover:opacity-90 transition-opacity">
                + Add
            </button>
        </form>
        @error('text')
            <p class="mt-2 text-sm text-rose-400">{{ $message }}</p>
        @enderror
    </section>

    @if ($active->isEmpty() && $inactive->isEmpty())
        <div class="chrono-panel rounded-2xl p-8 text-center">
            <p class="text-slate-200 text-base font-semibold">No rules yet — add your first principle above.</p>
            <p class="text-slate-400 text-sm mt-2">Examples: "Sleep before midnight." · "Deep work before email." · "No phone at meals."</p>
        </div>
    @else
        <section class="space-y-3">
            <div class="flex items-baseline justify-between gap-4">
                <h2 class="font-display text-sm uppercase tracking-[0.3em] text-slate-300">Active</h2>
                <span class="text-xs text-slate-500">{{ $active->count() }} {{ Str::plural('rule', $active->count()) }}</span>
            </div>

            @if ($active->isEmpty())
                <div class="chrono-panel rounded-2xl p-6 text-center text-sm text-slate-400">
                    All your rules are paused. Resume one below to surface it again.
                </div>
            @else
                <ul id="rules_list" class="space-y-2">
                    @foreach ($active as $i => $rule)
                        @include('rules.partials.row', ['rule' => $rule, 'index' => $i, 'last' => $i === $active->count() - 1])
                    @endforeach
                </ul>
            @endif
        </section>

        @if ($inactive->isNotEmpty())
            <section class="mt-8">
                <details class="group">
                    <summary class="cursor-pointer list-none flex items-center justify-between gap-4 px-4 py-3 rounded-xl border border-slate-800/60 bg-slate-900/40 hover:border-slate-700 transition-colors">
                        <span class="font-display text-xs uppercase tracking-[0.3em] text-slate-300">
                            {{ $inactive->count() }} inactive {{ Str::plural('rule', $inactive->count()) }}
                        </span>
                        <span class="text-slate-500 group-open:rotate-180 transition-transform">▾</span>
                    </summary>
                    <ul class="mt-3 space-y-2">
                        @foreach ($inactive as $i => $rule)
                            @include('rules.partials.row', ['rule' => $rule, 'index' => $i, 'last' => true])
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

                // Inline edit: swap the static text for an input, swap the
                // pencil for save/cancel. Submitting PUTs to rules.update.
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
