<?php

use App\Http\Controllers\CourseController;
use App\Http\Controllers\CoursesController;
use App\Http\Controllers\ManifestController;
use App\Http\Controllers\OperatorController;
use App\Http\Controllers\SuggestionController;
use App\Http\Controllers\WorkspaceRecoveryController;
use App\Http\Controllers\WorkspacesController;
use App\Models\Material;
use App\Models\Workspace;
use App\Support\ClientKey;
use App\Support\VisitTally;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

// Unknown addresses are rate-limited per client, so boards can't be found by trying slugs.
Route::bind('workspace', function ($slug) {
    $workspace = Workspace::where('slug', $slug)->first();
    if ($workspace) {
        return $workspace;
    }

    $key = 'board_miss:'.ClientKey::of(request());
    abort_if(RateLimiter::tooManyAttempts($key, 60), 429, 'Too many tries. Wait a few minutes.');
    RateLimiter::hit($key, 600);
    abort(404);
});

// Marketing landing page — Blade, not Inertia, for SEO.
Route::view('/', 'welcome')->name('welcome');

Route::get('/start', [WorkspacesController::class, 'index'])->name('start');
Route::post('/workspaces', [WorkspacesController::class, 'store'])->name('workspaces.store');
Route::post('/workspaces/open', [WorkspacesController::class, 'open'])->name('workspaces.open');
Route::post('/workspaces/forget', [WorkspacesController::class, 'forget'])->name('workspaces.forget');
Route::post('/suggestions', [SuggestionController::class, 'store'])->name('suggestions.store');

Route::view('/privacy', 'legal.privacy')->name('privacy');
Route::get('/manifest.webmanifest', [ManifestController::class, 'site'])->name('manifest');
Route::view('/terms', 'legal.terms')->name('terms');
Route::view('/lecturers', 'lecturers')->name('lecturers');

// By the random download_token, never the id, so files can't be enumerated.
Route::get('/download/{token}', function (Request $request, string $token) {
    $material = Material::where('download_token', $token)->firstOrFail();
    abort_unless($material->course()->exists(), 404);
    abort_unless(Storage::disk('local')->exists($material->stored_path), 404);

    if (! VisitTally::isBot($request)) {
        $material->course->workspace->touchAccess();
    }

    // Inline only for previewable types: serving any upload inline would be an XSS vector.
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

// The uploader's private manage_token, or owner mode on the file's own board.
Route::delete('/materials/{material}/{token}', function (Material $material, string $token) {
    abort_unless($material->course()->exists(), 404);

    $workspace = $material->course->workspace;

    $byToken = filled($material->manage_token)
        && hash_equals($material->manage_token, $token);
    $byOwner = $workspace->sessionIsOwner();

    abort_unless($byToken || $byOwner, 403);

    $material->discard();

    return redirect()
        ->route('course.show', [
            'workspace' => $workspace->slug,
            'slug' => $material->course->slug,
        ])
        ->with('notice', 'File removed.');
})->name('material.destroy');

// Operator moderation dashboard. Gated by OPERATOR_SECRET held in session.
Route::get('/operator', [OperatorController::class, 'dashboard'])->name('operator.dashboard');
Route::post('/operator/login', [OperatorController::class, 'login'])->name('operator.login');
Route::post('/operator/logout', [OperatorController::class, 'logout'])->name('operator.logout');
Route::post('/operator/telegram-test', [OperatorController::class, 'telegramTest'])->name('operator.telegram-test');
Route::post('/operator/material/{material}/remove', [OperatorController::class, 'remove'])->name('operator.remove');
Route::post('/operator/material/{material}/dismiss', [OperatorController::class, 'dismiss'])->name('operator.dismiss');
Route::post('/operator/undo', [OperatorController::class, 'undo'])->name('operator.undo');
Route::post('/operator/suggestions/{suggestion}/delete', [OperatorController::class, 'deleteSuggestion'])->name('operator.suggestions.delete');
Route::post('/operator/boards/{board}/note', [OperatorController::class, 'saveNote'])->name('operator.boards.note');
Route::post('/operator/boards/{board}/delete', [OperatorController::class, 'deleteBoard'])->name('operator.boards.delete');
Route::post('/operator/ambassadors', [OperatorController::class, 'storeAmbassador'])->name('operator.ambassadors.store');
Route::post('/operator/ambassadors/{ambassador}/retire', [OperatorController::class, 'retireAmbassador'])->name('operator.ambassadors.retire');

Route::middleware('workspace')->group(function () {
    Route::get('/{workspace}', [CoursesController::class, 'index'])->name('courses.index');
    Route::get('/{workspace}/manifest.webmanifest', [ManifestController::class, 'board'])->name('workspace.manifest');
    Route::post('/{workspace}/courses', [CoursesController::class, 'store'])->name('courses.store');
    Route::post('/{workspace}/courses/reorder', [CoursesController::class, 'reorder'])->name('courses.reorder');
    Route::put('/{workspace}/c/{slug}', [CoursesController::class, 'update'])->name('courses.update');
    Route::post('/{workspace}/unlock', [CoursesController::class, 'unlock'])->name('courses.unlock');
    Route::post('/{workspace}/lock', [CourseController::class, 'exitOwner'])->name('courses.lock');
    Route::post('/{workspace}/recovery-email', [CoursesController::class, 'saveRecoveryEmail'])->name('courses.recovery-email');
    Route::post('/{workspace}/upload-access', [CoursesController::class, 'setUploadAccess'])->name('courses.upload-access');
    Route::post('/{workspace}/owner-link/reset', [CoursesController::class, 'resetOwnerLink'])->name('courses.owner-link.reset');
    Route::delete('/{workspace}', [CoursesController::class, 'destroy'])->name('courses.destroy');

    Route::get('/{workspace}/c/{slug}', [CourseController::class, 'show'])->name('course.show');
    Route::get('/{workspace}/c/{slug}/download/{section}', [CourseController::class, 'downloadSection'])->name('course.download-section');
    Route::post('/{workspace}/c/{slug}/upload', [CourseController::class, 'upload'])->name('course.upload');

    Route::delete('/{workspace}/c/{slug}/materials', [CourseController::class, 'bulkDelete'])->name('course.bulk-delete');
    Route::delete('/{workspace}/c/{slug}/undo', [CourseController::class, 'undoUpload'])->name('course.undo-upload');

    Route::post('/{workspace}/c/{slug}/report/{material}', [CourseController::class, 'report'])->name('material.report');
    Route::post('/{workspace}/c/{slug}/pin/{material}', [CourseController::class, 'pin'])->name('material.pin');
    Route::patch('/{workspace}/c/{slug}/materials/{material}', [CourseController::class, 'update'])->name('material.update');

    Route::get('/{workspace}/recover', [WorkspaceRecoveryController::class, 'show'])->name('workspace.recover');
    Route::post('/{workspace}/recover', [WorkspaceRecoveryController::class, 'store'])->name('workspace.recover.store');
    Route::get('/{workspace}/recover/{nonce}', [WorkspaceRecoveryController::class, 'redeem'])
        ->middleware('signed')
        ->name('workspace.recover.redeem');
});
