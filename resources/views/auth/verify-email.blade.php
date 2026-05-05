@extends('layouts.guest')

@section('content')
    <h1 class="text-2xl font-semibold mb-4">Verify your email</h1>
    <p class="text-sm text-slate-300 mb-6">
        Thanks for signing up. Please verify your email address by clicking the link we sent.
    </p>

    @if (session('status') === 'verification-link-sent')
        <p class="text-sm text-emerald-400 mb-4">A new verification link has been sent to your email address.</p>
    @endif

    <div class="flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="rounded-md bg-blue-500 text-slate-950 font-semibold px-4 py-2">
                Resend link
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-sm text-slate-300">Log out</button>
        </form>
    </div>
@endsection
