<?php

use App\Models\Course;
use App\Models\Workspace;
use App\Tenancy\Tenancy;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('components.layouts.app')] class extends Component
{
    public Workspace $workspace;

    public string $search = '';
    public string $sort = 'active'; // active | az

    // Create-course form state (only used in owner mode)
    public string $code = '';
    public string $title = '';

    /**
     * Livewire's update endpoint (POST /livewire/update) does NOT pass
     * through the {workspace} route, so ResolveWorkspace middleware never
     * runs and no tenant is set — yet the component re-renders and hits
     * workspace-scoped queries. Re-establish the resolver from the persisted
     * $workspace on every subsequent Livewire request.
     */
    public function hydrate(): void
    {
        if (isset($this->workspace)) {
            app(Tenancy::class)->set($this->workspace);
        }
    }

    public function mount(?string $workspace = null): void
    {
        // The $workspace route segment is accepted but intentionally unused:
        // the resolved tenant (set by middleware, or by the test harness) is
        // the single source of truth. Binding to it directly would inject the
        // raw slug string into the typed Workspace property.
        $this->workspace = app(Tenancy::class)->current();

        // Owner mode: ?owner=SECRET (timing-safe, per-workspace) unlocks
        // course creation for this workspace only.
        $given = request()->query('owner');
        if ($this->workspace->verifyOwner(is_string($given) ? $given : null)) {
            session([$this->workspace->ownerSessionKey() => true]);
            $this->redirectRoute('courses.index', [
                'workspace' => $this->workspace->slug,
            ], navigate: true);
        }
    }

    public function isOwner(): bool
    {
        return session($this->workspace->ownerSessionKey()) === true;
    }

    // Owner-unlock form (returning owner who doesn't have the ?owner= URL handy)
    public string $ownerInput = '';

    /**
     * Let a returning owner unlock owner mode by pasting EITHER the raw
     * secret OR the full owner link they saved — recognition over recall,
     * they paste whatever they kept. Same timing-safe check, same per-
     * workspace session key as the ?owner= URL path. No accounts.
     */
    public function unlockOwner(): void
    {
        $key = ‘unlock_owner:’.$this->workspace->id;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError(‘ownerInput’, ‘Too many attempts. Try again in a few minutes.’);
            return;
        }

        $given = trim($this->ownerInput);

        // Accept a pasted full owner URL: pull the ?owner= value out of it.
        if (str_contains($given, ‘owner=’)) {
            parse_str((string) parse_url($given, PHP_URL_QUERY), $q);
            $given = $q[‘owner’] ?? $given;
        }

        if ($this->workspace->verifyOwner($given !== ‘’ ? $given : null)) {
            RateLimiter::clear($key);
            session([$this->workspace->ownerSessionKey() => true]);
            $this->reset(‘ownerInput’);

            return;
        }

        RateLimiter::hit($key, 600);
        $this->addError(‘ownerInput’, ‘That owner secret or link isn’t right for this workspace.’);
    }

    // Opt-in recovery email (owner-only). Empty clears it.
    public string $recoveryEmail = '';

    public function saveRecoveryEmail(): void
    {
        // Server-side gate — only the owner sets recovery for this board.
        abort_unless($this->isOwner(), 403);

        $email = trim($this->recoveryEmail);
        if ($email !== '') {
            $this->validate(['recoveryEmail' => 'email:rfc'], [], ['recoveryEmail' => 'email']);
        }

        $this->workspace->setRecoveryEmail($email);
        session()->flash('recoverySaved', $email === ''
            ? 'Recovery email removed.'
            : 'Recovery email saved.');
    }

    /** True when the owner hasn't opted into recovery — drives the nudge. */
    public function needsRecoveryEmail(): bool
    {
        return blank($this->workspace->recovery_email);
    }

    /**
     * Recovery is only meaningful if mail can actually reach a person. The
     * `log`/`array` drivers send nowhere a user sees, so treat those as
     * "not configured" and hide the feature rather than promise recovery we
     * can't deliver (spec: graceful degrade, mirrors Telegram no-op).
     */
    public function recoveryAvailable(): bool
    {
        return ! in_array(config('mail.default'), ['log', 'array', null], true);
    }

    public function createCourse(): void
    {
        // Server-side gate: never trust the hidden form alone.
        abort_unless($this->isOwner(), 403);

        $data = $this->validate([
            'code' => 'required|string|max:40',
            'title' => 'required|string|max:120',
        ]);

        // workspace_id is auto-set by the BelongsToWorkspace trait from the
        // resolved tenant — never from input.
        $course = Course::create([
            'code' => strip_tags($data['code']),
            'title' => strip_tags($data['title']),
            'slug' => $this->uniqueSlug($data['code']),
        ]);

        $this->reset('code', 'title');
        session()->flash('created', "“{$course->code}” created.");
        $this->redirectRoute('course.show', [
            'workspace' => $this->workspace->slug,
            'slug' => $course->slug,
        ], navigate: true);
    }

    /**
     * Slug from the code, with a numeric suffix if it collides *within this
     * workspace* (the global scope already constrains the existence check).
     */
    private function uniqueSlug(string $code): string
    {
        $base = Str::slug($code) ?: 'course';
        $slug = $base;
        $n = 2;
        while (Course::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$n}";
            $n++;
        }

        return $slug;
    }

    public function with(): array
    {
        $query = Course::withCount('materials')
            ->withMax('materials', 'created_at');

        if ($term = trim($this->search)) {
            $query->where(function ($q) use ($term) {
                $q->where('code', 'like', "%{$term}%")
                  ->orWhere('title', 'like', "%{$term}%");
            });
        }

        // "active" = most recent upload first (NULLs, i.e. empty courses,
        // fall back to the course's own creation date so they still sort).
        match ($this->sort) {
            'az' => $query->orderBy('code'),
            default => $query->orderByRaw('COALESCE(materials_max_created_at, courses.created_at) desc'),
        };

        return [
            'courses' => $query->get(),
            'totalCourses' => Course::count(),
        ];
    }
};
?>

<div class="flex min-h-screen flex-col">
<div class="mx-auto w-full max-w-3xl flex-1 px-5 pb-10 pt-10"
     x-data="{ sheet: false }"
     x-init="$watch('sheet', v => { if (v) $nextTick(() => $refs.codeField?.focus()) })"
     @keydown.escape.window="sheet = false">
    <header class="mb-7 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
            x-data="{ shareCopied: false,
                      share() {
                          // The PLAIN workspace link — never the ?owner= one.
                          const text = @js(route('courses.index', ['workspace' => $workspace->slug]));
                          const done = () => { this.shareCopied = true; setTimeout(() => this.shareCopied = false, 2000); };
                          if (navigator.clipboard && window.isSecureContext) {
                              navigator.clipboard.writeText(text).then(done).catch(() => this.legacy(text, done));
                          } else { this.legacy(text, done); }
                      },
                      legacy(text, done) {
                          const ta = document.createElement('textarea');
                          ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
                          document.body.appendChild(ta); ta.select();
                          try { document.execCommand('copy'); done(); } catch (e) {}
                          document.body.removeChild(ta);
                      } }">
        <div>
            <p class="mb-1.5 text-xs font-semibold uppercase tracking-[0.08em] text-neon">SlipNote</p>
            <h1 class="text-3xl font-bold tracking-tight text-ink">Courses</h1>
            <p class="mt-1.5 text-[15px] text-muted">
                @if ($totalCourses > 0)
                    Pick a course to browse and share materials.
                @else
                    Course materials for your board, all in one place.
                @endif
            </p>
        </div>
        <div class="flex shrink-0 flex-wrap items-center gap-2">
            {{-- Share = the PLAIN classmate link only. Explicitly labelled so
                 nobody hand-copies their owner URL by mistake. For everyone,
                 not just the owner — classmates pass it on too. --}}
            <button type="button" @click="share()"
                    class="inline-flex shrink-0 cursor-pointer items-center gap-1.5 rounded-lg border border-sky px-4 py-2.5 text-[14px] font-semibold text-muted transition hover:border-neon hover:text-neon">
                <span x-show="!shareCopied">Share with classmates</span>
                <span x-show="shareCopied" x-cloak class="text-neon">Link copied ✓</span>
            </button>
        {{-- Persistent "add another" — only once there's a list to add to.
             While the board is empty the empty-state card owns the single
             call-to-action, so we don't show two identical buttons at once. --}}
            @if ($this->isOwner() && $totalCourses > 0)
                <button type="button" @click="sheet = true"
                        class="inline-flex shrink-0 cursor-pointer items-center gap-1.5 rounded-lg bg-neon px-4 py-2.5 text-[14px] font-bold text-base shadow-sm transition hover:brightness-125">
                    <span class="text-lg leading-none">+</span> New course
                </button>
            @endif
        </div>
    </header>

    @if (session('created'))
        <div class="mb-5 rounded-lg border border-sky bg-sky/40 px-4 py-3 text-sm font-medium text-teal">
            {{ session('created') }}
        </div>
    @endif

    @if ($totalCourses === 0)
        <div class="rounded-2xl border border-sky bg-surface px-6 py-10 text-center shadow-sm">
            <p class="text-[15px] font-semibold text-ink">No courses yet</p>
            @if ($this->isOwner())
                <p class="mt-1.5 text-[14px] text-muted">Add the first one to get this board started.</p>
                <button type="button" @click="sheet = true"
                        class="mt-4 inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-neon px-4 py-2.5 text-[14px] font-bold text-base shadow-sm transition hover:brightness-125">
                    <span class="text-lg leading-none">+</span> New course
                </button>
            @else
                <p class="mx-auto mt-1.5 max-w-sm text-[14px] text-muted">
                    Courses are added by whoever set this board up. If that’s you,
                    open it with your <span class="font-semibold text-ink">owner link</span>
                    (the one shown when you created it). Otherwise, check back soon.
                </p>
            @endif
        </div>
    @else
        {{-- Search + sort appear once the list is long enough to warrant it --}}
        @if ($totalCourses > 3)
            <div class="mb-4 flex flex-col gap-2 sm:flex-row">
                <input type="search" wire:model.live.debounce.250ms="search"
                       placeholder="Search {{ $totalCourses }} courses…"
                       aria-label="Search courses"
                       class="h-11 flex-1 rounded-lg border border-sky bg-surface px-3.5 text-[15px] text-ink shadow-sm placeholder:text-muted focus:border-neon focus:outline-2 focus:outline-neon">
                <select wire:model.live="sort" aria-label="Sort courses"
                        class="h-11 rounded-lg border border-sky bg-surface px-3.5 text-[15px] font-medium text-ink shadow-sm focus:border-neon focus:outline-2 focus:outline-neon">
                    <option value="active">Most recently active</option>
                    <option value="az">A–Z</option>
                </select>
            </div>
        @endif

        @if ($courses->isEmpty())
            <p class="rounded-2xl border border-sky bg-surface px-6 py-8 text-center text-[15px] text-muted shadow-sm">
                No courses match “<span class="font-semibold text-ink">{{ trim($search) }}</span>”.
            </p>
        @else
            <div class="space-y-2.5">
                @foreach ($courses as $course)
                    <a href="{{ route('course.show', ['workspace' => $workspace->slug, 'slug' => $course->slug]) }}" wire:navigate
                       class="group flex items-center justify-between gap-4 rounded-2xl border border-sky bg-surface px-6 py-4 shadow-sm transition hover:-translate-y-0.5 hover:border-neon hover:shadow-md">
                        <div class="min-w-0">
                            <p class="flex items-center gap-1.5 text-[15px] font-bold tracking-tight text-neon">
                                {{ $course->code }}
                                <span aria-hidden="true" class="opacity-0 transition group-hover:translate-x-0.5 group-hover:opacity-100">→</span>
                            </p>
                            <p class="mt-0.5 truncate text-[13px] text-muted">{{ $course->title }}</p>
                            @if ($course->materials_max_created_at)
                                <p class="mt-0.5 text-[12px] text-muted/80">
                                    Updated {{ \Illuminate\Support\Carbon::parse($course->materials_max_created_at)->diffForHumans() }}
                                </p>
                            @endif
                        </div>
                        @if ($course->materials_count > 0)
                            <span class="shrink-0 rounded-full bg-sky/30 px-2.5 py-0.5 text-xs font-medium tabular-nums text-muted">
                                {{ $course->materials_count }} {{ Str::plural('file', $course->materials_count) }}
                            </span>
                        @else
                            <span class="shrink-0 rounded-full border border-dashed border-sky px-2.5 py-0.5 text-xs font-medium text-muted/80">
                                No files yet — be the first
                            </span>
                        @endif
                    </a>
                @endforeach
            </div>
        @endif
    @endif

    @if ($this->isOwner())
        {{-- Create-course sheet: a right-side panel over a backdrop, so the
             course list stays the page and creation is a deliberate action.
             Opened by the header "New course" button; Esc / backdrop close it. --}}
        <div x-show="sheet" x-cloak class="fixed inset-0 z-40" role="dialog" aria-modal="true" aria-label="New course">
            <div x-show="sheet" x-transition.opacity
                 @click="sheet = false"
                 class="absolute inset-0 bg-ink/30 backdrop-blur-sm"></div>

            <div x-show="sheet" x-cloak
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
                 @keydown.escape.window="sheet = false"
                 class="absolute right-0 top-0 flex h-full w-full max-w-md flex-col bg-surface px-6 py-6 shadow-xl">
                <div class="mb-5 flex items-center justify-between">
                    <h2 class="text-xs font-bold uppercase tracking-[0.06em] text-muted">New course</h2>
                    <button type="button" @click="sheet = false"
                            class="cursor-pointer text-[13px] font-semibold text-muted hover:text-neon">Close</button>
                </div>
                <form wire:submit="createCourse" class="flex flex-col gap-3.5">
                    <div>
                        <label for="code" class="mb-1.5 block text-[13px] font-semibold text-ink">Course code</label>
                        <input id="code" type="text" wire:model="code" placeholder="e.g. PHYS 101"
                               x-ref="codeField"
                               @error('code') aria-invalid="true" aria-describedby="code-error" @enderror
                               class="w-full rounded-lg border border-sky bg-base px-3 py-2.5 text-[15px] text-ink placeholder:text-muted focus:border-neon focus:outline-2 focus:outline-neon">
                        @error('code') <span id="code-error" role="alert" class="mt-1.5 block text-[13px] text-red-600">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="title" class="mb-1.5 block text-[13px] font-semibold text-ink">Title</label>
                        <input id="title" type="text" wire:model="title" placeholder="e.g. Introductory Physics"
                               @error('title') aria-invalid="true" aria-describedby="title-error" @enderror
                               class="w-full rounded-lg border border-sky bg-base px-3 py-2.5 text-[15px] text-ink placeholder:text-muted focus:border-neon focus:outline-2 focus:outline-neon">
                        @error('title') <span id="title-error" role="alert" class="mt-1.5 block text-[13px] text-red-600">{{ $message }}</span> @enderror
                    </div>
                    <button type="submit"
                            class="mt-1 cursor-pointer rounded-lg bg-neon py-3 text-[15px] font-bold text-base transition hover:brightness-125">
                        Create course
                    </button>
                </form>
            </div>
        </div>

        {{-- Owner recovery email — opt-in safety net. The NUDGE (shown when
             none is set) is what actually drives adoption; without it the
             recovery feature helps almost nobody. Hidden entirely if mail
             can't actually be delivered — don't promise recovery we can't
             honour (graceful degrade). --}}
        @if ($this->recoveryAvailable())
        <div class="mt-8 rounded-xl border px-5 py-4
                    {{ $this->needsRecoveryEmail() ? 'border-neon/40 bg-neon/5' : 'border-sky bg-surface/50' }}">
            @if (session('recoverySaved'))
                <p class="mb-2 text-[13px] font-semibold text-teal">{{ session('recoverySaved') }}</p>
            @endif

            @if ($this->needsRecoveryEmail())
                <p class="text-[13px] font-semibold text-ink">No recovery email set</p>
                <p class="mt-1 text-[12px] text-muted">
                    If you lose your owner link, this board can’t be recovered.
                    Add an email and we can send the link back to it.
                </p>
            @else
                <p class="text-[13px] font-semibold text-ink">Recovery email is set</p>
                <p class="mt-1 text-[12px] text-muted">
                    If you lose the owner link, request it from the board’s
                    recovery page and we’ll email a fresh link (the old one
                    stops working). Anyone with that inbox can control this board.
                </p>
            @endif

            <form wire:submit="saveRecoveryEmail" class="mt-3 flex flex-col gap-2 sm:flex-row">
                <input type="email" wire:model="recoveryEmail"
                       aria-label="Recovery email"
                       placeholder="{{ $this->needsRecoveryEmail() ? 'you@example.com' : 'new email, or blank to remove' }}"
                       class="h-9 flex-1 rounded-lg border border-sky bg-base px-3 text-[13px] text-ink placeholder:text-muted focus:border-neon focus:outline-2 focus:outline-neon">
                <button type="submit"
                        class="h-9 shrink-0 cursor-pointer rounded-lg bg-neon px-4 text-[13px] font-semibold text-base transition hover:brightness-125">
                    Save
                </button>
            </form>
            @error('recoveryEmail') <span role="alert" class="mt-2 block text-[12px] text-red-600">{{ $message }}</span> @enderror
        </div>
    @endif
    @endif

    {{-- Returning-owner unlock: the common friction point — owner comes back
         to manage the board but doesn't have the ?owner= URL handy. Quiet,
         collapsed by default so the read-only classmate view stays clean.
         Hidden once already in owner mode. --}}
    @unless ($this->isOwner())
        {{-- Returning-owner unlock as one coherent panel: a single bordered
             card whose header is the toggle and whose body reveals in place.
             Quiet by default (collapsed) so the classmate view stays clean;
             reads as a deliberate "owner area", not a stray button. --}}
        <div class="mx-auto mt-10 max-w-sm overflow-hidden rounded-xl border border-sky bg-surface/50"
             x-data="{ open: @js($errors->has('ownerInput')) }">
            <button type="button" @click="open = !open"
                    class="flex w-full cursor-pointer items-center justify-between gap-3 px-4 py-3 text-left transition hover:bg-surface"
                    :aria-expanded="open">
                <span class="text-[13px] font-semibold text-ink">Manage this board</span>
                <span class="text-[12px] text-muted" x-text="open ? 'Close' : 'I’m the owner'"></span>
            </button>

            <div x-show="open" x-cloak class="border-t border-sky px-4 pb-4 pt-3.5">
                <label for="ownerInput" class="block text-[12px] text-muted">
                    Paste the owner secret or link you saved when you created it.
                </label>
                {{-- Plain text, NOT masked: the owner must visually verify the
                     value (whole link? right one?); no shoulder-surf threat on
                     unlocking your own board. --}}
                <form wire:submit="unlockOwner" class="mt-2 flex gap-1.5">
                    <input id="ownerInput" type="text" wire:model="ownerInput"
                           autocomplete="off" autocapitalize="off" spellcheck="false"
                           placeholder="owner secret or link"
                           class="h-9 flex-1 rounded-lg border border-sky bg-base px-3 text-[13px] text-ink placeholder:text-muted focus:border-neon focus:outline-2 focus:outline-neon">
                    <button type="submit"
                            class="h-9 shrink-0 cursor-pointer rounded-lg bg-neon px-4 text-[13px] font-semibold text-base transition hover:brightness-125">
                        Unlock
                    </button>
                </form>
                {{-- Error is the one thing allowed to stand out (still muted,
                     not red — a wrong secret is a routine recall miss). Trust
                     note is a faint footnote beneath. --}}
                @error('ownerInput')
                    <p role="alert" class="mt-2 text-[12px] font-medium text-muted">Not a match — check you copied the whole thing.</p>
                @enderror
                <p class="mt-2 text-[11px] text-muted/60">
                    Goes only to this board · SlipNote never asks for it by email.
                </p>
            </div>
        </div>
    @endunless
</div>

<footer class="mt-auto border-t border-sky/60 py-8">
    <div class="mx-auto max-w-3xl px-5 flex items-center justify-between gap-4">
        <p class="text-[13px] font-semibold text-neon">SlipNote</p>
        <p class="text-[13px] text-muted">Share notes, not stress &middot; {{ date('Y') }}</p>
    </div>
</footer>
</div>
