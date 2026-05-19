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
        Schema::table('materials', function (Blueprint $table) {
            // Secret per-upload token: whoever holds it can delete the file.
            // Nullable so pre-existing / seeded rows simply aren't deletable.
            $table->string('manage_token', 64)->nullable()->unique()->after('uploader_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn('manage_token');
        });
    }
};
