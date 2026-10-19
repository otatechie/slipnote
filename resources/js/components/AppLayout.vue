<script setup>
import SuggestDialog from './SuggestDialog.vue'
import { openSuggest } from './suggest'
</script>

<template>
    <div class="flex min-h-screen flex-col">
        <slot />
        <!-- Two rows, not four. Every line here was its own centred row, stacking
             into ~200px of chrome under pages whose whole job is one short form.
             The tagline is decoration, so it rides along with the copyright. -->
        <footer class="mt-auto border-t border-sky/60 py-6">
            <div class="mx-auto flex max-w-3xl flex-col items-center gap-2.5 px-5 text-center">
                <div class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-[13px] text-muted">
                <nav aria-label="Footer" class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1">
                    <a href="/privacy" class="inline-flex min-h-11 items-center hover:text-neon sm:min-h-0 sm:py-1.5">Privacy</a>
                    <span aria-hidden="true" class="text-muted/50">&middot;</span>
                    <a href="/terms" class="inline-flex min-h-11 items-center hover:text-neon sm:min-h-0 sm:py-1.5">Terms</a>
                    <span aria-hidden="true" class="text-muted/50">&middot;</span>
                    <a href="https://github.com/otatechie/slipnote"
                       class="inline-flex min-h-11 items-center gap-1 hover:text-neon sm:min-h-0 sm:py-1.5" target="_blank" rel="noopener noreferrer">
                        GitHub
                        <svg aria-hidden="true" class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M7 17 17 7M8 7h9v9"/>
                        </svg>
                        <span class="sr-only">(opens in a new tab)</span>
                    </a>
                    <span aria-hidden="true" class="text-muted/50">&middot;</span>
                    <button type="button" @click="openSuggest()" class="inline-flex min-h-11 cursor-pointer items-center hover:text-neon sm:min-h-0 sm:py-1.5">Suggest a feature</button>
                    <template v-if="$page.props.ambassadorFormUrl">
                        <span aria-hidden="true" class="text-muted/50">&middot;</span>
                        <a :href="$page.props.ambassadorFormUrl" class="inline-flex min-h-11 items-center gap-1 hover:text-neon sm:min-h-0 sm:py-1.5" target="_blank" rel="noopener noreferrer">
                            Become a campus ambassador
                            <svg aria-hidden="true" class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M8 7h9v9"/></svg>
                            <span class="sr-only">(opens in a new tab)</span>
                        </a>
                    </template>
                </nav>
                <span aria-hidden="true" class="hidden text-muted/50 sm:inline">&middot;</span>
                <!-- All three modes visible, so nothing is hidden behind a cycle. applyTheme()
                     in app.js sets aria-pressed on the current one, which drives its fill. -->
                <div data-theme-switch role="group" aria-label="Theme" class="inline-flex items-center rounded-full border border-sky bg-surface p-px">
                    <button type="button" data-theme-set="system" aria-pressed="false" title="System theme"
                            class="inline-flex size-9 cursor-pointer items-center justify-center rounded-full sm:size-5 text-muted transition hover:text-ink aria-pressed:bg-sky aria-pressed:text-ink">
                        <svg aria-hidden="true" class="size-3.5 sm:size-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="14" rx="2"/><path d="M8 21h8M12 18v3"/></svg>
                        <span class="sr-only">System</span>
                    </button>
                    <button type="button" data-theme-set="light" aria-pressed="false" title="Light theme"
                            class="inline-flex size-9 cursor-pointer items-center justify-center rounded-full sm:size-5 text-muted transition hover:text-ink aria-pressed:bg-sky aria-pressed:text-ink">
                        <svg aria-hidden="true" class="size-3.5 sm:size-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.5 1.5M17.5 17.5 19 19M19 5l-1.5 1.5M6.5 17.5 5 19"/></svg>
                        <span class="sr-only">Light</span>
                    </button>
                    <button type="button" data-theme-set="dark" aria-pressed="false" title="Dark theme"
                            class="inline-flex size-9 cursor-pointer items-center justify-center rounded-full sm:size-5 text-muted transition hover:text-ink aria-pressed:bg-sky aria-pressed:text-ink">
                        <svg aria-hidden="true" class="size-3.5 sm:size-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A8 8 0 1 1 11.2 3 6 6 0 0 0 21 12.8z"/></svg>
                        <span class="sr-only">Dark</span>
                    </button>
                </div>
                </div>
                <!-- Full-strength muted: at /70 this measured 2.96:1 on the light
                     page, under AA. The token is only "AA-safe at /70" in dark. -->
                <p class="flex flex-wrap items-center justify-center gap-x-1.5 text-[12px] text-muted">
                    <!-- No copyright or licence line: neither is required, the
                         legal position lives on the Terms page, and the GitHub
                         link beside this leads to LICENSE. Kept identical to the
                         Blade footer in components/layouts/app.blade.php. -->
                    <span class="inline-flex items-center gap-1.5">
                        Made with <svg aria-hidden="true" class="size-3.5 fill-none stroke-current text-teal" viewBox="0 0 20 20" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M10 17.25C10 17.25 2 12 2 6.5A4.5 4.5 0 0 1 10 3.914 4.5 4.5 0 0 1 18 6.5C18 12 10 17.25 10 17.25Z"/></svg> for students
                    </span>
                </p>
            </div>
        </footer>
        <SuggestDialog />
    </div>
</template>
