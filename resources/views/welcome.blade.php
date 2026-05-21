<x-layouts.app
    title="Share course notes with classmates"
    description="A free, no-account board for sharing course materials. One link, your whole class — no logins, no setup."
    :indexable="true">

    <x-slot:head>
        <link rel="canonical" href="{{ url('/') }}">

        <meta property="og:type" content="website">
        <meta property="og:site_name" content="SlipNote">
        <meta property="og:title" content="SlipNote — Share course notes with classmates">
        <meta property="og:description" content="A free, no-account board for sharing course materials. One link, your whole class.">
        <meta property="og:url" content="{{ url('/') }}">
        <meta name="twitter:card" content="summary">
        <meta name="twitter:title" content="SlipNote — Share course notes with classmates">
        <meta name="twitter:description" content="A free, no-account board for sharing course materials. One link, your whole class.">

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
            "description": "A free, no-account board for sharing course materials."
        }
        </script>
    </x-slot:head>

    {{-- HERO --}}
    <section class="mx-auto w-full max-w-4xl flex-1 px-5 pt-12 pb-10 text-center sm:pt-16">
        <h1 class="text-4xl font-bold tracking-tight text-ink sm:text-5xl md:text-6xl">
            Share course notes
            <span class="block text-neon">in one link.</span>
        </h1>
        <p class="mx-auto mt-5 max-w-xl text-[16px] leading-relaxed text-muted sm:text-[17px]">
            Name your class, get a link, share it with classmates. They drop in slides,
            past papers, and notes — no accounts, no passwords, no setup.
        </p>
        <div class="mt-7 flex flex-col items-center gap-3">
            <a href="{{ route('start') }}"
               class="inline-flex w-full max-w-xs items-center justify-center gap-2 rounded-lg bg-neon px-6 py-3.5 text-[15px] font-bold text-white shadow-sm transition hover:brightness-125 sm:w-auto">
                Create your board
                <span aria-hidden="true">→</span>
            </a>
            <p class="text-[12px] text-muted">Free, forever · no email required</p>
        </div>

        {{-- Mock screenshot: a small, hand-drawn-feel preview of the actual
             course board so the hero isn't just text. Pure CSS — keeps the
             page lean and works for crawlers. --}}
        <div class="mx-auto mt-12 max-w-2xl">
            <div class="overflow-hidden rounded-2xl border border-sky/40 bg-surface shadow-xl ring-1 ring-black/5">
                {{-- Fake browser chrome --}}
                <div class="flex items-center gap-1.5 border-b border-sky/40 bg-base/60 px-4 py-2.5">
                    <span class="h-2.5 w-2.5 rounded-full bg-red-300"></span>
                    <span class="h-2.5 w-2.5 rounded-full bg-yellow-300"></span>
                    <span class="h-2.5 w-2.5 rounded-full bg-green-300"></span>
                    <span class="ml-3 inline-flex items-center gap-1 rounded-md bg-base/80 px-2.5 py-0.5 text-[11px] text-muted">
                        <span aria-hidden="true">🔒</span> slipnote.co/cs-101
                    </span>
                </div>
                {{-- Fake board content --}}
                <div class="px-4 py-5 text-left sm:px-6">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-muted">CS 101</p>
                    <p class="text-[18px] font-bold tracking-tight text-ink">Intro to Computer Science</p>
                    <div class="mt-4 space-y-2">
                        <div class="flex items-center justify-between rounded-lg border border-sky/30 bg-base/60 px-3 py-2">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="shrink-0 rounded bg-sky/40 px-1.5 py-0.5 text-[10px] font-bold text-muted">PDF</span>
                                <span class="truncate text-[13px] font-semibold text-neon">Week 7 quiz solutions</span>
                            </div>
                            <span class="shrink-0 rounded-full bg-neon px-2.5 py-0.5 text-[11px] font-semibold text-white">Download</span>
                        </div>
                        <div class="flex items-center justify-between rounded-lg border border-sky/30 bg-base/60 px-3 py-2">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="shrink-0 rounded bg-sky/40 px-1.5 py-0.5 text-[10px] font-bold text-muted">PPTX</span>
                                <span class="truncate text-[13px] font-semibold text-neon">Lecture 12 slides</span>
                            </div>
                            <span class="shrink-0 rounded-full bg-neon px-2.5 py-0.5 text-[11px] font-semibold text-white">Download</span>
                        </div>
                        <div class="flex items-center justify-between rounded-lg border border-sky/30 bg-base/60 px-3 py-2">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="shrink-0 rounded bg-sky/40 px-1.5 py-0.5 text-[10px] font-bold text-muted">PDF</span>
                                <span class="truncate text-[13px] font-semibold text-neon">2024 past paper</span>
                            </div>
                            <span class="shrink-0 rounded-full bg-neon px-2.5 py-0.5 text-[11px] font-semibold text-white">Download</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- HOW IT WORKS: three sequential steps. Sequential reads better than
         parallel for a 30-second product. --}}
    <section id="how" class="border-t border-sky/40 bg-surface/50 px-5 py-12">
        <div class="mx-auto max-w-4xl">
            <p class="mb-8 text-center text-[22px] font-bold tracking-tight text-ink sm:text-[26px]">
                Three steps. Thirty seconds.
            </p>
            <ol class="grid gap-5 sm:grid-cols-3">
                <li class="relative rounded-2xl border border-sky/30 bg-base px-6 py-5 shadow-sm">
                    <span class="absolute -top-3 left-5 inline-flex h-7 w-7 items-center justify-center rounded-full bg-neon text-[13px] font-bold text-white shadow-sm">1</span>
                    <p class="mt-1 text-[15px] font-bold text-ink">Name your class</p>
                    <p class="mt-1.5 text-[13.5px] leading-relaxed text-muted">
                        Type the course code or class name. Takes 5 seconds.
                    </p>
                </li>
                <li class="relative rounded-2xl border border-sky/30 bg-base px-6 py-5 shadow-sm">
                    <span class="absolute -top-3 left-5 inline-flex h-7 w-7 items-center justify-center rounded-full bg-neon text-[13px] font-bold text-white shadow-sm">2</span>
                    <p class="mt-1 text-[15px] font-bold text-ink">Share the link</p>
                    <p class="mt-1.5 text-[13.5px] leading-relaxed text-muted">
                        Drop it in your class group chat. That's it.
                    </p>
                </li>
                <li class="relative rounded-2xl border border-sky/30 bg-base px-6 py-5 shadow-sm">
                    <span class="absolute -top-3 left-5 inline-flex h-7 w-7 items-center justify-center rounded-full bg-neon text-[13px] font-bold text-white shadow-sm">3</span>
                    <p class="mt-1 text-[15px] font-bold text-ink">Everyone uploads</p>
                    <p class="mt-1.5 text-[13.5px] leading-relaxed text-muted">
                        Classmates add slides, papers, notes. No sign-ups.
                    </p>
                </li>
            </ol>

            {{-- Quick facts strip — what feature cards used to be, condensed.
                 Reads as a footnote, not a sales pitch. --}}
            <p class="mt-10 text-center text-[13px] text-muted">
                PDFs, Word, PowerPoint &amp; images · sorted by section ·
                anonymous uploads supported · owner link kept private
            </p>
        </div>
    </section>

    {{-- FINAL CTA --}}
    <section class="px-5 py-14 text-center">
        <h2 class="text-2xl font-bold tracking-tight text-ink sm:text-3xl">
            Start a board in 30 seconds.
        </h2>
        <p class="mx-auto mt-3 max-w-md text-[15px] text-muted">
            No credit card, no email, no fine print. Just a link to share with your class.
        </p>
        <a href="{{ route('start') }}"
           class="mt-6 inline-flex w-full max-w-xs items-center justify-center gap-2 rounded-lg bg-neon px-6 py-3.5 text-[15px] font-bold text-white shadow-sm transition hover:brightness-125 sm:w-auto">
            Create your board
            <span aria-hidden="true">→</span>
        </a>
    </section>
</x-layouts.app>
