<script setup>
import { computed, nextTick, ref } from 'vue'
import { Head, useForm, usePage, router } from '@inertiajs/vue3'
import AppLayout from '@/components/AppLayout.vue'
import { vModalFocus } from '@/components/modalFocus'

const props = defineProps({
    recent: { type: Array, default: () => [] },
})

const page = usePage()

// Flash data from server
const flash = computed(() => page.props.flash)
const errors = computed(() => page.props.errors)

// Two pages in one. A browser that already manages a board is coming back
// to it ("All boards" on a board links here), so it gets the list first and
// the create form behind "New board". A first visit gets the form.
const returning = computed(() => props.recent.length > 0)

// Create form
const createForm = useForm({ name: '' })
const creating = ref(!returning.value || !!errors.value?.name)
const nameField = ref(null)

function startCreating() {
    creating.value = true
    nextTick(() => nameField.value?.focus())
}

function boardSummary(ws) {
    if (ws.courses === 0) return 'No courses yet'
    const courses = ws.courses === 1 ? '1 course' : `${ws.courses} courses`
    if (ws.files === 0) return `${courses} · no files yet`
    return `${courses} · ${ws.files === 1 ? '1 file' : `${ws.files} files`}`
}

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
// Finding by name is the rare path, so its field stays folded into a line of
// links until asked for.
const finding = ref(!!errors.value?.openName)

function startFinding() {
    finding.value = true
    nextTick(() => openNameRef.value?.focus())
}

function recover() {
    const url = recoveryUrl()
    // Nothing typed yet: the recovery URL is built from the board name, so
    // open the name field and put the cursor there. Disabling the control
    // instead meant explaining, in more text, how to un-disable it.
    if (!url) {
        startFinding()
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
    <Head :title="flash.ownerUrl ? 'Save your owner link' : (returning ? 'Your boards' : 'Create a board')" />
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
                <template v-else-if="returning">
                    <h1 class="op-title text-[2.15rem] font-bold text-ink">Your boards</h1>
                    <p class="mx-auto mt-2 max-w-sm text-[15px] text-muted">The boards you manage on this browser.</p>
                </template>
                <template v-else>
                    <h1 class="op-title text-[2.15rem] font-bold text-ink">Create a board</h1>
                    <p class="mx-auto mt-2 max-w-xs text-balance text-[15px] text-muted">
                        Where your class keeps its notes, slides and past papers. One link, no accounts.
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
                    <p v-else-if="flash.ownerReset" class="text-[15px] font-bold text-teal">New owner link for "{{ flash.createdName }}". The old one no longer works.</p>
                    <p v-else class="text-[15px] font-bold text-teal">"{{ flash.createdName }}" is created 🎉</p>

                    <!-- One red thing: the label. The box, the label and a third red
                         warning line made the whole top half shout, so the box is
                         only faintly tinted and the consequence line is muted. -->
                    <div class="mt-4">
                        <p class="mb-1.5 flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.06em] text-danger">
                            <svg aria-hidden="true" class="size-3.5 shrink-0" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="4" y="8.5" width="12" height="8" rx="2" />
                                <path d="M7 8.5V6a3 3 0 0 1 6 0v2.5" />
                            </svg>
                            Owner link · keep it private
                        </p>
                        <p class="w-full cursor-text break-all rounded-lg border border-danger/25 bg-danger/5 px-3 py-2.5 font-mono text-[12px] leading-relaxed text-ink select-all"
                           @click="$event.target.select?.() || window.getSelection().selectAllChildren($event.target)">{{ flash.ownerUrl }}</p>
                        <p class="mt-1.5 text-[12px] text-muted">Anyone with this link can control or delete the board.</p>

                        <!-- "Copy owner link", not "Copy link": the share link below
                             has its own Copy, and mixing the two up is the one
                             mistake on this screen with no way back. Download is a
                             plain text button so the card has one filled control. -->
                        <div class="mt-3 flex items-center gap-2">
                            <button type="button" @click="copyOwnerLink"
                                    class="op-press min-h-11 flex-1 cursor-pointer rounded-full bg-neon text-[13px] font-semibold text-white sm:min-h-9">
                                <span v-if="!copied">Copy owner link</span>
                                <span v-else>Copied ✓</span>
                            </button>
                            <button type="button" @click="downloadTxt"
                                    class="op-press min-h-11 shrink-0 cursor-pointer rounded-full px-4 text-[13px] font-semibold text-muted transition hover:bg-sky/30 hover:text-ink sm:min-h-9">
                                <span v-if="!downloaded">Download .txt</span>
                                <span v-else>Saved ✓</span>
                            </button>
                        </div>
                    </div>

                    <div class="mt-5 border-t border-sky pt-4">
                        <!-- Labelled "once it has files": an empty board shared
                             into a group chat converts nobody and doesn't get a
                             second look. Save the link now, share it later. -->
                        <p class="mb-1.5 flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.06em] text-muted">
                            <svg aria-hidden="true" class="size-3.5 shrink-0" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M8.5 11.5a3 3 0 0 0 4.24 0l2.12-2.12a3 3 0 0 0-4.24-4.24l-.7.7" />
                                <path d="M11.5 8.5a3 3 0 0 0-4.24 0L5.14 10.62a3 3 0 0 0 4.24 4.24l.7-.7" />
                            </svg>
                            Share link · once it has files
                        </p>
                        <!-- Not a live link on purpose: navigating away here would
                             discard the owner link, which is shown only once. Copy
                             sits inside the box rather than as a pill beside or
                             under it, so it's one quiet row instead of a fourth
                             outlined button; the URL truncates since Copy is how
                             it leaves the page. -->
                        <div class="flex items-stretch overflow-hidden rounded-lg border border-sky bg-base">
                            <span class="min-w-0 flex-1 cursor-text select-all truncate px-3 py-2.5 font-mono text-[12px] leading-relaxed text-ink">{{ flash.createdUrl }}</span>
                            <button type="button" @click="copyShareLink"
                                    class="min-h-11 shrink-0 cursor-pointer border-l border-sky px-4 text-[13px] font-semibold text-neon transition hover:bg-sky/30 sm:min-h-9">
                                <span v-if="!shareCopied">Copy</span>
                                <span v-else>Copied ✓</span>
                            </button>
                        </div>
                    </div>

                    <label class="mt-6 flex cursor-pointer items-start gap-2.5 text-[13px] text-ink">
                        <input type="checkbox" v-model="saved"
                               class="mt-0.5 size-4.5 shrink-0 cursor-pointer accent-neon">
                        <span>I've saved the owner link; I won't see it again.</span>
                    </label>

                    <!-- Disabled is a flat muted fill, not a dashed outline with
                         instructions in it: the checkbox directly above already
                         says what unlocks it. A new board has no courses, and files
                         live inside a course, so the label names the step the next
                         page actually opens on. -->
                    <button type="button" @click="proceed" :disabled="!saved"
                            class="op-press mt-3 min-h-11 w-full rounded-full text-[15px] font-semibold transition
                                   enabled:cursor-pointer enabled:bg-neon enabled:text-white enabled:shadow-sm
                                   disabled:cursor-not-allowed disabled:bg-sky/50 disabled:text-muted/70">
                        <template v-if="flash.recovered || flash.ownerReset">Open {{ flash.createdName }}</template>
                        <template v-else>Add your first course</template>
                    </button>
                </div>
            </template>

            <template v-else>
                <p v-if="flash.boardDeleted" role="status"
                   class="op-toast mb-5 text-center text-sm font-medium text-ink">
                    "{{ flash.boardDeleted }}" is deleted, with every course and file on it.
                </p>
                <!-- Boards this browser manages: added on create, owner unlock and
                     recovery, never on a plain visit, so every row is one you own. -->
                <template v-if="returning">
                    <ul class="op-card">
                        <li v-for="ws in props.recent" :key="ws.slug"
                            class="flex items-center gap-1 border-t border-sky/80 py-1.5 pl-5 pr-1.5 first:border-t-0">
                            <a :href="'/' + ws.slug" class="group min-w-0 flex-1 py-2">
                                <span class="block truncate text-[15px] font-semibold text-ink group-hover:text-neon">{{ ws.name }}</span>
                                <span class="block text-[12px] text-muted">{{ boardSummary(ws) }}</span>
                            </a>
                            <!-- In a menu, not a "Remove" on every row: repeated down
                                 the list it was the loudest word on the page, for the
                                 action people take least. Labelled in full inside so it
                                 can't read as "delete the board". -->
                            <details class="op-menu relative shrink-0">
                                <summary :aria-label="`More for ${ws.name}`" title="More"
                                         class="flex size-11 cursor-pointer list-none items-center justify-center rounded-full text-muted transition hover:bg-sky/40 hover:text-ink sm:size-9">
                                    <svg aria-hidden="true" class="size-4" viewBox="0 0 20 20" fill="currentColor"><circle cx="4" cy="10" r="1.6" /><circle cx="10" cy="10" r="1.6" /><circle cx="16" cy="10" r="1.6" /></svg>
                                </summary>
                                <div class="absolute right-0 top-full z-20 mt-1 w-52 rounded-xl bg-surface p-1 shadow-xl ring-1 ring-sky">
                                    <button type="button"
                                            @click="$event.target.closest('details')?.removeAttribute('open'); forget(ws)"
                                            class="block min-h-11 w-full cursor-pointer rounded-lg px-3 py-2 text-left text-[13px] font-semibold text-ink hover:bg-sky/40 sm:min-h-0">
                                        Remove from this list
                                    </button>
                                </div>
                            </details>
                        </li>
                    </ul>

                    <!-- Outlined, not filled: on this page the boards are the point,
                         and a second board is the occasional case. -->
                    <button v-if="!creating" type="button" @click="startCreating"
                            class="op-press mt-3.5 inline-flex min-h-12 w-full cursor-pointer items-center justify-center gap-1.5 rounded-full border border-muted/50 text-[15px] font-semibold text-ink transition hover:bg-sky/30">
                        <svg aria-hidden="true" class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M10 4v12M4 10h12" /></svg>
                        New board
                    </button>
                </template>

                <!-- Create form -->
                <form v-if="creating" @submit.prevent="create" class="op-card p-5 sm:p-6" :class="returning ? 'mt-3.5' : ''">
                    <!-- "Your class", not "Board name": asked for a board name, people
                         typed a single subject ("IT for Business") and then had
                         nowhere sensible to put the courses the next page asks for. -->
                    <label for="name" class="block text-[13px] font-semibold text-ink">Your class</label>
                    <p class="mb-2 mt-0.5 text-[12px] text-muted">One board for the whole class. You'll add each course inside it.</p>
                    <input id="name" ref="nameField" type="text" v-model="createForm.name"
                           placeholder="Computer Science - Level 100" :autofocus="!returning"
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

                <!-- Find and recover: rare paths, so one line of links rather than
                     a card with its own field. The field opens in place. -->
                <div class="mt-7 text-center text-[13px] leading-relaxed text-muted">
                    <p>
                        {{ returning ? 'Looking for another board?' : 'Already made one?' }}
                        <button type="button" @click="startFinding"
                                class="inline-flex min-h-11 cursor-pointer items-center font-semibold text-neon hover:underline">{{ returning ? 'Find by name' : 'Find it by name' }}</button>
                    </p>
                    <p>
                        <button type="button" @click="recover"
                                class="inline-flex min-h-11 cursor-pointer items-center font-semibold text-neon hover:underline">Lost your owner link?</button>
                    </p>
                </div>

                <div v-if="finding" class="mt-4">
                    <form @submit.prevent="open" class="flex flex-col gap-2 sm:flex-row">
                        <input id="openName" ref="openNameRef" type="text" v-model="openForm.openName"
                               aria-label="Board name"
                               placeholder="Board name…"
                               class="w-full min-w-0 flex-1 rounded-xl border border-sky bg-surface px-3.5 py-2.5 text-[15px] text-ink placeholder:text-muted shadow-sm focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20">
                        <button type="submit" :disabled="openForm.processing"
                                class="op-press min-h-11 w-full shrink-0 cursor-pointer rounded-full border border-muted/50 px-5 text-[15px] font-semibold text-ink transition hover:bg-sky/30 disabled:opacity-60 sm:w-auto">
                            Open
                        </button>
                    </form>
                    <span v-if="errors.openName" role="alert" class="mt-2 block text-[13px] text-danger">{{ errors.openName[0] }}</span>
                    <p class="mt-2 text-[12px] text-muted">
                        Lost the owner link?
                        <button type="button" @click="recover" class="cursor-pointer font-semibold text-neon hover:underline">Recover it with this name</button>
                    </p>
                </div>
            </template>
        </div>

        <!-- Remove-from-list confirm. Matches the report/QR modals: backdrop
             click + Escape close, danger-styled confirm. -->
        <div v-if="forgetTarget" v-modal-focus class="fixed inset-0 z-50 flex items-end justify-center sm:items-center"
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
