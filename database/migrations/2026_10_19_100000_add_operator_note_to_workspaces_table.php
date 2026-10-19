<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A note from the operator to one board's owner, shown only in owner
     * mode on that board. The only way to reach an owner: there are no
     * accounts, and the recovery email is promised for recovery alone.
     * note_at tells the owner's browser a new note from a dismissed one.
     */
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->text('operator_note')->nullable();
            $table->timestamp('operator_note_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn(['operator_note', 'operator_note_at']);
        });
    }
};
