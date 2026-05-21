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
        <div class="mx-auto w-full max-w-md flex-1 px-5 pt-20 pb-12">
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
            <div v-if="flash.done" class="rounded-2xl border border-sky/30 bg-surface p-6 text-center shadow-sm">
                <p class="text-[15px] font-semibold text-ink">Check that inbox</p>
                <p class="mx-auto mt-2 max-w-sm text-[14px] text-muted">
                    If that email is on file for this board, a new owner link is
                    on its way. It may take a minute — and check spam. The
                    previous owner link no longer works.
                </p>
                <Link :href="'/' + workspace.slug"
                      class="mt-5 inline-block text-[13px] font-semibold text-neon hover:underline">
                    ← Back to {{ workspace.name }}
                </Link>
            </div>

            <!-- Request form -->
            <form v-else @submit.prevent="requestRecovery"
                  class="rounded-2xl border border-sky/30 bg-surface p-6 shadow-sm">
                <label for="email" class="mb-1.5 block text-[13px] font-semibold text-ink">
                    Recovery email
                </label>
                <input id="email" type="email" v-model="form.email"
                       autocomplete="off"
                       placeholder="the email you set for this board"
                       class="w-full rounded-lg border border-sky/30 bg-base px-3.5 py-3 text-[15px] text-ink placeholder:text-muted shadow-sm focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20">
                <p v-if="errors.email" role="alert" class="mt-2 text-[12px] text-muted">{{ errors.email[0] }}</p>
                <button type="submit" :disabled="form.processing"
                        class="mt-4 w-full cursor-pointer rounded-lg bg-neon py-3.5 text-[15px] font-bold text-base transition hover:brightness-125 disabled:opacity-60">
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
