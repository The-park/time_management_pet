<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('quotes')) {
            Schema::create('quotes', function (Blueprint $table) {
                $table->id();
                $table->string('text', 500);
                $table->string('author', 120)->nullable();
                $table->string('source', 120)->nullable();
                $table->string('category', 32);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('is_active');
            });
        }

        // Auto-seed when empty so a plain `php artisan migrate` (no
        // --seed) still populates the bubble with quotes. Wrapped in a
        // class_exists check because migrations may run before the
        // seeder class is autoloaded in some pipelines.
        if (DB::table('quotes')->count() === 0 && class_exists(\Database\Seeders\QuotesSeeder::class)) {
            (new \Database\Seeders\QuotesSeeder())->run();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
