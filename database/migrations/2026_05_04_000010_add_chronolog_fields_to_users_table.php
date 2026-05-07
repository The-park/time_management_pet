<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Idempotent retrofit — columns already exist on fresh installs
        // because the create_users_table migration adds them. This file
        // exists to upgrade older deployments only.
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'timezone')) {
                $table->string('timezone')->default('UTC');
            }
            if (! Schema::hasColumn('users', 'end_of_day_time')) {
                $table->time('end_of_day_time')->default('22:00:00');
            }
            if (! Schema::hasColumn('users', 'wake_up_time')) {
                $table->time('wake_up_time')->default('07:00:00');
            }
            if (! Schema::hasColumn('users', 'gap_threshold_minutes')) {
                $table->integer('gap_threshold_minutes')->default(60);
            }
            if (! Schema::hasColumn('users', 'status')) {
                $table->enum('status', ['active', 'suspended'])->default('active');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $existing = array_filter(
                ['timezone', 'end_of_day_time', 'wake_up_time', 'gap_threshold_minutes', 'status'],
                fn ($c) => Schema::hasColumn('users', $c),
            );
            if (! empty($existing)) {
                $table->dropColumn($existing);
            }
        });
    }
};
