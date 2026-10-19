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

        // Link previews for a course link name the course, not just the board
        // (see app.blade.php). Still no counts or filenames.
        view()->share('ogCourse', $course);

        // What landed since this browser last opened this course (or in the
        // last week, if it never has). Read before the cookie is bumped below,
        // so today's visit is measured against the previous one.
        $newSince = LastSeen::since($request, $course->id);
        Cookie::queue(LastSeen::mark($request, $course->id));
        if ($this->isOwner() && ($recent = RecentWorkspaces::touch($request, $workspace))) {
            Cookie::queue($recent);
        }

        $search = $this->searchInput($request);
        $sort = $request->input('sort', 'newest');

        $query = $course->materials();
        if ($search) {
            // Each word must match title or filename, so "past paper" finds
            // "past_papers_2023.pdf" — separators in filenames shouldn't hide
            // files from the natural query.
            // At most 8 words: each adds a clause.
            foreach (array_slice(preg_split('/\s+/', $search), 0, 8) as $term) {
                $this->whereLikeAny($query, ['title', 'original_filename'], $term);
            }
        }
        // Pinned files lead their section whatever the sort; the list groups
        // by section client-side, keeping this order within each.
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
        // From the whole course, not the filtered list, so the pin menu knows
        // a section is full even while a search hides some of its pins.
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
            // Shown in the row: two uploads can share a display name (both
            // "Lucid_by_WeOrg"), leaving nothing on screen to tell them apart.
            // Size also answers "is this worth it on mobile data?" before the tap.
            'file_size' => $m->file_size,
            // Names given before the field was dropped still show.
            'uploader_name' => $m->uploader_name,
            'pinned' => $m->pinned_at !== null,
            // "just now" reads better than "0 seconds ago" for fresh uploads.
            // Short units ("28m ago") keep the meta line from truncating on phones.
            'created_at_human' => $m->created_at->diffInSeconds() < 45
                ? 'just now'
                : $m->created_at->diffForHumans(short: true),
            'is_new' => $m->created_at->getTimestamp() > $newSince,
            'download_url' => $m->downloadUrl(),
            'preview_url' => $m->previewUrl(),
            // Owner delete goes through the session; the literal 'owner' is
            // never a valid token. The uploader's manage_url is deliberately
            // NOT here — it is the delete capability, and this array goes to
            // every visitor.
            'delete_url' => route('material.destroy', ['material' => $m->id, 'token' => 'owner']),
            'title' => $m->title,
        ]);

        return Inertia::render('CoursePage', [
            'workspace' => ['name' => $workspace->name, 'slug' => $workspace->slug],
            'course' => ['id' => $course->id, 'code' => $course->code, 'title' => $course->title, 'slug' => $course->slug],
            'isOwner' => $this->isOwner(),
            'storageFull' => $workspace->storageFull(),
            // False when the owner has locked uploads to themselves and this
            // visitor isn't the owner: no Add file anywhere on the page.
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

    /**
     * Stream every file in one section as a zip. Anonymous, like single
     * downloads — anyone with the board link can grab the whole section
     * (the real exam-time need: "all the past papers", not twelve clicks).
     * Built into a temp file then streamed and deleted after send.
     *
     * Rate-limited per client and board: each call materialises up to the
     * whole workspace cap (500 MB) in the temp dir before a byte is sent, and
     * the free-disk check only guards uploads. Ten a section per ten minutes
     * is generous for a class and useless for filling the disk.
     */
    public function downloadSection(Request $request, string $workspaceSlug, string $slug, string $section)
    {
        abort_unless(isset(Material::SECTIONS[$section]), 404);

        // 10 per board per 10 min, and 20 an hour across all boards: each zip
        // is built on disk, so one client fanning out over many boards could
        // otherwise fill the temp directory.
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

        // The temp directory is often its own small filesystem, which the
        // upload free-space check never looks at: leave the build room for
        // this zip plus 256 MB to spare, or refuse it for now.
        $free = disk_free_space(sys_get_temp_dir());
        $needed = (int) $materials->sum('file_size') + 256 * 1024 * 1024;
        if ($free === false || $free < $needed) {
            abort(503, 'Downloads are busy right now. Try again in a few minutes.');
        }

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

    /**
     * Pin a file to the top of its section, or unpin it. Owner only, and the
     * file is resolved through the scoped course, so it must be on this board.
     */
    public function pin(Request $request, string $workspaceSlug, string $slug, int $material)
    {
        $this->workspace();
        abort_unless($this->isOwner(), 403);

        $course = Course::where('slug', $slug)->firstOrFail();
        $material = $course->materials()->whereKey($material)->firstOrFail();

        $pin = $request->boolean('pinned');

        // A few, or pinning means nothing: pin everything and the top of the
        // section is just the list again. The menu says so before it gets
        // here; this catches a stale tab.
        if ($pin && $material->pinned_at === null) {
            $pinnedHere = $course->materials()->where('section', $material->section)->whereNotNull('pinned_at')->count();
            if ($pinnedHere >= Material::MAX_PINNED_PER_SECTION) {
                return back()->withErrors(['pin' => 'Up to '.Material::MAX_PINNED_PER_SECTION.' pinned files per section. Unpin one first.']);
            }
        }

        $material->forceFill(['pinned_at' => $pin ? ($material->pinned_at ?? now()) : null])->save();

        // A passing notice, not the upload receipt: the change is already in
        // the list, and the receipt sits at the top of the page, out of view
        // from a file pinned further down.
        return back()->with('notice', $pin ? 'Pinned to the top of '.Material::SECTIONS[$material->section] : 'Unpinned');
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

        // Throttle to blunt spam/notification floods: 5 reports per file and
        // client per 10 min, and 20 an hour per client across all files (per
        // file alone, one client could flood the operator's Telegram).
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

        // The owner locked uploads to themselves. The page hides Add file, so
        // this is a stale tab or a hand-made request; say why, don't 403.
        if ($workspace->uploads_owner_only && ! $this->isOwner()) {
            return back()->withErrors(['files' => 'Only the board\'s owner can add files here.']);
        }

        $data = $request->validate([
            'section' => 'required|in:'.implode(',', array_keys(Material::SECTIONS)),
            'title' => 'nullable|string|max:120',
            'passphrase' => 'nullable|string',
            'files' => 'required|array|min:1|max:20',
            // mimes checks the extension; mimetypes checks the real content
            // type (magic bytes via finfo), so a renamed executable — e.g.
            // virus.exe → notes.pdf — fails even though its extension passes.
            'files.*' => ['file', 'max:25600', 'mimes:pdf,docx,pptx,png,jpg,jpeg', 'mimetypes:application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,image/png,image/jpeg',
                // mimes/mimetypes read the content, not the name, so a PDF
                // named "slides.hta" passed and kept its name: a script for
                // whoever opened it. The name's extension must be allowed and
                // match what's inside.
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

        // Per-IP upload throttle: blunts automated abuse / mass dumping while
        // staying generous for a student uploading a semester's notes.
        $rlKey = 'upload:'.ClientKey::of($request);
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
            // 5 attempts per 10 min per board and client, so one client can't
            // lock a whole class out; 30 per board in all, since a passphrase
            // is human-chosen and guessable where an owner secret isn't.
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

        // A shared title only makes sense for a single file; with several
        // files each keeps its own filename.
        $title = count($files) === 1 && isset($data['title']) ? strip_tags($data['title']) : null;
        // No uploader name: the optional field was left blank on nearly every
        // file, so rows said "Anonymous" and nothing more. Whether the file
        // came from whoever runs the board is recorded instead. Not shown on
        // the row ("Owner" meant nothing to a classmate, and on a locked board
        // it was on every file; pinning says "this one matters" better), but
        // kept for the Telegram ping and for filtering later.
        $byOwner = $this->isOwner();

        // Soft per-workspace cap: track headroom across this batch so a single
        // request never overshoots. The host-disk check above is the hard
        // safety net; a tiny overshoot under concurrent uploads is acceptable.
        $remaining = $workspace->storageRemaining();
        // Hard file-count cap alongside the byte cap: a flood of tiny files
        // shouldn't exhaust inodes or the list UI while under the size limit.
        $filesLeft = $workspace->filesRemaining();
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
            // For "Tell the class": the message names what's new.
            'addedName' => $count === 1 ? $created[0]->displayName() : null,
            'addedCount' => $count,
        ]);
    }

    /**
     * Clean a client-supplied filename before it's stored and later echoed
     * into a Content-Disposition header on download. Strips directory parts
     * and control characters (which enable header injection / spoofing),
     * collapses whitespace, and guarantees a non-empty fallback.
     */
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
