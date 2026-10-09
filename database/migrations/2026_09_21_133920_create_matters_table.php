<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
       Schema::create('matters', function (Blueprint $table) {
    $table->id();

    $table->string('matter_reference')->unique();
    $table->string('title');
    $table->string('section');
    $table->string('category');
    $table->string('priority')->default('Normal');
    $table->string('district')->nullable();
    $table->string('status')->default('Open');

    $table->string('assigned_officer')->nullable();
    $table->string('related_department')->nullable();

    $table->date('received_date')->nullable();
    $table->date('deadline')->nullable();

    $table->text('summary')->nullable();
    $table->text('last_action')->nullable();
    $table->text('next_action')->nullable();

    $table->timestamps();

    $table->index('section');
    $table->index('priority');
    $table->index('status');
    $table->index('deadline');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matters');
    }
};
