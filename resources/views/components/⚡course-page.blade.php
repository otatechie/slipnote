<?php

use App\Models\Course;
use App\Models\Material;
use App\Models\Workspace;
use App\Services\TelegramNotifier;
use App\Tenancy\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new
#[Layout('components.layouts.app')]
#[Title('Course')]
class extends Component
{
    use WithFileUploads;

    public Workspace $workspace;
    public Course $course;

    // Browse state
    public string $search = '';
    public string $sort = 'newest'; // newest | oldest | az
    public string $activeSection = ''; // '' = all sections; otherwise a SECTIONS key

    /** Toggle the section filter: click the active pill again to clear it. */
    public function filterSection(string $key): void
    {
        $this->activeSection = $this->activeSection === $key ? '' : $key;
    }

    // Upload form state
    public string $section = 'notes';
    public ?string $title = null;
    public ?string $uploaderName = null;
    public ?string $passphrase = null;
    public $file;

    /**
     * True when uploads require a passphrase the session hasn't entered yet.
     * When no passphrase is configured this is always false (open uploads).
     */
    public function passphraseNeeded(): bool
    {
        return filled($this->workspace->upload_passphrase)
            && session($this->workspace->uploadUnlockKey()) !== true;
    }

    /**
     * Shared rules so the instant (updatedFile) and submit checks stay in sync.
     */
    protected function rules(): array
    {
        return [
            'section' => 'required|in:' . implode(',', array_keys(Material::SECTIONS)),
            'title' => 'nullable|string|max:120',
            'uploaderName' => 'nullable|string|max:60',
            'file' => 'required|file|max:10240|mimes:pdf,docx,pptx,png,jpg,jpeg',
        ];
    }

    /**
     * Re-establish the tenant on every Livewire request: the POST
     * /livewire/update endpoint bypasses the {workspace} route + middleware,
     * so without this any wire:model/action here would hit a scoped query
     * with no resolved workspace and 500. (See courses-page for full note.)
     */
    public function hydrate(): void
    {
        if (isset($this->workspace)) {
            app(Tenancy::class)->set($this->workspace);
        }
    }

    public function mount(string $slug, ?string $workspace = null): void
    {
        // $workspace route segment accepted but unused — the resolved tenant
        // is the source of truth (see courses-page mount for rationale).
        $this->workspace = app(Tenancy::class)->current();

        // Auto-scoped by the WorkspaceScope global scope: a slug that lives
        // only in another workspace 404s here, as required for isolation.
        $this->course = Course::where('slug', $slug)->firstOrFail();

        // Owner mode: ?owner=SECRET (timing-safe, per-workspace) unlocks
        // delete-any for this workspace only, surviving SPA navigation.
        $given = request()->query('owner');
        if ($this->workspace->verifyOwner(is_string($given) ? $given : null)) {
            session([$this->workspace->ownerSessionKey() => true]);
            $this->redirectRoute('course.show', [
                'workspace' => $this->workspace->slug,
                'slug' => $this->course->slug,
            ], navigate: true);
        }
    }

    /** Whether the session has unlocked owner mode for this workspace. */
    public function isOwner(): bool
    {
        return session($this->workspace->ownerSessionKey()) === true;
    }

    public function exitOwner(): void
    {
        session()->forget($this->workspace->ownerSessionKey());
    }

    /**
     * Validate the file the moment it is picked, so size/type errors
     * surface before the user reaches the Upload button.
     */
    public function updatedFile(): void
    {
        $this->validateOnly('file');
    }

    public function save(): void
    {
        $this->validate();

        // Global disk-free safety net: refuse uploads when the host machine
        // is running low, regardless of per-workspace cap. Protects the box.
        $free = @disk_free_space(storage_path('app/public')) ?: PHP_INT_MAX;
        if ($free < (int) config('noteshare.min_free_disk_bytes')) {
            $this->addError('file', 'The site is at capacity — please try again later.');
            return;
        }

        // Per-workspace soft cap: would this upload push the board over?
        $incoming = (int) $this->file->getSize();
        if ($this->workspace->storageBytes() + $incoming > (int) config('noteshare.workspace_storage_bytes')) {
            $this->addError('file', 'This board is full — ask the owner to delete old files.');
            return;
        }

        // Gate uploads behind the shared passphrase, if one is configured.
        // Once correct, the session is unlocked so it isn't asked again.
        if ($this->passphraseNeeded()) {
            if (! $this->workspace->uploadPassphraseMatches($this->passphrase)) {
                $this->addError('passphrase', "That passphrase isn't right — ask your course rep.");

                return;
            }
            session([$this->workspace->uploadUnlockKey() => true]);
            $this->reset('passphrase');
        }

        $storedPath = $this->file->store('materials', 'public');

        $material = $this->course->materials()->create([
            'section' => $this->section,
            'title' => $this->title ? strip_tags($this->title) : null,
            'original_filename' => $this->file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'uploader_name' => $this->uploaderName ? strip_tags($this->uploaderName) : null,
            'manage_token' => \Illuminate\Support\Str::random(40),
            'file_size' => $incoming,
        ]);

        $this->reset('file', 'title', 'uploaderName');
        session()->flash('uploaded', 'File added.');
        // One-time uploader receipt: the only place this delete link is shown.
        session()->flash('manageUrl', $material->manageUrl());

        // Best-effort Telegram notice — sent after the response so a slow or
        // down Telegram never delays the upload. No-ops unless configured.
        dispatch(fn () => app(TelegramNotifier::class)->notifyUpload($material))
            ->afterResponse();
    }

    public function with(): array
    {
        $query = $this->course->materials();

        if ($term = trim($this->search)) {
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', "%{$term}%")
                  ->orWhere('original_filename', 'like', "%{$term}%");
            });
        }

        if ($this->activeSection !== '' && isset(Material::SECTIONS[$this->activeSection])) {
            $query->where('section', $this->activeSection);
        }

        match ($this->sort) {
            'oldest' => $query->oldest(),
            'az' => $query->orderByRaw('COALESCE(title, original_filename) collate nocase asc'),
            default => $query->latest(),
        };

        return [
            'sections' => Material::SECTIONS,
            'materialsBySection' => $query->get()->groupBy('section'),
            'resultCount' => $this->course->materials()->count(),
            // Unfiltered per-section totals so the pills keep their real counts
            // and stay usable to switch sections even while a filter is active.
            'sectionCounts' => $this->course->materials()
                ->selectRaw('section, count(*) as total')
                ->groupBy('section')
                ->pluck('total', 'section'),
        ];
    }
};
?>

<div class="flex min-h-screen flex-col">
<div class="mx-auto w-full max-w-3xl flex-1 px-5 pb-10 pt-10">
    <header class="mb-7">
        <a href="{{ route('courses.index', ['workspace' => $workspace->slug]) }}" wire:navigate
           class="mb-1.5 inline-block text-xs font-semibold uppercase tracking-[0.08em] text-neon hover:underline">← {{ $workspace->name }}</a>
        <h1 class="text-3xl font-bold tracking-tight text-ink">{{ $course->code }}</h1>
        <p class="mt-1.5 text-[15px] text-muted">{{ $course->title }}</p>
    </header>

    @if ($this->isOwner())
        <div class="mb-5 flex flex-wrap items-center justify-between gap-x-3 gap-y-1 rounded-lg border border-neon/40 bg-neon/10 px-4 py-3 text-sm font-medium text-neon">
            <span>Owner mode — you can remove any file.</span>
            <button type="button" wire:click="exitOwner"
                    class="cursor-pointer font-semibold underline-offset-2 hover:underline">Exit</button>
        </div>
    @endif

    @if (session('uploaded'))
        <div class="mb-5 flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg border border-sky bg-sky/40 px-4 py-3 text-sm font-medium text-teal">
            <span>{{ session('uploaded') }}</span>
            @if (session('manageUrl'))
                {{-- One-time uploader receipt: this is the only place the delete link appears --}}
                <form method="POST" action="{{ session('manageUrl') }}"
                      onsubmit="return confirm('Remove this file? This can\'t be undone.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="cursor-pointer font-semibold text-neon underline-offset-2 hover:underline">
                        Uploaded the wrong file? Remove it
                    </button>
                </form>
            @endif
        </div>
    @endif

    {{-- Sticky find bar: at semester volume the page is many screens tall.
         z-30 keeps it above cards and the FAB; it pins to the very top. --}}
    <div class="sticky top-0 z-30 -mx-5 mb-5 space-y-3 bg-base/95 px-5 py-3 backdrop-blur">
        <div class="flex flex-col gap-2 sm:flex-row">
            <input type="search" wire:model.live.debounce.250ms="search"
                   placeholder="Search {{ $resultCount }} files by name…"
                   aria-label="Search files"
                   class="h-11 flex-1 rounded-lg border border-sky bg-white px-3.5 text-[15px] text-ink shadow-sm placeholder:text-muted focus:border-neon focus:outline-2 focus:outline-neon">
            <select wire:model.live="sort" aria-label="Sort files"
                    class="h-11 rounded-lg border border-sky bg-white px-3.5 text-[15px] font-medium text-ink shadow-sm focus:border-neon focus:outline-2 focus:outline-neon">
                <option value="newest">Newest first</option>
                <option value="oldest">Oldest first</option>
                <option value="az">A–Z</option>
            </select>
        </div>

        {{-- Section filter pills: click to show only that section, click again to clear --}}
        <nav aria-label="Filter by section" class="flex flex-wrap gap-1.5">
            @foreach ($sections as $key => $label)
                @php
                    $count = $sectionCounts[$key] ?? 0;
                    $active = $activeSection === $key;
                @endphp
                <button type="button" wire:click="filterSection('{{ $key }}')"
                        @disabled(! $count && ! $active)
                        aria-pressed="{{ $active ? 'true' : 'false' }}"
                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-full px-3 py-1 text-[13px] font-semibold transition
                               {{ $active ? 'bg-neon text-base' : ($count ? 'bg-sky/50 text-neon hover:bg-sky' : 'cursor-not-allowed bg-sky/20 text-muted') }}">
                    {{ $label }}
                    <span class="rounded-full px-1.5 text-xs tabular-nums {{ $active ? 'bg-white/25 text-base' : ($count ? 'bg-white/70 text-neon' : 'bg-white/40 text-muted/70') }}">{{ $count }}</span>
                </button>
            @endforeach
            @if ($activeSection !== '')
                <button type="button" wire:click="$set('activeSection', '')"
                        class="inline-flex cursor-pointer items-center rounded-full px-3 py-1 text-[13px] font-semibold text-muted underline-offset-2 transition hover:text-neon hover:underline">
                    Clear filter
                </button>
            @endif
        </nav>
    </div>

    @php
        $isSearching = trim($search) !== '';
        $isFiltered = $isSearching || $activeSection !== '';
    @endphp
    @if ($isFiltered && $materialsBySection->flatten()->isEmpty())
        <p class="rounded-2xl border border-sky bg-surface px-6 py-8 text-center text-[15px] text-muted shadow-sm">
            @if ($isSearching)
                No files match “<span class="font-semibold text-ink">{{ $search }}</span>”@if ($activeSection !== '') in <span class="font-semibold text-ink">{{ $sections[$activeSection] }}</span>@endif.
            @else
                No files in <span class="font-semibold text-ink">{{ $sections[$activeSection] }}</span> yet.
            @endif
        </p>
    @endif

    @foreach ($sections as $key => $label)
        @php $items = $materialsBySection[$key] ?? collect(); @endphp

        @if ($items->isEmpty())
            {{-- While searching or section-filtering, a hidden section just
                 disappears (no misleading "be the first" prompt) --}}
            @unless ($isFiltered)
                {{-- Compact empty state: present but low-emphasis so files lead the page --}}
                <section id="sec-{{ $key }}"
                         class="mb-3 flex scroll-mt-20 items-baseline justify-between gap-3 rounded-xl border border-sky/60 px-5 py-3">
                    <h2 class="text-xs font-bold uppercase tracking-[0.06em] text-muted">{{ $label }}</h2>
                    <p class="text-[13px] text-muted">
                        Empty —
                        <a href="#add-file" class="font-semibold text-neon hover:underline">be the first to upload</a>
                    </p>
                </section>
            @endunless
        @else
            <section id="sec-{{ $key }}" class="mb-4 scroll-mt-20 rounded-2xl border border-sky bg-surface px-6 py-5 shadow-md ring-1 ring-black/3">
                <h2 class="mb-3.5 flex items-baseline justify-between text-xs font-bold uppercase tracking-[0.06em] text-muted">
                    <span>{{ $label }}</span>
                    <span class="rounded-full border border-sky bg-sky/40 px-2.5 py-0.5 text-xs font-semibold normal-case tracking-normal text-teal">{{ $items->count() }}</span>
                </h2>

                @foreach ($items as $material)
                    <div class="flex items-center justify-between gap-4 border-b border-black/5 py-3 first:pt-0 last:border-0 last:pb-0">
                        <div class="flex min-w-0 items-start gap-3">
                            <span class="mt-1 shrink-0 rounded bg-sky/30 px-1 py-px text-[10px] font-semibold tracking-wide text-muted"
                                  title="{{ $material->fileTypeLabel() }} file">
                                {{ $material->fileTypeLabel() }}
                            </span>
                            <div class="min-w-0">
                                <a href="{{ route('material.download', $material) }}"
                                   class="break-words text-[15px] font-semibold text-neon hover:underline">
                                    {{ $material->displayName() }}
                                </a>
                                <div class="mt-0.5 text-[13px] text-muted">
                                    @if ($material->title)
                                        {{ $material->original_filename }} ·
                                    @endif
                                    {{ $material->uploader_name ?: 'Anonymous' }} · {{ $material->created_at->diffForHumans() }}
                                </div>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            @if ($this->isOwner())
                                <form method="POST" action="{{ route('material.destroy', ['material' => $material->id, 'token' => 'owner']) }}"
                                      data-name="{{ $material->displayName() }}"
                                      onsubmit="return confirm('Remove “' + this.dataset.name + '”? This can\'t be undone.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="cursor-pointer rounded-full px-3 py-1.5 text-[13px] font-semibold text-muted transition hover:bg-red-50 hover:text-red-600">
                                        Delete
                                    </button>
                                </form>
                            @endif
                            <a href="{{ route('material.download', $material) }}"
                               class="rounded-full bg-neon px-4 py-1.5 text-[13px] font-semibold text-base shadow-sm transition hover:brightness-125">
                                Download
                            </a>
                        </div>
                    </div>
                @endforeach
            </section>
        @endif
    @endforeach

    <section id="add-file" class="mt-7 scroll-mt-6"
             x-data="{ open: window.location.hash === '#add-file' || @js($errors->isNotEmpty()) }"
             @hashchange.window="if (window.location.hash === '#add-file') open = true">
        @if ($workspace->storageFull())
            {{-- Board over its storage cap: hide upload entirely with a clear
                 message instead of letting users prep an upload that'll fail. --}}
            <div class="rounded-2xl border border-red-200 bg-red-50 px-6 py-5 text-center">
                <p class="text-[14px] font-semibold text-red-700">This board is full</p>
                <p class="mt-1 text-[13px] text-red-600">
                    Ask the owner to delete old files before new uploads can go up.
                </p>
            </div>
        @else
        {{-- Quick-create FAB: always reachable without scrolling to the bottom --}}
        <button type="button" x-show="!open"
                @click="open = true; $nextTick(() => $root.scrollIntoView({ behavior: 'smooth', block: 'start' }))"
                aria-label="Add a file"
                class="fixed bottom-6 right-6 z-20 flex h-14 w-14 cursor-pointer items-center justify-center rounded-full bg-neon text-2xl font-bold text-base shadow-lg transition hover:brightness-125">
            +
        </button>

        <button type="button" x-show="!open" @click="open = true"
                class="flex w-full cursor-pointer items-center justify-center gap-2 rounded-2xl border border-dashed border-teal/50 bg-surface px-6 py-4 text-[15px] font-semibold text-neon shadow-sm transition hover:bg-sky/30">
            <span class="text-lg leading-none">+</span> Add a file
        </button>

        <div x-show="open" x-cloak
             x-transition.opacity.duration.150ms
             class="rounded-2xl border border-dashed border-teal/50 bg-surface px-6 py-5 shadow-sm">
            <div class="mb-3.5 flex items-center justify-between">
                <h2 class="text-xs font-bold uppercase tracking-[0.06em] text-muted">Add a file</h2>
                <button type="button" @click="open = false"
                        class="cursor-pointer text-[13px] font-semibold text-muted hover:text-neon">Close</button>
            </div>
            <form wire:submit="save" class="flex flex-col gap-3.5">
            @if ($this->passphraseNeeded())
                <div>
                    <label for="passphrase" class="mb-1.5 block text-[13px] font-semibold text-ink">Course passphrase</label>
                    <input id="passphrase" type="password" wire:model="passphrase" placeholder="Ask your course rep"
                           @error('passphrase') aria-invalid="true" aria-describedby="passphrase-error" @enderror
                           class="w-full rounded-lg border border-sky bg-base px-3 py-2.5 text-[15px] text-ink placeholder:text-muted focus:border-neon focus:outline-2 focus:outline-neon">
                    @error('passphrase') <span id="passphrase-error" role="alert" class="mt-1.5 block text-[13px] text-red-600">{{ $message }}</span> @enderror
                </div>
            @endif
            <div>
                <label for="title" class="mb-1.5 block text-[13px] font-semibold text-ink">What is this? (optional)</label>
                <input id="title" type="text" wire:model="title" placeholder="e.g. Week 7 quiz solutions"
                       @error('title') aria-invalid="true" aria-describedby="title-error" @enderror
                       class="w-full rounded-lg border border-sky bg-base px-3 py-2.5 text-[15px] text-ink placeholder:text-muted focus:border-neon focus:outline-2 focus:outline-neon">
                @error('title') <span id="title-error" role="alert" class="mt-1.5 block text-[13px] text-red-600">{{ $message }}</span> @enderror
            </div>

            <div>
                <label for="section" class="mb-1.5 block text-[13px] font-semibold text-ink">Section</label>
                <select id="section" wire:model="section"
                        class="w-full rounded-lg border border-sky bg-base px-3 py-2.5 text-[15px] text-ink focus:border-neon focus:outline-2 focus:outline-neon">
                    @foreach ($sections as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="uploaderName" class="mb-1.5 block text-[13px] font-semibold text-ink">Your name (optional)</label>
                <input id="uploaderName" type="text" wire:model="uploaderName" placeholder="e.g. Alex"
                       @error('uploaderName') aria-invalid="true" aria-describedby="uploaderName-error" @enderror
                       class="w-full rounded-lg border border-sky bg-base px-3 py-2.5 text-[15px] text-ink placeholder:text-muted focus:border-neon focus:outline-2 focus:outline-neon">
                @error('uploaderName') <span id="uploaderName-error" role="alert" class="mt-1.5 block text-[13px] text-red-600">{{ $message }}</span> @enderror
            </div>

            <div>
                <label for="file" class="mb-1.5 block text-[13px] font-semibold text-ink">File</label>
                <input id="file" type="file" wire:model="file"
                       @error('file') aria-invalid="true" aria-describedby="file-error" @else aria-describedby="file-hint" @enderror
                       class="w-full text-sm text-muted file:mr-3 file:rounded-md file:border-0 file:bg-sky file:px-3 file:py-1.5 file:text-[13px] file:font-semibold file:text-teal">
                <p id="file-hint" class="mt-1.5 text-xs text-muted">PDF, Word, PowerPoint, or image · up to 10&nbsp;MB</p>
                <div wire:loading wire:target="file" class="text-[13px] text-muted">Checking file…</div>
                @error('file') <span id="file-error" role="alert" class="mt-1.5 block text-[13px] text-red-600">{{ $message }}</span> @enderror
            </div>

            <button type="submit" wire:loading.attr="disabled" wire:target="save"
                    class="cursor-pointer rounded-lg bg-neon py-3 text-[15px] font-bold text-base transition hover:brightness-125 disabled:cursor-progress disabled:opacity-60">
                <span wire:loading.remove wire:target="save">Upload</span>
                <span wire:loading wire:target="save">Saving…</span>
            </button>
            </form>
        </div>
        @endif
    </section>
</div>

<footer class="mt-auto border-t border-sky/60 py-8">
    <div class="mx-auto max-w-3xl px-5 flex items-center justify-between gap-4">
        <p class="text-[13px] font-semibold text-neon">SlipNote</p>
        <p class="text-[13px] text-muted">Share notes, not stress &middot; {{ date('Y') }}</p>
    </div>
</footer>
</div>
