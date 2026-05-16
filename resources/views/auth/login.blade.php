@extends('layouts.guest')

@section('page_title', 'Sign in')

@section('content')
    <h1 class="text-2xl font-semibold mb-6">Log in</h1>

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <label class="block text-sm mb-1" for="email">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                class="w-full rounded-md bg-slate-900 border border-slate-700 px-3 py-2">
            @error('email')
                <p class="text-sm text-rose-400 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-sm mb-1" for="password">Password</label>
            <input id="password" name="password" type="password" required
                class="w-full rounded-md bg-slate-900 border border-slate-700 px-3 py-2">
            @error('password')
                <p class="text-sm text-rose-400 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="w-full rounded-md bg-blue-500 text-slate-950 font-semibold py-2">Log in</button>
    </form>

    <div class="mt-4 flex items-center justify-between text-sm">
        <a class="text-blue-300" href="{{ route('password.request') }}">Forgot password?</a>
        <a class="text-blue-300" href="{{ route('register') }}">Create account</a>
    </div>
@endsection
