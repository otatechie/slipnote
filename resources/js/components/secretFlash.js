import { onMounted, reactive, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

// Flash props are kept in history.state for the Back button. A one-time
// secret (the owner link, an Undo token) must not be: on a shared computer
// the next person would press Back and get it. The page keeps its own copy
// for as long as it's open, and the history entry loses the keys.
export function useSecretFlash(keys) {
    const page = usePage()
    const held = reactive(Object.fromEntries(keys.map((k) => [k, null])))

    const take = () => {
        const flash = page.props.flash ?? {}
        if (!keys.some((k) => flash[k] != null)) return
        keys.forEach((k) => { held[k] = flash[k] ?? null })
        router.replaceProp('flash', (current) => {
            const copy = { ...current }
            keys.forEach((k) => delete copy[k])
            return copy
        })
    }

    keys.forEach((k) => { held[k] = page.props.flash?.[k] ?? null })
    onMounted(take)
    watch(() => page.props.flash, take)

    return held
}
