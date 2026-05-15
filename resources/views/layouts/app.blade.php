<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@hasSection('page_title')@yield('page_title') · @endif{{ config('app.name', 'Time Management Pet') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
        <script>
            window.ChronoAuth = {
                isAuthenticated: @json(auth()->check()),
                loginUrl: @json(route('login')),
                registerUrl: @json(route('register')),
            };
        </script>
        @auth
            @php
                // Inject the user's persisted time blocks as JSON so that
                // localStorage gets hydrated synchronously, before any
                // page-specific script reads it. Without this, a fresh
                // browser / cleared cache / different device would render
                // an empty dashboard / empty history page even though the
                // user's data still lives in the database.
                $serverBlocks = \App\Models\TimeBlock::query()
                    ->where('user_id', auth()->id())
                    ->orderBy('start_time')
                    ->get()
                    ->map(function ($b) {
                        return [
                            'id' => $b->external_id ?: ('srv_'.$b->id),
                            'date' => $b->start_time->toDateString(),
                            'start' => $b->start_time->format('H:i'),
                            'end' => $b->end_time?->format('H:i'),
                            'durationMs' => (int) $b->duration_seconds * 1000,
                            'label' => (string) ($b->reason ?? ''),
                            'category' => $b->category,
                            // Preserves the user's manual chip toggle across
                            // page refreshes — without this, the dashboard's
                            // auto-classify migration loop would re-run on
                            // every load and overwrite the user's choice.
                            'categoryManual' => (bool) $b->category_manual,
                            'auto_filled' => (bool) $b->auto_filled,
                            'status' => 'completed',
                        ];
                    })
                    ->values();
            @endphp
            <script>
                // Synchronous hydration: merge the server's time blocks into
                // localStorage BEFORE any page-specific JS reads from it.
                // - If localStorage is empty (fresh browser, cleared cache,
                //   different device, or another logout/login cycle), this
                //   restores the user's data from the server.
                // - If localStorage already has the same id, the server's
                //   record wins (server is the source of truth).
                // - Local-only blocks (an offline save not yet synced) are
                //   preserved.
                (() => {
                    const KEY = 'chrono.timeBlocks.v1';
                    const server = @json($serverBlocks);
                    let local = [];
                    try {
                        const raw = localStorage.getItem(KEY);
                        local = raw ? (JSON.parse(raw) || []) : [];
                        if (!Array.isArray(local)) local = [];
                    } catch { local = []; }

                    const serverIds = new Set(server.map(b => b.id));
                    const localOnly = local.filter(b => b && b.id && !serverIds.has(b.id));
                    const merged = [...server, ...localOnly];

                    try {
                        if (merged.length === 0) {
                            localStorage.removeItem(KEY);
                        } else {
                            localStorage.setItem(KEY, JSON.stringify(merged));
                        }
                    } catch {}

                    // Tell page-specific scripts the local cache is now in
                    // sync with the server, so they can safely run their
                    // own outgoing /time-blocks/sync calls.
                    window.ChronoBlocksHydrated = true;
                })();
            </script>
        @endauth
    </head>
    <body class="scanlines bg-[var(--chrono-bg)] text-slate-100">
        <div class="min-h-screen relative">
            <header class="border-b border-slate-800/60">
                <div class="mx-auto max-w-6xl px-6 py-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="h-3 w-3 rounded-full bg-[var(--chrono-blue)] shadow-[0_0_12px_var(--chrono-blue)]"></div>
                        <a href="{{ route('dashboard') }}" class="font-display text-lg tracking-[0.2em] hover:text-[var(--chrono-blue)] transition-colors">Time Management Pet</a>
                    </div>
                    <nav class="text-sm uppercase tracking-[0.2em] flex items-center gap-6">
                        <a href="{{ route('dashboard') }}" class="hover:text-[var(--chrono-blue)]">Dashboard</a>
                        @auth
                            <a href="{{ route('goals.index') }}" class="hover:text-[var(--chrono-blue)]">Goals</a>
                            <a href="{{ route('history.index') }}" class="hover:text-[var(--chrono-blue)]">History</a>
                            <a href="{{ route('settings.show') }}" class="hover:text-[var(--chrono-blue)]">Settings</a>
                            <a href="{{ route('contact.show') }}" class="hover:text-[var(--chrono-blue)]">Contact</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="hover:text-[var(--chrono-red)]">Log out</button>
                            </form>
                        @else
                            <a href="{{ route('contact.show') }}" class="hover:text-[var(--chrono-blue)]">Contact</a>
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
                @auth
                    @if (! auth()->user()->hasVerifiedEmail())
                        <div class="mb-6 rounded-2xl border border-amber-500/30 bg-amber-500/10 p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div class="text-sm text-amber-100">
                                <span class="font-semibold">Email not verified.</span>
                                We sent a verification link to <span class="text-amber-50">{{ auth()->user()->email }}</span>.
                                @if (session('status') === 'verification-link-sent')
                                    <span class="text-emerald-200">A new link was just sent — check your inbox.</span>
                                @endif
                            </div>
                            <form method="POST" action="{{ route('verification.send') }}" class="shrink-0">
                                @csrf
                                <button type="submit"
                                    class="rounded-lg border border-amber-400/60 bg-amber-500/20 hover:bg-amber-500/30 text-amber-100 text-xs font-semibold uppercase tracking-[0.2em] px-3 py-1.5">
                                    Resend link
                                </button>
                            </form>
                        </div>
                    @endif
                @endauth
                @if (session('status') || session('toast'))
                    @php($toastMessage = session('toast') ?? match (session('status')) {
                        'profile-updated' => 'Profile saved.',
                        'profile-updated-email-changed' => 'Profile saved — verification link sent to your new email.',
                        'password-updated' => 'Password updated.',
                        'settings-updated' => 'Settings saved.',
                        default => null,
                    })
                    @if ($toastMessage)
                        <div data-toast-from-server data-toast-message="{{ $toastMessage }}" class="hidden"></div>
                    @endif
                @endif
                @yield('content')
            </main>

            <footer class="mt-12 border-t border-slate-800/60">
                <div class="mx-auto max-w-6xl px-6 py-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-xs text-slate-500">
                    <div class="flex items-center gap-2">
                        <span class="font-display tracking-[0.2em] text-slate-300">Time Management Pet</span>
                        <span class="text-slate-700">·</span>
                        <span>© {{ date('Y') }}</span>
                    </div>
                    <div class="flex items-center gap-4">
                        <a href="{{ route('dashboard') }}" class="hover:text-slate-200">Dashboard</a>
                        @auth
                            <a href="{{ route('goals.index') }}" class="hover:text-slate-200">Goals</a>
                            <a href="{{ route('history.index') }}" class="hover:text-slate-200">History</a>
                            <a href="{{ route('settings.show') }}" class="hover:text-slate-200">Settings</a>
                        @else
                            <a href="{{ route('login') }}" class="hover:text-slate-200">Sign in</a>
                        @endauth
                        <a href="{{ route('contact.show') }}" class="hover:text-slate-200">Contact</a>
                    </div>
                </div>
            </footer>
        </div>

        <div id="toast_stack"
            class="pointer-events-none fixed bottom-6 right-6 z-[60] flex flex-col items-end gap-2"
            aria-live="polite" aria-atomic="false"></div>

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

        <script>
            (() => {
                const stack = document.getElementById('toast_stack');
                if (!stack) return;

                const TONES = {
                    info: 'border-slate-700 bg-slate-900/95 text-slate-100',
                    success: 'border-emerald-500/40 bg-emerald-900/40 text-emerald-100',
                    warn: 'border-amber-500/40 bg-amber-900/40 text-amber-100',
                    error: 'border-rose-500/40 bg-rose-900/40 text-rose-100',
                };

                const showToast = (message, { tone = 'success', duration = 3200 } = {}) => {
                    if (!message) return;
                    const toast = document.createElement('div');
                    toast.className =
                        'chrono-toast pointer-events-auto rounded-xl border px-4 py-2 text-sm shadow-2xl backdrop-blur-sm max-w-sm ' +
                        (TONES[tone] || TONES.info);
                    toast.textContent = message;
                    stack.appendChild(toast);
                    setTimeout(() => {
                        toast.classList.add('is-leaving');
                        toast.addEventListener('animationend', () => toast.remove(), { once: true });
                    }, duration);
                };

                window.showToast = showToast;

                // Replay any server-rendered flash messages as toasts.
                document.querySelectorAll('[data-toast-from-server]').forEach((el) => {
                    const msg = el.dataset.toastMessage;
                    if (msg) showToast(msg, { tone: 'success' });
                });
            })();
        </script>

        @livewireScripts
        @stack('scripts')

        @include('partials.flying-quote')
    </body>
</html>
