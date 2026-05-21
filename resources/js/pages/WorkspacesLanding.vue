<script setup>
import { computed, ref } from 'vue'
import { Head, useForm, usePage, router } from '@inertiajs/vue3'
import AppLayout from '@/components/AppLayout.vue'

const props = defineProps({
    recent: { type: Array, default: () => [] },
})

const page = usePage()

// Flash data from server
const flash = computed(() => page.props.flash)
const errors = computed(() => page.props.errors)

// Create form
const createForm = useForm({ name: '' })

const slugPreview = computed(() => {
    const s = createForm.name.trim().toLowerCase()
    return s.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '')
})

function create() {
    createForm.post('/workspaces', { preserveScroll: true })
}

// Open form
const openForm = useForm({ openName: '' })

function open() {
    openForm.post('/workspaces/open', { preserveScroll: true })
}

// Owner receipt
const saved = ref(false)
const copied = ref(false)

function copyOwnerLink() {
    window.copyText(flash.value.ownerUrl).then(() => {
        copied.value = true
        saved.value = true
        setTimeout(() => { copied.value = false }, 2000)
    }).catch(() => {})
}

function downloadTxt() {
    const name = flash.value.createdName
    const body =
        'SlipNote owner link for "' + name + '"\n\n' +
        'OWNER (keep private - controls the workspace):\n' + flash.value.ownerUrl + '\n\n' +
        'SHARE WITH CLASSMATES:\n' + flash.value.createdUrl + '\n'
    const a = document.createElement('a')
    const blob = new Blob([body], { type: 'text/plain' })
    a.href = URL.createObjectURL(blob)
    a.download = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') + '-owner-link.txt'
    a.click()
    URL.revokeObjectURL(a.href)
    saved.value = true
}

function proceed() {
    router.visit(flash.value.createdUrl)
}

function forget(slug) {
    router.post('/workspaces/forget', { slug }, { preserveScroll: true })
}
</script>

<template>
    <Head title="Create your course board" />
    <AppLayout>
        <div class="mx-auto w-full max-w-md flex-1 px-5 pt-20 pb-12">
            <header class="mb-7 text-center">
                <p class="mb-2 text-xs font-semibold uppercase tracking-[0.08em] text-muted">SlipNote</p>
                <template v-if="flash.ownerUrl">
                    <h1 class="text-3xl font-bold tracking-tight text-ink">Save your owner link</h1>
                    <p class="mx-auto mt-2 max-w-sm text-[15px] text-muted">
                        Your board is created. Keep the link below — it's shown only once.
                    </p>
                </template>
                <template v-else>
                    <h1 class="text-3xl font-bold tracking-tight text-ink">Create your course board</h1>
                    <p class="mx-auto mt-2 max-w-sm text-[15px] text-muted">
                        Name it, get a link, share it with your classmates. No account, no password.
                    </p>
                </template>
            </header>

            <!-- Owner receipt card -->
            <template v-if="flash.ownerUrl">
                <div class="rounded-2xl border border-neon/40 bg-neon/10 p-6">
                    <p class="text-[15px] font-bold text-neon">"{{ flash.createdName }}" is ready 🎉</p>
                    <p class="mt-1.5 text-[13px] text-ink">
                        <span class="font-semibold">Save your owner link — shown once, not recoverable.</span>
                        It controls this workspace.
                    </p>

                    <p class="mt-4 w-full cursor-text break-all rounded-lg bg-sky/50 px-3 py-2.5 font-mono text-[12px] leading-relaxed text-ink select-all"
                       @click="$event.target.select?.() || window.getSelection().selectAllChildren($event.target)">{{ flash.ownerUrl }}</p>

                    <div class="mt-2.5 flex gap-2">
                        <button type="button" @click="copyOwnerLink"
                                class="h-9 flex-1 cursor-pointer rounded-lg bg-neon text-[13px] font-bold text-white transition hover:brightness-125">
                            <span v-if="!copied">Copy link</span>
                            <span v-else>Copied ✓</span>
                        </button>
                        <button type="button" @click="downloadTxt"
                                class="h-9 flex-1 cursor-pointer rounded-lg border border-neon/50 text-[13px] font-semibold text-neon transition hover:bg-neon/10">
                            Download .txt
                        </button>
                    </div>

                    <p class="mt-3 text-[12px] text-muted">
                        Classmates only need
                        <a :href="flash.createdUrl" class="font-semibold text-neon hover:underline">{{ flash.createdUrl }}</a>
                    </p>

                    <label class="mt-5 flex items-start gap-2.5 text-[13px] text-ink">
                        <input type="checkbox" v-model="saved"
                               class="mt-0.5 size-4 shrink-0 cursor-pointer accent-neon">
                        <span>I've saved the owner link — I won't see it again.</span>
                    </label>

                    <button type="button" @click="proceed" :disabled="!saved"
                            class="mt-4 w-full rounded-lg py-3.5 text-[15px] font-bold transition
                                   enabled:cursor-pointer enabled:bg-neon enabled:text-white enabled:hover:brightness-125
                                   disabled:cursor-not-allowed disabled:border disabled:border-sky disabled:bg-surface disabled:text-muted">
                        Continue to {{ flash.createdName }}
                    </button>
                </div>
            </template>

            <!-- Create form -->
            <template v-else>
                <form @submit.prevent="create"
                      class="rounded-2xl border border-sky/30 bg-surface p-6 shadow-sm">
                    <label for="name" class="mb-1.5 block text-[13px] font-semibold text-ink">Workspace name</label>
                    <input id="name" type="text" v-model="createForm.name"
                           placeholder="e.g. CS Masters 2026" autofocus
                           class="w-full rounded-lg border border-sky/30 bg-base px-3.5 py-3 text-[15px] text-ink placeholder:text-muted shadow-sm focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20">

                    <div class="mt-2 min-h-5 text-[12px]">
                        <span v-if="errors.name" role="alert" class="text-red-600">{{ errors.name[0] }}</span>
                        <span v-else-if="slugPreview" class="text-muted">
                            Your link: <span class="font-semibold text-ink">/{{ slugPreview }}</span>
                        </span>
                    </div>

                    <button type="submit" :disabled="createForm.processing"
                            class="mt-3 w-full cursor-pointer rounded-lg bg-neon py-3.5 text-[15px] font-bold text-white transition hover:brightness-125 disabled:opacity-60">
                        Create workspace
                    </button>
                    <p class="mt-3 text-center text-[12px] text-muted">
                        You'll get a link to share — and a private owner link to keep.
                    </p>
                </form>

                <div class="mt-7 rounded-2xl border border-sky/60 bg-surface/60 px-6 py-5 text-center">
                    <p class="text-[13px] text-ink">Already made one? Your saved link is the fastest way back — or find it by name:</p>
                    <form @submit.prevent="open" class="mx-auto mt-2.5 flex max-w-sm gap-2">
                        <input id="openName" type="text" v-model="openForm.openName"
                               aria-label="Workspace name"
                               placeholder="CS Masters 2026"
                               class="h-10 flex-1 rounded-lg border border-sky/30 bg-base px-3.5 text-[14px] text-ink placeholder:text-muted shadow-sm focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20">
                        <button type="submit" :disabled="openForm.processing"
                                class="h-10 shrink-0 cursor-pointer rounded-lg bg-neon px-5 text-[14px] font-semibold text-white transition hover:brightness-125 disabled:opacity-60">
                            Open
                        </button>
                    </form>
                    <span v-if="errors.openName" role="alert" class="mt-2 block text-[13px] text-muted">{{ errors.openName[0] }}</span>
                </div>

                <div v-if="props.recent.length" class="mt-4 rounded-2xl border border-sky/30 bg-surface/40 px-6 py-4">
                    <p class="mb-2 text-[12px] font-semibold uppercase tracking-[0.06em] text-muted">Your recent workspaces</p>
                    <ul class="divide-y divide-sky/30">
                        <li v-for="ws in props.recent" :key="ws.slug"
                            class="flex items-center justify-between gap-3 py-2">
                            <a :href="'/' + ws.slug"
                               class="min-w-0 flex-1 truncate text-[14px] font-medium text-neon hover:underline">
                                {{ ws.name }}
                            </a>
                            <button type="button" @click="forget(ws.slug)"
                                    aria-label="Forget this workspace"
                                    class="cursor-pointer rounded-md px-2 py-1 text-[12px] text-muted transition hover:bg-sky/40 hover:text-ink">
                                Forget
                            </button>
                        </li>
                    </ul>
                    <p class="mt-2 text-[11px] text-muted/80">
                        Saved on this browser only — not synced or accessible to anyone else.
                    </p>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
