@extends('layouts.app')

@section('page_title', 'Edit goal')

@section('content')
    <div class="relative overflow-hidden rounded-2xl border border-slate-800/60 bg-[radial-gradient(circle_at_top,_rgba(0,224,255,0.15),_transparent_45%)] p-8 mb-8">
        <div class="absolute -right-24 -top-24 h-56 w-56 rounded-full bg-[radial-gradient(circle,_rgba(255,107,26,0.35),_transparent_70%)] blur-2xl"></div>
        <div class="relative">
            <a href="{{ route('goals.show', $goal) }}" class="text-xs uppercase tracking-[0.2em] text-slate-400 hover:text-slate-100">← {{ $goal->title }}</a>
            <h1 class="mt-2 font-display text-3xl tracking-[0.3em] uppercase">Edit goal</h1>
            <p class="text-slate-300 text-sm mt-2">All changes are recorded in the goal's log.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('goals.update', $goal) }}" class="space-y-6">
        @csrf
        @method('PUT')

        @include('goals.partials.form', ['goal' => $goal, 'today' => $today])

        <section class="chrono-panel rounded-2xl p-6 md:p-8">
            <h2 class="font-display text-sm uppercase tracking-[0.3em] text-slate-300 mb-1">Why this change?</h2>
            <p class="text-xs text-slate-500 mb-4">Required. Goes into the goal log so future-you can see the reasoning.</p>
            <textarea name="reason" rows="3" maxlength="500" required
                class="w-full rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 text-slate-100"
                placeholder="e.g. exam moved to a new venue, scope was bigger than expected, …">{{ old('reason') }}</textarea>
            @error('reason')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
        </section>

        <div class="flex justify-end gap-2">
            <a href="{{ route('goals.show', $goal) }}"
                class="rounded-lg border border-slate-600 hover:border-slate-400 px-4 py-2 text-sm text-slate-200">
                Cancel
            </a>
            <button type="submit"
                class="rounded-lg bg-[var(--chrono-blue)] text-slate-950 font-semibold px-4 py-2">
                Save changes
            </button>
        </div>
    </form>
@endsection
