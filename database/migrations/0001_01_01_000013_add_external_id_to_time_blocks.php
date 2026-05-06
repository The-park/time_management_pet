<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_blocks', function (Blueprint $table) {
            // The dashboard generates client-side IDs (e.g. "1714987200000_a3f4")
            // and stores blocks in localStorage. We sync those to the server
            // keyed by (user_id, external_id) so re-syncs upsert in place.
            $table->string('external_id', 64)->nullable()->after('id');
            $table->string('category', 32)->nullable()->after('reason');

            $table->unique(['user_id', 'external_id'], 'time_blocks_user_external_unique');
        });
    }

    public function down(): void
    {
        Schema::table('time_blocks', function (Blueprint $table) {
            $table->dropUnique('time_blocks_user_external_unique');
            $table->dropColumn(['external_id', 'category']);
        });
    }
};
