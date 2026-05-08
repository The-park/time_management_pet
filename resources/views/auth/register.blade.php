@extends('layouts.guest')

@section('page_title', 'Create account')

@section('content')
    <h1 class="text-2xl font-semibold mb-6">Create account</h1>

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        <div>
            <label class="block text-sm mb-1" for="name">Name</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus
                class="w-full rounded-md bg-slate-900 border border-slate-700 px-3 py-2">
            @error('name')
                <p class="text-sm text-rose-400 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-sm mb-1" for="email">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required
                class="w-full rounded-md bg-slate-900 border border-slate-700 px-3 py-2">
            @error('email')
                <p class="text-sm text-rose-400 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-sm mb-1" for="timezone">Timezone</label>
            @include('partials.timezone-select', [
                'selected'   => old('timezone'),
                'autodetect' => true,
            ])
            <p class="text-xs text-slate-500 mt-1">Auto-detected from your browser. Change it if you're elsewhere.</p>
            @error('timezone')
                <p class="text-sm text-rose-400 mt-1">{{ $message }}</p>
            @enderror
        </div>

        @php
            $endTimeValue = old('end_of_day_time', '22:00');
            $wakeTimeValue = old('wake_up_time', '07:00');
            $endTimeDisplay = \Carbon\Carbon::createFromFormat('H:i', $endTimeValue)->format('g:i A');
            $wakeTimeDisplay = \Carbon\Carbon::createFromFormat('H:i', $wakeTimeValue)->format('g:i A');
        @endphp
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm mb-1" for="end_of_day_time">End of day</label>
                <input id="end_of_day_time_display" type="text" inputmode="numeric" placeholder="10:00 PM"
                    value="{{ $endTimeDisplay }}" required
                    data-time12
                    data-time12-hidden-id="end_of_day_time"
                    data-time12-error-id="end_of_day_time_error"
                    data-time12-label="End of day"
                    data-time12-min="18:00"
                    data-time12-max="23:59"
                    data-time12-example="10:00 PM"
                    data-time12-group="register"
                    class="w-full rounded-md bg-slate-900 border border-slate-700 px-3 py-2">
                <input id="end_of_day_time" name="end_of_day_time" type="hidden" value="{{ $endTimeValue }}">
                <p id="end_of_day_time_error" class="text-xs text-rose-400 mt-1 hidden" aria-live="polite">
                    Enter time in 12-hour format with AM/PM (example: 10:00 PM).
                </p>
                @error('end_of_day_time')
                    <p class="text-sm text-rose-400 mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="block text-sm mb-1" for="wake_up_time">Wake up</label>
                <input id="wake_up_time_display" type="text" inputmode="numeric" placeholder="7:00 AM"
                    value="{{ $wakeTimeDisplay }}" required
                    data-time12
                    data-time12-hidden-id="wake_up_time"
                    data-time12-error-id="wake_up_time_error"
                    data-time12-label="Wake-up"
                    data-time12-min="04:00"
                    data-time12-max="11:00"
                    data-time12-example="7:00 AM"
                    data-time12-group="register"
                    class="w-full rounded-md bg-slate-900 border border-slate-700 px-3 py-2">
                <input id="wake_up_time" name="wake_up_time" type="hidden" value="{{ $wakeTimeValue }}">
                <p id="wake_up_time_error" class="text-xs text-rose-400 mt-1 hidden" aria-live="polite">
                    Enter time in 12-hour format with AM/PM (example: 7:00 AM).
                </p>
                @error('wake_up_time')
                    <p class="text-sm text-rose-400 mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>
        <div class="text-xs text-slate-400 space-y-1">
            <p>Enter times in <strong>12-hour format with AM/PM</strong>. Casing and spacing are flexible.</p>
            <p>Accepted: <span class="text-slate-300">10:00 PM</span>, <span class="text-slate-300">7 AM</span>, <span class="text-slate-300">10:30pm</span>, <span class="text-slate-300">7 a.m.</span>, <span class="text-slate-300">11.45 PM</span>.</p>
            <p>24-hour input (like <span class="text-slate-300">22:00</span> or <span class="text-slate-300">07:00</span>) is <strong>not accepted</strong> — the Register button will stay disabled until both fields use AM/PM.</p>
            <p>End of day must fall between <strong>6:00 PM and 11:59 PM</strong>. Wake-up must fall between <strong>4:00 AM and 11:00 AM</strong>.</p>
        </div>

        <div>
            <label class="block text-sm mb-1" for="password">Password</label>
            <input id="password" name="password" type="password" required
                class="w-full rounded-md bg-slate-900 border border-slate-700 px-3 py-2">
            @error('password')
                <p class="text-sm text-rose-400 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-sm mb-1" for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required
                class="w-full rounded-md bg-slate-900 border border-slate-700 px-3 py-2">
        </div>

        <button id="register_button" type="submit" data-time12-gate="register"
            class="w-full rounded-md bg-blue-500 text-slate-950 font-semibold py-2 disabled:opacity-50 disabled:cursor-not-allowed">
            Register
        </button>
    </form>

    <div class="mt-4 text-sm">
        <a class="text-blue-300" href="{{ route('login') }}">Already have an account? Log in</a>
    </div>
@endsection
