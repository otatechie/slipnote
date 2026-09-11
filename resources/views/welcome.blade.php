<x-layouts.app
    title="Never ask “who has the notes?” again"
    description="The free, no-login board where your class keeps its notes, slides and past papers. One link, no accounts, no setup."
    :indexable="true">

    <x-slot:head>
        <link rel="canonical" href="{{ url('/') }}">

        <meta property="og:type" content="website">
        <meta property="og:site_name" content="SlipNote">
        <meta property="og:title" content="SlipNote: Never ask “who has the notes?” again">
        <meta property="og:description" content="One link for your whole class's notes, slides and past papers. Free, no accounts, no setup.">
        <meta property="og:url" content="{{ url('/') }}">
        <meta property="og:image" content="{{ url('/og.png') }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="SlipNote: Never ask “who has the notes?” again">
        <meta name="twitter:description" content="One link for your whole class's notes, slides and past papers. Free, no accounts, no setup.">
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
            "description": "The free, no-login board where your class keeps its notes, slides and past papers."
        }
        </script>
    </x-slot:head>

    <div class="lp min-h-screen flex-1">
        <header class="lp-nav sticky top-0 z-30 px-4 py-3 sm:px-6">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4">
                <a href="{{ route('welcome') }}" class="inline-flex items-center gap-2 text-[17px] font-bold text-ink">
                    <img src="/logo-mark.png" alt="" class="size-8">
                    SlipNote
                </a>
                <nav class="flex items-center gap-2" aria-label="Main navigation">
                    <a href="#how" class="hidden rounded-full px-3 py-2 text-[13px] font-semibold text-muted hover:text-ink sm:inline-flex">How it works</a>
                    <a href="{{ route('start') }}" class="lp-cta inline-flex min-h-11 items-center rounded-full bg-neon px-4 text-[13px] font-semibold text-white sm:min-h-9">
                        Create your board
                    </a>
                </nav>
            </div>
        </header>

        <main>
            <section class="px-4 pb-10 pt-8 sm:px-6 sm:pb-20 sm:pt-16">
                <div class="mx-auto grid max-w-6xl items-center gap-8 lg:grid-cols-[0.92fr_1.08fr] lg:gap-16">
                    <div class="text-center lg:text-left">
                        <h1 data-reveal class="text-[40px] font-bold leading-[1.03] tracking-[-0.045em] text-ink sm:text-[62px]">
                            Every class file.<br>
                            <span class="lp-marker">One easy link.</span>
                        </h1>
                        <p data-reveal style="--delay: 120ms" class="mx-auto mt-6 max-w-lg text-[16px] leading-7 text-muted sm:text-[18px] lg:mx-0">
                            Give notes, slides, and past papers a proper home—organized by course and ready when exams arrive.
                        </p>
                        <div data-reveal style="--delay: 180ms" class="mt-8 flex flex-col items-center gap-3 sm:flex-row sm:justify-center lg:justify-start">
                            <a href="{{ route('start') }}" class="lp-cta inline-flex min-h-12 w-full items-center justify-center rounded-full bg-neon px-7 text-[15px] font-semibold text-white sm:w-auto">
                                Create your board
                                <svg aria-hidden="true" class="ml-2 size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 10h12M11 5l5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </a>
                            <span class="text-[14px] text-muted">Free · no account needed</span>
                    </div>
                    </div>

                    {{-- No rules between rows. Each row already groups itself: the
                         type badge anchors the left edge and the title/meta pair is
                         2px apart against 20px between rows, so the hairlines were
                         drawing a boundary the spacing had already drawn. --}}
                    <div data-reveal style="--delay: 160ms" class="relative mx-auto w-full max-w-xl pt-3" role="img" aria-label="Example SlipNote board: the course CS 101 with three files">
                        <div class="lp-paper overflow-hidden">
                            <div class="px-5 pb-5 pt-4">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">Computer Science · Level 100</p>
                                <h2 class="mt-1 text-[22px] font-bold tracking-tight text-ink">CS 101</h2>
                                <p class="text-[13px] text-muted">Intro to Computer Science</p>
                            </div>
                            @foreach ([
                                ['kind' => 'PDF', 'title' => 'Week 7 quiz solutions', 'meta' => 'Kwame · 1.2 MB'],
                                ['kind' => 'PPTX', 'title' => 'Lecture 12 slides', 'meta' => 'Ama · 4.8 MB'],
                                ['kind' => 'PDF', 'title' => '2024 past paper', 'meta' => 'Yaw · 820 KB'],
                            ] as $file)
                                <div class="flex items-center justify-between gap-4 px-5 py-2.5 first:pt-0 last:pb-5">
                                    <div class="flex min-w-0 items-start gap-3">
                                        <span class="mt-0.5 rounded border border-sky/80 bg-base px-1.5 py-0.5 text-[10px] font-bold text-muted">{{ $file['kind'] }}</span>
                                        <div class="min-w-0">
                                            <p class="truncate text-[14px] font-semibold text-ink">{{ $file['title'] }}</p>
                                            <p class="mt-0.5 text-[12px] text-muted">{{ $file['meta'] }}</p>
                                        </div>
                                    </div>
                                    {{-- The secondary button /start ships: rounded-full,
                                         border-muted/50, bg-base, muted label. Muted rather than
                                         the accent because a control repeating down every row
                                         stops reading as "the action here" -- and three blue links
                                         beside the real CTA spend the page's one accent. --}}
                                    <span class="shrink-0 rounded-full border border-muted/50 bg-surface px-3 py-1.5 text-[12px] font-semibold text-muted">Download</span>
                                </div>
                            @endforeach
                    </div>
                    </div>
                </div>
            </section>

            <section id="how" class="px-4 py-10 sm:px-6 sm:py-20">
                <div class="mx-auto max-w-5xl">
                    <div data-reveal class="mx-auto max-w-2xl text-center">
                        <h2 class="text-[31px] font-bold tracking-[-0.035em] text-ink sm:text-[42px]">Three steps. No setup headache.</h2>
                    </div>
                    {{-- A numbered run, not three cards. Cards are the grid pattern:
                         parallel, unordered, browse in any direction. These are a
                         sequence, and the heading says so. The connecting rule is
                         what carries the order; the numerals only label it. --}}
                    <ol class="lp-steps mt-10 sm:mt-14">
                        @foreach ([
                            ['title' => 'Name the board', 'body' => 'Create one board for your class, year, or study group.'],
                            ['title' => 'Add the courses', 'body' => 'Give every subject its own tidy place for files.'],
                            ['title' => 'Share one link', 'body' => 'Classmates can open it and contribute without an account.'],
                        ] as $i => $step)
                            <li data-reveal style="--delay: {{ $i * 90 }}ms" class="lp-step">
                                <span class="lp-step-mark" aria-hidden="true">{{ $i + 1 }}</span>
                                <h3 class="text-[19px] font-bold text-ink">{{ $step['title'] }}</h3>
                                <p class="mt-2 text-[14px] leading-6 text-muted">{{ $step['body'] }}</p>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </section>

            <section class="px-4 py-10 sm:px-6 sm:py-20">
                <div class="mx-auto grid max-w-6xl gap-9 lg:gap-12 lg:grid-cols-[0.85fr_1.15fr] lg:items-center">
                    <div data-reveal>
                        <h2 class="text-[31px] font-bold tracking-[-0.035em] text-ink sm:text-[42px]">Files deserve somewhere findable.</h2>
                        <p class="mt-5 max-w-lg text-[16px] leading-7 text-muted">
                            Stop scrolling through weeks of messages before every exam. SlipNote keeps the ease of sharing a link and adds the organization your class needs.
                        </p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach ([
                            [
                                'title' => 'No accounts',
                                'body' => 'Open the link and start using it. Nothing to install or remember.',
                                'icon' => '<path d="M101.85,191.14C97.34,201,82.29,224,40,224a8,8,0,0,1-8-8c0-42.29,23-57.34,32.86-61.85a8,8,0,0,1,6.64,14.56c-6.43,2.93-20.62,12.36-23.12,38.91,26.55-2.5,36-16.69,38.91-23.12a8,8,0,1,1,14.56,6.64Zm122-144a16,16,0,0,0-15-15c-12.58-.75-44.73.4-71.4,27.07h0L88,108.7A8,8,0,0,1,76.67,97.39l26.56-26.57A4,4,0,0,0,100.41,64H74.35A15.9,15.9,0,0,0,63,68.68L28.7,103a16,16,0,0,0,9.07,27.16l38.47,5.37,44.21,44.21,5.37,38.49a15.94,15.94,0,0,0,10.78,12.92,16.11,16.11,0,0,0,5.1.83A15.91,15.91,0,0,0,153,227.3L187.32,193A16,16,0,0,0,192,181.65V155.59a4,4,0,0,0-6.83-2.82l-26.57,26.56a8,8,0,0,1-11.71-.42,8.2,8.2,0,0,1,.6-11.1l49.27-49.27h0C223.45,91.86,224.6,59.71,223.85,47.12Z"/>',
                            ],
                            [
                                'title' => 'Built for files',
                                'body' => 'Preview, search, download one, or grab an entire section.',
                                'icon' => '<path d="M224,64H154.67L126.93,43.2a16.12,16.12,0,0,0-9.6-3.2H72A16,16,0,0,0,56,56V72H40A16,16,0,0,0,24,88V200a16,16,0,0,0,16,16H192.89A15.13,15.13,0,0,0,208,200.89V184h16.89A15.13,15.13,0,0,0,240,168.89V80A16,16,0,0,0,224,64Zm0,104H208V112a16,16,0,0,0-16-16H122.67L94.93,75.2a16.12,16.12,0,0,0-9.6-3.2H72V56h45.33L147.2,78.4A8,8,0,0,0,152,80h72Z"/>',
                            ],
                            [
                                'title' => 'Easy to share',
                                'body' => 'Copy the link or show its QR code in the lecture hall.',
                                'icon' => '<path d="M232,96a16,16,0,0,0-16-16H184V48a16,16,0,0,0-16-16H40A16,16,0,0,0,24,48V176a8,8,0,0,0,13,6.22L72,154V184a16,16,0,0,0,16,16h93.59L219,230.22a8,8,0,0,0,5,1.78,8,8,0,0,0,8-8Zm-42.55,89.78a8,8,0,0,0-5-1.78H88V152h80a16,16,0,0,0,16-16V96h32V207.25Z"/>',
                            ],
                            [
                                'title' => 'Owner controls',
                                'body' => 'A private owner link handles courses, files, and recovery.',
                                'icon' => '<path d="M216,130.16q.06-2.16,0-4.32l14.92-18.64a8,8,0,0,0,1.48-7.06,107.6,107.6,0,0,0-10.88-26.25,8,8,0,0,0-6-3.93l-23.72-2.64q-1.48-1.56-3-3L186,40.54a8,8,0,0,0-3.94-6,107.29,107.29,0,0,0-26.25-10.86,8,8,0,0,0-7.06,1.48L130.16,40Q128,40,125.84,40L107.2,25.11a8,8,0,0,0-7.06-1.48A107.6,107.6,0,0,0,73.89,34.51a8,8,0,0,0-3.93,6L67.32,64.27q-1.56,1.49-3,3L40.54,70a8,8,0,0,0-6,3.94,107.71,107.71,0,0,0-10.87,26.25,8,8,0,0,0,1.49,7.06L40,125.84Q40,128,40,130.16L25.11,148.8a8,8,0,0,0-1.48,7.06,107.6,107.6,0,0,0,10.88,26.25,8,8,0,0,0,6,3.93l23.72,2.64q1.49,1.56,3,3L70,215.46a8,8,0,0,0,3.94,6,107.71,107.71,0,0,0,26.25,10.87,8,8,0,0,0,7.06-1.49L125.84,216q2.16.06,4.32,0l18.64,14.92a8,8,0,0,0,7.06,1.48,107.21,107.21,0,0,0,26.25-10.88,8,8,0,0,0,3.93-6l2.64-23.72q1.56-1.48,3-3L215.46,186a8,8,0,0,0,6-3.94,107.71,107.71,0,0,0,10.87-26.25,8,8,0,0,0-1.49-7.06ZM128,168a40,40,0,1,1,40-40A40,40,0,0,1,128,168Z"/>',
                            ],
                        ] as $i => $benefit)
                            <article class="lp-proof p-5">
                                {{-- An icon per card, not four identical ticks. A tick asserts
                                     "done / included"; four side by side assert nothing and
                                     differentiate nothing. These depict what each card is
                                     about, so the row can be scanned by shape. All four are
                                     the fill weight: the lightning and key here were regular
                                     and bold, so two icons read as outlines beside two solids.
                                     Phosphor Icons (phosphor-icons.com) — MIT, © Phosphor
                                     Icons. Solid weights, inlined rather than added as a
                                     dependency for four glyphs. aria-hidden because the
                                     heading beside each already says it. --}}
                                <span class="lp-proof-mark">
                                    <svg aria-hidden="true" class="size-[19px]" viewBox="0 0 256 256" fill="currentColor">
                                        {!! $benefit['icon'] !!}
                                    </svg>
                                </span>
                                <h3 class="mt-3 text-[16px] font-bold text-ink">{{ $benefit['title'] }}</h3>
                                <p class="mt-1.5 text-[13px] leading-5 text-muted">{{ $benefit['body'] }}</p>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="lp-halo px-4 pb-12 pt-8 text-center sm:px-6 sm:pb-24 sm:pt-16">
                <div data-reveal class="mx-auto max-w-3xl">
                    {{-- No card. A white slab covering the ruling is the least notebook-like
                         thing that could close a notebook page, and it was mostly air. The
                         closing ask sits on the paper itself. --}}
                    <h2 class="text-[26px] font-bold tracking-[-0.035em] text-ink sm:text-[34px]">Make the board before the next file gets lost.</h2>
                    <p class="mx-auto mt-4 max-w-xl text-[16px] leading-7 text-muted">Name it, add your courses, and share one link. It takes less than a minute.</p>
                    <a href="{{ route('start') }}" class="lp-cta mt-8 inline-flex min-h-12 items-center rounded-full bg-neon px-8 text-[15px] font-semibold text-white">Create your board</a>
                </div>
            </section>
        </main>
    </div>
</x-layouts.app>
