@extends('layouts.admin')

@section('page_title', 'New quote')

@section('content')
    <div class="mb-6">
        <div class="text-xs uppercase tracking-[0.2em] text-slate-500">
            <a href="{{ route('admin.dashboard') }}" class="hover:text-slate-300">Admin</a>
            <span class="mx-1.5 text-slate-700">/</span>
            <a href="{{ route('admin.quotes.index') }}" class="hover:text-slate-300">Quotes</a>
            <span class="mx-1.5 text-slate-700">/</span>
            <span>New</span>
        </div>
        <h1 class="font-display text-2xl md:text-3xl tracking-[0.2em] uppercase mt-1 text-slate-100">New quote</h1>
    </div>

    <section class="max-w-2xl rounded-xl border border-slate-800/60 bg-slate-900/40 overflow-hidden">
        <form method="POST" action="{{ route('admin.quotes.store') }}" class="px-5 py-5 space-y-4">
            @csrf
            @include('admin.quotes._form', ['quote' => null, 'categories' => $categories])

            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                    class="rounded-lg bg-rose-500 hover:bg-rose-400 text-white font-semibold px-4 py-2 text-sm transition-colors">
                    Save quote
                </button>
                <a href="{{ route('admin.quotes.index') }}" class="text-xs text-slate-400 hover:text-slate-200">Cancel</a>
            </div>
        </form>
    </section>
@endsection
