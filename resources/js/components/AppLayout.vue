<script setup>
const year = new Date().getFullYear()
</script>

<template>
    <div class="flex min-h-screen flex-col">
        <slot />
        <footer class="mt-auto border-t border-sky/60 py-8">
            <div class="mx-auto max-w-3xl px-5 flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
                <p class="text-[13px] font-semibold text-muted">SlipNote</p>
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
