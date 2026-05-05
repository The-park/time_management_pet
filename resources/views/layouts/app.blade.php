<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'ChronoLog') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="scanlines bg-[var(--chrono-bg)] text-slate-100">
        <div class="min-h-screen relative">
            <header class="border-b border-slate-800/60">
                <div class="mx-auto max-w-6xl px-6 py-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="h-3 w-3 rounded-full bg-[var(--chrono-blue)] shadow-[0_0_12px_var(--chrono-blue)]"></div>
                        <span class="font-display text-lg tracking-[0.2em]">ChronoLog</span>
                    </div>
                    <nav class="text-sm uppercase tracking-[0.2em] flex items-center gap-6">
                        <a href="{{ route('dashboard') }}" class="hover:text-[var(--chrono-blue)]">Dashboard</a>
                        <a href="/history" class="hover:text-[var(--chrono-blue)]">History</a>
                        <a href="/settings" class="hover:text-[var(--chrono-blue)]">Settings</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="hover:text-[var(--chrono-red)]">Log out</button>
                        </form>
                    </nav>
                </div>
            </header>

            <main class="mx-auto max-w-6xl px-6 py-10">
                @yield('content')
            </main>
        </div>

        @livewireScripts
        @stack('scripts')
    </body>
</html>
