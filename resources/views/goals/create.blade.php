@extends('layouts.app')

@section('page_title', 'New goal')

@section('content')
    <div class="relative overflow-hidden rounded-2xl border border-slate-800/60 bg-[radial-gradient(circle_at_top,_rgba(0,224,255,0.15),_transparent_45%)] p-8 mb-8">
        <div class="absolute -right-24 -top-24 h-56 w-56 rounded-full bg-[radial-gradient(circle,_rgba(255,107,26,0.35),_transparent_70%)] blur-2xl"></div>
        <div class="relative">
            <a href="{{ route('goals.index') }}" class="text-xs uppercase tracking-[0.2em] text-slate-400 hover:text-slate-100">← Goals</a>
            <h1 class="mt-2 font-display text-3xl tracking-[0.3em] uppercase">New goal</h1>
            <p class="text-slate-300 text-sm mt-2">Pick a deadline. Log progress regularly. We'll do the math.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('goals.store') }}" class="space-y-6">
        @csrf
        @include('goals.partials.form', ['goal' => null, 'today' => $today])

        <div class="flex justify-end gap-2">
            <a href="{{ route('goals.index') }}"
                class="rounded-lg border border-slate-600 hover:border-slate-400 px-4 py-2 text-sm text-slate-200">
                Cancel
            </a>
            <button type="submit"
                class="rounded-lg bg-[var(--chrono-blue)] text-slate-950 font-semibold px-4 py-2">
                Create goal
            </button>
        </div>
    </form>
@endsection
