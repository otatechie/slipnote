<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            // The reported file. Cascades away if the file is removed, so the
            // dashboard never lists reports for files that no longer exist.
            $table->foreignId('material_id')->constrained()->cascadeOnDelete();
            $table->string('reason')->nullable();
            $table->string('reporter_ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('material_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
