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
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            // Tenancy: every course belongs to exactly one workspace.
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('title');
            $table->string('slug');
            $table->timestamps();

            // Slugs are unique *within* a workspace, not globally — two
            // workspaces may each have a "phys-201".
            $table->unique(['workspace_id', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
