<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Time Management Pet') }} · Admin</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
        <div class="pointer-events-none fixed -top-32 -left-32 h-72 w-72 rounded-full bg-[radial-gradient(circle,_rgba(244,63,94,0.15),_transparent_70%)] blur-3xl"></div>
        <div class="pointer-events-none fixed -bottom-40 -right-40 h-80 w-80 rounded-full bg-[radial-gradient(circle,_rgba(244,63,94,0.10),_transparent_70%)] blur-3xl"></div>

        <div class="min-h-screen flex flex-col items-center justify-center px-6 py-12">
            <div class="mb-8 flex items-center gap-2.5">
                <div class="h-2 w-2 rounded-full bg-rose-400 shadow-[0_0_10px_#fb7185]"></div>
                <div>
                    <div class="font-display text-sm tracking-[0.2em] text-slate-100">Admin Console</div>
                    <div class="text-[0.6rem] uppercase tracking-wider text-slate-500 mt-0.5">Time Management Pet</div>
                </div>
            </div>

            <div class="chrono-fade-in w-full max-w-md rounded-2xl border border-slate-800/60 bg-slate-900/40 backdrop-blur-sm p-7 shadow-2xl">
                @yield('content')
            </div>

            <p class="mt-6 text-xs text-slate-500">
                Restricted to authorised personnel. Activity is logged.
            </p>
        </div>
    </body>
</html>
