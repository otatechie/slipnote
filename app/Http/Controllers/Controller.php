<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Support\ClientKey;
use App\Tenancy\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

abstract class Controller
{
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

    protected function searchInput(Request $request): string
    {
        $value = $request->input('search');

        return is_string($value) ? mb_substr(trim($value), 0, 100) : '';
    }

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
