<x-layouts.app title="For lecturers" :indexable="true">
<div class="op mx-auto w-full max-w-2xl flex-1 px-4 pb-10 pt-8 sm:px-5 sm:pt-10">
    <header class="mb-6">
        <a href="{{ route('welcome') }}"
           class="-my-2.5 inline-flex min-h-11 items-center gap-2 text-[15px] font-bold text-ink transition hover:opacity-80">
            <img src="/logo-mark.png" alt="" class="size-6 shrink-0">
            SlipNote
        </a>
        <h1 class="op-title mt-3 text-[2.15rem] font-bold text-ink">For lecturers</h1>
        <p class="mt-2 text-[16px] leading-relaxed text-muted">
            One private page per class, not a public library.<br class="hidden sm:inline">
            Anything of yours can be taken down.
        </p>
    </header>

    <ul class="space-y-2 text-[15px] text-ink">
        <li class="flex gap-2.5">
            <svg aria-hidden="true" class="mt-1 size-4 shrink-0 text-muted" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10.5l4 4 8-9"/></svg>
            <span><strong class="font-semibold">Invisible to search engines</strong>, every board and every file</span>
        </li>
        <li class="flex gap-2.5">
            <svg aria-hidden="true" class="mt-1 size-4 shrink-0 text-muted" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10.5l4 4 8-9"/></svg>
            <span><strong class="font-semibold">Reached only by the class's link</strong>, with no directory to browse</span>
        </li>
        <li class="flex gap-2.5">
            <svg aria-hidden="true" class="mt-1 size-4 shrink-0 text-muted" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10.5l4 4 8-9"/></svg>
            <span><strong class="font-semibold">No paywall</strong>, and no rewards for uploading</span>
        </li>
    </ul>

    <section class="op-card mt-7 px-5 py-5 sm:px-6">
        <h2 class="text-[17px] font-semibold text-ink">To have your material removed</h2>
        <ol class="mt-3 space-y-2 text-[15px] text-ink">
            <li class="flex gap-3"><span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-sky/60 text-[12px] font-semibold tabular-nums">1</span><span>Open the file on the board your students use.</span></li>
            <li class="flex gap-3"><span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-sky/60 text-[12px] font-semibold tabular-nums">2</span><span>Tap <strong class="font-semibold">⋯</strong> → <strong class="font-semibold">Report this file</strong> → <strong class="font-semibold">Copyright</strong>.</span></li>
            <li class="flex gap-3"><span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-sky/60 text-[12px] font-semibold tabular-nums">3</span><span>We review it within {{ config('noteshare.takedown_turnaround') }}. Once removed, it can't be uploaded again anywhere on SlipNote.</span></li>
        </ol>
        @if (filled(config('noteshare.contact_email')))
            <p class="mt-4 text-[13px] text-muted">
                A lot to remove? Email
                <a href="mailto:{{ config('noteshare.contact_email') }}" class="font-semibold text-neon hover:underline">{{ config('noteshare.contact_email') }}</a>
                with the board's link.
            </p>
        @endif
    </section>

    <section class="mt-8">
        <h2 class="text-[17px] font-semibold text-ink">Or run a board for your course</h2>
        <p class="mt-1.5 text-[15px] leading-relaxed text-muted">
            Your files in one place instead of a group chat. No account. You keep the owner link, choose whether students can add files or only you, and pin the files that matter to the top of their section. Delete any file, or the whole board, whenever you want.
        </p>
        <a href="{{ route('start') }}"
           class="op-press mt-4 inline-flex min-h-11 items-center rounded-full bg-neon px-5 text-[15px] font-semibold text-white">
            Create a board
        </a>
    </section>

    @if (filled(config('noteshare.operator_name')))
        <section class="mt-8">
            <h2 class="text-[17px] font-semibold text-ink">Who runs SlipNote</h2>
            <p class="mt-1.5 text-[15px] leading-relaxed text-muted">
                {{ config('noteshare.operator_name') }}, independently: not a university service, and nobody pays to see what's on a board.
                @if (filled(config('noteshare.contact_email')))
                    Questions or concerns:
                    <a href="mailto:{{ config('noteshare.contact_email') }}" class="font-semibold text-neon hover:underline">{{ config('noteshare.contact_email') }}</a>.
                @endif
            </p>
        </section>
    @endif
</div>
</x-layouts.app>
