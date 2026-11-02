<x-layouts.app title="Board archived">
<div class="op mx-auto flex w-full max-w-md flex-1 flex-col items-center justify-center px-4 pb-16 pt-10 text-center sm:px-5">
    <a href="{{ route('welcome') }}"
       class="inline-flex min-h-11 items-center gap-2 text-[15px] font-bold text-ink transition hover:opacity-80">
        <img src="/logo-mark.png" alt="" class="size-6 shrink-0">
        SlipNote
    </a>
    <h1 class="op-title mt-6 text-[1.75rem] font-bold text-ink">This board is archived</h1>
    <p class="mt-2 text-[16px] leading-relaxed text-muted">
        Its files are kept but can’t be opened right now. If you’re expecting it back, ask whoever shared the link with you.
    </p>
    <a href="{{ route('welcome') }}"
       class="op-press mt-7 inline-flex min-h-11 items-center rounded-full bg-neon px-5 text-[15px] font-semibold text-white">
        Go to SlipNote
    </a>
</div>
</x-layouts.app>
