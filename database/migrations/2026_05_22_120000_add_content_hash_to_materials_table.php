<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            // sha256 of the file's bytes — lets uploads detect an identical
            // file already on the board and skip it. Indexed for the lookup.
            $table->string('content_hash', 64)->nullable()->index();
        });

        // Backfill existing rows from disk. Missing files stay null and are
        // simply never matched as duplicates.
        DB::table('materials')->orderBy('id')->each(function ($row) {
            $path = Storage::disk('local')->path($row->stored_path);
            if (is_file($path)) {
                DB::table('materials')
                    ->where('id', $row->id)
                    ->update(['content_hash' => hash_file('sha256', $path)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn('content_hash');
        });
    }
};
