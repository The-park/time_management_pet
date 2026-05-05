<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The admin 2FA flow was removed; the columns added by Fortify's
     * TwoFactorAuthenticatable are no longer used anywhere in the codebase.
     * Drop them so the schema reflects reality.
     */
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('admins', 'two_factor_secret')) {
                $columns[] = 'two_factor_secret';
            }
            if (Schema::hasColumn('admins', 'two_factor_recovery_codes')) {
                $columns[] = 'two_factor_recovery_codes';
            }
            if (Schema::hasColumn('admins', 'two_factor_confirmed_at')) {
                $columns[] = 'two_factor_confirmed_at';
            }
            if (! empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }

    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
        });
    }
};
