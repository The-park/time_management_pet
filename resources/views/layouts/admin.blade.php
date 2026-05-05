<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@hasSection('page_title')@yield('page_title') · @endif{{ config('app.name', 'Track Your Time') }} · Admin</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-slate-950 text-slate-100 min-h-screen antialiased">
        @php
            $admin = auth('admin')->user();
            $navItems = [
                ['route' => 'admin.dashboard', 'label' => 'Dashboard', 'match' => 'admin.dashboard',
                    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 12l9-9 9 9M5 10v10h4v-6h6v6h4V10"/>'],
                ['route' => 'admin.users.index', 'label' => 'Users', 'match' => 'admin.users.*',
                    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>'],
                ['route' => 'admin.audit.index', 'label' => 'Audit log', 'match' => 'admin.audit.*',
                    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>'],
                ['route' => 'admin.domains.index', 'label' => 'Domains', 'match' => 'admin.domains.*',
                    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z"/>'],
                ['route' => 'admin.administrators.index', 'label' => 'Admins', 'match' => 'admin.administrators.*',
                    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>'],
            ];
            $navLink = function ($item) {
                $isActive = request()->routeIs($item['match']);
                $cls = 'group flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-colors border ';
                $cls .= $isActive
                    ? 'bg-rose-500/10 text-rose-200 border-rose-500/20'
                    : 'text-slate-300 hover:bg-slate-800/40 hover:text-slate-100 border-transparent';
                return $cls;
            };
        @endphp

        <div class="flex min-h-screen">
            {{-- ─── Sidebar ───────────────────────────────────────────────── --}}
            <aside class="hidden md:flex md:w-60 md:flex-col border-r border-slate-800/60 bg-slate-950/80 backdrop-blur-sm">
                <div class="px-5 py-5">
                    <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2.5 group">
                        <div class="h-2 w-2 rounded-full bg-rose-400 shadow-[0_0_10px_#fb7185] group-hover:scale-110 transition-transform"></div>
                        <div>
                            <div class="font-display text-sm tracking-[0.2em] text-slate-100">Admin</div>
                            <div class="text-[0.6rem] uppercase tracking-wider text-slate-500 mt-0.5">Track Your Time</div>
                        </div>
                    </a>
                </div>

                <nav class="flex-1 px-3 space-y-0.5">
                    <div class="px-3 py-1 text-[0.6rem] uppercase tracking-[0.2em] text-slate-600">Navigation</div>
                    @foreach ($navItems as $item)
                        <a href="{{ route($item['route']) }}" class="{{ $navLink($item) }}">
                            <svg class="h-4 w-4 shrink-0 opacity-80" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true">
                                {!! $item['icon'] !!}
                            </svg>
                            <span>{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </nav>

                <div class="mx-3 my-3 border-t border-slate-800/60"></div>

                <div class="px-5 pb-5">
                    <div class="text-[0.6rem] uppercase tracking-wider text-slate-500">Signed in</div>
                    <div class="mt-1 text-sm text-slate-200 truncate">{{ $admin?->name ?? 'Admin' }}</div>
                    <div class="text-xs text-slate-500 truncate">{{ $admin?->email }}</div>
                    <form method="POST" action="{{ route('admin.logout') }}" class="mt-3">
                        @csrf
                        <button type="submit"
                            class="inline-flex items-center gap-1.5 text-xs text-slate-400 hover:text-rose-300 transition-colors">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75"/></svg>
                            Sign out
                        </button>
                    </form>
                </div>
            </aside>

            {{-- ─── Main column ──────────────────────────────────────────── --}}
            <div class="flex-1 min-w-0 flex flex-col">
                {{-- Mobile top bar --}}
                <header class="md:hidden border-b border-slate-800/60 bg-slate-950/90 backdrop-blur-sm">
                    <div class="px-4 py-3 flex items-center justify-between">
                        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2">
                            <div class="h-2 w-2 rounded-full bg-rose-400 shadow-[0_0_8px_#fb7185]"></div>
                            <span class="font-display text-sm tracking-[0.2em]">Admin</span>
                        </a>
                        <form method="POST" action="{{ route('admin.logout') }}">
                            @csrf
                            <button type="submit" class="text-xs text-slate-400 hover:text-rose-300">Sign out</button>
                        </form>
                    </div>
                    <nav class="flex overflow-x-auto border-t border-slate-800/60 text-xs">
                        @foreach ($navItems as $item)
                            @php
                                $active = request()->routeIs($item['match']);
                                $cls = $active
                                    ? 'border-rose-400 text-rose-300'
                                    : 'border-transparent text-slate-400 hover:text-slate-100';
                            @endphp
                            <a href="{{ route($item['route']) }}" class="px-4 py-2.5 border-b-2 whitespace-nowrap {{ $cls }}">
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    </nav>
                </header>

                {{-- Page area --}}
                <main class="flex-1 px-6 md:px-10 py-6 md:py-10">
                    <div class="max-w-7xl mx-auto">
                        @if (session('toast'))
                            <div class="mb-6 chrono-fade-in rounded-xl border border-rose-500/30 bg-rose-500/5 px-4 py-3 text-sm text-rose-100 flex items-start gap-3">
                                <svg class="h-5 w-5 shrink-0 text-rose-300 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <div>{{ session('toast') }}</div>
                            </div>
                        @endif
                        @yield('content')
                    </div>
                </main>

                <footer class="border-t border-slate-800/60 px-6 md:px-10 py-4 text-xs text-slate-500">
                    <div class="max-w-7xl mx-auto flex flex-wrap items-center justify-between gap-2">
                        <span>Admin Console · Track Your Time</span>
                        <span>{{ now()->format('M j, Y · g:i A') }}</span>
                    </div>
                </footer>
            </div>
        </div>
    </body>
</html>
