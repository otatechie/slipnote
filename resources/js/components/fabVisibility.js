import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

// A phone's floating button sits over the right of each row, where the
// download buttons are. It steps aside while the page scrolls down, and comes
// back on the way up, near the top or bottom, and whenever it has focus.
export function useFabVisibility() {
    const scrollShown = ref(true)
    const focused = ref(false)
    let lastY = 0
    let queued = false

    function update() {
        queued = false
        const y = window.scrollY
        const nearEdge = y < 80
            || window.innerHeight + y >= document.documentElement.scrollHeight - 8
        if (nearEdge) {
            scrollShown.value = true
            lastY = y
            return
        }
        // Ignore small jitters, so the button doesn't flicker mid-swipe.
        if (Math.abs(y - lastY) < 8) return
        scrollShown.value = y < lastY
        lastY = y
    }

    function onScroll() {
        if (queued) return
        queued = true
        requestAnimationFrame(update)
    }

    onMounted(() => {
        lastY = window.scrollY
        window.addEventListener('scroll', onScroll, { passive: true })
    })
    onBeforeUnmount(() => window.removeEventListener('scroll', onScroll))

    return {
        shown: computed(() => scrollShown.value || focused.value),
        onFocus: () => { focused.value = true },
        onBlur: () => { focused.value = false },
    }
}
