{{--
  Reusable IANA timezone <select> with live search + browser auto-detect.
  Shared by /register and /settings so the two stay in sync.

  - Source of truth: PHP's DateTimeZone::listIdentifiers() (IANA tzdata —
    same database iOS / macOS / Linux / Node use). 400+ real entries.
  - Each option label is "City — UTC±HH:MM" (current offset, DST-aware).
  - Sorted by offset within each region so users can scan top-to-bottom.
  - Search input above the <select> filters options + optgroups live;
    matches against city name, full IANA path, and offset string.
  - Browser auto-detect (when $autodetect=true): on first paint, picks
    Intl.DateTimeFormat().resolvedOptions().timeZone if it's in the list;
    otherwise falls back to $defaultTo (Asia/Kolkata by default).
  - If the user explicitly chose something (validation re-fill or saved
    value), data-initial-tz is set and autodetect is skipped.

  Vars:
    $name         — name attribute (default 'timezone')
    $id           — id attribute (default 'timezone')
    $selected     — explicit pre-selection (e.g. old('timezone') or saved)
    $defaultTo    — fallback when $selected is null (default 'Asia/Kolkata')
    $autodetect   — true on register; false on settings (user already chose)
    $extraClass   — Tailwind classes appended to the <select>
--}}
@php
    $name        = $name        ?? 'timezone';
    $id          = $id          ?? 'timezone';
    $selected    = $selected    ?? null;
    $defaultTo   = $defaultTo   ?? 'Asia/Kolkata';
    $autodetect  = $autodetect  ?? false;
    $extraClass  = $extraClass  ?? 'w-full rounded-md bg-slate-900 border border-slate-700 px-3 py-2';

    // What the markup pre-selects: an explicit user choice if we have one,
    // otherwise the configured default. Autodetect can still override on the
    // client when no explicit choice was made.
    $effective = $selected ?: $defaultTo;

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

    $grouped = [];
    foreach (\DateTimeZone::listIdentifiers() as $tz) {
        $region = str_contains($tz, '/') ? explode('/', $tz, 2)[0] : 'Other';
        $offset = $offsetLabel($tz);
        $city   = str_contains($tz, '/') ? str_replace('_', ' ', substr($tz, strpos($tz, '/') + 1)) : $tz;
        $city   = str_replace('_', ' ', $city);
        $grouped[$region][] = [
            'id'     => $tz,
            'label'  => $city.' — '.$offset,
            'offset' => (new \DateTime('now', new \DateTimeZone($tz)))->getOffset(),
        ];
    }
    foreach ($grouped as &$list) {
        usort($list, fn ($a, $b) => $a['offset'] <=> $b['offset'] ?: strcmp($a['label'], $b['label']));
    }
    unset($list);
@endphp

<div data-tz-combo>
    {{-- Search input — filters the <select> options below as you type. --}}
    <div class="relative mb-2">
        <input type="text"
            data-tz-search-for="{{ $id }}"
            placeholder="Search timezone (city, region, or offset)..."
            autocomplete="off"
            class="w-full rounded-md bg-slate-900 border border-slate-700 px-9 py-2 text-sm text-slate-100 placeholder-slate-500 focus:border-cyan-400/40 focus:outline-none focus:ring-1 focus:ring-cyan-400/20"
            aria-label="Search timezones">
        {{-- Magnifier icon --}}
        <svg class="absolute left-2.5 top-2.5 h-4 w-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3" stroke-linecap="round"/>
        </svg>
        {{-- "no matches" hint, hidden by default --}}
        <span data-tz-no-match
            class="absolute right-2.5 top-2 text-[0.65rem] uppercase tracking-wider text-amber-400/80 hidden">
            no matches
        </span>
    </div>

    <select id="{{ $id }}" name="{{ $name }}" required class="{{ $extraClass }}"
        @if ($autodetect) data-tz-autodetect="1" @endif
        @if ($selected) data-initial-tz="{{ $selected }}" @endif
        data-fallback-tz="{{ $defaultTo }}">
        <option value="UTC" @selected($effective === 'UTC')>UTC — UTC+0:00</option>
        @foreach ($regionLabels as $region => $label)
            @if (! empty($grouped[$region]))
                <optgroup label="{{ $label }}">
                    @foreach ($grouped[$region] as $entry)
                        <option value="{{ $entry['id'] }}" @selected($effective === $entry['id'])>
                            {{ $entry['label'] }}
                        </option>
                    @endforeach
                </optgroup>
            @endif
        @endforeach
    </select>
</div>

<script>
    (() => {
        // ── Auto-detect: pick the browser's IANA timezone on fresh forms ──
        // Skipped if the server pre-selected a real user choice (data-initial-tz).
        // If the browser zone isn't in our list, falls back to data-fallback-tz
        // (Asia/Kolkata by default), which is also already pre-selected by markup.
        document.querySelectorAll('select[data-tz-autodetect="1"]').forEach((sel) => {
            if (sel.dataset.initialTz) return;        // explicit selection — leave alone
            const fallback = sel.dataset.fallbackTz || 'Asia/Kolkata';
            const has = (v) => [...sel.options].some((o) => o.value === v);
            try {
                const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
                if (tz && has(tz)) {
                    sel.value = tz;
                    return;
                }
            } catch {}
            if (has(fallback)) sel.value = fallback;
        });

        // ── Live search: filters options inside the <select> as user types. ──
        // Hides non-matching options and any optgroup that ends up empty.
        // Matches against the option label, the IANA value, and the visible
        // optgroup label so "asia", "kolkata", "+5:30", "ist" all work.
        document.querySelectorAll('input[data-tz-search-for]').forEach((input) => {
            const sel = document.getElementById(input.dataset.tzSearchFor);
            if (!sel) return;
            const noMatch = input.parentElement.querySelector('[data-tz-no-match]');

            const apply = () => {
                const term = input.value.trim().toLowerCase();
                let visible = 0;

                // Group children
                sel.querySelectorAll('optgroup').forEach((og) => {
                    const groupLabel = (og.getAttribute('label') || '').toLowerCase();
                    let groupHas = false;
                    og.querySelectorAll('option').forEach((opt) => {
                        const text = (opt.textContent || '').toLowerCase();
                        const value = (opt.value || '').toLowerCase();
                        const match = !term
                            || text.includes(term)
                            || value.includes(term)
                            || groupLabel.includes(term);
                        opt.hidden = !match;
                        opt.disabled = !match;
                        if (match) { groupHas = true; visible++; }
                    });
                    og.hidden = !groupHas;
                    og.disabled = !groupHas;
                });

                // Top-level options (UTC entry)
                Array.from(sel.children).forEach((child) => {
                    if (child.tagName !== 'OPTION') return;
                    const text = (child.textContent || '').toLowerCase();
                    const value = (child.value || '').toLowerCase();
                    const match = !term || text.includes(term) || value.includes(term);
                    child.hidden = !match;
                    child.disabled = !match;
                    if (match) visible++;
                });

                if (noMatch) noMatch.classList.toggle('hidden', visible !== 0);
            };

            input.addEventListener('input', apply);
            // Pressing Enter inside the search box opens the select instead
            // of submitting the form prematurely.
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    sel.focus();
                }
            });
        });
    })();
</script>
