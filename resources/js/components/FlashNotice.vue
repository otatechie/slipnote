<script setup>
import { onBeforeUnmount, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'

defineProps({
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
    <div role="status" aria-live="polite" aria-atomic="true"
        class="pointer-events-none fixed inset-x-4 top-[max(0.75rem,env(safe-area-inset-top))] z-40 flex justify-center"
        :class="belowStickyBar ? 'sm:top-21' : 'sm:top-4'">
        <Transition
            enter-active-class="transition duration-200 ease-out motion-reduce:transition-none"
            enter-from-class="-translate-y-2 opacity-0"
            leave-active-class="transition duration-300 ease-in motion-reduce:transition-none"
            leave-to-class="-translate-y-2 opacity-0">
            <p v-if="text" @mouseenter="pause" @mouseleave="resume"
                class="pointer-events-auto max-w-sm rounded-full bg-ink px-4 py-2.5 text-center text-[14px] font-semibold text-[var(--color-base)] shadow-lg">
                {{ text }}
            </p>
        </Transition>
    </div>
</template>
