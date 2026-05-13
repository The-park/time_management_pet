<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@hasSection('page_title')@yield('page_title') · @endif{{ config('app.name', 'Time Management Pet') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="scanlines min-h-screen bg-[var(--chrono-bg)] text-slate-100">
        {{-- Subtle ambient glows so the auth pages feel like part of the same world. --}}
        <div class="pointer-events-none fixed -top-32 -left-32 h-72 w-72 rounded-full bg-[radial-gradient(circle,_rgba(0,224,255,0.18),_transparent_70%)] blur-3xl"></div>
        <div class="pointer-events-none fixed -bottom-40 -right-40 h-80 w-80 rounded-full bg-[radial-gradient(circle,_rgba(255,107,26,0.18),_transparent_70%)] blur-3xl"></div>

        <div class="min-h-screen flex flex-col items-center justify-center px-6 py-12">
            <a href="/" class="mb-8 inline-flex items-center gap-2 text-slate-400 hover:text-slate-100 transition-colors">
                <span class="h-2 w-2 rounded-full bg-[var(--chrono-blue)] shadow-[0_0_10px_var(--chrono-blue)]"></span>
                <span class="font-display text-sm tracking-[0.3em] uppercase">Time Management Pet</span>
            </a>
            <div class="chrono-fade-in w-full max-w-md rounded-2xl border border-slate-800/60 bg-slate-900/40 backdrop-blur-sm p-6 md:p-8">
                @yield('content')
            </div>
        </div>

        {{-- Session-flash toast pickup. Mirrors the same mechanism in
             layouts/app.blade.php so a controller can simply
             ->with('toast', '…') and have it surface on the auth pages too.
             Used by the logout flow ("You've been logged out…"). --}}
        @if (session('status') || session('toast'))
            @php($toastMessage = session('toast') ?? match (session('status')) {
                'verification-link-sent' => 'A new verification link has been sent.',
                'profile-information-updated' => 'Profile updated.',
                'password-updated' => 'Password updated.',
                default => null,
            })
            @if ($toastMessage)
                <div data-toast-from-server data-toast-message="{{ $toastMessage }}" class="hidden"></div>
            @endif
        @endif

        <div id="toast_stack" aria-live="polite" aria-atomic="false"
            class="fixed inset-x-0 bottom-6 z-50 pointer-events-none flex flex-col items-center gap-2 px-4"></div>

        <script>
            // Minimal toast renderer — kept inline so guest pages don't
            // depend on the layout's larger script bundle. Same DOM contract
            // as layouts/app's renderer so the toast styling stays identical.
            (() => {
                const stack = document.getElementById('toast_stack');
                if (!stack) return;
                const TONES = {
                    info:    'border-slate-700 bg-slate-900/95 text-slate-100',
                    success: 'border-emerald-500/40 bg-emerald-900/40 text-emerald-100',
                    warn:    'border-amber-500/40 bg-amber-900/40 text-amber-100',
                    error:   'border-rose-500/40 bg-rose-900/40 text-rose-100',
                };
                const showToast = (message, { tone = 'info', duration = 4200 } = {}) => {
                    if (!message) return;
                    const t = document.createElement('div');
                    t.className =
                        'chrono-toast pointer-events-auto rounded-xl border px-4 py-2 text-sm shadow-2xl backdrop-blur-sm max-w-sm ' +
                        (TONES[tone] || TONES.info);
                    t.textContent = message;
                    stack.appendChild(t);
                    setTimeout(() => {
                        t.classList.add('is-leaving');
                        t.addEventListener('animationend', () => t.remove(), { once: true });
                    }, duration);
                };
                window.showToast = showToast;
                document.querySelectorAll('[data-toast-from-server]').forEach((el) => {
                    const msg = el.dataset.toastMessage;
                    if (msg) showToast(msg, { tone: 'info', duration: 5000 });
                });
            })();
        </script>
    </body>
</html>
