{{--
  Reusable IANA timezone <select>. Shared by /register and /settings so the
  two dropdowns can never drift apart.

  - Source of truth: PHP's DateTimeZone::listIdentifiers() (the IANA tzdata —
    same database iOS / macOS / Linux / Node use). 400+ real entries, no
    dummy values.
  - Each option label is "City — UTC±HH:MM" so users can scan by offset.
  - Reflects the *current* offset (handles DST automatically).
  - Grouped into <optgroup>s by region for fast finding.

  Required attributes / vars:
    $name           — name attribute for the <select> (default 'timezone')
    $id             — id attribute (default 'timezone')
    $selected       — currently-selected IANA identifier (e.g. old('timezone'))
    $autodetect     — true to add a tiny script that defaults the field to the
                      browser's own timezone if no $selected was supplied
    $extraClass     — Tailwind classes appended to the <select> (default sane)
--}}
@php
    $name        = $name        ?? 'timezone';
    $id          = $id          ?? 'timezone';
    $selected    = $selected    ?? null;
    $autodetect  = $autodetect  ?? false;
    $extraClass  = $extraClass  ?? 'w-full rounded-md bg-slate-900 border border-slate-700 px-3 py-2';

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
    // Static cache so we don't rebuild for repeated identical TZs.
    $offsetLabel = function (string $tz): string {
        static $cache = [];
        if (isset($cache[$tz])) return $cache[$tz];
        try {
            $z   = new \DateTimeZone($tz);
            $off = (new \DateTime('now', $z))->getOffset();      // seconds
            $h   = intdiv(abs($off), 3600);
            $m   = intdiv(abs($off) % 3600, 60);
            $sign = $off < 0 ? '−' : '+';                          // proper minus sign
            return $cache[$tz] = sprintf('UTC%s%d:%02d', $sign, $h, $m);
        } catch (\Throwable $e) {
            return $cache[$tz] = '';
        }
    };

    // Group identifiers by region, with each entry as ['id'=>..., 'label'=>...].
    // Sorted by offset within each region so users can scan top-to-bottom.
    $grouped = [];
    foreach (\DateTimeZone::listIdentifiers() as $tz) {
        $region = str_contains($tz, '/') ? explode('/', $tz, 2)[0] : 'Other';
        $offset = $offsetLabel($tz);
        $city   = str_contains($tz, '/') ? str_replace('_', ' ', substr($tz, strpos($tz, '/') + 1)) : $tz;
        // Replace remaining underscores everywhere (e.g. 'America/Indiana/Indianapolis')
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

<select id="{{ $id }}" name="{{ $name }}" required class="{{ $extraClass }}"
    @if ($autodetect) data-tz-autodetect="1" @endif
    @if ($selected) data-initial-tz="{{ $selected }}" @endif>
    <option value="UTC" @selected($selected === 'UTC')>UTC — UTC+0:00</option>
    @foreach ($regionLabels as $region => $label)
        @if (! empty($grouped[$region]))
            <optgroup label="{{ $label }}">
                @foreach ($grouped[$region] as $entry)
                    <option value="{{ $entry['id'] }}" @selected($selected === $entry['id'])>
                        {{ $entry['label'] }}
                    </option>
                @endforeach
            </optgroup>
        @endif
    @endforeach
</select>

@if ($autodetect)
    {{-- Default the dropdown to the browser's IANA timezone if the server
         didn't set one (i.e. fresh form, no validation-error pre-fill).
         Falls back to UTC if Intl is unavailable or the detected zone isn't
         in our list. Runs once at DOMContentLoaded. --}}
    <script>
        (() => {
            const sel = document.querySelector('select[data-tz-autodetect="1"]');
            if (!sel) return;
            // Only override the dropdown when the server didn't pre-select
            // anything — i.e. the first option ('UTC') is the chosen one only
            // because nothing better was provided. If a real selection exists
            // (validation re-fill or saved value), data-initial-tz is set.
            if (sel.dataset.initialTz) return;
            try {
                const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
                if (!tz) return;
                if ([...sel.options].some(o => o.value === tz)) {
                    sel.value = tz;
                }
            } catch {}
        })();
    </script>
@endif
