<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category', 32)->default('custom');
            $table->date('start_date');
            $table->date('target_date');
            $table->date('original_target_date');
            $table->json('keywords')->nullable();
            $table->enum('status', ['active', 'completed', 'abandoned', 'missed'])->default('active');
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('extension_count')->default(0);
            $table->unsignedInteger('change_count')->default(0);
            $table->decimal('last_probability', 5, 2)->nullable();
            $table->timestamp('last_probability_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'target_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goals');
    }
};
