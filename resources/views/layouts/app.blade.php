<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'ChronoLog') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
        <script>
            window.ChronoAuth = {
                isAuthenticated: @json(auth()->check()),
                loginUrl: @json(route('login')),
                registerUrl: @json(route('register')),
            };
        </script>
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
                        @auth
                            <a href="{{ route('history.index') }}" class="hover:text-[var(--chrono-blue)]">History</a>
                            <a href="{{ route('profile.show') }}" class="hover:text-[var(--chrono-blue)]">Profile</a>
                            <a href="{{ route('settings.show') }}" class="hover:text-[var(--chrono-blue)]">Settings</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="hover:text-[var(--chrono-red)]">Log out</button>
                            </form>
                        @else
                            <a href="{{ route('login') }}" class="hover:text-[var(--chrono-blue)]">Sign in</a>
                            <a href="{{ route('register') }}"
                                class="rounded-lg bg-[var(--chrono-blue)] text-slate-950 font-semibold px-3 py-1.5 normal-case tracking-normal hover:opacity-90">
                                Create account
                            </a>
                        @endauth
                    </nav>
                </div>
            </header>

            <main class="mx-auto max-w-6xl px-6 py-10">
                @yield('content')
            </main>
        </div>

        <div id="login_required_modal" role="dialog" aria-modal="true" aria-labelledby="login_required_title" aria-hidden="true"
            class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 backdrop-blur-sm p-4">
            <div class="w-full max-w-md rounded-2xl border border-slate-700/60 bg-[var(--chrono-bg)] p-6 shadow-2xl">
                <h3 id="login_required_title" class="font-display text-base uppercase tracking-[0.2em] text-slate-100">
                    Sign in to continue
                </h3>
                <p class="mt-3 text-sm text-slate-300">
                    Create an account or sign in to <span class="text-slate-100" data-login-prompt-action>do this</span>.
                </p>
                <p class="mt-1 text-xs text-slate-500">
                    Without an account only the <strong>Custom countdown</strong> works — everything else needs your profile.
                </p>
                <div class="mt-5 flex flex-wrap justify-end gap-2">
                    <button type="button" data-login-prompt-cancel
                        class="rounded-lg border border-slate-600 hover:border-slate-400 px-4 py-2 text-sm text-slate-200">
                        Maybe later
                    </button>
                    <a href="{{ route('register') }}"
                        class="rounded-lg border border-[var(--chrono-blue)] hover:bg-[var(--chrono-blue)]/10 px-4 py-2 text-sm font-semibold text-[var(--chrono-blue)]">
                        Create account
                    </a>
                    <a href="{{ route('login') }}"
                        class="rounded-lg bg-[var(--chrono-blue)] hover:opacity-90 px-4 py-2 text-sm font-semibold text-slate-950">
                        Sign in
                    </a>
                </div>
            </div>
        </div>

        <script>
            (() => {
                const modal = document.getElementById('login_required_modal');
                if (!modal) return;
                const actionEl = modal.querySelector('[data-login-prompt-action]');
                const cancelBtn = modal.querySelector('[data-login-prompt-cancel]');

                const open = (action) => {
                    if (actionEl && action) actionEl.textContent = action;
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                    modal.setAttribute('aria-hidden', 'false');
                    document.addEventListener('keydown', onKey);
                };
                const close = () => {
                    modal.classList.remove('flex');
                    modal.classList.add('hidden');
                    modal.setAttribute('aria-hidden', 'true');
                    document.removeEventListener('keydown', onKey);
                };
                const onKey = (e) => { if (e.key === 'Escape') { e.preventDefault(); close(); } };

                modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
                cancelBtn?.addEventListener('click', close);

                window.showLoginRequired = open;
                window.hideLoginRequired = close;
                window.ChronoAuthRequire = (action = 'do this') => {
                    if (window.ChronoAuth?.isAuthenticated) return true;
                    open(action);
                    return false;
                };
            })();
        </script>

        @livewireScripts
        @stack('scripts')
    </body>
</html>
