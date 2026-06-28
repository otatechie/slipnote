<x-layouts.app title="Operator">
<div class="mx-auto w-full max-w-md flex-1 px-5 pb-10 pt-16">
    <header class="mb-7">
        <p class="mb-1.5 text-xs font-semibold uppercase tracking-[0.08em] text-muted">SlipNote</p>
        <h1 class="text-3xl font-bold tracking-tight text-ink">Operator</h1>
        <p class="mt-1.5 text-[15px] text-muted">Enter the operator secret to review reported files.</p>
    </header>

    <form method="POST" action="{{ route('operator.login') }}" class="flex flex-col gap-3">
        @csrf
        <input type="password" name="secret" autocomplete="off" autofocus
               placeholder="Operator secret"
               class="w-full rounded-lg border border-sky bg-base px-3.5 py-3 text-[15px] text-ink shadow-inner placeholder:text-muted focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20">
        @error('secret')
            <span role="alert" class="text-[13px] text-danger">{{ $message }}</span>
        @enderror
        <button type="submit"
                class="cursor-pointer rounded-lg bg-neon py-3 text-[15px] font-bold text-white transition hover:brightness-125">
            Unlock
        </button>
    </form>
</div>
</x-layouts.app>
