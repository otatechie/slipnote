<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Arrivals, counted, never logged. One row per day x board x country x
     * source holding a number, so the operator can see where people come
     * from and how they found the site without any row describing a visitor.
     * No IP, no user agent, no cookie, no path beyond the board.
     *
     * Empty-string and zero sentinels instead of NULLs: a unique index
     * treats NULLs as distinct, which would break the upsert that keeps
     * this at one row per bucket.
     */
    public function up(): void
    {
        Schema::create('visit_tallies', function (Blueprint $table) {
            $table->id();
            $table->date('day');
            // 0 = not on a board (landing page, /start).
            $table->unsignedBigInteger('workspace_id')->default(0);
            // ISO 3166 alpha-2 from the CDN's country header; '' = unknown.
            $table->string('country', 2)->default('');
            // Where the arrival came from: a named bucket or a referring host.
            $table->string('source', 64);
            $table->unsignedInteger('arrivals')->default(0);

            $table->unique(['day', 'workspace_id', 'country', 'source']);
            $table->index('workspace_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_tallies');
    }
};
