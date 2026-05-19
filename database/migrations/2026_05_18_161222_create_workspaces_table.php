<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A workspace is an isolated board: its own courses, owner credential,
     * and optional upload passphrase. No accounts — the owner secret (stored
     * hashed) is the only credential, handed out once as a capability link.
     */
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            // bcrypt hash of the owner secret — never the plaintext.
            $table->string('owner_secret_hash');
            // null/empty = uploads open; otherwise required once per session.
            $table->string('upload_passphrase')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspaces');
    }
};
