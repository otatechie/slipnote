<?php

use App\Models\Workspace;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('components.layouts.app')]
#[Title('Create your course board')]
class extends Component
{
    // Create a workspace (the primary action)
    public string $name = '';

    // Open an existing workspace by its human name (recovery path —
    // most people arrive via their saved link, not by typing here)
    public string $openName = '';

    // One-time receipt after creating (the only place the owner link shows)
    public ?string $createdName = null;
    public ?string $createdUrl = null;
    public ?string $ownerUrl = null;

    /** Live preview of the URL the name will become, so the outcome is
     *  predictable before the user commits. */
    public function slugPreview(): string
    {
        return Str::slug(trim($this->name));
    }

    public function create(): void
    {
        $data = $this->validate(['name' => 'required|string|min:2|max:60']);

        // Validate against the *stripped* name — that's what provision()
        // actually stores and slugifies.
        $clean = strip_tags($data['name']);
        $slug = Str::slug($clean);

        // 1. The name must yield a usable URL slug. "!!!" / pure punctuation
        //    slugify to "" → an unfindable workspace. Reject with guidance.
        if ($slug === '') {
            $this->addError('name', 'Use some letters or numbers — “'.$clean.'” can’t become a link.');

            return;
        }

        // 2. Names are unique by slug. Because the app lets people FIND a
        //    workspace by name, two names that slugify the same (e.g.
        //    "CS Masters 2026" vs "cs-masters 2026") would silently send
        //    users to the wrong board. Reject the collision instead.
        if (Workspace::where('slug', $slug)->exists()) {
            $this->addError('name', 'A workspace with this name already exists — pick a different name.');

            return;
        }

        [$workspace, $secret] = Workspace::provision($clean);

        // Shown exactly once. The plaintext secret is never stored or
        // re-displayed — same one-time-capability pattern as the uploader
        // delete receipt.
        $this->createdName = $workspace->name;
        $this->createdUrl = route('courses.index', ['workspace' => $workspace->slug]);
        $this->ownerUrl = $this->createdUrl.'?owner='.$secret;

        $this->reset('name');
    }

    /** Leave the receipt and enter the new workspace. The blocking
     *  "I saved it" confirmation is enforced client-side on the receipt;
     *  this just navigates. */
    public function proceed(): void
    {
        if ($this->createdUrl) {
            $this->redirect($this->createdUrl, navigate: true);
        }
    }

    public function open(): void
    {
        // Accept the human name ("CS Masters 2026") — slugify it the same
        // way provisioning did, so the user never needs to know what a slug
        // is or type it exactly. Recognition over recall.
        $typed = trim($this->openName);

        if ($typed === '') {
            $this->addError('openName', 'Type the workspace name, or use the link you saved.');

            return;
        }

        $slug = Str::slug($typed);
        $workspace = $slug !== '' ? Workspace::where('slug', $slug)->first() : null;

        if (! $workspace) {
            $this->addError('openName', "Couldn’t find “{$typed}”. The link you saved is the surest way in.");

            return;
        }

        $this->redirect(route('courses.index', ['workspace' => $workspace->slug]), navigate: true);
    }
};
?>

<div class="flex min-h-screen flex-col">
<div class="mx-auto w-full max-w-md flex-1 px-5 pt-20 pb-12">
    <header class="mb-7 text-center">
        <p class="mb-2 text-xs font-semibold uppercase tracking-[0.08em] text-neon">SlipNote</p>
        @if ($ownerUrl)
            <h1 class="text-3xl font-bold tracking-tight text-ink">Save your owner link</h1>
            <p class="mx-auto mt-2 max-w-sm text-[15px] text-muted">
                Your board is created. Keep the link below — it’s shown only once.
            </p>
        @else
            <h1 class="text-3xl font-bold tracking-tight text-ink">Create your course board</h1>
            <p class="mx-auto mt-2 max-w-sm text-[15px] text-muted">
                Name it, get a link, share it with your classmates. No account, no password.
            </p>
        @endif
    </header>

    @if ($ownerUrl)
        {{-- One-time owner receipt — takes over the screen. The owner link
             is shown ONCE and is unrecoverable, so leaving is gated behind
             an explicit "I saved it" confirmation (option 1), with copy and
             download to make saving frictionless (option 2). --}}
        <div x-data="{ saved: false, copied: false,
                       copy() {
                           const text = @js($ownerUrl);
                           const done = () => {
                               this.copied = true; this.saved = true;
                               setTimeout(() => this.copied = false, 2000);
                           };
                           // navigator.clipboard is undefined on insecure
                           // origins (plain HTTP). Fall back to execCommand.
                           if (navigator.clipboard && window.isSecureContext) {
                               navigator.clipboard.writeText(text).then(done).catch(() => this.legacyCopy(text, done));
                           } else {
                               this.legacyCopy(text, done);
                           }
                       },
                       legacyCopy(text, done) {
                           const ta = document.createElement('textarea');
                           ta.value = text;
                           ta.style.position = 'fixed'; ta.style.opacity = '0';
                           document.body.appendChild(ta);
                           ta.select();
                           try { document.execCommand('copy'); done(); }
                           catch (e) { /* user can still select the link manually */ }
                           document.body.removeChild(ta);
                       },
                       download() {
                           const workspaceName = @js($createdName);
                           const ownerUrl = @js($ownerUrl);
                           const createdUrl = @js($createdUrl);
                           const filename = @js(Str::slug($createdName).'-owner-link.txt');
                           const body = `SlipNote owner link for "${workspaceName}"\n\n`
                               + `OWNER (keep private - controls the workspace):\n${ownerUrl}\n\n`
                               + `SHARE WITH CLASSMATES:\n${createdUrl}\n`;
                           const a = document.createElement('a');
                           a.href = URL.createObjectURL(new Blob([body], {type:'text/plain'}));
                           a.download = filename;
                           a.click(); URL.revokeObjectURL(a.href);
                           this.saved = true;
                       } }"
             class="rounded-2xl border border-neon/40 bg-neon/10 p-6">
            <p class="text-[15px] font-bold text-neon">“{{ $createdName }}” is ready 🎉</p>
            <p class="mt-1.5 text-[13px] text-ink">
                <span class="font-semibold">Save your owner link — shown once,
                not recoverable.</span> It controls this workspace.
            </p>

            {{-- Full link, wrapped (not truncated): the one unrecoverable
                 string in the app — the user must see all of it. --}}
            <p onclick="window.getSelection().selectAllChildren(this)"
               class="mt-4 w-full cursor-text break-all rounded-lg border border-sky bg-base px-3 py-2.5 font-mono text-[12px] leading-relaxed text-ink select-all">{{ $ownerUrl }}</p>

            <div class="mt-2.5 flex gap-2">
                <button type="button" @click="copy()"
                        class="h-9 flex-1 cursor-pointer rounded-lg bg-neon text-[13px] font-bold text-base transition hover:brightness-125">
                    <span x-show="!copied">Copy link</span>
                    <span x-show="copied" x-cloak>Copied ✓</span>
                </button>
                <button type="button" @click="download()"
                        class="h-9 flex-1 cursor-pointer rounded-lg border border-neon/50 text-[13px] font-semibold text-neon transition hover:bg-neon/10">
                    Download .txt
                </button>
            </div>

            <p class="mt-3 text-[12px] text-muted">
                Classmates only need
                <a href="{{ $createdUrl }}" class="font-semibold text-neon hover:underline">{{ $createdUrl }}</a>
            </p>

            <label class="mt-5 flex items-start gap-2.5 text-[13px] text-ink">
                <input type="checkbox" x-model="saved"
                       class="mt-0.5 size-4 shrink-0 cursor-pointer accent-neon">
                <span>I’ve saved the owner link — I won’t see it again.</span>
            </label>

            <button type="button" wire:click="proceed"
                    x-bind:disabled="!saved"
                    class="mt-4 w-full rounded-lg py-3.5 text-[15px] font-bold transition
                           enabled:cursor-pointer enabled:bg-neon enabled:text-base enabled:hover:brightness-125
                           disabled:cursor-not-allowed disabled:border disabled:border-sky disabled:bg-surface disabled:text-muted">
                Continue to {{ $createdName }}
            </button>
        </div>
    @else
        {{-- PRIMARY: create. The single focus of this screen. --}}
        <form wire:submit="create"
              class="rounded-2xl border border-sky bg-surface p-6 shadow-sm">
            <label for="name" class="mb-1.5 block text-[13px] font-semibold text-ink">Workspace name</label>
            <input id="name" type="text" wire:model.live.debounce.300ms="name"
                   placeholder="e.g. CS Masters 2026" autofocus
                   class="w-full rounded-lg border border-sky bg-base px-3.5 py-3 text-[15px] text-ink placeholder:text-muted focus:border-neon focus:outline-2 focus:outline-neon">

            <div class="mt-2 min-h-5 text-[12px]">
                @error('name')
                    <span role="alert" class="text-red-600">{{ $message }}</span>
                @else
                    @if ($this->slugPreview())
                        <span class="text-muted">Your link: <span class="font-semibold text-ink">/{{ $this->slugPreview() }}</span></span>
                    @endif
                @enderror
            </div>

            <button type="submit"
                    class="mt-3 w-full cursor-pointer rounded-lg bg-neon py-3.5 text-[15px] font-bold text-base transition hover:brightness-125">
                Create workspace
            </button>
            <p class="mt-3 text-center text-[12px] text-muted/80">
                You’ll get a link to share — and a private owner link to keep.
            </p>
        </form>

        {{-- SECONDARY: recovery only — most return via their saved link.
             Deliberately quiet: no card, just an inline affordance. --}}
        <div class="mt-7 text-center">
            <p class="text-[13px] text-muted">Already made one? Your saved link is the fastest way back — or find it by name:</p>
            <form wire:submit="open" class="mx-auto mt-2.5 flex max-w-sm gap-2">
                <input id="openName" type="text" wire:model.blur="openName"
                       aria-label="Workspace name"
                       placeholder="CS Masters 2026"
                       class="h-10 flex-1 rounded-lg border border-sky bg-base px-3.5 text-[14px] text-ink placeholder:text-muted focus:border-neon focus:outline-2 focus:outline-neon">
                <button type="submit"
                        class="h-10 shrink-0 cursor-pointer rounded-lg border border-sky px-5 text-[14px] font-semibold text-muted transition hover:border-neon hover:text-neon">
                    Open
                </button>
            </form>
            {{-- Not-found here is a normal recall miss, not a system error —
                 a calm muted hint, not alarm-red. --}}
            @error('openName') <span role="alert" class="mt-2 block text-[13px] text-muted">{{ $message }}</span> @enderror
        </div>
    @endif
</div>

<footer class="mt-auto border-t border-sky/60 py-8">
    <div class="mx-auto max-w-xl px-5 flex items-center justify-between gap-4">
        <p class="text-[13px] font-semibold text-neon">SlipNote</p>
        <p class="text-[13px] text-muted">Share notes, not stress &middot; {{ date('Y') }}</p>
    </div>
</footer>
</div>
