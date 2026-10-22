<x-layouts.operator title="Operator">
<div class="op op-compact mx-auto w-full max-w-5xl flex-1 px-5 pb-12">
    {{-- Not pinned: a title and Log out don't need to follow you down a list,
         and pinned they floated translucent over every row on the way. --}}
    <header class="mb-4 flex items-center justify-between gap-4 pb-1 pt-6 sm:pt-8">
        <div class="min-w-0">
            {{-- The mark, as on the login page and /start. "Moderation" went:
                 the page also holds boards, visitors and suggestions. --}}
            <a href="/" class="-my-2.5 inline-flex min-h-11 items-center gap-1.5 text-[13px] font-bold text-muted transition hover:text-ink sm:my-0 sm:mb-1 sm:min-h-0">
                <img src="/logo-mark.png" alt="" class="size-4">
                SlipNote
            </a>
            <h1 class="op-title text-[1.85rem] font-bold text-ink sm:text-[2.15rem]">Operator</h1>
        </div>
        <form method="POST" action="{{ route('operator.logout') }}">
            @csrf
            <button type="submit" class="op-press inline-flex h-11 shrink-0 cursor-pointer items-center rounded-full border border-sky/50 bg-surface/80 px-3.5 text-[13px] font-semibold text-muted sm:h-8">Log out</button>
        </form>
    </header>

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
            <span class="sm:hidden">Reports</span><span class="hidden sm:inline">Reported files</span>
            @if ($reportedTotal > 0)
                <span class="rounded-full bg-base px-1.5 text-[11px] font-semibold tabular-nums text-danger">{{ $reportedTotal }}</span>
            @endif
        </a>
        <a href="{{ route('operator.dashboard', ['tab' => 'boards']) }}"
           @if ($tab === 'boards') aria-current="page" @endif
           class="op-press">
            <span class="sm:hidden">Boards</span><span class="hidden sm:inline">Newest boards</span>
        </a>
        <a href="{{ route('operator.dashboard', ['tab' => 'visitors']) }}"
           @if ($tab === 'visitors') aria-current="page" @endif
           class="op-press">
            <span class="sm:hidden">Visits</span><span class="hidden sm:inline">Visitors</span>
        </a>
        <a href="{{ route('operator.dashboard', ['tab' => 'suggestions']) }}"
           @if ($tab === 'suggestions') aria-current="page" @endif
           class="op-press">
            <span class="sm:hidden">Ideas</span><span class="hidden sm:inline">Suggestions</span>
            @if ($suggestionsWeek > 0)
                <span class="rounded-full bg-base/80 px-1.5 text-[11px] font-semibold tabular-nums text-muted" title="This week">{{ $suggestionsWeek }}</span>
            @endif
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
                       class="w-full rounded-xl border border-sky bg-surface px-3.5 py-2.5 text-[15px] text-ink shadow-sm">
                @error('name')<span role="alert" class="mt-1.5 block text-[13px] text-danger">{{ $message }}</span>@enderror
            </div>
            <div>
                <label for="amb-slug" class="mb-1.5 block text-[13px] font-semibold text-ink">Ref slug</label>
                <input id="amb-slug" name="slug" value="{{ old('slug') }}" required maxlength="40" placeholder="knust-kwame"
                       autocapitalize="none" spellcheck="false"
                       @error('slug') aria-invalid="true" @enderror
                       class="w-full rounded-xl border border-sky bg-surface px-3.5 py-2.5 font-mono text-[15px] text-ink shadow-sm">
                @error('slug')<span role="alert" class="mt-1.5 block text-[13px] text-danger">{{ $message }}</span>@enderror
            </div>
            <div>
                <label for="amb-campus" class="mb-1.5 block text-[13px] font-semibold text-ink">Campus <span class="font-normal text-muted">(optional)</span></label>
                <input id="amb-campus" name="campus" value="{{ old('campus') }}" maxlength="80"
                       class="w-full rounded-xl border border-sky bg-surface px-3.5 py-2.5 text-[15px] text-ink shadow-sm">
            </div>
            {{-- Side by side from sm up; stacked on phones, where the pair left
                 the Phone label wrapping over two lines beside a cramped select. --}}
            <div class="grid gap-3 sm:grid-cols-[1fr_auto] sm:gap-2">
                <div>
                    <label for="amb-phone" class="mb-1.5 block text-[13px] font-semibold text-ink">Phone <span class="font-normal text-muted">(optional — where the bundle goes)</span></label>
                    <input id="amb-phone" name="phone" value="{{ old('phone') }}" maxlength="30" inputmode="tel"
                           class="w-full rounded-xl border border-sky bg-surface px-3.5 py-2.5 text-[15px] text-ink shadow-sm">
                </div>
                <div>
                    <label for="amb-network" class="mb-1.5 block text-[13px] font-semibold text-ink">Network</label>
                    <select id="amb-network" name="network"
                            class="h-[46px] w-full rounded-xl border border-sky bg-surface px-3 text-[15px] sm:w-auto text-ink shadow-sm">
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
        {{-- Plain words, in funnel order: made through their link -> got a file
             -> being opened. "Boards / Seeded / Active" needed the footnote to
             be read at all; these read on their own. --}}
        <div class="hidden gap-x-3 px-4 py-2 text-[11px] font-semibold uppercase tracking-[0.08em] text-muted sm:grid sm:grid-cols-[1fr_5rem_5.5rem_7.5rem_2.5rem]">
            <span>Ambassador</span><span class="text-right">Created</span><span class="text-right">With files</span><span class="text-right">Used this month</span><span class="sr-only">Actions</span>
        </div>
        @php
            $group = null;
            $currency = config('noteshare.ambassador_currency');
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
            <div class="op-row relative grid gap-x-3 gap-y-2 sm:grid-cols-[1fr_5rem_5.5rem_7.5rem_2.5rem] sm:items-center {{ $thisGroup === 'retired' ? 'opacity-55' : '' }}"
                 data-ref="{{ $row['slug'] }}" data-boards="{{ $row['boards'] }}" data-seeded="{{ $row['seeded'] }}" data-active="{{ $row['active'] }}" data-due="{{ $row['due'] }}">
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
                    {{-- Phones: two deliberate lines rather than one run that wraps
                         wherever it lands -- context above, the pay line below,
                         each part kept whole so no word is orphaned. --}}
                    <p class="mt-1 text-[12px] tabular-nums text-muted sm:hidden">
                        <span class="whitespace-nowrap">{{ $row['boards'] }} created</span> ·
                        <span class="whitespace-nowrap">{{ $row['seeded'] }} with files</span>
                    </p>
                    <p class="mt-0.5 text-[12px] tabular-nums sm:hidden">
                        <span class="whitespace-nowrap font-semibold text-ink">{{ $row['active'] }} used this month</span>@if ($amb && $row['due'] > 0)<span class="text-muted"> · </span><span class="whitespace-nowrap font-semibold text-ink">{{ $currency }} {{ $row['due'] }} due</span>@endif
                    </p>
                </div>
                <p class="hidden text-right tabular-nums text-muted sm:block">{{ $row['boards'] }}</p>
                <p class="hidden text-right tabular-nums text-ink sm:block">{{ $row['seeded'] }}</p>
                {{-- The pay column, with what it comes to underneath. Quiet on
                     purpose: a number to act on, not a price tag on a person. --}}
                <div class="hidden text-right tabular-nums sm:block">
                    <p class="font-semibold text-ink">{{ $row['active'] }}</p>
                    @if ($amb && $row['due'] > 0)
                        <p class="text-[11px] text-muted">{{ $currency }} {{ $row['due'] }} due</p>
                    @endif
                </div>
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
    @php
        $rate = (int) config('noteshare.ambassador_reward');
        $currency = config('noteshare.ambassador_currency');
        $dueTotal = $ambassadorRows->filter(fn ($r) => $r['ambassador'] !== null)->sum('due');
    @endphp
    <p class="mt-3 text-[13px] text-muted">
        Created = a board made through their link. With files = it has at least one file. Used this month = someone opened it or downloaded from it in the last 30 days.
        Due = used boards × {{ $currency }} {{ $rate }} (<span class="font-mono">AMBASSADOR_REWARD</span>), paid by hand once a month.
        @if ($dueTotal > 0)
            <span class="font-semibold text-ink">Due this month: {{ $currency }} {{ $dueTotal }}.</span>
        @endif
    </p>
    @endif
    @endif

    @if ($tab === 'visitors')
    {{-- Counts only: the tallies hold a number per day, board, country and
         source, never a visitor. An arrival is a page opened from outside the
         site, so clicking around a board doesn't inflate anything. --}}
    @if ($visitors['total'] === 0)
        <div class="op-card px-5 py-4">
            <p class="text-[14px] text-muted"><span class="font-semibold text-ink">No arrivals counted yet.</span> Counting starts with the next visit.</p>
            <p class="mt-1 text-[12px] text-muted">Countries need Cloudflare in front of the site and its ranges in TRUSTED_PROXIES; without them every country reads Unknown.</p>
        </div>
    @else
        <p class="mb-4 text-[13px] text-muted">
            <span class="font-semibold text-ink">{{ number_format($visitors['total']) }} {{ Str::plural('arrival', $visitors['total']) }}</span>
            in the last {{ $visitors['days'] }} days.
            @if ($visitors['first_day'] && $visitors['first_day'] > now()->subDays($visitors['days'])->toDateString())
                Counting since {{ \Illuminate\Support\Carbon::parse($visitors['first_day'])->isoFormat('MMM D') }}.
            @endif
        </p>

        {{-- Each count with its share, so the split reads at a glance. --}}
        @php
            $share = fn ($n) => max(1, (int) round($n / max(1, $visitors['total']) * 100)).'%';
        @endphp
        <div class="grid gap-5 sm:grid-cols-2">
            <section>
                <h2 class="mb-2 text-[11px] font-semibold uppercase tracking-[0.08em] text-muted">Countries</h2>
                {{-- All Unknown means the country header isn't trusted yet: say
                     why, rather than show one row that tells you nothing. --}}
                @if ($visitors['countries']->count() === 1 && $visitors['countries']->first()['code'] === '')
                    <div class="op-card px-4 py-3">
                        <p class="text-[13px] text-muted"><span class="font-semibold text-ink">Not known yet.</span> Countries come from Cloudflare, and are only believed once its ranges are in TRUSTED_PROXIES.</p>
                    </div>
                @else
                    <div class="op-card overflow-hidden">
                        @foreach ($visitors['countries'] as $row)
                            <div class="op-row flex items-center justify-between gap-3">
                                <span class="truncate text-[14px] text-ink">{{ $row['label'] }}</span>
                                <span class="shrink-0 text-[13px] tabular-nums text-muted"><span class="font-semibold text-ink">{{ number_format($row['total']) }}</span> · {{ $share($row['total']) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
            <section>
                <h2 class="mb-2 text-[11px] font-semibold uppercase tracking-[0.08em] text-muted">How they found it</h2>
                <div class="op-card overflow-hidden">
                    @foreach ($visitors['sources'] as $row)
                        <div class="op-row flex items-center justify-between gap-3">
                            <span class="truncate text-[14px] text-ink">{{ $row['source'] }}</span>
                            <span class="shrink-0 text-[13px] tabular-nums text-muted"><span class="font-semibold text-ink">{{ number_format($row['total']) }}</span> · {{ $share($row['total']) }}</span>
                        </div>
                    @endforeach
                </div>
                {{-- "direct" is the bucket people misread as "nobody". --}}
                <p class="mt-2 text-[12px] text-muted">"direct" is a typed link, a QR scan, the installed app, or a tap in an app that hides where it came from. WhatsApp usually does. "other" includes visits that came by the site's own IP address.</p>
            </section>
        </div>

        @if ($visitors['boards']->isNotEmpty())
            <h2 class="mb-2 mt-6 text-[11px] font-semibold uppercase tracking-[0.08em] text-muted">Boards people arrive at</h2>
            <div class="op-card overflow-hidden">
                @foreach ($visitors['boards'] as $row)
                    <div class="op-row flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-[14px] font-semibold tracking-tight text-ink">
                                <a href="{{ route('courses.index', ['workspace' => $row['workspace']->slug]) }}" class="underline decoration-muted/40 underline-offset-4 hover:decoration-ink/40">{{ $row['workspace']->name }}</a>
                            </p>
                            <p class="mt-0.5 truncate text-[12px] text-muted">{{ implode(' · ', $row['countries']) }}</p>
                        </div>
                        <span class="shrink-0 text-[13px] tabular-nums text-muted"><span class="font-semibold text-ink">{{ number_format($row['total']) }}</span> · {{ $share($row['total']) }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    @endif
    @endif

    @if ($tab === 'suggestions')
    {{-- From the "Suggest a feature" form. Newest first, last 100. --}}
    @if ($suggestions->isEmpty())
        <div class="op-card px-5 py-4">
            <p class="text-[14px] text-muted"><span class="font-semibold text-ink">No suggestions yet.</span> They come from "Suggest a feature" in the app footer and the "Tell us" card on busy boards.</p>
        </div>
    @else
        <div class="op-card overflow-hidden">
            @foreach ($suggestions as $s)
                <div class="op-row flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="whitespace-pre-line break-words text-[14px] text-ink">{{ $s->message }}</p>
                        <p class="mt-1 text-[12px] text-muted">
                            {{ $s->created_at->diffForHumans() }}
                            @if ($s->page) · from <span class="font-medium text-ink">{{ $s->page }}</span> @endif
                            @if ($s->reply_to) · reply to <span class="select-all font-medium text-ink">{{ $s->reply_to }}</span> @endif
                        </p>
                    </div>
                    <form method="POST" action="{{ route('operator.suggestions.delete', $s->id) }}" class="shrink-0">
                        @csrf
                        <button type="submit" aria-label="Delete this suggestion" title="Delete"
                                class="op-press flex size-11 cursor-pointer items-center justify-center rounded-full text-muted transition hover:bg-danger/10 hover:text-danger sm:size-9">
                            <svg aria-hidden="true" class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"><path d="M5 5l10 10M15 5L5 15" /></svg>
                        </button>
                    </form>
                </div>
            @endforeach
        </div>
    @endif
    @endif

    @if ($tab === 'boards')
    {{-- Note to one board's owner: shown only in owner mode on that board,
         answered through the suggestion form. The way to reach a heavy user
         without accounts or touching the recovery email. --}}
    @if ($noteBoard)
        <form method="POST" action="{{ route('operator.boards.note', $noteBoard->id) }}" class="op-card mb-5 px-5 py-4">
            @csrf
            <label for="ownerNote" class="block text-[14px] font-semibold text-ink">Note to the owner of “{{ $noteBoard->name }}”</label>
            <p class="mt-0.5 text-[12px] text-muted">Shown only to them, on their board, until they dismiss it. They reply through the suggestion form; replies land in Suggestions.</p>
            <textarea id="ownerNote" name="note" rows="3" maxlength="500" required
                      placeholder="e.g. Hi from the SlipNote team. Your class uses this a lot. Could we ask you two quick questions?"
                      class="mt-3 w-full resize-y rounded-xl border border-sky bg-base px-3.5 py-2.5 text-[15px] text-ink shadow-inner placeholder:text-muted">{{ old('note', $noteBoard->operator_note) }}</textarea>
            @error('note')
                <span role="alert" class="mt-1.5 block text-[13px] text-danger">{{ $message }}</span>
            @enderror
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <button type="submit" class="op-press inline-flex min-h-11 cursor-pointer items-center rounded-full bg-neon px-5 text-[14px] font-semibold text-white sm:min-h-9">
                    {{ $noteBoard->operator_note ? 'Update note' : 'Leave note' }}
                </button>
                <a href="{{ route('operator.dashboard', array_filter(['tab' => 'boards', 'q' => $boardQuery])) }}"
                   class="inline-flex min-h-11 items-center rounded-full px-4 text-[14px] font-semibold text-muted hover:text-ink sm:min-h-9">Cancel</a>
            </div>
        </form>
        @if ($noteBoard->operator_note)
            <form method="POST" action="{{ route('operator.boards.note', $noteBoard->id) }}" class="-mt-3 mb-5 px-5">
                @csrf
                <input type="hidden" name="note" value="">
                <button type="submit" class="inline-flex min-h-11 cursor-pointer items-center text-[13px] font-semibold text-danger hover:underline sm:min-h-0">Remove note</button>
                <span class="text-[12px] text-muted">· left {{ $noteBoard->operator_note_at?->diffForHumans() }}</span>
            </form>
        @endif
    @endif

    <form method="GET" action="{{ route('operator.dashboard') }}" class="mb-4 flex gap-2">
        <input type="hidden" name="tab" value="boards">
        <input type="search" name="q" value="{{ $boardQuery }}" aria-label="Find a board by name"
               placeholder="Find a board by name…"
               class="w-full min-w-0 flex-1 rounded-xl border border-sky bg-surface px-3.5 py-2.5 text-[15px] text-ink shadow-sm placeholder:text-muted">
        <button type="submit" class="op-press inline-flex min-h-11 shrink-0 cursor-pointer items-center rounded-full border border-muted/50 px-4 text-[14px] font-semibold text-ink hover:bg-sky/30">Find</button>
    </form>

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
            {{-- The whole row opens the board (the link stretches over it): the
                 name alone was a 19px-tall target on a phone. --}}
            <div class="op-row relative flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between sm:gap-3 {{ $ws->materials_count === 0 ? 'opacity-55' : '' }}">
                <div class="min-w-0">
                    <p class="truncate text-[14px] font-semibold tracking-tight text-ink">
                        <a href="{{ route('courses.index', ['workspace' => $ws->slug]) }}" class="underline decoration-muted/40 underline-offset-4 after:absolute after:inset-0 hover:decoration-ink/40">{{ $ws->name }}</a>
                    </p>
                    <p class="mt-0.5 text-[12px] text-muted">
                        {{-- "0 courses · 0 files" on eight of ten rows is the same
                             noise the right column already drops; one phrase says
                             it. A board with a course but no file keeps its counts:
                             getting partway is worth seeing. --}}
                        @if ($ws->courses_count === 0 && $ws->materials_count === 0)
                            Nothing added yet
                        @else
                            {{ $ws->courses_count }} {{ Str::plural('course', $ws->courses_count) }} ·
                            {{ $ws->materials_count }} {{ Str::plural('file', $ws->materials_count) }}
                        @endif
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
                    {{-- relative z-10: above the row's stretched board link. --}}
                    <a href="{{ route('operator.dashboard', array_filter(['tab' => 'boards', 'q' => $boardQuery, 'note' => $ws->id])) }}"
                       class="relative z-10 inline-flex min-h-11 items-center text-[12px] font-semibold text-neon hover:underline sm:min-h-0">
                        {{ $ws->operator_note ? 'Note left · edit' : 'Note to owner' }}
                    </a>
                </div>
            </div>
        @empty
            <p class="px-4 py-8 text-center text-[14px] text-muted">{{ $boardQuery !== '' ? 'No board matches “'.$boardQuery.'”.' : 'No boards yet.' }}</p>
        @endforelse
    </div>
    {{-- The list is capped and sorted by creation, and empty rows are faded —
         both are invisible rules until stated. The empty count is the useful
         number here: boards created and never filled are the drop-off. --}}
    @if ($recent->isNotEmpty())
        <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-[13px] text-muted">
                @if ($boardQuery !== '')
                    {{ $recent->firstItem() }}–{{ $recent->lastItem() }} of {{ number_format($recent->total()) }} {{ Str::plural('board', $recent->total()) }} matching “{{ $boardQuery }}”.
                @else
                    {{ $recent->firstItem() }}–{{ $recent->lastItem() }} of {{ number_format($recent->total()) }}, newest first.
                @endif
                @if ($stats['empty_boards'] > 0)
                    <span class="opacity-70">Faded = no files yet ({{ $stats['empty_boards'] }} of {{ number_format($stats['workspaces']) }} overall).</span>
                @endif
            </p>
            {{-- Newer / page / Older: two directions and where you are, all a
                 list sorted by date needs. Links, so the page is in the URL. --}}
            @if ($recent->hasPages())
                <nav aria-label="Board pages" class="flex shrink-0 items-center gap-2 text-[13px]">
                    @if ($recent->onFirstPage())
                        <span class="op-pill inline-flex min-h-11 items-center rounded-full px-4 font-semibold text-muted opacity-50 sm:min-h-9" aria-disabled="true">← Newer</span>
                    @else
                        <a href="{{ $recent->previousPageUrl() }}" rel="prev" class="op-pill op-press inline-flex min-h-11 items-center rounded-full px-4 font-semibold text-ink sm:min-h-9">← Newer</a>
                    @endif
                    <span class="px-1 tabular-nums text-muted">Page {{ $recent->currentPage() }} of {{ $recent->lastPage() }}</span>
                    @if ($recent->hasMorePages())
                        <a href="{{ $recent->nextPageUrl() }}" rel="next" class="op-pill op-press inline-flex min-h-11 items-center rounded-full px-4 font-semibold text-ink sm:min-h-9">Older →</a>
                    @else
                        <span class="op-pill inline-flex min-h-11 items-center rounded-full px-4 font-semibold text-muted opacity-50 sm:min-h-9" aria-disabled="true">Older →</span>
                    @endif
                </nav>
            @endif
        </div>
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
        {{-- One line, not a 200px card: an empty queue is good news, and a
             tall empty state pushed "At a glance" below the fold for nothing. --}}
        <div class="op-card flex items-center gap-3 px-5 py-4">
            <svg aria-hidden="true" class="size-5 shrink-0 text-muted" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10.5l4 4 8-9"/></svg>
            <p class="text-[14px] text-muted"><span class="font-semibold text-ink">Nothing reported.</span> Flagged files appear here.</p>
        </div>
    @endif
    @endif

    {{-- Context, not the task — so it sits after the queue rather than pushing
         it below the fold. Deltas only when they moved: "+0 this week" on every
         tile is chrome that looks like a signal and isn't. On the landing tab
         only: repeated under every other tab it was a footer nobody asked for. --}}
    @if ($tab === 'reported')
    <h2 class="mb-2 mt-8 text-[11px] font-semibold uppercase tracking-[0.08em] text-muted">At a glance</h2>
    <div class="op-metrics">
        <div class="op-metric">
            <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-muted">Boards</p>
            <p class="mt-0.5 text-[1.35rem] font-bold leading-tight tabular-nums tracking-tight text-ink sm:mt-1 sm:text-[1.65rem]">{{ number_format($stats['workspaces']) }}</p>
            @if ($stats['workspaces_week'] > 0)
                <p class="mt-0.5 text-[12px] text-muted">+{{ $stats['workspaces_week'] }} this week</p>
            @endif
        </div>
        {{-- The activation rate, in place of a course count that decides nothing.
             Boards -> With files -> Opened reads as the funnel: made, filled,
             used. This is the number the empty-board work has to move. --}}
        <div class="op-metric">
            <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-muted">With files</p>
            <p class="mt-0.5 text-[1.35rem] font-bold leading-tight tabular-nums tracking-tight text-ink sm:mt-1 sm:text-[1.65rem]">{{ number_format($stats['workspaces'] - $stats['empty_boards']) }}</p>
            <p class="mt-0.5 text-[12px] text-muted">of {{ number_format($stats['workspaces']) }} boards</p>
        </div>
        {{-- Opened or downloaded from, not uploaded to: an archive nobody adds
             to but everyone reads is still doing its job. Says so on the tile,
             because a bare count could mean either and the difference is the point.
             Before any board has been visited the count is 0 for a reason that
             isn't "nobody came" — say which, or it reads as broken. --}}
        <div class="op-metric">
            <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-muted">Active</p>
            <p class="mt-0.5 text-[1.35rem] font-bold leading-tight tabular-nums tracking-tight text-ink sm:mt-1 sm:text-[1.65rem]">{{ $stats['tracking_started'] ? number_format($stats['active_week']) : '—' }}</p>
            <p class="mt-0.5 text-[12px] text-muted">
                {!! $stats['tracking_started'] ? 'opened or downloaded, 7&nbsp;days' : 'no visits recorded yet' !!}
            </p>
        </div>
        <div class="op-metric">
            <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-muted">Files</p>
            <p class="mt-0.5 text-[1.35rem] font-bold leading-tight tabular-nums tracking-tight text-ink sm:mt-1 sm:text-[1.65rem]">{{ number_format($stats['files']) }}</p>
            @if ($stats['files_week'] > 0)
                <p class="mt-0.5 text-[12px] text-muted">+{{ $stats['files_week'] }} this week</p>
            @endif
        </div>
        <div class="op-metric">
            <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-muted">Storage</p>
            <p class="mt-0.5 text-[1.35rem] font-bold leading-tight tabular-nums tracking-tight text-ink sm:mt-1 sm:text-[1.65rem]">{{ number_format($stats['storage_mb'], 1) }} <span class="text-[14px] font-semibold text-muted">MB</span></p>
            <p class="mt-0.5 text-[12px] text-muted">across all boards</p>
        </div>
    </div>
    @endif
</div>
</x-layouts.operator>
