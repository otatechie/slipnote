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

    <section class="hero-bg relative isolate flex-1 overflow-hidden bg-white">
        <div class="hero-vignette pointer-events-none absolute inset-x-0 bottom-0 -z-10 h-40" aria-hidden="true"></div>
        <header class="relative px-4 pt-4 sm:px-5 sm:pt-6">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 rounded-full border border-sky/80 bg-surface/85 px-3 py-2.5 shadow-[0_10px_30px_-24px_rgba(0,0,0,0.45)] backdrop-blur sm:px-6 sm:py-3">
                <a href="{{ route('welcome') }}" class="inline-flex min-w-0 items-center gap-2 text-[13px] font-bold tracking-[-0.01em] text-teal sm:text-[14px]">
                    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-neon text-[14px] text-white shadow-[0_4px_12px_-6px_rgba(91,91,214,0.85)] sm:h-9 sm:w-9 sm:text-[15px]">S</span>
                    SlipNote
                </a>

                <nav class="hidden items-center gap-6 text-[14px] font-medium text-muted md:flex">
                    <a href="#how" class="transition hover:text-neon">How it works</a>
                    <a href="#why" class="transition hover:text-neon">Why SlipNote</a>
                    <a href="{{ route('privacy') }}" class="transition hover:text-neon">Privacy</a>
                </nav>

                <a href="{{ route('start') }}"
                   class="inline-flex shrink-0 items-center justify-center rounded-full bg-neon px-3 py-2 text-[12px] font-semibold text-white transition hover:brightness-110 sm:px-4 sm:py-2.5 sm:text-[13px]">
                    <span class="sm:hidden">Create board</span>
                    <span class="hidden sm:inline">Create your board</span>
                </a>
            </div>
        </header>

        <section class="relative px-4 pt-6 pb-10 sm:px-5 sm:pt-14 sm:pb-20">
            <div class="mx-auto grid max-w-6xl items-center gap-9 sm:gap-14 lg:grid-cols-[1.05fr_1fr] lg:gap-10">
                <div class="text-center lg:text-left">
                    <h1 class="text-[34px] font-bold leading-[1.02] tracking-[-0.03em] text-teal sm:text-[52px] lg:text-[58px]">
                        Your whole class's notes,
                        <span class="relative inline-block text-neon sm:whitespace-nowrap">
                            in one link.
                            <svg class="absolute -bottom-2 left-0 w-full text-neon" height="12" viewBox="0 0 240 12" preserveAspectRatio="none" fill="none" aria-hidden="true">
                                <path d="M3 8 Q 40 2 80 6 T 160 5 T 237 6" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
                            </svg>
                        </span>
                    </h1>
                    <p class="mx-auto mt-4 max-w-md text-[15px] leading-relaxed text-muted sm:mt-7 sm:text-[17px] lg:mx-0">
                        Stop asking “who has the notes?” in the group chat. One board
                        for your class, a space for each course, one link everyone keeps.
                    </p>
                    <div class="mt-5 flex flex-col items-center gap-2.5 sm:mt-8 sm:flex-row sm:justify-center sm:gap-3.5 lg:justify-start">
                        <a href="{{ route('start') }}"
                           class="inline-flex w-full max-w-xs items-center justify-center rounded-full bg-neon px-7 py-3.5 text-[15px] font-bold text-white shadow-[0_4px_0_0_var(--color-teal)] transition-all hover:translate-y-0.5 hover:shadow-[0_2px_0_0_var(--color-teal)] active:translate-y-1 active:shadow-none sm:w-auto">
                            Create your board
                        </a>
                        <a href="#how"
                           class="inline-flex w-full max-w-xs items-center justify-center rounded-full border-2 border-sky bg-surface px-7 py-3 text-[15px] font-bold text-ink transition hover:border-neon hover:text-neon sm:w-auto">
                            See how it works
                        </a>
                    </div>
                    <p class="mt-3 text-[13px] font-medium text-muted">
                        No account, no setup · takes under a minute
                    </p>
                </div>

                <div class="group relative mx-auto w-full max-w-md lg:max-w-none" role="img" aria-label="Example board: the Computer Science Level 100 class, showing its CS 101 course with three shared files (a quiz solution, lecture slides and a past paper), each downloadable.">
                    <div class="pointer-events-none absolute inset-2 -z-10 -rotate-1 rounded-2xl border border-sky bg-surface sm:-rotate-3" aria-hidden="true"></div>
                    <div class="overflow-hidden rounded-2xl border border-sky bg-surface text-left shadow-[0_18px_50px_-22px_rgba(0,0,0,0.3)] transition-transform duration-300 rotate-1 group-hover:rotate-0 sm:rotate-2" aria-hidden="true">
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
                            <span class="hidden text-[12.5px] text-muted sm:block">24 contributors</span>
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
            <ol class="grid gap-5 sm:grid-cols-3">
                @foreach ([
                    [
                        'title' => 'Name your board',
                        'body' => 'Make one for your class and add a space for each course.',
                        // duotone: solid document body + solid plus badge
                        'icon' => '<path class="opacity-40" d="M6 2h7l6 6v13a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1z"/><path d="M13 2l6 6h-6V2z"/><circle cx="17" cy="17" r="5"/><path d="M17 14.6v4.8M14.6 17h4.8" stroke="white" stroke-width="2" stroke-linecap="round"/>',
                    ],
                    [
                        'title' => 'Share the link',
                        'body' => 'Drop it in your class group chat. That\'s it.',
                        // duotone: solid nodes + solid connector bars
                        'icon' => '<path class="opacity-40" d="M7.6 10.6l8.8-4.9 1.5 2.6-8.8 4.9zM9.1 11.1l8.8 4.9-1.5 2.6-8.8-4.9z"/><circle cx="6" cy="12" r="3.4"/><circle cx="18" cy="5" r="3.4"/><circle cx="18" cy="19" r="3.4"/>',
                    ],
                    [
                        'title' => 'Everyone chips in',
                        'body' => 'Classmates open a course and add slides, papers and notes.',
                        // duotone: solid tray + solid arrow
                        'icon' => '<path class="opacity-40" d="M3 14h18v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M12 3l5 5h-3.2v6.5h-3.6V8H7z"/>',
                    ],
                ] as $step)
                    <li class="rounded-3xl border border-sky bg-base px-5 py-5 shadow-[0_4px_14px_-12px_rgba(51,29,44,0.3)] sm:px-9 sm:py-10">
                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-neon/10 text-neon sm:h-12 sm:w-12">
                            <svg viewBox="0 0 24 24" class="h-6 w-6" fill="currentColor" aria-hidden="true">
                                {!! $step['icon'] !!}
                            </svg>
                        </span>
                        <p class="mt-4 text-[18px] font-semibold leading-[1.1] tracking-[-0.02em] text-teal sm:mt-6 sm:text-[30px]">{{ $step['title'] }}</p>
                        <p class="mt-2.5 text-[15px] leading-relaxed text-muted">{{ $step['body'] }}</p>
                    </li>
                @endforeach
            </ol>

            <ul class="mt-8 flex flex-wrap items-center justify-center gap-2 sm:mt-14">
                @foreach ([
                    'PDFs, Word, PowerPoint & images',
                    'Sorted into sections',
                    'Name optional',
                    'Private owner link',
                ] as $chip)
                    <li class="rounded-full border border-sky bg-base px-3.5 py-1.5 text-[12.5px] font-medium text-muted">{{ $chip }}</li>
                @endforeach
            </ul>
        </div>
    </section>

    <section id="why" class="border-t border-sky bg-surface px-4 py-9 sm:px-5 sm:py-20">
        <div class="mx-auto grid max-w-6xl gap-8 lg:grid-cols-[0.9fr_1.1fr] lg:items-start">
            <div>
                <h2 class="max-w-md text-[24px] font-semibold leading-[1.08] tracking-[-0.03em] text-teal sm:text-[34px]">
                    Better than losing files in a busy class group chat
                </h2>
                <p class="mt-3 max-w-lg text-[15px] leading-relaxed text-muted sm:mt-4 sm:text-[16px]">
                    Group chats are great for talking, but files get buried fast.
                    SlipNote keeps sharing just as easy (drop a file in) while keeping everything findable a week later.
                </p>
                <p class="mt-4 text-[13px] font-medium text-muted sm:mt-5">
                    Free · open source · no accounts, ever
                </p>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                @foreach ([
                    [
                        'title' => 'Works anywhere',
                        'body' => 'Opens in any browser on any phone or laptop — nothing to install.',
                        // duotone: solid bolt + faded disc
                        'icon' => '<circle class="opacity-40" cx="12" cy="12" r="10"/><path d="M13 4 6.5 13.2H11l-1 6.8 7-9.5h-4.4z"/>',
                    ],
                    [
                        'title' => 'Always findable',
                        'body' => 'The newest files sit right on top, so nothing scrolls out of reach weeks later.',
                        // duotone: solid bars + faded panel
                        'icon' => '<rect class="opacity-40" x="3" y="3" width="18" height="18" rx="3"/><circle cx="7.5" cy="8" r="1.4"/><rect x="10.5" y="7" width="7.5" height="2" rx="1"/><circle cx="7.5" cy="12" r="1.4"/><rect x="10.5" y="11" width="7.5" height="2" rx="1"/><circle cx="7.5" cy="16" r="1.4"/><rect x="10.5" y="15" width="7.5" height="2" rx="1"/>',
                    ],
                    [
                        'title' => 'Share on your terms',
                        'body' => 'Add your name or stay anonymous — your call on every file you upload.',
                        // duotone: solid lock body + faded open shackle
                        'icon' => '<path class="opacity-40" d="M9 10V7a4.5 4.5 0 0 1 8.5-2l-2.6 1.1A1.8 1.8 0 0 0 11.4 7v3z"/><rect x="4" y="10" width="13" height="11" rx="2.5"/>',
                    ],
                    [
                        'title' => 'Safer ownership',
                        'body' => 'One person owns the board, with a private link to recover access anytime.',
                        // duotone: faded shield + solid check
                        'icon' => '<path class="opacity-40" d="M12 2.5l8 3.2V11c0 5-3.3 8.4-8 10-4.7-1.6-8-5-8-10V5.7z"/><path d="M8.5 11.5l2.4 2.4 4.4-4.6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>',
                    ],
                ] as $benefit)
                    <div class="rounded-3xl border border-sky bg-base px-5 py-6 shadow-[0_4px_14px_-12px_rgba(51,29,44,0.3)] sm:px-7 sm:py-8">
                        <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-neon/10 text-neon">
                            <svg viewBox="0 0 24 24" class="h-6 w-6" fill="currentColor" aria-hidden="true">
                                {!! $benefit['icon'] !!}
                            </svg>
                        </span>
                        <p class="mt-5 text-[17px] font-semibold text-teal">{{ $benefit['title'] }}</p>
                        <p class="mt-2.5 text-[14px] leading-relaxed text-muted">{{ $benefit['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="border-t border-sky px-4 py-10 sm:px-5 sm:py-20">
        <div class="mx-auto max-w-5xl rounded-[2rem] border border-sky bg-surface px-5 py-6 shadow-[0_8px_24px_-22px_rgba(51,29,44,0.3)] sm:px-8 sm:py-10">
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
                       class="inline-flex items-center justify-center rounded-full bg-neon px-6 py-3 text-[15px] font-bold text-white shadow-[0_4px_0_0_var(--color-teal)] transition-all hover:translate-y-0.5 hover:shadow-[0_2px_0_0_var(--color-teal)] active:translate-y-1 active:shadow-none">
                        Create your board
                    </a>
                </div>
            </div>
        </div>
    </section>
</x-layouts.app>
