<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Campus ambassadors: the people handed a ?ref= link. Operator-managed,
     * no login of their own. The slug is what boards carry in `referrer`;
     * everything else is so the operator can put a name to a row and knows
     * where to send the reward.
     */
    public function up(): void
    {
        Schema::create('ambassadors', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 40)->unique();
            $table->string('campus', 80)->nullable();
            // Encrypted at rest (model cast); it is a real person's number.
            $table->text('phone')->nullable();
            $table->string('network', 20)->nullable();
            // Retired keeps the history; the slug is never handed out again.
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ambassadors');
    }
};
