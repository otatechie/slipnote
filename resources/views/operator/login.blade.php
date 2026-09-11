<x-layouts.operator title="Operator">
<div class="op mx-auto w-full max-w-md flex-1 px-5 pb-10 pt-16">
    <header class="mb-8">
        <p class="op-kicker mb-2 text-[11px] font-semibold uppercase text-muted">SlipNote</p>
        <h1 class="op-title text-[2.15rem] font-bold text-ink">Operator</h1>
        <p class="mt-2 text-[15px] leading-relaxed text-muted">Enter the operator secret to review reported files.</p>
    </header>

    <form method="POST" action="{{ route('operator.login') }}" class="flex flex-col gap-3">
        @csrf
        <div>
            {{-- A real label. The placeholder was this field's only name, and a
                 placeholder disappears on the first keystroke. --}}
            <label for="secret" class="mb-1.5 block text-[13px] font-semibold text-ink">Operator secret</label>
            {{-- bg-surface, not bg-base: the page itself is bg-base, so the field
                 was the same tone as what sits behind it and read as inert. An
                 input has to contrast with whatever is directly behind it. --}}
            <input id="secret" type="password" name="secret" autocomplete="off" autofocus
                   @error('secret') aria-invalid="true" @enderror
                   class="w-full rounded-xl border border-sky bg-surface px-3.5 py-2.5 text-[15px] text-ink shadow-sm focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20">
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
