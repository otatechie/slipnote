<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * by_owner is set when a file is uploaded in owner mode (used by the
     * Telegram ping, not shown on the board), and pinned_at puts a file at
     * the top of its section.
     */
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->boolean('by_owner')->default(false);
            $table->timestamp('pinned_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn(['by_owner', 'pinned_at']);
        });
    }
};
