<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who sent this board's creator. An ambassador's slug, copied from the
     * slipnote_ref cookie at creation and never changed. Attribution lives
     * on the board, not on a person: there are no accounts, and nothing
     * about the creator is recorded.
     */
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->string('referrer', 40)->nullable()->index()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn('referrer');
        });
    }
};
