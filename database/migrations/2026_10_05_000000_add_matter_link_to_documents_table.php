<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->unsignedBigInteger('matter_id')->nullable();
            $table->unsignedBigInteger('suggested_matter_id')->nullable();
            $table->string('matter_link_source')->nullable();

            $table->index('matter_id');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['matter_id']);
            $table->dropColumn(['matter_id', 'suggested_matter_id', 'matter_link_source']);
        });
    }
};
