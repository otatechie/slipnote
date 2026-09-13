<x-layouts.operator title="Operator">
<div class="op mx-auto w-full max-w-5xl flex-1 px-5 pb-12">
    <header class="op-top mb-4 flex items-center justify-between gap-4">
        <div class="min-w-0">
            <p class="op-kicker mb-1 text-[11px] font-semibold uppercase text-muted">SlipNote · Moderation</p>
            <h1 class="op-title text-[1.85rem] font-bold text-ink sm:text-[2.15rem]">Operator</h1>
        </div>
        <form method="POST" action="{{ route('operator.logout') }}">
            @csrf
            <button type="submit" class="op-press inline-flex h-11 shrink-0 cursor-pointer items-center rounded-full border border-sky/50 bg-surface/80 px-3.5 text-[13px] font-semibold text-muted sm:h-8">Log out</button>
        </form>
    </header>

    {{-- States the job outright: this is a moderation console, and the numbers
         are only a health check — not the main task. Scrolls away with the page
         rather than riding the sticky bar: it orients you once, and pinning it
         above a 50-row queue costs the same space on every screen after that. --}}
    <p class="mb-7 text-[13px] text-muted">Clear reported files first. The usage numbers are just a health check.</p>

    @if (session('done') || $undo)
        <div class="op-toast mb-5 text-sm font-medium text-ink">
            <p>{{ session('done') ?? 'You can still undo the last action.' }}</p>
            @if ($undo)
                <form method="POST" action="{{ route('operator.undo') }}">
                    @csrf
                    <button type="submit" class="op-press inline-flex min-h-11 cursor-pointer items-center text-[13px] font-semibold text-neon sm:min-h-0">
                        Undo{{ ! empty($undo['label']) ? ' “'.$undo['label'].'”' : '' }}
                    </button>
                </form>
            @endif
        </div>
    @endif
    @error('undo')
        <div class="op-toast mb-5 text-sm font-medium text-danger" role="alert">{{ $message }}</div>
    @enderror

    {{-- Tab lives in the URL so refresh doesn't yank you off a tab. Reported
         stays the default: action before browsing. No eyebrow: "Needs review"
         described act-vs-browse, and Ambassadors is neither. Labels shorten
         below sm so three cells fit a phone without wrapping; the badges
         still say which is which. --}}
    <div class="op-seg mb-5" role="tablist">
        <a href="{{ route('operator.dashboard') }}"
           @if ($tab === 'reported') aria-current="page" @endif
           class="op-press">
            <span class="sm:hidden">Reported</span><span class="hidden sm:inline">Reported files</span>
            @if ($reportedTotal > 0)
                <span class="rounded-full bg-base px-1.5 text-[11px] font-semibold tabular-nums text-danger">{{ $reportedTotal }}</span>
            @endif
        </a>
        <a href="{{ route('operator.dashboard', ['tab' => 'boards']) }}"
           @if ($tab === 'boards') aria-current="page" @endif
           class="op-press">
            <span class="sm:hidden">Boards</span><span class="hidden sm:inline">Newest boards</span>
            <span class="rounded-full bg-base/80 px-1.5 text-[11px] font-semibold tabular-nums text-muted">{{ $recent->count() }}</span>
        </a>
        <a href="{{ route('operator.dashboard', ['tab' => 'ambassadors']) }}"
           @if ($tab === 'ambassadors') aria-current="page" @endif
           class="op-press">
            Ambassadors
            @if ($ambassadors->whereNull('retired_at')->count() > 0)
                <span class="rounded-full bg-base/80 px-1.5 text-[11px] font-semibold tabular-nums text-muted">{{ $ambassadors->whereNull('retired_at')->count() }}</span>
            @endif
        </a>
    </div>

    @if ($tab === 'ambassadors')
    {{-- Attribution only. A ref link is the normal URL with ?ref=slug; nothing
         is "created" -- this form just puts a name to the slug, so the list
         below shows people, and records where the reward goes. --}}
    <details class="op-card group mb-5" @if ($ambassadors->isEmpty() || $errors->any()) open @endif>
        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4 marker:hidden">
            <span class="text-[15px] font-bold tracking-tight text-ink">Add an ambassador</span>
            <svg aria-hidden="true" class="size-4 shrink-0 text-muted transition-transform duration-150 group-open:rotate-180" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 8l4 4 4-4" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </summary>
    <form method="POST" action="{{ route('operator.ambassadors.store') }}" class="border-t border-sky/60 px-5 pb-5 pt-4">
        @csrf
        <p class="text-[13px] text-muted">Their link becomes <span class="font-mono">{{ url('/') }}/?ref=<em>slug</em></span>. Pick a slug you'll never reuse.</p>
        <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <div>
                <label for="amb-name" class="mb-1.5 block text-[13px] font-semibold text-ink">Name</label>
                <input id="amb-name" name="name" value="{{ old('name') }}" required maxlength="80"
                       @error('name') aria-invalid="true" @enderror
                       class="w-full rounded-xl border border-sky bg-surface px-3.5 py-2.5 text-[15px] text-ink shadow-sm focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20">
                @error('name')<span role="alert" class="mt-1.5 block text-[13px] text-danger">{{ $message }}</span>@enderror
            </div>
            <div>
                <label for="amb-slug" class="mb-1.5 block text-[13px] font-semibold text-ink">Ref slug</label>
                <input id="amb-slug" name="slug" value="{{ old('slug') }}" required maxlength="40" placeholder="knust-kwame"
                       autocapitalize="none" spellcheck="false"
                       @error('slug') aria-invalid="true" @enderror
                       class="w-full rounded-xl border border-sky bg-surface px-3.5 py-2.5 font-mono text-[15px] text-ink shadow-sm focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20">
                @error('slug')<span role="alert" class="mt-1.5 block text-[13px] text-danger">{{ $message }}</span>@enderror
            </div>
            <div>
                <label for="amb-campus" class="mb-1.5 block text-[13px] font-semibold text-ink">Campus <span class="font-normal text-muted">(optional)</span></label>
                <input id="amb-campus" name="campus" value="{{ old('campus') }}" maxlength="80"
                       class="w-full rounded-xl border border-sky bg-surface px-3.5 py-2.5 text-[15px] text-ink shadow-sm focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20">
            </div>
            <div class="grid grid-cols-[1fr_auto] gap-2">
                <div>
                    <label for="amb-phone" class="mb-1.5 block text-[13px] font-semibold text-ink">Phone <span class="font-normal text-muted">(optional — where the bundle goes)</span></label>
                    <input id="amb-phone" name="phone" value="{{ old('phone') }}" maxlength="30" inputmode="tel"
                           class="w-full rounded-xl border border-sky bg-surface px-3.5 py-2.5 text-[15px] text-ink shadow-sm focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20">
                </div>
                <div>
                    <label for="amb-network" class="mb-1.5 block text-[13px] font-semibold text-ink">Network</label>
                    <select id="amb-network" name="network"
                            class="h-[46px] rounded-xl border border-sky bg-surface px-3 text-[15px] text-ink shadow-sm focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20">
                        <option value="">Not set</option>
                        @foreach (\App\Models\Ambassador::NETWORKS as $key => $label)
                            <option value="{{ $key }}" @selected(old('network') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
        <button type="submit" class="op-press mt-4 inline-flex min-h-11 cursor-pointer items-center rounded-full bg-neon px-5 text-[14px] font-semibold text-white">Add ambassador</button>
    </form>
    </details>

    @if ($ambassadorRows->isEmpty())
        <p class="mb-6 text-[13px] text-muted">No ambassadors yet. Add one above and send them their link.</p>
    @else
    {{-- One list, not a list plus a table: an ambassador's numbers sit on
         their row, next to the phone the bundle goes to. Stat cells line up
         as columns from sm; on phones they fold into one line. Unknown refs
         and retired ambassadors sit below the live ones, each in its own
         group, so an anomaly never reads as a peer. --}}
    <h3 class="mb-2 text-[11px] font-semibold uppercase tracking-[0.08em] text-muted">Ambassadors</h3>
    <div class="op-card overflow-hidden">
        <div class="hidden gap-x-3 px-4 py-2 text-[11px] font-semibold uppercase tracking-[0.08em] text-muted sm:grid sm:grid-cols-[1fr_4.5rem_4.5rem_4.5rem_2.5rem]">
            <span>Ambassador</span><span class="text-right">Boards</span><span class="text-right">Seeded</span><span class="text-right">Active</span><span class="sr-only">Actions</span>
        </div>
        @php
            $group = null;
        @endphp
        @foreach ($ambassadorRows as $row)
            @php
                $amb = $row['ambassador'];
                $thisGroup = $amb === null ? 'unknown' : ($amb->isRetired() ? 'retired' : 'live');
            @endphp
            @if ($thisGroup !== $group && $thisGroup !== 'live')
                {{-- No border-t: the row above already ends in an .op-row
                     hairline, and adding one here stacked two 1px lines. --}}
                <p class="bg-base/60 px-4 py-1.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-muted">
                    {{ $thisGroup === 'unknown' ? 'Refs nobody owns' : 'Retired' }}
                </p>
            @endif
            @php
                $group = $thisGroup;
            @endphp
            <div class="op-row relative grid gap-x-3 gap-y-2 sm:grid-cols-[1fr_4.5rem_4.5rem_4.5rem_2.5rem] sm:items-center {{ $thisGroup === 'retired' ? 'opacity-55' : '' }}"
                 data-ref="{{ $row['slug'] }}" data-boards="{{ $row['boards'] }}" data-seeded="{{ $row['seeded'] }}" data-active="{{ $row['active'] }}">
                <div class="min-w-0">
                    @if ($amb)
                        <p class="truncate text-[14px] font-semibold tracking-tight text-ink">
                            {{ $amb->name }}
                            <span class="ml-1 font-mono text-[12px] font-normal text-muted">{{ $amb->slug }}</span>
                        </p>
                        @if ($amb->campus || $amb->phone)
                            <p class="mt-0.5 truncate text-[12px] text-muted">
                                {{ $amb->campus }}@if ($amb->campus && $amb->phone) · @endif
                                @if ($amb->phone){{ $amb->phone }}@if ($amb->network) ({{ \App\Models\Ambassador::NETWORKS[$amb->network] ?? $amb->network }})@endif @endif
                            </p>
                        @endif
                    @else
                        <p class="text-[14px] tracking-tight text-ink">
                            <span class="font-mono">{{ $row['slug'] }}</span>
                            <span class="ml-1 rounded-full bg-danger/10 px-1.5 py-0.5 text-[11px] font-semibold text-danger" title="No ambassador has this slug — a typo, or someone guessing">unknown ref</span>
                        </p>
                    @endif
                    {{-- Phones: the three numbers as one line, in reward order. --}}
                    <p class="mt-1 text-[12px] tabular-nums text-muted sm:hidden">
                        {{ $row['boards'] }} {{ Str::plural('board', $row['boards']) }} · {{ $row['seeded'] }} seeded · <span class="font-semibold text-ink">{{ $row['active'] }} active</span>
                    </p>
                </div>
                <p class="hidden text-right tabular-nums text-muted sm:block">{{ $row['boards'] }}</p>
                <p class="hidden text-right tabular-nums text-ink sm:block">{{ $row['seeded'] }}</p>
                <p class="hidden text-right tabular-nums font-semibold text-ink sm:block">{{ $row['active'] }}</p>
                @if ($amb && ! $amb->isRetired())
                    {{-- Overflow menu, not three inline buttons: every action here
                         is per-ambassador admin done a few times a semester, while
                         the row is read weekly for its numbers. Three buttons also
                         wrapped to a second line. <details> so it opens with no JS
                         under the nonce-only CSP; app.js adds outside-click and
                         Escape. Absolute on phones so it pins to the row's corner
                         instead of dropping below the figures. --}}
                    {{-- Positioning lives entirely in utilities: absolute on phones
                         (pinned to the row corner), relative from sm so the panel
                         anchors to the button. Offsets are reset at sm too --
                         top/right on a relative element are a translate, and
                         left in place they nudged the button 12px down and left. --}}
                    <details class="op-menu absolute right-3 top-3 sm:relative sm:right-auto sm:top-auto sm:justify-self-end">
                        <summary aria-label="Actions for {{ $amb->name }}"
                                 class="op-press flex size-9 cursor-pointer list-none items-center justify-center rounded-full border border-sky/50 bg-surface text-muted marker:hidden sm:size-8">
                            <svg aria-hidden="true" class="size-4" viewBox="0 0 20 20" fill="currentColor"><circle cx="10" cy="4" r="1.6"/><circle cx="10" cy="10" r="1.6"/><circle cx="10" cy="16" r="1.6"/></svg>
                        </summary>
                        <div class="absolute right-0 top-full z-20 mt-1 w-56 rounded-xl border border-sky bg-surface p-1 text-left shadow-xl">
                            <button type="button" data-copy="{{ $amb->link() }}"
                                    class="block w-full cursor-pointer rounded-lg px-3 py-2 text-left text-[13px] font-semibold text-muted hover:bg-sky/30 hover:text-ink">Copy link</button>
                            <button type="button" data-copy="{{ $amb->inviteMessage() }}"
                                    class="block w-full cursor-pointer rounded-lg px-3 py-2 text-left text-[13px] font-semibold text-muted hover:bg-sky/30 hover:text-ink">Copy invite message</button>
                            <span class="my-1 block h-px bg-sky/60"></span>
                            {{-- Irreversible (the slug is never reused), so it
                                 confirms like Remove does. --}}
                            <button type="button" data-dialog-open="retire-{{ $amb->id }}"
                                    class="block w-full cursor-pointer rounded-lg px-3 py-2 text-left text-[13px] font-semibold text-danger hover:bg-danger/10">Retire</button>
                        </div>
                    </details>
                    {{-- Outside the <details>: a closed one hides its children, and
                         showModal() on a hidden dialog paints nothing. --}}
                    <dialog id="retire-{{ $amb->id }}" class="op-dialog m-auto w-[calc(100%-2rem)] max-w-sm rounded-2xl p-0 shadow-2xl">
                            <div class="px-6 py-6">
                                <h2 class="text-[16px] font-bold tracking-tight text-ink">Retire {{ $amb->name }}?</h2>
                                <p class="mt-3 text-[13px] leading-relaxed text-ink/80">Their boards keep the ref <span class="font-mono">{{ $amb->slug }}</span> and it will never be handed to anyone else. Their numbers stay on this page. This can't be undone.</p>
                                <div class="mt-5 flex items-center justify-end gap-2">
                                    <button type="button" data-dialog-close
                                            class="op-press inline-flex min-h-11 cursor-pointer items-center rounded-full px-4 text-[14px] font-semibold text-muted">Cancel</button>
                                    <form method="POST" action="{{ route('operator.ambassadors.retire', $amb) }}">
                                        @csrf
                                        <button type="submit" class="op-press btn-danger inline-flex min-h-11 cursor-pointer items-center rounded-full px-5 text-[14px] font-semibold">Retire</button>
                                    </form>
                                </div>
                            </div>
                    </dialog>
                @endif
            </div>
        @endforeach
    </div>
    <p class="mt-3 text-[13px] text-muted">Seeded = has at least one file. Active = opened or downloaded from in the last 7 days. Pay on live boards, never on sign-ups.</p>
    @endif
    @endif

    @if ($tab === 'boards')
    <div class="op-card overflow-hidden">
        @forelse ($recent as $ws)
            {{-- A board with no files is an unused shell — fade it so the boards
                 that actually hold content stand out when scanning. --}}
            @php
                $bytes = (int) ($ws->materials_sum_file_size ?? 0);
                $size = $bytes >= 1_048_576
                    ? number_format($bytes / 1_048_576, 1).' MB'
                    : number_format($bytes / 1024).' KB';
            @endphp
            {{-- Stacks on phones: the right column plus a truncating board name
                 leaves ~190px for the name at 320px wide, which cuts it mid-word. --}}
            <div class="op-row flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between sm:gap-3 {{ $ws->materials_count === 0 ? 'opacity-55' : '' }}">
                <div class="min-w-0">
                    <p class="truncate text-[14px] font-semibold tracking-tight text-ink">
                        <a href="{{ route('courses.index', ['workspace' => $ws->slug]) }}" class="underline decoration-muted/40 underline-offset-4 hover:decoration-ink/40">{{ $ws->name }}</a>
                    </p>
                    <p class="mt-0.5 text-[12px] text-muted">
                        {{ $ws->courses_count }} {{ Str::plural('course', $ws->courses_count) }} ·
                        {{ $ws->materials_count }} {{ Str::plural('file', $ws->materials_count) }}
                        {{-- Spelled out rather than a bare badge: on a list of
                             boards a lone red number could be any of these counts. --}}
                        @if ($ws->reported_count > 0)
                            · <span class="font-semibold text-danger">{{ $ws->reported_count }} reported</span>
                        @endif
                    </p>
                </div>
                <div class="flex shrink-0 flex-wrap items-center gap-x-2 sm:block sm:text-right">
                    {{-- Both lines are conditional: an empty board's "0 KB" and a
                         never-opened board's blank time are noise repeated down
                         the column, drowning out the rows that do say something. --}}
                    @if ($ws->materials_count > 0)
                        <p class="text-[12px] tabular-nums text-muted">{{ $size }}</p>
                    @endif
                    @if ($ws->last_accessed_at)
                        <p class="text-[12px] tabular-nums text-ink/80" title="Last opened {{ $ws->last_accessed_at }}">
                            opened {{ $ws->last_accessed_at->diffForHumans(null, true) }} ago
                        </p>
                    @endif
                </div>
            </div>
        @empty
            <p class="px-4 py-8 text-center text-[14px] text-muted">No boards yet.</p>
        @endforelse
    </div>
    {{-- The list is capped and sorted by creation, and empty rows are faded —
         both are invisible rules until stated. The empty count is the useful
         number here: boards created and never filled are the drop-off. --}}
    @if ($recent->isNotEmpty())
        <p class="mt-3 text-[13px] text-muted">
            The {{ $recent->count() }} newest of {{ number_format($stats['workspaces']) }}.
            @if ($stats['empty_boards'] > 0)
                <span class="opacity-70">Faded = no files yet ({{ $stats['empty_boards'] }} of {{ number_format($stats['workspaces']) }} overall).</span>
            @endif
        </p>
    @endif
    @endif

    @if ($tab === 'reported')
    @if ($materials->isNotEmpty())
        <p class="mb-3 text-[13px] text-muted">
            Most-reported first — work down the list.
            @if ($reportedTotal > $materials->count())
                Showing the top {{ $materials->count() }} of {{ $reportedTotal }}; clear these to see the rest.
            @endif
        </p>
        <div class="op-card overflow-hidden">
            @foreach ($materials as $material)
                @php
                    $reasons = $material->reports->whereNotNull('reason');
                    $workspace = $material->course->workspace;
                    $courseUrl = route('course.show', ['workspace' => $workspace->slug, 'slug' => $material->course->slug]);
                    $boardUrl = route('courses.index', ['workspace' => $workspace->slug]);
                    $sectionLabel = \App\Models\Material::SECTIONS[$material->section] ?? $material->section;
                    $reviewUrl = $material->previewUrl() ?? $material->downloadUrl();
                    $reviewLabel = $material->previewUrl() ? 'Review file' : 'Download to review';
                    $latest = $reasons->first();
                    $context = $material->course->code.' · '.$material->displayName();
                @endphp
                <div class="op-row flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between sm:gap-5">
                    {{-- Course code leads the title so two files with the same
                         display name don't scan as the same row. --}}
                    <div class="min-w-0 flex-1">
                        <p class="flex items-center gap-2 text-[15px] font-semibold tracking-tight text-ink">
                            <span class="truncate">{{ $context }}</span>
                            <span class="shrink-0 rounded-full bg-danger/10 px-1.5 py-0.5 text-[11px] font-semibold tabular-nums text-danger"
                                  title="{{ $material->reports_count }} {{ $material->reports_count === 1 ? 'report' : 'reports' }}">
                                {{ $material->reports_count }}
                            </span>
                        </p>
                        <p class="mt-1 flex flex-wrap items-center gap-x-1.5 gap-y-1 text-[12px] text-muted">
                            <a href="{{ $boardUrl }}" class="underline decoration-muted/40 underline-offset-4 hover:text-ink hover:decoration-ink/40">{{ $workspace->name }}</a>
                            <span aria-hidden="true" class="text-muted/50">·</span>
                            <a href="{{ $courseUrl }}" class="underline decoration-muted/40 underline-offset-4 hover:text-ink hover:decoration-ink/40">{{ $material->course->code }}</a>
                            <span aria-hidden="true" class="text-muted/50">·</span>
                            <span>{{ $sectionLabel }}</span>
                            @if ($reviewUrl)
                                <span aria-hidden="true" class="text-muted/50">·</span>
                                <a href="{{ $reviewUrl }}" class="op-review">
                                    <svg class="size-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1.5 10S4.5 4 10 4s8.5 6 8.5 6-3 6-8.5 6-8.5-6-8.5-6Z"/><circle cx="10" cy="10" r="2.5"/></svg>
                                    {{ $reviewLabel }}
                                </a>
                            @endif
                        </p>

                        {{-- Reasons: latest inline with when it was filed; full history collapsed --}}
                        @if ($reasons->isNotEmpty())
                            <details class="group mt-2.5">
                                <summary class="flex cursor-pointer list-none items-center gap-1.5 text-[13px] leading-snug text-muted marker:hidden">
                                    {{-- Only the quote is clamped. "+N more" sits
                                         outside it because it's the one signal the
                                         row expands, and inside the clamp it was
                                         exactly what got cut ("+2…") on a phone. --}}
                                    <span class="line-clamp-2 min-w-0 flex-1 sm:truncate">
                                        “{{ \Illuminate\Support\Str::limit($latest->reason, 80) }}”
                                        <span class="text-muted">{{ $latest->created_at->diffForHumans() }}</span>
                                    </span>
                                    @if ($reasons->count() > 1)
                                        <span class="shrink-0 text-muted">+{{ $reasons->count() - 1 }} more</span>
                                    @endif
                                    <svg class="size-3 shrink-0 transition-transform duration-150 group-open:rotate-180" style="transition-timing-function: var(--ease-out)" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 8l4 4 4-4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </summary>
                                <ul class="mt-1.5 space-y-1 border-l-2 border-sky/60 pl-3">
                                    @foreach ($reasons as $report)
                                        <li class="text-[12px] text-ink/90">
                                            <span class="text-muted">{{ $report->created_at->diffForHumans() }}:</span>
                                            {{ $report->reason }}
                                        </li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif
                    </div>

                    {{-- Two decisions only — inspect lives inline above. Dialogs
                         repeat course + filename so the wrong row is harder to confirm. --}}
                    <div class="grid shrink-0 grid-cols-2 items-center gap-2.5 sm:flex sm:justify-end">
                        <button type="button" data-dialog-open="dismiss-{{ $material->id }}"
                                title="Clear the reports — the file stays up"
                                class="op-press inline-flex h-11 w-full cursor-pointer items-center justify-center rounded-full border border-sky/50 bg-surface px-3.5 text-[13px] font-semibold text-muted sm:h-8 sm:w-auto sm:text-[12px]">
                            Dismiss
                        </button>
                        <button type="button" data-dialog-open="remove-{{ $material->id }}"
                                title="Delete the file and block re-upload"
                                class="op-press btn-danger inline-flex h-11 w-full cursor-pointer items-center justify-center rounded-full px-3.5 text-[13px] font-semibold sm:h-8 sm:w-auto sm:text-[12px]">
                            Remove
                        </button>
                    </div>

                    <dialog id="dismiss-{{ $material->id }}"
                            class="op-dialog m-auto w-[calc(100%-2rem)] max-w-sm rounded-2xl p-0 shadow-2xl">
                        <div class="px-6 py-6">
                            <h2 class="text-[16px] font-bold tracking-tight text-ink">Dismiss the reports?</h2>
                            <p class="mt-1 text-[13px] text-muted">{{ $context }} · {{ $workspace->name }}</p>
                            <p class="mt-3 text-[13px] leading-relaxed text-ink/80">This clears the reports on this file. The file stays up and visible to everyone. You can undo for a few minutes after.</p>
                            <div class="mt-5 flex items-center justify-end gap-2">
                                <button type="button" data-dialog-close
                                        class="op-press inline-flex min-h-11 cursor-pointer items-center rounded-full px-4 text-[14px] font-semibold text-muted">Cancel</button>
                                <form method="POST" action="{{ route('operator.dismiss', $material->id) }}">
                                    @csrf
                                    <button type="submit"
                                            class="op-press inline-flex min-h-11 cursor-pointer items-center rounded-full bg-neon px-5 text-[14px] font-semibold text-white">
                                        Dismiss reports
                                    </button>
                                </form>
                            </div>
                        </div>
                    </dialog>

                    <dialog id="remove-{{ $material->id }}"
                            class="op-dialog m-auto w-[calc(100%-2rem)] max-w-sm rounded-2xl p-0 shadow-2xl">
                        <div class="px-6 py-6">
                            <h2 class="text-[16px] font-bold tracking-tight text-ink">Remove this file?</h2>
                            <p class="mt-1 text-[13px] text-muted">{{ $context }} · {{ $workspace->name }}</p>
                            <p class="mt-3 text-[13px] leading-relaxed text-ink/80">This deletes the file and its reports, and blocks these exact bytes from being uploaded again. You can undo for a few minutes after.</p>
                            <div class="mt-5 flex items-center justify-end gap-2">
                                <button type="button" data-dialog-close
                                        class="op-press inline-flex min-h-11 cursor-pointer items-center rounded-full px-4 text-[14px] font-semibold text-muted">Cancel</button>
                                <form method="POST" action="{{ route('operator.remove', $material->id) }}">
                                    @csrf
                                    <button type="submit"
                                            class="op-press btn-danger inline-flex min-h-11 cursor-pointer items-center rounded-full px-5 text-[14px] font-semibold">
                                        Remove file
                                    </button>
                                </form>
                            </div>
                        </div>
                    </dialog>
                </div>
            @endforeach
        </div>
    @else
        <div class="op-card px-6 py-16 text-center">
            <p class="text-[16px] font-semibold tracking-tight text-ink">Nothing reported</p>
            <p class="mx-auto mt-1.5 max-w-sm text-[14px] leading-relaxed text-muted">No files are currently flagged. Reports show up here when someone uses the report button on a file.</p>
        </div>
    @endif
    @endif

    {{-- Context, not the task — so it sits after the queue rather than pushing
         it below the fold. Deltas only when they moved: "+0 this week" on every
         tile is chrome that looks like a signal and isn't. --}}
    <h2 class="mb-2 mt-10 text-[11px] font-semibold uppercase tracking-[0.08em] text-muted">At a glance</h2>
    <div class="op-metrics">
        <div class="op-metric">
            <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-muted">Boards</p>
            <p class="mt-1 text-[1.65rem] font-bold tabular-nums tracking-tight text-ink">{{ number_format($stats['workspaces']) }}</p>
            @if ($stats['workspaces_week'] > 0)
                <p class="mt-0.5 text-[12px] text-muted">+{{ $stats['workspaces_week'] }} this week</p>
            @endif
        </div>
        {{-- Opened or downloaded from, not uploaded to: an archive nobody adds
             to but everyone reads is still doing its job. Says so on the tile,
             because a bare count could mean either and the difference is the point.
             Before any board has been visited the count is 0 for a reason that
             isn't "nobody came" — say which, or it reads as broken. --}}
        <div class="op-metric">
            <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-muted">Opened</p>
            <p class="mt-1 text-[1.65rem] font-bold tabular-nums tracking-tight text-ink">{{ $stats['tracking_started'] ? number_format($stats['active_week']) : '—' }}</p>
            <p class="mt-0.5 text-[12px] text-muted">
                {{ $stats['tracking_started'] ? 'or downloaded, last 7d' : 'no visits recorded yet' }}
            </p>
        </div>
        <div class="op-metric">
            <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-muted">Courses</p>
            <p class="mt-1 text-[1.65rem] font-bold tabular-nums tracking-tight text-ink">{{ number_format($stats['courses']) }}</p>
            @if ($stats['courses_week'] > 0)
                <p class="mt-0.5 text-[12px] text-muted">+{{ $stats['courses_week'] }} this week</p>
            @endif
        </div>
        <div class="op-metric">
            <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-muted">Files</p>
            <p class="mt-1 text-[1.65rem] font-bold tabular-nums tracking-tight text-ink">{{ number_format($stats['files']) }}</p>
            @if ($stats['files_week'] > 0)
                <p class="mt-0.5 text-[12px] text-muted">+{{ $stats['files_week'] }} this week</p>
            @endif
        </div>
        <div class="op-metric">
            <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-muted">Storage</p>
            <p class="mt-1 text-[1.65rem] font-bold tabular-nums tracking-tight text-ink">{{ number_format($stats['storage_mb'], 1) }} <span class="text-[14px] font-semibold text-muted">MB</span></p>
            <p class="mt-0.5 text-[12px] text-muted">across all boards</p>
        </div>
    </div>
</div>
</x-layouts.operator>
