<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Support\RecentWorkspaces;
use App\Tenancy\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

abstract class Controller
{
    /** Shared with the POST unlock form: wrong guesses from either path count. */
    protected function ownerUnlockKey(Workspace $workspace): string
    {
        return 'unlock_owner:'.$workspace->id;
    }

    /**
     * Redeem a ?owner= link on a GET. Returns true when the session was just
     * unlocked (caller should redirect to the clean URL).
     *
     * Rate-limited like the POST form. Without this, every unauthenticated
     * GET carrying ?owner= ran a bcrypt check (~250 ms of CPU at 12 rounds)
     * with nothing counting the failures.
     */
    protected function redeemOwnerQuery(Request $request, Workspace $workspace): bool
    {
        $given = $request->query('owner');
        if (! is_string($given) || $given === '') {
            return false;
        }

        $key = $this->ownerUnlockKey($workspace);
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return false;
        }

        if (! $workspace->verifyOwner($given)) {
            RateLimiter::hit($key, 600);

            return false;
        }

        RateLimiter::clear($key);
        session()->regenerate(); // anti-fixation on privilege change
        session([$workspace->ownerSessionKey() => true]);

        return true;
    }

    /** The workspace resolved for the current request. */
    protected function workspace(): Workspace
    {
        return app(Tenancy::class)->current();
    }

    /** Whether the current session has owner access to the resolved workspace. */
    protected function isOwner(): bool
    {
        return session($this->workspace()->ownerSessionKey()) === true;
    }
}
