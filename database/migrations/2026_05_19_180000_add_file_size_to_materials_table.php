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
            $table->unsignedBigInteger('file_size')->default(0);
        });

        // Backfill existing rows from disk. Missing files → 0 (won't count
        // against the workspace cap, but they're broken downloads anyway).
        DB::table('materials')->orderBy('id')->each(function ($row) {
            $bytes = Storage::disk('public')->exists($row->stored_path)
                ? Storage::disk('public')->size($row->stored_path)
                : 0;
            DB::table('materials')->where('id', $row->id)->update(['file_size' => $bytes]);
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn('file_size');
        });
    }
};
