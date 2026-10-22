import { ref } from 'vue'

export const suggestOpen = ref(false)
export const suggestPrefill = ref('')

export function openSuggest(prefill = '') {
    suggestPrefill.value = prefill
    suggestOpen.value = true
}
