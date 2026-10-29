<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3'
import AppLayout from '@/components/AppLayout.vue'
import { openSuggest } from '@/components/suggest'
import { vModalFocus } from '@/components/modalFocus'

const props = defineProps({
    workspace: Object,
    courses: Array,
    totalCourses: Number,
    totalFiles: Number,
    isOwner: Boolean,
    operatorNote: Object,
    uploadsOwnerOnly: Boolean,
    recoveryAvailable: Boolean,
    needsRecoveryEmail: Boolean,
    currentRecoveryEmail: String,
    storageUsed: Number,
    storageCap: Number,
    storagePct: Number,
    search: String,
    sort: String,
})

const page = usePage()
const flash = computed(() => page.props.flash)
const errors = computed(() => page.props.errors)

// Search + sort — debounced via router visit
const localSearch = ref(props.search)
const localSort = ref(props.sort)

let searchTimer = null
watch(localSearch, (val) => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
        router.visit(window.location.pathname, {
            data: { search: val, sort: localSort.value },
            preserveState: true,
            replace: true,
        })
    }, 250)
})
watch(localSort, (val) => {
    router.visit(window.location.pathname, {
        data: { search: localSearch.value, sort: val },
        preserveState: true,
        replace: true,
    })
})

// Workspace share URL (never the ?owner= one)
const workspaceUrl = computed(() => window.location.origin + '/' + props.workspace.slug)
const shareCopied = ref(false)
const shareText = computed(() =>
    `${props.workspace.name} — notes, slides and past papers for the class. No account needed:`)
function share() {
    const touch = window.matchMedia?.('(pointer: coarse)').matches
    if (touch && navigator.share) {
        navigator.share({ title: props.workspace.name, text: shareText.value, url: workspaceUrl.value })
            .catch(() => { })
        return
    }
    window.copyText(workspaceUrl.value).then(() => {
        shareCopied.value = true
        setTimeout(() => { shareCopied.value = false }, 2000)
    }).catch(() => { })
}

const FEEDBACK_KEY = 'slipnote_feedback_dismissed'
const feedbackDismissed = ref(false)
try { feedbackDismissed.value = window.localStorage.getItem(FEEDBACK_KEY) === '1' } catch { }
const showFeedback = computed(() => props.isOwner && props.totalFiles >= 5 && !feedbackDismissed.value)
const NOTE_KEY = 'slipnote_note_seen_' + props.workspace.slug
const noteSeen = ref('')
try { noteSeen.value = window.localStorage.getItem(NOTE_KEY) ?? '' } catch { }
const showNote = computed(() => !!props.operatorNote && noteSeen.value !== props.operatorNote.at)
function dismissNote() {
    noteSeen.value = props.operatorNote.at
    try { window.localStorage.setItem(NOTE_KEY, props.operatorNote.at) } catch { }
}
function replyToNote() {
    openSuggest(`Reply about "${props.workspace.name}": `)
}

const RECOVERY_LATER_KEY = 'slipnote_recovery_later_' + props.workspace.slug
const RECOVERY_LATER_MS = 30 * 24 * 60 * 60 * 1000
const recoveryLater = ref(0)
try { recoveryLater.value = Number(window.localStorage.getItem(RECOVERY_LATER_KEY)) || 0 } catch { }
const recoveryAskSaved = ref(false)
const showRecoveryAsk = computed(() => recoveryAskSaved.value || (
    props.isOwner && props.recoveryAvailable && props.needsRecoveryEmail
    && props.totalFiles > 0 && Date.now() - recoveryLater.value > RECOVERY_LATER_MS))
function recoveryNotNow() {
    recoveryLater.value = Date.now()
    try { window.localStorage.setItem(RECOVERY_LATER_KEY, String(recoveryLater.value)) } catch { }
}
function saveRecoveryFromAsk() {
    recoveryForm.post('/' + props.workspace.slug + '/recovery-email', {
        preserveScroll: true,
        onSuccess: () => { recoveryForm.reset(); recoveryAskSaved.value = true },
    })
}

// Who can add files. Saved on tap: two choices, nothing to confirm.
const uploadAccessSaving = ref(false)
function setUploadAccess(ownerOnly) {
    if (uploadAccessSaving.value || ownerOnly === props.uploadsOwnerOnly) return
    uploadAccessSaving.value = true
    router.post('/' + props.workspace.slug + '/upload-access', { ownerOnly }, {
        preserveScroll: true,
        onFinish: () => { uploadAccessSaving.value = false },
    })
}

function tellUs() {
    openSuggest(`We use SlipNote for "${props.workspace.name}". `)
}
function dismissFeedback() {
    feedbackDismissed.value = true
    try { window.localStorage.setItem(FEEDBACK_KEY, '1') } catch { }
}

const qrDataUrl = ref('')
const qrOpen = ref(false)
const qrError = ref(false)
async function toggleQr() {
    if (qrOpen.value) { qrOpen.value = false; return }
    qrOpen.value = true
    if (qrDataUrl.value) return
    qrError.value = false
    try {
        const QR = (await import('qrcode')).default
        qrDataUrl.value = await QR.toDataURL(workspaceUrl.value, { width: 512, margin: 2 })
    } catch {
        qrError.value = true
    }
}
function downloadQr() {
    const a = document.createElement('a')
    a.href = qrDataUrl.value
    a.download = props.workspace.slug + '-qr.png'
    a.click()
}

const sheet = ref(false)
const editing = ref(null)
const codeField = ref(null)
watch(sheet, (v) => {
    if (v) {
        setTimeout(() => codeField.value?.focus(), 50)
    }
})

const courseForm = useForm({ code: '', title: '' })

const codeLooksOff = computed(() => {
    const c = courseForm.code.trim()
    return c !== '' && !/[a-zA-Z]/.test(c)
})

const codeDuplicate = computed(() => {
    const c = courseForm.code.trim().toLowerCase()
    if (c === '') return false
    return props.courses.some(course =>
        course.code.trim().toLowerCase() === c
        && (!editing.value || course.id !== editing.value.id))
})

function openCreate() {
    editing.value = null
    courseForm.reset()
    courseForm.clearErrors()
    sheet.value = true
}

onMounted(() => {
    if (props.isOwner && props.totalCourses === 0) openCreate()
})

function openEdit(course) {
    editing.value = course
    courseForm.code = course.code
    courseForm.title = course.title
    courseForm.clearErrors()
    sheet.value = true
}

function submitCourse() {
    if (editing.value) {
        courseForm.put('/' + props.workspace.slug + '/c/' + editing.value.slug, {
            onSuccess: () => { sheet.value = false },
        })
    } else {
        courseForm.post('/' + props.workspace.slug + '/courses', {
            onSuccess: () => { sheet.value = false },
        })
    }
}

const settingsOpen = ref(
    !!page.props.flash?.recoverySaved
    || !!page.props.flash?.nameSaved
    || !!page.props.errors?.recoveryEmail
    || !!page.props.errors?.boardName,
)
const settingsLift = ref(null)
function measureSettingsLift() {
    const vv = window.visualViewport
    if (!vv || !window.matchMedia('(max-width: 639.98px)').matches) { settingsLift.value = null; return }
    const keyboard = Math.max(0, window.innerHeight - vv.height - vv.offsetTop)
    settingsLift.value = { bottom: keyboard + 'px', maxHeight: Math.round(vv.height * 0.9) + 'px' }
}
watch(settingsOpen, (open) => {
    const vv = window.visualViewport
    if (!vv) return
    if (open) {
        measureSettingsLift()
        vv.addEventListener('resize', measureSettingsLift)
        vv.addEventListener('scroll', measureSettingsLift)
    } else {
        vv.removeEventListener('resize', measureSettingsLift)
        vv.removeEventListener('scroll', measureSettingsLift)
        settingsLift.value = null
    }
}, { immediate: true })
onBeforeUnmount(() => {
    window.visualViewport?.removeEventListener('resize', measureSettingsLift)
    window.visualViewport?.removeEventListener('scroll', measureSettingsLift)
})

const renameForm = useForm({ boardName: props.workspace.name })
watch(() => props.workspace.name, (name) => { renameForm.boardName = name; renameForm.defaults({ boardName: name }) })
function renameBoard() {
    renameForm.post('/' + props.workspace.slug + '/name', { preserveScroll: true })
}

const recoveryForm = useForm({ recoveryEmail: '' })
function saveRecoveryEmail() {
    recoveryForm.post('/' + props.workspace.slug + '/recovery-email', {
        preserveScroll: true,
        onSuccess: () => { recoveryForm.reset() },
    })
}

const unlockOpen = ref(!!(errors.value?.ownerInput))
const unlockForm = useForm({ ownerInput: '' })
const ownerField = ref(null)
watch(unlockOpen, (v) => {
    if (v) setTimeout(() => ownerField.value?.focus(), 50)
})

function onEscape() {
    if (confirmingDelete.value) { confirmingDelete.value = false; return }
    if (confirmingReset.value) { confirmingReset.value = false; return }
    if (confirmingExit.value) { confirmingExit.value = false; return }
    if (qrOpen.value) { qrOpen.value = false; return }
    if (sheet.value) { sheet.value = false; return }
    if (unlockOpen.value) { unlockOpen.value = false; return }
    if (settingsOpen.value) { settingsOpen.value = false }
}
function unlockOwner() {
    unlockForm.post('/' + props.workspace.slug + '/unlock', {
        preserveScroll: true,
        onSuccess: () => { unlockForm.reset() },
    })
}

const confirmingExit = ref(false)

const confirmingReset = ref(false)
const resetting = ref(false)
function resetOwnerLink() {
    resetting.value = true
    router.post('/' + props.workspace.slug + '/owner-link/reset', {}, {
        onFinish: () => { resetting.value = false },
    })
}

const confirmingDelete = ref(false)
const deleteForm = useForm({ confirmName: '' })
const deleteNameMatches = computed(() =>
    deleteForm.confirmName.trim().toLowerCase() === props.workspace.name.trim().toLowerCase())
watch(confirmingDelete, (open) => { if (!open) { deleteForm.reset(); deleteForm.clearErrors() } })
function deleteBoard() {
    if (!deleteNameMatches.value) return
    deleteForm.delete('/' + props.workspace.slug)
}

function lockBoard() {
    confirmingExit.value = false
    router.post('/' + props.workspace.slug + '/lock', {}, { preserveScroll: true })
}

// Storage display
const mbUsed = computed(() => {
    const v = props.storageUsed
    return (v / 1048576).toFixed(v >= 10485760 ? 0 : 1)
})
const mbCap = computed(() => Math.round(props.storageCap / 1048576))
const storageTone = computed(() => {
    if (props.storagePct >= 75) return 'text-neon'
    return 'text-muted'
})

// Date helper
function timeAgo(dateStr) {
    if (!dateStr) return null
    const d = new Date(dateStr)
    const secs = Math.floor((Date.now() - d) / 1000)
    if (secs < 60) return 'just now'
    const mins = Math.floor(secs / 60)
    if (mins < 60) return `${mins}m ago`
    const hrs = Math.floor(mins / 60)
    if (hrs < 24) return `${hrs}h ago`
    const days = Math.floor(hrs / 24)
    if (days < 30) return `${days}d ago`
    const months = Math.floor(days / 30)
    if (months < 12) return `${months}mo ago`
    return `${Math.floor(months / 12)}y ago`
}

function plural(n, word) {
    return n === 1 ? `${n} ${word}` : `${n} ${word}s`
}

function courseUrl(slug) {
    return '/' + props.workspace.slug + '/c/' + slug
}

function recoveryUrl() {
    return '/' + props.workspace.slug + '/recover'
}

const canReorder = computed(() => props.isOwner && !localSearch.value && localSort.value === 'manual' && props.courses.length > 1)
const reordering = ref(false)
const draggable = computed(() => canReorder.value && reordering.value)
const dragList = ref([...props.courses])
watch(() => props.courses, (v) => { dragList.value = [...v] })

let dragSrcId = null

function onDragStart(e, id) {
    dragSrcId = id
    e.dataTransfer.effectAllowed = 'move'
}

function onDragOver(e, id) {
    if (dragSrcId === id) return
    e.preventDefault()
    e.dataTransfer.dropEffect = 'move'
    const srcIdx = dragList.value.findIndex(c => c.id === dragSrcId)
    const dstIdx = dragList.value.findIndex(c => c.id === id)
    if (srcIdx === -1 || dstIdx === -1) return
    const reordered = [...dragList.value]
    reordered.splice(dstIdx, 0, reordered.splice(srcIdx, 1)[0])
    dragList.value = reordered
}

function onDrop() {
    persistOrder()
}

function moveCourse(index, dir) {
    const target = index + dir
    if (target < 0 || target >= dragList.value.length) return
    const reordered = [...dragList.value]
    const [moved] = reordered.splice(index, 1)
    reordered.splice(target, 0, moved)
    dragList.value = reordered
    persistOrder()
}

function persistOrder() {
    router.post('/' + props.workspace.slug + '/courses/reorder', {
        ids: dragList.value.map(c => c.id),
    }, {
        preserveScroll: true,
        onError: () => { dragList.value = [...props.courses] },
    })
}
</script>

<template>

    <Head :title="workspace.name + ' · Courses'" />
    <AppLayout :fab-space="isOwner && totalCourses > 0">
        <div class="op mx-auto w-full max-w-3xl flex-1 px-5 pb-10" @keydown.escape.window="onEscape">

            <div class="flex min-h-11 items-center justify-between gap-3 pt-4 sm:pt-6">
                <a v-if="isOwner" href="/start"
                    class="op-kicker group inline-flex h-11 items-center gap-1.5 text-[11px] font-semibold uppercase text-muted transition hover:text-neon">
                    <svg aria-hidden="true"
                        class="size-3.5 shrink-0 transition-transform duration-200 group-hover:-translate-x-0.5"
                        viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 12H5" />
                        <path d="M12 19l-7-7 7-7" />
                    </svg>
                    All boards
                </a>
                <a v-else href="/"
                    class="inline-flex h-11 items-center gap-1.5 text-[13px] font-bold text-muted transition hover:text-ink">
                    <img src="/logo-mark.png" alt="" class="size-4">
                    SlipNote
                </a>
                <button v-if="isOwner" type="button" @click="settingsOpen = true"
                    aria-haspopup="dialog" aria-label="Owner: board settings"
                    class="op-pill op-press inline-flex h-11 cursor-pointer items-center gap-1.5 rounded-full px-3 text-[12px] font-semibold text-ink transition sm:h-9">
                    Owner
                    <svg aria-hidden="true" class="size-3.5 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/></svg>
                </button>
            </div>

            <header class="mb-2.5 flex items-center justify-between gap-4 pt-1 sm:sticky sm:top-0 sm:z-10 sm:bg-base sm:py-3">
                <h1 class="op-title line-clamp-2 min-w-0 wrap-break-word text-[1.6rem] font-bold text-ink sm:line-clamp-1 sm:text-[1.85rem]">{{ workspace.name }}</h1>
                <button v-if="isOwner && totalCourses > 0" type="button" @click="openCreate"
                    class="op-press hidden h-9 shrink-0 cursor-pointer items-center gap-1 rounded-full bg-neon px-4 text-[13px] font-semibold text-white sm:inline-flex">
                    <span class="text-base leading-none">+</span> New course
                </button>
            </header>

            <aside v-if="showNote" aria-label="A note from the SlipNote team"
                class="op-card op-card-quiet mb-4 flex items-start gap-3 px-4 py-3.5">
                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-muted">From the SlipNote team</p>
                    <p class="mt-1 whitespace-pre-line text-[14px] text-ink">{{ operatorNote.text }}</p>
                    <button type="button" @click="replyToNote"
                        class="op-press mt-2.5 inline-flex h-11 cursor-pointer items-center rounded-full bg-neon px-4 text-[13px] font-semibold text-white sm:h-8">
                        Reply
                    </button>
                </div>
                <button type="button" @click="dismissNote" aria-label="Dismiss note"
                    class="-mr-1.5 -mt-1 flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-full text-muted transition hover:bg-sky/40 hover:text-ink sm:size-8">
                    <svg aria-hidden="true" class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 5l10 10M15 5L5 15"/></svg>
                </button>
            </aside>

            <aside v-if="showRecoveryAsk" aria-labelledby="recoveryAskTitle"
                class="op-card op-card-quiet mb-4 px-4 py-3.5">
                <p v-if="recoveryAskSaved" role="status"
                    class="teal-accent flex items-center gap-1.5 text-[14px] font-semibold text-teal">
                    <svg aria-hidden="true" class="size-4 shrink-0" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10.5l4 4 8-9"/></svg>
                    Saved. Lose your owner link and we'll email you a new one.
                </p>
                <template v-else>
                    <div class="flex items-start gap-3">
                        <div class="min-w-0 flex-1">
                            <h2 id="recoveryAskTitle" class="text-[14px] font-semibold text-ink">Don't lose this board</h2>
                            <p class="mt-1 text-[13px] text-muted">
                                It has {{ totalFiles }} {{ totalFiles === 1 ? 'file' : 'files' }} now. Add an email and we'll send you a new owner link if you lose this one. It's used for nothing else.
                            </p>
                        </div>
                        <button type="button" @click="recoveryNotNow" aria-label="Not now" title="Not now"
                            class="-mr-1.5 -mt-1 flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-full text-muted transition hover:bg-sky/40 hover:text-ink sm:size-8">
                            <svg aria-hidden="true" class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 5l10 10M15 5L5 15"/></svg>
                        </button>
                    </div>
                    <form @submit.prevent="saveRecoveryFromAsk" class="mt-3 flex gap-2">
                        <input type="email" required v-model="recoveryForm.recoveryEmail" aria-label="Recovery email"
                            autocomplete="email" placeholder="you@example.com"
                            :aria-invalid="errors.recoveryEmail ? 'true' : undefined"
                            :aria-describedby="errors.recoveryEmail ? 'recoveryAskError' : undefined"
                            class="min-h-11 w-full min-w-0 flex-1 rounded-xl border border-sky bg-base px-3.5 text-[15px] text-ink shadow-inner placeholder:text-muted sm:min-h-9">
                        <button type="submit" :disabled="recoveryForm.processing"
                            class="op-press min-h-11 shrink-0 cursor-pointer rounded-full bg-neon px-5 text-[13px] font-semibold text-white disabled:opacity-60 sm:min-h-9">
                            Save
                        </button>
                    </form>
                    <span v-if="errors.recoveryEmail" id="recoveryAskError" role="alert" class="mt-2 block text-[12px] text-danger">{{ errors.recoveryEmail[0] }}</span>
                </template>
            </aside>

            <p class="mb-5 text-[13px] text-muted">
                <template v-if="isOwner && totalCourses > 0 && totalFiles === 0">Open a course to add the first file — a board that opens empty rarely gets opened twice.</template>
                <template v-else-if="totalCourses > 0 && uploadsOwnerOnly && !isOwner">Notes, slides and past papers for this class. Open a course to read or download them.</template>
                <template v-else-if="totalCourses > 0">Notes, slides and past papers from your classmates. Open a course to read or add files.</template>
                <template v-else>Your board's courses live here — add one to get started.</template>
            </p>

            <div v-if="totalCourses > 0" class="mb-7 flex items-center gap-2">
                <button type="button" @click="share"
                    class="op-pill op-press inline-flex h-11 cursor-pointer items-center justify-center gap-1.5 whitespace-nowrap rounded-full border border-sky/50 bg-surface px-4 text-[13px] font-semibold text-ink transition sm:h-9 sm:flex-none">
                    <svg aria-hidden="true" class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor"
                        stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10 3v10" />
                        <path d="M6.5 6.5 10 3l3.5 3.5" />
                        <path d="M5 10v5.5A1.5 1.5 0 0 0 6.5 17h7a1.5 1.5 0 0 0 1.5-1.5V10" />
                    </svg>
                    <span v-if="!shareCopied">Share board</span>
                    <span v-else>Link copied ✓</span>
                </button>
                <button type="button" @click="toggleQr"
                    :aria-expanded="qrOpen" aria-label="Show QR code for this board" title="QR code"
                    class="op-pill op-press inline-flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-full border border-sky/50 bg-surface text-ink transition sm:size-9">
                    <svg aria-hidden="true" class="size-4" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M3 3h5v5H3V3zm1.5 1.5v2h2v-2h-2zM12 3h5v5h-5V3zm1.5 1.5v2h2v-2h-2zM3 12h5v5H3v-5zm1.5 1.5v2h2v-2h-2zM12 12h2v2h-2v-2zm3 0h2v2h-2v-2zm-3 3h2v2h-2v-2zm3 0h2v2h-2v-2z" />
                    </svg>
                </button>
            </div>
            <div v-else class="mb-7"></div>

            <div v-if="flash.created" class="op-toast mb-5 text-sm font-medium text-ink">
                <p>{{ flash.created }}</p>
            </div>

            <template v-if="totalCourses === 0">
                <div class="op-card mt-8 px-6 py-16 text-center sm:mt-12">
                    <p class="text-[16px] font-semibold tracking-tight text-ink">No courses yet</p>
                    <template v-if="isOwner">
                        <p class="mx-auto mt-1.5 max-w-sm text-[14px] leading-relaxed text-muted">
                            Each course is one subject — like <span class="font-semibold text-ink">PHYS 101</span> or
                            <span class="font-semibold text-ink">CS 250</span>. Its notes, slides and past papers
                            all live in one place.
                        </p>
                        <button type="button" @click="openCreate"
                            class="op-press mt-5 inline-flex min-h-11 cursor-pointer items-center gap-1 rounded-full bg-neon px-5 text-[14px] font-semibold text-white">
                            <span class="text-lg leading-none">+</span> New course
                        </button>
                    </template>
                    <template v-else>
                        <p class="mx-auto mt-1.5 max-w-sm text-[14px] leading-relaxed text-muted">
                            Courses are added by whoever set this board up. If that's you,
                            open it with your <span class="font-semibold text-ink">owner link</span>
                            (the one shown when you created it). Otherwise, check back soon.
                        </p>
                    </template>
                </div>
            </template>

            <template v-else>
                <div v-if="storagePct >= 90" role="status"
                    class="mb-6 rounded-2xl border border-danger/30 bg-danger/10 px-4 py-3 text-[13px] text-ink">
                    <p class="font-semibold">This board is almost full</p>
                    <p class="mt-0.5 text-muted">
                        {{ mbUsed }} of {{ mbCap }} MB used.
                        <template v-if="isOwner">Delete old files inside a course to make room for new ones.</template>
                        <template v-else>New uploads may not fit. The board's owner can free up space.</template>
                    </p>
                </div>

                <div class="mb-2 flex min-h-11 items-center justify-between gap-3 sm:min-h-8">
                    <h2 class="text-[14px] font-semibold text-ink">
                        Courses <span class="font-normal tabular-nums text-muted">· {{ totalCourses }}</span>
                    </h2>
                    <button v-if="canReorder" type="button" @click="reordering = !reordering"
                        :aria-pressed="reordering"
                        class="inline-flex min-h-11 cursor-pointer items-center rounded-full px-3 text-[12px] font-semibold transition sm:min-h-8"
                        :class="reordering ? 'bg-neon text-white' : 'text-muted hover:bg-sky/40 hover:text-ink'">
                        {{ reordering ? 'Done' : 'Reorder' }}
                    </button>
                </div>
                <div v-if="totalCourses > 6" class="mb-4 flex flex-col gap-2 sm:flex-row">
                    <input type="search" v-model="localSearch" :placeholder="`Search ${totalCourses} courses…`"
                        aria-label="Search courses"
                        class="op-pill box-border h-11 w-full min-w-0 appearance-none rounded-xl border border-sky bg-surface px-3.5 text-[14px] font-medium leading-none text-ink shadow-sm placeholder:font-normal placeholder:text-muted sm:flex-1">
                    <div class="relative">
                        <select v-model="localSort" aria-label="Sort courses"
                            class="op-pill box-border h-11 w-full appearance-none rounded-xl border border-sky bg-surface pl-3.5 pr-10 text-[14px] font-medium leading-none text-ink shadow-sm sm:w-auto">
                            <option value="manual">Custom order</option>
                            <option value="active">Most recently active</option>
                            <option value="az">A–Z</option>
                        </select>
                        <svg class="pointer-events-none absolute right-3.5 top-1/2 size-4 -translate-y-1/2 text-muted"
                            viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M6 8l4 4 4-4" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </div>
                </div>

                <p v-if="courses.length === 0" class="op-card px-6 py-8 text-center text-[14px] text-muted">
                    No courses match "<span class="font-semibold text-ink">{{ localSearch }}</span>".
                </p>

                <div v-else class="op-card overflow-hidden">
                    <div v-for="(course, idx) in ((draggable && courses.length > 1) ? dragList : courses)"
                        :key="course.id" :draggable="draggable && courses.length > 1"
                        @dragstart="draggable && courses.length > 1 && onDragStart($event, course.id)"
                        @dragover="draggable && courses.length > 1 && onDragOver($event, course.id)"
                        @drop="draggable && courses.length > 1 && onDrop()"
                        class="op-row group flex items-start gap-3 sm:gap-4">

                        <div v-if="draggable && courses.length > 1" class="-my-1 flex shrink-0 flex-col items-center">
                            <button type="button" @click.stop.prevent="moveCourse(idx, -1)" :disabled="idx === 0"
                                :aria-label="`Move ${course.code} up`"
                                class="flex h-11 w-9 items-center justify-center rounded-full text-muted transition enabled:cursor-pointer enabled:hover:bg-sky/40 enabled:hover:text-neon disabled:opacity-30 sm:size-6">
                                <svg class="size-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 10l4-4 4 4" />
                                </svg>
                            </button>
                            <svg aria-hidden="true" title="Drag to reorder"
                                class="my-0.5 hidden size-3.5 cursor-grab text-muted/40 active:cursor-grabbing sm:block"
                                viewBox="0 0 16 16" fill="currentColor">
                                <rect x="4" y="3" width="2" height="2" rx="1" />
                                <rect x="10" y="3" width="2" height="2" rx="1" />
                                <rect x="4" y="7" width="2" height="2" rx="1" />
                                <rect x="10" y="7" width="2" height="2" rx="1" />
                            </svg>
                            <button type="button" @click.stop.prevent="moveCourse(idx, 1)"
                                :disabled="idx === dragList.length - 1" :aria-label="`Move ${course.code} down`"
                                class="flex h-11 w-9 items-center justify-center rounded-full text-muted transition enabled:cursor-pointer enabled:hover:bg-sky/40 enabled:hover:text-neon disabled:opacity-30 sm:size-6">
                                <svg class="size-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 6l4 4 4-4" />
                                </svg>
                            </button>
                        </div>

                        <Link :href="courseUrl(course.slug)" class="min-w-0 flex-1" @click.stop>
                            <div class="flex items-center gap-2">
                                <p class="min-w-0 truncate text-[15px] font-bold tracking-tight text-ink">{{ course.code }}</p>
                                <span v-if="course.new_count > 0"
                                    class="shrink-0 rounded-full bg-neon/10 px-2 py-0.5 text-[11px] font-semibold text-neon"
                                    :title="`${course.new_count} added since you last opened this course`">
                                    {{ course.new_count }} new
                                </span>
                            </div>
                            <p class="mt-0.5 text-[13px] text-muted">{{ course.title }}</p>
                            <span v-if="isOwner && course.materials_count === 0"
                                @click.stop.prevent="router.visit(courseUrl(course.slug) + '#add-file')"
                                class="mt-2 inline-flex h-11 items-center gap-1.5 rounded-full bg-neon/15 pl-3 pr-3.5 text-[13px] font-semibold text-neon sm:h-8">
                                <svg aria-hidden="true" class="size-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M10 4v12M4 10h12" /></svg>
                                Add the first file
                            </span>
                            <p v-if="course.materials_count > 0" class="mt-1 text-[12px] tabular-nums text-muted">
                                {{ plural(course.materials_count, 'file') }}<template v-if="course.materials_max_created_at"> · updated {{ timeAgo(course.materials_max_created_at) }}</template>
                            </p>
                            <p v-else-if="!isOwner" class="mt-1 text-[12px] text-muted">No files yet</p>
                        </Link>

                        <svg v-if="!isOwner" aria-hidden="true"
                            class="mt-1 size-4 shrink-0 text-muted/50 transition group-hover:translate-x-0.5 group-hover:text-neon"
                            viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path d="M8 6l4 4-4 4" />
                        </svg>

                        <button v-if="isOwner" type="button" @click.stop.prevent="openEdit(course)"
                            :aria-label="`Edit ${course.code}`"
                            class="mt-0.5 flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-full text-muted transition hover:bg-sky/40 hover:text-neon focus:opacity-100 sm:size-auto sm:p-1.5 sm:opacity-0 sm:group-hover:opacity-100">
                            <svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor"
                                stroke-width="1.7">
                                <path d="M13.5 3.5l3 3L7 16l-4 1 1-4 9.5-9.5z" stroke-linecap="round"
                                    stroke-linejoin="round" />
                            </svg>
                        </button>
                    </div>
                </div>

                <p v-if="draggable" class="mt-3 text-[13px] text-muted">
                    Use the arrows (or drag) to reorder — saved automatically.
                </p>

                <div v-if="!isOwner" class="mt-6 flex flex-wrap gap-x-5 text-[12px] text-muted">
                    <p>
                        Run a class of your own?
                        <a href="/start" class="inline-flex min-h-11 items-center font-semibold text-neon hover:underline">Make a board</a>
                    </p>
                    <p>
                        Lecturer?
                        <a href="/lecturers" class="inline-flex min-h-11 items-center font-semibold text-neon hover:underline">How we handle your material</a>
                    </p>
                </div>

                <aside v-if="showFeedback" aria-label="Feedback"
                    class="op-card op-card-quiet mt-6 flex items-start gap-3 px-4 py-3.5">
                    <div class="min-w-0 flex-1">
                        <p class="text-[14px] font-semibold tracking-tight text-ink">Using SlipNote a lot? Tell us how 👋</p>
                        <p class="mt-0.5 text-[13px] text-muted">One line helps: what your class uses it for, and what's missing.</p>
                        <button type="button" @click="tellUs"
                            class="op-pill op-press mt-2.5 inline-flex h-11 cursor-pointer items-center rounded-full px-4 text-[13px] font-semibold text-ink transition sm:h-9">
                            Tell us
                        </button>
                    </div>
                    <button type="button" @click="dismissFeedback" aria-label="Dismiss feedback prompt"
                        class="-mr-1.5 -mt-1 flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-full text-muted transition hover:bg-sky/40 hover:text-ink sm:size-8">
                        <svg aria-hidden="true" class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 5l10 10M15 5L5 15"/></svg>
                    </button>
                </aside>
            </template>

            <button v-if="isOwner && totalCourses > 0 && !sheet" type="button" @click="openCreate"
                class="fixed bottom-5 right-5 z-20 inline-flex h-12 cursor-pointer items-center gap-1.5 rounded-full bg-neon pl-4 pr-5 text-[15px] font-semibold text-white shadow-lg transition hover:brightness-125 sm:hidden">
                <svg aria-hidden="true" class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M10 4v12M4 10h12" /></svg>
                New course
            </button>

            <template v-if="isOwner">
                <div v-if="sheet" v-modal-focus class="fixed inset-0 z-40" role="dialog" aria-modal="true"
                    :aria-label="editing ? 'Edit course' : 'New course'">
                    <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" @click="sheet = false"></div>
                    <div class="absolute right-0 top-0 flex h-full w-full max-w-md flex-col bg-surface px-5 py-6 shadow-xl
                                transition-transform duration-200 sm:px-6" style="transform: translateX(0)">
                        <div class="mb-1.5 flex items-start justify-between">
                            <h2 class="text-xs font-bold uppercase tracking-[0.06em] text-muted">{{ editing ? 'Edit course' : 'New course' }}</h2>
                            <button type="button" @click="sheet = false" aria-label="Close"
                                class="op-press -mr-1.5 -mt-1.5 flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-full text-muted transition hover:bg-danger/10 hover:text-danger sm:size-9">
                                <svg aria-hidden="true" class="size-5" viewBox="0 0 20 20" fill="none"
                                    stroke="currentColor" stroke-width="1.75" stroke-linecap="round">
                                    <path d="M5 5l10 10M15 5L5 15" />
                                </svg>
                            </button>
                        </div>
                        <p v-if="!editing" class="mb-5 text-[13px] leading-relaxed text-muted">
                            Add a course — like PHYS 101 or CS 250. You'll go straight to it to upload files.
                        </p>
                        <div v-else class="mb-5"></div>
                        <form @submit.prevent="submitCourse" class="flex flex-col gap-3.5">
                            <div>
                                <label for="code" class="mb-1 block text-[13px] font-semibold text-ink">
                                    Course code <span class="text-danger" aria-hidden="true">*</span>
                                </label>
                                <p class="mb-1.5 text-[12px] text-muted">The short code your class uses.</p>
                                <input id="code" type="text" v-model="courseForm.code" placeholder="e.g. PHYS 101"
                                    ref="codeField" required :aria-invalid="!!courseForm.errors.code"
                                    class="w-full rounded-xl border border-sky bg-base px-3.5 py-2.5 text-[15px] text-ink shadow-inner placeholder:text-muted">
                                <span v-if="courseForm.errors.code" role="alert"
                                    class="mt-1.5 block text-[13px] text-danger">{{ courseForm.errors.code }}</span>
                                <span v-else-if="codeDuplicate" class="mt-1.5 block text-[13px] text-amber-600">This
                                    board already has a course with this code.</span>
                                <span v-else-if="codeLooksOff" class="mt-1.5 block text-[13px] text-amber-600">A course
                                    code usually includes letters, like “PHYS 101”.</span>
                            </div>
                            <div>
                                <label for="ctitle" class="mb-1 block text-[13px] font-semibold text-ink">
                                    Title <span class="text-danger" aria-hidden="true">*</span>
                                </label>
                                <p class="mb-1.5 text-[12px] text-muted">The full course name.</p>
                                <input id="ctitle" type="text" v-model="courseForm.title"
                                    placeholder="e.g. Introductory Physics" required
                                    :aria-invalid="!!courseForm.errors.title"
                                    class="w-full rounded-xl border border-sky bg-base px-3.5 py-2.5 text-[15px] text-ink shadow-inner placeholder:text-muted">
                                <span v-if="courseForm.errors.title" role="alert"
                                    class="mt-1.5 block text-[13px] text-danger">{{ courseForm.errors.title }}</span>
                            </div>
                            <button type="submit" :disabled="courseForm.processing"
                                class="op-press mt-1 min-h-11 cursor-pointer rounded-full bg-neon py-3 text-[15px] font-semibold text-white disabled:opacity-60">
                                {{ editing ? 'Save changes' : 'Create course' }}
                            </button>
                        </form>
                    </div>
                </div>

                <div v-if="settingsOpen" v-modal-focus class="fixed inset-0 z-40" role="dialog" aria-modal="true" aria-label="Board settings">
                    <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" @click="settingsOpen = false"></div>
                    <div :style="settingsLift"
                        class="absolute inset-x-0 bottom-0 flex max-h-[90vh] flex-col overflow-y-auto overscroll-contain rounded-t-2xl bg-surface px-5 pb-[max(1.5rem,env(safe-area-inset-bottom))] pt-3 shadow-xl
                               sm:inset-x-auto sm:right-0 sm:top-0 sm:h-full sm:max-h-none sm:w-full sm:max-w-md sm:rounded-none sm:px-6 sm:py-6">
                        <div aria-hidden="true" class="mx-auto mb-2 h-1 w-10 shrink-0 rounded-full bg-muted/30 sm:hidden"></div>
                        <div class="-mt-1.5 mb-3.5 flex items-center justify-between">
                            <h2 class="text-xs font-bold uppercase tracking-[0.06em] text-muted">Board settings</h2>
                            <button type="button" @click="settingsOpen = false" aria-label="Close"
                                class="op-press -mr-1.5 flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-full text-muted transition hover:bg-danger/10 hover:text-danger sm:size-9">
                                <svg aria-hidden="true" class="size-5" viewBox="0 0 20 20" fill="none"
                                    stroke="currentColor" stroke-width="1.75" stroke-linecap="round">
                                    <path d="M5 5l10 10M15 5L5 15" />
                                </svg>
                            </button>
                        </div>

                        <section>
                            <form @submit.prevent="renameBoard">
                                <label for="boardName" class="text-[14px] font-semibold text-ink">Board name</label>
                                <p class="mt-0.5 text-[12px] text-muted">The link stays the same, so links you've shared keep working.</p>
                                <div class="mt-2 flex gap-2">
                                    <input id="boardName" type="text" v-model="renameForm.boardName" maxlength="60" required
                                        :aria-invalid="!!errors.boardName" :aria-describedby="errors.boardName ? 'boardNameError' : undefined"
                                        class="min-w-0 flex-1 rounded-xl border border-sky bg-base px-3.5 py-2.5 text-[15px] text-ink shadow-inner">
                                    <button type="submit" :disabled="renameForm.processing || !renameForm.isDirty || renameForm.boardName.trim().length < 2"
                                        class="op-press inline-flex min-h-11 shrink-0 cursor-pointer items-center rounded-full bg-neon px-4 text-[13px] font-semibold text-white disabled:cursor-not-allowed disabled:bg-sky disabled:text-muted">
                                        Save
                                    </button>
                                </div>
                                <span v-if="errors.boardName" id="boardNameError" role="alert" class="mt-1.5 block text-[12px] text-danger">{{ errors.boardName[0] }}</span>
                                <p v-else-if="flash.nameSaved" role="status"
                                   class="teal-accent mt-2 flex items-center gap-1.5 rounded-lg bg-teal/10 px-3 py-2 text-[13px] font-semibold text-teal">
                                    <svg aria-hidden="true" class="size-4 shrink-0" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10.5l4 4 8-9"/></svg>
                                    {{ flash.nameSaved }}
                                </p>
                            </form>
                        </section>

                        <section class="mt-7 border-t border-sky pt-6">
                            <div class="flex items-center justify-between gap-4">
                                <p id="uploadAccessLabel" class="text-[14px] font-semibold text-ink">Anyone with the link can add files</p>
                                <button type="button" role="switch" :aria-checked="!uploadsOwnerOnly" aria-labelledby="uploadAccessLabel"
                                    :aria-busy="uploadAccessSaving" @click="setUploadAccess(!uploadsOwnerOnly)"
                                    class="relative inline-flex h-11 w-14 shrink-0 cursor-pointer items-center justify-center aria-busy:opacity-60">
                                    <span aria-hidden="true" class="h-7 w-12 rounded-full transition-colors duration-200"
                                        :class="!uploadsOwnerOnly ? 'bg-neon' : 'bg-muted/70'"></span>
                                    <span aria-hidden="true" class="absolute left-2 top-1/2 size-5 -translate-y-1/2 rounded-full bg-white shadow transition-transform duration-200"
                                        :class="!uploadsOwnerOnly ? 'translate-x-5' : 'translate-x-0'"></span>
                                </button>
                            </div>
                        </section>

                        <section v-if="recoveryAvailable" class="mt-7 border-t border-sky pt-6">
                            <h3 class="text-[14px] font-semibold text-ink">
                                {{ needsRecoveryEmail ? 'Recovery email' : 'Recovery email is set' }}
                            </h3>
                            <p v-if="flash.recoverySaved" role="status"
                               class="teal-accent mt-2 flex items-center gap-1.5 rounded-lg bg-teal/10 px-3 py-2 text-[13px] font-semibold text-teal">
                                <svg aria-hidden="true" class="size-4 shrink-0" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10.5l4 4 8-9"/></svg>
                                {{ flash.recoverySaved }}
                            </p>
                            <template v-if="needsRecoveryEmail">
                                <p class="mt-1 text-[12px] text-muted">
                                    Lose your owner link and we'll email a fresh one. Only used for that, never shared.
                                </p>
                            </template>
                            <template v-else>
                                <span v-if="currentRecoveryEmail"
                                    class="mt-2 inline-flex rounded-full border border-sky bg-base px-2.5 py-0.5 text-[11px] font-medium text-muted">
                                    {{ currentRecoveryEmail }}
                                </span>
                                <p class="mt-1 text-[12px] text-muted">
                                    Lose your owner link and we'll email a fresh one. Anyone with that inbox can control this board.
                                </p>
                                <Link :href="recoveryUrl()"
                                    class="mt-2 inline-flex min-h-11 items-center gap-1 text-[12px] font-semibold text-neon transition hover:underline sm:min-h-0">
                                    Recover owner link
                                    <svg aria-hidden="true" class="size-3 shrink-0" viewBox="0 0 20 20" fill="none"
                                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M8 6l4 4-4 4" />
                                    </svg>
                                </Link>
                            </template>
                            <form @submit.prevent="saveRecoveryEmail" class="mt-3 flex flex-col gap-2">
                                <input type="email" v-model="recoveryForm.recoveryEmail" aria-label="Recovery email"
                                    :placeholder="needsRecoveryEmail ? 'you@example.com' : 'New address, or leave empty to remove'"
                                    class="w-full min-w-0 rounded-xl border border-sky bg-base px-3.5 py-2.5 text-[15px] text-ink shadow-inner placeholder:text-muted">
                                <button type="submit" :disabled="recoveryForm.processing"
                                    class="op-press min-h-11 cursor-pointer rounded-full bg-neon px-5 text-[13px] font-semibold text-white disabled:opacity-60">
                                    {{ needsRecoveryEmail ? 'Save recovery email' : 'Update' }}
                                </button>
                            </form>
                            <span v-if="errors.recoveryEmail" role="alert" class="mt-2 block text-[12px] text-danger">{{ errors.recoveryEmail[0] }}</span>
                            <p v-if="!needsRecoveryEmail" class="mt-2 text-[11px] text-muted">Saving an empty field removes the current recovery email.</p>
                        </section>

                        <section class="mt-7 border-t border-sky pt-6">
                            <h3 class="text-[14px] font-semibold text-ink">Owner link</h3>
                            <p class="mt-1 text-[12px] text-muted">
                                Shared it by mistake? Reset it. You get a new owner link, and the old one stops working on every device.
                            </p>
                            <button type="button" @click="confirmingReset = true"
                                class="op-pill op-press mt-3 inline-flex min-h-11 cursor-pointer items-center rounded-full px-4 text-[13px] font-semibold text-ink transition sm:min-h-9">
                                Reset owner link
                            </button>
                        </section>

                        <section class="mt-7 border-t border-sky pt-6">
                            <h3 class="text-[14px] font-semibold text-ink">Delete board</h3>
                            <p class="mt-1 text-[12px] text-muted">
                                Removes the board, its courses and every file, for everyone. This can't be undone.
                            </p>
                            <button type="button" @click="confirmingDelete = true"
                                class="op-press mt-3 inline-flex min-h-11 cursor-pointer items-center rounded-full border border-danger/40 px-4 text-[13px] font-semibold text-danger transition hover:bg-danger/10 sm:min-h-9">
                                Delete board
                            </button>
                        </section>

                        <section class="mt-7 border-t border-sky pt-5 sm:mt-auto">
                            <button type="button" @click="confirmingExit = true"
                                class="inline-flex min-h-11 cursor-pointer items-center gap-2 text-[13px] font-semibold text-muted transition hover:text-ink">
                                <svg aria-hidden="true" class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor"
                                    stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M8 4H5.5A1.5 1.5 0 0 0 4 5.5v9A1.5 1.5 0 0 0 5.5 16H8" />
                                    <path d="M12.5 13 16 10l-3.5-3" />
                                    <path d="M16 10H8.5" />
                                </svg>
                                Exit owner mode on this device
                            </button>
                        </section>
                    </div>
                </div>
            </template>

            <div v-if="confirmingExit" v-modal-focus class="fixed inset-0 z-50 flex items-end justify-center sm:items-center"
                 role="dialog" aria-modal="true" aria-labelledby="exitTitle" aria-describedby="exitDesc">
                <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" @click="confirmingExit = false"></div>
                <div class="relative max-h-[90vh] w-full max-w-md overflow-y-auto rounded-t-2xl bg-surface px-6 pb-[max(1.5rem,env(safe-area-inset-bottom))] pt-3 shadow-xl sm:rounded-2xl sm:pt-6">
                    <div class="mx-auto mb-3 h-1 w-10 rounded-full bg-muted/30 sm:hidden"></div>
                    <button type="button" @click="confirmingExit = false" aria-label="Close"
                            class="op-press absolute right-3 top-3 hidden size-9 cursor-pointer items-center justify-center rounded-full text-muted transition hover:bg-danger/10 hover:text-danger sm:flex">
                        <svg aria-hidden="true" class="size-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round">
                            <path d="M5 5l10 10M15 5L5 15" />
                        </svg>
                    </button>
                    <h2 id="exitTitle" class="pr-10 text-[15px] font-semibold text-ink">Exit owner mode on this device?</h2>
                    <p id="exitDesc" class="mt-2 text-[13px] leading-relaxed text-muted">
                        The board stays exactly as it is &mdash; this only signs you out here.
                        To manage it again you&rsquo;ll need your owner link, so make sure you still have it.
                    </p>
                    <div class="mt-5 flex items-center justify-end gap-2">
                        <button type="button" @click="confirmingExit = false" data-autofocus
                                class="op-press inline-flex min-h-11 cursor-pointer items-center rounded-full px-4 text-[14px] font-semibold text-muted transition hover:bg-sky/30 hover:text-ink">Stay in owner mode</button>
                        <button type="button" @click="lockBoard"
                                class="op-press inline-flex min-h-11 cursor-pointer items-center rounded-full bg-neon px-5 text-[14px] font-semibold text-white">
                            Exit
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="confirmingDelete" v-modal-focus class="fixed inset-0 z-50 flex items-end justify-center sm:items-center"
                 role="dialog" aria-modal="true" aria-labelledby="deleteTitle" aria-describedby="deleteDesc">
                <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" @click="confirmingDelete = false"></div>
                <div class="relative max-h-[90vh] w-full max-w-md overflow-y-auto rounded-t-2xl bg-surface px-6 pb-[max(1.5rem,env(safe-area-inset-bottom))] pt-3 shadow-xl sm:rounded-2xl sm:pt-6">
                    <div aria-hidden="true" class="mx-auto mb-3 h-1 w-10 rounded-full bg-muted/30 sm:hidden"></div>
                    <h2 id="deleteTitle" class="text-[15px] font-semibold text-ink">Delete "{{ workspace.name }}"?</h2>
                    <p id="deleteDesc" class="mt-2 text-[13px] leading-relaxed text-muted">
                        Every course and {{ totalFiles === 1 ? 'the 1 file' : `all ${totalFiles} files` }} on it go for good, for everyone with the link. This can't be undone.
                    </p>
                    <form @submit.prevent="deleteBoard" class="mt-4">
                        <label for="deleteConfirmName" class="mb-1.5 block text-[13px] font-semibold text-ink">
                            Type <span class="select-all font-bold">{{ workspace.name }}</span> to confirm
                        </label>
                        <input id="deleteConfirmName" v-model="deleteForm.confirmName" type="text" data-autofocus
                            autocomplete="off" autocapitalize="off" spellcheck="false"
                            :aria-invalid="errors.confirmName ? 'true' : undefined"
                            :aria-describedby="errors.confirmName ? 'deleteError' : undefined"
                            class="w-full min-w-0 rounded-xl border border-sky bg-base px-3.5 py-2.5 text-[15px] text-ink shadow-inner">
                        <span v-if="errors.confirmName" id="deleteError" role="alert" class="mt-2 block text-[12px] text-danger">{{ errors.confirmName[0] }}</span>
                        <div class="mt-5 flex items-center justify-end gap-2">
                            <button type="button" @click="confirmingDelete = false"
                                    class="op-press inline-flex min-h-11 cursor-pointer items-center rounded-full px-4 text-[14px] font-semibold text-muted transition hover:bg-sky/30 hover:text-ink">Cancel</button>
                            <button type="submit" :disabled="!deleteNameMatches || deleteForm.processing"
                                    class="op-press btn-danger inline-flex min-h-11 cursor-pointer items-center rounded-full px-5 text-[14px] font-semibold disabled:cursor-not-allowed disabled:opacity-50">
                                Delete board
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div v-if="confirmingReset" v-modal-focus class="fixed inset-0 z-50 flex items-end justify-center sm:items-center"
                 role="dialog" aria-modal="true" aria-labelledby="resetTitle" aria-describedby="resetDesc">
                <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" @click="confirmingReset = false"></div>
                <div class="relative max-h-[90vh] w-full max-w-md overflow-y-auto rounded-t-2xl bg-surface px-6 pb-[max(1.5rem,env(safe-area-inset-bottom))] pt-3 shadow-xl sm:rounded-2xl sm:pt-6">
                    <div class="mx-auto mb-3 h-1 w-10 rounded-full bg-muted/30 sm:hidden"></div>
                    <button type="button" @click="confirmingReset = false" aria-label="Close"
                            class="op-press absolute right-3 top-3 hidden size-9 cursor-pointer items-center justify-center rounded-full text-muted transition hover:bg-danger/10 hover:text-danger sm:flex">
                        <svg aria-hidden="true" class="size-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round">
                            <path d="M5 5l10 10M15 5L5 15" />
                        </svg>
                    </button>
                    <h2 id="resetTitle" class="pr-10 text-[15px] font-semibold text-ink">Reset the owner link?</h2>
                    <p id="resetDesc" class="mt-2 text-[13px] leading-relaxed text-muted">
                        The old link stops working right away, and anyone who used it loses owner access, including your other devices.
                        You'll see the new link once, so save it.
                    </p>
                    <div class="mt-5 flex items-center justify-end gap-2">
                        <button type="button" @click="confirmingReset = false" data-autofocus
                                class="op-press inline-flex min-h-11 cursor-pointer items-center rounded-full px-4 text-[14px] font-semibold text-muted transition hover:bg-sky/30 hover:text-ink">Cancel</button>
                        <button type="button" @click="resetOwnerLink" :disabled="resetting"
                                class="op-press inline-flex min-h-11 cursor-pointer items-center rounded-full bg-neon px-5 text-[14px] font-semibold text-white disabled:opacity-60">
                            Reset link
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="qrOpen" v-modal-focus class="fixed inset-0 z-40 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-label="Board QR code">
                <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" @click="qrOpen = false"></div>
                <div class="relative flex w-full max-w-xs flex-col items-center rounded-2xl bg-surface px-6 pb-6 pt-11 shadow-xl">
                    <button type="button" @click="qrOpen = false" aria-label="Close"
                        class="op-press absolute right-3 top-3 flex size-11 cursor-pointer items-center justify-center rounded-full text-muted transition hover:bg-danger/10 hover:text-danger sm:size-9">
                        <svg aria-hidden="true" class="size-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round">
                            <path d="M5 5l10 10M15 5L5 15" />
                        </svg>
                    </button>
                    <img v-if="qrDataUrl" :src="qrDataUrl" alt="QR code for this board" class="size-52 rounded-xl bg-white p-2 shadow-sm" />
                    <div v-else role="status"
                        class="flex size-52 items-center justify-center rounded-xl border border-sky/50 bg-base px-4 text-center text-[12px] text-muted">
                        {{ qrError ? 'Could not build the QR code.' : 'Building QR code…' }}
                    </div>
                    <p class="mt-4 text-[13px] font-semibold text-ink">
                        {{ qrError ? 'Share this link instead' : 'Scan to open this board' }}
                    </p>
                    <p class="mt-1 w-full break-all text-center text-[12px] text-muted">{{ workspaceUrl }}</p>
                    <button v-if="qrDataUrl" type="button" @click="downloadQr"
                        class="op-press mt-4 inline-flex min-h-11 w-full cursor-pointer items-center justify-center gap-1.5 rounded-full bg-neon px-3 text-[13px] font-semibold text-white">
                        Download QR
                    </button>
                </div>
            </div>

            <template v-if="!isOwner">
                <div class="op-card mt-10 overflow-hidden">
                    <h2>
                        <button type="button" @click="unlockOpen = !unlockOpen"
                            class="flex w-full cursor-pointer items-center justify-between gap-3 px-4 py-3 text-left transition hover:bg-sky/30"
                            :aria-expanded="unlockOpen" aria-controls="unlockPanel">
                            <span class="flex flex-col">
                                <span class="text-[13px] font-semibold text-ink">Manage this board</span>
                                <span v-if="!unlockOpen" class="text-[12px] text-muted">Owner? Unlock to add courses and settings.</span>
                            </span>
                            <svg aria-hidden="true"
                                class="size-4 shrink-0 text-muted transition-transform duration-200"
                                :class="unlockOpen ? '-rotate-180' : ''"
                                viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m6 9 6 6 6-6" />
                            </svg>
                        </button>
                    </h2>
                    <div v-if="unlockOpen" id="unlockPanel" class="px-4 pb-4 pt-1">
                        <label for="ownerInput" class="block text-[13px] font-semibold text-ink">
                            Owner secret or link
                            <span class="font-normal text-muted">— either works</span>
                        </label>
                        <form @submit.prevent="unlockOwner" class="mt-2 flex flex-col gap-2 sm:flex-row">
                            <input id="ownerInput" ref="ownerField" type="text" v-model="unlockForm.ownerInput" autocomplete="off"
                                autocapitalize="off" spellcheck="false" placeholder="Paste your owner link or secret"
                                aria-describedby="ownerInputNote"
                                :aria-invalid="!!errors.ownerInput"
                                class="min-w-0 flex-1 rounded-xl border border-sky bg-base px-3.5 py-2.5 text-[13px] text-ink shadow-inner placeholder:text-muted">
                            <button type="submit" :disabled="unlockForm.processing"
                                class="op-press w-full min-h-11 shrink-0 cursor-pointer rounded-full bg-neon px-5 text-[13px] font-semibold text-white disabled:opacity-60 sm:w-auto">
                                {{ unlockForm.processing ? 'Unlocking…' : 'Unlock' }}
                            </button>
                        </form>
                        <p v-if="errors.ownerInput" role="alert"
                           class="mt-2 text-[12px] font-medium text-danger">{{ errors.ownerInput[0] }}</p>
                        <p id="ownerInputNote" class="mt-2 text-[11px] text-muted">
                            Goes only to this board · SlipNote never asks for it by email.
                        </p>
                        <Link v-if="recoveryAvailable" :href="recoveryUrl()"
                            class="mt-1 inline-flex min-h-11 items-center text-[12px] font-semibold text-neon hover:underline sm:min-h-0">
                            Lost your owner link? Recover it
                        </Link>
                    </div>
                </div>
            </template>

            <div v-if="storageUsed > 0 && storagePct < 90 && (isOwner || storagePct >= 75)"
                class="mt-8 flex items-center gap-2">
                <div class="h-1 w-32 overflow-hidden rounded-full bg-ink/15">
                    <div class="h-full rounded-full transition-all"
                         :class="storagePct >= 75 ? 'bg-neon' : 'bg-muted'"
                         :style="{ width: storagePct + '%' }"></div>
                </div>
                <p class="text-[11px]" :class="storageTone">
                    {{ mbUsed }} of {{ mbCap }} MB used
                </p>
            </div>
        </div>
    </AppLayout>
</template>
