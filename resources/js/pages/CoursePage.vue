<script setup>
import { computed, ref, watch, nextTick } from 'vue'
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3'
import AppLayout from '@/components/AppLayout.vue'
import FlashNotice from '@/components/FlashNotice.vue'

const props = defineProps({
    workspace: Object,
    course: Object,
    isOwner: Boolean,
    storageFull: Boolean,
    canUpload: Boolean,
    storageUsed: Number,
    storageCap: Number,
    storagePct: Number,
    passphraseNeeded: Boolean,
    sections: Object,   // { notes: 'Notes', slides: 'Slides', ... }
    sectionCounts: Object,
    pinnedCounts: Object,  // { notes: 2, ... } across the course, search or not
    maxPinned: Number,
    materials: Array,
    resultCount: Number,
    search: String,
    sort: String,
})

const page = usePage()
const flash = computed(() => page.props.flash)

// The upload receipt stays until closed: it holds Undo (this moment only) and
// Tell the class, and controls must not time out. A new upload reopens it.
// Closing it hands focus to the course heading rather than dropping it on the
// page body.
const receiptClosed = ref(false)
const uploadReceipt = ref(null)
const courseHeading = ref(null)
watch(() => page.props.flash, () => { receiptClosed.value = false })
function closeReceipt() {
    receiptClosed.value = true
    nextTick(() => courseHeading.value?.focus())
}
const errors = computed(() => page.props.errors)
// A per-file error (files.0, files.3, …): the first message from whichever
// file failed. Errors arrive as arrays, so the message is [0], not the list.
const perFileError = computed(() => {
    const key = Object.keys(errors.value ?? {}).find((k) => k.startsWith('files.'))
    return key ? [].concat(errors.value[key])[0] : null
})

// Share, offered at the one moment it is earned: a file just landed. Never
// the owner link. "Tell the class it's here": the link goes to this course
// (which leads back to the whole board, and unfurls as the course), where the new
// file is on top and marked New, not to the board, which left classmates to
// find the course and then the file. The message says what's new, so it's
// worth a tap in a busy group.
const courseUrl = computed(() => window.location.origin + '/' + props.workspace.slug + '/c/' + props.course.slug)
const shareCopied = ref(false)
function shareCourse() {
    window.copyText(courseUrl.value).then(() => {
        shareCopied.value = true
        setTimeout(() => { shareCopied.value = false }, 2000)
    }).catch(() => { })
}
const whatsAppUrl = computed(() => 'https://wa.me/?text=' + encodeURIComponent(
    (flash.value.addedName
        ? `New in ${props.course.code}: ${flash.value.addedName}`
        : flash.value.addedCount > 1
            ? `${flash.value.addedCount} new files in ${props.course.code}`
            : `New in ${props.course.code}`)
    + `\n\nOpen it here, no account needed:\n${courseUrl.value}`
))

// Search / sort — server-side via router
const localSearch = ref(props.search)
const localSort = ref(props.sort)

let searchTimer = null
watch(localSearch, (val) => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => visitFilter(), 250)
})
watch(localSort, () => visitFilter())

function visitFilter() {
    router.visit(window.location.pathname, {
        data: {
            search: localSearch.value,
            sort: localSort.value,
        },
        preserveState: true,
        replace: true,
    })
}

// Grouped materials by section
// One section in use across the course (not just the current search): a
// plain list, no section heading, since the heading only told files apart
// from other sections that aren't there. Download all stays.
const singleSection = computed(() =>
    Object.values(props.sectionCounts ?? {}).filter((n) => n > 0).length === 1)

const materialsBySection = computed(() => {
    const map = {}
    for (const key of Object.keys(props.sections)) {
        map[key] = props.materials.filter(m => m.section === key)
    }
    return map
})

// "Download all" — zips a whole section (the exam-time need). Plain GET link.
function sectionZipUrl(key) {
    return '/' + props.workspace.slug + '/c/' + props.course.slug + '/download/' + key
}

const isFiltered = computed(() => localSearch.value.trim() !== '')

// Hard per-file limit, mirrors the server's `max:25600` (KB) upload rule.
const MAX_FILE_BYTES = 25 * 1024 * 1024
// Every user-facing mention of the cap derives from the constant. They had
// drifted: the blocking error said 10 MB while the hint above it said 25.
const maxFileMb = Math.round(MAX_FILE_BYTES / 1048576)

function fileSize(bytes) {
    if (bytes < 1024) return bytes + ' B'
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(0) + ' KB'
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

// Storage meter — owner-only. Mirrors the workspace-level indicator on CoursesPage.
const mbUsed = computed(() => {
    const v = props.storageUsed
    return (v / 1048576).toFixed(v >= 10485760 ? 0 : 1)
})
const mbCap = computed(() => Math.round(props.storageCap / 1048576))
const storageTone = computed(() => {
    if (props.storagePct >= 75) return 'text-neon'
    return 'text-muted'
})

const hasOversizedFile = computed(() =>
    uploadForm.files.some(f => f.size > MAX_FILE_BYTES))

// Upload form
const uploadOpen = ref(false)
const uploadForm = useForm({
    section: 'notes',
    title: '',
    passphrase: '',
    files: [],
})

watch(uploadOpen, (v) => {
    if (v) {
        nextTick(() => {
            const el = document.getElementById('add-file')
            el?.scrollIntoView({ behavior: 'smooth', block: 'start' })
        })
    }
})

// Open if hash or has errors
if (props.canUpload && (window.location.hash === '#add-file' || (errors.value && Object.keys(errors.value).length > 0))) {
    uploadOpen.value = true
}

window.addEventListener('hashchange', () => {
    if (props.canUpload && window.location.hash === '#add-file') uploadOpen.value = true
})

function onFileChange(e) {
    // Append to the running selection so "Add more files" accumulates,
    // skipping exact dupes (same name + size). Clearing the input lets the
    // same file be re-picked after removal.
    const picked = Array.from(e.target.files)
    const seen = new Set(uploadForm.files.map(f => f.name + ':' + f.size))
    for (const f of picked) {
        const key = f.name + ':' + f.size
        if (!seen.has(key)) {
            uploadForm.files.push(f)
            seen.add(key)
        }
    }
    e.target.value = ''
}

function removeFile(index) {
    uploadForm.files = uploadForm.files.filter((_, i) => i !== index)
}

function upload() {
    uploadForm.post('/' + props.workspace.slug + '/c/' + props.course.slug + '/upload', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            uploadForm.reset()
            uploadOpen.value = false
            // The form sits at the foot of the page and the receipt at the
            // top, with Undo (this moment only) and Tell the class: bring it
            // into view rather than leave the uploader at the bottom.
            nextTick(() => {
                const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches
                uploadReceipt.value?.scrollIntoView({ block: 'start', behavior: reduce ? 'auto' : 'smooth' })
            })
        },
    })
}

// Report a file to the site operator. Anonymous, via a styled modal with
// preset reasons. The server rate-limits and notifies — never auto-deletes.
const REPORT_REASONS = [
    'Not my notes / wrong file',
    'Copyright — shouldn\'t be shared',
    'Inappropriate or offensive',
    'Spam',
    'Other',
]
const reportTarget = ref(null)
const reportReason = ref('')
const reportNote = ref('')
const reportSubmitting = ref(false)

// "Other" with an empty note reaches the operator as the single word "Other",
// which they can't act on. It's the one reason that carries no meaning by
// itself, so it's the one that has to bring the note with it.
const reportNeedsNote = computed(() =>
    reportReason.value === 'Other' && reportNote.value.trim() === '')

function openReport(material) {
    reportTarget.value = material
    reportReason.value = ''
    reportNote.value = ''
}

function submitReport() {
    if (!reportTarget.value) return
    // Combine the preset reason and the optional note into one string.
    const reason = [reportReason.value, reportNote.value.trim()].filter(Boolean).join(' — ')
    reportSubmitting.value = true
    router.post(
        '/' + props.workspace.slug + '/c/' + props.course.slug + '/report/' + reportTarget.value.id,
        { reason },
        {
            preserveScroll: true,
            onFinish: () => { reportSubmitting.value = false; reportTarget.value = null },
        },
    )
}

// One styled confirm for both delete paths. Deleting was the last destructive
// action still on window.confirm — unstyled, theme-blind, and out of step with
// the Report and Undo dialogs this page already uses, even though it's the only
// one of the three that can't be taken back.
// The single-file path still submits the native form (same URL, CSRF and
// _method spoofing as before); the dialog only gates it.
const deleteTarget = ref(null)

// Pinned files lead their section whatever the sort (ordered server-side),
// up to maxPinned per section; the server holds the same line.
function sectionPinsFull(section) {
    return (props.pinnedCounts?.[section] ?? 0) >= props.maxPinned
}
function togglePin(material) {
    router.post(
        '/' + props.workspace.slug + '/c/' + props.course.slug + '/pin/' + material.id,
        { pinned: !material.pinned },
        { preserveScroll: true },
    )
}

function askDeleteFile(e) {
    e.preventDefault()
    const form = e.target.closest('form')
    deleteTarget.value = {
        title: 'Remove this file?',
        label: form.dataset.name,
        detail: 'This removes the file from the board for everyone.',
        run: () => form.submit(),
    }
}

// Undo-upload uses the app's own dialog (not window.confirm) for a
// consistent look with the Report modal. The form is only submitted once
// the user confirms inside it.
const undoForm = ref(null)
const confirmingUndo = ref(false)

function submitUndo() {
    confirmingUndo.value = false
    undoForm.value?.submit()
}

function courseListUrl() {
    return '/' + props.workspace.slug
}

// Modal focus. A native <dialog> gives this free, but these are plain divs: focus
// stayed on the trigger behind the overlay, Tab walked the page underneath, and
// closing left focus wherever it landed. Only one modal is ever open, so they
// share one ref.
const modalRef = ref(null)
let lastFocused = null

function focusables(root) {
    return Array.from(root.querySelectorAll(
        'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
    )).filter(el => el.getClientRects().length > 0)
}

// Wrap Tab at both ends so focus can't escape to the page behind.
function trapTab(e) {
    if (e.key !== 'Tab' || !modalRef.value) return
    const items = focusables(modalRef.value)
    if (!items.length) return
    const first = items[0]
    const last = items[items.length - 1]
    if (e.shiftKey && document.activeElement === first) {
        e.preventDefault()
        last.focus()
    } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault()
        first.focus()
    }
}

const anyModalOpen = computed(() =>
    !!reportTarget.value || confirmingUndo.value || !!deleteTarget.value)

watch(anyModalOpen, (open) => {
    if (open) {
        lastFocused = document.activeElement
        nextTick(() => {
            const root = modalRef.value
            if (!root) return
            // First control, or the dialog itself if it somehow has none. For the
            // two destructive dialogs that's Cancel — the safe default.
            const items = focusables(root)
            ;(items[0] ?? root).focus()
        })
    } else {
        lastFocused?.focus?.()
        lastFocused = null
    }
})

// Bulk delete (owner only). Checkboxes appear only in select mode: shown on
// every row they were a second delete control beside each row's bin icon.
const selecting = ref(false)
const selected = ref([])

function stopSelecting() {
    selected.value = []
    selecting.value = false
}
const selectedCount = computed(() => selected.value.length)
const allSelected = computed(() => selectedCount.value > 0 && selectedCount.value === props.materials.length)

function isSelected(id) {
    return selected.value.includes(id)
}

function toggleSelect(id) {
    const idx = selected.value.indexOf(id)
    if (idx === -1) selected.value.push(id)
    else selected.value.splice(idx, 1)
}

function toggleSelectAll() {
    if (allSelected.value) {
        selected.value = []
    } else {
        selected.value = props.materials.map(m => m.id)
    }
}

const bulkForm = useForm({})

function askBulkDelete() {
    if (!selectedCount.value) return
    const count = selectedCount.value
    deleteTarget.value = {
        title: `Remove ${count === 1 ? '1 file' : count + ' files'}?`,
        label: null,
        detail: 'This removes them from the board for everyone.',
        run: () => {
            bulkForm.transform(() => ({ ids: selected.value }))
                .delete('/' + props.workspace.slug + '/c/' + props.course.slug + '/materials', {
                    preserveScroll: true,
                    onSuccess: () => stopSelecting(),
                })
        },
    }
}

function runDelete() {
    const job = deleteTarget.value?.run
    deleteTarget.value = null
    job?.()
}

// Clear selection when materials list changes (after delete / filter)
watch(() => props.materials, () => { selected.value = [] })
</script>

<template>
    <Head :title="course.code + ' · ' + workspace.name" />
    <AppLayout :fab-space="canUpload && !storageFull && resultCount > 0">
        <!-- pb-6 + the last section's mb-4: 40px to the footer, as on the board page. -->
        <div class="op mx-auto flex w-full max-w-3xl flex-1 flex-col px-5 pb-6">

            <!-- Deliberately NOT .op-top (sticky) like the board page: the filter
                 bar below already claims the sticky slot at top-0, and the bulk bar
                 offsets from its height. On a file list, search that stays reachable
                 beats a title that does — so this keeps the look, not the pinning. -->
            <!-- Code, then the course name right under it as its subtitle (a
                 separate paragraph 40px below read as another block). -->
            <header class="mb-4 flex items-center justify-between gap-4 pt-6 sm:pt-8">
                <div class="min-w-0">
                    <Link :href="courseListUrl()"
                          class="op-kicker group -mt-2 mb-0 inline-flex min-h-11 items-center gap-1.5 text-[11px] font-semibold uppercase text-muted transition hover:text-neon sm:mt-0 sm:mb-1 sm:min-h-0">
                        <svg aria-hidden="true"
                             class="size-3.5 shrink-0 transition-transform duration-200 group-hover:-translate-x-0.5"
                             viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 12H5" />
                            <path d="M12 19l-7-7 7-7" />
                        </svg>
                        <span class="truncate">{{ workspace.name }}</span>
                    </Link>
                    <h1 ref="courseHeading" tabindex="-1" class="op-title truncate text-[1.6rem] font-bold text-ink outline-none sm:text-[1.85rem]">{{ course.code }}</h1>
                    <p class="mt-0.5 truncate text-[14px] text-muted">{{ course.title }}</p>
                </div>
                <!-- Desktop only — on mobile the FAB below covers this from the thumb
                     zone. Hidden when the board is full (can't upload) and on an empty
                     course, where the empty-state card's CTA is the sole invitation. -->
                <button v-if="canUpload && !storageFull && resultCount > 0" type="button" @click="uploadOpen = true"
                        class="op-press hidden h-11 shrink-0 cursor-pointer items-center gap-1 rounded-full bg-neon px-4 text-[13px] font-semibold text-white sm:inline-flex sm:h-9">
                    <span class="text-base leading-none">+</span> Add file
                </button>
            </header>

            <!-- Owner mode — quiet inline note, not a full banner -->
            <!-- Owner tools and the storage meter on one line, so files start
                 higher up the screen. -->
            <div v-if="isOwner" class="mb-4 flex flex-wrap items-center gap-x-4 gap-y-1">
                <p class="inline-flex items-center gap-1.5 text-[12px] font-medium text-muted">
                    Owner
                    <template v-if="resultCount > 1 && !selecting">
                        <span aria-hidden="true" class="text-muted/50">&middot;</span>
                        <button type="button" @click="selecting = true"
                                class="-my-3 inline-flex min-h-11 cursor-pointer items-center px-1 font-semibold text-ink transition hover:text-neon sm:my-0 sm:min-h-0">Select files</button>
                    </template>
                </p>
                <!-- Storage meter — makes the soft per-board cap legible before
                     uploads bounce. From 90% the notice below says it in words. -->
                <div v-if="storageUsed > 0 && storagePct < 90" class="flex items-center gap-2">
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

            <!-- Near full: the same notice as the board page. Everyone sees it,
                 since anyone can upload and theirs is the upload that won't fit. -->
            <div v-if="storagePct >= 90" role="status"
                class="mb-5 rounded-2xl border border-danger/30 bg-danger/10 px-4 py-3 text-[13px] text-ink">
                <p class="font-semibold">This board is almost full</p>
                <p class="mt-0.5 text-muted">
                    {{ mbUsed }} of {{ mbCap }} MB used.
                    <template v-if="isOwner">Delete files you no longer need to make room for new ones.</template>
                    <template v-else>New uploads may not fit. The board's owner can free up space.</template>
                </p>
            </div>

            <!-- Course created — confirmation only; the empty-state card owns the next-step CTA. -->
            <div v-if="flash.created"
                 class="mb-5 rounded-lg border border-neon/40 bg-neon/10 px-4 py-3 text-sm font-medium text-ink">
                {{ flash.created }}
            </div>

            <!-- Upload receipt. manageUrl is only set for a single-file upload
                 (one token, one file) — the uploader's only way to delete
                 their own upload later, since there's no login to recover it. -->
            <!-- What happened, in plain text with a check (not blue: blue is
                 for links here, and "File added." beside "Undo" read as two).
                 Then the next step, sharing, at full strength: it's what makes
                 the board worth opening. Undo is the rare case, so it's a quiet
                 line, and its time limit is a fact, not a red warning. -->
            <div v-if="flash.uploaded && !receiptClosed" ref="uploadReceipt"
                 class="op-card op-card-quiet relative mb-5 scroll-mt-4 py-3.5 pl-4 pr-12 text-sm">
                <button type="button" @click="closeReceipt" aria-label="Close"
                        class="absolute right-0.5 top-0.5 flex size-11 cursor-pointer items-center justify-center rounded-full text-muted transition hover:bg-sky/60 hover:text-ink">
                    <svg aria-hidden="true" class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 5l10 10M15 5L5 15"/></svg>
                </button>
                <div class="flex items-start gap-2.5">
                    <span aria-hidden="true" class="mt-px flex size-5 shrink-0 items-center justify-center rounded-full bg-neon/15 text-neon">
                        <svg class="size-3" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 10.5l3.5 3.5 7.5-8"/></svg>
                    </span>
                    <!-- Undo keeps a 44px target on phones (-my-3 so it doesn't push
                         the line down). -->
                    <div class="min-w-0">
                        <p class="text-[14px] font-semibold text-ink">{{ flash.uploaded }}</p>
                        <form v-if="flash.manageUrl" ref="undoForm" :action="flash.manageUrl" method="POST"
                              class="mt-0.5 flex flex-wrap items-center gap-x-1.5 text-[12px] text-muted">
                            <input type="hidden" name="_token" :value="$page.props.csrf_token ?? ''">
                            <input type="hidden" name="_method" value="DELETE">
                            <button type="button" @click="confirmingUndo = true"
                                    class="-my-3 inline-flex min-h-11 cursor-pointer items-center font-semibold text-ink underline decoration-muted/50 underline-offset-2 hover:decoration-ink sm:my-0 sm:min-h-0">Undo upload</button>
                            <span>· only until you leave</span>
                        </form>
                    </div>
                </div>
                <!-- The share moment. This is the first time the board is worth
                     opening, so this is where the link gets pasted into the
                     class group, pre-written, no composing. WhatsApp first:
                     it's where the class is. This banner is uploads only; pins
                     and removals are a FlashNotice. -->
                <div class="-mr-8 mt-3.5 flex flex-wrap items-center gap-x-3 gap-y-2">
                    <span class="text-[13px] font-semibold text-ink">Tell the class it's here</span>
                    <div class="flex flex-wrap gap-2">
                        <a :href="whatsAppUrl" target="_blank" rel="noopener noreferrer"
                            class="op-pill op-press inline-flex h-11 cursor-pointer items-center gap-1.5 rounded-full px-4 text-[13px] font-semibold text-ink transition sm:h-9 sm:px-3.5">
                            <svg aria-hidden="true" class="size-4" viewBox="0 0 256 256" fill="currentColor"><path d="M187.3,68.7A83.3,83.3,0,0,0,128,44a84,84,0,0,0-72.9,125.9L44.6,204.7a8,8,0,0,0,9.8,9.8l35.2-10.5A84,84,0,0,0,212,128,83.3,83.3,0,0,0,187.3,68.7ZM128,196a67.6,67.6,0,0,1-34.6-9.5,8,8,0,0,0-6.4-.8L64.4,192.3l6.7-22.6a8,8,0,0,0-.8-6.4A68,68,0,1,1,128,196Zm37.6-49.3-14.3-8.2a8,8,0,0,0-8.7.5l-8,6a2,2,0,0,1-2.2.2,52.3,52.3,0,0,1-21.6-21.6,2,2,0,0,1,.2-2.2l6-8a8,8,0,0,0,.5-8.7L109.3,90.4A8,8,0,0,0,102.4,86a26.2,26.2,0,0,0-24.7,20.2,60.1,60.1,0,0,0,72.1,72.1A26.2,26.2,0,0,0,170,153.6,8,8,0,0,0,165.6,146.7Z"/></svg>
                            <span>WhatsApp</span>
                            <span class="sr-only">(opens WhatsApp)</span>
                        </a>
                        <button type="button" @click="shareCourse"
                            class="op-pill op-press inline-flex h-11 cursor-pointer items-center gap-1.5 rounded-full px-4 text-[13px] font-semibold text-ink transition sm:h-9 sm:px-3.5">
                            <span v-if="!shareCopied">Copy link</span>
                            <span v-else>Copied ✓</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Pin refused (a stale tab: the menu already says when a section is full). -->
            <div v-if="errors.pin" role="alert"
                 class="mb-5 rounded-xl border border-danger/30 bg-danger/5 px-4 py-3 text-sm font-medium text-danger">
                {{ errors.pin[0] }}
            </div>

            <!-- Report receipt -->
            <div v-if="flash.reported"
                 class="teal-accent mb-5 rounded-xl border border-sky bg-sky/40 px-4 py-3 text-sm font-medium text-teal">
                {{ flash.reported }}
            </div>

            <!-- Sticky filter bar — only useful once the course has files -->
            <!-- Static on phones: stacked search+sort+pills would pin ~170px of
                 chrome over a small viewport, and the bulk bar's offset assumes
                 the desktop height. Sticky from sm up. -->
            <!-- Search and sort, from 7 files: about a screen's worth on a phone.
                 Below that the list is its own search. No section pills: the
                 files are already grouped under section headings, each with its
                 count on "Download all", so the pills repeated them above the list. -->
            <div v-if="resultCount > 6" class="z-30 -mx-5 mb-5 bg-base/95 px-5 py-3 backdrop-blur sm:sticky sm:top-0">
                <div class="flex flex-col gap-2 sm:flex-row">
                    <input type="text" inputmode="search" v-model="localSearch"
                           :placeholder="`Search ${resultCount} files by name…`"
                           aria-label="Search files"
                           class="op-pill box-border w-full min-w-0 flex-1 appearance-none rounded-xl border border-sky bg-surface px-3.5 py-2.5 text-[14px] font-medium text-ink shadow-sm placeholder:font-normal placeholder:text-muted">
                    <div class="relative w-full sm:w-auto">
                        <select v-model="localSort" aria-label="Sort files"
                                class="op-pill box-border w-full appearance-none rounded-xl border border-sky bg-surface py-2.5 pl-3.5 pr-10 text-[14px] font-medium text-ink shadow-sm">
                            <option value="newest">Newest first</option>
                            <option value="oldest">Oldest first</option>
                            <option value="az">A–Z</option>
                        </select>
                        <svg class="pointer-events-none absolute right-3.5 top-1/2 size-4 -translate-y-1/2 text-muted" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M6 8l4 4 4-4" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Empty filtered state -->
            <p v-if="isFiltered && materials.length === 0"
               class="op-card px-6 py-8 text-center text-[14px] text-muted">
                No files match "<span class="font-semibold text-ink">{{ localSearch }}</span>".
            </p>

            <!-- Whole-course empty state: one friendly card instead of four
                 empty section stubs. Only when nothing's uploaded and no
                 filter is active. -->
            <!-- Not my-auto: this is a flex child of a flex-1 column, so auto margins
                 centred it in every remaining pixel of viewport. With a header, a
                 subtitle, an owner note and a flash banner stacked above, that opened
                 ~300px of void between the message and the card it belongs to. -->
            <div v-if="resultCount === 0 && !isFiltered"
                 class="op-card px-6 py-14 text-center">
                <p class="text-[16px] font-semibold tracking-tight text-ink">No files yet</p>
                <p v-if="canUpload" class="mx-auto mt-1.5 max-w-sm text-[14px] leading-relaxed text-muted">
                    Be the first to add notes, slides, or past papers for
                    <span class="font-semibold text-ink">{{ course.code }}</span>.
                </p>
                <!-- Uploads locked to the owner: say who adds files, so an empty
                     course reads as "not yet", not as broken. -->
                <p v-else class="mx-auto mt-1.5 max-w-sm text-[14px] leading-relaxed text-muted">
                    Files for <span class="font-semibold text-ink">{{ course.code }}</span> are added by the board's owner. Check back soon.
                </p>
                <button v-if="canUpload" type="button" @click="uploadOpen = true"
                        class="op-press mt-5 inline-flex min-h-11 cursor-pointer items-center gap-1 rounded-full bg-neon px-5 text-[14px] font-semibold text-white">
                    <span class="text-lg leading-none">+</span> Add the first file
                </button>
            </div>

            <!-- Bulk action bar (owner, selection active) -->
            <!-- top-2 on phones (filter bar is static there); below the sticky
                 filter bar's desktop height from sm up. -->
            <div v-if="isOwner && selecting"
                 :class="resultCount > 6 ? 'sm:top-18' : 'sm:top-2'"
                 class="sticky top-2 z-20 mb-4 flex flex-col gap-2 rounded-xl border border-sky bg-surface px-4 py-2.5 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:gap-3">
                <!-- The count is inside the label, so select-all has a full-width
                     target instead of a 16px box. -->
                <label class="flex min-h-11 cursor-pointer items-center gap-3 sm:min-h-0">
                    <input type="checkbox"
                           :checked="allSelected"
                           :indeterminate="selectedCount > 0 && !allSelected"
                           @change="toggleSelectAll"
                           class="size-4 shrink-0 cursor-pointer accent-teal">
                    <span class="text-xs font-medium text-muted">
                        {{ selectedCount }} {{ selectedCount === 1 ? 'file' : 'files' }} selected
                    </span>
                </label>
                <div class="flex items-center gap-4">
                    <button type="button" @click="stopSelecting"
                            class="op-press inline-flex min-h-11 cursor-pointer items-center text-[13px] font-medium text-muted hover:text-ink sm:min-h-0">
                        Cancel
                    </button>
                    <button type="button" @click="askBulkDelete" :disabled="bulkForm.processing || selectedCount === 0"
                            class="btn-danger op-press inline-flex min-h-11 cursor-pointer items-center rounded-full px-4 text-[13px] font-semibold disabled:opacity-60 sm:min-h-0 sm:py-1.5">
                        <template v-if="selectedCount === 0">Delete</template>
                        <template v-else>Delete {{ selectedCount === 1 ? '1 file' : selectedCount + ' files' }}</template>
                    </button>
                </div>
            </div>

            <!-- Materials grouped by section. Empty sections aren't shown —
                 the filter chips above already report which sections are empty,
                 so per-section "nothing here" stubs would just duplicate that. -->
            <template v-for="(label, key) in sections" :key="key">
                <template v-if="materialsBySection[key]?.length > 0">
                    <!-- op-card + op-row: hairline-separated rows in one surface,
                         same as the board page. Drops the old shadow-md and
                         ring-black/3, which was invisible on a dark background. Not
                         overflow-hidden: it cut off a row menu that runs past the
                         card's foot (the last row's, two-line "Pin to top" when the
                         section is full). The last row rounds its own corners so a
                         selected row's tint still fits the card. -->
                    <section :id="'sec-' + key" class="op-card mb-4 scroll-mt-20">
                        <!-- A course using one section: no visible heading (still named
                             for screen readers), just Download all, or nothing. -->
                        <div v-if="!singleSection || sectionCounts[key] > 1"
                             class="flex items-center gap-3 px-4 pb-1 pt-3 sm:px-5"
                             :class="singleSection ? 'justify-end' : 'justify-between'">
                            <!-- With no dividers the heading is all that separates
                                 sections, so body-size ink with its count, not 11px
                                 grey capitals. -->
                            <h2 class="text-[14px] font-semibold text-ink" :class="singleSection ? 'sr-only' : ''">
                                {{ label }} <span class="font-normal text-muted">· {{ sectionCounts[key] ?? 0 }}</span>
                            </h2>
                            <a v-if="sectionCounts[key] > 1" :href="sectionZipUrl(key)"
                               class="op-press inline-flex min-h-11 shrink-0 items-center gap-1 text-[12px] font-semibold text-muted transition hover:text-ink sm:min-h-0">
                                <svg aria-hidden="true" class="size-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M10 3v9m0 0 3.5-3.5M10 12 6.5 8.5M4 15h12" />
                                </svg>
                                Download all ({{ sectionCounts[key] }})
                            </a>
                        </div>
                        <div v-for="material in materialsBySection[key]" :key="material.id"
                             class="op-row flex items-center justify-between gap-2 last:rounded-b-[var(--op-radius)] sm:gap-3"
                             :class="isSelected(material.id) ? 'bg-teal/5' : ''">
                            <div class="flex min-w-0 items-start gap-2.5 sm:gap-3">
                                <!-- Bulk select checkbox (owner only). The label carries
                                     a 44px tap area on phones — the filename beside it
                                     is a link, so it can't be folded in the way the
                                     select-all count can. -->
                                <!-- h-11 w-9: the tap area stays 44px tall, but 44px
                                     wide around a 16px box was 28px of pure padding
                                     taken straight off the filename. -->
                                <label v-if="isOwner && selecting"
                                       class="-my-2 flex h-11 w-9 shrink-0 cursor-pointer items-center justify-center sm:my-0 sm:mt-1 sm:size-4">
                                    <input type="checkbox"
                                           :checked="isSelected(material.id)"
                                           @change="toggleSelect(material.id)"
                                           :aria-label="`Select ${material.displayName} to delete`"
                                           title="Select to delete"
                                           class="size-4 shrink-0 cursor-pointer accent-teal">
                                </label>
                                <!-- Type badge hidden on phones — the row width goes to the
                                     filename; the extension is usually in the name anyway.
                                     Fixed width, centred: sized to its text, "PPTX" pushed
                                     its title right of every "PDF" title in the list. -->
                                <span class="mt-[3px] hidden w-9 shrink-0 rounded-[5px] bg-sky/40 py-px text-center text-[9px] font-semibold uppercase leading-4 tracking-wide text-muted sm:inline-block"
                                      :title="material.fileTypeLabel + ' file'">
                                    {{ material.fileTypeLabel }}
                                </span>
                                <div class="min-w-0">
                                    <!-- No underline until hover (focus has the shared outline): in a list the ↗, the row
                                         highlight and the position already say "link", and an
                                         underline on every title striped the page.
                                         Title opens the preview (look inside — the library mental model);
                                         the ↗ icon signals it opens in a new tab so it isn't a silent
                                         trapdoor. Non-previewable types (docx/ppt) have no preview, so the
                                         title downloads and shows no icon. The pill stays the download action. -->
                                    <!-- Pinned: an amber pin before the name, nothing else. The
                                         file is already first in its section; the pin says that
                                         was on purpose. Amber because a pushpin is, and because
                                         nothing else here is (blue means New). amber-600 is 3.2:1
                                         on white; app.css lifts it on dark. -->
                                    <div class="flex min-w-0 items-start gap-1.5">
                                    <svg v-if="material.pinned" role="img" aria-label="Pinned" class="mt-[3px] size-4 shrink-0 text-amber-600" viewBox="0 0 20 20" fill="currentColor"><title>Pinned to the top</title><path d="M12.6 2.3a1 1 0 0 1 1.4 0l3.7 3.7a1 1 0 0 1 0 1.4l-1.2 1.2a1 1 0 0 1-1.1.2l-2.6 2.6.4 3.2a1 1 0 0 1-.3.8l-.9.9a1 1 0 0 1-1.4 0L7.4 13.1l-3.7 3.7a.75.75 0 0 1-1.1-1.1l3.7-3.7-3.2-3.2a1 1 0 0 1 0-1.4l.9-.9a1 1 0 0 1 .8-.3l3.2.4 2.6-2.6a1 1 0 0 1 .2-1.1z"/></svg>
                                    <div class="min-w-0 flex-1">
                                    <a v-if="material.preview_url" :href="material.preview_url"
                                       target="_blank" rel="noopener"
                                       :title="`Preview ${material.displayName} (opens in a new tab)`"
                                       class="flex items-center gap-1 text-[15px] font-semibold text-ink decoration-muted/60 underline-offset-4 hover:underline">
                                        <span class="line-clamp-2 wrap-anywhere sm:truncate">{{ material.displayName }}</span>
                                        <svg aria-hidden="true" class="size-3.5 shrink-0 self-start text-muted mt-1" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M8 5H5v10h10v-3M12 4h4v4M16 4l-7 7" />
                                        </svg>
                                    </a>
                                    <a v-else-if="material.download_url" :href="material.download_url"
                                       class="line-clamp-2 wrap-anywhere text-[15px] font-semibold text-ink decoration-muted/60 underline-offset-4 hover:underline sm:block sm:truncate">
                                        {{ material.displayName }}
                                    </a>
                                    <span v-else class="block truncate text-[15px] font-semibold text-ink">
                                        {{ material.displayName }}
                                    </span>
                                    </div>
                                    </div>
                                    <!-- New first, kept whole; only the time and size shorten
                                         when a phone row runs out of room. New is since
                                         this browser last opened the course, or this week.
                                         No "Anonymous" for files without a name: it said nothing. -->
                                    <div class="mt-0.5 flex min-w-0 items-center gap-1 text-[12px] text-muted">
                                        <span v-if="material.is_new" class="shrink-0 rounded-full bg-neon/10 px-1.5 py-px text-[10px] font-bold uppercase tracking-[0.06em] text-neon">New</span>
                                        <span class="min-w-0 truncate"><template v-if="material.uploader_name">{{ material.uploader_name }} · </template>{{ material.created_at_human }}<template v-if="material.file_size"> · {{ fileSize(material.file_size) }}</template></span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex shrink-0 items-center gap-1">
                                <!-- Delete lives in a menu, like Report for classmates:
                                     a bare bin on every row sat one mis-tap from
                                     Download, and "Select files" covers clearing out. -->
                                <details v-if="isOwner && !selecting" class="op-menu relative">
                                    <summary :aria-label="`More actions for ${material.displayName}`" title="More"
                                             class="flex size-10 shrink-0 cursor-pointer list-none items-center justify-center rounded-full text-muted transition hover:bg-sky/40 hover:text-ink marker:hidden sm:size-9">
                                        <svg aria-hidden="true" class="size-4" viewBox="0 0 20 20" fill="currentColor"><circle cx="4" cy="10" r="1.6"/><circle cx="10" cy="10" r="1.6"/><circle cx="16" cy="10" r="1.6"/></svg>
                                    </summary>
                                    <div class="absolute right-full top-1/2 z-20 mr-1 w-48 -translate-y-1/2 rounded-xl bg-surface p-1 text-left shadow-xl ring-1 ring-sky">
                                        <!-- Full section: the item stays, says why, and does
                                             nothing, rather than vanishing or failing after the tap. -->
                                        <button v-if="!material.pinned && sectionPinsFull(material.section)" type="button"
                                                aria-disabled="true" :aria-describedby="`pinfull-${material.id}`"
                                                class="block min-h-11 w-full cursor-not-allowed rounded-lg px-3 py-2 text-left sm:min-h-0">
                                            <span class="block text-[13px] font-semibold text-muted">Pin to top</span>
                                            <span :id="`pinfull-${material.id}`" class="block text-[11px] text-muted">{{ maxPinned }} already pinned in {{ sections[material.section] }}. Unpin one first.</span>
                                        </button>
                                        <button v-else type="button"
                                                @click="$event.target.closest('details')?.removeAttribute('open'); togglePin(material)"
                                                class="block min-h-11 w-full cursor-pointer rounded-lg px-3 py-2 text-left text-[13px] font-semibold text-ink hover:bg-sky/40 sm:min-h-0">
                                            {{ material.pinned ? 'Unpin' : 'Pin to top' }}
                                        </button>
                                        <form :action="material.delete_url" method="POST"
                                              :data-name="material.displayName"
                                              @submit="askDeleteFile">
                                            <input type="hidden" name="_token" :value="$page.props.csrf_token ?? ''">
                                            <input type="hidden" name="_method" value="DELETE">
                                            <button type="submit"
                                                    @click="$event.target.closest('details')?.removeAttribute('open')"
                                                    class="block min-h-11 w-full cursor-pointer rounded-lg px-3 py-2 text-left text-[13px] font-semibold text-danger hover:bg-danger/10 sm:min-h-0">
                                                Delete this file
                                            </button>
                                        </form>
                                    </div>
                                </details>
                                <!-- Report lives in a menu, not as a bare flag beside
                                     Download: a flag doesn't say "report", and on a
                                     phone it sat one mis-tap from the action people
                                     came for. <details> + app.js handle open, outside
                                     click and Escape, as on the operator page. Opens to
                                     the left, inside the row: the section card clips
                                     anything that drops below its last row. -->
                                <details v-if="!isOwner && !selecting" class="op-menu relative">
                                    <summary :aria-label="`More actions for ${material.displayName}`" title="More"
                                             class="flex size-10 shrink-0 cursor-pointer list-none items-center justify-center rounded-full text-muted transition hover:bg-sky/40 hover:text-ink marker:hidden sm:size-9">
                                        <svg aria-hidden="true" class="size-4" viewBox="0 0 20 20" fill="currentColor"><circle cx="4" cy="10" r="1.6"/><circle cx="10" cy="10" r="1.6"/><circle cx="16" cy="10" r="1.6"/></svg>
                                    </summary>
                                    <div class="absolute right-full top-1/2 z-20 mr-1 w-44 -translate-y-1/2 rounded-xl bg-surface p-1 text-left shadow-xl ring-1 ring-sky">
                                        <button type="button"
                                                @click="$event.target.closest('details')?.removeAttribute('open'); openReport(material)"
                                                class="block w-full cursor-pointer rounded-lg px-3 py-2 text-left text-[13px] font-semibold text-danger hover:bg-danger/10">
                                            Report this file
                                        </button>
                                    </div>
                                </details>
                                <!-- Icon-only on mobile so the filename keeps its width;
                                     text pill from sm up.
                                     Soft grey, not the accent: Download repeats on every
                                     row, and an accent-coloured control running down the
                                     whole list stops reading as "the action here". bg-base
                                     because bg-surface was the same tone as the op-card
                                     behind it, which left the blue arrow carrying the
                                     entire affordance (border-sky is 1.23:1 in dark). -->
                                <a v-if="!selecting && material.download_url" :href="material.download_url"
                                   aria-label="Download" title="Download"
                                   class="op-pill op-press flex size-10 items-center justify-center rounded-full border border-sky bg-base text-ink transition hover:bg-sky/40 sm:h-auto sm:w-auto sm:px-3.5 sm:py-2">
                                    <svg aria-hidden="true" class="size-4 sm:hidden" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M10 3v9m0 0 3.5-3.5M10 12 6.5 8.5M4 15h12" />
                                    </svg>
                                    <span class="hidden text-[13px] font-semibold sm:inline">Download</span>
                                </a>
                            </div>
                        </div>
                    </section>
                </template>
            </template>

            <!-- Upload section -->
            <!-- Not rendered at all when the owner keeps uploads to themselves:
                 no form, no floating Add file. The server refuses them anyway. -->
            <!-- Closed, this holds only the fixed Add file button, so it takes no
                 room: its margin left a 28px gap above the footer. -->
            <section v-if="canUpload" id="add-file" class="scroll-mt-6" :class="uploadOpen || storageFull ? 'mt-7' : ''">
                <!-- Board full -->
                <div v-if="storageFull" class="rounded-2xl border border-danger/30 bg-danger/5 px-6 py-5 text-center">
                    <p class="text-[14px] font-semibold text-danger">This board is full</p>
                    <p class="mt-1 text-[13px] text-danger">
                        Ask the owner to delete old files before new uploads can go up.
                    </p>
                </div>

                <template v-else>
                    <!-- Mobile-only FAB — bottom-right thumb zone. On phones the
                         header pill is out of reach and scrolls away; this is the
                         standard mobile counterpart to the desktop header action. -->
                    <!-- Labelled, not a bare "+": desktop says "Add file", and an
                         unlabelled icon makes people guess. The page reserves room
                         beneath the footer (AppLayout fab-space) so it never covers
                         a row or a footer link. -->
                    <button v-if="!uploadOpen && resultCount > 0" type="button" @click="uploadOpen = true"
                            class="fixed bottom-5 right-5 z-20 inline-flex h-12 cursor-pointer items-center gap-1.5 rounded-full bg-neon pl-4 pr-5 text-[15px] font-semibold text-white shadow-lg transition hover:brightness-125 sm:hidden">
                        <svg aria-hidden="true" class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M10 4v12M4 10h12"/></svg>
                        Add file
                    </button>

                    <!-- Expanded form -->
                    <div v-if="uploadOpen"
                         class="op-card px-4 py-5 sm:px-6">
                        <div class="mb-3.5 flex items-center justify-between">
                            <h2 class="text-xs font-bold uppercase tracking-[0.06em] text-muted">Add a file</h2>
                            <button type="button" @click="uploadOpen = false"
                                    aria-label="Close"
                                    class="op-press -mr-1 flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-full text-muted transition hover:bg-danger/10 hover:text-danger sm:size-9">
                                <svg aria-hidden="true" class="size-4" viewBox="0 0 20 20" fill="none"
                                     stroke="currentColor" stroke-width="1.75" stroke-linecap="round">
                                    <path d="M5 5l10 10M15 5L5 15" />
                                </svg>
                            </button>
                        </div>

                        <form @submit.prevent="upload" class="flex flex-col gap-3.5">
                            <!-- Passphrase -->
                            <div v-if="passphraseNeeded">
                                <label for="passphrase" class="mb-0.5 block text-[13px] font-semibold text-ink">Course passphrase</label>
                                <!-- Was the placeholder. That's guidance, not an example
                                     of what to type, and it vanished on the first
                                     keystroke — the moment you'd realise you have to go
                                     and ask someone. -->
                                <p class="mb-1.5 text-[12px] text-muted">Ask your course rep if you don't have it.</p>
                                <input id="passphrase" type="password" v-model="uploadForm.passphrase"
                                       :aria-invalid="!!errors.passphrase"
                                       class="w-full rounded-xl border border-sky bg-base px-3.5 py-2.5 text-[15px] text-ink shadow-inner placeholder:text-muted">
                                <span v-if="errors.passphrase" role="alert" class="mt-1.5 block text-[13px] text-danger">{{ errors.passphrase[0] }}</span>
                            </div>

                            <!-- First, not last: this is the one required field and the
                                 reason anyone opened the form. It used to sit under
                                 three optional ones, so "Name this file (optional)"
                                 asked you to name a file you hadn't chosen yet — and
                                 that field, which only shows for a single upload,
                                 would vanish once you picked a second. -->
                            <div>
                                <span class="mb-1.5 block text-[13px] font-semibold text-ink">
                                    Files <span class="text-danger" aria-hidden="true">*</span>
                                </span>
                                <!-- Native input is visually hidden; the label below
                                     drives it so the browser's "No file chosen" text
                                     never contradicts the managed list. -->
                                <input id="ufile" type="file" multiple @change="onFileChange"
                                       accept=".pdf,.docx,.pptx,.png,.jpg,.jpeg"
                                       :aria-invalid="!!errors.files" class="sr-only">
                                <!-- The whole area is the target, with the limits inside it:
                                     a small pill plus a separate grey line read as two
                                     things to parse before the one thing to do. -->
                                <label for="ufile"
                                       class="op-press flex min-h-24 cursor-pointer flex-col items-center justify-center gap-1 rounded-2xl border border-dashed border-muted/50 px-4 py-4 text-center transition hover:bg-sky/30">
                                    <span class="inline-flex items-center gap-1.5 text-[14px] font-semibold text-neon">
                                        <svg aria-hidden="true" class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round">
                                            <path d="M10 4v12M4 10h12"/>
                                        </svg>
                                        {{ uploadForm.files.length > 0 ? 'Add more files' : 'Choose files' }}
                                    </span>
                                    <span class="text-xs text-muted">PDF, Word, PowerPoint, image · max {{ maxFileMb }}&nbsp;MB</span>
                                </label>
                                <div v-if="uploadForm.files.length > 0" class="mt-2 rounded-lg bg-base px-3 py-2.5">
                                    <p class="mb-1.5 text-[11px] font-semibold uppercase tracking-[0.06em] text-muted">
                                        {{ uploadForm.files.length }} {{ uploadForm.files.length === 1 ? 'file' : 'files' }} selected
                                    </p>
                                    <ul class="space-y-0.5">
                                        <li v-for="(f, i) in uploadForm.files" :key="i"
                                            class="flex items-center gap-2 text-[12px] text-ink">
                                            <span class="min-w-0 flex-1 truncate">{{ f.name }}</span>
                                            <span class="shrink-0 tabular-nums"
                                                  :class="f.size > MAX_FILE_BYTES ? 'font-semibold text-danger' : 'text-muted'">
                                                {{ fileSize(f.size) }}<template v-if="f.size > MAX_FILE_BYTES"> · too big</template>
                                            </span>
                                            <button type="button" @click="removeFile(i)"
                                                    :aria-label="`Remove ${f.name}`"
                                                    class="-my-2 -mr-1 flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-full text-muted transition hover:bg-danger/10 hover:text-danger">
                                                <svg class="size-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round">
                                                    <path d="M4 4l8 8M12 4l-8 8"/>
                                                </svg>
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                                <span v-if="errors.files" role="alert" class="mt-1.5 block text-[13px] text-danger">{{ errors.files[0] }}</span>
                                <span v-if="perFileError" role="alert" class="mt-1.5 block text-[13px] text-danger">{{ perFileError }}</span>
                            </div>

                            <!-- Three choices, so all three are shown: a dropdown hid the
                                 options behind a tap and needed a helper line to say what
                                 it was for. A radio group, styled like the filter pills. -->
                            <fieldset>
                                <legend class="mb-1.5 text-[13px] font-semibold text-ink">Section</legend>
                                <div class="flex flex-wrap gap-1.5">
                                    <label v-for="(label, key) in sections" :key="key"
                                           class="op-press inline-flex min-h-11 cursor-pointer items-center rounded-full px-4 text-[13px] font-semibold transition sm:min-h-9"
                                           :class="uploadForm.section === key ? 'bg-neon text-white' : 'bg-base text-muted hover:text-ink'">
                                        <input type="radio" name="usection" :value="key" v-model="uploadForm.section" class="sr-only">
                                        {{ label }}
                                    </label>
                                </div>
                            </fieldset>

                            <!-- Optional, so quiet: the label does the work of a helper line.
                                 Only for a single upload. No "Your name": it was left blank
                                 on nearly every file, and rows just said "Anonymous". -->
                            <div v-if="uploadForm.files.length <= 1">
                                <div class="min-w-0">
                                    <label for="utitle" class="mb-1.5 block text-[13px] font-semibold text-ink">File name <span class="font-normal text-muted">(optional)</span></label>
                                    <input id="utitle" type="text" v-model="uploadForm.title"
                                           placeholder="e.g. Week 7 quiz solutions"
                                           :aria-invalid="!!errors.title"
                                           class="w-full rounded-xl border border-sky bg-base px-3.5 py-2.5 text-[15px] text-ink shadow-inner placeholder:text-muted">
                                    <span v-if="errors.title" role="alert" class="mt-1.5 block text-[13px] text-danger">{{ errors.title[0] }}</span>
                                </div>
                            </div>

                            <button type="submit" :disabled="uploadForm.processing || uploadForm.files.length === 0 || hasOversizedFile"
                                    class="op-press relative min-h-11 cursor-pointer overflow-hidden rounded-full bg-neon py-3 text-[15px] font-semibold text-white transition
                                           disabled:cursor-not-allowed disabled:bg-sky disabled:text-muted">
                                <!-- Progress fill: a translucent white bar that
                                     grows left→right as the upload streams to
                                     the server. Sits BEHIND the button text. -->
                                <span v-if="uploadForm.progress"
                                      class="absolute inset-y-0 left-0 bg-white/25 transition-[width] duration-150 ease-linear"
                                      :style="{ width: uploadForm.progress.percentage + '%' }"></span>
                                <span class="relative" v-if="!uploadForm.processing">
                                    {{ uploadForm.files.length > 1 ? `Upload ${uploadForm.files.length} files` : 'Upload' }}
                                </span>
                                <span class="relative tabular-nums" v-else-if="uploadForm.progress">
                                    Uploading… {{ uploadForm.progress.percentage }}%
                                </span>
                                <span class="relative" v-else>Saving…</span>
                            </button>
                            <p v-if="uploadForm.files.length === 0" class="-mt-1 text-center text-[12px] text-muted">
                                Choose at least one file to upload.
                            </p>
                            <p v-else-if="hasOversizedFile" class="-mt-1 text-center text-[12px] text-danger">
                                Remove any file over {{ maxFileMb }}&nbsp;MB to upload.
                            </p>
                        </form>
                    </div>
                </template>
            </section>

            <!-- Report modal -->
            <div v-if="reportTarget" ref="modalRef" tabindex="-1" @keydown="trapTab"
                 class="fixed inset-0 z-50 flex items-end justify-center sm:items-center"
                 role="dialog" aria-modal="true" aria-label="Report file"
                 @keydown.escape.window="reportTarget = null">
                <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" @click="reportTarget = null"></div>
                <div class="relative max-h-[90vh] w-full max-w-md overflow-y-auto rounded-t-2xl bg-surface px-6 pb-[max(1.5rem,env(safe-area-inset-bottom))] pt-3 shadow-xl sm:rounded-2xl sm:pt-6">
                    <!-- Mobile grab handle -->
                    <div class="mx-auto mb-3 h-1 w-10 rounded-full bg-muted/30 sm:hidden"></div>
                    <h2 class="text-[15px] font-bold text-ink">Report this file</h2>
                    <!-- Not truncated: this line is the only proof you're reporting
                         the file you meant, and it ellipsised on narrow screens. -->
                    <p class="mt-1 wrap-break-word text-[13px] text-muted">{{ reportTarget.displayName }}</p>

                    <fieldset class="mt-4 space-y-1.5">
                        <!-- Visible, not sr-only: every other field on this page
                             carries a label, and it'd be odd for the optional note
                             below to have one while the required choice doesn't. -->
                        <legend class="mb-1.5 text-[13px] font-semibold text-ink">Why are you reporting it?</legend>
                        <label v-for="r in REPORT_REASONS" :key="r"
                               class="flex min-h-11 cursor-pointer items-center gap-2.5 rounded-xl border px-3.5 py-2.5 text-[14px] transition"
                               :class="reportReason === r ? 'border-neon bg-neon/5 text-ink' : 'border-sky/40 text-ink/90 hover:bg-sky/30'">
                            <input type="radio" name="report-reason" :value="r" v-model="reportReason"
                                   class="size-4 accent-neon">
                            {{ r }}
                        </label>
                    </fieldset>

                    <!-- A real label, not just a placeholder: the placeholder was the
                         only thing naming this field, and it disappears the moment
                         you type. Picking "Other" makes this the only place to say
                         what's actually wrong. -->
                    <label for="reportNote" class="mt-3 block text-[13px] font-semibold text-ink">
                        {{ reportReason === 'Other' ? 'What\'s wrong with it?' : 'Add a note (optional)' }}
                    </label>
                    <textarea id="reportNote" v-model="reportNote" rows="2" maxlength="280"
                              placeholder="Anything the operator should know"
                              class="mt-1.5 w-full resize-none rounded-xl border border-sky bg-base px-3.5 py-2.5 text-[14px] text-ink shadow-inner placeholder:text-muted"></textarea>
                    <!-- Only once it's close to mattering — typing used to just stop
                         dead at 280 with nothing on screen explaining why. -->
                    <p v-if="reportNote.length > 200" aria-live="polite"
                       class="mt-1 text-right text-[11px] tabular-nums"
                       :class="reportNote.length >= 280 ? 'text-danger' : 'text-muted'">
                        {{ reportNote.length }}/280
                    </p>

                    <div class="mt-4 flex items-center justify-end gap-2">
                        <button type="button" @click="reportTarget = null"
                                class="op-press inline-flex min-h-11 cursor-pointer items-center rounded-full px-4 text-[14px] font-semibold text-muted transition hover:bg-sky/30 hover:text-ink">Cancel</button>
                        <button type="button" @click="submitReport"
                                :disabled="!reportReason || reportNeedsNote || reportSubmitting"
                                class="op-press inline-flex min-h-11 cursor-pointer items-center rounded-full bg-neon px-5 text-[14px] font-semibold text-white disabled:opacity-50">
                            {{ reportSubmitting ? 'Reporting…' : 'Report file' }}
                        </button>
                    </div>
                    <!-- Says why the button is dead rather than leaving the user to
                         work it out — the only blocked state in this dialog. -->
                    <p v-if="reportNeedsNote" class="mt-2 text-[12px] text-muted">
                        Add a note so the operator knows what to look at.
                    </p>
                    <!-- The lecturer reporting their own slides: what happens next,
                         and how to get a lot removed at once. -->
                    <p v-if="reportReason.startsWith('Copyright')" class="mt-3 text-[12px] text-muted">
                        Your own material?
                        <a href="/lecturers" target="_blank" rel="noopener" class="font-semibold text-neon hover:underline">What we do for lecturers<span class="sr-only"> (opens in a new tab)</span></a>
                    </p>
                    <p class="mt-3 text-[11px] text-muted">Goes to the site operator for review. Files aren't removed automatically.</p>
                </div>
            </div>

            <div v-if="confirmingUndo" ref="modalRef" tabindex="-1" @keydown="trapTab"
                 class="fixed inset-0 z-50 flex items-end justify-center sm:items-center"
                 role="dialog" aria-modal="true" aria-label="Undo upload"
                 @keydown.escape.window="confirmingUndo = false">
                <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" @click="confirmingUndo = false"></div>
                <div class="relative w-full max-w-md rounded-t-2xl bg-surface px-6 pb-[max(1.5rem,env(safe-area-inset-bottom))] pt-3 shadow-xl sm:rounded-2xl sm:pt-6">
                    <!-- Mobile grab handle -->
                    <div class="mx-auto mb-3 h-1 w-10 rounded-full bg-muted/30 sm:hidden"></div>
                    <h2 class="text-[15px] font-bold text-ink">Undo this upload?</h2>
                    <p class="mt-1 text-[13px] text-muted">The file will be removed from the board. This can't be undone.</p>

                    <div class="mt-4 flex items-center justify-end gap-2">
                        <button type="button" @click="confirmingUndo = false"
                                class="op-press inline-flex min-h-11 cursor-pointer items-center rounded-full px-4 text-[14px] font-semibold text-muted transition hover:bg-sky/30 hover:text-ink">Cancel</button>
                        <button type="button" @click="submitUndo"
                                class="op-press btn-danger inline-flex min-h-11 cursor-pointer items-center rounded-full px-5 text-[14px] font-semibold">
                            Undo upload
                        </button>
                    </div>
                </div>
            </div>

            <!-- Delete confirm. Same shape as the Report and Undo dialogs, so the
                 one action on this page that genuinely can't be taken back no
                 longer has the least considered UI. Names what's going, and that
                 it goes for everyone — window.confirm said neither. -->
            <div v-if="deleteTarget" ref="modalRef" tabindex="-1" @keydown="trapTab"
                 class="fixed inset-0 z-50 flex items-end justify-center sm:items-center"
                 role="dialog" aria-modal="true" aria-label="Confirm removal"
                 @keydown.escape.window="deleteTarget = null">
                <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" @click="deleteTarget = null"></div>
                <div class="relative w-full max-w-md rounded-t-2xl bg-surface px-6 pb-[max(1.5rem,env(safe-area-inset-bottom))] pt-3 shadow-xl sm:rounded-2xl sm:pt-6">
                    <!-- Mobile grab handle -->
                    <div class="mx-auto mb-3 h-1 w-10 rounded-full bg-muted/30 sm:hidden"></div>
                    <h2 class="text-[15px] font-bold text-ink">{{ deleteTarget.title }}</h2>
                    <p v-if="deleteTarget.label" class="mt-1 text-[13px] text-muted">{{ deleteTarget.label }}</p>
                    <p class="mt-3 text-[13px] leading-relaxed text-ink/80">
                        {{ deleteTarget.detail }} This can't be undone.
                    </p>

                    <div class="mt-4 flex items-center justify-end gap-2">
                        <button type="button" @click="deleteTarget = null"
                                class="op-press inline-flex min-h-11 cursor-pointer items-center rounded-full px-4 text-[14px] font-semibold text-muted transition hover:bg-sky/30 hover:text-ink">Cancel</button>
                        <button type="button" @click="runDelete"
                                class="btn-danger op-press inline-flex min-h-11 cursor-pointer items-center rounded-full px-5 text-[14px] font-semibold">
                            Remove
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <FlashNotice :below-sticky-bar="resultCount > 6" />
    </AppLayout>
</template>
