<script setup>
import { nextTick, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { suggestOpen, suggestPrefill } from './suggest'

// A form, not a mailto: link. Most students have no mail app set up on their
// phone, so "Email us" opened nothing at all. This posts to the server, which
// keeps the message and pings the operator on Telegram.
const form = useForm({ message: '', reply_to: '', page: '', website: '' })
const sent = ref(false)
const messageField = ref(null)

watch(suggestOpen, (open) => {
    if (!open) return
    sent.value = false
    form.clearErrors()
    if (!form.message) form.message = suggestPrefill.value
    nextTick(() => messageField.value?.focus())
})

// The app shares validation errors as arrays (see HandleInertiaRequests), so
// the template shows the first message rather than the raw list.

function close() {
    suggestOpen.value = false
}

function submit() {
    form.page = window.location.pathname
    form.post('/suggestions', {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            sent.value = true
            form.reset()
        },
    })
}
</script>

<template>
    <div v-if="suggestOpen" class="fixed inset-0 z-50 flex items-end justify-center sm:items-center"
         role="dialog" aria-modal="true" aria-labelledby="suggestTitle"
         @keydown.escape.window="close">
        <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" @click="close"></div>
        <div class="relative max-h-[90vh] w-full max-w-md overflow-y-auto rounded-t-2xl bg-surface px-6 pb-[max(1.5rem,env(safe-area-inset-bottom))] pt-3 shadow-xl sm:rounded-2xl sm:pt-6">
            <div class="mx-auto mb-3 h-1 w-10 rounded-full bg-muted/30 sm:hidden"></div>
            <button type="button" @click="close" aria-label="Close"
                    class="op-press absolute right-3 top-3 hidden size-9 cursor-pointer items-center justify-center rounded-full text-muted transition hover:bg-sky/40 hover:text-ink sm:flex">
                <svg aria-hidden="true" class="size-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round">
                    <path d="M5 5l10 10M15 5L5 15" />
                </svg>
            </button>

            <template v-if="sent">
                <h2 id="suggestTitle" class="pr-10 text-[15px] font-semibold text-ink">Thanks, got it</h2>
                <p class="mt-2 text-[13px] leading-relaxed text-muted">
                    Every message is read. If you left a way to reach you, we may follow up.
                </p>
                <div class="mt-5 flex justify-end">
                    <button type="button" @click="close"
                            class="op-press inline-flex min-h-11 cursor-pointer items-center rounded-full bg-neon px-5 text-[14px] font-semibold text-white">
                        Done
                    </button>
                </div>
            </template>

            <form v-else @submit.prevent="submit">
                <h2 id="suggestTitle" class="pr-10 text-[15px] font-semibold text-ink">Suggest a feature</h2>
                <p class="mt-1 text-[13px] text-muted">What would make SlipNote better for your class?</p>

                <label for="suggestMessage" class="sr-only">Your suggestion</label>
                <textarea id="suggestMessage" ref="messageField" v-model="form.message" rows="4" maxlength="1000"
                          :aria-invalid="!!form.errors.message"
                          placeholder="e.g. Let us pin the most important file in each course"
                          class="mt-4 w-full resize-y rounded-xl border border-sky bg-base px-3.5 py-2.5 text-[15px] text-ink shadow-inner placeholder:text-muted focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20"></textarea>
                <span v-if="form.errors.message" role="alert" class="mt-1.5 block text-[13px] text-danger">{{ [].concat(form.errors.message)[0] }}</span>

                <label for="suggestReply" class="mb-1.5 mt-3.5 block text-[13px] font-semibold text-ink">
                    Email or WhatsApp <span class="font-normal text-muted">(optional)</span>
                </label>
                <input id="suggestReply" type="text" v-model="form.reply_to" maxlength="120" autocomplete="email"
                       placeholder="Only if you'd like a reply"
                       class="w-full rounded-xl border border-sky bg-base px-3.5 py-2.5 text-[15px] text-ink shadow-inner placeholder:text-muted focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20">

                <!-- Spam trap: hidden from people and screen readers, filled by bots. -->
                <div aria-hidden="true" class="absolute -left-[9999px] size-px overflow-hidden">
                    <label for="suggestWebsite">Website</label>
                    <input id="suggestWebsite" type="text" v-model="form.website" tabindex="-1" autocomplete="off">
                </div>

                <div class="mt-5 flex items-center justify-end gap-2">
                    <button type="button" @click="close"
                            class="op-press inline-flex min-h-11 cursor-pointer items-center rounded-full px-4 text-[14px] font-semibold text-muted transition hover:bg-sky/30 hover:text-ink">Cancel</button>
                    <button type="submit" :disabled="form.processing"
                            class="op-press inline-flex min-h-11 cursor-pointer items-center rounded-full bg-neon px-5 text-[14px] font-semibold text-white disabled:opacity-60">
                        Send
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
