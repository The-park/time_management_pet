<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            if (! Schema::hasColumn('quotes', 'user_id')) {
                // Nullable because admin-created quotes have no owner
                // (user_id = NULL is the global pool). User-created quotes
                // get the foreign key. Cascade on delete so removing the
                // user also removes their personal quotes.
                $table->foreignId('user_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('users')
                    ->cascadeOnDelete();
                $table->index('user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            if (Schema::hasColumn('quotes', 'user_id')) {
                // Drop FK first (Laravel's convention: <table>_<col>_foreign).
                try {
                    $table->dropForeign(['user_id']);
                } catch (\Throwable $e) {
                    // already gone — ignore
                }
                try {
                    $table->dropIndex(['user_id']);
                } catch (\Throwable $e) {
                    // already gone — ignore
                }
                $table->dropColumn('user_id');
            }
        });
    }
};
