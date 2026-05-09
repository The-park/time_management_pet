<?php

namespace App\Http\Controllers;

use App\Models\TimeBlock;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Receives the dashboard's localStorage block snapshot and upserts it
 * into time_blocks so that server-side features (goal probability,
 * goal attribution) can see what the user has logged.
 *
 * The dashboard stores blocks under the key 'chrono.timeBlocks.v1' and
 * each block has shape:
 *   { id, date: 'YYYY-MM-DD', start: 'HH:MM', end: 'HH:MM' or null,
 *     durationMs: number, label: string, category: string|null,
 *     auto_filled?: boolean }
 *
 * Sync is full-replace per user: every block currently in DB for the
 * user is deleted and the snapshot is inserted. This is safe because
 * the dashboard's localStorage is the source of truth for the user.
 */
class TimeBlockSyncController extends Controller
{
    /**
     * Snapshot endpoint — returns every time block for the user in the
     * exact shape the dashboard's localStorage expects:
     *   { id, date: 'YYYY-MM-DD', start: 'HH:MM', end: 'HH:MM',
     *     durationMs: number, label: string, category: string|null,
     *     auto_filled: boolean, status: 'completed' }
     *
     * Called by the dashboard on every page load BEFORE any sync fires,
     * so localStorage gets re-hydrated from the server. Without this,
     * a fresh browser / cleared cache / different device would push an
     * empty snapshot and wipe the persisted history.
     */
    public function snapshot(Request $request): JsonResponse
    {
        $blocks = TimeBlock::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('start_time')
            ->get();

        $payload = $blocks->map(function (TimeBlock $b) {
            $start = $b->start_time;
            $end = $b->end_time;
            return [
                'id' => $b->external_id ?: ('srv_'.$b->id),
                'date' => $start->toDateString(),
                'start' => $start->format('H:i'),
                'end' => $end ? $end->format('H:i') : null,
                'durationMs' => (int) $b->duration_seconds * 1000,
                'label' => (string) ($b->reason ?? ''),
                'category' => $b->category,
                'categoryManual' => (bool) $b->category_manual,
                'auto_filled' => (bool) $b->auto_filled,
                'status' => 'completed',
            ];
        });

        return response()->json([
            'ok' => true,
            'count' => $payload->count(),
            'blocks' => $payload,
        ]);
    }

    public function sync(Request $request)
    {
        $data = $request->validate([
            'blocks' => ['present', 'array'],
            'blocks.*.id' => ['required', 'string', 'max:64'],
            'blocks.*.date' => ['required', 'string', 'date_format:Y-m-d'],
            'blocks.*.start' => ['required', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'blocks.*.end' => ['nullable', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'blocks.*.durationMs' => ['required', 'numeric'],
            'blocks.*.label' => ['nullable', 'string', 'max:500'],
            'blocks.*.category' => ['nullable', 'string', 'in:productive,wasted,neutral'],
            'blocks.*.auto_filled' => ['nullable', 'boolean'],
        ]);

        $userId = $request->user()->id;
        $records = [];

        foreach ($data['blocks'] as $b) {
            $start = Carbon::parse($b['date'].' '.$b['start']);
            $duration = (int) round($b['durationMs'] / 1000);

            if (! empty($b['end'])) {
                $end = Carbon::parse($b['date'].' '.$b['end']);
                if ($end->lte($start)) $end->addDay();   // wraps past midnight
            } else {
                $end = $start->copy()->addSeconds(max(0, $duration));
            }

            $records[] = [
                'user_id' => $userId,
                'external_id' => mb_substr($b['id'], 0, 64),
                'start_time' => $start,
                'end_time' => $end,
                'duration_seconds' => $duration,
                'reason' => (string) ($b['label'] ?? ''),
                'category' => $b['category'] ?? null,
                'category_manual' => ! empty($b['categoryManual']),
                'auto_filled' => ! empty($b['auto_filled']),
            ];
        }

        DB::transaction(function () use ($userId, $records) {
            // Full replace per user; the dashboard always sends the
            // complete snapshot of localStorage.
            TimeBlock::where('user_id', $userId)->delete();
            foreach ($records as $r) {
                // Use Eloquent so encrypted/datetime casts run.
                TimeBlock::create($r);
            }
        });

        return response()->json([
            'ok' => true,
            'count' => count($records),
            'synced_at' => now()->toIso8601String(),
        ]);
    }
}
