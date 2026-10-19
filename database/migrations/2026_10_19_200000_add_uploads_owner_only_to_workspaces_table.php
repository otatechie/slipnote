<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who can add files to a board: anyone with its link (the default, as a
     * class rep's board needs), or only the owner (as a lecturer's course
     * board needs). Enforced in the upload route, not just hidden in the UI.
     */
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->boolean('uploads_owner_only')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn('uploads_owner_only');
        });
    }
};
