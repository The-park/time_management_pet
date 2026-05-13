<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per user-data-backup that left the system. Powers:
 *   - the user-facing "Last sent / total sent" summary in /settings,
 *   - the admin-facing "Backups taken" tile in /admin/users/{id},
 *   - the idempotency check that prevents double-firing the auto-daily
 *     job (we read `users.backup_last_sent_at` for that, but the log is
 *     also queryable as a cross-check).
 *
 * `status` is 'queued' the moment the controller enqueues the job, then
 * flips to 'sent' or 'failed' inside the Mailable. The Mailable runs on
 * the queue, so the user's HTTP request returns immediately.
 */
class DataExportLog extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENT   = 'sent';
    public const STATUS_FAILED = 'failed';

    public const TYPE_MANUAL_COMPLETE = 'manual_complete';
    public const TYPE_MANUAL_RANGE    = 'manual_range';
    public const TYPE_AUTO_DAILY      = 'auto_daily';

    protected $fillable = [
        'user_id',
        'email',
        'export_type',
        'range_start',
        'range_end',
        'blocks_count',
        'goals_count',
        'status',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'user_id'      => 'integer',
            'range_start'  => 'date',
            'range_end'    => 'date',
            'blocks_count' => 'integer',
            'goals_count'  => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
