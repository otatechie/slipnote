<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Support\LastSeen;
use App\Support\RecentWorkspaces;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CoursesController extends Controller
{
    public function index(Request $request)
    {
        $workspace = $this->workspace();

        if ($this->redeemOwnerQuery($request, $workspace)) {
            return redirect()
                ->route('courses.index', ['workspace' => $workspace->slug])
                ->withCookie(RecentWorkspaces::add($request, $workspace));
        }

        $search = $this->searchInput($request);
        $sort = $request->input('sort', 'manual');

        $seen = LastSeen::read($request);
        $query = Course::withCount([
            'materials',
            'materials as new_count' => function ($q) use ($seen) {
                $q->where(function ($q) use ($seen) {
                    foreach ($seen as $courseId => $ts) {
                        $q->orWhere(fn ($q) => $q->where('course_id', $courseId)
                            ->where('created_at', '>', date('Y-m-d H:i:s', $ts)));
                    }
                    $q->orWhere(fn ($q) => $q->whereNotIn('course_id', array_keys($seen))
                        ->where('created_at', '>', date('Y-m-d H:i:s', LastSeen::firstLook())));
                });
            },
        ])->withMax('materials', 'created_at');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $this->whereLikeAny($q, ['code', 'title'], $search);
            });
        }

        match ($sort) {
            'az' => $query->orderBy('code'),
            'active' => $query->orderByRaw('COALESCE(materials_max_created_at, courses.created_at) desc'),
            default => $query->orderBy('position'),
        };

        $used = $workspace->storageBytes();
        $cap = (int) config('noteshare.workspace_storage_bytes');
        $isOwner = $this->isOwner();
        if ($isOwner && ($recent = RecentWorkspaces::touch($request, $workspace))) {
            Cookie::queue($recent);
        }

        return Inertia::render('CoursesPage', [
            'workspace' => ['name' => $workspace->name, 'slug' => $workspace->slug],
            'courses' => $query->get()->map(fn ($c) => [
                'id' => $c->id,
                'code' => $c->code,
                'title' => $c->title,
                'slug' => $c->slug,
                'position' => $c->position,
                'materials_count' => $c->materials_count,
                'new_count' => $c->new_count,
                'materials_max_created_at' => $c->materials_max_created_at,
            ]),
            'totalCourses' => Course::count(),
            'totalFiles' => $workspace->materials()->count(),
            'isOwner' => $isOwner,
            'uploadsOwnerOnly' => (bool) $workspace->uploads_owner_only,
            // Owner-only: a message from the operator, and when it was left.
            'operatorNote' => $isOwner && filled($workspace->operator_note)
                ? ['text' => $workspace->operator_note, 'at' => $workspace->operator_note_at?->toIso8601String()]
                : null,
            'recoveryAvailable' => ! in_array(config('mail.default'), ['log', 'array', null], true),
            'needsRecoveryEmail' => blank($workspace->recovery_email),
            'currentRecoveryEmail' => $isOwner ? $workspace->recovery_email : null,
            'storageUsed' => $used,
            'storageCap' => $cap,
            'storagePct' => $cap > 0 ? min(100, (int) round($used / $cap * 100)) : 0,
            'search' => $search,
            'sort' => $sort,
        ]);
    }

    public function unlock(Request $request)
    {
        $workspace = $this->workspace();

        if ($this->ownerUnlockThrottled($workspace)) {
            return back()->withErrors(['ownerInput' => 'Too many attempts. Try again in a few minutes.']);
        }

        $given = is_string($request->input('ownerInput')) ? trim($request->input('ownerInput')) : '';
        if ($given === '') {
            return back()->withErrors(['ownerInput' => 'Paste your owner link or secret.']);
        }
        if (str_contains($given, 'owner=')) {
            parse_str((string) parse_url($given, PHP_URL_QUERY), $q);
            $given = $q['owner'] ?? $given;
        }

        if ($workspace->verifyOwner($given)) {
            RateLimiter::clear($this->ownerUnlockKey($workspace));
            session()->regenerate(); // anti-fixation on privilege change

            session([$workspace->ownerSessionKey() => $workspace->ownerSessionValue()]);

            return redirect()
                ->route('courses.index', ['workspace' => $workspace->slug])
                ->withCookie(RecentWorkspaces::add($request, $workspace));
        }

        $this->ownerUnlockFailed($workspace);

        return back()->withErrors(['ownerInput' => "That owner secret or link isn't right for this board — check you copied the whole thing."]);
    }

    public function resetOwnerLink(Request $request)
    {
        $workspace = $this->workspace();
        abort_unless($this->isOwner(), 403);

        [$secret, $hash] = $workspace->draftOwnerSecretRotation();
        $workspace->forceFill(['owner_secret_hash' => $hash])->save();

        session()->regenerate(); // anti-fixation on privilege change
        session([$workspace->ownerSessionKey() => $workspace->ownerSessionValue()]);

        return redirect()
            ->route('start')
            ->with([
                'ownerReset' => true,
                'createdName' => $workspace->name,
                'createdUrl' => route('courses.index', ['workspace' => $workspace->slug]),
                'ownerUrl' => route('courses.index', ['workspace' => $workspace->slug]).'?owner='.$secret,
            ]);
    }

    public function destroy(Request $request)
    {
        $workspace = $this->workspace();
        abort_unless($this->isOwner(), 403);

        $typed = Str::lower(trim((string) $request->input('confirmName', '')));
        if ($typed !== Str::lower(trim($workspace->name))) {
            return back()->withErrors(['confirmName' => 'Type the board\'s name exactly as it appears to delete it.']);
        }

        $name = $workspace->name;
        session()->forget($workspace->ownerSessionKey());
        $workspace->purge();

        return redirect()->route('start')
            ->with('boardDeleted', $name)
            ->withCookie(RecentWorkspaces::remove($request, $workspace->slug));
    }

    /** Who can add files: anyone with the link, or only the owner. */
    public function setUploadAccess(Request $request)
    {
        $workspace = $this->workspace();
        abort_unless($this->isOwner(), 403);

        $data = $request->validate(['ownerOnly' => 'required|boolean']);
        $workspace->forceFill(['uploads_owner_only' => (bool) $data['ownerOnly']])->save();

        return back();
    }

    public function saveRecoveryEmail(Request $request)
    {
        $workspace = $this->workspace();
        abort_unless($this->isOwner(), 403);

        $email = trim($request->input('recoveryEmail', ''));
        if ($email !== '') {
            $request->validate(['recoveryEmail' => 'email:rfc'], [], ['recoveryEmail' => 'email']);
        }

        $workspace->setRecoveryEmail($email);

        return back()->with('recoverySaved', $email === '' ? 'Recovery email removed.' : 'Recovery email saved.');
    }

    public function reorder(Request $request)
    {
        $workspace = $this->workspace();
        abort_unless($this->isOwner(), 403);

        $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer'])['ids'];

        // Only update courses that belong to this workspace
        $allowed = Course::whereIn('id', $ids)->pluck('workspace_id', 'id');

        foreach ($ids as $position => $id) {
            if (($allowed[$id] ?? null) === $workspace->id) {
                Course::where('id', $id)->update(['position' => $position]);
            }
        }

        return response()->noContent();
    }

    public function store(Request $request)
    {
        $workspace = $this->workspace();
        abort_unless($this->isOwner(), 403);

        $data = $request->validate([
            'code' => [
                'required', 'string', 'max:40', 'regex:/[a-zA-Z]/',
                function ($attr, $value, $fail) {
                    $exists = Course::whereRaw('lower(trim(code)) = ?', [mb_strtolower(trim($value))])->exists();
                    if ($exists) {
                        $fail("This board already has a course called “{$value}”. Pick a different code.");
                    }
                },
            ],
            'title' => 'required|string|max:120',
        ], [
            'code.regex' => 'A course code should include letters, like “PHYS 101”.',
        ]);

        $base = Str::slug($data['code']) ?: 'course';
        $slug = $base;
        $n = 2;
        while (Course::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$n}";
            $n++;
        }

        $nextPosition = Course::max('position') + 1;

        $course = Course::create([
            'code' => strip_tags($data['code']),
            'title' => strip_tags($data['title']),
            'slug' => $slug,
            'position' => $nextPosition,
        ]);

        return redirect()->route('course.show', [
            'workspace' => $workspace->slug,
            'slug' => $course->slug,
        ])->with('created', "\u{201C}{$course->code}\u{201D} created.");
    }

    public function update(Request $request, string $workspaceSlug, string $slug)
    {
        $workspace = $this->workspace();
        abort_unless($this->isOwner(), 403);

        // WorkspaceScope constrains this to the current workspace.
        $course = Course::where('slug', $slug)->firstOrFail();

        $data = $request->validate([
            'code' => [
                'required', 'string', 'max:40', 'regex:/[a-zA-Z]/',
                function ($attr, $value, $fail) use ($course) {
                    // Unique within this board, ignoring the course being edited.
                    $exists = Course::whereRaw('lower(trim(code)) = ?', [mb_strtolower(trim($value))])
                        ->whereKeyNot($course->id)
                        ->exists();
                    if ($exists) {
                        $fail("This board already has a course called “{$value}”. Pick a different code.");
                    }
                },
            ],
            'title' => 'required|string|max:120',
        ], [
            'code.regex' => 'A course code should include letters, like “PHYS 101”.',
        ]);

        // Slug is left untouched: classmates' shared /c/{slug} links stay valid.
        $course->update([
            'code' => strip_tags($data['code']),
            'title' => strip_tags($data['title']),
        ]);

        return redirect()
            ->route('courses.index', ['workspace' => $workspace->slug])
            ->with('created', "\u{201C}{$course->code}\u{201D} updated.");
    }
}
