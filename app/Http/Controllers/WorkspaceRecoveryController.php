<?php

namespace App\Http\Controllers;

use App\Mail\OwnerLinkRecovery;
use App\Support\ClientKey;
use App\Support\RecentWorkspaces;
use App\Tenancy\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Throwable;

class WorkspaceRecoveryController extends Controller
{
    /** How long the mailed link stays valid. */
    private const REDEEM_TTL_SECONDS = 3600;

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
        $key = 'recovery:'.$workspace->id.':'.ClientKey::of($request);
        $boardKey = 'recovery_board:'.$workspace->id;

        if (RateLimiter::tooManyAttempts($key, 5) || RateLimiter::tooManyAttempts($boardKey, 50)) {
            return back()->with('done', true);
        }

        $data = $request->validate(['email' => 'required|email:rfc']);
        RateLimiter::hit($key, 600);
        RateLimiter::hit($boardKey, 600);

        $mailOn = ! in_array(config('mail.default'), ['log', 'array', null], true);

        if ($mailOn && $workspace->recoveryEmailMatches($data['email'])) {
            $nonce = Str::random(40);
            Cache::put(self::redeemKey($nonce), $workspace->id, self::REDEEM_TTL_SECONDS);

            $restoreUrl = URL::temporarySignedRoute(
                'workspace.recover.redeem',
                now()->addSeconds(self::REDEEM_TTL_SECONDS),
                ['workspace' => $workspace->slug, 'nonce' => $nonce],
            );

            try {
                Mail::to($workspace->recovery_email)->send(new OwnerLinkRecovery($workspace->name, $restoreUrl));
            } catch (Throwable $e) {
                Cache::forget(self::redeemKey($nonce));
                report($e);
            }
        }

        return back()->with('done', true);
    }

    public function redeem(Request $request, string $workspaceSlug, string $nonce)
    {
        $workspace = app(Tenancy::class)->current();

        if (Cache::pull(self::redeemKey($nonce)) !== $workspace->id) {
            return redirect()
                ->route('workspace.recover', ['workspace' => $workspace->slug])
                ->withErrors(['email' => 'That link has expired or was already used. Request a new one.']);
        }

        [$secret, $hash] = $workspace->draftOwnerSecretRotation();
        $workspace->forceFill(['owner_secret_hash' => $hash])->save();

        session()->regenerate(); // anti-fixation on privilege change
        session([$workspace->ownerSessionKey() => $workspace->ownerSessionValue()]);

        return redirect()
            ->route('start')
            ->withCookie(RecentWorkspaces::add($request, $workspace))
            ->with([
                'recovered' => true,
                'createdName' => $workspace->name,
                'createdUrl' => route('courses.index', ['workspace' => $workspace->slug]),
                'ownerUrl' => route('courses.index', ['workspace' => $workspace->slug]).'?owner='.$secret,
            ]);
    }

    private static function redeemKey(string $nonce): string
    {
        return 'recovery_redeem:'.$nonce;
    }
}
