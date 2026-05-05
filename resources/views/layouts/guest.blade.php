<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@hasSection('page_title')@yield('page_title') · @endif{{ config('app.name', 'Track Your Time') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="scanlines min-h-screen bg-[var(--chrono-bg)] text-slate-100">
        {{-- Subtle ambient glows so the auth pages feel like part of the same world. --}}
        <div class="pointer-events-none fixed -top-32 -left-32 h-72 w-72 rounded-full bg-[radial-gradient(circle,_rgba(0,224,255,0.18),_transparent_70%)] blur-3xl"></div>
        <div class="pointer-events-none fixed -bottom-40 -right-40 h-80 w-80 rounded-full bg-[radial-gradient(circle,_rgba(255,107,26,0.18),_transparent_70%)] blur-3xl"></div>

        <div class="min-h-screen flex flex-col items-center justify-center px-6 py-12">
            <a href="/" class="mb-8 inline-flex items-center gap-2 text-slate-400 hover:text-slate-100 transition-colors">
                <span class="h-2 w-2 rounded-full bg-[var(--chrono-blue)] shadow-[0_0_10px_var(--chrono-blue)]"></span>
                <span class="font-display text-sm tracking-[0.3em] uppercase">Track Your Time</span>
            </a>
            <div class="chrono-fade-in w-full max-w-md rounded-2xl border border-slate-800/60 bg-slate-900/40 backdrop-blur-sm p-6 md:p-8">
                @yield('content')
            </div>
        </div>
    </body>
</html>
