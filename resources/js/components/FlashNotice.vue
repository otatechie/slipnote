<script setup>
import { onBeforeUnmount, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'

// A passing confirmation (flash.notice: pinned, unpinned, file removed) at the
// top of the screen, not the page: fixed, so it's seen from wherever the page
// is scrolled. A banner at the top of the page was out of view after pinning
// a file further down, which also moved that file out of view. Gone after a
// few seconds, as the change itself is already on screen; held while the
// pointer is on it. Never used for anything with an action in it (the upload
// receipt's Undo): controls must not time out.
defineProps({
    // From sm up the course page's search bar sticks to the top; sit below
    // it, or the notice reads as text in the search field.
    belowStickyBar: { type: Boolean, default: false },
})

const SHOW_MS = 4000
const page = usePage()
const text = ref('')
let timer = null
let paused = false

function arm() {
    clearTimeout(timer)
    if (!paused) timer = setTimeout(() => { text.value = '' }, SHOW_MS)
}

// Every visit brings a new flash object, so the same notice twice in a row
// (pinning two files) still shows again and restarts the clock.
watch(() => page.props.flash, (flash) => {
    if (flash?.notice) {
        text.value = flash.notice
        arm()
    }
}, { immediate: true })

function pause() {
    paused = true
    clearTimeout(timer)
}
function resume() {
    paused = false
    if (text.value) arm()
}

onBeforeUnmount(() => clearTimeout(timer))
</script>

<template>
    <!-- The live region is always in the page, so screen readers announce
         the text when it arrives; an element added with its text is often
         missed. -->
    <div role="status" aria-live="polite" aria-atomic="true"
        class="pointer-events-none fixed inset-x-4 top-[max(0.75rem,env(safe-area-inset-top))] z-40 flex justify-center"
        :class="belowStickyBar ? 'sm:top-21' : 'sm:top-4'">
        <Transition
            enter-active-class="transition duration-200 ease-out motion-reduce:transition-none"
            enter-from-class="-translate-y-2 opacity-0"
            leave-active-class="transition duration-300 ease-in motion-reduce:transition-none"
            leave-to-class="-translate-y-2 opacity-0">
            <!-- Inverted (ink on page colour) so it reads as floating over the
                 list in both themes, not as one more card in it. -->
            <p v-if="text" @mouseenter="pause" @mouseleave="resume"
                class="pointer-events-auto max-w-sm rounded-full bg-ink px-4 py-2.5 text-center text-[14px] font-semibold text-[var(--color-base)] shadow-lg">
                {{ text }}
            </p>
        </Transition>
    </div>
</template>
