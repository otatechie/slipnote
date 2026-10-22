<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Support\ClientKey;
use App\Support\RecentWorkspaces;
use App\Support\Referral;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Inertia\Inertia;

class WorkspacesController extends Controller
{
    public function index(Request $request)
    {
        $recent = RecentWorkspaces::read($request);
        $boards = Workspace::whereIn('slug', array_column($recent, 'slug'))
            ->withCount(['courses', 'materials'])
            ->get()
            ->keyBy('slug');

        return Inertia::render('WorkspacesLanding', [
            'recent' => collect($recent)
                ->filter(fn ($item) => $boards->has($item['slug']))
                ->map(fn ($item) => [
                    'slug' => $item['slug'],
                    'name' => $boards[$item['slug']]->name,
                    'courses' => $boards[$item['slug']]->courses_count,
                    'files' => $boards[$item['slug']]->materials_count,
                ])
                ->values(),
        ]);
    }

    public function forget(Request $request)
    {
        $slug = (string) $request->input('slug', '');

        return back()->withCookie(RecentWorkspaces::remove($request, $slug));
    }

    public function store(Request $request)
    {
        $rlKey = 'workspace_create:'.ClientKey::of($request);
        if (RateLimiter::tooManyAttempts($rlKey, 10)) {
            return back()->withErrors(['name' => 'Too many workspaces created from here recently. Try again later.']);
        }

        $data = $request->validate(['name' => 'required|string|min:2|max:60']);
        $clean = strip_tags($data['name']);
        $slug = Str::slug($clean);

        if ($slug === '') {
            return back()->withErrors(['name' => 'Use some letters or numbers — "'.$clean.'" can\'t become a link.']);
        }

        Workspace::pruneAbandonedDaily();
        [$workspace, $secret] = Workspace::provision($clean);
        RateLimiter::hit($rlKey, 3600); // count only successful creations

        if (($referrer = Referral::read($request)) !== null) {
            $workspace->forceFill(['referrer' => $referrer])->save();
        }

        session()->regenerate();
        session([$workspace->ownerSessionKey() => $workspace->ownerSessionValue()]);

        return back()
            ->withCookie(RecentWorkspaces::add($request, $workspace))
            ->withCookie(Referral::forget())
            ->with([
                'createdName' => $workspace->name,
                'createdUrl' => route('courses.index', ['workspace' => $workspace->slug]),
                'ownerUrl' => route('courses.index', ['workspace' => $workspace->slug]).'?owner='.$secret,
            ]);
    }

    public function open(Request $request)
    {
        $typed = is_string($request->input('openLink')) ? trim($request->input('openLink')) : '';
        if ($typed === '') {
            return back()->withErrors(['openLink' => 'Paste the board\'s link.']);
        }

        $key = 'open_board:'.ClientKey::of($request);
        if (RateLimiter::tooManyAttempts($key, 20)) {
            return back()->withErrors(['openLink' => 'Too many tries. Wait a few minutes, or open the link directly.']);
        }

        $slug = self::slugFromLink($typed);
        $workspace = $slug !== '' ? Workspace::where('slug', $slug)->first() : null;

        if (! $workspace) {
            RateLimiter::hit($key, 600);

            return back()->withErrors(['openLink' => "That link doesn't match a board. Check you copied all of it."]);
        }

        return redirect()->route('courses.index', ['workspace' => $workspace->slug]);
    }

    /** "https://slipnote.co/econ-200-k7q2m9/c/x", "slipnote.co/econ-200-k7q2m9" or "econ-200-k7q2m9". */
    public static function slugFromLink(string $typed): string
    {
        if (str_contains($typed, '/')) {
            $path = (string) parse_url(str_contains($typed, '://') ? $typed : 'https://'.ltrim($typed, '/'), PHP_URL_PATH);
            $typed = explode('/', trim($path, '/'))[0] ?? '';
        }

        return strtolower(trim($typed));
    }
}
