<?php

namespace App\Http\Controllers;

use App\Models\BlockedUpload;
use App\Models\Course;
use App\Models\Material;
use App\Services\TelegramNotifier;
use App\Support\RecentWorkspaces;
use App\Tenancy\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CourseController extends Controller
{
    private function workspace()
    {
        return app(Tenancy::class)->current();
    }

    private function isOwner(): bool
    {
        return session($this->workspace()->ownerSessionKey()) === true;
    }

    public function show(Request $request, string $workspaceSlug, string $slug)
    {
        $workspace = $this->workspace();

        // Handle ?owner= URL param
        $given = $request->query('owner');
        if ($workspace->verifyOwner(is_string($given) ? $given : null)) {
            session()->regenerate(); // anti-fixation on privilege change

            session([$workspace->ownerSessionKey() => true]);

            return redirect()
                ->route('course.show', ['workspace' => $workspace->slug, 'slug' => $slug])
                ->withCookie(RecentWorkspaces::add($request, $workspace));
        }

        $course = Course::where('slug', $slug)->firstOrFail();

        $search = trim($request->input('search', ''));
        $sort = $request->input('sort', 'newest');
        $activeSection = $request->input('section', '');

        $query = $course->materials();
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")->orWhere('original_filename', 'like', "%{$search}%");
            });
        }
        if ($activeSection !== '' && isset(Material::SECTIONS[$activeSection])) {
            $query->where('section', $activeSection);
        }
        match ($sort) {
            'oldest' => $query->oldest(),
            'az' => $query->orderByRaw('COALESCE(title, original_filename) collate nocase asc'),
            default => $query->latest(),
        };

        $sectionCounts = $course->materials()
            ->selectRaw('section, count(*) as total')
            ->groupBy('section')
            ->pluck('total', 'section');

        $materials = $query->get()->map(fn ($m) => [
            'id' => $m->id,
            'section' => $m->section,
            'displayName' => $m->displayName(),
            'original_filename' => $m->original_filename,
            'fileTypeLabel' => $m->fileTypeLabel(),
            'uploader_name' => $m->uploader_name,
            // "just now" reads better than "0 seconds ago" for fresh uploads.
            'created_at_human' => $m->created_at->diffInSeconds() < 45
                ? 'just now'
                : $m->created_at->diffForHumans(),
            'download_url' => filled($m->manage_token)
                ? route('material.download', ['token' => $m->manage_token])
                : null,
            'delete_url' => route('material.destroy', ['material' => $m->id, 'token' => 'owner']),
            'manage_url' => $m->manageUrl(),
            'title' => $m->title,
        ]);

        return Inertia::render('CoursePage', [
            'workspace' => ['name' => $workspace->name, 'slug' => $workspace->slug],
            'course' => ['id' => $course->id, 'code' => $course->code, 'title' => $course->title, 'slug' => $course->slug],
            'isOwner' => $this->isOwner(),
            'storageFull' => $workspace->storageFull(),
            'passphraseNeeded' => filled($workspace->upload_passphrase) && session($workspace->uploadUnlockKey()) !== true,
            'sections' => Material::SECTIONS,
            'sectionCounts' => $sectionCounts,
            'materials' => $materials,
            'resultCount' => $course->materials()->count(),
            'search' => $search,
            'sort' => $sort,
            'activeSection' => $activeSection,
        ]);
    }

    public function bulkDelete(Request $request, string $workspaceSlug, string $slug)
    {
        $workspace = $this->workspace();
        abort_unless($this->isOwner(), 403);

        $course = Course::where('slug', $slug)->firstOrFail();

        $ids = $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer'])['ids'];

        $materials = $course->materials()->whereIn('id', $ids)->get();

        foreach ($materials as $material) {
            Storage::disk('local')->delete($material->stored_path);
            $material->delete();
        }

        $count = $materials->count();

        return back()->with('uploaded', $count === 1 ? '1 file removed.' : "{$count} files removed.");
    }

    public function exitOwner(Request $request)
    {
        $workspace = $this->workspace();
        session()->forget($workspace->ownerSessionKey());

        return back();
    }

    /**
     * Anonymous abuse report. Anyone viewing a board can flag a file; the
     * operator is notified (Telegram, or logged) and decides what to do —
     * we never auto-delete, so a report can't be weaponised to remove a
     * legit file. The material is resolved through the scoped course, so a
     * report can only target a file in the board being viewed.
     */
    public function report(Request $request, string $workspaceSlug, string $slug, int $material)
    {
        $course = Course::where('slug', $slug)->firstOrFail();

        // Material has no workspace global scope, so resolve it THROUGH the
        // (scoped) course — a report can only target a file in this board.
        $material = $course->materials()->whereKey($material)->firstOrFail();

        // Throttle to blunt spam/notification floods: 5 reports / 10 min,
        // keyed per material + client IP.
        $key = 'report:'.$material->id.':'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->with('reported', 'Thanks — this file has already been reported.');
        }
        RateLimiter::hit($key, 600);

        $reason = trim((string) $request->input('reason', ''));
        $reason = $reason !== '' ? strip_tags(mb_substr($reason, 0, 280)) : null;

        // Persist for the operator dashboard, then notify (best-effort).
        $material->reports()->create([
            'reason' => $reason,
            'reporter_ip' => $request->ip(),
        ]);

        dispatch(fn () => app(TelegramNotifier::class)->notifyReport($material, $reason))->afterResponse();

        return back()->with('reported', 'Thanks — reported to the site operator for review.');
    }

    public function upload(Request $request, string $workspaceSlug, string $slug)
    {
        $workspace = $this->workspace();
        $course = Course::where('slug', $slug)->firstOrFail();

        $data = $request->validate([
            'section' => 'required|in:'.implode(',', array_keys(Material::SECTIONS)),
            'title' => 'nullable|string|max:120',
            'uploaderName' => 'nullable|string|max:60',
            'passphrase' => 'nullable|string',
            'files' => 'required|array|min:1|max:20',
            'files.*' => 'file|max:25600|mimes:pdf,docx,pptx,png,jpg,jpeg',
        ]);

        // Per-IP upload throttle: blunts automated abuse / mass dumping while
        // staying generous for a student uploading a semester's notes.
        $rlKey = 'upload:'.$request->ip();
        if (RateLimiter::tooManyAttempts($rlKey, 30)) {
            return back()->withErrors(['files' => 'Too many uploads in a short time. Please wait a few minutes and try again.']);
        }
        RateLimiter::hit($rlKey, 600);

        // Fail closed: if the free-space probe errors (returns false), treat
        // it as zero space rather than waving the upload through.
        $free = disk_free_space(storage_path('app/public'));
        if ($free === false || $free < (int) config('noteshare.min_free_disk_bytes')) {
            return back()->withErrors(['files' => 'The site is at capacity — please try again later.']);
        }

        if (filled($workspace->upload_passphrase) && session($workspace->uploadUnlockKey()) !== true) {
            // Brute-force limit: 5 attempts per 10 min per workspace.
            $key = 'upload_passphrase:'.$workspace->id;
            if (RateLimiter::tooManyAttempts($key, 5)) {
                return back()->withErrors(['passphrase' => 'Too many attempts. Try again in a few minutes.']);
            }

            if (! $workspace->uploadPassphraseMatches($data['passphrase'] ?? null)) {
                RateLimiter::hit($key, 600);

                return back()->withErrors(['passphrase' => "That passphrase isn't right — ask your course rep."]);
            }

            RateLimiter::clear($key);
            session([$workspace->uploadUnlockKey() => true]);
        }

        $files = $request->file('files');

        // A shared title only makes sense for a single file; with several
        // files each keeps its own filename.
        $title = count($files) === 1 && isset($data['title']) ? strip_tags($data['title']) : null;
        $uploaderName = isset($data['uploaderName']) ? strip_tags($data['uploaderName']) : null;

        // Soft per-workspace cap: track headroom across this batch so a single
        // request never overshoots. The host-disk check above is the hard
        // safety net; a tiny overshoot under concurrent uploads is acceptable.
        $remaining = $workspace->storageRemaining();
        $created = [];
        $skipped = 0;
        $blocked = 0;

        foreach ($files as $file) {
            // Refuse files the operator previously removed (exact-content match).
            $hash = hash_file('sha256', $file->getRealPath());
            if (BlockedUpload::where('content_hash', $hash)->exists()) {
                $blocked++;

                continue;
            }

            $size = (int) $file->getSize();
            if ($size > $remaining) {
                $skipped++;

                continue;
            }
            $remaining -= $size;

            $material = $course->materials()->create([
                'section' => $data['section'],
                'title' => $title,
                'original_filename' => $file->getClientOriginalName(),
                'stored_path' => $file->store('materials', 'local'),
                'uploader_name' => $uploaderName,
                'manage_token' => Str::random(40),
                'file_size' => $size,
            ]);

            dispatch(fn () => app(TelegramNotifier::class)->notifyUpload($material))->afterResponse();
            $created[] = $material;
        }

        if (count($created) === 0) {
            if ($blocked > 0 && $skipped === 0) {
                return back()->withErrors(['files' => 'That file was removed by the site operator and can’t be re-uploaded.']);
            }

            return back()->withErrors(['files' => 'This board is full — ask the owner to delete old files.']);
        }

        $count = count($created);
        $message = $count === 1 ? 'File added.' : "{$count} files added.";
        if ($skipped > 0) {
            $message .= " {$skipped} skipped — board is full.";
        }
        if ($blocked > 0) {
            $message .= " {$blocked} blocked — removed by the operator.";
        }

        return back()->with([
            'uploaded' => $message,
            'manageUrl' => $count === 1 ? $created[0]->manageUrl() : null,
        ]);
    }
}
