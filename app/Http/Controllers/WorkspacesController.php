<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Support\RecentWorkspaces;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class WorkspacesController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('WorkspacesLanding', [
            'recent' => RecentWorkspaces::read($request),
        ]);
    }

    public function forget(Request $request)
    {
        $slug = (string) $request->input('slug', '');

        return back()->withCookie(RecentWorkspaces::remove($request, $slug));
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|min:2|max:60']);
        $clean = strip_tags($data['name']);
        $slug = Str::slug($clean);

        if ($slug === '') {
            return back()->withErrors(['name' => 'Use some letters or numbers — "'.$clean.'" can\'t become a link.']);
        }

        if (Workspace::where('slug', $slug)->exists()) {
            return back()->withErrors(['name' => 'A workspace with this name already exists — pick a different name.']);
        }

        [$workspace, $secret] = Workspace::provision($clean);

        // The creator owns it; unlock the owner session immediately so they
        // don't have to re-paste the secret on the next page. Regenerate the
        // session ID on every privilege change (anti-fixation).
        session()->regenerate();
        session([$workspace->ownerSessionKey() => true]);

        return back()
            ->withCookie(RecentWorkspaces::add($request, $workspace))
            ->with([
                'createdName' => $workspace->name,
                'createdUrl' => route('courses.index', ['workspace' => $workspace->slug]),
                'ownerUrl' => route('courses.index', ['workspace' => $workspace->slug]).'?owner='.$secret,
            ]);
    }

    public function open(Request $request)
    {
        $typed = trim($request->input('openName', ''));

        if ($typed === '') {
            return back()->withErrors(['openName' => 'Type the workspace name, or use the link you saved.']);
        }

        $slug = Str::slug($typed);
        $workspace = $slug !== '' ? Workspace::where('slug', $slug)->first() : null;

        if (! $workspace) {
            return back()->withErrors(['openName' => "Couldn't find \"{$typed}\". The link you saved is the surest way in."]);
        }

        return redirect()->route('courses.index', ['workspace' => $workspace->slug]);
    }
}
