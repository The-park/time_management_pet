@extends('layouts.app')

@section('page_title', 'Goal log · '.$goal->title)

@section('content')
    <div class="relative overflow-hidden rounded-2xl border border-slate-800/60 bg-[radial-gradient(circle_at_top,_rgba(0,224,255,0.15),_transparent_45%)] p-8 mb-6">
        <div class="absolute -right-24 -top-24 h-56 w-56 rounded-full bg-[radial-gradient(circle,_rgba(255,107,26,0.35),_transparent_70%)] blur-2xl"></div>
        <div class="relative">
            <a href="{{ route('goals.show', $goal) }}" class="text-xs uppercase tracking-[0.2em] text-slate-400 hover:text-slate-100">← {{ $goal->title }}</a>
            <h1 class="mt-2 font-display text-3xl tracking-[0.3em] uppercase">Goal log</h1>
            <p class="text-slate-300 text-sm mt-2">Every change, every progress entry, every reason — kept for accountability.</p>
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3">
            <div class="text-[0.6rem] uppercase tracking-wider text-slate-500">Total entries</div>
            <div class="mt-1 font-digital text-2xl text-slate-100">{{ $logs->total() }}</div>
        </div>
        <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3">
            <div class="text-[0.6rem] uppercase tracking-wider text-slate-500">Times extended</div>
            <div class="mt-1 font-digital text-2xl {{ $goal->extension_count > 0 ? 'text-amber-300' : 'text-slate-100' }}">
                {{ $goal->extension_count }}
            </div>
        </div>
        <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3">
            <div class="text-[0.6rem] uppercase tracking-wider text-slate-500">Times changed</div>
            <div class="mt-1 font-digital text-2xl text-slate-100">{{ $goal->change_count }}</div>
        </div>
        <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-3">
            <div class="text-[0.6rem] uppercase tracking-wider text-slate-500">Status</div>
            <div class="mt-1 text-lg text-slate-100">{{ ucfirst($goal->status) }}</div>
        </div>
    </div>

    @php
        // Field-name humaniser: storage uses snake_case; we want the UI to
        // read like English ("Target date" instead of "target_date").
        $humanizeKey = function (string $key): string {
            return ucfirst(str_replace('_', ' ', $key));
        };

        // Smart value formatter:
        //   - YYYY-MM-DD strings render as "May 6, 2026 · Wed"
        //   - ISO 8601 datetimes render as "May 6, 2026 · 11:23 AM"
        //   - Arrays of strings (keywords) render as colored chips
        //   - Booleans render as Yes / No
        //   - null / empty render as the em-dash placeholder
        //   - Everything else: plain text
        $renderValue = function ($value, string $tone) {
            if ($value === null || $value === '' || (is_array($value) && empty($value))) {
                return '<span class="text-slate-600">—</span>';
            }
            if (is_bool($value)) {
                return $value
                    ? '<span class="text-emerald-300">Yes</span>'
                    : '<span class="text-slate-400">No</span>';
            }
            if (is_array($value)) {
                // Strings → chip list (keywords); otherwise inline JSON.
                if (collect($value)->every(fn ($v) => is_string($v))) {
                    $chipBg = $tone === 'old' ? 'bg-rose-500/10 border-rose-500/30 text-rose-200'
                                              : 'bg-emerald-500/10 border-emerald-500/30 text-emerald-200';
                    $chips = collect($value)->take(20)->map(function ($v) use ($chipBg) {
                        return '<span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[0.65rem] '.$chipBg.'">'
                            . e($v) . '</span>';
                    })->implode(' ');
                    if (count($value) > 20) {
                        $chips .= ' <span class="text-slate-500 text-[0.65rem]">+'.(count($value) - 20).' more</span>';
                    }
                    return '<div class="flex flex-wrap gap-1">'.$chips.'</div>';
                }
                return '<code class="text-xs text-slate-300">'.e(json_encode($value, JSON_UNESCAPED_SLASHES)).'</code>';
            }
            $str = (string) $value;
            // Date-only YYYY-MM-DD
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $str)) {
                try {
                    $dt = \Carbon\Carbon::parse($str);
                    return '<span>'.e($dt->format('M j, Y')).' <span class="text-slate-500">· '.e($dt->format('D')).'</span></span>';
                } catch (\Throwable $e) { /* fall through */ }
            }
            // ISO datetime
            if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:/', $str)) {
                try {
                    $dt = \Carbon\Carbon::parse($str);
                    return '<span>'.e($dt->format('M j, Y')).' <span class="text-slate-500">· '.e($dt->format('g:i A')).'</span></span>';
                } catch (\Throwable $e) { /* fall through */ }
            }
            // Long text (description) — preserve newlines but cap width.
            if (mb_strlen($str) > 80) {
                return '<span class="block whitespace-pre-line">'.e($str).'</span>';
            }
            return '<span>'.e($str).'</span>';
        };

        // Categorise actions: which ones should auto-expand the diff
        // (because the diff *is* the news), which collapse by default.
        $autoOpenActions = ['extended', 'shortened', 'edited'];
    @endphp

    <section class="chrono-panel rounded-2xl p-6 md:p-8">
        @if ($logs->isEmpty())
            <p class="text-sm text-slate-500">No log entries yet.</p>
        @else
            <ol class="relative space-y-5 border-l border-slate-800/60 pl-6">
                @foreach ($logs as $log)
                    @php
                        // Full class strings, statically present so Tailwind's
                        // JIT scanner can pick them up.
                        $accent = match ($log->action) {
                            'created' => ['bg-sky-400', 'text-sky-300'],
                            'extended' => ['bg-amber-400', 'text-amber-300'],
                            'shortened' => ['bg-sky-400', 'text-sky-300'],
                            'edited' => ['bg-slate-400', 'text-slate-300'],
                            'progress_added' => ['bg-emerald-400', 'text-emerald-300'],
                            'completed' => ['bg-emerald-400', 'text-emerald-300'],
                            'abandoned' => ['bg-rose-400', 'text-rose-300'],
                            'reopened' => ['bg-sky-400', 'text-sky-300'],
                            default => ['bg-slate-400', 'text-slate-300'],
                        };

                        // Gather the union of keys across before/after so we
                        // can render a single change-row per field.
                        $oldValues = is_array($log->old_value) ? $log->old_value : [];
                        $newValues = is_array($log->new_value) ? $log->new_value : [];
                        $allKeys = array_keys(array_merge($oldValues, $newValues));
                        // Only render keys that actually moved (or that exist
                        // exclusively on one side — created/abandoned cases).
                        $changedKeys = collect($allKeys)->filter(function ($k) use ($oldValues, $newValues) {
                            $hasOld = array_key_exists($k, $oldValues);
                            $hasNew = array_key_exists($k, $newValues);
                            if ($hasOld !== $hasNew) return true;
                            return ($oldValues[$k] ?? null) !== ($newValues[$k] ?? null);
                        })->values();
                        $hasChanges = $changedKeys->isNotEmpty();
                        $isInitial = $log->action === 'created' && empty($oldValues);
                        $autoOpen = in_array($log->action, $autoOpenActions, true);
                    @endphp
                    <li class="relative">
                        <span class="absolute -left-[31px] top-1 h-3 w-3 rounded-full border-2 border-slate-900 {{ $accent[0] }}"></span>
                        <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-4">
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="text-[0.65rem] uppercase tracking-wider {{ $accent[1] }} font-semibold">
                                        {{ $log->actionLabel() }}
                                    </span>
                                    @if ($hasChanges)
                                        <span class="rounded-full border border-slate-700/60 bg-slate-900/60 px-1.5 py-0.5 text-[0.6rem] uppercase tracking-wider text-slate-400">
                                            {{ $changedKeys->count() }} {{ Str::plural('field', $changedKeys->count()) }}
                                        </span>
                                    @endif
                                </div>
                                <time class="text-[0.65rem] text-slate-500" datetime="{{ $log->created_at->toIso8601String() }}">
                                    {{ $log->created_at->format('M j, Y · g:i A') }}
                                </time>
                            </div>

                            @if ($log->reason)
                                <p class="mt-2 text-sm text-slate-200 italic">"{{ $log->reason }}"</p>
                            @endif

                            @if ($hasChanges)
                                <details class="mt-3 group/diff" {{ $autoOpen ? 'open' : '' }}>
                                    <summary class="cursor-pointer inline-flex items-center gap-1.5 text-[0.65rem] uppercase tracking-wider text-slate-500 hover:text-slate-200 transition-colors">
                                        <svg class="h-3 w-3 text-slate-500 transition-transform group-open/diff:rotate-90" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                        {{ $isInitial ? 'Initial settings' : 'What changed' }}
                                    </summary>

                                    <div class="mt-3 rounded-lg border border-slate-800/60 bg-slate-950/40 divide-y divide-slate-800/40">
                                        @foreach ($changedKeys as $key)
                                            @php
                                                $oldVal = $oldValues[$key] ?? null;
                                                $newVal = $newValues[$key] ?? null;
                                                $hasOld = array_key_exists($key, $oldValues);
                                                $hasNew = array_key_exists($key, $newValues);
                                            @endphp
                                            <div class="grid grid-cols-1 md:grid-cols-[10rem_1fr] gap-x-4 gap-y-1 px-4 py-3 text-sm">
                                                <div class="text-[0.65rem] uppercase tracking-wider text-slate-400 md:pt-0.5">
                                                    {{ $humanizeKey($key) }}
                                                </div>
                                                <div class="space-y-1.5">
                                                    @if ($isInitial)
                                                        {{-- "Initial settings" — no before, just the value. --}}
                                                        <div class="flex items-start gap-2">
                                                            <span class="inline-flex items-center justify-center mt-0.5 h-4 w-4 shrink-0 rounded-full bg-emerald-500/15 text-emerald-300 text-[0.7rem] font-bold">+</span>
                                                            <div class="text-emerald-200 min-w-0 flex-1">{!! $renderValue($newVal, 'new') !!}</div>
                                                        </div>
                                                    @elseif ($hasOld && ! $hasNew)
                                                        <div class="flex items-start gap-2">
                                                            <span class="inline-flex items-center justify-center mt-0.5 h-4 w-4 shrink-0 rounded-full bg-rose-500/15 text-rose-300 text-[0.7rem] font-bold">−</span>
                                                            <div class="text-slate-400 line-through min-w-0 flex-1">{!! $renderValue($oldVal, 'old') !!}</div>
                                                        </div>
                                                    @elseif (! $hasOld && $hasNew)
                                                        <div class="flex items-start gap-2">
                                                            <span class="inline-flex items-center justify-center mt-0.5 h-4 w-4 shrink-0 rounded-full bg-emerald-500/15 text-emerald-300 text-[0.7rem] font-bold">+</span>
                                                            <div class="text-emerald-200 min-w-0 flex-1">{!! $renderValue($newVal, 'new') !!}</div>
                                                        </div>
                                                    @else
                                                        <div class="flex items-start gap-2">
                                                            <span class="inline-flex items-center justify-center mt-0.5 h-4 w-4 shrink-0 rounded-full bg-rose-500/15 text-rose-300 text-[0.7rem] font-bold">−</span>
                                                            <div class="text-slate-400 line-through min-w-0 flex-1">{!! $renderValue($oldVal, 'old') !!}</div>
                                                        </div>
                                                        <div class="flex items-start gap-2">
                                                            <span class="inline-flex items-center justify-center mt-0.5 h-4 w-4 shrink-0 rounded-full bg-emerald-500/15 text-emerald-300 text-[0.7rem] font-bold">+</span>
                                                            <div class="text-emerald-200 min-w-0 flex-1">{!! $renderValue($newVal, 'new') !!}</div>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    {{-- Power-user escape hatch: the original
                                         JSON, hidden behind a second toggle. --}}
                                    <details class="mt-2 group/raw">
                                        <summary class="cursor-pointer inline-flex items-center gap-1.5 text-[0.6rem] uppercase tracking-wider text-slate-600 hover:text-slate-400 transition-colors">
                                            <svg class="h-2.5 w-2.5 transition-transform group-open/raw:rotate-90" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                            View raw JSON
                                        </summary>
                                        <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                                            @if ($log->old_value)
                                                <div>
                                                    <div class="text-[0.6rem] uppercase tracking-wider text-rose-400 mb-1">Before</div>
                                                    <pre class="rounded-lg bg-slate-950/60 border border-slate-800/60 p-2 text-rose-200 whitespace-pre-wrap break-words">{{ json_encode($log->old_value, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre>
                                                </div>
                                            @endif
                                            @if ($log->new_value)
                                                <div>
                                                    <div class="text-[0.6rem] uppercase tracking-wider text-emerald-400 mb-1">After</div>
                                                    <pre class="rounded-lg bg-slate-950/60 border border-slate-800/60 p-2 text-emerald-200 whitespace-pre-wrap break-words">{{ json_encode($log->new_value, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre>
                                                </div>
                                            @endif
                                        </div>
                                    </details>
                                </details>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>

            <div class="mt-6">
                {{ $logs->links() }}
            </div>
        @endif
    </section>
@endsection
