<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocked_uploads', function (Blueprint $table) {
            $table->id();
            // SHA-256 of the file contents of a file the operator removed.
            // Re-uploads of the exact same bytes are refused — anonymous
            // whack-a-mole defense that needs no accounts.
            $table->string('content_hash', 64)->unique();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocked_uploads');
    }
};
