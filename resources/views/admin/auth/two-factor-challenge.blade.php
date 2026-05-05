@extends('layouts.admin-guest')

@section('content')
    <h1 class="text-2xl font-semibold mb-4">Two-factor challenge</h1>
    <p class="text-sm text-rose-200 mb-6">Enter your authentication code or a recovery code.</p>

    <form method="POST" action="{{ route('admin.two-factor.verify') }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm mb-1" for="code">Authentication code</label>
            <input id="code" name="code" type="text" inputmode="numeric"
                class="w-full rounded-md bg-rose-900 border border-rose-700 px-3 py-2">
        </div>

        <div>
            <label class="block text-sm mb-1" for="recovery_code">Recovery code</label>
            <input id="recovery_code" name="recovery_code" type="text"
                class="w-full rounded-md bg-rose-900 border border-rose-700 px-3 py-2">
        </div>

        @error('code')
            <p class="text-sm text-rose-300">{{ $message }}</p>
        @enderror

        <button type="submit" class="w-full rounded-md bg-rose-400 text-rose-950 font-semibold py-2">
            Verify
        </button>
    </form>
@endsection
