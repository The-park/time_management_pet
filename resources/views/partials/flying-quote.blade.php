@auth
    @if (auth()->user()->flying_quotes_enabled ?? false)
        @php
            $initial = null;
            try {
                $initial = \App\Models\Quote::active()->inRandomOrder()->first();
            } catch (\Throwable $e) {
                $initial = null;
            }
            $initialPayload = $initial ? [
                'text' => $initial->text,
                'author' => $initial->author,
                'source' => $initial->source,
                'category' => $initial->category,
            ] : null;
        @endphp

        @if ($initial)
            <style>
                @keyframes chrono-quote-rise {
                    0%   { transform: translate(-50%, 110vh); opacity: 0; }
                    8%   { opacity: 1; }
                    92%  { opacity: 1; }
                    100% { transform: translate(-50%, -25vh); opacity: 0; }
                }
                .chrono-flying-quote {
                    position: fixed;
                    left: 50%;
                    bottom: 0;
                    transform: translate(-50%, 110vh);
                    z-index: 5;
                    pointer-events: none;
                    max-width: min(360px, 80vw);
                    padding: 0.6rem 1.1rem;
                    border-radius: 999px;
                    background: rgba(15, 23, 42, 0.78);
                    backdrop-filter: blur(6px);
                    color: #e2e8f0;
                    font-size: 0.85rem;
                    line-height: 1.35;
                    text-align: center;
                    border: 1px solid rgba(16, 185, 129, 0.28);
                    box-shadow: 0 0 18px rgba(16, 185, 129, 0.22),
                                0 0 36px rgba(0, 224, 255, 0.15);
                    animation: chrono-quote-rise 15s ease-in-out forwards;
                }
                .chrono-flying-quote .chrono-flying-quote-meta {
                    display: block;
                    font-size: 0.65rem;
                    opacity: 0.7;
                    margin-top: 0.15rem;
                    letter-spacing: 0.05em;
                }
                @media (prefers-reduced-motion: reduce) {
                    .chrono-flying-quote { animation: none; opacity: 0; }
                }
            </style>

            <div id="chrono_flying_quote_host" aria-hidden="true"></div>

            <script>
                (() => {
                    const host = document.getElementById('chrono_flying_quote_host');
                    if (!host) return;
                    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

                    const ENDPOINT = @json(route('quotes.random'));
                    const ANIM_MS = 15000;
                    const GAP_MS = 12000;

                    const initial = @json($initialPayload);

                    let currentEl = null;

                    const escapeHtml = (s) => String(s ?? '')
                        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');

                    const render = (q) => {
                        if (!q || !q.text) return;
                        if (currentEl) {
                            try { currentEl.remove(); } catch {}
                        }
                        const el = document.createElement('div');
                        el.className = 'chrono-flying-quote';
                        const meta = [];
                        if (q.author) meta.push(escapeHtml(q.author));
                        if (q.source) meta.push(escapeHtml(q.source));
                        el.innerHTML = '<span>' + escapeHtml(q.text) + '</span>'
                            + (meta.length ? '<span class="chrono-flying-quote-meta">— ' + meta.join(' · ') + '</span>' : '');
                        host.appendChild(el);
                        currentEl = el;
                        setTimeout(() => {
                            if (el.parentNode) el.parentNode.removeChild(el);
                        }, ANIM_MS + 500);
                    };

                    render(initial);

                    const fetchNext = async () => {
                        try {
                            const res = await fetch(ENDPOINT, {
                                headers: { 'Accept': 'application/json' },
                                credentials: 'same-origin',
                            });
                            if (!res.ok) return null;
                            return await res.json();
                        } catch {
                            return null;
                        }
                    };

                    setInterval(async () => {
                        if (document.hidden) return;
                        const next = await fetchNext();
                        render(next);
                    }, GAP_MS);
                })();
            </script>
        @endif
    @endif
@endauth
