{{--
  Branded data-backup email template.
  - Mirrors emails/verify-email.blade.php + emails/reset-password.blade.php
    so the user feels the same identity across the auth flow + the new
    data-export flow.
  - Inline styles for Gmail/Outlook safety; <style> block for progressive
    animation enhancements on supporting clients.
  - The attached JSON file does the actual work — this body explains
    what's in it, the range it covers, and (when applicable) the
    auto-daily settings note so users know how to turn it off.
--}}
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <title>Your Time Management Pet data backup</title>

    <style>
        /* Progressive enhancement only — clients that strip <style>
           still get the polished static design. */
        @media (prefers-reduced-motion: no-preference) {
            @keyframes tmp-glow {
                0%, 100% { filter: drop-shadow(0 0 0 rgba(0, 224, 255, 0)); }
                50%      { filter: drop-shadow(0 0 10px rgba(0, 224, 255, 0.55)); }
            }
            @keyframes tmp-tick {
                from { transform: rotate(0deg); }
                to   { transform: rotate(360deg); }
            }
            @keyframes tmp-shimmer {
                0%   { background-position: -200% 0; }
                100% { background-position: 200% 0; }
            }
            @keyframes tmp-rise {
                from { opacity: 0; transform: translateY(10px); }
                to   { opacity: 1; transform: translateY(0); }
            }
            .tmp-logo   { animation: tmp-glow 2.6s ease-in-out infinite; }
            .tmp-second { transform-origin: 16px 18px; animation: tmp-tick 60s linear infinite; }
            .tmp-header {
                background-image: linear-gradient(135deg,#0f172a 0%,#1e293b 40%,#0f172a 60%,#1e293b 100%);
                background-size: 200% 100%;
                animation: tmp-shimmer 8s ease-in-out infinite;
            }
            .tmp-rise   { animation: tmp-rise 0.7s ease-out both; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#f4f5f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#0f172a;-webkit-font-smoothing:antialiased;">

    {{-- Hidden preheader (preview text shown in the inbox list) --}}
    <div style="display:none;max-height:0;overflow:hidden;font-size:1px;line-height:1px;color:#f4f5f7;opacity:0;">
        Your Time Management Pet data is attached as a JSON file. Range: {{ $rangeStart ?: '—' }} to {{ $rangeEnd ?: '—' }}.
    </div>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#f4f5f7;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px;width:100%;background-color:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 1px 3px rgba(15,23,42,0.06),0 1px 2px rgba(15,23,42,0.04);">

                    {{-- ── Header (gradient w/ animated shimmer in supporting clients) ── --}}
                    <tr>
                        <td class="tmp-header" style="background:linear-gradient(135deg,#0f172a 0%,#1e293b 100%);padding:28px 36px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                <tr>
                                    <td>
                                        <span class="tmp-logo" style="display:inline-block;vertical-align:middle;margin-right:12px;line-height:0;">
                                            <svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                                <!-- Pet ears -->
                                                <path d="M9 8 L7.2 2.8 L12.5 6.2 Z" fill="#00e0ff" opacity="0.95"/>
                                                <path d="M23 8 L24.8 2.8 L19.5 6.2 Z" fill="#00e0ff" opacity="0.95"/>
                                                <!-- Outer clock ring -->
                                                <circle cx="16" cy="18" r="10.5" fill="#0b1424" stroke="#00e0ff" stroke-width="1.8"/>
                                                <!-- Tick marks -->
                                                <line x1="16" y1="9.5"  x2="16" y2="11"  stroke="#00e0ff" stroke-width="1.4" stroke-linecap="round"/>
                                                <line x1="16" y1="25"   x2="16" y2="26.5" stroke="#00e0ff" stroke-width="1.4" stroke-linecap="round"/>
                                                <line x1="7.5" y1="18"  x2="9"  y2="18"  stroke="#00e0ff" stroke-width="1.4" stroke-linecap="round"/>
                                                <line x1="23" y1="18"   x2="24.5" y2="18" stroke="#00e0ff" stroke-width="1.4" stroke-linecap="round"/>
                                                <!-- Hour + minute hands -->
                                                <line x1="16" y1="18" x2="16"   y2="12.5" stroke="#f1f5f9" stroke-width="1.8" stroke-linecap="round"/>
                                                <line x1="16" y1="18" x2="20"   y2="20"   stroke="#f1f5f9" stroke-width="1.8" stroke-linecap="round"/>
                                                <!-- Sweeping second hand (animated) -->
                                                <line class="tmp-second" x1="16" y1="18" x2="16" y2="10" stroke="#ff6b1a" stroke-width="1" stroke-linecap="round"/>
                                                <!-- Centre dot -->
                                                <circle cx="16" cy="18" r="1.6" fill="#00e0ff"/>
                                            </svg>
                                        </span>
                                        <span style="display:inline-block;color:#f1f5f9;font-size:13px;letter-spacing:3px;text-transform:uppercase;font-weight:700;vertical-align:middle;">Time Management Pet</span>
                                    </td>
                                    <td align="right" style="vertical-align:middle;">
                                        <span style="display:inline-block;background:rgba(0,224,255,0.12);color:#67e8f9;font-size:10px;letter-spacing:2px;text-transform:uppercase;font-weight:700;padding:5px 10px;border-radius:999px;border:1px solid rgba(0,224,255,0.3);">
                                            Data backup
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- ── Body ───────────────────────────────────────── --}}
                    <tr>
                        <td class="tmp-rise" style="padding:40px 36px 8px;">
                            <h1 style="margin:0 0 18px;font-size:22px;line-height:1.3;font-weight:700;color:#0f172a;letter-spacing:-0.01em;">
                                Your data backup is attached
                            </h1>
                            <p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#1e293b;">
                                Hi {{ $user->name ?? 'there' }},
                            </p>
                            <p style="margin:0 0 24px;font-size:15px;line-height:1.6;color:#334155;">
                                Your time blocks and goals from <strong>Time Management Pet</strong> are attached as a JSON
                                file. Open it in any text editor, import it back into the app later, or hand it to a script
                                of your own — the schema is stable and documented (<code style="background:#f1f5f9;padding:1px 5px;border-radius:4px;font-size:13px;">schema_version: 1</code>).
                            </p>
                        </td>
                    </tr>

                    {{-- ── Snapshot card ──────────────────────────────── --}}
                    <tr>
                        <td style="padding:0 36px 8px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
                                style="background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;">
                                <tr>
                                    <td style="padding:16px 18px;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                            <tr>
                                                <td style="font-size:10px;letter-spacing:2px;text-transform:uppercase;color:#64748b;font-weight:600;padding-bottom:4px;">
                                                    Range
                                                </td>
                                                <td align="right" style="font-size:10px;letter-spacing:2px;text-transform:uppercase;color:#64748b;font-weight:600;padding-bottom:4px;">
                                                    Trigger
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="font-size:14px;color:#0f172a;font-weight:600;padding-bottom:14px;">
                                                    {{ $rangeStart ?: '—' }} &rarr; {{ $rangeEnd ?: '—' }}
                                                </td>
                                                <td align="right" style="font-size:14px;color:#0f172a;font-weight:600;padding-bottom:14px;">
                                                    @switch($exportType)
                                                        @case('manual_complete') Complete export @break
                                                        @case('manual_range')    Date-range export @break
                                                        @case('auto_daily')      Daily auto-backup @break
                                                        @default                 {{ $exportType }}
                                                    @endswitch
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="font-size:10px;letter-spacing:2px;text-transform:uppercase;color:#64748b;font-weight:600;padding-bottom:4px;border-top:1px solid #e2e8f0;padding-top:14px;">
                                                    Time blocks
                                                </td>
                                                <td align="right" style="font-size:10px;letter-spacing:2px;text-transform:uppercase;color:#64748b;font-weight:600;padding-bottom:4px;border-top:1px solid #e2e8f0;padding-top:14px;">
                                                    Goals
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="font-size:18px;color:#0f172a;font-weight:700;font-variant-numeric:tabular-nums;">
                                                    {{ number_format((int) ($blocksCount ?? 0)) }}
                                                </td>
                                                <td align="right" style="font-size:18px;color:#0f172a;font-weight:700;font-variant-numeric:tabular-nums;">
                                                    {{ number_format((int) ($goalsCount ?? 0)) }}
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    @if ($exportType === 'auto_daily')
                    {{-- ── Auto-daily explainer ───────────────────────── --}}
                    <tr>
                        <td style="padding:18px 36px 0;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
                                style="background-color:#fefce8;border:1px solid #fde68a;border-radius:10px;">
                                <tr>
                                    <td style="padding:14px 18px;">
                                        <p style="margin:0;font-size:13px;line-height:1.55;color:#713f12;">
                                            You're receiving this automatically because <strong>daily auto-backup</strong>
                                            is enabled on your account. Turn it off any time from
                                            <a href="{{ rtrim(config('app.url'), '/') }}/settings" style="color:#a16207;text-decoration:underline;">Settings &rarr; Email backup</a>.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    @endif

                    {{-- ── Tip ────────────────────────────────────────── --}}
                    <tr>
                        <td style="padding:24px 36px 0;">
                            <p style="margin:0;font-size:13px;line-height:1.55;color:#475569;">
                                <strong style="color:#0f172a;">Tip:</strong> save the attachment somewhere safe (cloud
                                drive, password manager, or your usual backups folder). It's a plain JSON file — no
                                special tools required.
                            </p>
                        </td>
                    </tr>

                    {{-- ── Divider + footer note ──────────────────────── --}}
                    <tr>
                        <td style="padding:24px 36px 36px;">
                            <hr style="border:none;border-top:1px solid #e2e8f0;margin:0 0 18px;">
                            <p style="margin:0;font-size:12px;line-height:1.6;color:#94a3b8;">
                                Didn't request this? Your account address is <strong>{{ $user->email }}</strong> — if a
                                backup arrived at a different inbox you didn't recognise, sign in and review
                                <em>Settings &rarr; Email backup</em>.
                            </p>
                        </td>
                    </tr>

                </table>

                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px;width:100%;margin-top:14px;">
                    <tr>
                        <td align="center" style="padding:0 16px;">
                            <p style="margin:0;font-size:11px;line-height:1.6;color:#94a3b8;">
                                © {{ date('Y') }} Time Management Pet · Sent from
                                <a href="{{ config('app.url') }}" style="color:#94a3b8;text-decoration:underline;">{{ str_replace(['https://','http://'], '', config('app.url')) }}</a>
                            </p>
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>

</body>
</html>
