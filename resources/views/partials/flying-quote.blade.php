@auth
    @php
        $user = auth()->user();
        $showQuotes = (bool) ($user->flying_quotes_enabled ?? false);
        $showLastLogTimer = (bool) ($user->last_log_timer_enabled ?? true);
        $endOfDayTime = $user?->end_of_day_time ? substr($user->end_of_day_time, 0, 5) : '22:00';
        $timezone = $user?->timezone ?: config('app.timezone', 'UTC');

        $initialPayload = null;
        if ($showQuotes) {
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
        }
    @endphp

    @if ($showQuotes || $showLastLogTimer)
        <style>
            @keyframes chrono-quote-rise {
                0%   { transform: translate(-50%, 10vh);  opacity: 0; }
                8%   { transform: translate(-50%, 0);     opacity: 1; }
                92%  { transform: translate(-50%, -100vh); opacity: 1; }
                100% { transform: translate(-50%, -110vh); opacity: 0; }
            }
            .chrono-flying-quote {
                position: fixed;
                left: 50%;
                bottom: var(--chrono-quote-bottom, 0);
                transform: translate(-50%, 10vh);
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
                animation: chrono-quote-rise 28s linear forwards;
            }
            .chrono-flying-quote .chrono-flying-quote-meta {
                display: block;
                font-size: 0.65rem;
                opacity: 0.7;
                margin-top: 0.15rem;
                letter-spacing: 0.05em;
            }
            .chrono-last-log-timer {
                position: fixed;
                left: 50%;
                bottom: 0.75rem;
                transform: translateX(-50%);
                z-index: 6;
                pointer-events: none;
                width: min(760px, calc(100vw - 1.5rem));
                padding: 0.55rem;
                border-radius: 1rem;
                border: 1px solid rgba(0, 224, 255, 0.22);
                background: rgba(8, 13, 26, 0.9);
                color: #dbeafe;
                box-shadow: 0 18px 40px -24px rgba(0, 224, 255, 0.75),
                            0 0 0 1px rgba(15, 23, 42, 0.9) inset;
                backdrop-filter: blur(12px);
                font-size: 0.86rem;
                line-height: 1.25;
            }
            .chrono-last-log-timer__grid {
                display: grid;
                grid-template-columns: minmax(0, 1.35fr) minmax(12rem, 0.9fr);
                gap: 0.5rem;
            }
            .chrono-last-log-timer__item {
                min-width: 0;
                border-radius: 0.75rem;
                border: 1px solid rgba(148, 163, 184, 0.16);
                background: rgba(15, 23, 42, 0.74);
                padding: 0.55rem 0.7rem;
            }
            .chrono-last-log-timer__label {
                display: block;
                margin-bottom: 0.15rem;
                color: #94a3b8;
                font-size: 0.62rem;
                font-weight: 700;
                letter-spacing: 0.14em;
                text-transform: uppercase;
            }
            .chrono-last-log-timer__value {
                color: #e2e8f0;
                font-weight: 600;
            }
            .chrono-last-log-timer__value strong {
                color: var(--chrono-blue);
                font-family: var(--font-digital);
                font-size: 1.05rem;
                letter-spacing: 0.02em;
            }
            .chrono-last-log-timer__sub {
                color: #64748b;
                font-size: 0.68rem;
                margin-top: 0.2rem;
            }
            .chrono-last-log-timer__count {
                color: var(--chrono-blue);
                font-family: var(--font-digital);
                font-size: 1.18rem;
                font-weight: 700;
                letter-spacing: 0.03em;
            }
            .chrono-last-log-timer[data-gap-tone="watch"] .chrono-last-log-timer__item:first-child {
                border-color: rgba(251, 191, 36, 0.46);
                box-shadow: 0 0 20px rgba(251, 191, 36, 0.12);
            }
            .chrono-last-log-timer[data-gap-tone="watch"] .chrono-last-log-timer__item:first-child strong {
                color: #fbbf24;
            }
            .chrono-last-log-timer[data-gap-tone="warm"] .chrono-last-log-timer__item:first-child {
                border-color: rgba(251, 146, 60, 0.52);
                box-shadow: 0 0 22px rgba(251, 146, 60, 0.15);
            }
            .chrono-last-log-timer[data-gap-tone="warm"] .chrono-last-log-timer__item:first-child strong {
                color: #fb923c;
            }
            .chrono-last-log-timer[data-gap-tone="danger"] .chrono-last-log-timer__item:first-child {
                border-color: rgba(251, 113, 133, 0.58);
                box-shadow: 0 0 26px rgba(251, 113, 133, 0.18);
            }
            .chrono-last-log-timer[data-gap-tone="danger"] .chrono-last-log-timer__item:first-child strong {
                color: #fb7185;
            }
            .chrono-last-log-timer[data-day-tone="watch"] .chrono-last-log-timer__item:last-child {
                border-color: rgba(251, 191, 36, 0.46);
            }
            .chrono-last-log-timer[data-day-tone="watch"] .chrono-last-log-timer__count {
                color: #fbbf24;
            }
            .chrono-last-log-timer[data-day-tone="warm"] .chrono-last-log-timer__item:last-child {
                border-color: rgba(251, 146, 60, 0.52);
            }
            .chrono-last-log-timer[data-day-tone="warm"] .chrono-last-log-timer__count {
                color: #fb923c;
            }
            .chrono-last-log-timer[data-day-tone="danger"] .chrono-last-log-timer__item:last-child {
                border-color: rgba(251, 113, 133, 0.62);
                box-shadow: 0 0 28px rgba(251, 113, 133, 0.2);
            }
            .chrono-last-log-timer[data-day-tone="danger"] .chrono-last-log-timer__count {
                color: #fb7185;
                text-shadow: 0 0 16px rgba(251, 113, 133, 0.5);
            }
            .chrono-last-log-timer[hidden] {
                display: none;
            }
            @media (max-width: 640px) {
                .chrono-last-log-timer {
                    width: min(430px, calc(100vw - 1rem));
                    font-size: 0.8rem;
                }
                .chrono-last-log-timer__grid {
                    grid-template-columns: 1fr;
                }
            }
            @media (prefers-reduced-motion: reduce) {
                .chrono-flying-quote { animation: none; opacity: 0; }
            }
        </style>

        @if ($showQuotes)
            <div id="chrono_flying_quote_host" aria-hidden="true"></div>
        @endif

        @if ($showLastLogTimer)
            <div id="chrono_last_log_timer" class="chrono-last-log-timer" aria-live="polite" hidden></div>
        @endif

        <script>
            (() => {
                const QUOTES_ENABLED = @json($showQuotes);
                const LAST_LOG_TIMER_ENABLED = @json($showLastLogTimer);
                const BLOCKS_KEY = 'chrono.timeBlocks.v1';
                const END_OF_DAY_TIME = @json($endOfDayTime);
                const END_OF_DAY_ZONE = @json($timezone);

                const escapeHtml = (s) => String(s ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');

                const pad = (n) => String(n).padStart(2, '0');
                const localDateString = (d = new Date()) =>
                    `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
                const parseHHMM = (hhmm) => {
                    const m = /^(\d{2}):(\d{2})$/.exec(String(hhmm || ''));
                    if (!m) return null;
                    const h = Number(m[1]);
                    const min = Number(m[2]);
                    if (!Number.isFinite(h) || !Number.isFinite(min) || h > 23 || min > 59) return null;
                    return { h, min };
                };
                const formatTime12 = (hhmm) => {
                    const parsed = parseHHMM(hhmm);
                    if (!parsed) return '';
                    const period = parsed.h >= 12 ? 'PM' : 'AM';
                    const h12 = parsed.h % 12 || 12;
                    return `${h12}:${pad(parsed.min)} ${period}`;
                };
                const formatElapsed = (ms) => {
                    const totalMin = Math.max(0, Math.floor(ms / 60000));
                    if (totalMin < 1) return 'just now';
                    if (totalMin < 60) return `${totalMin}m`;
                    const h = Math.floor(totalMin / 60);
                    const m = totalMin % 60;
                    return m === 0 ? `${h}h` : `${h}h ${m}m`;
                };
                const formatClock = (ms) => {
                    const totalSeconds = Math.max(0, Math.floor(ms / 1000));
                    const h = Math.floor(totalSeconds / 3600);
                    const m = Math.floor((totalSeconds % 3600) / 60);
                    const s = totalSeconds % 60;
                    return `${pad(h)}:${pad(m)}:${pad(s)}`;
                };
                const gapTone = (ms) => {
                    if (ms >= 3 * 60 * 60 * 1000) return 'danger';
                    if (ms >= 2 * 60 * 60 * 1000) return 'warm';
                    if (ms >= 60 * 60 * 1000) return 'watch';
                    return 'fresh';
                };
                const dayTone = (ms) => {
                    if (ms <= 60 * 60 * 1000) return 'danger';
                    if (ms <= 2 * 60 * 60 * 1000) return 'warm';
                    if (ms <= 3 * 60 * 60 * 1000) return 'watch';
                    return 'fresh';
                };

                if (QUOTES_ENABLED) {
                    const host = document.getElementById('chrono_flying_quote_host');
                    const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

                    if (host && !reduceMotion) {
                        const ENDPOINT = @json(route('quotes.random'));
                        const ANIM_MS = 28000;
                        const GAP_MS = 22000;
                        const initial = @json($initialPayload);

                        let currentEl = null;

                        const renderQuote = (q) => {
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
                                + (meta.length ? '<span class="chrono-flying-quote-meta">- ' + meta.join(' / ') + '</span>' : '');
                            host.appendChild(el);
                            currentEl = el;
                            setTimeout(() => {
                                if (el.parentNode) el.parentNode.removeChild(el);
                            }, ANIM_MS + 500);
                        };

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

                        renderQuote(initial);

                        setInterval(async () => {
                            if (document.hidden) return;
                            const next = await fetchNext();
                            renderQuote(next);
                        }, GAP_MS);
                    }
                }

                if (!LAST_LOG_TIMER_ENABLED) return;

                const timerEl = document.getElementById('chrono_last_log_timer');
                if (!timerEl) return;

                const loadBlocks = () => {
                    try {
                        const raw = localStorage.getItem(BLOCKS_KEY);
                        if (!raw) return [];
                        const parsed = JSON.parse(raw);
                        return Array.isArray(parsed) ? parsed : [];
                    } catch {
                        return [];
                    }
                };
                const blockEndDate = (block) => {
                    if (!block || !block.date || !block.end) return null;
                    const end = parseHHMM(block.end);
                    if (!end) return null;
                    const parts = String(block.date).split('-').map(Number);
                    if (parts.length !== 3 || parts.some((n) => !Number.isFinite(n))) return null;

                    const endDate = new Date(parts[0], parts[1] - 1, parts[2], end.h, end.min, 0, 0);
                    const start = parseHHMM(block.start);
                    if (start) {
                        const startDate = new Date(parts[0], parts[1] - 1, parts[2], start.h, start.min, 0, 0);
                        if (endDate <= startDate) endDate.setDate(endDate.getDate() + 1);
                    }
                    return endDate;
                };
                const latestCompletedBlockToday = () => {
                    const today = localDateString();
                    const now = Date.now();
                    let latest = null;
                    for (const block of loadBlocks()) {
                        if (!block || block.date !== today) continue;
                        if (block.status === 'active' || block.status === 'paused') continue;
                        if (!block.end) continue;
                        const endDate = blockEndDate(block);
                        if (!endDate) continue;
                        const ts = endDate.getTime();
                        if (ts > now + 60000) continue;
                        if (!latest || ts > latest.ts) {
                            latest = { block, ts };
                        }
                    }
                    return latest;
                };
                const endOfDayTarget = () => {
                    const parsed = parseHHMM(END_OF_DAY_TIME);
                    if (!parsed) return null;
                    const now = new Date();
                    return new Date(now.getFullYear(), now.getMonth(), now.getDate(), parsed.h, parsed.min, 0, 0);
                };
                const setQuoteOffset = (visible) => {
                    if (visible) {
                        document.documentElement.style.setProperty('--chrono-quote-bottom', '6.2rem');
                    } else {
                        document.documentElement.style.removeProperty('--chrono-quote-bottom');
                    }
                };
                const refreshLastLogTimer = () => {
                    const latest = latestCompletedBlockToday();
                    const now = Date.now();
                    const target = endOfDayTarget();
                    const remainingMs = target ? Math.max(0, target.getTime() - now) : 0;
                    const dayEnded = target ? now > target.getTime() : false;
                    const elapsedMs = latest ? Math.max(0, now - latest.ts) : 0;
                    const elapsed = latest ? formatElapsed(elapsedMs) : 'not logged yet';
                    const lastTime = latest ? formatTime12(latest.block.end) : 'today';
                    const endTime = formatTime12(END_OF_DAY_TIME);
                    timerEl.dataset.gapTone = latest ? gapTone(elapsedMs) : 'watch';
                    timerEl.dataset.dayTone = dayEnded ? 'danger' : dayTone(remainingMs);
                    timerEl.innerHTML = `
                        <div class="chrono-last-log-timer__grid">
                            <div class="chrono-last-log-timer__item">
                                <span class="chrono-last-log-timer__label">Time passed</span>
                                <div class="chrono-last-log-timer__value">
                                    <strong>${escapeHtml(elapsed)}</strong>
                                    ${latest
                                        ? ` from last logged time <strong>${escapeHtml(lastTime)}</strong>`
                                        : ' since the day started with no completed log'}
                                </div>
                                <div class="chrono-last-log-timer__sub">1h amber, 2h orange, 3h red</div>
                            </div>
                            <div class="chrono-last-log-timer__item">
                                <span class="chrono-last-log-timer__label">End of day left</span>
                                <div class="chrono-last-log-timer__count">${escapeHtml(dayEnded ? '00:00:00' : formatClock(remainingMs))}</div>
                                <div class="chrono-last-log-timer__sub">Until ${escapeHtml(endTime)} · ${escapeHtml(END_OF_DAY_ZONE)}</div>
                            </div>
                        </div>
                    `;
                    timerEl.hidden = false;
                    setQuoteOffset(true);
                };

                refreshLastLogTimer();
                setInterval(refreshLastLogTimer, 1000);
                window.addEventListener('chrono:blocks:changed', refreshLastLogTimer);
                window.addEventListener('storage', (event) => {
                    if (!event.key || event.key === BLOCKS_KEY) refreshLastLogTimer();
                });
                document.addEventListener('visibilitychange', () => {
                    if (!document.hidden) refreshLastLogTimer();
                });
            })();
        </script>
    @endif
@endauth
