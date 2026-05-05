<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Countdown;
use App\Models\DailyGoal;
use App\Models\DisposableEmailDomain;
use App\Models\TimeBlock;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $now = now();
        $sevenDaysAgo = $now->copy()->subDays(7);
        $thirtyDaysAgo = $now->copy()->subDays(30);

        $userQ = User::withTrashed();

        return view('admin.dashboard', [
            'metrics' => [
                'users_total' => (clone $userQ)->count(),
                'users_active' => User::query()->where('status', 'active')->count(),
                'users_suspended' => User::query()->where('status', 'suspended')->count(),
                'users_deleted' => User::onlyTrashed()->count(),
                'users_signups_7d' => User::query()->where('created_at', '>=', $sevenDaysAgo)->count(),
                'users_signups_30d' => User::query()->where('created_at', '>=', $thirtyDaysAgo)->count(),
                'users_unverified' => User::query()->whereNull('email_verified_at')->count(),
                'time_blocks' => TimeBlock::withoutGlobalScopes()->count(),
                'daily_goals' => DailyGoal::withoutGlobalScopes()->count(),
                'countdowns' => Countdown::withoutGlobalScopes()->count(),
                'disposable_domains' => DisposableEmailDomain::query()->count(),
                'audit_entries' => AdminAuditLog::query()->count(),
            ],
            'series' => [
                'signups' => $this->seriesByDay(User::query(), 'created_at', 30),
                'audits' => $this->seriesByDay(AdminAuditLog::query(), 'viewed_at', 30),
                'time_blocks' => $this->seriesByDay(TimeBlock::withoutGlobalScopes(), 'start_time', 30),
            ],
            'recentSignups' => User::query()
                ->latest('created_at')
                ->limit(5)
                ->get(),
            'recentAudits' => AdminAuditLog::query()
                ->with(['admin:id,name', 'viewedUser' => fn ($q) => $q->withTrashed()])
                ->latest('viewed_at')
                ->limit(8)
                ->get(),
        ]);
    }

    /**
     * Return one row per day for the last $days days (oldest first), using PHP-side
     * grouping so the implementation stays portable across SQLite / MySQL / Postgres
     * (no DATE() / date_trunc() differences).
     *
     * @return array<int, array{date: string, label: string, count: int}>
     */
    private function seriesByDay(Builder $query, string $column, int $days): array
    {
        $start = CarbonImmutable::now()->startOfDay()->subDays($days - 1);

        $rows = (clone $query)
            ->where($column, '>=', $start)
            ->get([$column])
            ->groupBy(fn ($row) => optional($row->{$column})->format('Y-m-d'))
            ->map->count()
            ->all();

        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $start->addDays($i);
            $key = $date->format('Y-m-d');
            $out[] = [
                'date' => $key,
                'label' => $date->format('M j'),
                'count' => (int) ($rows[$key] ?? 0),
            ];
        }
        return $out;
    }
}
