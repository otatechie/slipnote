<?php

use App\Models\Material;
use App\Models\Workspace;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

// Resolve the {workspace} route segment by slug (NOT by id — implicit
// binding would try the primary key and 404 every slug). Unknown → 404.
Route::bind('workspace', fn ($slug) => Workspace::where('slug', $slug)->firstOrFail());

// Root: pick or create a workspace. No tenant resolved here.
Route::livewire('/', 'workspaces-landing')->name('home');

// Material download. By global id (decision a): these are shared files and
// downloads are anonymous by design (see README). Declared BEFORE the
// /{workspace} catch-all so "download" isn't read as a workspace slug.
Route::get('/download/{material}', function (Material $material) {
    abort_unless($material->course()->exists(), 404);
    abort_unless(Storage::disk('public')->exists($material->stored_path), 404);

    return Storage::disk('public')->download(
        $material->stored_path,
        $material->original_filename,
    );
})->name('material.download');

// Uploader-or-owner delete. By global id; the owner path is scoped to the
// material's OWN workspace session — owning workspace A grants nothing over
// a file in workspace B.
Route::delete('/materials/{material}/{token}', function (Material $material, string $token) {
    abort_unless($material->course()->exists(), 404);

    $workspace = $material->course->workspace;

    $byToken = filled($material->manage_token)
        && hash_equals($material->manage_token, $token);
    $byOwner = session($workspace->ownerSessionKey()) === true;

    abort_unless($byToken || $byOwner, 403);

    Storage::disk('public')->delete($material->stored_path);
    $material->delete();

    return redirect()
        ->route('course.show', [
            'workspace' => $workspace->slug,
            'slug' => $material->course->slug,
        ])
        ->with('uploaded', 'File removed.');
})->name('material.destroy');

// Everything inside a workspace. ResolveWorkspace sets the current tenant
// from the {workspace} slug (unknown slug → 404) before any scoped query.
// Declared last so the static routes above win.
Route::middleware('workspace')->group(function () {
    Route::livewire('/{workspace}', 'courses-page')->name('courses.index');
    Route::livewire('/{workspace}/c/{slug}', 'course-page')->name('course.show');
    // Public: a locked-out owner is NOT in owner mode, so this must be
    // reachable without the owner secret.
    Route::livewire('/{workspace}/recover', 'workspace-recovery')->name('workspace.recover');
});
