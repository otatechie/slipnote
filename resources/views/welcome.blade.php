<x-layouts.app
    title="Never ask “who has the notes?” again"
    description="The free, no-login board where your class keeps its notes and slides, sorted by course. One link, no accounts, no setup."
    :indexable="true">

    <x-slot:head>
        <link rel="canonical" href="{{ url('/') }}">

        <meta property="og:type" content="website">
        <meta property="og:site_name" content="SlipNote">
        <meta property="og:title" content="SlipNote: Never ask “who has the notes?” again">
        <meta property="og:description" content="One link for your whole class's notes and slides, sorted by course. Free, no accounts, no setup.">
        <meta property="og:url" content="{{ url('/') }}">
        <meta property="og:image" content="{{ url('/og.png') }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="SlipNote: Never ask “who has the notes?” again">
        <meta name="twitter:description" content="One link for your whole class's notes and slides, sorted by course. Free, no accounts, no setup.">
        <meta name="twitter:image" content="{{ url('/og.png') }}">

        <script type="application/ld+json">
        {
            "@@context": "https://schema.org",
            "@@type": "SoftwareApplication",
            "name": "SlipNote",
            "url": "{{ url('/') }}",
            "applicationCategory": "EducationalApplication",
            "operatingSystem": "Web",
            "offers": {
                "@@type": "Offer",
                "price": "0",
                "priceCurrency": "USD"
            },
            "description": "The free, no-login board where your class keeps its notes and slides, sorted by course."
        }
        </script>
    </x-slot:head>

    {{-- Built to Nielsen Norman Group guidance: say what the product is in
         the first line, one primary action per screen, headings that are
         statements a scanner can read on their own, conventional patterns
         (a table is a table, a list is a list), and nothing decorative that
         carries no information. Tokens only, so dark mode is the same page. --}}
    <div class="lp flex-1">
        {{-- A floating, rounded bar: inset from the edges, a soft shadow
             instead of a rule, and it stays put while the page scrolls. The
             outer header is transparent so content shows around the bar. --}}
        <header class="sticky top-0 z-30 px-3 pt-3 sm:px-6 sm:pt-4">
            <div class="mx-auto flex h-14 max-w-5xl items-center justify-between gap-3 rounded-full bg-surface/90 pl-4 pr-2 shadow-[0_1px_2px_rgba(16,24,40,0.06),0_10px_30px_-12px_rgba(16,24,40,0.18)] ring-1 ring-sky/60 backdrop-blur-md sm:h-16 sm:pl-6 sm:pr-3">
                <a href="{{ route('welcome') }}" class="inline-flex items-center gap-1.5 text-[16px] font-bold text-ink">
                    <img src="/logo-mark.png" alt="" class="size-6">
                    SlipNote
                </a>
                <nav class="flex items-center gap-1" aria-label="Main navigation">
                    <a href="#how" class="hidden rounded-full px-3 py-2 text-[14px] font-medium text-muted hover:text-ink sm:inline-flex">How it works</a>
                    <a href="#features" class="hidden rounded-full px-3 py-2 text-[14px] font-medium text-muted hover:text-ink sm:inline-flex">Compare</a>
                    <a href="#faq" class="hidden rounded-full px-3 py-2 text-[14px] font-medium text-muted hover:text-ink md:inline-flex">Questions</a>
                    <a href="{{ route('start') }}" class="op-press ml-1 inline-flex min-h-10 items-center rounded-full bg-neon px-4 text-[14px] font-semibold text-white sm:ml-2">
                        Create a board
                    </a>
                </nav>
            </div>
        </header>

        <main>
            {{-- What it is, who it is for, and what to do next, beside the moment
                 every class knows: the group chat asking for the notes. The chat
                 is a picture (role="img" with a label), not content to tab
                 through. Neutral colours, no brand styling, so it reads as any
                 class group rather than an imitation of one app. --}}
            {{-- Example address in the illustrations: fixed, not the running host,
                 so a local or staging copy never shows its own domain. --}}
            @php $heroHost = 'slipnote.co'; @endphp
            <section class="px-4 pb-12 pt-8 sm:px-6 sm:pb-20 sm:pt-16">
                <div class="mx-auto grid max-w-5xl items-center gap-12 lg:grid-cols-[1.1fr_0.9fr] lg:gap-14">
                    <div>
                        <h1 class="text-[30px] font-bold leading-[1.15] tracking-[-0.02em] text-ink sm:text-balance sm:text-[46px]">
                            Share your class’s notes and slides with one link.
                        </h1>
                        <p class="mt-5 max-w-xl text-[17px] leading-7 text-muted sm:text-[18px] sm:leading-8">
                            A free board for your class, sorted by course. No account needed.
                        </p>
                        <div class="mt-7 flex flex-wrap items-center gap-x-4 gap-y-3 sm:mt-8">
                            <a href="{{ route('start') }}" class="op-press inline-flex min-h-12 items-center justify-center rounded-full bg-neon px-6 text-[16px] font-semibold text-white">
                                Create a board
                            </a>
                            <a href="#how" class="inline-flex min-h-12 items-center text-[15px] font-semibold text-neon underline-offset-4 hover:underline">
                                See how it works
                            </a>
                        </div>
                    </div>

                    {{-- The chat sits on a soft tinted stage instead of in a bordered
                         box: a phone-like panel with a gentle shadow, a faded second
                         panel behind it for depth, borderless bubbles, and a message
                         bar so it reads as a chat at a glance. Sender names are
                         coloured per person, as group chats do. Your own message is
                         a light tint with dark text, right-hand, no name, with
                         ticks. The link preview says what the link opens. --}}
                    <figure role="img" aria-label="Your class group chat. A classmate asks who has the ECON 201 slides, another says it was sent last week, scroll up. You post the SlipNote board link, with 6 courses and 48 files, and a classmate replies: finally."
                            class="lp-stage relative mx-auto w-full max-w-md overflow-hidden rounded-[24px] px-3 pb-5 pt-10 sm:rounded-[28px] sm:px-8 sm:pb-8 sm:pt-12 lg:max-w-none">
                        <figcaption aria-hidden="true" class="absolute left-4 top-3.5 text-[12px] font-medium text-muted sm:left-8 sm:top-4">In your class group chat</figcaption>

                        {{-- Depth: a second panel peeking out behind. --}}
                        <div aria-hidden="true" class="absolute bottom-10 left-1/2 top-16 hidden w-[300px] -translate-x-[62%] -rotate-3 rounded-[28px] bg-surface/50 sm:block"></div>

                        <div aria-hidden="true" class="relative mx-auto w-full max-w-[360px] overflow-hidden rounded-[28px] bg-surface shadow-[0_1px_2px_rgba(16,24,40,0.06),0_24px_60px_-24px_rgba(16,24,40,0.35)]">
                            <div class="flex items-center gap-3 px-4 pb-2 pt-3">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-base text-muted">
                                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate text-[14px] font-semibold text-ink">ECON Level 200</p>
                                    <p class="text-[11px] text-muted">142 members</p>
                                </div>
                            </div>

                            <div class="space-y-2 px-3 pb-3 text-[14px] leading-5">
                                @foreach ([
                                    ['Ama', 'lp-name-1', 'Please who has the ECON 201 slides from Tuesday?', '9:41'],
                                    ['Yaw', 'lp-name-3', 'I sent it last week, scroll up', '9:44'],
                                ] as [$who, $nameClass, $text, $time])
                                    <div class="w-fit max-w-[85%] rounded-2xl rounded-bl-md bg-base px-3 py-1.5">
                                        <p class="text-[11px] font-semibold {{ $nameClass }}">{{ $who }}</p>
                                        <p class="text-ink">{{ $text }}<span class="ml-2 inline-block align-baseline text-[10px] text-muted">{{ $time }}</span></p>
                                    </div>
                                @endforeach

                                <div class="lp-mine ml-auto w-fit max-w-[90%] rounded-2xl rounded-br-md p-1.5">
                                    <div class="flex items-center gap-2.5 rounded-xl bg-surface p-2 shadow-[0_1px_2px_rgba(16,24,40,0.08)]">
                                        <img src="/logo-mark.png" alt="" class="size-9 shrink-0 rounded-lg">
                                        <div class="min-w-0">
                                            <p class="truncate text-[13px] font-semibold text-ink">ECON Level 200</p>
                                            <p class="truncate text-[12px] text-muted">6 courses · 48 files · {{ $heroHost }}</p>
                                        </div>
                                    </div>
                                    <p class="px-1.5 pb-0.5 pt-1.5 text-ink">Everything is on the board now
                                        <span class="ml-2 inline-flex items-center gap-0.5 align-baseline text-[10px] text-muted">9:47
                                            <svg class="size-3.5 text-neon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12.5l4 4 9-9.5"/><path d="M10 15.5l1 1 9-9.5"/></svg>
                                        </span>
                                    </p>
                                </div>

                                <div class="w-fit max-w-[85%] rounded-2xl rounded-bl-md bg-base px-3 py-1.5">
                                    <p class="text-[11px] font-semibold lp-name-2">Kofi</p>
                                    <p class="text-ink">Finally. Thank you!<span class="ml-2 inline-block align-baseline text-[10px] text-muted">9:48</span></p>
                                </div>
                            </div>

                            {{-- Message bar; hidden on phones, where it is a row the eye skips. --}}
                            <div class="hidden px-3 pb-3 sm:block">
                                <div class="flex items-center gap-2 rounded-full bg-base py-1.5 pl-3 pr-1.5">
                                    <svg class="size-4 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                                    <span class="flex-1 text-[13px] text-muted">Message</span>
                                    <span class="flex size-7 items-center justify-center rounded-full lp-mine text-neon">
                                        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5"/><path d="m5 12 7-7 7 7"/></svg>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </figure>
                </div>
            </section>

            {{-- The process, as a numbered list a reader can follow. --}}
            {{-- How it works, to NNG guidance: three short steps, each a verb
                 and one or two lines a scanner will actually read. Step 1 shows
                 the link the name turns into instead of describing it. Steps run
                 top to bottom with a line through the numbers: a vertical list
                 scans faster than three columns, and reads the same on phones.
                 An ordered list, so screen readers announce "1 of 3". --}}
            @php $howHost = $heroHost; @endphp
            <section id="how" class="scroll-mt-24 px-4 py-12 sm:px-6 sm:py-20">
                <div class="mx-auto grid max-w-5xl gap-10 lg:grid-cols-[0.8fr_1.2fr] lg:gap-16">
                    <div>
                        <h2 class="text-[26px] font-bold tracking-[-0.02em] text-ink sm:text-[32px]">How it works</h2>
                        <p class="mt-2 max-w-sm text-[16px] leading-7 text-muted">One person sets the board up. Everyone else only needs the link.</p>
                    </div>

                    <ol>
                        @foreach ([
                            ['link', 'Name the board', 'Name it after your class, like ECON Level 200. That name becomes the link. You also get a private owner link for managing the board.'],
                            ['courses', 'Add your courses', 'Add a course for each subject, like ECON 201. Each one has a place for notes, slides and past papers.'],
                            ['share', 'Share the link', 'Post it in your WhatsApp group or show its QR code in a lecture. Classmates can read, download and add files.'],
                        ] as $i => [$kind, $title, $body])
                            <li class="relative flex gap-5 {{ $loop->last ? '' : 'pb-10' }}">
                                {{-- A neutral hairline from under this number to the next. --}}
                                @unless ($loop->last)
                                    <span aria-hidden="true" class="absolute bottom-1 left-[17.5px] top-12 w-px bg-sky"></span>
                                @endunless
                                <span aria-hidden="true" class="flex size-9 shrink-0 items-center justify-center rounded-full bg-surface text-[14px] font-semibold text-ink ring-1 ring-sky">{{ $i + 1 }}</span>
                                <div class="pt-1">
                                    <h3 class="text-[17px] font-semibold text-ink">{{ $title }}</h3>
                                    <p class="mt-1.5 max-w-md text-[15px] leading-6 text-muted">{{ $body }}</p>

                                    {{-- A small piece of the real thing per step. Decoration
                                         for sighted readers; the sentence above says it. --}}
                                    <div aria-hidden="true" class="mt-3">
                                        @if ($kind === 'link')
                                            <p class="inline-flex max-w-full items-center gap-1.5 text-[14px] text-muted">
                                                <svg class="size-4 shrink-0 text-neon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                                                <span class="truncate">{{ $howHost }}/<span class="font-medium text-ink">econ-level-200</span></span>
                                            </p>
                                        @elseif ($kind === 'courses')
                                            <div class="w-full max-w-xs divide-y divide-sky/70 overflow-hidden rounded-xl bg-surface shadow-[0_1px_2px_rgba(16,24,40,0.06)]">
                                                @foreach ([['ECON 201', 'Microeconomics', '14 files'], ['ECON 203', 'Statistics', '9 files'], ['ECON 205', 'Mathematics', 'No files yet']] as [$code, $title, $count])
                                                    <div class="flex items-center justify-between gap-3 px-3 py-2">
                                                        <div class="min-w-0">
                                                            <p class="truncate text-[13px] font-bold tracking-tight text-teal">{{ $code }}</p>
                                                            <p class="truncate text-[11px] text-muted">{{ $title }}</p>
                                                        </div>
                                                        @if ($count === 'No files yet')
                                                            <span class="shrink-0 rounded-full border border-dashed border-muted/40 px-2 py-0.5 text-[10px] font-semibold text-muted">{{ $count }}</span>
                                                        @else
                                                            <span class="shrink-0 rounded-full bg-teal/10 px-2 py-0.5 text-[10px] font-semibold text-teal">{{ $count }}</span>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <div class="flex items-center gap-2.5">
                                                <div class="flex -space-x-2">
                                                    @foreach ([['A', 'bg-[color-mix(in_srgb,var(--color-neon)_16%,var(--color-surface))] text-neon'], ['K', 'bg-[color-mix(in_srgb,var(--color-ink)_9%,var(--color-surface))] text-ink'], ['Y', 'bg-[color-mix(in_srgb,var(--color-teal)_12%,var(--color-surface))] text-teal'], ['E', 'bg-sky text-muted']] as [$who, $tint])
                                                        <span class="flex size-7 items-center justify-center rounded-full text-[11px] font-semibold ring-2 ring-base {{ $tint }}">{{ $who }}</span>
                                                    @endforeach
                                                </div>
                                                <span class="text-[13px] text-muted">+40 classmates</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </section>

            {{-- What the product does, as facts. --}}
            {{-- Three ways a class shares files, as one table, matched to how a
                 student already thinks about the choice:
                 - Rows are things a student wants to do, in their words, each a
                   yes/no question. No system jargon, no row that breaks the pattern.
                 - Every cell answers with the same three outline marks: a circle
                   check is yes, a faint circle cross is no, a darker circle minus
                   is "partly" and always carries a short note. The minus is kept
                   darker than the cross so "partly" never reads as a faint "no".
                 - Columns use the names students say (WhatsApp group, Google
                   Drive), and every row starts with a verb, matching the header.
                   Yes is the same colour in every column, so a mark means the same
                   thing wherever it appears; SlipNote stands out by its raised
                   panel, not by different marks.
                 - No row dividers: spacing and the centred marks carry the rows.
                   Cells are top-aligned so the marks in a row share one line even
                   when a cell carries a note under its mark.
                 Phones: the task name moves to its own full-width row above its
                 three marks, column names shorten to one line so they align,
                 and the raised panel is dropped (the task rows would cut it);
                 the blue column name marks SlipNote. Marks look identical in
                 every column, so a check never reads as a different kind of yes. --}}
            @php
                $columns = [
                    ['name' => 'WhatsApp group', 'short' => 'WhatsApp', 'pick' => false],
                    ['name' => 'SlipNote', 'short' => 'SlipNote', 'pick' => true],
                    ['name' => 'Google Drive', 'short' => 'Drive', 'pick' => false],
                ];
                // [task, [[state, note], ...]] with state yes | no | part.
                $rows = [
                    ['Find week 3’s slides without scrolling', [['no', null], ['yes', null], ['part', 'If kept tidy']]],
                    ['Get files shared before you joined', [['no', null], ['yes', null], ['yes', null]]],
                    ['Download a course’s notes in one go', [['no', null], ['yes', null], ['yes', null]]],
                    ['See what’s new since you last looked', [['part', 'Messages only'], ['yes', null], ['no', null]]],
                    ['Let classmates add files without an account', [['yes', null], ['yes', null], ['part', 'Needs a Google account']]],
                    ['Keep files organised without anyone tidying', [['no', null], ['yes', null], ['no', null]]],
                ];
                $marks = [
                    'yes' => '<svg aria-hidden="true" class="mx-auto size-6 text-neon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9.25"/><path d="M8.25 12.25l2.5 2.5 5-5.25"/></svg><span class="sr-only">Yes</span>',
                    'no' => '<svg aria-hidden="true" class="mx-auto size-6 text-muted/45" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><circle cx="12" cy="12" r="9.25"/><path d="M9.5 9.5l5 5M14.5 9.5l-5 5"/></svg><span class="sr-only">No</span>',
                    'part' => '<svg aria-hidden="true" class="mx-auto size-6 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="9.25"/><path d="M8.5 12h7"/></svg><span class="sr-only">Partly</span>',
                ];
                $pickCell = 'sm:bg-surface';
            @endphp
            <section id="features" class="scroll-mt-24 px-4 py-12 sm:px-6 sm:py-20">
                <div class="mx-auto max-w-5xl">
                    <div class="max-w-2xl sm:mx-auto sm:text-center">
                        <h2 class="text-[26px] font-bold leading-[1.15] tracking-[-0.02em] text-ink sm:text-[40px]">Compare the three ways to share class files</h2>
                        <p class="mt-2 text-[16px] leading-7 text-muted sm:mt-4 sm:text-[18px]">Keep the WhatsApp group for talking. Put the board’s link in it.</p>
                    </div>

                    <table class="mt-8 w-full table-fixed border-separate border-spacing-0 text-left sm:mt-16">
                        <thead>
                            <tr>
                                <th scope="col" class="hidden pb-5 align-bottom text-[13px] font-medium text-muted sm:table-cell sm:w-[40%]">What you want to do</th>
                                @foreach ($columns as $col)
                                    <th scope="col" class="px-2 pb-5 pt-5 text-center align-bottom font-normal sm:px-5 sm:pt-6 {{ $col['pick'] ? $pickCell.' sm:rounded-t-2xl' : '' }}">
                                        <span class="flex flex-col items-center gap-1.5 sm:flex-row sm:flex-wrap sm:justify-center sm:gap-2">
                                            <span class="text-[14px] font-semibold leading-5 sm:text-[16px] {{ $col['pick'] ? 'text-neon' : 'text-ink' }}"><span class="sm:hidden">{{ $col['short'] }}</span><span class="hidden sm:inline">{{ $col['name'] }}</span></span>
                                        </span>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $r => [$task, $cells])
                                @php $last = $r === count($rows) - 1; @endphp
                                {{-- Exactly one of the two task headers is displayed at
                                     any width, so screen readers always get one. --}}
                                <tr class="sm:hidden">
                                    <th scope="colgroup" colspan="3" class="px-0 pb-1 pt-5 text-left text-[15px] font-medium leading-6 text-ink">{{ $task }}</th>
                                </tr>
                                <tr>
                                    <th scope="row" class="hidden py-3.5 pr-4 align-top text-[15px] font-normal leading-6 text-ink sm:table-cell">{{ $task }}</th>
                                    @foreach ($cells as $i => [$state, $note])
                                        @php $pick = $columns[$i]['pick']; @endphp
                                        <td class="px-2 pb-3 pt-2 text-center align-top sm:px-5 sm:py-3.5 {{ $pick ? $pickCell.($last ? ' sm:rounded-b-2xl sm:pb-7' : '') : '' }}">
                                            <span class="inline-flex p-1.5 sm:p-0">{!! $marks[$state] !!}</span>
                                            @if ($note)
                                                <span class="mt-1 block text-[12px] leading-4 text-muted">{{ $note }}</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- FAQ, to Nielsen Norman Group guidance:
                 - Real questions in the words students use, ordered by what most
                   often stops someone sharing the link (cost, sign-up, privacy),
                   not by what is easy to explain.
                 - Every answer leads with the direct answer ("Yes.", "No.",
                   "Anyone with the link."), then one or two plain sentences.
                 - No accordion. Every answer is a sentence or two and a reader
                   skimming wants most of them; hiding them costs a click each and
                   hides the answer a reader didn't know to look for. Shown openly
                   in two columns, the whole list is one scan.
                 - A way out for anything not answered, after the last answer,
                   where a reader who didn't find it ends up.
                 Limits come from config, so the page never states a number the
                 app does not enforce. --}}
            @php
                $capMb = (int) round(config('noteshare.workspace_storage_bytes') / 1_048_576);
                $faqs = [
                    ['Is SlipNote free?', 'Yes.', 'There is no paid plan and no limit on how many classmates use a board.'],
                    ['Do my classmates need an account?', 'No.', 'Nobody does, including you. Anyone with the link can open the board, download files and add files.'],
                    ['Who can see the files on a board?', 'Anyone with the link.', 'Boards are hidden from search engines and the links cannot be guessed, but a link can be passed on. Don’t upload anything you wouldn’t want a stranger to read.'],
                    ['What files can I upload?', 'PDF, Word, PowerPoint and images (PNG or JPG), up to 25 MB each.', "A board holds up to {$capMb} MB, more than a semester needs."],
                    ['Who can delete a file?', 'The person who uploaded it, and whoever set up the board.', 'After you upload, you get a link to delete your file. The person who set up the board can remove any file with their owner link. Everyone else can only read and add.'],
                    ['What if I lose my owner link?', 'We can email you a new one.', 'Add a recovery email on the board while you still have the owner link. Without one, the board stays up but can’t be managed.'],
                    ['What does SlipNote collect about me?', 'Almost nothing.', 'No accounts, no advertising and no tracking of individuals. It keeps the files you upload and a daily count of visits by country. The privacy page lists everything.'],
                ];
                $askWhatsApp = config('noteshare.feedback_whatsapp');
                $askEmail = config('noteshare.feedback_email');
                $askEmail = is_string($askEmail) && filter_var($askEmail, FILTER_VALIDATE_EMAIL) ? $askEmail : null;
            @endphp
            <section id="faq" class="scroll-mt-24 px-4 py-12 sm:px-6 sm:py-20">
                <div class="mx-auto max-w-5xl">
                    <h2 class="text-[26px] font-bold tracking-[-0.02em] text-ink sm:text-[32px]">Questions</h2>

                    <dl class="mt-10 grid gap-x-12 gap-y-9 md:grid-cols-2">
                        @foreach ($faqs as [$q, $lead, $rest])
                            <div>
                                <dt class="text-[16px] font-semibold leading-6 text-ink">{{ $q }}</dt>
                                <dd class="mt-2 max-w-prose text-[15px] leading-6 text-muted">
                                    <span class="font-medium text-ink">{{ $lead }}</span> {{ $rest }}
                                </dd>
                            </div>
                        @endforeach
                    </dl>

                    {{-- The way out, where a reader lands after the last answer:
                         one line, plain links, no box competing with the answers. --}}
                    @php $faqLink = 'font-medium text-ink underline decoration-muted/50 underline-offset-4 hover:decoration-ink'; @endphp
                    <p class="mt-10 text-[15px] leading-6 text-muted">
                        Didn’t find your answer?
                        @if ($askWhatsApp)
                            <a href="https://wa.me/{{ $askWhatsApp }}" class="{{ $faqLink }}" target="_blank" rel="noopener noreferrer">Ask on WhatsApp<span class="sr-only"> (opens in a new tab)</span></a>@if ($askEmail) or <a href="mailto:{{ $askEmail }}" class="{{ $faqLink }}">email us</a>@endif.
                        @elseif ($askEmail)
                            <a href="mailto:{{ $askEmail }}" class="{{ $faqLink }}">Email us</a>.
                        @else
                            <a href="https://github.com/otatechie/slipnote/issues" class="{{ $faqLink }}" target="_blank" rel="noopener noreferrer">Ask on GitHub<span class="sr-only"> (opens in a new tab)</span></a>.
                        @endif
                        <span class="whitespace-nowrap">See also the <a href="{{ route('privacy') }}" class="{{ $faqLink }}">privacy page</a> and <a href="{{ route('terms') }}" class="{{ $faqLink }}">terms</a>.</span>
                    </p>
                </div>
            </section>

            {{-- Close. One action, the same one as the top of the page, on a
                 sheet of ruled notebook paper: faint rules and a red margin line,
                 with the words written to the right of the margin. The paper is
                 background only; nothing on it competes with the button. --}}
            <section class="px-4 pb-16 pt-6 sm:px-6 sm:pb-24 sm:pt-10">
                <div class="lp-close mx-auto flex max-w-5xl flex-col items-start gap-5 overflow-hidden rounded-2xl py-8 pr-6 shadow-[0_1px_2px_rgba(16,24,40,0.05),0_16px_40px_-24px_rgba(16,24,40,0.3)] sm:flex-row sm:items-center sm:justify-between sm:gap-8 sm:py-10 sm:pr-10">
                    <div>
                        <h2 class="max-w-xl text-balance text-[22px] font-bold leading-8 tracking-[-0.02em] text-ink sm:text-[26px]">
                            Give your class one link for every file
                        </h2>
                        <p class="mt-1 max-w-lg text-pretty text-[15px] leading-8 text-muted">
                            Set it up once. Classmates open it, find what they need, and add what they have.
                        </p>
                    </div>
                    <a href="{{ route('start') }}" class="op-press inline-flex min-h-12 shrink-0 items-center justify-center gap-2 rounded-full bg-neon px-5 text-[15px] font-semibold text-white sm:min-h-11">
                        Create a board
                        <svg aria-hidden="true" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                    </a>
                </div>
            </section>
        </main>
    </div>
</x-layouts.app>
