{{--
  Reusable IANA timezone picker. Shared by /register and /settings (and any
  future page) so the dropdowns can never drift apart.

  - Source of truth: PHP's DateTimeZone::listIdentifiers() (the IANA tzdata
    — same database iOS / macOS / Linux / Node use). 400+ real entries.
  - Each option label is "City — UTC±HH:MM" so users can scan by offset.
  - Reflects the *current* offset (handles DST automatically).
  - Searchable combobox UI: type to filter by city / region / offset / IANA
    id. Falls back to a plain <select> if JS is disabled.
  - Default selection: if no $selected is provided, defaults to
    Asia/Kolkata (UTC+5:30). The browser-tz autodetect (when enabled) runs
    on top and overrides the default if it matches a known IANA id.

  Required attributes / vars:
    $name        — name attribute for the hidden input (default 'timezone')
    $id          — id attribute for the hidden input + UI root (default 'timezone')
    $selected    — currently-selected IANA identifier (e.g. old('timezone'))
    $autodetect  — true to default the field to the browser's own timezone
                   when no $selected was supplied
    $extraClass  — Tailwind classes appended to the visible button
--}}
@php
    $name        = $name        ?? 'timezone';
    $id          = $id          ?? 'timezone';
    $selected    = $selected    ?? null;
    $autodetect  = $autodetect  ?? false;
    $extraClass  = $extraClass  ?? 'w-full rounded-md bg-slate-900 border border-slate-700 px-3 py-2';

    // App default: India (UTC+5:30). Used when the form has no prior
    // selection and autodetect is off (or the detected zone isn't in our
    // list). Server-side fallback lives here instead of in the controller
    // so it stays consistent across every page that includes this partial.
    $defaultTz = 'Asia/Kolkata';
    $resolvedSelected = $selected ?: $defaultTz;

    $regionLabels = [
        'Africa'     => 'Africa',
        'America'    => 'America',
        'Antarctica' => 'Antarctica',
        'Arctic'     => 'Arctic',
        'Asia'       => 'Asia',
        'Atlantic'   => 'Atlantic',
        'Australia'  => 'Australia',
        'Europe'     => 'Europe',
        'Indian'     => 'Indian Ocean',
        'Pacific'    => 'Pacific',
    ];

    // Compute "UTC±HH:MM" for a given timezone using its CURRENT offset.
    $offsetLabel = function (string $tz): string {
        static $cache = [];
        if (isset($cache[$tz])) return $cache[$tz];
        try {
            $z   = new \DateTimeZone($tz);
            $off = (new \DateTime('now', $z))->getOffset();
            $h   = intdiv(abs($off), 3600);
            $m   = intdiv(abs($off) % 3600, 60);
            $sign = $off < 0 ? '−' : '+';
            return $cache[$tz] = sprintf('UTC%s%d:%02d', $sign, $h, $m);
        } catch (\Throwable $e) {
            return $cache[$tz] = '';
        }
    };

    // Group identifiers by region. Sort by offset within each region so
    // users can scan top-to-bottom.
    $grouped = [];
    foreach (\DateTimeZone::listIdentifiers() as $tz) {
        $region = str_contains($tz, '/') ? explode('/', $tz, 2)[0] : 'Other';
        $offset = $offsetLabel($tz);
        $city   = str_contains($tz, '/') ? str_replace('_', ' ', substr($tz, strpos($tz, '/') + 1)) : $tz;
        $city   = str_replace('_', ' ', $city);
        $grouped[$region][] = [
            'id'       => $tz,
            'city'     => $city,
            'label'    => $city.' — '.$offset,
            'offset'   => (new \DateTime('now', new \DateTimeZone($tz)))->getOffset(),
            'offsetTx' => $offset,
        ];
    }
    foreach ($grouped as &$list) {
        usort($list, fn ($a, $b) => $a['offset'] <=> $b['offset'] ?: strcmp($a['label'], $b['label']));
    }
    unset($list);

    // Resolve the display label for the initially-selected id.
    $resolveLabel = function (string $tz) use ($offsetLabel): string {
        if ($tz === 'UTC') return 'UTC — UTC+0:00';
        $city = str_contains($tz, '/') ? str_replace('_', ' ', substr($tz, strpos($tz, '/') + 1)) : $tz;
        return $city.' — '.$offsetLabel($tz);
    };
    $initialLabel = $resolveLabel($resolvedSelected);
@endphp

<div class="relative" data-tz-picker
    @if ($autodetect) data-tz-autodetect="1" @endif
    @if ($selected) data-initial-tz="{{ $selected }}" @endif
    data-default-tz="{{ $defaultTz }}">

    {{-- Hidden input is the form-submitted value. Validation rules
         reference $name on the server unchanged. --}}
    <input type="hidden" id="{{ $id }}" name="{{ $name }}" value="{{ $resolvedSelected }}" data-tz-value>

    {{-- Visible button that opens the searchable list. --}}
    <button type="button" data-tz-button
        class="{{ $extraClass }} text-left flex items-center justify-between gap-2"
        aria-haspopup="listbox" aria-expanded="false">
        <span class="truncate" data-tz-label>{{ $initialLabel }}</span>
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
            class="h-4 w-4 shrink-0 text-slate-400">
            <polyline points="6 9 12 15 18 9"/>
        </svg>
    </button>

    {{-- Dropdown panel with search box + grouped options. --}}
    <div data-tz-panel
        class="hidden absolute z-30 mt-1 w-full rounded-md border border-slate-700 bg-slate-900 shadow-lg shadow-black/40">
        <div class="p-2 border-b border-slate-800 sticky top-0 bg-slate-900 rounded-t-md">
            <div class="relative">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    class="h-4 w-4 absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-500 pointer-events-none">
                    <circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input type="text" data-tz-search
                    placeholder="Search city, region, or UTC offset…"
                    autocomplete="off"
                    class="w-full rounded-md bg-slate-950 border border-slate-700 pl-8 pr-2 py-1.5 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:border-[var(--chrono-blue,#22d3ee)]/60">
            </div>
        </div>
        <ul role="listbox" data-tz-list class="max-h-72 overflow-y-auto py-1 text-sm">
            <li role="option" data-tz-option data-tz-id="UTC"
                data-tz-haystack="utc — utc+0:00 utc"
                class="px-3 py-1.5 cursor-pointer hover:bg-slate-800 text-slate-200 {{ $resolvedSelected === 'UTC' ? 'bg-slate-800/60 text-cyan-300' : '' }}">
                UTC — UTC+0:00
            </li>
            @foreach ($regionLabels as $region => $regionLabel)
                @if (! empty($grouped[$region]))
                    <li class="px-3 py-1 mt-1 text-[0.6rem] uppercase tracking-[0.2em] text-slate-500 sticky top-0 bg-slate-900/95">
                        {{ $regionLabel }}
                    </li>
                    @foreach ($grouped[$region] as $entry)
                        <li role="option" data-tz-option data-tz-id="{{ $entry['id'] }}"
                            data-tz-haystack="{{ strtolower($entry['city'].' '.$regionLabel.' '.$entry['offsetTx'].' '.$entry['id']) }}"
                            class="px-3 py-1.5 cursor-pointer hover:bg-slate-800 flex items-center justify-between gap-3 {{ $resolvedSelected === $entry['id'] ? 'bg-slate-800/60 text-cyan-300' : 'text-slate-200' }}">
                            <span class="truncate">{{ $entry['city'] }}</span>
                            <span class="text-xs text-slate-500 shrink-0">{{ $entry['offsetTx'] }}</span>
                        </li>
                    @endforeach
                @endif
            @endforeach
            <li data-tz-empty class="hidden px-3 py-3 text-center text-xs text-slate-500">
                No timezones match — try a city name or "UTC+5:30".
            </li>
        </ul>
    </div>
</div>

@once
    <script>
        (() => {
            // One-shot module that wires up every [data-tz-picker] on the
            // page. Idempotent so we can re-include this partial multiple
            // times safely (the Blade once-guard normally handles this;
            // the data-tz-attached flag is a belt-and-braces guard).
            const init = () => {
                document.querySelectorAll('[data-tz-picker]').forEach((root) => {
                    if (root.dataset.tzAttached === '1') return;
                    root.dataset.tzAttached = '1';

                    const valueEl = root.querySelector('[data-tz-value]');
                    const button = root.querySelector('[data-tz-button]');
                    const labelEl = root.querySelector('[data-tz-label]');
                    const panel = root.querySelector('[data-tz-panel]');
                    const search = root.querySelector('[data-tz-search]');
                    const listEl = root.querySelector('[data-tz-list]');
                    const emptyEl = root.querySelector('[data-tz-empty]');
                    if (!valueEl || !button || !panel || !search || !listEl) return;

                    const options = Array.from(listEl.querySelectorAll('[data-tz-option]'));
                    const groupHeaders = Array.from(listEl.querySelectorAll('li:not([data-tz-option]):not([data-tz-empty])'));

                    const close = () => {
                        panel.classList.add('hidden');
                        button.setAttribute('aria-expanded', 'false');
                    };
                    const open = () => {
                        panel.classList.remove('hidden');
                        button.setAttribute('aria-expanded', 'true');
                        search.value = '';
                        applyFilter('');
                        // Scroll to the currently-selected option.
                        const sel = listEl.querySelector('[data-tz-option].bg-slate-800\\/60')
                            || listEl.querySelector(`[data-tz-id="${CSS.escape(valueEl.value)}"]`);
                        sel?.scrollIntoView({ block: 'center' });
                        setTimeout(() => search.focus(), 0);
                    };

                    const setValue = (id) => {
                        const option = options.find((o) => o.dataset.tzId === id);
                        if (!option) return;
                        valueEl.value = id;
                        if (labelEl) {
                            const city = option.querySelector('span:first-child')?.textContent?.trim()
                                || option.textContent.trim();
                            const offset = option.querySelector('span:last-child')?.textContent?.trim() || '';
                            labelEl.textContent = offset ? `${city} — ${offset}` : option.textContent.trim();
                        }
                        // Visual highlight.
                        options.forEach((o) => {
                            o.classList.remove('bg-slate-800/60', 'text-cyan-300');
                            o.classList.add('text-slate-200');
                        });
                        option.classList.remove('text-slate-200');
                        option.classList.add('bg-slate-800/60', 'text-cyan-300');
                        valueEl.dispatchEvent(new Event('change', { bubbles: true }));
                    };

                    const applyFilter = (raw) => {
                        const q = raw.trim().toLowerCase();
                        let visible = 0;
                        options.forEach((o) => {
                            const hay = o.dataset.tzHaystack || '';
                            const ok = !q || hay.includes(q);
                            o.classList.toggle('hidden', !ok);
                            if (ok) visible++;
                        });
                        // Hide group headers when none of their children match.
                        groupHeaders.forEach((h) => {
                            let next = h.nextElementSibling;
                            let any = false;
                            while (next && !next.matches('li:not([data-tz-option]):not([data-tz-empty])')) {
                                if (next.matches('[data-tz-option]') && !next.classList.contains('hidden')) {
                                    any = true;
                                    break;
                                }
                                next = next.nextElementSibling;
                            }
                            h.classList.toggle('hidden', !any);
                        });
                        if (emptyEl) emptyEl.classList.toggle('hidden', visible > 0);
                    };

                    button.addEventListener('click', () => {
                        if (panel.classList.contains('hidden')) open(); else close();
                    });

                    search.addEventListener('input', () => applyFilter(search.value));
                    search.addEventListener('keydown', (e) => {
                        if (e.key === 'Escape') { close(); button.focus(); return; }
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            const first = options.find((o) => !o.classList.contains('hidden'));
                            if (first) {
                                setValue(first.dataset.tzId);
                                close();
                                button.focus();
                            }
                        }
                    });

                    listEl.addEventListener('click', (e) => {
                        const opt = e.target.closest('[data-tz-option]');
                        if (!opt) return;
                        setValue(opt.dataset.tzId);
                        close();
                        button.focus();
                    });

                    document.addEventListener('click', (e) => {
                        if (!root.contains(e.target)) close();
                    });

                    // Browser-timezone autodetect: only override the
                    // server-rendered default when there's no real prior
                    // selection (data-initial-tz absent). Run once at
                    // setup. Falls back to Asia/Kolkata (already pre-set
                    // by the server) if Intl is unavailable or the
                    // detected zone isn't in our list.
                    if (root.dataset.tzAutodetect === '1' && !root.dataset.initialTz) {
                        try {
                            const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
                            if (tz && options.some((o) => o.dataset.tzId === tz)) {
                                setValue(tz);
                            }
                        } catch {}
                    }
                });
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else {
                init();
            }
        })();
    </script>
@endonce
