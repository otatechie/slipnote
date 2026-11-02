<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When the operator archived the board: closed to everyone, nothing
     * deleted, and back in one click.
     */
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn('archived_at');
        });
    }
};
