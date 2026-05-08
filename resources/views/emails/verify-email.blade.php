{{--
  Branded email-verification template.
  Inline styles only — Gmail and many other clients strip <style> tags.
  Tables for layout — older clients (and some modern ones) ignore flex/grid.
  600px max width, ~14-15px body font, single CTA button + fallback text link.
--}}
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <title>Verify your email — Time Management Pet</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f5f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#0f172a;-webkit-font-smoothing:antialiased;">

    {{-- Hidden preheader (preview text shown in Gmail/Outlook list view) --}}
    <div style="display:none;max-height:0;overflow:hidden;font-size:1px;line-height:1px;color:#f4f5f7;opacity:0;">
        Confirm your email so we can finish setting up your Time Management Pet account.
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
                                        <span style="display:inline-block;color:#f1f5f9;font-size:13px;letter-spacing:3px;text-transform:uppercase;font-weight:600;vertical-align:middle;">Time Management Pet</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- ── Body ───────────────────────────────────────── --}}
                    <tr>
                        <td style="padding:40px 36px 16px;">
                            <h1 style="margin:0 0 18px;font-size:22px;line-height:1.3;font-weight:700;color:#0f172a;letter-spacing:-0.01em;">
                                Verify your email address
                            </h1>
                            <p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#1e293b;">
                                Hi {{ $user?->name ?? 'there' }},
                            </p>
                            <p style="margin:0 0 28px;font-size:15px;line-height:1.6;color:#334155;">
                                Welcome to Time Management Pet. Please confirm this is your email address so you can start logging time blocks, tracking goals, and seeing your daily efficiency. The link expires in 60 minutes.
                            </p>

                            {{-- CTA button (table-based for Outlook compatibility) --}}
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td align="center" bgcolor="#00e0ff" style="border-radius:8px;">
                                        <a href="{{ $url }}"
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

                {{-- Outer footer --}}
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
