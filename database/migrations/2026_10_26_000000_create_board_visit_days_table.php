<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Days a board was opened or downloaded from by someone other than its
     * owner (see Workspace::noteVisitor). One row per board per day, no
     * visitor identity: enough to tell a used board from one its owner or
     * ambassador keeps opening for the reward.
     */
    public function up(): void
    {
        Schema::create('board_visit_days', function (Blueprint $table) {
            $table->unsignedBigInteger('workspace_id');
            $table->date('day');

            $table->primary(['workspace_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_visit_days');
    }
};
