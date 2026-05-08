{{--
  Branded password-reset email. Mirrors the verify-email layout for visual
  consistency. Uses an amber accent on the CTA + a clear "you can ignore this"
  fallback so users who didn't request a reset aren't worried.
--}}
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <title>Reset your password — Track Your Time</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f5f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#0f172a;-webkit-font-smoothing:antialiased;">

    <div style="display:none;max-height:0;overflow:hidden;font-size:1px;line-height:1px;color:#f4f5f7;opacity:0;">
        Reset your Track Your Time password — link expires in 60 minutes.
    </div>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#f4f5f7;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px;width:100%;background-color:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 1px 3px rgba(15,23,42,0.06),0 1px 2px rgba(15,23,42,0.04);">

                    {{-- ── Header ─────────────────────────────────────── --}}
                    <tr>
                        <td style="background:linear-gradient(135deg,#0f172a 0%,#1e293b 100%);padding:28px 36px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                <tr>
                                    <td>
                                        <span style="display:inline-block;width:9px;height:9px;border-radius:50%;background-color:#00e0ff;box-shadow:0 0 12px rgba(0,224,255,0.6);vertical-align:middle;margin-right:10px;"></span>
                                        <span style="display:inline-block;color:#f1f5f9;font-size:13px;letter-spacing:3px;text-transform:uppercase;font-weight:600;vertical-align:middle;">Track Your Time</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- ── Body ───────────────────────────────────────── --}}
                    <tr>
                        <td style="padding:40px 36px 16px;">
                            <div style="display:inline-block;background-color:#fef3c7;color:#92400e;font-size:11px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;padding:4px 10px;border-radius:999px;margin-bottom:18px;">
                                Password reset
                            </div>
                            <h1 style="margin:0 0 18px;font-size:22px;line-height:1.3;font-weight:700;color:#0f172a;letter-spacing:-0.01em;">
                                Reset your password
                            </h1>
                            <p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#1e293b;">
                                Hi {{ $user?->name ?? 'there' }},
                            </p>
                            <p style="margin:0 0 28px;font-size:15px;line-height:1.6;color:#334155;">
                                We received a request to reset the password for your Track Your Time account ({{ $user?->email ?? 'this address' }}). Click the button below to set a new one. The link expires in 60 minutes.
                            </p>

                            {{-- CTA button --}}
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td align="center" bgcolor="#f59e0b" style="border-radius:8px;">
                                        <a href="{{ $url }}"
                                            style="display:inline-block;background-color:#f59e0b;color:#0f172a;font-size:14px;font-weight:700;text-decoration:none;padding:13px 32px;border-radius:8px;letter-spacing:0.4px;mso-padding-alt:0;">
                                            Reset password →
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
                            <p style="margin:0 0 8px;font-size:12px;line-height:1.6;color:#94a3b8;">
                                Didn't request a password reset? You can safely ignore this email — your password won't change unless you click the link above.
                            </p>
                            <p style="margin:0;font-size:12px;line-height:1.6;color:#94a3b8;">
                                If you're getting these regularly, please <a href="{{ config('app.url') }}" style="color:#94a3b8;text-decoration:underline;">contact us</a>.
                            </p>
                        </td>
                    </tr>

                </table>

                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px;width:100%;margin-top:14px;">
                    <tr>
                        <td align="center" style="padding:0 16px;">
                            <p style="margin:0;font-size:11px;line-height:1.6;color:#94a3b8;">
                                © {{ date('Y') }} Track Your Time · Sent from <a href="{{ config('app.url') }}" style="color:#94a3b8;text-decoration:underline;">{{ str_replace(['https://','http://'], '', config('app.url')) }}</a>
                            </p>
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>

</body>
</html>
