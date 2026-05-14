@extends('layouts.admin')

@section('page_title', 'Edit quote')

@section('content')
    <div class="mb-6">
        <div class="text-xs uppercase tracking-[0.2em] text-slate-500">
            <a href="{{ route('admin.dashboard') }}" class="hover:text-slate-300">Admin</a>
            <span class="mx-1.5 text-slate-700">/</span>
            <a href="{{ route('admin.quotes.index') }}" class="hover:text-slate-300">Quotes</a>
            <span class="mx-1.5 text-slate-700">/</span>
            <span>Edit #{{ $quote->id }}</span>
        </div>
        <h1 class="font-display text-2xl md:text-3xl tracking-[0.2em] uppercase mt-1 text-slate-100">Edit quote</h1>
    </div>

    <section class="max-w-2xl rounded-xl border border-slate-800/60 bg-slate-900/40 overflow-hidden">
        <form method="POST" action="{{ route('admin.quotes.update', $quote->id) }}" class="px-5 py-5 space-y-4">
            @csrf
            @method('PUT')
            @include('admin.quotes._form', ['quote' => $quote, 'categories' => $categories])

            <div class="flex items-center justify-between gap-3 pt-2">
                <div class="flex items-center gap-3">
                    <button type="submit"
                        class="rounded-lg bg-rose-500 hover:bg-rose-400 text-white font-semibold px-4 py-2 text-sm transition-colors">
                        Save changes
                    </button>
                    <a href="{{ route('admin.quotes.index') }}" class="text-xs text-slate-400 hover:text-slate-200">Cancel</a>
                </div>
            </div>
        </form>

        <div class="px-5 py-4 border-t border-slate-800/60 flex items-center justify-between gap-3">
            <form method="POST" action="{{ route('admin.quotes.toggle', $quote->id) }}">
                @csrf
                <button type="submit" class="text-xs text-slate-300 hover:text-slate-100 transition-colors">
                    {{ $quote->is_active ? 'Disable this quote' : 'Enable this quote' }}
                </button>
            </form>
            <form method="POST" action="{{ route('admin.quotes.destroy', $quote->id) }}"
                onsubmit="return confirm('Delete this quote permanently?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-xs text-rose-300 hover:text-rose-200 transition-colors">Delete</button>
            </form>
        </div>
    </section>
@endsection
