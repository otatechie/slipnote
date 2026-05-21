<?php

use App\Models\Material;
use App\Models\Workspace;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

// Resolve the {workspace} route segment by slug (NOT by id — implicit
// binding would try the primary key and 404 every slug). Unknown → 404.
Route::bind('workspace', fn ($slug) => Workspace::where('slug', $slug)->firstOrFail());

// Root: pick or create a workspace. No tenant resolved here.
Route::get('/', [App\Http\Controllers\WorkspacesController::class, 'index'])->name('home');
Route::post('/workspaces', [App\Http\Controllers\WorkspacesController::class, 'store'])->name('workspaces.store');
Route::post('/workspaces/open', [App\Http\Controllers\WorkspacesController::class, 'open'])->name('workspaces.open');
Route::post('/workspaces/forget', [App\Http\Controllers\WorkspacesController::class, 'forget'])->name('workspaces.forget');

// Static legal pages. Declared BEFORE the /{workspace} catch-all so the
// slugs "privacy" and "terms" aren't read as workspace names.
Route::view('/privacy', 'legal.privacy')->name('privacy');
Route::view('/terms', 'legal.terms')->name('terms');

// Material download. By global id (decision a): these are shared files and
// downloads are anonymous by design. Declared BEFORE the /{workspace}
// catch-all so "download" isn't read as a workspace slug.
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
    Route::get('/{workspace}', [App\Http\Controllers\CoursesController::class, 'index'])->name('courses.index');
    Route::post('/{workspace}/courses', [App\Http\Controllers\CoursesController::class, 'store'])->name('courses.store');
    Route::post('/{workspace}/unlock', [App\Http\Controllers\CoursesController::class, 'unlock'])->name('courses.unlock');
    Route::post('/{workspace}/recovery-email', [App\Http\Controllers\CoursesController::class, 'saveRecoveryEmail'])->name('courses.recovery-email');

    Route::get('/{workspace}/c/{slug}', [App\Http\Controllers\CourseController::class, 'show'])->name('course.show');
    Route::post('/{workspace}/c/{slug}/upload', [App\Http\Controllers\CourseController::class, 'upload'])->name('course.upload');
    Route::post('/{workspace}/c/{slug}/exit-owner', [App\Http\Controllers\CourseController::class, 'exitOwner'])->name('course.exit-owner');

    Route::get('/{workspace}/recover', [App\Http\Controllers\WorkspaceRecoveryController::class, 'show'])->name('workspace.recover');
    Route::post('/{workspace}/recover', [App\Http\Controllers\WorkspaceRecoveryController::class, 'store'])->name('workspace.recover.store');
});
