<?php

namespace App\Http\Controllers;

use App\Mail\UserDataBackupMail;
use App\Models\DataExportLog;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        // Eager bookkeeping. If the queued job fails, `failed()` flips
        // the log row to 'failed' — but we keep the timestamp bump so
        // the user knows the system tried.
        $user->forceFill([
            'backup_last_sent_at' => now(),
            'backup_email_address' => $data['email'], // remember for auto-daily
        ])->save();

        Mail::to($data['email'])->queue(
            new UserDataBackupMail($user, $log->id, $start, $end, $type)
        );

        return back()->with(
            'toast',
            'Backup queued — it should land in '.$data['email'].' within a minute or two.'
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
