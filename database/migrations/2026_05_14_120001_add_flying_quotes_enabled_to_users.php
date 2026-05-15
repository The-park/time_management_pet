<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'flying_quotes_enabled')) {
                $table->boolean('flying_quotes_enabled')->default(true)->after('gap_threshold_minutes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'flying_quotes_enabled')) {
                $table->dropColumn('flying_quotes_enabled');
            }
        });
    }
};
