@extends('layouts.app')

@section('page_title', 'Contact us')

@section('content')
    <div class="max-w-2xl mx-auto">
        <header class="mb-6">
            <h1 class="font-display text-2xl uppercase tracking-[0.25em] text-slate-100">Contact us</h1>
            <p class="mt-2 text-sm text-slate-400">
                Found a bug, have feedback, or want to say hi? Drop us a line and we'll get back to you.
            </p>
        </header>

        @if ($submitted)
            <div class="mb-5 rounded-xl border border-emerald-500/40 bg-emerald-500/10 p-4 text-sm text-emerald-100">
                <p class="font-semibold">Message received.</p>
                <p class="mt-1 text-emerald-200/90">
                    We'll review it shortly and reply to the email you provided.
                </p>
            </div>
        @endif

        <form method="POST" action="{{ route('contact.store') }}"
            class="chrono-panel rounded-2xl p-6 md:p-8 space-y-5">
            @csrf

            {{-- Category chips. Defaults to 'bug' since that's the headline use case. --}}
            <div>
                <label class="block text-xs uppercase tracking-[0.2em] text-slate-400 mb-2">What's this about?</label>
                <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="Message category">
                    @foreach ([
                        'bug' => ['label' => 'Bug report', 'cls' => 'border-rose-500/40 text-rose-200 bg-rose-500/10'],
                        'feedback' => ['label' => 'Feedback / Idea', 'cls' => 'border-emerald-500/40 text-emerald-200 bg-emerald-500/10'],
                        'other' => ['label' => 'Other', 'cls' => 'border-slate-500/40 text-slate-200 bg-slate-500/10'],
                    ] as $value => $opt)
                        @php($checked = old('category', 'bug') === $value)
                        <label class="cursor-pointer">
                            <input type="radio" name="category" value="{{ $value }}" class="peer sr-only"
                                {{ $checked ? 'checked' : '' }}>
                            <span class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs uppercase tracking-[0.2em] transition-colors
                                {{ $opt['cls'] }}
                                opacity-50 hover:opacity-100 peer-checked:opacity-100 peer-checked:ring-1 peer-checked:ring-white/20">
                                {{ $opt['label'] }}
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('category')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
            </div>

            {{-- Name + Email side by side on md+, stacked on mobile. --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs uppercase tracking-[0.2em] text-slate-400 mb-1" for="name">Name</label>
                    <input id="name" name="name" type="text" required maxlength="120"
                        value="{{ old('name', auth()->user()->name ?? '') }}"
                        class="w-full rounded-md bg-slate-900 border border-slate-700 px-3 py-2 text-slate-100">
                    @error('name')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs uppercase tracking-[0.2em] text-slate-400 mb-1" for="email">Email</label>
                    <input id="email" name="email" type="email" required maxlength="191"
                        value="{{ old('email', auth()->user()->email ?? '') }}"
                        class="w-full rounded-md bg-slate-900 border border-slate-700 px-3 py-2 text-slate-100">
                    @error('email')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label class="block text-xs uppercase tracking-[0.2em] text-slate-400 mb-1" for="phone">
                    Phone <span class="text-slate-600 normal-case tracking-normal">(optional)</span>
                </label>
                <input id="phone" name="phone" type="tel" maxlength="40"
                    placeholder="+91 98765 43210"
                    value="{{ old('phone') }}"
                    class="w-full rounded-md bg-slate-900 border border-slate-700 px-3 py-2 text-slate-100">
                @error('phone')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-xs uppercase tracking-[0.2em] text-slate-400 mb-1" for="message">Message</label>
                <textarea id="message" name="message" rows="6" required minlength="10" maxlength="5000"
                    placeholder="Describe the bug, your feedback, or your question. The more detail the better."
                    class="w-full rounded-md bg-slate-900 border border-slate-700 px-3 py-2 text-slate-100 placeholder-slate-500 leading-relaxed resize-y">{{ old('message') }}</textarea>
                @error('message')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
            </div>

            <div class="flex items-center justify-between gap-3 pt-2">
                <p class="text-xs text-slate-500">
                    We never share your details. Submissions are rate-limited to keep bots out.
                </p>
                <button type="submit"
                    class="rounded-lg bg-[var(--chrono-blue)] text-slate-950 font-semibold px-5 py-2 hover:opacity-90 transition-opacity">
                    Send message
                </button>
            </div>
        </form>
    </div>
@endsection
