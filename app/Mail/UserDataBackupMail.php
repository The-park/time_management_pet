<?php

namespace App\Mail;

use App\Models\DataExportLog;
use App\Models\User;
use App\Services\DataExportService;
use Carbon\CarbonImmutable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Synchronous mailable that builds the JSON export and ships it to
 * the user's nominated address. Mirrors how Fortify's password-reset
 * + verify-email notifications send (also synchronous) so deployments
 * without a queue worker (`php artisan queue:work`) still get the
 * mail out.
 *
 * Previously implemented `ShouldQueue` which put the job in the
 * database `jobs` table where it sat indefinitely on servers without
 * a running queue worker — the visible symptom was "POST /backup/send
 * returns 302 but the mail never arrives". Synchronous send keeps the
 * implementation simple and matches the working password-reset flow.
 *
 * The controller is responsible for creating the DataExportLog row +
 * the eager `backup_last_sent_at` / `backup_count` updates. If the
 * SMTP send throws, the controller catches it, flips the log to
 * 'failed', and shows a toast to the user.
 */
class UserDataBackupMail extends Mailable
{
    use SerializesModels;

    public User $user;
    public int $logId;
    public ?string $rangeStartIso;
    public ?string $rangeEndIso;
    public string $exportType;

    /** Counts computed in attachments() and reused by content(). */
    private int $cachedBlocksCount = 0;
    private int $cachedGoalsCount = 0;
    private string $cachedRangeStart = '';
    private string $cachedRangeEnd = '';

    public function __construct(
        User $user,
        int $logId,
        ?CarbonImmutable $rangeStart,
        ?CarbonImmutable $rangeEnd,
        string $exportType,
    ) {
        $this->user          = $user;
        $this->logId         = $logId;
        $this->rangeStartIso = $rangeStart?->toIso8601String();
        $this->rangeEndIso   = $rangeEnd?->toIso8601String();
        $this->exportType    = $exportType;
    }

    public function envelope(): Envelope
    {
        $appName = config('app.name', 'Time Management Pet');
        return new Envelope(
            subject: "Your {$appName} data backup",
            tags: ['user-data-backup'],
            metadata: [
                'user_id'    => (string) $this->user->id,
                'export_log' => (string) $this->logId,
                'type'       => $this->exportType,
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.user-data-backup',
            with: [
                'user'        => $this->user,
                'exportType'  => $this->exportType,
                'rangeStart'  => $this->cachedRangeStart,
                'rangeEnd'    => $this->cachedRangeEnd,
                'blocksCount' => $this->cachedBlocksCount,
                'goalsCount'  => $this->cachedGoalsCount,
            ],
        );
    }

    /**
     * Build the export at send-time (inside the queue worker). Laravel
     * calls attachments() BEFORE content(), so the counts are populated
     * by the time the view renders. If this throws, the queue retries +
     * eventually invokes failed() below.
     */
    public function attachments(): array
    {
        $service = app(DataExportService::class);
        $start = $this->rangeStartIso ? CarbonImmutable::parse($this->rangeStartIso) : null;
        $end   = $this->rangeEndIso   ? CarbonImmutable::parse($this->rangeEndIso)   : null;

        $payload  = $service->build($this->user, $start, $end);
        $json     = $service->toJson($payload);
        $filename = $service->filename($payload);

        // Cache for content() — these decide what counts/dates the
        // email body displays.
        $this->cachedBlocksCount = (int) ($payload['meta']['blocks_count'] ?? 0);
        $this->cachedGoalsCount  = (int) ($payload['meta']['goals_count']  ?? 0);
        $this->cachedRangeStart  = (string) ($payload['meta']['range_start'] ?? '');
        $this->cachedRangeEnd    = (string) ($payload['meta']['range_end']   ?? '');

        // Persist counts onto the audit log so the user-facing summary
        // and admin tile show the right numbers without recomputing.
        DataExportLog::where('id', $this->logId)->update([
            'blocks_count' => $this->cachedBlocksCount,
            'goals_count'  => $this->cachedGoalsCount,
            'status'       => DataExportLog::STATUS_SENT,
        ]);

        return [
            Attachment::fromData(fn () => $json, $filename)
                ->as($filename)
                ->withMime('application/json'),
        ];
    }

    /**
     * Final-failure hook. Runs after the queue exhausts retries.
     */
    public function failed(\Throwable $exception): void
    {
        DataExportLog::where('id', $this->logId)->update([
            'status'         => DataExportLog::STATUS_FAILED,
            'failure_reason' => substr($exception->getMessage(), 0, 1000),
        ]);
        Log::error('UserDataBackupMail failed', [
            'user_id'   => $this->user->id,
            'log_id'    => $this->logId,
            'message'   => $exception->getMessage(),
        ]);
    }
}
