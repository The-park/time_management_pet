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
        Schema::table('users', function (Blueprint $table) {
            $table->string('timezone')->default('UTC');
            $table->time('end_of_day_time')->default('22:00:00');
            $table->time('wake_up_time')->default('07:00:00');
            $table->integer('gap_threshold_minutes')->default(60);
            $table->enum('status', ['active', 'suspended'])->default('active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'timezone',
                'end_of_day_time',
                'wake_up_time',
                'gap_threshold_minutes',
                'status',
            ]);
        });
    }
};
