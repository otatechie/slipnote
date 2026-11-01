<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Support\ClientKey;
use App\Support\RecentWorkspaces;
use App\Tenancy\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

abstract class Controller
{
    protected function ownerUnlockKey(Workspace $workspace): string
    {
        return 'unlock_owner:'.$workspace->id.':'.ClientKey::of(request());
    }

    // Per client only. A board-wide budget let anyone who knew the slug lock
    // the real owner out; the secret is 40 random characters, so the budget
    // only has to keep one client from running bcrypt in a loop.
    protected function ownerUnlockThrottled(Workspace $workspace): bool
    {
        return RateLimiter::tooManyAttempts($this->ownerUnlockKey($workspace), 5);
    }

    protected function ownerUnlockFailed(Workspace $workspace): void
    {
        RateLimiter::hit($this->ownerUnlockKey($workspace), 600);
    }

    /**
     * Redeems ?owner= on a board or course page: null when there's none, else
     * where to send the visitor. A wrong or refused link says so on the board,
     * where the owner-link box opens, instead of quietly showing a visitor's view.
     */
    protected function redeemOwnerQuery(Request $request, Workspace $workspace, string $back): ?RedirectResponse
    {
        $given = $request->query('owner');
        if (! is_string($given) || $given === '') {
            return null;
        }

        $board = route('courses.index', ['workspace' => $workspace->slug]);

        if ($this->ownerUnlockThrottled($workspace)) {
            return redirect($board)->withErrors(['ownerInput' => 'Too many wrong owner links from here. Try again in a few minutes.']);
        }

        if (! $workspace->verifyOwner($given)) {
            $this->ownerUnlockFailed($workspace);

            return redirect($board)->withErrors(['ownerInput' => 'That owner link isn’t right. Paste the whole link, or the secret after owner=.']);
        }

        RateLimiter::clear($this->ownerUnlockKey($workspace));
        session()->regenerate(); // anti-fixation on privilege change
        session([$workspace->ownerSessionKey() => $workspace->ownerSessionValue()]);

        return redirect($back)->withCookie(RecentWorkspaces::add($request, $workspace));
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
