<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Email-backup feature columns + audit log table.
     *
     * `users.backup_email_enabled` is the ADMIN gate — until it's true,
     * the user doesn't see the feature at all.
     * `backup_email_address` is the destination the user nominated;
     * `backup_auto_daily` controls whether we mail on first dashboard
     * visit of each day; `backup_last_sent_at` is the idempotency
     * marker that prevents duplicate auto-sends; `backup_count` is
     * surfaced to admins so they can see usage.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'backup_email_enabled')) {
                $table->boolean('backup_email_enabled')->default(false)->after('status');
            }
            if (! Schema::hasColumn('users', 'backup_email_address')) {
                $table->string('backup_email_address', 254)->nullable()->after('backup_email_enabled');
            }
            if (! Schema::hasColumn('users', 'backup_auto_daily')) {
                $table->boolean('backup_auto_daily')->default(false)->after('backup_email_address');
            }
            if (! Schema::hasColumn('users', 'backup_last_sent_at')) {
                $table->timestamp('backup_last_sent_at')->nullable()->after('backup_auto_daily');
            }
            if (! Schema::hasColumn('users', 'backup_count')) {
                $table->unsignedInteger('backup_count')->default(0)->after('backup_last_sent_at');
            }
        });

        // Audit log for every export. Admin reads this; users see a
        // small "Last sent / Total sent" summary derived from it.
        if (! Schema::hasTable('data_export_logs')) {
            Schema::create('data_export_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('email', 254);
                // 'manual_complete' | 'manual_range' | 'auto_daily'
                $table->string('export_type', 32);
                $table->date('range_start')->nullable();
                $table->date('range_end')->nullable();
                $table->unsignedInteger('blocks_count')->default(0);
                $table->unsignedInteger('goals_count')->default(0);
                $table->string('status', 16)->default('queued'); // queued|sent|failed
                $table->text('failure_reason')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('data_export_logs');
        Schema::table('users', function (Blueprint $table) {
            foreach ([
                'backup_email_enabled',
                'backup_email_address',
                'backup_auto_daily',
                'backup_last_sent_at',
                'backup_count',
            ] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
