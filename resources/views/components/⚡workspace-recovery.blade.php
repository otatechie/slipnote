<?php

use App\Mail\OwnerLinkRecovery;
use App\Models\Workspace;
use App\Tenancy\Tenancy;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('components.layouts.app')] class extends Component
{
    public Workspace $workspace;

    public string $email = '';

    /** Set once the request is processed — the response is deliberately the
     *  same whether or not the email matched (no enumeration oracle). */
    public bool $done = false;

    public function hydrate(): void
    {
        // POST /livewire/update bypasses the {workspace} route + middleware;
        // re-establish the tenant from the persisted workspace.
        if (isset($this->workspace)) {
            app(Tenancy::class)->set($this->workspace);
        }
    }

    public function mount(?string $workspace = null): void
    {
        // $workspace route segment accepted but unused — the resolved tenant
        // is the source of truth (binding it would inject the raw slug
        // string into the typed Workspace property).
        $this->workspace = app(Tenancy::class)->current();
    }

    public function requestRecovery(): void
    {
        $key = 'recover:'.$this->workspace->id;

        // Rate limit per workspace so the form can't be used to spam the
        // owner's inbox or brute-probe emails. 5 / 10 min.
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('email', 'Too many attempts. Try again later.');

            return;
        }
        RateLimiter::hit($key, 600);

        // Only act if the typed email matches the STORED one. Constant-time
        // check; returns false for "no recovery email set" too — we must not
        // branch differently (no enumeration).
        if ($this->workspace->recoveryEmailMatches($this->email)) {
            // Rotate: old link dies, new one is mailed. We never stored the
            // secret, so recovery = issue a fresh one.
            $newSecret = $this->workspace->rotateOwnerSecret();

            $ownerUrl = route('courses.index', ['workspace' => $this->workspace->slug])
                .'?owner='.$newSecret;

            // Send ONLY to the stored address, never the typed input.
            Mail::to($this->workspace->recovery_email)
                ->queue(new OwnerLinkRecovery($this->workspace->name, $ownerUrl));
        }

        // Identical outcome regardless of match.
        $this->reset('email');
        $this->done = true;
    }
};
?>

<div class="flex min-h-screen flex-col">
<div class="mx-auto w-full max-w-md flex-1 px-5 pt-20 pb-12">
    <header class="mb-7 text-center">
        <p class="mb-2 text-xs font-semibold uppercase tracking-[0.08em] text-neon">SlipNote</p>
        <h1 class="text-3xl font-bold tracking-tight text-ink">Recover owner access</h1>
        <p class="mx-auto mt-2 max-w-sm text-[15px] text-muted">
            For <span class="font-semibold text-ink">{{ $workspace->name }}</span>.
            If a recovery email was set for this board, we’ll send a fresh
            owner link there.
        </p>
    </header>

    @if ($done)
        <div class="rounded-2xl border border-sky bg-surface p-6 text-center shadow-sm">
            <p class="text-[15px] font-semibold text-ink">Check that inbox</p>
            <p class="mx-auto mt-2 max-w-sm text-[14px] text-muted">
                If that email is on file for this board, a new owner link is
                on its way. It may take a minute — and check spam. The
                previous owner link no longer works.
            </p>
            <a href="{{ route('courses.index', ['workspace' => $workspace->slug]) }}"
               wire:navigate
               class="mt-5 inline-block text-[13px] font-semibold text-neon hover:underline">
                ← Back to {{ $workspace->name }}
            </a>
        </div>
    @else
        <form wire:submit="requestRecovery"
              class="rounded-2xl border border-sky bg-surface p-6 shadow-sm">
            <label for="email" class="mb-1.5 block text-[13px] font-semibold text-ink">
                Recovery email
            </label>
            <input id="email" type="email" wire:model="email"
                   autocomplete="off"
                   placeholder="the email you set for this board"
                   class="w-full rounded-lg border border-sky bg-base px-3.5 py-3 text-[15px] text-ink placeholder:text-muted focus:border-neon focus:outline-2 focus:outline-neon">
            @error('email')
                <p role="alert" class="mt-2 text-[12px] text-muted">{{ $message }}</p>
            @enderror
            <button type="submit"
                    class="mt-4 w-full cursor-pointer rounded-lg bg-neon py-3.5 text-[15px] font-bold text-base transition hover:brightness-125">
                Send recovery link
            </button>
            <p class="mt-3 text-center text-[12px] text-muted/70">
                No recovery email was set? The owner link can’t be recovered —
                that’s the trade-off of no accounts.
            </p>
        </form>
    @endif
</div>

<footer class="mt-auto border-t border-sky/60 py-8">
    <div class="mx-auto max-w-md px-5 flex items-center justify-between gap-4">
        <p class="text-[13px] font-semibold text-neon">SlipNote</p>
        <p class="text-[13px] text-muted">Share notes, not stress &middot; {{ date('Y') }}</p>
    </div>
</footer>
</div>
