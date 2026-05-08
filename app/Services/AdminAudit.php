<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use Illuminate\Support\Facades\Auth;

class AdminAudit
{
    /**
     * Record an admin action against the audit log. Silently no-ops if there
     * is no admin in the session — calls outside an admin request shouldn't
     * crash the response, and the audit table is for the admin trail only.
     *
     * $viewedUserId is nullable: actions like "created admin" or "added
     * disposable domain" don't target a user. The schema permits NULL there.
     *
     * $metadata is for typed context that doesn't fit in the action string —
     * e.g. ['date' => '2026-05-08'] for the day-view action — and is stored
     * as JSON.
     */
    public static function log(string $action, ?int $viewedUserId = null, ?array $metadata = null): void
    {
        $admin = Auth::guard('admin')->user();
        if (! $admin) {
            return;
        }

        AdminAuditLog::create([
            'admin_id' => $admin->id,
            'viewed_user_id' => $viewedUserId,
            'action' => $action,
            'ip_address' => request()->ip(),
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 255),
            'metadata' => $metadata ?: null,
            'viewed_at' => now(),
        ]);
    }
}
