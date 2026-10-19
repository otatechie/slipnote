<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Messages from the "Suggest a feature" form. Kept so none are lost if
     * Telegram is down or unset. No IP and no user agent: the only thing
     * identifying a sender is a reply address they chose to type.
     */
    public function up(): void
    {
        Schema::create('suggestions', function (Blueprint $table) {
            $table->id();
            $table->text('message');
            $table->string('reply_to', 120)->nullable();
            // Path of the page it was sent from (e.g. /nursing-level-200), no query.
            $table->string('page', 160)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suggestions');
    }
};
