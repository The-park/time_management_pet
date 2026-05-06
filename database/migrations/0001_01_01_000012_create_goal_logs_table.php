<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goal_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('action', 32);
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['goal_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goal_logs');
    }
};
