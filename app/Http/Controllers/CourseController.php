<?php

namespace App\Http\Controllers;

use App\Models\BlockedUpload;
use App\Models\Course;
use App\Models\Material;
use App\Services\TelegramNotifier;
use App\Support\ClientKey;
use App\Support\LastSeen;
use App\Support\RecentWorkspaces;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CourseController extends Controller
{
    public function show(Request $request, string $workspaceSlug, string $slug)
    {
        $workspace = $this->workspace();

        if ($this->redeemOwnerQuery($request, $workspace)) {
            return redirect()
                ->route('course.show', ['workspace' => $workspace->slug, 'slug' => $slug])
                ->withCookie(RecentWorkspaces::add($request, $workspace));
        }

        $course = Course::where('slug', $slug)->firstOrFail();

        view()->share('ogCourse', $course);

        $newSince = LastSeen::since($request, $course->id);
        Cookie::queue(LastSeen::mark($request, $course->id));
        if ($this->isOwner() && ($recent = RecentWorkspaces::touch($request, $workspace))) {
            Cookie::queue($recent);
        }

        $search = $this->searchInput($request);
        $sort = $request->input('sort', 'newest');

        $query = $course->materials();
        if ($search) {
            foreach (array_slice(preg_split('/\s+/', $search), 0, 8) as $term) {
                $this->whereLikeAny($query, ['title', 'original_filename'], $term);
            }
        }
        $query->orderByRaw('pinned_at IS NULL')->orderByDesc('pinned_at');
        match ($sort) {
            'oldest' => $query->oldest(),
            'az' => $query->orderByRaw('COALESCE(title, original_filename) collate nocase asc'),
            default => $query->latest(),
        };

        $sectionCounts = $course->materials()
            ->selectRaw('section, count(*) as total')
            ->groupBy('section')
            ->pluck('total', 'section');
        $pinnedCounts = $course->materials()->whereNotNull('pinned_at')
            ->selectRaw('section, count(*) as total')
            ->groupBy('section')
            ->pluck('total', 'section');

        $materials = $query->get()->map(fn ($m) => [
            'id' => $m->id,
            'section' => $m->section,
            'displayName' => $m->displayName(),
            'original_filename' => $m->original_filename,
            'fileTypeLabel' => $m->fileTypeLabel(),
            'file_size' => $m->file_size,
            // Names given before the field was dropped still show.
            'uploader_name' => $m->uploader_name,
            'pinned' => $m->pinned_at !== null,
            'created_at_human' => $m->created_at->diffInSeconds() < 45
                ? 'just now'
                : $m->created_at->diffForHumans(short: true),
            'is_new' => $m->created_at->getTimestamp() > $newSince,
            'download_url' => $m->downloadUrl(),
            'preview_url' => $m->previewUrl(),
            'delete_url' => route('material.destroy', ['material' => $m->id, 'token' => 'owner']),
            'title' => $m->title,
        ]);

        return Inertia::render('CoursePage', [
            'workspace' => ['name' => $workspace->name, 'slug' => $workspace->slug],
            'course' => ['id' => $course->id, 'code' => $course->code, 'title' => $course->title, 'slug' => $course->slug],
            'isOwner' => $this->isOwner(),
            'storageFull' => $workspace->storageFull(),
            'canUpload' => ! $workspace->uploads_owner_only || $this->isOwner(),
            'storageUsed' => $storageUsed = $workspace->storageBytes(),
            'storageCap' => $storageCap = (int) config('noteshare.workspace_storage_bytes'),
            'storagePct' => $storageCap > 0 ? min(100, (int) round($storageUsed / $storageCap * 100)) : 0,
            'passphraseNeeded' => filled($workspace->upload_passphrase) && session($workspace->uploadUnlockKey()) !== true,
            'sections' => Material::SECTIONS,
            'sectionCounts' => $sectionCounts,
            'pinnedCounts' => $pinnedCounts,
            'maxPinned' => Material::MAX_PINNED_PER_SECTION,
            'materials' => $materials,
            'resultCount' => $course->materials()->count(),
            'search' => $search,
            'sort' => $sort,
        ]);
    }

    public function downloadSection(Request $request, string $workspaceSlug, string $slug, string $section)
    {
        abort_unless(isset(Material::SECTIONS[$section]), 404);

        $key = 'section_zip:'.ClientKey::of($request).':'.$this->workspace()->id;
        $anyKey = 'section_zip_any:'.ClientKey::of($request);
        if (RateLimiter::tooManyAttempts($key, 10) || RateLimiter::tooManyAttempts($anyKey, 20)) {
            abort(429, 'Too many section downloads. Try again in a few minutes.');
        }
        RateLimiter::hit($key, 600);
        RateLimiter::hit($anyKey, 3600);

        $course = Course::where('slug', $slug)->firstOrFail();

        $materials = $course->materials()
            ->where('section', $section)
            ->latest()
            ->get();

        abort_if($materials->isEmpty(), 404);

        $free = disk_free_space(sys_get_temp_dir());
        $needed = (int) $materials->sum('file_size') + 256 * 1024 * 1024;
        if ($free === false || $free < $needed) {
            abort(503, 'Downloads are busy right now. Try again in a few minutes.');
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'slipzip');
        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::OVERWRITE);

        $used = [];
        $added = 0;
        foreach ($materials as $material) {
            $realPath = Storage::disk('local')->path($material->stored_path);
            if (! is_file($realPath)) {
                continue;
            }

            $key = $material->downloadName();
            $name = $key;
            if (isset($used[$name])) {
                $ext = pathinfo($name, PATHINFO_EXTENSION);
                $base = pathinfo($name, PATHINFO_FILENAME);
                $name = $ext !== '' ? "{$base} ({$used[$name]}).{$ext}" : "{$base} ({$used[$name]})";
                $used[$key]++;
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

        return back()->with('notice', $count === 1 ? '1 file removed.' : "{$count} files removed.");
    }

    /** The uploader's Undo for a batch: only files whose manage_token they were handed. */
    public function undoUpload(Request $request, string $workspaceSlug, string $slug)
    {
        $this->workspace();
        $course = Course::where('slug', $slug)->firstOrFail();

        $tokens = array_values(array_filter(
            (array) $request->input('tokens', []),
            fn ($t) => is_string($t) && strlen($t) === 40,
        ));
        $materials = $tokens === []
            ? collect()
            : $course->materials()->whereIn('manage_token', array_slice($tokens, 0, 20))->get();

        abort_if($materials->isEmpty(), 403);

        foreach ($materials as $material) {
            Storage::disk('local')->delete($material->stored_path);
            $material->delete();
        }

        $count = $materials->count();

        return back()->with('notice', $count === 1 ? 'File removed.' : "{$count} files removed.");
    }

    public function pin(Request $request, string $workspaceSlug, string $slug, int $material)
    {
        $this->workspace();
        abort_unless($this->isOwner(), 403);

        $course = Course::where('slug', $slug)->firstOrFail();
        $material = $course->materials()->whereKey($material)->firstOrFail();

        $pin = $request->boolean('pinned');

        if ($pin && $material->pinned_at === null) {
            $pinnedHere = $course->materials()->where('section', $material->section)->whereNotNull('pinned_at')->count();
            if ($pinnedHere >= Material::MAX_PINNED_PER_SECTION) {
                return back()->withErrors(['pin' => 'Up to '.Material::MAX_PINNED_PER_SECTION.' pinned files per section. Unpin one first.']);
            }
        }

        $material->forceFill(['pinned_at' => $pin ? ($material->pinned_at ?? now()) : null])->save();

        return back()->with('notice', $pin ? 'Pinned to the top of '.Material::SECTIONS[$material->section] : 'Unpinned');
    }

    public function update(Request $request, string $workspaceSlug, string $slug, int $material)
    {
        $this->workspace();
        abort_unless($this->isOwner(), 403);

        $course = Course::where('slug', $slug)->firstOrFail();
        $material = $course->materials()->whereKey($material)->firstOrFail();

        $data = $request->validate([
            'name' => 'nullable|string|max:120',
            'section' => 'required|in:'.implode(',', array_keys(Material::SECTIONS)),
        ], [
            'name.max' => 'That name is too long. Keep it under 120 characters.',
        ]);

        $name = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($data['name'] ?? ''))));
        $material->title = $name === '' || $name === pathinfo($material->original_filename, PATHINFO_FILENAME) ? null : $name;

        $moved = $data['section'] !== $material->section;
        $unpinned = $moved && $material->pinned_at !== null
            && $course->materials()->where('section', $data['section'])->whereNotNull('pinned_at')->count() >= Material::MAX_PINNED_PER_SECTION;
        $material->section = $data['section'];
        if ($unpinned) {
            $material->pinned_at = null;
        }
        $material->save();

        $to = Material::SECTIONS[$material->section];

        return back()->with('notice', match (true) {
            $unpinned => "Moved to {$to} and unpinned. {$to} already has ".Material::MAX_PINNED_PER_SECTION.' pinned.',
            $moved => "Moved to {$to}",
            default => 'Saved',
        });
    }

    public function exitOwner(Request $request)
    {
        $workspace = $this->workspace();
        session()->forget($workspace->ownerSessionKey());

        return back();
    }

    public function report(Request $request, string $workspaceSlug, string $slug, int $material)
    {
        $course = Course::where('slug', $slug)->firstOrFail();

        $material = $course->materials()->whereKey($material)->firstOrFail();

        $key = 'report:'.$material->id.':'.ClientKey::of($request);
        $clientKey = 'report_any:'.ClientKey::of($request);
        if (RateLimiter::tooManyAttempts($key, 5) || RateLimiter::tooManyAttempts($clientKey, 20)) {
            return back()->with('reported', 'Thanks — this file has already been reported.');
        }
        RateLimiter::hit($key, 600);
        RateLimiter::hit($clientKey, 3600);

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

        if ($workspace->uploads_owner_only && ! $this->isOwner()) {
            return back()->withErrors(['files' => 'Only the board\'s owner can add files here.']);
        }

        $data = $request->validate([
            'section' => 'required|in:'.implode(',', array_keys(Material::SECTIONS)),
            'title' => 'nullable|string|max:120',
            'passphrase' => 'nullable|string',
            'files' => 'required|array|min:1|max:20',
            'files.*' => ['file', 'max:25600', 'mimes:pdf,docx,pptx,png,jpg,jpeg', 'mimetypes:application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,image/png,image/jpeg',
                function (string $attribute, mixed $file, \Closure $fail) {
                    $norm = fn (?string $e) => $e === 'jpeg' ? 'jpg' : strtolower((string) $e);
                    $named = $norm($file->getClientOriginalExtension());
                    if (! in_array($named, ['pdf', 'docx', 'pptx', 'png', 'jpg'], true) || $named !== $norm($file->guessExtension())) {
                        $fail('A file\'s name doesn\'t match what\'s inside it. Upload the original PDF, Word, PowerPoint or image file.');
                    }
                },
            ],
        ], [
            'files.required' => 'Choose at least one file to upload.',
            'files.min' => 'Choose at least one file to upload.',
            'files.max' => 'You can upload up to 20 files at once.',
            'files.*.mimes' => 'Unsupported file type. Use PDF, Word, PowerPoint, or an image (PNG/JPG).',
            'files.*.mimetypes' => 'Unsupported file type. Use PDF, Word, PowerPoint, or an image (PNG/JPG).',
            'files.*.max' => 'That file is too large — each file can be up to 25 MB.',
            'title.max' => 'That title is too long — keep it under 120 characters.',
        ]);

        $rlKey = 'upload:'.ClientKey::of($request);
        if (RateLimiter::tooManyAttempts($rlKey, 30)) {
            return back()->withErrors(['files' => 'Too many uploads in a short time. Please wait a few minutes and try again.']);
        }
        RateLimiter::hit($rlKey, 600);

        $free = disk_free_space(storage_path('app/private'));
        if ($free === false || $free < (int) config('noteshare.min_free_disk_bytes')) {
            return back()->withErrors(['files' => 'The site is at capacity — please try again later.']);
        }

        if (filled($workspace->upload_passphrase) && session($workspace->uploadUnlockKey()) !== true) {
            $key = 'upload_passphrase:'.$workspace->id.':'.ClientKey::of($request);
            $boardKey = 'upload_passphrase_board:'.$workspace->id;
            if (RateLimiter::tooManyAttempts($key, 5) || RateLimiter::tooManyAttempts($boardKey, 30)) {
                return back()->withErrors(['passphrase' => 'Too many attempts. Try again in a few minutes.']);
            }

            if (! $workspace->uploadPassphraseMatches($data['passphrase'] ?? null)) {
                RateLimiter::hit($key, 600);
                RateLimiter::hit($boardKey, 600);

                return back()->withErrors(['passphrase' => "That passphrase isn't right — ask your course rep."]);
            }

            RateLimiter::clear($key);
            session([$workspace->uploadUnlockKey() => true]);
        }

        $files = $request->file('files');

        $title = count($files) === 1 && isset($data['title']) ? strip_tags($data['title']) : null;
        $byOwner = $this->isOwner();

        $remaining = $workspace->storageRemaining();
        $filesLeft = $workspace->filesRemaining();
        $created = [];
        $skipped = 0;
        $blocked = 0;
        $duplicate = 0;
        $duplicateName = null;
        $seenHashes = $course->materials()->whereNotNull('content_hash')->pluck('content_hash')->flip();

        foreach ($files as $file) {
            $hash = hash_file('sha256', $file->getRealPath());

            // Refuse files the operator previously removed (exact-content match).
            if (BlockedUpload::where('content_hash', $hash)->exists()) {
                $blocked++;

                continue;
            }

            if ($seenHashes->has($hash)) {
                $duplicate++;
                $duplicateName ??= $course->materials()->where('content_hash', $hash)->value('title')
                    ?? $course->materials()->where('content_hash', $hash)->value('original_filename');

                continue;
            }

            $size = (int) $file->getSize();
            if ($size > $remaining || $filesLeft <= 0) {
                $skipped++;

                continue;
            }
            $remaining -= $size;
            $filesLeft--;
            $seenHashes->put($hash, true);

            $material = $course->materials()->create([
                'section' => $data['section'],
                'title' => $title,
                'original_filename' => $this->sanitizeFilename($file->getClientOriginalName()),
                'stored_path' => $file->store('materials', 'local'),
                'by_owner' => $byOwner,
                'manage_token' => Str::random(40),
                'download_token' => Str::random(40),
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
            // Several files: one Undo for the lot, by each file's own manage_token.
            'undoBatch' => $count > 1 ? [
                'url' => route('course.undo-upload', ['workspace' => $workspace->slug, 'slug' => $course->slug]),
                'tokens' => array_map(fn (Material $m) => $m->manage_token, $created),
            ] : null,
            // For "Tell the class": the message names what's new.
            'addedName' => $count === 1 ? $created[0]->displayName() : null,
            'addedCount' => $count,
        ]);
    }

    private function sanitizeFilename(string $name): string
    {
        // Drop any path components a client may have sent (basename only).
        $name = basename(str_replace('\\', '/', $name));
        // Remove control chars incl. CR/LF (header injection) and quotes.
        $name = preg_replace('/[\x00-\x1F\x7F"\\\\]+/', '', $name);
        $name = trim(preg_replace('/\s+/', ' ', $name));

        return $name !== '' ? mb_substr($name, 0, 200) : 'file';
    }
}
