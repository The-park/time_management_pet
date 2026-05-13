<?php

namespace App\Services;

use App\Models\Goal;
use App\Models\TimeBlock;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Builds a JSON-serialisable snapshot of a user's data for the
 * email-backup feature. Bypasses the BelongsToUser global scope
 * deliberately — admin/system callers need access regardless of
 * the currently-authenticated user.
 *
 * Output schema is stable: callers (re-import scripts, the user's
 * own analytics) can rely on the key names. If we ever need to
 * change a field, bump `meta.schema_version`.
 */
class DataExportService
{
    public const SCHEMA_VERSION = 1;

    /**
     * Build the export payload as a PHP array. JSON-encoding is left
     * to the caller (the Mailable attaches it via Storage::put or
     * builds a string at send time).
     *
     * @param  CarbonImmutable|null  $rangeStart  inclusive; null = signup date
     * @param  CarbonImmutable|null  $rangeEnd    inclusive; null = today end-of-day
     * @return array{meta:array, user:array, time_blocks:array, goals:array}
     */
    public function build(User $user, ?CarbonImmutable $rangeStart = null, ?CarbonImmutable $rangeEnd = null): array
    {
        $signup = CarbonImmutable::parse($user->created_at ?? now());
        $rangeStart = $rangeStart ?? $signup;
        $rangeEnd   = $rangeEnd   ?? CarbonImmutable::now();

        // Normalise to whole-day windows so 'from May 1 to May 3' captures
        // every block logged on May 3 regardless of the time component.
        $startDt = $rangeStart->startOfDay();
        $endDt   = $rangeEnd->endOfDay();

        $blocks = TimeBlock::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->whereBetween('start_time', [$startDt, $endDt])
            ->orderBy('start_time')
            ->get();

        $goals = Goal::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->orderBy('start_date')
            ->get();

        $blocksOut = $blocks->map(function (TimeBlock $b) {
            return [
                'id'              => $b->id,
                'external_id'     => $b->external_id,
                // Encrypted in storage; auto-decrypted by the cast on read.
                'reason'          => (string) ($b->reason ?? ''),
                'start_time'      => optional($b->start_time)->toIso8601String(),
                'end_time'        => optional($b->end_time)->toIso8601String(),
                'duration_seconds'=> (int) $b->duration_seconds,
                'category'        => $b->category,
                'category_manual' => (bool) $b->category_manual,
                'auto_filled'     => (bool) $b->auto_filled,
                'created_at'      => optional($b->created_at)->toIso8601String(),
                'updated_at'      => optional($b->updated_at)->toIso8601String(),
            ];
        })->all();

        $goalsOut = $goals->map(function (Goal $g) {
            return [
                'id'              => $g->id,
                'title'           => $g->title,
                // Encrypted in storage; auto-decrypted by the cast on read.
                'description'     => (string) ($g->description ?? ''),
                'category'        => $g->category,
                'start_date'      => optional($g->start_date)->toDateString(),
                'target_date'     => optional($g->target_date)->toDateString(),
                'original_target_date' => optional($g->original_target_date)->toDateString(),
                'keywords'        => is_array($g->keywords) ? $g->keywords : [],
                'status'          => $g->status,
                'completed_at'    => optional($g->completed_at)->toIso8601String(),
                'extension_count' => (int) $g->extension_count,
                'change_count'    => (int) $g->change_count,
                'created_at'      => optional($g->created_at)->toIso8601String(),
            ];
        })->all();

        return [
            'meta' => [
                'schema_version' => self::SCHEMA_VERSION,
                'app'            => config('app.name'),
                'generated_at'   => CarbonImmutable::now()->toIso8601String(),
                'range_start'    => $startDt->toDateString(),
                'range_end'      => $endDt->toDateString(),
                'blocks_count'   => count($blocksOut),
                'goals_count'    => count($goalsOut),
                'is_complete'    => $rangeStart->lessThanOrEqualTo($signup),
            ],
            'user' => [
                'id'                    => $user->id,
                'name'                  => $user->name,
                'email'                 => $user->email,
                'signup_date'           => $signup->toDateString(),
                'timezone'              => $user->timezone,
                'wake_up_time'          => $user->wake_up_time,
                'end_of_day_time'       => $user->end_of_day_time,
                'gap_threshold_minutes' => $user->gap_threshold_minutes,
            ],
            'time_blocks' => $blocksOut,
            'goals'       => $goalsOut,
        ];
    }

    /**
     * Serialise the payload to a JSON string ready for attachment.
     * Pretty-printed so the user can open it in any text editor and
     * read it; minor size cost is irrelevant for typical exports
     * (low MB even for power users).
     */
    public function toJson(array $payload): string
    {
        return json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * Suggested filename. Includes the range so the user can stack
     * multiple exports in a folder without overwriting each other.
     */
    public function filename(array $payload): string
    {
        $userId = $payload['user']['id'] ?? 'user';
        $start  = $payload['meta']['range_start']  ?? 'unknown';
        $end    = $payload['meta']['range_end']    ?? 'unknown';
        $stamp  = CarbonImmutable::now()->format('Ymd-His');
        return "chrono-backup-user{$userId}-{$start}-to-{$end}-{$stamp}.json";
    }
}
