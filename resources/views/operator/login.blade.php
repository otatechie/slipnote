<x-layouts.operator title="Operator">
{{-- Same shape as /start: a centred column with the mark above the heading and
     the form in a card. Left-aligned at the top of a wide page, it read as a
     fragment of some other screen rather than a page of its own. --}}
<div class="op mx-auto flex w-full max-w-md flex-1 flex-col justify-center px-4 py-12 sm:px-5">
    <header class="mb-7 text-center">
        <a href="/"
           class="mb-3 inline-flex items-center gap-2 py-1 text-[16px] font-bold tracking-[-0.01em] text-teal transition hover:opacity-80">
            <img src="/logo-mark.png" alt="" class="size-7 shrink-0">
            SlipNote
        </a>
        <h1 class="op-title text-[2.15rem] font-bold text-ink">Operator</h1>
        <p class="mx-auto mt-2 max-w-xs text-balance text-[15px] text-muted">Reports, boards, visitors and suggestions, in one place.</p>
    </header>

    <form method="POST" action="{{ route('operator.login') }}" class="op-card flex flex-col gap-4 p-5 sm:p-6">
        @csrf
        <div>
            {{-- A real label. The placeholder was this field's only name, and a
                 placeholder disappears on the first keystroke. --}}
            <label for="secret" class="mb-1.5 block text-[13px] font-semibold text-ink">Operator secret</label>
            {{-- bg-base inside the bg-surface card: an input has to contrast with
                 whatever is directly behind it. --}}
            <input id="secret" type="password" name="secret" autocomplete="off" autofocus
                   @error('secret') aria-invalid="true" @enderror
                   class="w-full rounded-xl border border-sky bg-base px-3.5 py-2.5 text-[15px] text-ink shadow-inner">
            @error('secret')
                <span role="alert" class="mt-1.5 block text-[13px] text-danger">{{ $message }}</span>
            @enderror
        </div>
        <button type="submit"
                class="op-press inline-flex min-h-11 cursor-pointer items-center justify-center rounded-full bg-neon px-5 text-[15px] font-semibold text-white">
            Unlock
        </button>
    </form>
</div>
</x-layouts.operator>
