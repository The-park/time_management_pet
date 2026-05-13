<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your {{ config('app.name', 'Time Management Pet') }} backup</title>
</head>
<body style="margin:0; padding:0; background:#0b1018; color:#e2e8f0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height:1.55;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#0b1018; padding:32px 16px;">
    <tr>
        <td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px; width:100%; background:#0f172a; border:1px solid #1e293b; border-radius:14px;">
                <tr>
                    <td style="padding:24px 28px 8px 28px;">
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td style="width:12px;">
                                    <div style="width:10px; height:10px; border-radius:9999px; background:#00e0ff; box-shadow:0 0 14px #00e0ff;"></div>
                                </td>
                                <td style="padding-left:10px; font-size:14px; letter-spacing:3px; text-transform:uppercase; color:#94a3b8;">
                                    {{ config('app.name', 'Time Management Pet') }}
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:8px 28px 0 28px;">
                        <h1 style="margin:8px 0 0 0; font-size:22px; line-height:1.3; color:#f8fafc; letter-spacing:0.5px;">
                            Your data backup is attached
                        </h1>
                        <p style="margin:8px 0 0 0; color:#cbd5e1; font-size:14px;">
                            Hi {{ $user->name ?? 'there' }}, here's the export you requested.
                        </p>
                    </td>
                </tr>

                <tr>
                    <td style="padding:20px 28px 4px 28px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                            style="background:#0b1320; border:1px solid #1e293b; border-radius:10px;">
                            <tr>
                                <td style="padding:14px 16px;">
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                        <tr>
                                            <td style="font-size:11px; letter-spacing:2px; text-transform:uppercase; color:#64748b;">Range</td>
                                            <td style="font-size:11px; letter-spacing:2px; text-transform:uppercase; color:#64748b; text-align:right;">Trigger</td>
                                        </tr>
                                        <tr>
                                            <td style="font-size:14px; color:#f1f5f9; padding-top:4px;">
                                                {{ $rangeStart ?: '—' }} &rarr; {{ $rangeEnd ?: '—' }}
                                            </td>
                                            <td style="font-size:14px; color:#f1f5f9; text-align:right; padding-top:4px;">
                                                @switch($exportType)
                                                    @case('manual_complete') Complete export (manual) @break
                                                    @case('manual_range')    Date-range export (manual) @break
                                                    @case('auto_daily')      Daily auto-backup @break
                                                    @default                 {{ $exportType }}
                                                @endswitch
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:0 16px 14px 16px;">
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                        <tr>
                                            <td style="font-size:11px; letter-spacing:2px; text-transform:uppercase; color:#64748b;">Time blocks</td>
                                            <td style="font-size:11px; letter-spacing:2px; text-transform:uppercase; color:#64748b; text-align:right;">Goals</td>
                                        </tr>
                                        <tr>
                                            <td style="font-size:14px; color:#f1f5f9; padding-top:4px;">
                                                {{ number_format((int) ($blocksCount ?? 0)) }}
                                            </td>
                                            <td style="font-size:14px; color:#f1f5f9; text-align:right; padding-top:4px;">
                                                {{ number_format((int) ($goalsCount ?? 0)) }}
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="padding:18px 28px 0 28px;">
                        <p style="margin:0; color:#cbd5e1; font-size:14px;">
                            The attached <code style="background:#0b1320; padding:2px 6px; border-radius:4px; border:1px solid #1e293b; font-size:13px;">.json</code>
                            file contains your time blocks and goals in a stable schema (schema_version: 1).
                            You can open it in any text editor, import it back into the app later, or hand it to a script of your own.
                        </p>
                    </td>
                </tr>

                @if ($exportType === 'auto_daily')
                <tr>
                    <td style="padding:14px 28px 0 28px;">
                        <p style="margin:0; color:#94a3b8; font-size:12px;">
                            You're receiving this because <strong>daily auto-backup</strong> is enabled on your account.
                            Turn it off any time from <em>Settings &rarr; Email backup</em>.
                        </p>
                    </td>
                </tr>
                @endif

                <tr>
                    <td style="padding:24px 28px 24px 28px;">
                        <hr style="border:none; border-top:1px solid #1e293b; margin:0 0 12px 0;">
                        <p style="margin:0; color:#64748b; font-size:11px; letter-spacing:1px; text-transform:uppercase;">
                            Generated for {{ $user->email }} &middot; keep this file safe
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
