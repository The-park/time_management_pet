@extends('layouts.admin-guest')

@section('content')
    <h1 class="text-2xl font-semibold mb-6">Admin login</h1>

    <form method="POST" action="{{ route('admin.login.store') }}" class="space-y-4">
        @csrf

        <div>
            <label class="block text-sm mb-1" for="email">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                class="w-full rounded-md bg-rose-900 border border-rose-700 px-3 py-2">
            @error('email')
                <p class="text-sm text-rose-300 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-sm mb-1" for="password">Password</label>
            <input id="password" name="password" type="password" required
                class="w-full rounded-md bg-rose-900 border border-rose-700 px-3 py-2">
            @error('password')
                <p class="text-sm text-rose-300 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="w-full rounded-md bg-rose-400 text-rose-950 font-semibold py-2">Log in</button>
    </form>
@endsection
