<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matter_id')->constrained('matters')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('stage');
            $table->date('deadline');
            $table->text('message');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // One reminder per matter, person, stage and deadline. A changed deadline starts fresh.
            $table->unique(['matter_id', 'user_id', 'stage', 'deadline']);
            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
