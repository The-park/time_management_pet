{{-- Guest landing hero. Stylised SVG "time keeper" mascot drawn in the chrono
     palette — no external assets so this works offline and never 404s. --}}
<div class="relative overflow-hidden rounded-2xl border border-slate-800/60 bg-[radial-gradient(circle_at_top,_rgba(0,224,255,0.18),_transparent_50%)] p-6 md:p-10 mb-10">
    <div class="absolute -right-24 -top-24 h-72 w-72 rounded-full bg-[radial-gradient(circle,_rgba(255,107,26,0.28),_transparent_70%)] blur-2xl pointer-events-none"></div>
    <div class="absolute -left-32 -bottom-32 h-72 w-72 rounded-full bg-[radial-gradient(circle,_rgba(0,224,255,0.18),_transparent_70%)] blur-2xl pointer-events-none"></div>

    <div class="relative grid md:grid-cols-[1fr_auto] gap-8 items-center">
        <div>
            <div class="text-xs uppercase tracking-[0.3em] text-[var(--chrono-blue)] mb-3">Welcome</div>
            <h1 class="font-display text-3xl md:text-5xl tracking-[0.2em] uppercase leading-tight">
                Track Your Time
            </h1>
            <p class="mt-4 text-slate-300 text-sm md:text-base max-w-xl">
                A focused space to log how you spend your hours, run countdowns for your work,
                and see — week, month, year — what's actually paying off.
            </p>
            <p class="mt-2 text-xs text-slate-500 max-w-xl">
                Try the <strong class="text-slate-300">Custom countdown</strong> below — no account needed.
                Logging blocks, history, and stats unlock once you sign up.
            </p>

            <div class="mt-5 flex flex-wrap gap-2">
                <a href="{{ route('register') }}"
                    class="rounded-lg bg-[var(--chrono-blue)] hover:opacity-90 text-slate-950 font-semibold px-4 py-2 text-sm">
                    Create free account
                </a>
                <a href="{{ route('login') }}"
                    class="rounded-lg border border-slate-700 hover:border-slate-500 text-slate-200 px-4 py-2 text-sm">
                    Sign in
                </a>
                <a href="#custom-countdown"
                    class="rounded-lg border border-slate-700/60 hover:border-slate-500 text-slate-300 px-4 py-2 text-sm">
                    Try the timer ↓
                </a>
            </div>
        </div>

        <div class="hidden md:flex items-center justify-center">
            {{-- Mascot: a stylised cyber clock-keeper with a single eye (cycle-eye), antenna,
                 and a faintly glowing shoulder dial. Pure SVG so it scales and stays crisp. --}}
            <div class="chrono-bob">
                <svg width="220" height="220" viewBox="0 0 220 220" fill="none" xmlns="http://www.w3.org/2000/svg"
                    role="img" aria-label="Track Your Time mascot">
                    <defs>
                        <radialGradient id="m_face" cx="0.5" cy="0.4" r="0.7">
                            <stop offset="0%" stop-color="#1f2937"/>
                            <stop offset="100%" stop-color="#0a0e1a"/>
                        </radialGradient>
                        <linearGradient id="m_visor" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0%" stop-color="#00e0ff" stop-opacity="0.95"/>
                            <stop offset="100%" stop-color="#ff6b1a" stop-opacity="0.9"/>
                        </linearGradient>
                        <radialGradient id="m_eye" cx="0.5" cy="0.5" r="0.5">
                            <stop offset="0%" stop-color="#ffffff"/>
                            <stop offset="60%" stop-color="#00e0ff"/>
                            <stop offset="100%" stop-color="#003a4a"/>
                        </radialGradient>
                        <filter id="m_glow" x="-50%" y="-50%" width="200%" height="200%">
                            <feGaussianBlur stdDeviation="4" result="coloredBlur"/>
                            <feMerge>
                                <feMergeNode in="coloredBlur"/>
                                <feMergeNode in="SourceGraphic"/>
                            </feMerge>
                        </filter>
                    </defs>

                    {{-- antenna with a pulsing tip --}}
                    <line x1="110" y1="32" x2="110" y2="14" stroke="#475569" stroke-width="2" stroke-linecap="round"/>
                    <circle cx="110" cy="12" r="4" fill="#00e0ff" filter="url(#m_glow)">
                        <animate attributeName="r" values="3;5;3" dur="1.8s" repeatCount="indefinite"/>
                        <animate attributeName="fill-opacity" values="1;0.5;1" dur="1.8s" repeatCount="indefinite"/>
                    </circle>

                    {{-- head capsule --}}
                    <rect x="50" y="32" width="120" height="120" rx="38" fill="url(#m_face)" stroke="#1e293b" stroke-width="2"/>

                    {{-- visor band --}}
                    <rect x="62" y="64" width="96" height="34" rx="14" fill="url(#m_visor)" opacity="0.18"/>
                    <rect x="62" y="64" width="96" height="34" rx="14" fill="none" stroke="url(#m_visor)" stroke-width="1.5"/>

                    {{-- single cycle-eye (clock face hint) --}}
                    <circle cx="110" cy="81" r="13" fill="url(#m_eye)"/>
                    <circle cx="110" cy="81" r="13" fill="none" stroke="#0a0e1a" stroke-width="1.5"/>
                    <line x1="110" y1="81" x2="110" y2="72" stroke="#0a0e1a" stroke-width="2" stroke-linecap="round">
                        <animateTransform attributeName="transform" type="rotate" from="0 110 81" to="360 110 81" dur="6s" repeatCount="indefinite"/>
                    </line>
                    <line x1="110" y1="81" x2="116" y2="81" stroke="#0a0e1a" stroke-width="1.5" stroke-linecap="round">
                        <animateTransform attributeName="transform" type="rotate" from="0 110 81" to="360 110 81" dur="2s" repeatCount="indefinite"/>
                    </line>

                    {{-- mouth: tiny LED bar --}}
                    <rect x="92" y="118" width="36" height="6" rx="2" fill="#0f172a" stroke="#1e293b" stroke-width="1"/>
                    <rect x="94" y="119.5" width="8" height="3" rx="1" fill="#39ff14" opacity="0.9">
                        <animate attributeName="opacity" values="0.9;0.3;0.9" dur="2.2s" repeatCount="indefinite"/>
                    </rect>
                    <rect x="106" y="119.5" width="8" height="3" rx="1" fill="#00e0ff" opacity="0.7">
                        <animate attributeName="opacity" values="0.4;0.9;0.4" dur="2.2s" repeatCount="indefinite"/>
                    </rect>
                    <rect x="118" y="119.5" width="8" height="3" rx="1" fill="#ff6b1a" opacity="0.5">
                        <animate attributeName="opacity" values="0.4;0.7;0.4" dur="2.2s" repeatCount="indefinite"/>
                    </rect>

                    {{-- shoulder / chest dial --}}
                    <circle cx="110" cy="172" r="24" fill="#0f172a" stroke="#1e293b" stroke-width="2"/>
                    <circle cx="110" cy="172" r="24" fill="none" stroke="#00e0ff" stroke-width="1.5" stroke-dasharray="6 4" opacity="0.6">
                        <animateTransform attributeName="transform" type="rotate" from="0 110 172" to="360 110 172" dur="14s" repeatCount="indefinite"/>
                    </circle>
                    <text x="110" y="177" text-anchor="middle" font-family="Orbitron, sans-serif" font-size="11" font-weight="700" fill="#00e0ff">TYT</text>

                    {{-- side ear panels --}}
                    <rect x="40" y="78" width="14" height="34" rx="6" fill="#1e293b" stroke="#0f172a" stroke-width="1"/>
                    <rect x="166" y="78" width="14" height="34" rx="6" fill="#1e293b" stroke="#0f172a" stroke-width="1"/>
                    <circle cx="47" cy="95" r="2.5" fill="#ff6b1a"/>
                    <circle cx="173" cy="95" r="2.5" fill="#ff6b1a"/>
                </svg>
            </div>
        </div>
    </div>
</div>
