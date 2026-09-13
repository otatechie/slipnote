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

// Resolve {workspace} by slug, not id (implicit binding would 404 every slug).
Route::bind('workspace', fn ($slug) => Workspace::where('slug', $slug)->firstOrFail());

// Marketing landing page — Blade, not Inertia, for SEO.
Route::view('/', 'welcome')->name('welcome');

Route::get('/start', [WorkspacesController::class, 'index'])->name('start');
Route::post('/workspaces', [WorkspacesController::class, 'store'])->name('workspaces.store');
Route::post('/workspaces/open', [WorkspacesController::class, 'open'])->name('workspaces.open');
Route::post('/workspaces/forget', [WorkspacesController::class, 'forget'])->name('workspaces.forget');

// Static routes below are declared BEFORE the /{workspace} catch-all so their
// paths (privacy, terms, download, operator, …) aren't read as workspace slugs.
Route::view('/privacy', 'legal.privacy')->name('privacy');
Route::view('/terms', 'legal.terms')->name('terms');

// Material download. Anonymous by design — but addressed by the file's
// random download_token, not a sequential id, so files can't be enumerated
// across workspaces by guessing /download/1, /download/2, … This is the
// ADDRESS token only: it must never be accepted by the delete route below,
// which takes the separate, uploader-private manage_token.
Route::get('/download/{token}', function (Request $request, string $token) {
    $material = Material::where('download_token', $token)->firstOrFail();
    abort_unless($material->course()->exists(), 404);
    abort_unless(Storage::disk('local')->exists($material->stored_path), 404);

    // Downloads are the signal that a board is still doing its job — a past
    // papers archive gets no uploads for a year and is used every exam week.
    // This route sits outside the workspace group, so ResolveWorkspace's
    // touch never fires here.
    $material->course->workspace->touchAccess();

    // ?view=1 serves PDFs/images inline for preview. Only previewable types —
    // serving arbitrary uploads (e.g. HTML) inline would be an XSS vector.
    if ($request->boolean('view') && $material->isPreviewable()) {
        return Storage::disk('local')->response(
            $material->stored_path,
            $material->downloadName(),
        );
    }

    return Storage::disk('local')->download(
        $material->stored_path,
        $material->downloadName(),
    );
})->name('material.download');

// Uploader-or-owner delete. Owner path is scoped to the material's OWN
// workspace session — owning workspace A grants nothing over a file in B.
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

// Operator moderation dashboard. Gated by OPERATOR_SECRET held in session.
Route::get('/operator', [OperatorController::class, 'dashboard'])->name('operator.dashboard');
Route::post('/operator/login', [OperatorController::class, 'login'])->name('operator.login');
Route::post('/operator/logout', [OperatorController::class, 'logout'])->name('operator.logout');
Route::post('/operator/material/{material}/remove', [OperatorController::class, 'remove'])->name('operator.remove');
Route::post('/operator/material/{material}/dismiss', [OperatorController::class, 'dismiss'])->name('operator.dismiss');
Route::post('/operator/undo', [OperatorController::class, 'undo'])->name('operator.undo');
Route::post('/operator/ambassadors', [OperatorController::class, 'storeAmbassador'])->name('operator.ambassadors.store');
Route::post('/operator/ambassadors/{ambassador}/retire', [OperatorController::class, 'retireAmbassador'])->name('operator.ambassadors.retire');

// Workspace-scoped routes. The catch-all /{workspace} lives here, so this
// group is declared last (static routes above win).
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
    // The link that was mailed. Signed (tamper-proof, 1h expiry) and backed by
    // a single-use nonce; rotating the owner secret happens HERE, not when the
    // request is made, so a stranger who knows the recovery address can't
    // revoke the owner's link by asking.
    Route::get('/{workspace}/recover/{nonce}', [WorkspaceRecoveryController::class, 'redeem'])
        ->middleware('signed')
        ->name('workspace.recover.redeem');
});
