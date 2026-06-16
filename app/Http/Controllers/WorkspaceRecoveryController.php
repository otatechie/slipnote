<?php

namespace App\Http\Controllers;

use App\Mail\OwnerLinkRecovery;
use App\Tenancy\Tenancy;
use Throwable;
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
            [$secret, $hash] = $workspace->draftOwnerSecretRotation();
            $ownerUrl = route('courses.index', ['workspace' => $workspace->slug]).'?owner='.$secret;

            try {
                Mail::to($workspace->recovery_email)->send(new OwnerLinkRecovery($workspace->name, $ownerUrl));
                $workspace->forceFill(['owner_secret_hash' => $hash])->save();
            } catch (Throwable $e) {
                report($e);
            }
        }

        return back()->with('done', true);
    }
}
