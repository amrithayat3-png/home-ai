<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('original_name');
            $table->string('stored_path');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size');
            $table->string('category');
            $table->decimal('confidence', 4, 3)->default(0);
            $table->string('classification_reason', 500)->nullable();
            $table->text('extracted_text')->nullable();
            $table->string('review_status')->default('Needs review');
            $table->timestamp('classified_at')->nullable();
            $table->timestamps();

            $table->index('category');
            $table->index('review_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
