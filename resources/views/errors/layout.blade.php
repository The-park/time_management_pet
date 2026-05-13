{{-- Shared layout for every error page. Self-contained (no @extends) so it
     renders even when the layout's own session/auth context blew up — error
     pages should NEVER fail to render. Mirrors the visual language of
     layouts/app + layouts/guest so the user doesn't feel teleported. --}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('title') · {{ config('app.name', 'Time Management Pet') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="scanlines min-h-screen bg-[var(--chrono-bg)] text-slate-100">
        <div class="pointer-events-none fixed -top-32 -left-32 h-72 w-72 rounded-full bg-[radial-gradient(circle,_rgba(0,224,255,0.18),_transparent_70%)] blur-3xl"></div>
        <div class="pointer-events-none fixed -bottom-40 -right-40 h-80 w-80 rounded-full bg-[radial-gradient(circle,_rgba(255,107,26,0.18),_transparent_70%)] blur-3xl"></div>

        <header class="border-b border-slate-800/60">
            <div class="mx-auto max-w-6xl px-6 py-4 flex items-center justify-between">
                <a href="{{ url('/') }}" class="flex items-center gap-3 hover:text-[var(--chrono-blue)] transition-colors">
                    <span class="h-3 w-3 rounded-full bg-[var(--chrono-blue)] shadow-[0_0_12px_var(--chrono-blue)]"></span>
                    <span class="font-display text-lg tracking-[0.2em]">Time Management Pet</span>
                </a>
                <nav class="text-sm uppercase tracking-[0.2em] flex items-center gap-6">
                    @auth
                        <a href="{{ url('/') }}" class="hover:text-[var(--chrono-blue)]">Dashboard</a>
                        <a href="{{ route('history.index') }}" class="hover:text-[var(--chrono-blue)]">History</a>
                    @else
                        <a href="{{ url('/') }}" class="hover:text-[var(--chrono-blue)]">Home</a>
                        @if (Route::has('login'))
                            <a href="{{ route('login') }}" class="hover:text-[var(--chrono-blue)]">Log in</a>
                        @endif
                    @endauth
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-16">
            <div class="rounded-2xl border border-slate-800/60 bg-slate-900/40 backdrop-blur-sm p-8 md:p-12 chrono-fade-in">
                <div class="font-display text-[0.65rem] uppercase tracking-[0.4em] text-slate-500">
                    Error @yield('code')
                </div>
                <h1 class="mt-2 font-display text-3xl md:text-4xl uppercase tracking-[0.2em] @yield('heading_class', 'text-rose-300')">
                    @yield('heading')
                </h1>
                <p class="mt-4 text-slate-300 leading-relaxed">
                    @yield('description')
                </p>

                @hasSection('details')
                    <div class="mt-4 rounded-lg border border-slate-800/60 bg-slate-950/40 px-4 py-3 text-xs text-slate-400 font-mono break-all">
                        @yield('details')
                    </div>
                @endif

                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ url('/') }}"
                        class="rounded-lg bg-[var(--chrono-orange)] text-slate-950 font-semibold px-5 py-2.5 text-sm uppercase tracking-[0.15em] hover:opacity-90 transition-opacity">
                        Return to Dashboard
                    </a>
                    @auth
                        <a href="{{ route('history.index') }}"
                            class="rounded-lg border border-slate-700 hover:border-[var(--chrono-blue)]/60 hover:text-[var(--chrono-blue)] px-5 py-2.5 text-sm uppercase tracking-[0.15em] text-slate-200 transition-colors">
                            View history
                        </a>
                    @else
                        @if (Route::has('login'))
                            <a href="{{ route('login') }}"
                                class="rounded-lg border border-slate-700 hover:border-[var(--chrono-blue)]/60 hover:text-[var(--chrono-blue)] px-5 py-2.5 text-sm uppercase tracking-[0.15em] text-slate-200 transition-colors">
                                Log in
                            </a>
                        @endif
                    @endauth
                    <button type="button" onclick="history.back()"
                        class="rounded-lg border border-slate-700 hover:border-slate-400 px-5 py-2.5 text-sm uppercase tracking-[0.15em] text-slate-200 transition-colors">
                        Go back
                    </button>
                </div>
            </div>
        </main>
    </body>
</html>
