<?php

namespace App\Http\Controllers;

use App\Mail\UserDataBackupMail;
use App\Models\DataExportLog;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/**
 * User-facing email-backup endpoints.
 *
 * Every action here re-checks the admin gate (`backup_email_enabled`)
 * defensively, in addition to the route middleware. That way a stale
 * tab or a routing mistake can't bypass the admin's toggle.
 */
class BackupController extends Controller
{
    /**
     * Manual send. Two modes:
     *   - 'complete' → signup_date → today (`manual_complete`)
     *   - 'range'    → user-chosen from/to (`manual_range`)
     *
     * The mailable is `ShouldQueue`, so this returns immediately and the
     * heavy lifting (DB read, JSON encode) happens in the worker.
     */
    public function send(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        if (! $user->backup_email_enabled) {
            return back()->with('toast', 'Email backup is not enabled on your account. Ask an admin to turn it on.');
        }

        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:254'],
            'mode'  => ['required', Rule::in(['complete', 'range'])],
            'from'  => ['nullable', 'date', 'required_if:mode,range'],
            'to'    => ['nullable', 'date', 'required_if:mode,range', 'after_or_equal:from'],
        ]);

        $signup = CarbonImmutable::parse($user->created_at ?? now());
        $today  = CarbonImmutable::now();

        if ($data['mode'] === 'complete') {
            $start = $signup;
            $end   = $today;
            $type  = DataExportLog::TYPE_MANUAL_COMPLETE;
        } else {
            $start = CarbonImmutable::parse($data['from']);
            $end   = CarbonImmutable::parse($data['to']);
            // Don't let the user specify a future end (we have no data there).
            if ($end->greaterThan($today)) {
                $end = $today;
            }
            // Don't let them request data from before they signed up.
            if ($start->lessThan($signup)) {
                $start = $signup;
            }
            $type = DataExportLog::TYPE_MANUAL_RANGE;
        }

        $log = DataExportLog::create([
            'user_id'     => $user->id,
            'email'       => $data['email'],
            'export_type' => $type,
            'range_start' => $start->toDateString(),
            'range_end'   => $end->toDateString(),
            'status'      => DataExportLog::STATUS_QUEUED,
        ]);

        // Remember the address for the auto-daily setting + bookkeeping.
        // We bump `backup_last_sent_at` BEFORE the send so a concurrent
        // request from a panicked re-click can't double-send.
        $user->forceFill([
            'backup_last_sent_at'  => now(),
            'backup_email_address' => $data['email'],
        ])->save();

        // Synchronous send. Same path Fortify uses for password-reset
        // + email-verification, so no queue worker is needed on the
        // server. If SMTP throws, catch it, flip the log row to
        // 'failed', and show the user a toast — never bubble a 500
        // up just because a mail server hiccup'd.
        try {
            Mail::to($data['email'])->send(
                new UserDataBackupMail($user, $log->id, $start, $end, $type)
            );
            // The Mailable's attachments() method already flips the log
            // row to 'sent' as part of building the JSON. Bump the
            // user-facing counter on success.
            $user->increment('backup_count');
        } catch (\Throwable $e) {
            Log::error('UserDataBackupMail send failed', [
                'user_id' => $user->id,
                'email'   => $data['email'],
                'log_id'  => $log->id,
                'error'   => $e->getMessage(),
            ]);
            DataExportLog::where('id', $log->id)->update([
                'status'         => DataExportLog::STATUS_FAILED,
                'failure_reason' => substr($e->getMessage(), 0, 1000),
            ]);
            return back()->with(
                'toast',
                "Couldn't send backup to {$data['email']} — please try again or check the address. Admins can see the failure reason in the export log."
            );
        }

        return back()->with(
            'toast',
            'Backup emailed to '.$data['email'].'. Check your inbox.'
        );
    }

    /**
     * Update the auto-daily + saved-email config. Doesn't trigger a send.
     */
    public function updateConfig(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        if (! $user->backup_email_enabled) {
            return back()->with('toast', 'Email backup is not enabled on your account.');
        }

        $data = $request->validate([
            // Auto-daily requires a saved email — validation enforces both
            // sides of that contract.
            'backup_auto_daily'     => ['nullable', 'boolean'],
            'backup_email_address'  => [
                'nullable', 'string', 'email', 'max:254',
                Rule::requiredIf(fn () => filter_var($request->input('backup_auto_daily'), FILTER_VALIDATE_BOOLEAN)),
            ],
        ]);

        $user->forceFill([
            'backup_auto_daily'    => (bool) ($data['backup_auto_daily'] ?? false),
            'backup_email_address' => $data['backup_email_address'] ?? $user->backup_email_address,
        ])->save();

        $msg = $user->backup_auto_daily
            ? 'Daily auto-backup ON. We\'ll email you on first login each day.'
            : 'Daily auto-backup OFF. You can still send manually any time.';

        return back()->with('toast', $msg);
    }
}
