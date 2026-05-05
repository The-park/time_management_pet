@extends('layouts.admin-guest')

@section('content')
    <h1 class="font-display text-xl tracking-[0.2em] uppercase text-slate-100">Admin sign in</h1>
    <p class="text-sm text-slate-400 mt-1">Use your administrator credentials to continue.</p>

    <form method="POST" action="{{ route('admin.login.store') }}" class="mt-6 space-y-4">
        @csrf

        <div>
            <label class="block text-xs uppercase tracking-[0.2em] text-slate-400 mb-1.5" for="email">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                class="w-full rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 text-slate-100 placeholder-slate-500 focus:border-rose-500/60 focus:outline-none focus:ring-1 focus:ring-rose-500/30">
            @error('email')
                <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-xs uppercase tracking-[0.2em] text-slate-400 mb-1.5" for="password">Password</label>
            <input id="password" name="password" type="password" required autocomplete="current-password"
                class="w-full rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 text-slate-100 placeholder-slate-500 focus:border-rose-500/60 focus:outline-none focus:ring-1 focus:ring-rose-500/30">
            @error('password')
                <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit"
            class="w-full rounded-lg bg-rose-500 hover:bg-rose-400 text-white font-semibold py-2.5 transition-colors">
            Sign in
        </button>
    </form>

    <p class="mt-6 text-xs text-slate-500 text-center">
        Failed attempts are rate-limited and logged.
    </p>
@endsection
