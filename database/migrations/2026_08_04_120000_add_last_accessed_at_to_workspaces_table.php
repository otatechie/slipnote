<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Last time anyone viewed the board or downloaded from it. `updated_at`
     * can't answer this — it only moves when the row itself changes, so a
     * board serving past papers to 40 students a week looks identical to one
     * nobody has opened since March.
     *
     * Distinguishes a dormant-but-used board from an abandoned one before any
     * retention policy gets written. Nullable: existing rows have no history,
     * and null reads as "not seen since this shipped".
     */
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->timestamp('last_accessed_at')->nullable()->after('recovery_email');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn('last_accessed_at');
        });
    }
};
