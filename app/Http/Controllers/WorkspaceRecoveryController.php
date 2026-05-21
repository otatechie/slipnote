<?php

namespace App\Http\Controllers;

use App\Mail\OwnerLinkRecovery;
use App\Tenancy\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;

class WorkspaceRecoveryController extends Controller
{
    public function show()
    {
        $workspace = app(Tenancy::class)->current();

        return Inertia::render('WorkspaceRecovery', [
            'workspace' => ['name' => $workspace->name, 'slug' => $workspace->slug],
        ]);
    }

    public function store(Request $request)
    {
        $workspace = app(Tenancy::class)->current();
        $key = 'recovery:'.$workspace->id;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->with('done', true);
        }

        $data = $request->validate(['email' => 'required|email:rfc']);
        RateLimiter::hit($key, 600);

        if ($workspace->recoveryEmailMatches($data['email'])) {
            $secret = $workspace->rotateOwnerSecret();
            $ownerUrl = route('courses.index', ['workspace' => $workspace->slug]).'?owner='.$secret;
            Mail::to($workspace->recovery_email)->queue(new OwnerLinkRecovery($workspace->name, $ownerUrl));
        }

        return back()->with('done', true);
    }
}
