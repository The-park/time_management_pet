{{--
  Branded email-verification template.
  - Inline styles for static rendering (Gmail/Outlook-safe)
  - <style> block for CSS animations — Apple Mail, iOS Mail, Thunderbird,
    most native clients honour @keyframes; clients that strip <style> just
    get the polished static design.
  - Inline SVG logo (clock face with cute pet-ears) — modern clients render
    SVG fine; the wordmark is right next to it as a fallback identity.
--}}
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <title>Verify your email — Time Management Pet</title>

    <style>
        /* Animations applied as progressive enhancement — clients that strip
           <style> tags simply skip these and the static design still looks great. */
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
            @keyframes tmp-cta {
                0%, 100% { box-shadow: 0 0 0 0 rgba(0, 224, 255, 0.45); }
                50%      { box-shadow: 0 0 0 8px rgba(0, 224, 255, 0); }
            }

            .tmp-logo       { animation: tmp-glow 2.6s ease-in-out infinite; }
            .tmp-second     { transform-origin: 16px 18px; animation: tmp-tick 60s linear infinite; }
            .tmp-header     {
                background-image: linear-gradient(135deg,#0f172a 0%,#1e293b 40%,#0f172a 60%,#1e293b 100%);
                background-size: 200% 100%;
                animation: tmp-shimmer 8s ease-in-out infinite;
            }
            .tmp-rise       { animation: tmp-rise 0.7s ease-out both; }
            .tmp-cta        { animation: tmp-cta 2.2s ease-in-out infinite; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#f4f5f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#0f172a;-webkit-font-smoothing:antialiased;">

    {{-- Hidden preheader (preview shown by Gmail/Outlook in the inbox list) --}}
    <div style="display:none;max-height:0;overflow:hidden;font-size:1px;line-height:1px;color:#f4f5f7;opacity:0;">
        Confirm your email so we can finish setting up your Time Management Pet account.
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
                                        {{-- Inline SVG logo: clock face + pet-ears mark --}}
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
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- ── Body ───────────────────────────────────────── --}}
                    <tr>
                        <td class="tmp-rise" style="padding:40px 36px 16px;">
                            <h1 style="margin:0 0 18px;font-size:22px;line-height:1.3;font-weight:700;color:#0f172a;letter-spacing:-0.01em;">
                                Verify your email address
                            </h1>
                            <p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#1e293b;">
                                Hi {{ $user?->name ?? 'there' }},
                            </p>
                            <p style="margin:0 0 28px;font-size:15px;line-height:1.6;color:#334155;">
                                Welcome to Time Management Pet. Please confirm this is your email address so you can start logging time blocks, tracking goals, and seeing your daily efficiency. The link expires in 60 minutes.
                            </p>

                            {{-- CTA button --}}
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td align="center" bgcolor="#00e0ff" style="border-radius:8px;">
                                        <a href="{{ $url }}" class="tmp-cta"
                                            style="display:inline-block;background-color:#00e0ff;color:#0f172a;font-size:14px;font-weight:700;text-decoration:none;padding:13px 32px;border-radius:8px;letter-spacing:0.4px;mso-padding-alt:0;">
                                            Verify email →
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:32px 0 6px;font-size:12px;line-height:1.5;color:#64748b;">
                                Button not working? Paste this link into your browser:
                            </p>
                            <p style="margin:0;font-size:12px;line-height:1.5;word-break:break-all;">
                                <a href="{{ $url }}" style="color:#0ea5e9;text-decoration:underline;">{{ $url }}</a>
                            </p>
                        </td>
                    </tr>

                    {{-- ── Divider + footer note ──────────────────────── --}}
                    <tr>
                        <td style="padding:24px 36px 36px;">
                            <hr style="border:none;border-top:1px solid #e2e8f0;margin:0 0 18px;">
                            <p style="margin:0;font-size:12px;line-height:1.6;color:#94a3b8;">
                                Didn't sign up for Time Management Pet? You can safely ignore this email — your address won't be added to anything.
                            </p>
                        </td>
                    </tr>

                </table>

                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px;width:100%;margin-top:14px;">
                    <tr>
                        <td align="center" style="padding:0 16px;">
                            <p style="margin:0;font-size:11px;line-height:1.6;color:#94a3b8;">
                                © {{ date('Y') }} Time Management Pet · Sent from <a href="{{ config('app.url') }}" style="color:#94a3b8;text-decoration:underline;">{{ str_replace(['https://','http://'], '', config('app.url')) }}</a>
                            </p>
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>

</body>
</html>
