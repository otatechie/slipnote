<?php

use App\Models\Workspace;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('boards:prune-abandoned', function () {
    [$removed, $warned] = Workspace::pruneAbandoned();
    $this->info("{$removed} removed, {$warned} owners warned.");
})->purpose('Remove boards with no files that nobody has opened for 30 days');

Schedule::command('boards:prune-abandoned')->daily();
