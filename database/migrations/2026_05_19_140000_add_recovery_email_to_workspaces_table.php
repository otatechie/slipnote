<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional, opt-in owner recovery email. Stored ENCRYPTED via the
     * model's `encrypted` cast (a leaked DB/backup must not expose owners'
     * emails). Nullable: no email = no recovery, the documented default.
     * Length allows for the encrypted ciphertext, not just an email.
     */
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->text('recovery_email')->nullable()->after('upload_passphrase');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn('recovery_email');
        });
    }
};
