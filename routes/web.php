<?php

use App\Http\Controllers\CourseController;
use App\Http\Controllers\CoursesController;
use App\Http\Controllers\OperatorController;
use App\Http\Controllers\WorkspaceRecoveryController;
use App\Http\Controllers\WorkspacesController;
use App\Models\Material;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

// Resolve the {workspace} route segment by slug (NOT by id — implicit
// binding would try the primary key and 404 every slug). Unknown → 404.
Route::bind('workspace', fn ($slug) => Workspace::where('slug', $slug)->firstOrFail());

// Marketing landing page (Blade for SEO — rendered server-side, full
// meta tag control, no Inertia/Vue hydration cost).
Route::view('/', 'welcome')->name('welcome');

// Workspace create/open hub.
Route::get('/start', [WorkspacesController::class, 'index'])->name('start');
Route::post('/workspaces', [WorkspacesController::class, 'store'])->name('workspaces.store');
Route::post('/workspaces/open', [WorkspacesController::class, 'open'])->name('workspaces.open');
Route::post('/workspaces/forget', [WorkspacesController::class, 'forget'])->name('workspaces.forget');

// Static legal pages. Declared BEFORE the /{workspace} catch-all so the
// slugs "privacy" and "terms" aren't read as workspace names.
Route::view('/privacy', 'legal.privacy')->name('privacy');
Route::view('/terms', 'legal.terms')->name('terms');

// Material download. Anonymous by design — but addressed by the file's
// random manage_token, not a sequential id, so files can't be enumerated
// across workspaces by guessing /download/1, /download/2, … Declared
// BEFORE the /{workspace} catch-all so "download" isn't read as a slug.
Route::get('/download/{token}', function (Request $request, string $token) {
    $material = Material::where('manage_token', $token)->firstOrFail();
    abort_unless($material->course()->exists(), 404);
    abort_unless(Storage::disk('local')->exists($material->stored_path), 404);

    // ?view=1 serves the file inline (Content-Disposition: inline) so students
    // can preview before downloading. Only PDFs and images are ever served
    // inline — serving arbitrary uploads (e.g. HTML) inline would be an XSS
    // vector. Everything else falls through to a normal attachment download.
    if ($request->boolean('view') && $material->isPreviewable()) {
        return Storage::disk('local')->response(
            $material->stored_path,
            $material->original_filename,
        );
    }

    return Storage::disk('local')->download(
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

    Storage::disk('local')->delete($material->stored_path);
    $material->delete();

    return redirect()
        ->route('course.show', [
            'workspace' => $workspace->slug,
            'slug' => $material->course->slug,
        ])
        ->with('uploaded', 'File removed.');
})->name('material.destroy');

// Operator moderation dashboard (site admin). Lists reported files across all
// workspaces; gated by OPERATOR_SECRET held in session. Declared BEFORE the
// /{workspace} catch-all so "operator" isn't read as a workspace slug.
Route::get('/operator', [OperatorController::class, 'dashboard'])->name('operator.dashboard');
Route::post('/operator/login', [OperatorController::class, 'login'])->name('operator.login');
Route::post('/operator/logout', [OperatorController::class, 'logout'])->name('operator.logout');
Route::post('/operator/material/{material}/remove', [OperatorController::class, 'remove'])->name('operator.remove');
Route::post('/operator/material/{material}/dismiss', [OperatorController::class, 'dismiss'])->name('operator.dismiss');

// Everything inside a workspace. ResolveWorkspace sets the current tenant
// from the {workspace} slug (unknown slug → 404) before any scoped query.
// Declared last so the static routes above win.
Route::middleware('workspace')->group(function () {
    Route::get('/{workspace}', [CoursesController::class, 'index'])->name('courses.index');
    Route::post('/{workspace}/courses', [CoursesController::class, 'store'])->name('courses.store');
    Route::post('/{workspace}/courses/reorder', [CoursesController::class, 'reorder'])->name('courses.reorder');
    Route::put('/{workspace}/c/{slug}', [CoursesController::class, 'update'])->name('courses.update');
    Route::post('/{workspace}/unlock', [CoursesController::class, 'unlock'])->name('courses.unlock');
    Route::post('/{workspace}/lock', [CourseController::class, 'exitOwner'])->name('courses.lock');
    Route::post('/{workspace}/recovery-email', [CoursesController::class, 'saveRecoveryEmail'])->name('courses.recovery-email');

    Route::get('/{workspace}/c/{slug}', [CourseController::class, 'show'])->name('course.show');
    Route::get('/{workspace}/c/{slug}/download/{section}', [CourseController::class, 'downloadSection'])->name('course.download-section');
    Route::post('/{workspace}/c/{slug}/upload', [CourseController::class, 'upload'])->name('course.upload');

    Route::delete('/{workspace}/c/{slug}/materials', [CourseController::class, 'bulkDelete'])->name('course.bulk-delete');

    Route::post('/{workspace}/c/{slug}/report/{material}', [CourseController::class, 'report'])->name('material.report');

    Route::get('/{workspace}/recover', [WorkspaceRecoveryController::class, 'show'])->name('workspace.recover');
    Route::post('/{workspace}/recover', [WorkspaceRecoveryController::class, 'store'])->name('workspace.recover.store');
});
