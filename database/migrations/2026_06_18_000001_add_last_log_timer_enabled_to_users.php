<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'last_log_timer_enabled')) {
                $table->boolean('last_log_timer_enabled')->default(true)->after('flying_quotes_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'last_log_timer_enabled')) {
                $table->dropColumn('last_log_timer_enabled');
            }
        });
    }
};
