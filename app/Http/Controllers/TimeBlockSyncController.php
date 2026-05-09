<?php

namespace App\Http\Controllers;

use App\Models\TimeBlock;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Receives the dashboard's localStorage block snapshot and upserts it
 * into time_blocks so that server-side features (goal probability,
 * goal attribution) can see what the user has logged.
 *
 * The dashboard stores blocks under the key 'chrono.timeBlocks.v1' and
 * each block has shape:
 *   { id, date: 'YYYY-MM-DD', start: 'HH:MM', end: 'HH:MM' or null,
 *     durationMs: number, label: string, category: string|null,
 *     categoryManual?: boolean, auto_filled?: boolean }
 *
 * Sync is full-replace per user: every block currently in DB for the
 * user is deleted and the snapshot is inserted. This is safe because
 * the dashboard's localStorage is the source of truth for the user.
 */
class TimeBlockSyncController extends Controller
{
    public function snapshot(Request $request): JsonResponse
    {
        $blocks = TimeBlock::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('start_time')
            ->get();

        $hasManual = $this->hasCategoryManualColumn();

        $payload = $blocks->map(function (TimeBlock $b) use ($hasManual) {
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
                // Defensive: only read the column when it exists. On a
                // freshly-deployed server before `php artisan migrate`
                // runs, the column would be missing and accessing it
                // throws. Default to false so the dashboard's auto-
                // classify migration is permitted to run on those rows.
                'categoryManual' => $hasManual ? (bool) $b->category_manual : false,
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
        $hasManual = $this->hasCategoryManualColumn();
        $records = [];

        foreach ($data['blocks'] as $b) {
            try {
                $start = Carbon::parse($b['date'].' '.$b['start']);
            } catch (Throwable $e) {
                Log::warning('time-blocks/sync: skipped block with bad start', [
                    'user_id' => $userId,
                    'block_id' => $b['id'] ?? null,
                    'reason' => $e->getMessage(),
                ]);
                continue;
            }

            $duration = (int) round(($b['durationMs'] ?? 0) / 1000);

            if (! empty($b['end'])) {
                try {
                    $end = Carbon::parse($b['date'].' '.$b['end']);
                } catch (Throwable $e) {
                    Log::warning('time-blocks/sync: bad end, deriving from duration', [
                        'user_id' => $userId,
                        'block_id' => $b['id'] ?? null,
                    ]);
                    $end = $start->copy()->addSeconds(max(0, $duration));
                }
                if ($end->lte($start)) $end->addDay();   // wraps past midnight
            } else {
                $end = $start->copy()->addSeconds(max(0, $duration));
            }

            $row = [
                'user_id' => $userId,
                'external_id' => mb_substr($b['id'], 0, 64),
                'start_time' => $start,
                'end_time' => $end,
                'duration_seconds' => $duration,
                'reason' => (string) ($b['label'] ?? ''),
                'category' => $b['category'] ?? null,
                'auto_filled' => ! empty($b['auto_filled']),
            ];
            if ($hasManual) {
                $row['category_manual'] = ! empty($b['categoryManual']);
            }
            $records[] = $row;
        }

        try {
            DB::transaction(function () use ($userId, $records) {
                // Full replace per user; the dashboard always sends the
                // complete snapshot of localStorage.
                TimeBlock::where('user_id', $userId)->delete();
                foreach ($records as $r) {
                    // Use Eloquent so encrypted/datetime casts run.
                    TimeBlock::create($r);
                }
            });
        } catch (Throwable $e) {
            // Surface the cause in the server log without leaking the
            // exception message into the response body. The most common
            // root causes here are (a) a pending migration on prod, (b) a
            // mass-assignment guard tripping, or (c) a column type
            // mismatch — all of which point at deploy steps the operator
            // needs to take.
            Log::error('time-blocks/sync failed', [
                'user_id' => $userId,
                'count' => count($records),
                'has_manual_column' => $hasManual,
                'class' => get_class($e),
                'message' => $e->getMessage(),
                'trace_head' => collect(explode("\n", $e->getTraceAsString()))->take(3)->implode(' | '),
            ]);
            return response()->json([
                'ok' => false,
                'error' => 'sync_failed',
                'message' => 'Server could not save the snapshot. The team has been notified.',
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'count' => count($records),
            'synced_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Cached column-existence check so we don't hit information_schema
     * on every request. Driver-agnostic via Schema facade.
     */
    private function hasCategoryManualColumn(): bool
    {
        static $cached = null;
        if ($cached !== null) return $cached;
        try {
            return $cached = Schema::hasColumn('time_blocks', 'category_manual');
        } catch (Throwable $e) {
            Log::warning('time-blocks: Schema::hasColumn check failed', ['error' => $e->getMessage()]);
            return $cached = false;
        }
    }
}
