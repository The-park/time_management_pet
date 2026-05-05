<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Countdown;
use App\Models\DailyGoal;
use App\Models\TimeBlock;
use App\Models\User;
use App\Services\AdminAudit;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
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

    public function show($id)
    {
        $user = User::withTrashed()->findOrFail($id);

        AdminAudit::log('viewed_user', $user->id);

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

        return view('admin.users.show', compact('user', 'timeBlocks', 'dailyGoals', 'countdowns', 'totals'));
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
        ]);

        // If email changed, force re-verification.
        if ($data['email'] !== $user->email) {
            $user->email_verified_at = null;
        }

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
            'action' => ['required', 'in:suspend,unsuspend,delete'],
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer'],
        ]);

        $action = $data['action'];
        $ids = $data['ids'];
        $count = 0;

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
                }
                $count++;
            }
        });

        $verb = match ($action) {
            'suspend' => 'suspended',
            'unsuspend' => 'reactivated',
            'delete' => 'soft-deleted',
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
