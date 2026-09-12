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

const openNameRef = ref(null)

function recover() {
    const url = recoveryUrl()
    // Nothing typed yet: the recovery URL is built from the name in the field
    // above, so put the cursor there. Disabling the control instead meant the
    // page had to explain, in a second line of text, how to un-disable it.
    if (!url) {
        openNameRef.value?.focus()
        return
    }
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

const shareCopied = ref(false)
function copyShareLink() {
    window.copyText(flash.value.createdUrl).then(() => {
        shareCopied.value = true
        setTimeout(() => { shareCopied.value = false }, 2000)
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

// Removing a recent board only clears the local shortcut, but if the user
// hasn't saved its link elsewhere this is their only way back, so confirm
// in a styled dialog (matches the report/QR modals elsewhere in the app).
const forgetTarget = ref(null)

function forget(ws) {
    forgetTarget.value = ws
}

function confirmForget() {
    if (! forgetTarget.value) return
    router.post('/workspaces/forget', { slug: forgetTarget.value.slug }, {
        preserveScroll: true,
        onFinish: () => { forgetTarget.value = null },
    })
}
</script>

<template>
    <Head title="Create your board" />
    <AppLayout>
        <div class="op mx-auto flex w-full max-w-md flex-1 flex-col justify-center px-4 py-8 sm:px-5 sm:py-12">
            <header class="mb-7 text-center">
                <!-- A link, not a label. AppLayout carries no nav, so this page had
                     no route back to the homepage at all — browser Back was it. The
                     mark + wordmark matches the welcome header; alt="" because the
                     adjacent text already names it, and alt="SlipNote logo" there
                     makes a screen reader say the name twice. -->
                <a href="/"
                   class="mb-3 inline-flex items-center gap-2 py-1 text-[16px] font-bold tracking-[-0.01em] text-teal transition hover:opacity-80">
                    <!-- 28px, between this page's old 24 and the welcome header's 32.
                         The welcome mark sits in a nav bar among other elements; alone
                         above a 34px H1, a 32px square out-weighs the heading's ~24px
                         cap height and the logo beats the page's actual message. -->
                    <img src="/logo-mark.png" alt="" class="size-7 shrink-0">
                    SlipNote
                </a>
                <template v-if="flash.ownerUrl">
                    <h1 class="op-title text-[2.15rem] font-bold text-ink">Save your owner link</h1>
                    <p class="mx-auto mt-2 max-w-sm text-[15px] text-muted">
                        It's shown only once — copy it or download it before you continue.
                    </p>
                </template>
                <template v-else>
                    <h1 class="op-title text-[2.15rem] font-bold text-ink">Create your board</h1>
                    <p class="mx-auto mt-2 max-w-sm text-[15px] text-muted">
                        The board where your class keeps its notes, slides and past papers. One link, no accounts, no passwords.
                    </p>
                </template>
            </header>

            <!-- Owner receipt card -->
            <template v-if="flash.ownerUrl">
                <!-- Neutral card, not a tinted one. This is the only card on the
                     screen, so the tint distinguished it from nothing — it just
                     washed the page blue and competed with the red block, which is
                     the one thing here that genuinely needs to be noticed. -->
                <div class="op-card p-5 sm:p-6">
                    <p v-if="flash.recovered" class="text-[15px] font-bold text-teal">Owner access to "{{ flash.createdName }}" is restored</p>
                    <p v-else class="text-[15px] font-bold text-teal">"{{ flash.createdName }}" is created 🎉</p>

                    <div class="mt-4">
                        <p class="mb-1.5 flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.06em] text-danger">
                            <svg aria-hidden="true" class="size-3.5 shrink-0" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="4" y="8.5" width="12" height="8" rx="2" />
                                <path d="M7 8.5V6a3 3 0 0 1 6 0v2.5" />
                            </svg>
                            Private owner link: never share this
                        </p>
                        <p class="w-full cursor-text break-all rounded-lg border border-danger/40 bg-danger/10 px-3 py-2.5 font-mono text-[12px] leading-relaxed text-ink select-all"
                           @click="$event.target.select?.() || window.getSelection().selectAllChildren($event.target)">{{ flash.ownerUrl }}</p>
                        <p class="mt-1.5 text-[11px] text-danger">Anyone with this link can control or delete the board.</p>
                    </div>

                    <div class="mt-2.5 flex gap-2">
                        <button type="button" @click="copyOwnerLink"
                                class="op-press min-h-11 flex-1 cursor-pointer rounded-full bg-neon text-[13px] font-semibold text-white sm:min-h-9">
                            <span v-if="!copied">Copy link</span>
                            <span v-else>Copied ✓</span>
                        </button>
                        <!-- Neutral, not a second accent. "Copy link" is the one
                             primary action on this screen; when the accent also
                             marks both secondaries and a section label, it stops
                             pointing at anything. -->
                        <button type="button" @click="downloadTxt"
                                class="op-press min-h-11 flex-1 cursor-pointer rounded-full border border-muted/50 bg-base text-[13px] font-semibold text-muted transition hover:bg-sky/30 hover:text-ink sm:min-h-9">
                            <span v-if="!downloaded">Download .txt</span>
                            <span v-else>Saved ✓</span>
                        </button>
                    </div>

                    <div class="mt-4">
                        <!-- Labelled "once it has files": an empty board shared
                             into a group chat converts nobody and doesn't get a
                             second look. Save the link now, share it later. -->
                        <!-- Muted, not accent. Paired against the red "never share
                             this" label above, red-vs-neutral says one section is
                             dangerous and one is ordinary; red-vs-blue said both
                             were special and left the reader to work out which. -->
                        <p class="mb-1.5 flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.06em] text-muted">
                            <svg aria-hidden="true" class="size-3.5 shrink-0" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M8.5 11.5a3 3 0 0 0 4.24 0l2.12-2.12a3 3 0 0 0-4.24-4.24l-.7.7" />
                                <path d="M11.5 8.5a3 3 0 0 0-4.24 0L5.14 10.62a3 3 0 0 0 4.24 4.24l.7-.7" />
                            </svg>
                            Share with classmates — once it has files
                        </p>
                        <!-- Not a live link on purpose: navigating away here would
                             discard the owner link, which is shown only once. -->
                        <!-- Box above, button below — the same shape as the owner
                             section. Beside the box, a fixed-height pill has to match
                             a box that wraps to one or two lines depending on the
                             board name: stretch it and it renders as a circle, don't
                             and it's a lone oval carrying a four-letter word. -->
                        <!-- bg-base now the card is bg-surface: a white box on a
                             white card is the same tone as what's behind it. -->
                        <p class="w-full cursor-text break-all rounded-lg border border-sky bg-base px-3 py-2.5 font-mono text-[12px] leading-relaxed text-ink select-all">{{ flash.createdUrl }}</p>
                        <!-- "Copy share link", not "Copy": there is already a "Copy
                             link" button above it that copies the *owner* link, and
                             on this screen confusing those two is the one mistake
                             with no way back. -->
                        <button type="button" @click="copyShareLink"
                                class="op-press mt-2 min-h-11 w-full cursor-pointer rounded-full border border-muted/50 bg-base text-[13px] font-semibold text-muted transition hover:bg-sky/30 hover:text-ink sm:min-h-9">
                            <span v-if="!shareCopied">Copy share link</span>
                            <span v-else>Copied ✓</span>
                        </button>
                    </div>

                    <label class="mt-5 flex items-start gap-2.5 text-[13px] text-ink">
                        <input type="checkbox" v-model="saved"
                               class="mt-0.5 size-4.5 shrink-0 cursor-pointer accent-neon">
                        <span>I've saved the owner link; I won't see it again.</span>
                    </label>

                    <button type="button" @click="proceed" :disabled="!saved"
                            class="op-press mt-4 min-h-11 w-full rounded-full text-[15px] font-semibold
                                   enabled:cursor-pointer enabled:bg-neon enabled:text-white enabled:shadow-sm
                                   disabled:cursor-not-allowed disabled:border disabled:border-dashed disabled:border-sky disabled:bg-base disabled:text-muted">
                        <span v-if="saved">Add files to {{ flash.createdName }}</span>
                        <span v-else>Tick the box above to continue</span>
                    </button>
                </div>
            </template>

            <!-- Create form -->
            <template v-else>
                <form @submit.prevent="create" class="op-card p-5 sm:p-6">
                    <label for="name" class="mb-2 block text-[13px] font-semibold text-ink">Board name</label>
                    <input id="name" type="text" v-model="createForm.name"
                           placeholder="Computer Science - Level 100" autofocus
                           class="w-full rounded-xl border border-sky bg-base px-3.5 py-2.5 text-[15px] text-ink placeholder:text-muted shadow-sm focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20">

                    <div class="mt-2 text-[12px]">
                        <span v-if="errors.name" role="alert" class="text-danger">{{ errors.name[0] }}</span>
                        <span v-else-if="slugPreview" class="text-muted">
                            Your link: <span class="font-semibold text-ink">/{{ slugPreview }}</span>
                        </span>
                        <span v-else class="text-muted">
                            Your link will look like <span class="font-medium text-ink">/computer-science-level-100</span>
                        </span>
                    </div>

                    <button type="submit" :disabled="createForm.processing"
                            class="op-press mt-4 min-h-11 w-full cursor-pointer rounded-full bg-neon text-[15px] font-semibold text-white disabled:opacity-60">
                        Create board
                    </button>
                    <p class="mt-3 text-[12px] text-muted">
                        You'll get a link to share, and a private owner link to keep.
                    </p>
                </form>

                <!-- Recent boards first among the "returning user" paths — one click
                     beats retyping a name. -->
                <div v-if="props.recent.length" class="op-card mt-7 px-5 py-4 sm:px-6">
                    <h2 class="mb-2 text-[12px] font-semibold uppercase tracking-[0.06em] text-muted">Your recent boards</h2>
                    <!-- No divide-y here. These rows are a single line, so a row rule
                         lands immediately under the link's own underline: two lines a
                         few pixels apart meaning different things. The operator board
                         list keeps its rules because a metadata line sits between. -->
                    <ul>
                        <li v-for="ws in props.recent" :key="ws.slug"
                            class="flex items-center justify-between gap-3">
                            <!-- Same link treatment as the operator console's board
                                 list and the course page's filenames: ink text with a
                                 muted underline. The dashed teal rule here was the
                                 app's only one. -->
                            <a :href="'/' + ws.slug"
                               class="min-w-0 flex-1 truncate py-2 text-[14px] font-medium text-ink underline decoration-muted/40 underline-offset-4 hover:decoration-ink/40">
                                {{ ws.name }}
                            </a>
                            <!-- Keeps its text label rather than becoming the round
                                 icon button used elsewhere: an × or bin here would
                                 read as "delete the board", which is the one thing
                                 this control must not be mistaken for. -->
                            <button type="button" @click="forget(ws)"
                                    :aria-label="`Remove ${ws.name} from this list`"
                                    class="op-press inline-flex min-h-11 shrink-0 cursor-pointer items-center rounded-full px-2.5 text-[12px] text-muted transition hover:bg-danger/10 hover:text-danger sm:min-h-9">
                                Remove
                            </button>
                        </li>
                    </ul>
                    <p class="mt-2 text-[11px] text-muted">
                        Saved on this browser only. Removing clears the shortcut; the board stays.
                    </p>
                </div>

                <div class="op-card px-5 py-5 sm:px-6"
                     :class="props.recent.length ? 'mt-4' : 'mt-7'">
                    <h2 class="text-[13px] font-semibold text-ink">Already made one? Find it by name:</h2>
                    <form @submit.prevent="open" class="mt-2.5 flex flex-col gap-2 sm:flex-row">
                        <input id="openName" ref="openNameRef" type="text" v-model="openForm.openName"
                               aria-label="Board name to open"
                               placeholder="Find a board by name…"
                               class="w-full min-w-0 flex-1 rounded-xl border border-sky bg-base px-3.5 py-2.5 text-[15px] text-ink placeholder:text-muted shadow-sm focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20">
                        <!-- Secondary, not a second primary. The page has one job —
                             create a board — and a solid blue "Open" sitting beside a
                             solid blue "Create board" reads as two equal choices.
                             bg-base, not bg-surface: this button sits *inside* an
                             op-card, which is bg-surface, so a surface fill was the
                             same tone as what's behind it — and bg-base vs bg-surface
                             is only 1.09:1, so the border, not the fill, marks this
                             button's extent. /50 (2.80 dark, 2.13 light) sits below
                             the 3:1 of WCAG 1.4.11 by choice: the label itself runs
                             5.5–7.9:1 and identifies the control, so the outline is
                             decoration. /80 passed outright but at ~4x the contrast
                             of every other bordered control on the page, it read as
                             the loudest thing on a screen full of quiet chrome. -->
                        <button type="submit" :disabled="openForm.processing"
                                class="op-press min-h-11 w-full shrink-0 cursor-pointer rounded-full border border-muted/50 bg-base px-5 text-[15px] font-semibold text-muted transition hover:border-muted hover:bg-sky/30 hover:text-ink disabled:opacity-60 sm:w-auto">
                            Open
                        </button>
                    </form>
                    <span v-if="errors.openName" role="alert" class="mt-2 block text-[13px] text-danger">{{ errors.openName[0] }}</span>
                    <div class="mt-3 border-t border-sky pt-3">
                        <p class="text-[12px] text-muted">
                            Lost your owner link?
                            <button type="button" @click="recover"
                                    class="cursor-pointer font-semibold text-neon underline decoration-neon/40 underline-offset-4 transition hover:decoration-neon">
                                Recover it using your board name
                            </button>
                        </p>
                    </div>
                </div>
            </template>
        </div>

        <!-- Remove-from-list confirm. Matches the report/QR modals: backdrop
             click + Escape close, danger-styled confirm. -->
        <div v-if="forgetTarget" class="fixed inset-0 z-50 flex items-end justify-center sm:items-center"
             role="dialog" aria-modal="true" aria-label="Remove board from list"
             @keydown.escape.window="forgetTarget = null">
            <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" @click="forgetTarget = null"></div>
            <div class="relative max-h-[90vh] w-full max-w-md overflow-y-auto rounded-t-2xl bg-surface px-6 pb-[max(1.5rem,env(safe-area-inset-bottom))] pt-3 shadow-xl sm:rounded-2xl sm:pt-6">
                <div class="mx-auto mb-3 h-1 w-10 rounded-full bg-muted/30 sm:hidden"></div>
                <button type="button" @click="forgetTarget = null" aria-label="Close"
                        class="op-press absolute right-3 top-3 hidden size-9 cursor-pointer items-center justify-center rounded-full text-muted transition hover:bg-danger/10 hover:text-danger sm:flex">
                    <svg aria-hidden="true" class="size-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round">
                        <path d="M5 5l10 10M15 5L5 15" />
                    </svg>
                </button>
                <h2 class="pr-10 text-[15px] font-semibold text-ink">Remove &ldquo;{{ forgetTarget.name }}&rdquo; from this list?</h2>
                <p class="mt-2 text-[13px] leading-relaxed text-muted">
                    This only clears the shortcut on this browser &mdash; the board itself stays.
                    If you haven&rsquo;t saved its link, you may lose your way back.
                </p>
                <div class="mt-5 flex items-center justify-end gap-2">
                    <button type="button" @click="forgetTarget = null"
                            class="op-press inline-flex min-h-11 cursor-pointer items-center rounded-full px-4 text-[14px] font-semibold text-muted transition hover:bg-sky/30 hover:text-ink">Cancel</button>
                    <button type="button" @click="confirmForget"
                            class="op-press inline-flex min-h-11 cursor-pointer items-center rounded-full bg-neon px-5 text-[14px] font-semibold text-white">
                        Remove from list
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
