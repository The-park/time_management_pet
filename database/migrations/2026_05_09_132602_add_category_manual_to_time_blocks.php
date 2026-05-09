<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persist the user's manual category choice. When a user clicks the chip
 * on a time block to flip it from productive → wasted (or any other
 * combination), we set this flag so the dashboard's auto-classify
 * migration loop respects the choice instead of re-running the keyword
 * scorer on every page load and overwriting it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_blocks', function (Blueprint $table) {
            $table->boolean('category_manual')->default(false)->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('time_blocks', function (Blueprint $table) {
            $table->dropColumn('category_manual');
        });
    }
};
