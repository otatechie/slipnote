<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When the owner was emailed that their empty, unopened board is about
     * to be removed (see Workspace::pruneAbandoned). Cleared when someone
     * opens the board, which also resets the clock.
     */
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->timestamp('abandon_warned_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn('abandon_warned_at');
        });
    }
};
