<script setup>
const year = new Date().getFullYear()
</script>

<template>
    <div class="flex min-h-screen flex-col">
        <slot />
        <footer class="mt-auto border-t border-sky/60 py-8">
            <div class="mx-auto max-w-3xl px-5 flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
                <p class="flex items-center gap-1.5 text-[13px] font-semibold text-muted">
                    Made with <svg class="size-3.5 fill-none stroke-current text-teal" viewBox="0 0 20 20" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M10 17.25C10 17.25 2 12 2 6.5A4.5 4.5 0 0 1 10 3.914 4.5 4.5 0 0 1 18 6.5C18 12 10 17.25 10 17.25Z"/></svg> SlipNote
                </p>
                <p class="text-[13px] text-muted">
                    <a href="/privacy" class="hover:text-neon">Privacy</a>
                    &middot;
                    <a href="/terms" class="hover:text-neon">Terms</a>
                    &middot;
                    {{ year }}
                </p>
            </div>
        </footer>
    </div>
</template>
