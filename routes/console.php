<?php

use App\Models\Workspace;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('boards:prune-abandoned', function () {
    [$removed, $warned] = Workspace::pruneAbandoned();
    $this->info("{$removed} removed, {$warned} owners warned.");
})->purpose('Remove boards with no files that nobody has opened for 30 days');

Schedule::command('boards:prune-abandoned')->daily();

// Run before every migration (docker/entrypoint.d): a bad migration must never meet the only copy.
Artisan::command('db:snapshot {--keep=10}', function () {
    $path = config('database.connections.sqlite.database');
    if (config('database.default') !== 'sqlite' || ! is_string($path) || ! is_file($path)) {
        $this->info('No SQLite file yet; nothing to copy.');

        return 0;
    }

    $dir = dirname($path).'/backups';
    File::ensureDirectoryExists($dir);
    $to = $dir.'/'.now()->format('Y-m-d_His').'.sqlite';

    // VACUUM INTO is a consistent copy even while the app is writing.
    DB::connection('sqlite')->statement('VACUUM INTO ?', [$to]);

    $all = glob($dir.'/*.sqlite');
    sort($all);
    foreach (array_slice($all, 0, max(0, count($all) - (int) $this->option('keep'))) as $old) {
        File::delete($old);
    }

    $this->info("Copied to {$to}");

    return 0;
})->purpose('Copy the SQLite database to database/backups, keeping the newest few');
