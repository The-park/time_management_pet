<?php

namespace App\Console\Commands;

use App\Models\AdminAuditLog;
use Illuminate\Console\Command;

class PruneAdminAuditCommand extends Command
{
    protected $signature = 'admin:prune-audit
        {--days=90 : Delete entries older than this many days}
        {--dry-run : Show how many rows would be deleted without deleting}';

    protected $description = 'Delete admin_audit_log entries older than the retention window.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $base = AdminAuditLog::query()->where('viewed_at', '<', $cutoff);

        if ($this->option('dry-run')) {
            $count = (clone $base)->count();
            $this->info("Dry run: {$count} entries older than {$days} days would be pruned (cutoff {$cutoff->toDateTimeString()}).");
            return self::SUCCESS;
        }

        $deleted = $base->delete();

        $this->info("Pruned {$deleted} entries older than {$days} days (cutoff {$cutoff->toDateTimeString()}).");

        return self::SUCCESS;
    }
}
