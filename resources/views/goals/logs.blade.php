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

    <section class="chrono-panel rounded-2xl p-6 md:p-8">
        @if ($logs->isEmpty())
            <p class="text-sm text-slate-500">No log entries yet.</p>
        @else
            <ol class="relative space-y-5 border-l border-slate-800/60 pl-6">
                @foreach ($logs as $log)
                    @php
                        $accent = match ($log->action) {
                            'created' => 'sky',
                            'extended' => 'amber',
                            'shortened' => 'sky',
                            'edited' => 'slate',
                            'progress_added' => 'emerald',
                            'completed' => 'emerald',
                            'abandoned' => 'rose',
                            'reopened' => 'sky',
                            default => 'slate',
                        };
                    @endphp
                    <li class="relative">
                        <span class="absolute -left-[31px] top-1 h-3 w-3 rounded-full border-2 border-slate-900 bg-{{ $accent }}-400"></span>
                        <div class="rounded-xl border border-slate-800/60 bg-slate-900/40 p-4">
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <div>
                                    <span class="text-[0.65rem] uppercase tracking-wider text-{{ $accent }}-300 font-semibold">
                                        {{ $log->actionLabel() }}
                                    </span>
                                </div>
                                <time class="text-[0.65rem] text-slate-500" datetime="{{ $log->created_at->toIso8601String() }}">
                                    {{ $log->created_at->format('M j, Y · g:i A') }}
                                </time>
                            </div>

                            @if ($log->reason)
                                <p class="mt-2 text-sm text-slate-200 italic">"{{ $log->reason }}"</p>
                            @endif

                            @if ($log->old_value || $log->new_value)
                                <details class="mt-3">
                                    <summary class="cursor-pointer text-[0.65rem] uppercase tracking-wider text-slate-500 hover:text-slate-300">
                                        Show diff
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
