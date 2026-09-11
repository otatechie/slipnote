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

    <section class="hero-bg nb-paper relative isolate flex-1 overflow-hidden bg-white">
        <div class="hero-vignette pointer-events-none absolute inset-x-0 bottom-0 -z-10 h-40" aria-hidden="true"></div>
        <header class="relative px-4 pt-4 sm:px-5 sm:pt-6">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 rounded-full border border-sky/80 bg-surface px-3 py-2.5 shadow-[0_6px_18px_-16px_rgba(0,0,0,0.35)] backdrop-blur sm:px-6 sm:py-3">
                <a href="{{ route('welcome') }}" class="inline-flex min-w-0 items-center gap-2 text-[16px] font-bold tracking-[-0.01em] text-teal sm:text-[18px]">
                    <img src="/logo-mark.png" alt="SlipNote logo" class="h-8 w-8 shrink-0 sm:h-9 sm:w-9">
                    SlipNote
                </a>

                {{-- No nav links: it's a one-scroll page and Privacy lives in the
                     footer, so the header's only job is the CTA. --}}
                <a href="{{ route('start') }}"
                   class="inline-flex shrink-0 items-center justify-center rounded-full bg-neon px-3 py-2 text-[12px] font-semibold text-white shadow-[0_3px_0_0_#1e3a8a] transition-all hover:translate-y-0.5 hover:shadow-[0_1px_0_0_#1e3a8a] active:translate-y-0.5 active:shadow-none sm:px-4 sm:py-2.5 sm:text-[13px]">
                    <span class="sm:hidden">Create board</span>
                    <span class="hidden sm:inline">Create your board</span>
                </a>
            </div>
        </header>

        <section class="relative px-4 pt-6 pb-10 sm:px-5 sm:pt-14 sm:pb-20">
            <div class="mx-auto grid max-w-6xl items-center gap-9 sm:gap-14 lg:grid-cols-[1.05fr_1fr] lg:gap-10">
                <div class="text-center lg:text-left">
                    <h1 class="text-[34px] font-bold leading-[1.06] tracking-[-0.03em] text-teal sm:text-[52px] lg:text-[58px]">
                        Your whole class's notes,
                        <span class="relative inline-block text-neon sm:whitespace-nowrap">
                            in one link.
                            {{-- thick marker underline, double-stroked like a real pen pass --}}
                            <svg class="pointer-events-none absolute -bottom-3 left-0 w-full text-neon" height="16" viewBox="0 0 240 16" preserveAspectRatio="none" fill="none" aria-hidden="true">
                                <path d="M4 9 Q 34 3 74 7 T 148 6 T 236 7" stroke="currentColor" stroke-width="5" stroke-linecap="round"/>
                                <path d="M10 13 Q 50 8 95 11 T 200 10" stroke="currentColor" stroke-width="3" stroke-linecap="round" opacity="0.55"/>
                            </svg>
                        </span>
                    </h1>
                    <p class="mx-auto mt-4 max-w-md text-[15px] leading-relaxed text-muted sm:mt-7 sm:text-[17px] lg:mx-0">
                        Stop asking “who has the notes?” in the group chat. One board
                        for your class, a space for each course, one link everyone keeps.
                    </p>
                    <div class="mt-5 flex flex-col items-center sm:mt-8 lg:items-start">
                        <a href="{{ route('start') }}"
                           class="inline-flex w-full max-w-xs items-center justify-center rounded-full bg-neon px-7 py-3.5 text-[15px] font-bold text-white shadow-[0_4px_0_0_#1e3a8a] transition-all hover:translate-y-0.5 hover:shadow-[0_2px_0_0_#1e3a8a] active:translate-y-1 active:shadow-none sm:w-auto">
                            Create your board
                        </a>
                    </div>
                    <p class="mt-3 font-hand text-[19px] text-muted lg:-rotate-1 lg:pl-1">
                        no account, no setup — takes under a minute ✓
                    </p>
                </div>

                <div class="group relative mx-auto w-full max-w-md lg:max-w-none" role="img" aria-label="Example board: the Computer Science Level 100 class, showing its CS 101 course with three shared files (a quiz solution, lecture slides and a past paper), with a Download-all option for the whole section.">
                    <div class="hero-mock overflow-hidden rounded-lg border border-sky bg-surface text-left shadow-[0_18px_50px_-22px_rgba(0,0,0,0.3)] transition-transform duration-300 rotate-1 group-hover:rotate-0 sm:rotate-2" aria-hidden="true">
                        <div class="flex items-center gap-1.5 border-b border-sky px-4 py-3">
                            <span class="h-2.5 w-2.5 rounded-full bg-red-400/70" aria-hidden="true"></span>
                            <span class="h-2.5 w-2.5 rounded-full bg-yellow-400/70" aria-hidden="true"></span>
                            <span class="h-2.5 w-2.5 rounded-full bg-green-400/70" aria-hidden="true"></span>
                            <span class="ml-2 truncate text-[12px] text-muted">slipnote.co/cs-level-100/c/cs-101</span>
                        </div>
                        <div class="flex items-start justify-between gap-3 px-4 py-4 sm:px-5">
                            <div class="min-w-0">
                                <p class="text-[11px] font-semibold text-muted">CS · Level 100</p>
                                <p class="mt-0.5 text-[14px] font-bold text-teal sm:text-[15px]">CS 101 · Intro to Computer Science</p>
                            </div>
                            <span class="hidden text-[12.5px] font-semibold text-neon sm:block">↓ Download all (3)</span>
                        </div>
                        <ul class="divide-y divide-sky border-t border-sky">
                            @foreach ([
                                ['kind' => 'PDF',  'tag' => 'filetag filetag-pdf',  'title' => 'Week 7 quiz solutions', 'by' => 'Kwame'],
                                ['kind' => 'PPTX', 'tag' => 'filetag filetag-pptx', 'title' => 'Lecture 12 slides',      'by' => 'Ama'],
                                ['kind' => 'PDF',  'tag' => 'filetag filetag-pdf',  'title' => '2024 past paper',        'by' => 'Yaw'],
                            ] as $file)
                                <li class="flex items-center gap-2 px-3 py-3.5 transition hover:bg-base/60 sm:gap-3 sm:px-5">
                                    <span class="inline-flex w-10 shrink-0 justify-center rounded-md px-2 py-1 text-[10px] font-bold tracking-wide sm:w-11 {{ $file['tag'] }}">{{ $file['kind'] }}</span>
                                    <span class="min-w-0 flex-1 truncate text-[13px] font-semibold text-ink sm:text-[14px]">{{ $file['title'] }}</span>
                                    <span class="hidden text-[12.5px] text-muted sm:block">{{ $file['by'] }}</span>
                                    <span class="shrink-0 rounded-full border border-sky bg-base px-2.5 py-1 text-[11px] font-semibold text-ink shadow-[0_1px_2px_rgba(51,29,44,0.12)] sm:px-3 sm:text-[12px]">Open</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </section>
    </section>

    <section id="how" class="border-t border-sky bg-surface px-4 py-10 sm:px-5 sm:py-20">
        <div class="mx-auto max-w-6xl">
            <h2 class="mb-7 text-center text-[24px] font-semibold tracking-[-0.01em] text-teal sm:mb-12 sm:text-[28px]">
                Three steps to a
                <span class="relative inline-block whitespace-nowrap">
                    shared board
                    <svg class="absolute -bottom-1.5 left-0 w-full text-neon" height="10" viewBox="0 0 240 10" preserveAspectRatio="none" fill="none" aria-hidden="true">
                        <path d="M3 7 Q 40 2 80 5 T 160 4 T 237 5" stroke="currentColor" stroke-width="3.5" stroke-linecap="round"/>
                    </svg>
                </span>
            </h2>
            <ol class="grid gap-7 pt-4 sm:grid-cols-3 sm:gap-6">
                @foreach ([
                    [
                        'title' => 'Name your board',
                        'body' => 'Make one for your class and add a space for each course.',
                        'tint' => 'nb-tint-rose',
                        'tilt' => '-rotate-[1.5deg]',
                    ],
                    [
                        'title' => 'Share the link',
                        'body' => 'Drop it in your class group chat. That\'s it.',
                        'tint' => 'nb-tint-sky',
                        'tilt' => 'rotate-1',
                    ],
                    [
                        'title' => 'Everyone chips in',
                        'body' => 'Classmates open a course and add slides, papers and notes.',
                        'tint' => 'nb-tint-amber',
                        'tilt' => '-rotate-1',
                    ],
                ] as $i => $step)
                    <li class="nb-sticky {{ $step['tint'] }} {{ $step['tilt'] }} relative px-6 pb-8 pt-7 transition-transform duration-200 hover:rotate-0 sm:px-8 sm:pb-10 sm:pt-9">
                        <span class="nb-tape -top-3 left-1/2 -translate-x-1/2 -rotate-2" aria-hidden="true"></span>
                        <span class="font-hand text-[34px] font-bold leading-none text-neon sm:text-[40px]" aria-hidden="true">{{ $i + 1 }}.</span>
                        {{-- A real heading. At 26px semibold this reads as one, but as
                             a <p> it was invisible to heading navigation: the page
                             outline went h1 then three h2s, and none of the seven
                             titles carrying the actual content. --}}
                        <h3 class="mt-2 text-[18px] font-semibold leading-[1.1] tracking-[-0.02em] text-teal sm:mt-3 sm:text-[26px]">{{ $step['title'] }}</h3>
                        <p class="mt-2.5 text-[15px] leading-relaxed text-muted">{{ $step['body'] }}</p>
                    </li>
                @endforeach
            </ol>

            <ul class="mt-8 flex flex-wrap items-center justify-center gap-2 sm:mt-14">
                @foreach ([
                    'PDFs, Word, PowerPoint & images',
                    '"Download all" before exams',
                    'Preview before you download',
                    'QR code for the lecture hall',
                    'Private owner link',
                ] as $chip)
                    <li class="feature-chip rounded-sm border border-dashed border-sky bg-base px-3.5 py-1 font-hand text-[17px] text-muted odd:rotate-1 even:-rotate-1">{{ $chip }}</li>
                @endforeach
            </ul>
        </div>
    </section>

    <section id="why" class="border-t border-sky bg-surface px-4 py-9 sm:px-5 sm:py-20">
        <div class="mx-auto grid max-w-6xl gap-8 lg:grid-cols-[0.9fr_1.1fr] lg:items-center">
            <div>
                <h2 class="max-w-md text-[24px] font-semibold leading-[1.08] tracking-[-0.03em] text-teal sm:text-[34px]">
                    Better than losing files in a busy class group chat
                </h2>
                <p class="mt-3 max-w-lg text-[15px] leading-relaxed text-muted sm:mt-4 sm:text-[16px]">
                    Group chats are great for talking, but files get buried fast.
                    SlipNote keeps sharing just as easy (drop a file in) while keeping everything findable a week later.
                </p>
                <p class="mt-4 font-hand text-[20px] text-neon sm:mt-5 lg:-rotate-1">
                    free · open source · no accounts, ever
                </p>
            </div>

            <div class="grid gap-6 sm:grid-cols-2">
                @foreach ([
                    [
                        'title' => 'Works anywhere',
                        'body' => 'Opens in any browser on any phone or laptop — nothing to install.',
                        'tilt' => '-rotate-1',
                    ],
                    [
                        'title' => 'Always findable',
                        'body' => 'The newest files sit on top and search finds the rest — nothing scrolls out of reach weeks later.',
                        'tilt' => 'rotate-1',
                    ],
                    [
                        'title' => 'Share on your terms',
                        'body' => 'Add your name or stay anonymous — your call on every file you upload.',
                        'tilt' => 'rotate-1',
                    ],
                    [
                        'title' => 'Safer ownership',
                        'body' => 'One person owns the board, with a private link to recover access anytime.',
                        'tilt' => '-rotate-1',
                    ],
                ] as $benefit)
                    <div class="{{ $benefit['tilt'] }} relative rounded-lg border border-sky bg-base px-5 pb-6 pt-7 shadow-[0_4px_14px_-12px_rgba(51,29,44,0.3)] transition-transform duration-200 hover:rotate-0 sm:px-7 sm:pb-8 sm:pt-9">
                        <h3 class="text-[17px] font-semibold text-teal">{{ $benefit['title'] }}</h3>
                        <p class="mt-2.5 text-[14px] leading-relaxed text-muted">{{ $benefit['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="nb-paper border-t border-sky px-4 py-12 sm:px-5 sm:py-24">
        <div class="nb-sticky nb-cta nb-tint-sky relative mx-auto max-w-5xl -rotate-1 px-5 py-7 sm:px-10 sm:py-12">
            <span class="nb-tape -top-3 left-8 -rotate-6" aria-hidden="true"></span>
            <span class="nb-tape -top-3 right-8 rotate-3" aria-hidden="true"></span>
            <div class="grid gap-6 lg:grid-cols-[1fr_auto] lg:items-center">
                <div>
                    <h2 class="max-w-xl text-[24px] font-semibold leading-[1.08] tracking-[-0.03em] text-teal sm:text-[34px]">
                        Make your class's board in a minute
                    </h2>
                    <p class="mt-2.5 max-w-2xl text-[15px] leading-relaxed text-muted sm:mt-3">
                        Name it, share the link, and let everyone add their files. No account, no setup.
                    </p>
                </div>
                <div class="flex flex-col items-center gap-2 lg:items-stretch">
                    <a href="{{ route('start') }}"
                       class="inline-flex w-full max-w-xs items-center justify-center rounded-full bg-neon px-7 py-3.5 text-[15px] font-bold text-white shadow-[0_4px_0_0_#1e3a8a] transition-all hover:translate-y-0.5 hover:shadow-[0_2px_0_0_#1e3a8a] active:translate-y-1 active:shadow-none sm:w-auto">
                        Create your board
                    </a>
                </div>
            </div>
        </div>
    </section>
</x-layouts.app>
