<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two related fixes to the admin_audit_log table:
 *
 * 1. viewed_user_id was created NOT NULL by foreignId()->constrained(), but
 *    several admin actions don't target a user (create/update/delete admin,
 *    add/remove/refresh disposable email domains). All six of those endpoints
 *    were 500-erroring on a 23000 integrity constraint violation. Making the
 *    column nullable lets the audit row survive those non-user actions.
 *
 * 2. Some actions do carry context that doesn't fit in the action string —
 *    e.g. "viewed user day" wants to record which date was viewed. Adding a
 *    metadata JSON column gives the service a typed home for that data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_audit_log', function (Blueprint $table) {
            // SQL servers reject ALTER on a column that's part of a foreign
            // key, so drop the FK first, change the column, then re-add it.
            $table->dropForeign(['viewed_user_id']);
        });

        Schema::table('admin_audit_log', function (Blueprint $table) {
            $table->foreignId('viewed_user_id')->nullable()->change();
        });

        Schema::table('admin_audit_log', function (Blueprint $table) {
            $table->foreign('viewed_user_id')
                ->references('id')->on('users')
                ->cascadeOnDelete();

            if (! Schema::hasColumn('admin_audit_log', 'metadata')) {
                $table->json('metadata')->nullable()->after('user_agent');
            }
        });
    }

    public function down(): void
    {
        Schema::table('admin_audit_log', function (Blueprint $table) {
            if (Schema::hasColumn('admin_audit_log', 'metadata')) {
                $table->dropColumn('metadata');
            }
            $table->dropForeign(['viewed_user_id']);
        });

        // Note: rolling back to NOT NULL would fail if any row has a NULL
        // viewed_user_id. We leave the column nullable on rollback to keep
        // existing data intact; the FK is restored.
        Schema::table('admin_audit_log', function (Blueprint $table) {
            $table->foreign('viewed_user_id')
                ->references('id')->on('users')
                ->cascadeOnDelete();
        });
    }
};
