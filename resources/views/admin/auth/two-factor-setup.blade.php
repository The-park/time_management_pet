@extends('layouts.admin-guest')

@section('content')
    <h1 class="text-2xl font-semibold mb-4">Set up two-factor</h1>
    <p class="text-sm text-rose-200 mb-6">Scan the QR code with your authenticator app, then confirm the code.</p>

    <div class="bg-rose-900 border border-rose-700 rounded-md p-4 mb-6">
        {!! $qrCodeSvg !!}
    </div>

    <div class="mb-6">
        <h2 class="text-sm font-semibold mb-2">Recovery codes</h2>
        <ul class="text-xs text-rose-200 space-y-1">
            @foreach ($recoveryCodes as $code)
                <li>{{ $code }}</li>
            @endforeach
        </ul>
    </div>

    <form method="POST" action="{{ route('admin.two-factor.confirm') }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm mb-1" for="code">Authentication code</label>
            <input id="code" name="code" type="text" inputmode="numeric" required
                class="w-full rounded-md bg-rose-900 border border-rose-700 px-3 py-2">
            @error('code')
                <p class="text-sm text-rose-300 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="w-full rounded-md bg-rose-400 text-rose-950 font-semibold py-2">
            Confirm and continue
        </button>
    </form>
@endsection
