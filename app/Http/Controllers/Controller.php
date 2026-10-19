<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Support\ClientKey;
use App\Tenancy\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

abstract class Controller
{
    /*
     * Owner unlock limits, shared by the POST form and ?owner= links. Per board
     * AND client: keyed on the board alone, anyone could send five bad guesses
     * every ten minutes and lock the real owner out of their own link. The
     * secret is 40 random characters, so the limits only bound bcrypt work;
     * the per-board ceiling caps that across many clients.
     */
    protected function ownerUnlockKey(Workspace $workspace): string
    {
        return 'unlock_owner:'.$workspace->id.':'.ClientKey::of(request());
    }

    protected function ownerUnlockThrottled(Workspace $workspace): bool
    {
        return RateLimiter::tooManyAttempts($this->ownerUnlockKey($workspace), 5)
            || RateLimiter::tooManyAttempts('unlock_owner_board:'.$workspace->id, 100);
    }

    protected function ownerUnlockFailed(Workspace $workspace): void
    {
        RateLimiter::hit($this->ownerUnlockKey($workspace), 600);
        RateLimiter::hit('unlock_owner_board:'.$workspace->id, 600);
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

        if ($this->ownerUnlockThrottled($workspace)) {
            return false;
        }

        if (! $workspace->verifyOwner($given)) {
            $this->ownerUnlockFailed($workspace);

            return false;
        }

        RateLimiter::clear($this->ownerUnlockKey($workspace));
        session()->regenerate(); // anti-fixation on privilege change
        session([$workspace->ownerSessionKey() => $workspace->ownerSessionValue()]);

        return true;
    }

    /**
     * A search box value: text only (search[]=x was a 500), at most 100
     * characters, so a pasted essay can't become a huge query.
     */
    protected function searchInput(Request $request): string
    {
        $value = $request->input('search');

        return is_string($value) ? mb_substr(trim($value), 0, 100) : '';
    }

    /**
     * Match any of $columns against $term as a literal substring: % and _
     * are escaped, so they don't act as wildcards.
     */
    protected function whereLikeAny($query, array $columns, string $term): void
    {
        $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
        $query->where(function ($q) use ($columns, $pattern) {
            foreach ($columns as $column) {
                $q->orWhereRaw("{$column} LIKE ? ESCAPE '\\'", [$pattern]);
            }
        });
    }

    /** The workspace resolved for the current request. */
    protected function workspace(): Workspace
    {
        return app(Tenancy::class)->current();
    }

    /** Whether the current session has owner access to the resolved workspace. */
    protected function isOwner(): bool
    {
        return $this->workspace()->sessionIsOwner();
    }
}
