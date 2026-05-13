<?php

namespace App\Http\Middleware;

use App\Mail\UserDataBackupMail;
use App\Models\DataExportLog;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fires the daily email-backup on the user's first dashboard visit of
 * the day. Cron-free: if the user doesn't log in, no mail goes out.
 *
 * Idempotency: gated by `users.backup_last_sent_at`. Even on parallel
 * dashboard hits (browser refresh storms), only the first request
 * inside the same calendar day (in the user's timezone) will queue a
 * job. We re-check the timestamp AFTER the queue dispatch so a small
 * race window still won't double-fire because the eager
 * `backup_last_sent_at = now()` update happens before `Mail::queue()`
 * returns control.
 *
 * The mail is queued, not sent inline, so even on a slow worker this
 * never adds noticeable latency to the dashboard load.
 */
class TriggerDailyBackup
{
    public function handle(Request $request, Closure $next): Response
    {
        // Only run on GET requests by authenticated users. Anything
        // else (POST, AJAX, guest) is a no-op so the rest of the app
        // is unaffected.
        $user = $request->user();
        if ($user
            && $request->isMethod('GET')
            && $user->backup_email_enabled
            && $user->backup_auto_daily
            && ! empty($user->backup_email_address)
        ) {
            try {
                $this->maybeDispatch($user);
            } catch (\Throwable $e) {
                // NEVER block the dashboard for a mail problem. Log
                // and move on. The user can still trigger manually
                // from /settings, and the audit row reflects the
                // failure so admins can see what went wrong.
                report($e);
                // Best-effort: mark the most-recent queued log row for
                // this user as failed so the admin tile reflects it.
                try {
                    \App\Models\DataExportLog::where('user_id', $user->id)
                        ->where('status', \App\Models\DataExportLog::STATUS_QUEUED)
                        ->latest('id')
                        ->limit(1)
                        ->update([
                            'status' => \App\Models\DataExportLog::STATUS_FAILED,
                            'failure_reason' => substr($e->getMessage(), 0, 1000),
                        ]);
                } catch (\Throwable $inner) {
                    // Swallow — we're already in a fail-safe path.
                }
            }
        }
        return $next($request);
    }

    private function maybeDispatch($user): void
    {
        $tz = $user->timezone ?: 'UTC';
        $nowLocal   = CarbonImmutable::now($tz);
        $todayLocal = $nowLocal->startOfDay();

        $lastSent = $user->backup_last_sent_at
            ? CarbonImmutable::parse($user->backup_last_sent_at)->setTimezone($tz)
            : null;

        // Already sent today in the user's timezone — bail.
        if ($lastSent && $lastSent->greaterThanOrEqualTo($todayLocal)) {
            return;
        }

        $signup = CarbonImmutable::parse($user->created_at ?? now());
        // Daily auto-send always covers signup → end of yesterday.
        $rangeStart = $signup;
        $rangeEnd   = $nowLocal->subDay()->endOfDay()->setTimezone(config('app.timezone', 'UTC'));

        // Defensive: if user signed up today, there's nothing to back
        // up yet. Skip silently — no log row, no mail.
        if ($rangeEnd->lessThan($signup->startOfDay())) {
            return;
        }

        $log = DataExportLog::create([
            'user_id'     => $user->id,
            'email'       => $user->backup_email_address,
            'export_type' => DataExportLog::TYPE_AUTO_DAILY,
            'range_start' => $rangeStart->toDateString(),
            'range_end'   => $rangeEnd->toDateString(),
            'status'      => DataExportLog::STATUS_QUEUED,
        ]);

        // Update the idempotency marker BEFORE sending so a parallel
        // dashboard load in the same session won't see a stale
        // "not sent today" value and trigger a second send.
        $user->forceFill(['backup_last_sent_at' => now()])->save();

        // Synchronous send (no queue worker required on the server) —
        // matches password-reset / verify-email flow. The outer
        // try/catch in handle() turns any send failure into a
        // logged-and-swallowed error so the dashboard never 500s.
        Mail::to($user->backup_email_address)->send(
            new UserDataBackupMail(
                $user,
                $log->id,
                $rangeStart,
                $rangeEnd,
                DataExportLog::TYPE_AUTO_DAILY,
            )
        );

        // Bump the user-facing counter only on successful send. If the
        // send throws, control bubbles to handle()'s outer try/catch.
        $user->increment('backup_count');
    }
}
