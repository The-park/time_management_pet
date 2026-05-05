@extends('layouts.guest')

@section('content')
    <h1 class="text-2xl font-semibold mb-6">Reset password</h1>

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        <div>
            <label class="block text-sm mb-1" for="email">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required
                class="w-full rounded-md bg-slate-900 border border-slate-700 px-3 py-2">
            @error('email')
                <p class="text-sm text-rose-400 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="w-full rounded-md bg-blue-500 text-slate-950 font-semibold py-2">
            Send reset link
        </button>
    </form>

    <div class="mt-4 text-sm">
        <a class="text-blue-300" href="{{ route('login') }}">Back to login</a>
    </div>
@endsection
