@extends('layouts.guest')

@section('content')
    <h1 class="text-2xl font-semibold mb-6">Choose a new password</h1>

    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <label class="block text-sm mb-1" for="email">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email', $request->email) }}" required
                class="w-full rounded-md bg-slate-900 border border-slate-700 px-3 py-2">
            @error('email')
                <p class="text-sm text-rose-400 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-sm mb-1" for="password">Password</label>
            <input id="password" name="password" type="password" required data-pw-strength
                autocomplete="new-password"
                class="w-full rounded-md bg-slate-900 border border-slate-700 px-3 py-2">
            @error('password')
                <p class="text-sm text-rose-400 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-sm mb-1" for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required
                autocomplete="new-password"
                class="w-full rounded-md bg-slate-900 border border-slate-700 px-3 py-2">
        </div>

        <button type="submit" class="w-full rounded-md bg-blue-500 text-slate-950 font-semibold py-2">
            Reset password
        </button>
    </form>
@endsection
