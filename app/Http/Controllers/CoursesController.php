<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Support\RecentWorkspaces;
use App\Tenancy\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CoursesController extends Controller
{
    private function workspace()
    {
        return app(Tenancy::class)->current();
    }

    private function isOwner(): bool
    {
        return session($this->workspace()->ownerSessionKey()) === true;
    }

    public function index(Request $request)
    {
        $workspace = $this->workspace();

        // Handle ?owner= URL param
        $given = $request->query('owner');
        if ($workspace->verifyOwner(is_string($given) ? $given : null)) {
            session()->regenerate(); // anti-fixation on privilege change

            session([$workspace->ownerSessionKey() => true]);

            return redirect()
                ->route('courses.index', ['workspace' => $workspace->slug])
                ->withCookie(RecentWorkspaces::add($request, $workspace));
        }

        $search = trim($request->input('search', ''));
        $sort = $request->input('sort', 'manual');

        $query = Course::withCount('materials')->withMax('materials', 'created_at');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")->orWhere('title', 'like', "%{$search}%");
            });
        }

        match ($sort) {
            'az' => $query->orderBy('code'),
            'active' => $query->orderByRaw('COALESCE(materials_max_created_at, courses.created_at) desc'),
            default => $query->orderBy('position'),
        };

        $used = $workspace->storageBytes();
        $cap = (int) config('noteshare.workspace_storage_bytes');

        return Inertia::render('CoursesPage', [
            'workspace' => ['name' => $workspace->name, 'slug' => $workspace->slug],
            'courses' => $query->get()->map(fn ($c) => [
                'id' => $c->id,
                'code' => $c->code,
                'title' => $c->title,
                'slug' => $c->slug,
                'position' => $c->position,
                'materials_count' => $c->materials_count,
                'materials_max_created_at' => $c->materials_max_created_at,
            ]),
            'totalCourses' => Course::count(),
            'isOwner' => $this->isOwner(),
            'recoveryAvailable' => ! in_array(config('mail.default'), ['log', 'array', null], true),
            'needsRecoveryEmail' => blank($workspace->recovery_email),
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
        $key = 'unlock_owner:'.$workspace->id;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['ownerInput' => 'Too many attempts. Try again in a few minutes.']);
        }

        $given = trim($request->input('ownerInput', ''));
        if (str_contains($given, 'owner=')) {
            parse_str((string) parse_url($given, PHP_URL_QUERY), $q);
            $given = $q['owner'] ?? $given;
        }

        if ($workspace->verifyOwner($given !== '' ? $given : null)) {
            RateLimiter::clear($key);
            session()->regenerate(); // anti-fixation on privilege change

            session([$workspace->ownerSessionKey() => true]);

            return redirect()
                ->route('courses.index', ['workspace' => $workspace->slug])
                ->withCookie(RecentWorkspaces::add($request, $workspace));
        }

        RateLimiter::hit($key, 600);

        return back()->withErrors(['ownerInput' => "That owner secret or link isn't right for this workspace."]);
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
            'code' => 'required|string|max:40',
            'title' => 'required|string|max:120',
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
            'code' => 'required|string|max:40',
            'title' => 'required|string|max:120',
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
