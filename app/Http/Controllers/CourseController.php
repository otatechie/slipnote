<?php

namespace App\Http\Controllers;

use App\Models\BlockedUpload;
use App\Models\Course;
use App\Models\Material;
use App\Services\TelegramNotifier;
use App\Support\RecentWorkspaces;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CourseController extends Controller
{
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
            // Each word must match title or filename, so "past paper" finds
            // "past_papers_2023.pdf" — separators in filenames shouldn't hide
            // files from the natural query.
            foreach (preg_split('/\s+/', $search) as $term) {
                $query->where(function ($q) use ($term) {
                    $q->where('title', 'like', "%{$term}%")->orWhere('original_filename', 'like', "%{$term}%");
                });
            }
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
            // Short units ("28m ago") keep the meta line from truncating on phones.
            'created_at_human' => $m->created_at->diffInSeconds() < 45
                ? 'just now'
                : $m->created_at->diffForHumans(short: true),
            'download_url' => filled($m->manage_token)
                ? route('material.download', ['token' => $m->manage_token])
                : null,
            'preview_url' => $m->previewUrl(),
            'delete_url' => route('material.destroy', ['material' => $m->id, 'token' => 'owner']),
            'manage_url' => $m->manageUrl(),
            'title' => $m->title,
        ]);

        return Inertia::render('CoursePage', [
            'workspace' => ['name' => $workspace->name, 'slug' => $workspace->slug],
            'course' => ['id' => $course->id, 'code' => $course->code, 'title' => $course->title, 'slug' => $course->slug],
            'isOwner' => $this->isOwner(),
            'storageFull' => $workspace->storageFull(),
            'storageUsed' => $storageUsed = $workspace->storageBytes(),
            'storageCap' => $storageCap = (int) config('noteshare.workspace_storage_bytes'),
            'storagePct' => $storageCap > 0 ? min(100, (int) round($storageUsed / $storageCap * 100)) : 0,
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

    /**
     * Stream every file in one section as a zip. Anonymous, like single
     * downloads — anyone with the board link can grab the whole section
     * (the real exam-time need: "all the past papers", not twelve clicks).
     * Built into a temp file then streamed and deleted after send.
     */
    public function downloadSection(string $workspaceSlug, string $slug, string $section)
    {
        abort_unless(isset(Material::SECTIONS[$section]), 404);

        $course = Course::where('slug', $slug)->firstOrFail();

        $materials = $course->materials()
            ->where('section', $section)
            ->latest()
            ->get();

        abort_if($materials->isEmpty(), 404);

        $zipPath = tempnam(sys_get_temp_dir(), 'slipzip');
        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::OVERWRITE);

        // De-collide duplicate filenames inside the archive; skip files that
        // vanished from disk rather than failing the whole download.
        $used = [];
        $added = 0;
        foreach ($materials as $material) {
            $realPath = Storage::disk('local')->path($material->stored_path);
            if (! is_file($realPath)) {
                continue;
            }

            $name = $material->original_filename;
            if (isset($used[$name])) {
                $ext = pathinfo($name, PATHINFO_EXTENSION);
                $base = pathinfo($name, PATHINFO_FILENAME);
                $name = $ext !== '' ? "{$base} ({$used[$name]}).{$ext}" : "{$base} ({$used[$name]})";
                $used[$material->original_filename]++;
            } else {
                $used[$name] = 1;
            }

            $zip->addFile($realPath, $name);
            $added++;
        }
        $zip->close();

        if ($added === 0) {
            @unlink($zipPath);
            abort(404);
        }

        $sectionLabel = Str::slug(Material::SECTIONS[$section]);
        $filename = "{$course->slug}-{$sectionLabel}.zip";

        return response()
            ->download($zipPath, $filename)
            ->deleteFileAfterSend(true);
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
        $free = disk_free_space(storage_path('app/private'));
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
        $duplicate = 0;
        $duplicateName = null;
        // Hashes already present in this course, plus any added this batch, so
        // two identical files in one upload also de-dupe against each other.
        $seenHashes = $course->materials()->whereNotNull('content_hash')->pluck('content_hash')->flip();

        foreach ($files as $file) {
            $hash = hash_file('sha256', $file->getRealPath());

            // Refuse files the operator previously removed (exact-content match).
            if (BlockedUpload::where('content_hash', $hash)->exists()) {
                $blocked++;

                continue;
            }

            // Skip a file already on this board (same bytes). Prevents the
            // natural entropy of five copies of one past paper under different
            // names on an anonymous board.
            if ($seenHashes->has($hash)) {
                $duplicate++;
                $duplicateName ??= $course->materials()->where('content_hash', $hash)->value('title')
                    ?? $course->materials()->where('content_hash', $hash)->value('original_filename');

                continue;
            }

            $size = (int) $file->getSize();
            if ($size > $remaining) {
                $skipped++;

                continue;
            }
            $remaining -= $size;
            $seenHashes->put($hash, true);

            $material = $course->materials()->create([
                'section' => $data['section'],
                'title' => $title,
                'original_filename' => $file->getClientOriginalName(),
                'stored_path' => $file->store('materials', 'local'),
                'uploader_name' => $uploaderName,
                'manage_token' => Str::random(40),
                'file_size' => $size,
                'content_hash' => $hash,
            ]);

            dispatch(fn () => app(TelegramNotifier::class)->notifyUpload($material))->afterResponse();
            $created[] = $material;
        }

        if (count($created) === 0) {
            if ($blocked > 0 && $skipped === 0 && $duplicate === 0) {
                return back()->withErrors(['files' => 'That file was removed by the site operator and can’t be re-uploaded.']);
            }

            if ($duplicate > 0 && $skipped === 0 && $blocked === 0) {
                $where = $duplicateName ? " as \"{$duplicateName}\"" : '';

                return back()->withErrors(['files' => $duplicate === 1
                    ? "That file is already on this board{$where}."
                    : 'Those files are already on this board.']);
            }

            return back()->withErrors(['files' => 'This board is full — ask the owner to delete old files.']);
        }

        $count = count($created);
        $message = $count === 1 ? 'File added.' : "{$count} files added.";
        if ($duplicate > 0) {
            $message .= $duplicate === 1
                ? ' 1 skipped — already on the board.'
                : " {$duplicate} skipped — already on the board.";
        }
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
