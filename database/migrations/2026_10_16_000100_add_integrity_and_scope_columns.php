<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // SHA-256 of the uploaded file. Unique, so the same file cannot be filed twice.
            $table->string('file_hash', 64)->nullable()->unique();
            $table->unsignedBigInteger('uploaded_by')->nullable()->index();
        });

        Schema::table('users', function (Blueprint $table) {
            // null or empty means every section. Otherwise the account sees only these sections.
            $table->json('sections')->nullable();
        });

        // Stronger rules on PostgreSQL (production). SQLite, used by the tests, skips them.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('UPDATE documents SET matter_id = NULL WHERE matter_id IS NOT NULL AND matter_id NOT IN (SELECT id FROM matters)');
            DB::statement('UPDATE documents SET suggested_matter_id = NULL WHERE suggested_matter_id IS NOT NULL AND suggested_matter_id NOT IN (SELECT id FROM matters)');

            Schema::table('documents', function (Blueprint $table) {
                $table->foreign('matter_id')->references('id')->on('matters')->nullOnDelete();
                $table->foreign('suggested_matter_id')->references('id')->on('matters')->nullOnDelete();
            });

            DB::statement("ALTER TABLE matters ADD CONSTRAINT matters_status_check CHECK (status IN ('Open', 'Pending', 'Closed'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE matters DROP CONSTRAINT IF EXISTS matters_status_check');

            Schema::table('documents', function (Blueprint $table) {
                $table->dropForeign(['matter_id']);
                $table->dropForeign(['suggested_matter_id']);
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('sections');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['file_hash', 'uploaded_by']);
        });
    }
};
