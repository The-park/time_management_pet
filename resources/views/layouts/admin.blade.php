<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'ChronoLog') }} Admin</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="scanlines bg-rose-950 text-rose-50">
        <div class="min-h-screen relative">
            <header class="border-b border-rose-800/60">
                <div class="mx-auto max-w-6xl px-6 py-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="h-3 w-3 rounded-full bg-rose-400 shadow-[0_0_12px_#fb7185]"></div>
                        <span class="font-display text-lg tracking-[0.2em]">ChronoLog Admin</span>
                    </div>
                    <nav class="text-sm uppercase tracking-[0.2em] flex items-center gap-6">
                        <a href="/admin/users" class="hover:text-rose-200">Users</a>
                        <a href="/admin/audit" class="hover:text-rose-200">Audit</a>
                        <form method="POST" action="{{ route('admin.logout') }}">
                            @csrf
                            <button type="submit" class="hover:text-rose-200">Log out</button>
                        </form>
                    </nav>
                </div>
            </header>

            <main class="mx-auto max-w-6xl px-6 py-10">
                @yield('content')
            </main>
        </div>
    </body>
</html>
