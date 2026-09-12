<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Separate the file's public ADDRESS from its delete CAPABILITY.
     *
     * manage_token used to do both: it was the /download/{token} path handed
     * to every visitor on the course page, and the secret that authorises
     * DELETE /materials/{id}/{token}. Anyone who could read the page could
     * therefore delete any file. download_token is the address now;
     * manage_token goes back to being private to the uploader.
     */
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->string('download_token', 64)->nullable()->unique()->after('manage_token');
        });

        // Backfill so existing files stay downloadable. One-by-one because the
        // column is unique and each row needs its own random value.
        DB::table('materials')->select('id')->orderBy('id')->each(function ($row) {
            DB::table('materials')->where('id', $row->id)->update(['download_token' => Str::random(40)]);
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn('download_token');
        });
    }
};
