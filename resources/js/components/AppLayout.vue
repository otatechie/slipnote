<script setup>
const year = new Date().getFullYear()
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
                    <a href="/privacy" class="py-1.5 hover:text-neon">Privacy</a>
                    <span aria-hidden="true" class="text-muted/50">&middot;</span>
                    <a href="/terms" class="py-1.5 hover:text-neon">Terms</a>
                    <span aria-hidden="true" class="text-muted/50">&middot;</span>
                    <a href="https://github.com/otatechie/slipnote"
                       class="inline-flex items-center gap-1 py-1.5 hover:text-neon" target="_blank" rel="noopener noreferrer">
                        GitHub
                        <svg aria-hidden="true" class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M7 17 17 7M8 7h9v9"/>
                        </svg>
                        <span class="sr-only">(opens in a new tab)</span>
                    </a>
                </nav>
                <span aria-hidden="true" class="text-muted/50">&middot;</span>
                <!-- Labelled, not icon-only: the icon shows the current mode but a
                     three-way cycle gives no hint of what the next press does. The
                     text is filled in by applyTheme() in app.js, which looks for
                     [data-theme-label] — this layout was the one copy missing it. -->
                <button type="button"
                        data-theme-toggle
                        aria-label="Switch theme mode"
                        class="op-press group inline-flex min-h-8 cursor-pointer items-center justify-center gap-1 rounded-full border border-sky bg-surface px-2.5 py-1 text-muted transition hover:border-neon hover:text-neon sm:min-h-0 sm:px-2">
                    <svg aria-hidden="true" data-theme-icon="system" class="size-3.5 group-data-[active-theme=light]:hidden group-data-[active-theme=dark]:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="4" width="20" height="14" rx="2"/><path d="M8 21h8M12 18v3"/>
                    </svg>
                    <svg aria-hidden="true" data-theme-icon="light" class="hidden size-3.5 group-data-[active-theme=light]:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.5 1.5M17.5 17.5 19 19M19 5l-1.5 1.5M6.5 17.5 5 19"/>
                    </svg>
                    <svg aria-hidden="true" data-theme-icon="dark" class="hidden size-3.5 group-data-[active-theme=dark]:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 12.8A8 8 0 1 1 11.2 3 6 6 0 0 0 21 12.8z"/>
                    </svg>
                    <span data-theme-label class="text-[11px] font-semibold">Theme: System</span>
                </button>
                </div>
                <!-- Full-strength muted: at /70 this measured 2.96:1 on the light
                     page, under AA. The token is only "AA-safe at /70" in dark. -->
                <p class="flex flex-wrap items-center justify-center gap-x-1.5 text-[12px] text-muted">
                    <!-- The holder is the person named in LICENSE, not the product --
                         "SlipNote" is not a legal person and cannot hold copyright.
                         "Code" is load-bearing: the MIT licence covers this repository
                         only, and grants nothing over a hosted instance's boards or
                         uploaded files (README, License). Kept identical to the Blade
                         footer in components/layouts/app.blade.php. -->
                    <span>&copy; {{ year }} Ato Augustine</span>
                    <span aria-hidden="true" class="text-muted/50">&middot;</span>
                    <span>Code is MIT licensed</span>
                    <span aria-hidden="true" class="text-muted/50">&middot;</span>
                    <span class="inline-flex items-center gap-1.5">
                        Made with <svg aria-hidden="true" class="size-3.5 fill-none stroke-current text-teal" viewBox="0 0 20 20" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M10 17.25C10 17.25 2 12 2 6.5A4.5 4.5 0 0 1 10 3.914 4.5 4.5 0 0 1 18 6.5C18 12 10 17.25 10 17.25Z"/></svg> for students
                    </span>
                </p>
            </div>
        </footer>
    </div>
</template>
