<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SQLite doesn't index a foreign key by itself, so every board total and
     * file count walked the whole table. Section second serves the per-section
     * counts from the same index.
     */
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->index(['course_id', 'section']);
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropIndex(['course_id', 'section']);
        });
    }
};
