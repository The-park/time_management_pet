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
     */
    public static function log(string $action, ?int $viewedUserId = null): void
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
            'viewed_at' => now(),
        ]);
    }
}
