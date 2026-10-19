import { ref } from 'vue'

// One dialog, opened from the footer on every page and from the board's
// "Tell us" card. Module-level state, so both reach the same instance.
export const suggestOpen = ref(false)
export const suggestPrefill = ref('')

export function openSuggest(prefill = '') {
    suggestPrefill.value = prefill
    suggestOpen.value = true
}
