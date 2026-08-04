<?php

namespace App\Http\Controllers;

use App\Models\BlockedUpload;
use App\Models\Course;
use App\Models\Material;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

/**
 * Site-operator moderation dashboard — the kill-switch for abuse. Lists every
 * reported file across all workspaces and lets the operator remove or dismiss
 * them. Abuse-report notifications link here.
 *
 * Security posture:
 *  - Disabled entirely unless OPERATOR_SECRET is configured.
 *  - Login is a POST; the secret is held in the session (never in a URL),
 *    checked timing-safe, and rate-limited.
 *  - Destructive actions (remove / dismiss) are POSTs, CSRF-protected, behind
 *    the operator-session gate.
 */
class OperatorController extends Controller
{
    private function enabled(): bool
    {
        return filled(config('noteshare.operator_secret'));
    }

    /**
     * Session carries a fingerprint of the secret it was authenticated with.
     * Verifying it against the CURRENT secret means rotating OPERATOR_SECRET
     * immediately invalidates every existing operator session (as the
     * deployment guidance promises) — not just on expiry/manual logout.
     */
    private function secretFingerprint(): string
    {
        return hash('sha256', (string) config('noteshare.operator_secret'));
    }

    private function authed(): bool
    {
        return is_string(session('operator_fp'))
            && hash_equals($this->secretFingerprint(), session('operator_fp'));
    }

    /** The dashboard (or the login form when not authenticated). */
    public function dashboard()
    {
        abort_unless($this->enabled(), 404);

        if (! $this->authed()) {
            return view('operator.login');
        }

        // Reported files, most-reported first. Capped: the queue is worked
        // worst-first and items leave as they're removed/dismissed, so the tail
        // past 50 never needs to be on screen — but an abuse spike must not try
        // to render thousands of rows at once. The count note flags any overflow.
        $reportedTotal = Material::whereHas('reports')->count();
        $materials = Material::query()
            ->whereHas('reports')
            ->withCount('reports')
            ->with(['reports' => fn ($q) => $q->latest()->limit(20), 'course.workspace'])
            ->orderByDesc('reports_count')
            ->limit(50)
            ->get();

        // Usage stats: is anyone actually using this thing?
        $stats = [
            'workspaces' => Workspace::count(),
            'workspaces_week' => Workspace::where('created_at', '>=', now()->subDays(7))->count(),
            'courses' => Course::count(),
            'courses_week' => Course::where('created_at', '>=', now()->subDays(7))->count(),
            'files' => Material::count(),
            'files_week' => Material::where('created_at', '>=', now()->subDays(7))->count(),
            'storage_mb' => round(Material::sum('file_size') / 1_048_576, 1),
            // A board is "active" if anyone opened or downloaded from it this
            // week — not whether it received files. An archive that gets no
            // uploads but serves past papers every exam week is alive.
            'active_week' => Workspace::where('last_accessed_at', '>=', now()->subDays(7))->count(),
        ];

        // Most recent boards, with how much they hold and when they were last
        // touched — a board with files but no recent access is a dead one.
        $recent = Workspace::query()
            ->withCount(['courses', 'materials'])
            ->latest()
            ->limit(10)
            ->get();

        return view('operator.dashboard', [
            'materials' => $materials,
            'reportedTotal' => $reportedTotal,
            'stats' => $stats,
            'recent' => $recent,
        ]);
    }

    /** Enter the operator secret (timing-safe, rate-limited). */
    public function login(Request $request)
    {
        abort_unless($this->enabled(), 404);

        $key = 'operator_login:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['secret' => 'Too many attempts. Try again in a few minutes.']);
        }

        $given = (string) $request->input('secret', '');
        if ($given !== '' && hash_equals((string) config('noteshare.operator_secret'), $given)) {
            RateLimiter::clear($key);
            $request->session()->regenerate(); // anti-fixation on privilege change
            // Bind the session to THIS secret value; rotating it logs the
            // session out (authed() compares against the current secret).
            session(['operator_fp' => $this->secretFingerprint()]);

            return redirect()->route('operator.dashboard');
        }

        RateLimiter::hit($key, 600);

        return back()->withErrors(['secret' => "That operator secret isn't right."]);
    }

    public function logout(Request $request)
    {
        $request->session()->forget('operator_fp');

        return redirect()->route('operator.dashboard');
    }

    /** Delete the file and its reports (cascade). */
    public function remove(Request $request, Material $material)
    {
        abort_unless($this->enabled() && $this->authed(), 403);

        // Blocklist the exact bytes so the same file can't be re-uploaded
        // (anonymous whack-a-mole defense). Hash before the file is deleted.
        if (Storage::disk('local')->exists($material->stored_path)) {
            $hash = hash('sha256', Storage::disk('local')->get($material->stored_path));
            BlockedUpload::firstOrCreate(['content_hash' => $hash]);
        }

        Storage::disk('local')->delete($material->stored_path);
        $material->delete();

        return redirect()->route('operator.dashboard')->with('done', 'File removed.');
    }

    /** Clear a file's reports without deleting it (a false alarm). */
    public function dismiss(Request $request, Material $material)
    {
        abort_unless($this->enabled() && $this->authed(), 403);

        $material->reports()->delete();

        return redirect()->route('operator.dashboard')->with('done', 'Reports dismissed.');
    }
}
