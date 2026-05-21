<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->unsignedInteger('position')->default(0)->after('slug');
        });

        // Seed existing rows with their current id order per workspace
        DB::statement('
            UPDATE courses
            SET position = (
                SELECT COUNT(*)
                FROM courses c2
                WHERE c2.workspace_id = courses.workspace_id
                AND c2.id <= courses.id
            ) - 1
        ');
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};
