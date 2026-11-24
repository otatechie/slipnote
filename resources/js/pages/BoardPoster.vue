<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Head, Link } from '@inertiajs/vue3'

const props = defineProps({
    workspace: Object,
    url: String,          // the board's link, from APP_URL
    uploadsOpen: Boolean,
})

// Printed for people to type if the camera won't scan: no https://.
const shortUrl = computed(() => props.url.replace(/^https?:\/\//, ''))
const siteHost = computed(() => new URL(props.url).host)

// Names run from "P1" to "COMPUTING SCIENCE: C816 75—7": long ones get smaller
// type so the whole name fits in three lines and the sheet stays one A4 page.
const nameSize = computed(() => {
    const n = props.workspace.name.length
    return n <= 24 ? 'text-[7.5cqw]' : n <= 48 ? 'text-[6cqw]' : 'text-[4.8cqw]'
})

const qrSvg = ref('')
const qrError = ref(false)

// One A4 sheet with no browser margins, only while this page is open:
// a global @page rule would reach every other page's print-out.
let pageStyle = null

onMounted(async () => {
    pageStyle = document.createElement('style')
    pageStyle.textContent = '@page { size: A4 portrait; margin: 0; }'
    document.head.appendChild(pageStyle)

    try {
        const QR = (await import('qrcode')).default
        // SVG, not PNG: it stays sharp at any print size.
        qrSvg.value = await QR.toString(props.url, { type: 'svg', margin: 0, errorCorrectionLevel: 'M' })
    } catch {
        qrError.value = true
    }
})
onBeforeUnmount(() => pageStyle?.remove())

function print() {
    window.print()
}
</script>

<template>
    <Head :title="'Poster · ' + workspace.name" />
    <div class="min-h-screen bg-base px-4 py-6 print:min-h-0 print:bg-white print:p-0 sm:py-10">
        <div class="mx-auto mb-5 flex w-full max-w-[210mm] flex-wrap items-center justify-between gap-3 print:hidden">
            <Link :href="'/' + workspace.slug"
                  class="inline-flex min-h-11 items-center gap-1.5 text-[13px] font-semibold text-muted transition hover:text-ink">
                <svg aria-hidden="true" class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5l-5 5 5 5" /></svg>
                Back to the board
            </Link>
            <div class="flex items-center gap-3">
                <p class="hidden text-[13px] text-muted sm:block">A4. Pin it where the class will see it.</p>
                <button type="button" @click="print" :disabled="!qrSvg"
                        class="op-press inline-flex min-h-11 cursor-pointer items-center gap-1.5 rounded-full bg-neon px-5 text-[14px] font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60 sm:min-h-9">
                    Print poster
                </button>
            </div>
        </div>

        <!-- The sheet: sized in container units, so it scales on screen and prints at exactly A4. -->
        <article class="@container mx-auto aspect-[210/297] w-full max-w-[210mm] overflow-hidden bg-white text-neutral-900 shadow-xl ring-1 ring-black/5 print:h-[296mm] print:w-[210mm] print:max-w-none print:shadow-none print:ring-0">
            <!-- Every block keeps its height (shrink-0): squeezed, the name was the one that vanished. -->
            <div class="flex h-full flex-col px-[9cqw] pb-[5cqw] pt-[6cqw] [&>*]:shrink-0">
                <p class="flex items-center gap-[1.6cqw] text-[3.4cqw] font-bold tracking-[-0.01em]">
                    <img src="/logo-mark.png" alt="" class="size-[5cqw]">
                    SlipNote
                </p>

                <p class="mt-[4cqw] text-[3.6cqw] font-medium text-neutral-600">Course files for</p>
                <!-- The clamp hides overflow, and at this leading the last line's descenders hang
                     below the box: the padding takes them in, the margin keeps the spacing. -->
                <h1 class="mt-[calc(1cqw-0.15em)] mb-[-0.15em] line-clamp-3 py-[0.15em] wrap-anywhere font-bold leading-[1.08] tracking-[-0.02em]" :class="nameSize">{{ workspace.name }}</h1>

                <div class="mt-[5cqw] flex flex-col items-center">
                    <div v-if="qrSvg" role="img" :aria-label="'QR code that opens ' + shortUrl"
                         class="size-[44cqw] [&>svg]:size-full" v-html="qrSvg"></div>
                    <div v-else role="status"
                         class="flex size-[44cqw] items-center justify-center border-[0.4cqw] border-dashed border-neutral-300 px-[6cqw] text-center text-[3.4cqw] text-neutral-500">
                        {{ qrError ? 'The QR code didn’t build. Reload the page to try again.' : 'Building the QR code…' }}
                    </div>
                    <p class="mt-[3cqw] text-[4.6cqw] font-bold tracking-[-0.01em]">Scan with your phone camera</p>
                    <!-- Breaks at the slug's hyphens; break-all split words ("engineeri / ng"). -->
                    <p class="mt-[0.8cqw] text-balance text-center text-[3cqw] font-medium text-neutral-600 wrap-anywhere">{{ shortUrl }}</p>
                </div>

                <ul class="mx-auto mt-[4cqw] space-y-[1.2cqw] text-[3.2cqw] leading-snug">
                    <li class="flex gap-[2cqw]"><span aria-hidden="true" class="font-bold">✓</span> Opens in your browser. No account, no app.</li>
                    <li class="flex gap-[2cqw]"><span aria-hidden="true" class="font-bold">✓</span> Notes, slides and past papers in one place.</li>
                    <li v-if="uploadsOpen" class="flex gap-[2cqw]"><span aria-hidden="true" class="font-bold">✓</span> Have good notes? Add them for the class.</li>
                </ul>

                <p class="mt-auto border-t-[0.3cqw] border-neutral-200 pt-[3cqw] text-center text-[2.8cqw] text-neutral-600">
                    Teaching a class? Make a free board for it at <span class="font-semibold text-neutral-900">{{ siteHost }}</span>
                </p>
            </div>
        </article>
    </div>
</template>
