<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminAuditLog;
use Illuminate\Http\Request;

class AdminAuditController extends Controller
{
    public function prune(Request $request)
    {
        $data = $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:3650'],
        ]);

        $days = (int) $data['days'];
        $cutoff = now()->subDays($days);
        $deleted = AdminAuditLog::query()->where('viewed_at', '<', $cutoff)->delete();

        // Don't audit-log the prune action itself — it would just be deleted on the
        // next prune. The toast confirms the action.

        return redirect()
            ->route('admin.audit.index')
            ->with('toast', "Pruned {$deleted} audit entries older than {$days} days.");
    }

    public function index(Request $request)
    {
        $action = $request->query('action');
        $adminId = $request->query('admin_id');

        $query = AdminAuditLog::query()
            ->with([
                'admin:id,name,email',
                'viewedUser' => fn ($q) => $q->withTrashed(),
            ]);

        if ($action) {
            $query->where('action', $action);
        }
        if ($adminId) {
            $query->where('admin_id', $adminId);
        }

        $entries = $query
            ->orderByDesc('viewed_at')
            ->paginate(50)
            ->withQueryString();

        $actions = AdminAuditLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        $admins = Admin::query()->orderBy('name')->get(['id', 'name']);

        return view('admin.audit.index', [
            'entries' => $entries,
            'actions' => $actions,
            'admins' => $admins,
            'filterAction' => $action,
            'filterAdminId' => $adminId,
        ]);
    }
}
