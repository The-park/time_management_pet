<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'quote_source')) {
                // Stored as a plain string (not enum) to keep migrations
                // portable across MySQL/SQLite. Allowed values are
                // enforced at the application layer via validation +
                // the User accessor's fallback to 'mixed'.
                $table->string('quote_source', 16)
                    ->default('mixed')
                    ->after('flying_quotes_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'quote_source')) {
                $table->dropColumn('quote_source');
            }
        });
    }
};
