<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Countdown;
use App\Models\DailyGoal;
use App\Models\TimeBlock;
use App\Models\User;
use App\Services\AdminAudit;
use App\Services\SleepScheduleService;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function __construct(private SleepScheduleService $sleepSchedule)
    {
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $status = $request->query('status', 'all');

        $query = User::withTrashed();

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });
        }

        match ($status) {
            'active' => $query->whereNull('deleted_at')->where('status', 'active'),
            'suspended' => $query->whereNull('deleted_at')->where('status', 'suspended'),
            'deleted' => $query->whereNotNull('deleted_at'),
            default => null, // 'all'
        };

        $users = $query
            ->withCount([
                'timeBlocks as time_blocks_count' => fn ($q) => $q->withoutGlobalScopes(),
            ])
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'search' => $search,
            'status' => $status,
            'statusCounts' => [
                'all' => User::withTrashed()->count(),
                'active' => User::query()->where('status', 'active')->count(),
                'suspended' => User::query()->where('status', 'suspended')->count(),
                'deleted' => User::onlyTrashed()->count(),
            ],
        ]);
    }

    public function show(Request $request, $id)
    {
        $user = User::withTrashed()->findOrFail($id);

        AdminAudit::log('viewed_user', $user->id);

        // Calendar month is selectable via ?month=YYYY-MM. Defaults to the
        // user's current month in their timezone.
        $tz = $user->timezone ?: 'UTC';
        $today = CarbonImmutable::now($tz);
        $monthCursor = $request->query('month');
        if ($monthCursor && preg_match('/^\d{4}-\d{2}$/', $monthCursor)) {
            try {
                $monthStart = CarbonImmutable::createFromFormat('!Y-m', $monthCursor, $tz)->startOfMonth();
            } catch (\Throwable $e) {
                $monthStart = $today->startOfMonth();
            }
        } else {
            $monthStart = $today->startOfMonth();
        }
        $monthEnd = $monthStart->endOfMonth();

        // Pull all blocks in the month for the calendar.
        $monthBlocks = TimeBlock::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('duration_seconds', '>', 0)
            ->whereBetween('start_time', [$monthStart, $monthEnd])
            ->get(['start_time', 'duration_seconds', 'category', 'reason']);

        $byDate = $monthBlocks->groupBy(fn ($b) => $b->start_time->toDateString());

        // Build calendar grid: pad the first row to align Monday-first weeks.
        $firstWeekday = (int) $monthStart->dayOfWeek;       // Sun=0..Sat=6
        $padBefore = $firstWeekday === 0 ? 6 : $firstWeekday - 1;   // Mon-first
        $daysInMonth = (int) $monthStart->daysInMonth;

        $cells = [];
        for ($i = 0; $i < $padBefore; $i++) $cells[] = null;
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = $monthStart->setDay($d);
            $key = $date->toDateString();
            $blocks = $byDate[$key] ?? collect();
            $productiveSec = (int) $blocks->whereNotIn('category', ['wasted', 'neutral'])->sum('duration_seconds');
            $wastedSec = (int) $blocks->where('category', 'wasted')->sum('duration_seconds');
            $neutralSec = (int) $blocks->where('category', 'neutral')->sum('duration_seconds');
            $cells[] = [
                'date' => $date->toDateString(),
                'day' => $d,
                'is_today' => $date->isSameDay($today),
                'is_future' => $date->gt($today),
                'productive_seconds' => $productiveSec,
                'wasted_seconds' => $wastedSec,
                'neutral_seconds' => $neutralSec,
                'block_count' => $blocks->count(),
            ];
        }
        // Pad trailing cells so the grid is a multiple of 7.
        while (count($cells) % 7 !== 0) $cells[] = null;

        $monthTotals = [
            'productive_seconds' => (int) $monthBlocks->whereNotIn('category', ['wasted', 'neutral'])->sum('duration_seconds'),
            'wasted_seconds' => (int) $monthBlocks->where('category', 'wasted')->sum('duration_seconds'),
            'neutral_seconds' => (int) $monthBlocks->where('category', 'neutral')->sum('duration_seconds'),
            'block_count' => $monthBlocks->count(),
            'days_logged' => $byDate->count(),
        ];

        $timeBlocks = TimeBlock::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->orderByDesc('start_time')
            ->limit(50)
            ->get();

        $dailyGoals = DailyGoal::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->orderByDesc('date')
            ->limit(20)
            ->get();

        $countdowns = Countdown::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->orderByDesc('started_at')
            ->limit(20)
            ->get();

        $totals = [
            'time_blocks' => TimeBlock::withoutGlobalScopes()->where('user_id', $user->id)->count(),
            'daily_goals' => DailyGoal::withoutGlobalScopes()->where('user_id', $user->id)->count(),
            'countdowns' => Countdown::withoutGlobalScopes()->where('user_id', $user->id)->count(),
            'duration_seconds' => (int) TimeBlock::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->sum('duration_seconds'),
        ];

        return view('admin.users.show', compact(
            'user', 'timeBlocks', 'dailyGoals', 'countdowns', 'totals',
            'cells', 'monthStart', 'monthTotals'
        ));
    }

    /**
     * Read-only day report for any user, viewed by an admin. Mirrors the
     * end-user /history/day/{date} page but the date can belong to any user
     * the admin is inspecting. Logs an audit entry.
     */
    public function day(Request $request, $id, $date)
    {
        $user = User::withTrashed()->findOrFail($id);

        // Guard: regex on route checks shape; checkdate() catches invalid
        // calendar dates like 2026-13-40 or 2025-02-29.
        $parts = explode('-', $date);
        if (count($parts) !== 3
            || ! ctype_digit($parts[0]) || ! ctype_digit($parts[1]) || ! ctype_digit($parts[2])
            || ! checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0])) {
            return redirect()
                ->route('admin.users.show', $user->id)
                ->with('toast', 'That date doesn\'t look right.');
        }

        AdminAudit::log('viewed_user_day', $user->id, ['date' => $date]);

        $tz = $user->timezone ?: 'UTC';
        $target = CarbonImmutable::parse($date, $tz)->startOfDay();
        $today = CarbonImmutable::now($tz)->startOfDay();

        $blocks = TimeBlock::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('duration_seconds', '>', 0)
            ->whereBetween('start_time', [$target, $target->endOfDay()])
            ->orderBy('start_time')
            ->get();

        $productiveSec = (int) $blocks->whereNotIn('category', ['wasted', 'neutral'])->sum('duration_seconds');
        $wastedSec = (int) $blocks->where('category', 'wasted')->sum('duration_seconds');
        $neutralSec = (int) $blocks->where('category', 'neutral')->sum('duration_seconds');

        $isCurrentDay = $target->isSameDay($today);
        $isFuture = $target->gt($today);
        $schedule = $this->sleepSchedule->forUser($user);
        $fullSleepSec = $schedule['per_night_seconds'];
        $fullAwakeSec = max(0, (24 * 3600) - $fullSleepSec);
        $dayEnd = $target->addDay();
        $now = CarbonImmutable::now($tz);
        $effectiveEnd = $isFuture ? $target : ($now->lt($dayEnd) ? $now : $dayEnd);
        $elapsedSec = max(0, (int) $target->diffInSeconds($effectiveEnd, false));
        $elapsedSleepSec = $this->sleepSchedule->overlapSeconds($target, $effectiveEnd, $user);
        $elapsedAwakeSec = max(0, (int) ($elapsedSec - $elapsedSleepSec));
        $awakeForRatioSec = max(1, ($isCurrentDay || $isFuture) ? $elapsedAwakeSec : $fullAwakeSec);

        $loggedSec = $productiveSec + $wastedSec + $neutralSec;
        $unloggedSec = max(0, $awakeForRatioSec - $loggedSec);
        $efficiencyDenominatorSec = $productiveSec + $wastedSec + $unloggedSec;
        $efficiencyPct = $efficiencyDenominatorSec > 0
            ? (int) round(($productiveSec / $efficiencyDenominatorSec) * 100)
            : 0;
        $efficiencyPct = max(0, min(100, $efficiencyPct));
        $sleepSec = $isCurrentDay || $isFuture ? $elapsedSleepSec : $fullSleepSec;
        $awakeSec = $isCurrentDay || $isFuture ? $elapsedAwakeSec : $fullAwakeSec;
        $sleepMinsPerNight = (int) ($fullSleepSec / 60);
        $end = [$schedule['end_hour'], $schedule['end_minute']];
        $wake = [$schedule['wake_hour'], $schedule['wake_minute']];

        return view('admin.users.day', [
            'user' => $user,
            'date' => $target,
            'dateLabel' => $target->format('l, F j, Y'),
            'isCurrentDay' => $isCurrentDay,
            'isFuture' => $target->gt($today),
            'blocks' => $blocks,
            'productiveSec' => $productiveSec,
            'wastedSec' => $wastedSec,
            'neutralSec' => $neutralSec,
            'loggedSec' => $loggedSec,
            'unloggedSec' => $unloggedSec,
            'elapsedSec' => $isCurrentDay || $isFuture ? $elapsedSec : 24 * 3600,
            'sleepSec' => $sleepSec,
            'awakeSec' => $awakeSec,
            'sleepLabel' => $isCurrentDay ? 'scheduled sleep elapsed' : ($isFuture ? 'not started yet' : $schedule['end_label'].' to '.$schedule['wake_label']),
            'awakeLabel' => $isCurrentDay ? 'awake elapsed' : ($isFuture ? 'not started yet' : '24h - sleep'),
            'efficiencyPct' => $efficiencyPct,
            'sleepWindowLabel' => $this->display12($end[0], $end[1]).' → '.$this->display12($wake[0], $wake[1]),
            'sleepPerNightHours' => round($sleepMinsPerNight / 60, 2),
        ]);
    }

    private function parseHM(?string $raw, string $fallback): array
    {
        $raw = $raw ?: $fallback;
        $parts = explode(':', substr($raw, 0, 5));
        return [(int) ($parts[0] ?? 0), (int) ($parts[1] ?? 0)];
    }

    private function display12(int $h, int $m): string
    {
        $period = $h >= 12 ? 'PM' : 'AM';
        $h12 = $h % 12;
        if ($h12 === 0) $h12 = 12;
        return sprintf('%d:%02d %s', $h12, $m, $period);
    }

    public function edit($id)
    {
        $user = User::withTrashed()->findOrFail($id);
        return view('admin.users.edit', compact('user'));
    }

    public function update($id, Request $request)
    {
        $user = User::withTrashed()->findOrFail($id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'timezone' => ['required', 'string', Rule::in(DateTimeZone::listIdentifiers())],
            'end_of_day_time' => ['required', 'date_format:H:i', 'after_or_equal:18:00', 'before_or_equal:23:59'],
            'wake_up_time' => ['required', 'date_format:H:i', 'after_or_equal:04:00', 'before_or_equal:11:00'],
            'gap_threshold_minutes' => ['required', 'integer', 'min:15', 'max:240'],
            // Admin gate for the email-backup feature. Unchecked checkbox
            // doesn't appear in the request payload at all, so we normalise
            // to a boolean below.
            'backup_email_enabled' => ['nullable', 'boolean'],
        ]);

        // If email changed, force re-verification.
        if ($data['email'] !== $user->email) {
            $user->email_verified_at = null;
        }

        // Detect a flip on backup_email_enabled BEFORE saving so we can
        // (a) log it as a separate audit entry and (b) reset the user's
        // auto-daily toggle when the admin disables the feature — that
        // way an admin can fully revoke the feature in one click.
        $wasEnabled = (bool) $user->backup_email_enabled;
        $nowEnabled = (bool) ($data['backup_email_enabled'] ?? false);
        if ($wasEnabled !== $nowEnabled) {
            AdminAudit::log($nowEnabled ? 'enabled_email_backup' : 'disabled_email_backup', $user->id);
        }
        if (! $nowEnabled) {
            // Disabling the admin gate also turns off auto-daily so the
            // user can't keep firing scheduled mails through a stale
            // cookie or open tab.
            $data['backup_auto_daily'] = false;
        }
        $data['backup_email_enabled'] = $nowEnabled;

        $user->forceFill($data)->save();
        AdminAudit::log('updated_user', $user->id);

        return redirect()
            ->route('admin.users.show', $user->id)
            ->with('toast', "Updated {$user->email}.");
    }

    public function verifyEmail($id)
    {
        $user = User::query()->findOrFail($id);
        if ($user->email_verified_at) {
            return back()->with('toast', 'Email already verified.');
        }
        $user->forceFill(['email_verified_at' => now()])->save();
        AdminAudit::log('verified_user_email', $user->id);

        return redirect()
            ->route('admin.users.show', $user->id)
            ->with('toast', "Marked {$user->email} as verified.");
    }

    public function resendVerification($id)
    {
        $user = User::query()->findOrFail($id);
        if ($user->email_verified_at) {
            return back()->with('toast', 'Email is already verified — nothing to send.');
        }
        if (! method_exists($user, 'sendEmailVerificationNotification')) {
            return back()->with('toast', 'Email verification is not configured on the User model. Use Mark email verified instead.');
        }
        try {
            $user->sendEmailVerificationNotification();
            AdminAudit::log('resent_verification_email', $user->id);
            $msg = "Verification email queued for {$user->email}.";
        } catch (\Throwable $e) {
            $msg = 'Could not send verification email: '.$e->getMessage();
        }
        return redirect()->route('admin.users.show', $user->id)->with('toast', $msg);
    }

    public function sendPasswordReset($id)
    {
        $user = User::query()->findOrFail($id);
        $status = Password::broker('users')->sendResetLink(['email' => $user->email]);
        AdminAudit::log('sent_password_reset', $user->id);

        $msg = $status === Password::RESET_LINK_SENT
            ? "Password reset link sent to {$user->email}."
            : "Could not send reset link (status: {$status}).";

        return redirect()->route('admin.users.show', $user->id)->with('toast', $msg);
    }

    public function suspend($id)
    {
        $user = User::query()->findOrFail($id);
        $user->forceFill(['status' => 'suspended'])->save();
        AdminAudit::log('suspended_user', $user->id);

        return redirect()
            ->route('admin.users.show', $user->id)
            ->with('toast', "Suspended {$user->email}.");
    }

    public function unsuspend($id)
    {
        $user = User::query()->findOrFail($id);
        $user->forceFill(['status' => 'active'])->save();
        AdminAudit::log('unsuspended_user', $user->id);

        return redirect()
            ->route('admin.users.show', $user->id)
            ->with('toast', "Reactivated {$user->email}.");
    }

    public function destroy($id, Request $request)
    {
        $user = User::query()->findOrFail($id);
        $email = $user->email;
        $user->delete();
        AdminAudit::log('soft_deleted_user', $user->id);

        return redirect()
            ->route('admin.users.index')
            ->with('toast', "Soft-deleted {$email}. Restorable from the Deleted filter.");
    }

    public function restore($id)
    {
        $user = User::onlyTrashed()->findOrFail($id);
        $user->restore();
        AdminAudit::log('restored_user', $user->id);

        return redirect()
            ->route('admin.users.show', $user->id)
            ->with('toast', "Restored {$user->email}.");
    }

    public function bulk(Request $request)
    {
        $data = $request->validate([
            'action' => ['required', 'in:suspend,unsuspend,delete,enable_backup,disable_backup'],
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer'],
        ]);

        $action = $data['action'];
        $ids = $data['ids'];
        $count = 0;

        // Backup-toggle actions need the column. If migration hasn't run
        // yet, bail with a clear message instead of silently failing the
        // way a bare forceFill on a missing column would.
        if (in_array($action, ['enable_backup', 'disable_backup'], true)
            && ! Schema::hasColumn('users', 'backup_email_enabled')
        ) {
            return redirect()
                ->route('admin.users.index')
                ->with('toast', 'Migration required: run `php artisan migrate` to create the backup_email_enabled column before using this bulk action.');
        }

        DB::transaction(function () use ($action, $ids, &$count) {
            $users = User::query()->whereIn('id', $ids)->get();
            foreach ($users as $user) {
                switch ($action) {
                    case 'suspend':
                        $user->forceFill(['status' => 'suspended'])->save();
                        AdminAudit::log('bulk_suspended_user', $user->id);
                        break;
                    case 'unsuspend':
                        $user->forceFill(['status' => 'active'])->save();
                        AdminAudit::log('bulk_unsuspended_user', $user->id);
                        break;
                    case 'delete':
                        $user->delete();
                        AdminAudit::log('bulk_soft_deleted_user', $user->id);
                        break;
                    case 'enable_backup':
                        // Idempotent: skip the audit row if nothing actually
                        // changed. Same pattern as the per-user flip in
                        // update(), so the audit log doesn't get spammed
                        // when admin re-applies a bulk action.
                        if (! $user->backup_email_enabled) {
                            $user->forceFill(['backup_email_enabled' => true])->save();
                            AdminAudit::log('bulk_enabled_email_backup', $user->id);
                        }
                        break;
                    case 'disable_backup':
                        if ($user->backup_email_enabled || $user->backup_auto_daily) {
                            // Force off both the gate AND auto-daily so a
                            // disabled user can't keep auto-firing scheduled
                            // mails through a stale tab.
                            $user->forceFill([
                                'backup_email_enabled' => false,
                                'backup_auto_daily'    => false,
                            ])->save();
                            AdminAudit::log('bulk_disabled_email_backup', $user->id);
                        }
                        break;
                }
                $count++;
            }
        });

        $verb = match ($action) {
            'suspend'        => 'suspended',
            'unsuspend'      => 'reactivated',
            'delete'         => 'soft-deleted',
            'enable_backup'  => 'granted email-backup access',
            'disable_backup' => 'had email-backup access revoked',
        };

        return redirect()
            ->route('admin.users.index')
            ->with('toast', "Bulk action: {$count} user(s) {$verb}.");
    }

    public function forceDestroy($id, Request $request)
    {
        $user = User::withTrashed()->findOrFail($id);
        $email = $user->email;
        $userId = $user->id;

        DB::transaction(function () use ($user) {
            // Cascade-clean the user's owned rows. The global BelongsToUser
            // scope filters by the web auth()->id() — null in admin context —
            // so we explicitly bypass it on all three child tables.
            TimeBlock::withoutGlobalScopes()->where('user_id', $user->id)->delete();
            DailyGoal::withoutGlobalScopes()->where('user_id', $user->id)->delete();
            Countdown::withoutGlobalScopes()->where('user_id', $user->id)->delete();
            $user->forceDelete();
        });

        AdminAudit::log('force_deleted_user', $userId);

        return redirect()
            ->route('admin.users.index')
            ->with('toast', "Permanently deleted {$email}.");
    }
}
