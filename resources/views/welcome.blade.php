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

    {{-- The whole page sits on .op so the shared tokens resolve, and stays on
         bg-base throughout: op-card is bg-surface, so cards only lift if the
         page underneath them doesn't move. Sections are separated by the same
         hairline the rest of the app uses, not by alternating bands. --}}
    <div class="op flex-1">

        <header class="op-top px-4 sm:px-5">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-3">
                <a href="{{ route('welcome') }}"
                   class="inline-flex min-w-0 items-center gap-2 py-1 text-[16px] font-bold tracking-[-0.01em] text-teal transition hover:opacity-80 sm:text-[17px]">
                    {{-- alt="" because the wordmark beside it already names the site;
                         alt="SlipNote logo" made a screen reader say it twice. --}}
                    <img src="/logo-mark.png" alt="" class="size-7 shrink-0 sm:size-8">
                    SlipNote
                </a>

                {{-- No nav links: it's a one-scroll page and Privacy lives in the
                     footer, so the header's only job is the CTA. --}}
                <a href="{{ route('start') }}"
                   class="op-press inline-flex h-11 shrink-0 items-center justify-center rounded-full bg-neon px-4 text-[13px] font-semibold text-white sm:h-9">
                    <span class="sm:hidden">Create board</span>
                    <span class="hidden sm:inline">Create your board</span>
                </a>
            </div>
        </header>

        <section class="relative isolate overflow-hidden px-4 pb-16 pt-12 sm:px-5 sm:pb-24 sm:pt-20">
            {{-- Two layers behind the hero, both pointer-events-none and both
                 derived from the theme tokens: a grid for structure and an accent
                 wash for depth. They are the only decoration on the page, so the
                 rest can stay as quiet as the product. --}}
            <div class="lp-grid pointer-events-none absolute inset-x-0 top-0 -z-20 h-140" aria-hidden="true"></div>
            <div class="lp-glow pointer-events-none absolute inset-x-0 -top-24 -z-10 h-155" aria-hidden="true"></div>

            <div class="mx-auto grid max-w-6xl items-center gap-10 sm:gap-14 lg:grid-cols-[1.05fr_1fr] lg:gap-12">
                <div class="text-center lg:text-left">
                    <h1 class="op-title text-[34px] font-bold text-ink sm:text-[52px] lg:text-[56px]">
                        Your whole class's notes, <span class="text-neon">in one link.</span>
                    </h1>
                    <p class="mx-auto mt-4 max-w-md text-[15px] leading-relaxed text-muted sm:mt-6 sm:text-[17px] lg:mx-0">
                        Stop asking “who has the notes?” in the group chat. One board
                        for your class, a space for each course, one link everyone keeps.
                    </p>
                    <div class="mt-6 flex flex-col items-center gap-3 sm:mt-8 sm:flex-row sm:justify-center lg:justify-start">
                        <a href="{{ route('start') }}"
                           class="op-press inline-flex min-h-11 w-full items-center justify-center rounded-full bg-neon px-6 text-[15px] font-semibold text-white sm:w-auto">
                            Create your board
                        </a>
                        <p class="text-[13px] text-muted">No account, no setup — under a minute.</p>
                    </div>
                </div>

                {{-- Deliberately the same vocabulary as a real course page: op-card,
                     hairline rows, the app's type badge and Download pill. A mock
                     drawn in a style the product doesn't use promises the wrong
                     thing to anyone who clicks through. --}}
                <div class="mx-auto w-full max-w-md lg:max-w-none"
                     role="img"
                     aria-label="Example board: the Computer Science Level 100 class, showing its CS 101 course with three shared files (a quiz solution, lecture slides and a past paper), with a Download-all option for the whole section.">
                    <div class="op-card lp-mock overflow-hidden" aria-hidden="true">
                        <div class="flex items-center gap-1.5 border-b border-sky px-4 py-3">
                            <span class="size-2.5 rounded-full bg-muted/25"></span>
                            <span class="size-2.5 rounded-full bg-muted/25"></span>
                            <span class="size-2.5 rounded-full bg-muted/25"></span>
                            <span class="ml-2 truncate text-[12px] text-muted">slipnote.co/cs-level-100/c/cs-101</span>
                        </div>
                        <div class="flex items-start justify-between gap-3 border-b border-sky px-4 py-4 sm:px-5">
                            <div class="min-w-0">
                                <p class="op-kicker text-[11px] font-semibold uppercase text-muted">CS · Level 100</p>
                                <p class="op-title mt-1 text-[16px] font-bold text-ink">CS 101</p>
                                <p class="mt-0.5 text-[13px] text-muted">Intro to Computer Science</p>
                            </div>
                            <span class="hidden shrink-0 text-[12px] font-semibold text-neon sm:block">Download all (3)</span>
                        </div>
                        @foreach ([
                            ['kind' => 'PDF',  'title' => 'Week 7 quiz solutions', 'by' => 'Kwame', 'size' => '1.2 MB'],
                            ['kind' => 'PPTX', 'title' => 'Lecture 12 slides',     'by' => 'Ama',   'size' => '4.8 MB'],
                            ['kind' => 'PDF',  'title' => '2024 past paper',       'by' => 'Yaw',   'size' => '820 KB'],
                        ] as $file)
                            <div class="op-row flex items-center justify-between gap-3">
                                <div class="flex min-w-0 items-start gap-3">
                                    <span class="mt-0.5 shrink-0 rounded bg-sky/50 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted">{{ $file['kind'] }}</span>
                                    <div class="min-w-0">
                                        <span class="block truncate text-[14px] font-semibold text-ink underline decoration-muted/40 underline-offset-4">{{ $file['title'] }}</span>
                                        <span class="mt-0.5 block truncate text-[12px] text-muted">{{ $file['by'] }} · {{ $file['size'] }}</span>
                                    </div>
                                </div>
                                <span class="inline-flex shrink-0 items-center rounded-full border border-sky bg-base px-3.5 py-2 text-[13px] font-semibold text-muted">Download</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        <section id="how" class="border-t border-sky px-4 py-12 sm:px-5 sm:py-20">
            <div class="mx-auto max-w-6xl">
                <p class="op-kicker mb-2 text-center text-[11px] font-semibold uppercase text-muted">How it works</p>
                <h2 class="op-title mb-8 text-center text-[26px] font-bold text-ink sm:mb-12 sm:text-[34px]">
                    Three steps to a shared board
                </h2>
                <ol class="grid gap-4 sm:grid-cols-3 sm:gap-5">
                    @foreach ([
                        ['title' => 'Name your board', 'body' => 'Make one for your class and add a space for each course.'],
                        ['title' => 'Share the link',  'body' => 'Drop it in your class group chat. That\'s it.'],
                        ['title' => 'Everyone chips in', 'body' => 'Classmates open a course and add slides, papers and notes.'],
                    ] as $i => $step)
                        <li class="op-card lp-lift px-6 py-7 sm:px-7 sm:py-8">
                            {{-- The accent earns its place here: three numerals are
                                 the only thing telling you this is a sequence. --}}
                            <span class="flex size-9 items-center justify-center rounded-full border border-neon/30 bg-neon/10 text-[14px] font-bold tabular-nums text-neon"
                                  aria-hidden="true">{{ $i + 1 }}</span>
                            <h3 class="op-title mt-4 text-[18px] font-bold text-ink sm:text-[20px]">{{ $step['title'] }}</h3>
                            <p class="mt-2 text-[14px] leading-relaxed text-muted">{{ $step['body'] }}</p>
                        </li>
                    @endforeach
                </ol>

                <ul class="mt-8 flex flex-wrap items-center justify-center gap-2 sm:mt-12">
                    @foreach ([
                        'PDFs, Word, PowerPoint & images',
                        '"Download all" before exams',
                        'Preview before you download',
                        'QR code for the lecture hall',
                        'Private owner link',
                    ] as $chip)
                        <li class="rounded-full border border-sky bg-surface px-3.5 py-1.5 text-[13px] text-muted">{{ $chip }}</li>
                    @endforeach
                </ul>
            </div>
        </section>

        <section id="why" class="border-t border-sky px-4 py-12 sm:px-5 sm:py-20">
            <div class="mx-auto grid max-w-6xl gap-10 lg:grid-cols-[0.9fr_1.1fr] lg:items-center">
                <div>
                    <p class="op-kicker mb-2 text-[11px] font-semibold uppercase text-muted">Why a board</p>
                    <h2 class="op-title max-w-md text-[26px] font-bold text-ink sm:text-[34px]">
                        Better than losing files in a busy class group chat
                    </h2>
                    <p class="mt-4 max-w-lg text-[15px] leading-relaxed text-muted sm:text-[16px]">
                        Group chats are great for talking, but files get buried fast.
                        SlipNote keeps sharing just as easy (drop a file in) while keeping everything findable a week later.
                    </p>
                    {{-- Three claims, three chips. As one muted sentence this was the
                         page's strongest selling line rendered as its quietest text. --}}
                    <ul class="mt-6 flex flex-wrap gap-2">
                        @foreach (['Free', 'Open source', 'No accounts, ever'] as $claim)
                            <li class="inline-flex items-center gap-1.5 rounded-full border border-neon/25 bg-neon/10 px-3 py-1.5 text-[13px] font-semibold text-neon">
                                <svg aria-hidden="true" class="size-3.5 shrink-0" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 10.5l4 4 8-9" />
                                </svg>
                                {{ $claim }}
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 sm:gap-5">
                    @foreach ([
                        ['title' => 'Works anywhere', 'body' => 'Opens in any browser on any phone or laptop — nothing to install.'],
                        ['title' => 'Always findable', 'body' => 'The newest files sit on top and search finds the rest — nothing scrolls out of reach weeks later.'],
                        ['title' => 'Share on your terms', 'body' => 'Add your name or stay anonymous — your call on every file you upload.'],
                        ['title' => 'Safer ownership', 'body' => 'One person owns the board, with a private link to recover access anytime.'],
                    ] as $benefit)
                        <div class="op-card lp-lift px-5 py-6 sm:px-6 sm:py-7">
                            <h3 class="text-[16px] font-semibold text-ink">{{ $benefit['title'] }}</h3>
                            <p class="mt-2 text-[14px] leading-relaxed text-muted">{{ $benefit['body'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="relative isolate overflow-hidden border-t border-sky px-4 py-12 sm:px-5 sm:py-20">
            {{-- Same accent wash as the hero, mirrored, so the page closes on the
                 note it opened with instead of trailing off into flat grey. --}}
            <div class="lp-glow pointer-events-none absolute inset-x-0 bottom-0 -z-10 h-105 rotate-180" aria-hidden="true"></div>
            <div class="op-card lp-mock mx-auto max-w-5xl px-6 py-8 sm:px-10 sm:py-12">
                <div class="grid gap-6 lg:grid-cols-[1fr_auto] lg:items-center">
                    <div>
                        <h2 class="op-title max-w-xl text-[26px] font-bold text-ink sm:text-[32px]">
                            Make your class's board in a minute
                        </h2>
                        <p class="mt-2.5 max-w-2xl text-[15px] leading-relaxed text-muted">
                            Name it, share the link, and let everyone add their files. No account, no setup.
                        </p>
                    </div>
                    <a href="{{ route('start') }}"
                       class="op-press inline-flex min-h-11 w-full shrink-0 items-center justify-center rounded-full bg-neon px-6 text-[15px] font-semibold text-white sm:w-auto">
                        Create your board
                    </a>
                </div>
            </div>
        </section>
    </div>
</x-layouts.app>
