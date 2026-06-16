<script setup>
import { computed } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '@/components/AppLayout.vue'

const props = defineProps({
    workspace: Object,
})

const page = usePage()
const flash = computed(() => page.props.flash)
const errors = computed(() => page.props.errors)

const form = useForm({ email: '' })

function requestRecovery() {
    form.post('/' + props.workspace.slug + '/recover', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    })
}
</script>

<template>
    <Head title="Recover owner link" />
    <AppLayout>
        <div class="mx-auto flex w-full max-w-md flex-1 flex-col justify-center px-4 py-8 sm:px-5 sm:py-12">
            <header class="mb-7 text-center">
                <p class="mb-2 text-xs font-semibold uppercase tracking-[0.08em] text-muted">SlipNote</p>
                <h1 class="text-3xl font-bold tracking-tight text-ink">Recover owner access</h1>
                <p class="mx-auto mt-2 max-w-sm text-[15px] text-muted">
                    For <span class="font-semibold text-ink">{{ workspace.name }}</span>.
                    If a recovery email was set for this board, we'll send a fresh
                    owner link there.
                </p>
            </header>

            <!-- Success state (identical response to prevent enumeration) -->
            <div v-if="flash.done" class="rounded-3xl border border-sky/30 bg-surface p-6 shadow-sm sm:p-7">
                <div class="flex items-center gap-3">
                    <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-2xl bg-neon/15 text-neon">
                        <svg class="size-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M4 10.5 8 14l8-8" />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <p class="text-[12px] font-semibold uppercase tracking-[0.08em] text-muted">Request sent</p>
                        <h2 class="mt-0.5 text-[17px] font-semibold tracking-[-0.01em] text-ink">Check that inbox</h2>
                    </div>
                </div>
                <p class="mt-3 text-[14px] leading-relaxed text-muted">
                    If that address is on file, the link is on its way — give it a few minutes
                    and check spam. Open the newest link; any earlier one stops working.
                    Nothing arrives? Double-check the address you entered.
                </p>

                <div class="mt-5 flex flex-col gap-2 sm:flex-row">
                    <Link :href="'/' + workspace.slug"
                          class="inline-flex flex-1 items-center justify-center rounded-lg bg-neon px-4 py-2.5 text-[14px] font-semibold text-white transition hover:brightness-125">
                        Back to board
                    </Link>
                    <Link :href="'/' + workspace.slug + '/recover'"
                          class="inline-flex flex-1 items-center justify-center rounded-lg border border-sky bg-surface px-4 py-2.5 text-[14px] font-semibold text-muted transition hover:border-neon hover:text-neon">
                        Try another email
                    </Link>
                </div>
            </div>

            <!-- Request form -->
            <form v-else @submit.prevent="requestRecovery"
                  class="rounded-2xl border border-sky/30 bg-surface p-5 shadow-sm sm:p-6">
                <label for="email" class="mb-1.5 block text-[13px] font-semibold text-ink">
                    Recovery email
                </label>
                <input id="email" type="email" v-model="form.email"
                       autocomplete="off"
                       placeholder="the email you set for this board"
                       class="w-full rounded-lg border border-sky/30 bg-base px-3.5 py-3 text-[15px] text-ink placeholder:text-muted shadow-sm focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20">
                <p v-if="errors.email" role="alert" class="mt-2 text-[12px] text-muted">{{ errors.email[0] }}</p>
                <button type="submit" :disabled="form.processing"
                        class="mt-4 w-full cursor-pointer rounded-lg bg-neon py-3.5 text-[15px] font-bold text-white transition hover:brightness-125 disabled:opacity-60">
                    Send recovery link
                </button>
                <p class="mt-3 text-center text-[12px] text-muted/70">
                    No recovery email was set? The owner link can't be recovered —
                    that's the trade-off of no accounts.
                </p>
            </form>
        </div>
    </AppLayout>
</template>
