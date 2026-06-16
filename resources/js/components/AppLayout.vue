<script setup>
const year = new Date().getFullYear()
</script>

<template>
    <div class="flex min-h-screen flex-col">
        <slot />
        <footer class="mt-auto border-t border-sky/60 py-8">
            <div class="mx-auto flex max-w-3xl flex-col items-center gap-3 px-5 text-center">
                <p class="flex items-center gap-1.5 text-[13px] font-semibold text-muted">
                    Made with <svg class="size-3.5 fill-none stroke-current text-teal" viewBox="0 0 20 20" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M10 17.25C10 17.25 2 12 2 6.5A4.5 4.5 0 0 1 10 3.914 4.5 4.5 0 0 1 18 6.5C18 12 10 17.25 10 17.25Z"/></svg> for students
                </p>
                <nav aria-label="Footer" class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-[13px] text-muted">
                    <a href="/privacy" class="hover:text-neon">Privacy</a>
                    <span aria-hidden="true" class="text-muted/50">&middot;</span>
                    <a href="/terms" class="hover:text-neon">Terms</a>
                    <span aria-hidden="true" class="text-muted/50">&middot;</span>
                    <a href="https://github.com/otatechie/slipnote"
                       class="inline-flex items-center gap-1 hover:text-neon" target="_blank" rel="noopener noreferrer">
                        GitHub
                        <svg aria-hidden="true" class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M7 17 17 7M8 7h9v9"/>
                        </svg>
                    </a>
                </nav>
                <button type="button"
                        data-theme-toggle
                        aria-label="Switch theme mode"
                        class="group inline-flex cursor-pointer items-center justify-center rounded-full border border-sky bg-surface p-2 text-muted transition hover:border-neon hover:text-neon">
                    <svg aria-hidden="true" data-theme-icon="system" class="size-4 group-data-[active-theme=light]:hidden group-data-[active-theme=dark]:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="4" width="20" height="14" rx="2"/><path d="M8 21h8M12 18v3"/>
                    </svg>
                    <svg aria-hidden="true" data-theme-icon="light" class="hidden size-4 group-data-[active-theme=light]:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.5 1.5M17.5 17.5 19 19M19 5l-1.5 1.5M6.5 17.5 5 19"/>
                    </svg>
                    <svg aria-hidden="true" data-theme-icon="dark" class="hidden size-4 group-data-[active-theme=dark]:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 12.8A8 8 0 1 1 11.2 3 6 6 0 0 0 21 12.8z"/>
                    </svg>
                </button>
                <p class="text-[12px] text-muted/70">&copy; {{ year }} SlipNote</p>
            </div>
        </footer>
    </div>
</template>
