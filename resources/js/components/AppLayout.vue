<script setup>
const year = new Date().getFullYear()
</script>

<template>
    <div class="flex min-h-screen flex-col">
        <slot />
        <footer class="mt-auto border-t border-sky/60 py-8">
            <div class="mx-auto flex max-w-3xl flex-col items-center gap-3 px-5 text-center">
                <p class="flex items-center gap-1.5 text-[13px] font-semibold text-muted">
                    Made with <svg class="size-3.5 fill-none stroke-current text-teal" viewBox="0 0 20 20" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M10 17.25C10 17.25 2 12 2 6.5A4.5 4.5 0 0 1 10 3.914 4.5 4.5 0 0 1 18 6.5C18 12 10 17.25 10 17.25Z"/></svg> SlipNote
                </p>
                <p class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-[13px] text-muted">
                    <a href="/privacy" class="hover:text-neon">Privacy</a>
                    <span aria-hidden="true" class="text-muted/50">&middot;</span>
                    <a href="/terms" class="hover:text-neon">Terms</a>
                    <span aria-hidden="true" class="text-muted/50">&middot;</span>
                    <a href="https://github.com/otatechie/slipnote" class="hover:text-neon" target="_blank" rel="noopener noreferrer">GitHub</a>
                </p>
                <p class="text-[12px] text-muted/70">&copy; {{ year }} SlipNote</p>
            </div>
        </footer>
    </div>
</template>
