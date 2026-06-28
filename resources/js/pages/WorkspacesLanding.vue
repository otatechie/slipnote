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

function recoveryUrl() {
    const slug = openForm.openName.trim().toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')

    return slug ? `/${slug}/recover` : null
}

function recover() {
    const url = recoveryUrl()
    if (!url) return
    router.visit(url)
}

// Owner receipt
const saved = ref(false)
const copied = ref(false)
const downloaded = ref(false)

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
        'OWNER (keep private - controls the board):\n' + flash.value.ownerUrl + '\n\n' +
        'SHARE WITH CLASSMATES:\n' + flash.value.createdUrl + '\n'
    const a = document.createElement('a')
    const blob = new Blob([body], { type: 'text/plain' })
    a.href = URL.createObjectURL(blob)
    a.download = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') + '-owner-link.txt'
    a.click()
    URL.revokeObjectURL(a.href)
    saved.value = true
    downloaded.value = true
    setTimeout(() => { downloaded.value = false }, 2000)
}

function proceed() {
    router.visit(flash.value.createdUrl)
}

function forget(ws) {
    // Removing a recent board only clears the local shortcut, but if the user
    // hasn't saved its link elsewhere this is their only way back, so confirm.
    if (! window.confirm(`Remove "${ws.name}" from this list? This only clears the shortcut on this browser; the board itself stays. If you haven't saved its link, you may lose your way back.`)) {
        return
    }
    router.post('/workspaces/forget', { slug: ws.slug }, { preserveScroll: true })
}
</script>

<template>
    <Head title="Create your board" />
    <AppLayout>
        <div class="mx-auto flex w-full max-w-md flex-1 flex-col justify-center px-4 py-8 sm:px-5 sm:py-12">
            <header class="mb-7 text-center">
                <p class="mb-2 text-xs font-semibold uppercase tracking-[0.08em] text-muted">SlipNote</p>
                <template v-if="flash.ownerUrl">
                    <h1 class="text-3xl font-bold tracking-tight text-ink">Save your owner link</h1>
                    <p class="mx-auto mt-2 max-w-sm text-[15px] text-muted">
                        Your board is created. Keep the link below; it's shown only once.
                    </p>
                </template>
                <template v-else>
                    <h1 class="text-3xl font-bold tracking-tight text-ink">Create your board</h1>
                    <p class="mx-auto mt-2 max-w-sm text-[15px] text-muted">
                        A board is one shared space for your class. Everyone drops their notes, slides and past papers in. No account, no password.
                    </p>
                </template>
            </header>

            <!-- Owner receipt card -->
            <template v-if="flash.ownerUrl">
                <div class="rounded-2xl border border-neon/40 bg-neon/10 p-5 sm:p-6">
                    <p class="text-[15px] font-bold text-neon">"{{ flash.createdName }}" is ready 🎉</p>
                    <p class="mt-1.5 text-[13px] text-muted">
                        You can add a recovery email later to get it back if you lose it.
                    </p>

                    <div class="mt-4">
                        <p class="mb-1.5 flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.06em] text-danger">
                            🔒 Private owner link: never share this
                        </p>
                        <p class="w-full cursor-text break-all rounded-lg border border-danger/40 bg-danger/10 px-3 py-2.5 font-mono text-[12px] leading-relaxed text-ink select-all"
                           @click="$event.target.select?.() || window.getSelection().selectAllChildren($event.target)">{{ flash.ownerUrl }}</p>
                        <p class="mt-1.5 text-[11px] text-danger">Anyone with this link can control or delete the board.</p>
                    </div>

                    <div class="mt-2.5 flex gap-2">
                        <button type="button" @click="copyOwnerLink"
                                class="h-9 flex-1 cursor-pointer rounded-lg bg-neon text-[13px] font-bold text-white transition hover:brightness-125">
                            <span v-if="!copied">Copy link</span>
                            <span v-else>Copied ✓</span>
                        </button>
                        <button type="button" @click="downloadTxt"
                                class="h-9 flex-1 cursor-pointer rounded-lg border border-neon/50 text-[13px] font-semibold text-neon transition hover:bg-neon/10">
                            <span v-if="!downloaded">Download .txt</span>
                            <span v-else>Saved ✓</span>
                        </button>
                    </div>

                    <div class="mt-4">
                        <p class="mb-1.5 text-[11px] font-bold uppercase tracking-[0.06em] text-teal">
                            ✓ Share this with classmates
                        </p>
                        <a :href="flash.createdUrl"
                           class="block w-full break-all rounded-lg border border-sky bg-surface px-3 py-2.5 font-mono text-[12px] leading-relaxed text-ink hover:border-neon">{{ flash.createdUrl }}</a>
                    </div>

                    <label class="mt-5 flex items-start gap-2.5 text-[13px] text-ink">
                        <input type="checkbox" v-model="saved"
                               class="mt-0.5 size-4 shrink-0 cursor-pointer accent-neon">
                        <span>I've saved the owner link; I won't see it again.</span>
                    </label>

                    <button type="button" @click="proceed" :disabled="!saved"
                            class="mt-4 w-full rounded-lg py-3.5 text-[15px] font-bold transition
                                   enabled:cursor-pointer enabled:bg-neon enabled:text-white enabled:shadow-sm enabled:hover:brightness-125
                                   disabled:cursor-not-allowed disabled:border disabled:border-dashed disabled:border-sky disabled:bg-base disabled:text-muted">
                        <span v-if="saved">Continue to {{ flash.createdName }}</span>
                        <span v-else>Tick the box above to continue</span>
                    </button>
                </div>
            </template>

            <!-- Create form -->
            <template v-else>
                <form @submit.prevent="create"
                      class="rounded-2xl border border-sky bg-surface p-5 shadow-[0_4px_14px_-12px_rgba(51,29,44,0.3)] sm:p-6">
                    <label for="name" class="mb-1.5 block text-[13px] font-semibold text-ink">Board name</label>
                    <p class="mb-2 text-[12px] text-muted">Use your class level or year; it holds that group's courses.</p>
                    <input id="name" type="text" v-model="createForm.name"
                           placeholder="Computer Science - Level 100" autofocus
                           class="w-full rounded-lg border border-sky bg-base px-3.5 py-3 text-[15px] text-ink placeholder:text-muted shadow-sm focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20">

                    <div class="mt-2 text-[12px]">
                        <span v-if="errors.name" role="alert" class="text-danger">{{ errors.name[0] }}</span>
                        <span v-else-if="slugPreview" class="text-muted">
                            Your link: <span class="font-semibold text-ink">/{{ slugPreview }}</span>
                        </span>
                        <span v-else class="text-muted/70">
                            Your link will look like <span class="font-medium text-muted">/computer-science-level-100</span>
                        </span>
                    </div>

                    <button type="submit" :disabled="createForm.processing"
                            class="mt-4 w-full cursor-pointer rounded-lg bg-neon py-3.5 text-[15px] font-bold text-white transition hover:brightness-125 disabled:opacity-60">
                        Create board
                    </button>
                    <p class="mt-3 text-center text-[12px] text-muted">
                        You'll get a link to share, and a private owner link to keep.
                    </p>
                </form>

                <div class="mt-7 rounded-2xl border border-sky bg-surface px-5 py-5 text-center shadow-[0_4px_14px_-12px_rgba(51,29,44,0.3)] sm:px-6">
                    <p class="text-[13px] text-ink">Already made one? Your saved link is the fastest way back, or find it by name:</p>
                    <form @submit.prevent="open" class="mx-auto mt-2.5 flex max-w-sm flex-col gap-2 sm:flex-row">
                        <input id="openName" type="text" v-model="openForm.openName"
                               aria-label="Board name"
                               placeholder="Computer Science - Level 100"
                               class="w-full min-w-0 flex-1 rounded-lg border border-sky bg-base px-3.5 py-3 text-[15px] text-ink placeholder:text-muted shadow-sm focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20">
                        <button type="submit" :disabled="openForm.processing"
                                class="w-full shrink-0 cursor-pointer rounded-lg bg-neon px-5 py-3 text-[15px] font-semibold text-white transition hover:brightness-125 disabled:opacity-60 sm:w-auto">
                            Open
                        </button>
                    </form>
                    <span v-if="errors.openName" role="alert" class="mt-2 block text-[13px] text-muted">{{ errors.openName[0] }}</span>
                    <div class="mt-3 border-t border-sky/40 pt-3">
                        <p class="text-[12px] text-muted">
                            Lost your owner link?
                            <button type="button" @click="recover" :disabled="!recoveryUrl()"
                                    class="cursor-pointer font-semibold text-neon transition hover:underline disabled:cursor-not-allowed disabled:text-muted/50 disabled:no-underline">
                                Recover it with this board name
                            </button>
                        </p>
                        <p class="mt-1 text-[11px] text-muted/80">
                            <span v-if="recoveryUrl()">We'll take you to the recovery page for this board.</span>
                            <span v-else>Type your board name in the field above first.</span>
                        </p>
                    </div>
                </div>

                <div v-if="props.recent.length" class="mt-4 rounded-2xl border border-sky bg-surface px-5 py-4 shadow-[0_4px_14px_-12px_rgba(51,29,44,0.3)] sm:px-6">
                    <p class="mb-2 text-[12px] font-semibold uppercase tracking-[0.06em] text-muted">Your recent boards</p>
                    <ul class="divide-y divide-sky/30">
                        <li v-for="ws in props.recent" :key="ws.slug"
                            class="flex items-center justify-between gap-3 py-2">
                            <a :href="'/' + ws.slug"
                               class="min-w-0 flex-1 truncate text-[14px] font-medium text-neon hover:underline">
                                {{ ws.name }}
                            </a>
                            <button type="button" @click="forget(ws)"
                                    :aria-label="`Remove ${ws.name} from this list`"
                                    class="shrink-0 cursor-pointer rounded-md px-2 py-1 text-[12px] text-muted/70 transition hover:bg-sky/40 hover:text-ink">
                                Remove
                            </button>
                        </li>
                    </ul>
                    <p class="mt-2 text-[11px] text-muted/80">
                        Saved on this browser only, not synced or shared. Removing one just clears the shortcut here; the board stays.
                    </p>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
