<script setup>
import { computed, ref, watch } from 'vue'
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3'
import AppLayout from '@/components/AppLayout.vue'

const props = defineProps({
    workspace: Object,
    courses: Array,
    totalCourses: Number,
    totalFiles: Number,
    isOwner: Boolean,
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
// The link goes into the class WhatsApp group. Pre-written so the rep does
// not have to compose the pitch; the "no account" line is what gets a
// classmate to tap.
const whatsAppUrl = computed(() => 'https://wa.me/?text=' + encodeURIComponent(
    `${props.workspace.name} — notes, slides and past papers for the class. No account needed: ${workspaceUrl.value}`
))
function share() {
    window.copyText(workspaceUrl.value).then(() => {
        shareCopied.value = true
        setTimeout(() => { shareCopied.value = false }, 2000)
    }).catch(() => { })
}

// QR code — for the "get the notes here" moment in a room. Generated lazily
// (dynamic import keeps the qrcode lib out of the initial bundle).
const qrDataUrl = ref('')
const qrOpen = ref(false)
const qrError = ref(false)
async function toggleQr() {
    if (qrOpen.value) { qrOpen.value = false; return }
    // Open first, generate second. Awaiting the lazy chunk before showing
    // anything meant the first click looked dead on a slow connection — the
    // exact moment (a full lecture hall) this button exists for.
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

// Course create / edit sheet. editing holds the course being edited, or
// null when the sheet is creating a new course.
const sheet = ref(false)
const editing = ref(null)
const codeField = ref(null)
watch(sheet, (v) => {
    if (v) {
        setTimeout(() => codeField.value?.focus(), 50)
    }
})

const courseForm = useForm({ code: '', title: '' })

// Soft nudge: a course code is normally letters + numbers (PHYS 101). An
// all-digit entry like "123456" is usually a mistake. We warn, never block —
// the controller still accepts it if the user means it.
const codeLooksOff = computed(() => {
    const c = courseForm.code.trim()
    return c !== '' && !/[a-zA-Z]/.test(c)
})

// Soft mirror of the server's per-board uniqueness rule: warn (don't block) if
// the typed code already exists in this board. Ignores the course being edited.
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

// Recovery email form. The nudge collapses to one row once courses exist
// (so it can't out-weigh the course list); it starts open on an empty board
// and whenever there's feedback to show (save confirmation / error).
const recoveryOpen = ref(
    props.totalCourses === 0
    || !!page.props.flash?.recoverySaved
    || !!page.props.errors?.recoveryEmail,
)
const recoveryForm = useForm({ recoveryEmail: '' })
function saveRecoveryEmail() {
    recoveryForm.post('/' + props.workspace.slug + '/recovery-email', {
        preserveScroll: true,
        onSuccess: () => { recoveryForm.reset() },
    })
}

// Owner unlock form. The panel is nothing but a form, so opening it moves focus
// into the field — same as the course sheet. The recovery panel deliberately
// doesn't: it leads with status ("Recovery email is set"), and jumping to the
// input would skip the sentence explaining what the input is for.
const unlockOpen = ref(!!(errors.value?.ownerInput))
const unlockForm = useForm({ ownerInput: '' })
const ownerField = ref(null)
watch(unlockOpen, (v) => {
    if (v) setTimeout(() => ownerField.value?.focus(), 50)
})

// Escape closes the topmost dismissible thing, innermost first. Centralised so
// the two disclosures can be backed out of like the sheet and the QR overlay —
// they were the only things here a keyboard couldn't dismiss.
function onEscape() {
    if (confirmingExit.value) { confirmingExit.value = false; return }
    if (qrOpen.value) { qrOpen.value = false; return }
    if (sheet.value) { sheet.value = false; return }
    if (unlockOpen.value) { unlockOpen.value = false; return }
    if (recoveryOpen.value) { recoveryOpen.value = false }
}
function unlockOwner() {
    unlockForm.post('/' + props.workspace.slug + '/unlock', {
        preserveScroll: true,
        onSuccess: () => { unlockForm.reset() },
    })
}

// Leave owner mode on this device (useful on shared computers). Confirmed first:
// the only way back in is the owner link, and a board owner who never saved it is
// locked out of their own board by one click on a button the width of a word.
const confirmingExit = ref(false)

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
    if (props.storagePct >= 90) return 'text-danger'
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

// Drag-to-reorder (owner only, manual sort only when not searching/filtering)
const draggable = computed(() => props.isOwner && !localSearch.value && localSort.value === 'manual')
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

// Touch- and keyboard-accessible reorder: move a course up or down one slot.
// Drag is a desktop-only enhancement; these buttons work everywhere.
function moveCourse(index, dir) {
    const target = index + dir
    if (target < 0 || target >= dragList.value.length) return
    const reordered = [...dragList.value]
    const [moved] = reordered.splice(index, 1)
    reordered.splice(target, 0, moved)
    dragList.value = reordered
    persistOrder()
}

// Persist the current order. On failure, revert to the server's order so the
// UI never shows an order that wasn't saved (no silent data divergence).
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
    <AppLayout>
        <div class="op mx-auto w-full max-w-3xl flex-1 px-5 pb-10" @keydown.escape.window="onEscape">

            <!-- Sticky identity bar: which board you're in, plus the one action an
                 owner reaches for again and again. Everything else scrolls away. -->
            <header class="op-top mb-4 flex items-center justify-between gap-4">
                <div class="min-w-0">
                    <a href="/start"
                        class="op-kicker group mb-1 inline-flex items-center gap-1.5 text-[11px] font-semibold uppercase text-muted transition hover:text-neon">
                        <svg aria-hidden="true"
                            class="size-3.5 shrink-0 transition-transform duration-200 group-hover:-translate-x-0.5"
                            viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 12H5" />
                            <path d="M12 19l-7-7 7-7" />
                        </svg>
                        All boards
                    </a>
                    <h1 class="op-title truncate text-[1.85rem] font-bold text-ink sm:text-[2.15rem]">{{ workspace.name }}</h1>
                </div>
                <button v-if="isOwner && totalCourses > 0" type="button" @click="openCreate"
                    class="op-press inline-flex h-11 shrink-0 cursor-pointer items-center gap-1 rounded-full bg-neon px-4 text-[13px] font-semibold text-white sm:h-8">
                    <span class="text-base leading-none">+</span> New course
                </button>
            </header>

            <!-- Says what a board holds, and where adding actually happens. The old
                 line invited you to "add what you have" on a page with nothing to add
                 it to — uploads live one level down, inside a course. It also stated
                 the product model only in the empty state, which a visitor to a
                 populated board never sees. -->
            <p class="mb-5 text-[13px] text-muted">
                <template v-if="totalCourses > 0">Notes, slides and past papers from your classmates. Open a course to read or add files.</template>
                <template v-else>Your board's courses live here — add one to get started.</template>
            </p>

            <!-- Share and QR are occasional; owner mode is a state, not an action.
                 None of them earn a place in the pinned bar. -->
            <div v-if="totalCourses > 0 || isOwner" class="flex flex-wrap items-center gap-2" :class="isOwner && totalCourses > 0 && totalFiles === 0 ? 'mb-2' : 'mb-7'">
                <!-- Share leads nowhere on an empty board, so it appears once there's a course to find. -->
                <button v-if="totalCourses > 0" type="button" @click="share"
                    class="op-press inline-flex h-11 flex-1 cursor-pointer items-center justify-center gap-1.5 whitespace-nowrap rounded-full border border-sky/50 bg-surface px-4 text-[13px] font-semibold text-muted sm:h-8 sm:flex-none">
                    <svg aria-hidden="true" class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor"
                        stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M8 11a3 3 0 0 0 4.5.4l2.6-2.6a3 3 0 1 0-4.2-4.2l-1 1" />
                        <path d="M12 9a3 3 0 0 0-4.5-.4L4.9 11.2a3 3 0 1 0 4.2 4.2l1-1" />
                    </svg>
                    <span v-if="!shareCopied">Share board</span>
                    <span v-else>Link copied ✓</span>
                </button>
                <a v-if="totalCourses > 0" :href="whatsAppUrl" target="_blank" rel="noopener noreferrer"
                    class="op-press inline-flex h-11 flex-1 cursor-pointer items-center justify-center gap-1.5 whitespace-nowrap rounded-full border border-sky/50 bg-surface px-4 text-[13px] font-semibold text-muted sm:h-8 sm:flex-none">
                    <svg aria-hidden="true" class="size-4" viewBox="0 0 256 256" fill="currentColor"><path d="M187.3,68.7A83.3,83.3,0,0,0,128,44a84,84,0,0,0-72.9,125.9L44.6,204.7a8,8,0,0,0,9.8,9.8l35.2-10.5A84,84,0,0,0,212,128,83.3,83.3,0,0,0,187.3,68.7ZM128,196a67.6,67.6,0,0,1-34.6-9.5,8,8,0,0,0-6.4-.8L64.4,192.3l6.7-22.6a8,8,0,0,0-.8-6.4A68,68,0,1,1,128,196Zm37.6-49.3-14.3-8.2a8,8,0,0,0-8.7.5l-8,6a2,2,0,0,1-2.2.2,52.3,52.3,0,0,1-21.6-21.6,2,2,0,0,1,.2-2.2l6-8a8,8,0,0,0,.5-8.7L109.3,90.4A8,8,0,0,0,102.4,86a26.2,26.2,0,0,0-24.7,20.2,60.1,60.1,0,0,0,72.1,72.1A26.2,26.2,0,0,0,170,153.6,8,8,0,0,0,165.6,146.7Z"/></svg>
                    <span>WhatsApp</span>
                </a>
                <!-- QR for the "get the notes here" moment in a lecture hall. -->
                <button v-if="totalCourses > 0" type="button" @click="toggleQr"
                    :aria-expanded="qrOpen" aria-label="Show QR code for this board"
                    class="op-press inline-flex h-11 flex-1 cursor-pointer items-center justify-center gap-1.5 whitespace-nowrap rounded-full border border-sky/50 bg-surface px-4 text-[13px] font-semibold text-muted sm:h-8 sm:flex-none">
                    <svg aria-hidden="true" class="size-4" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M3 3h5v5H3V3zm1.5 1.5v2h2v-2h-2zM12 3h5v5h-5V3zm1.5 1.5v2h2v-2h-2zM3 12h5v5H3v-5zm1.5 1.5v2h2v-2h-2zM12 12h2v2h-2v-2zm3 0h2v2h-2v-2zm-3 3h2v2h-2v-2zm3 0h2v2h-2v-2z" />
                    </svg>
                    <span>QR code</span>
                </button>
                <!-- Owner-mode status (quiet — it's a state, not an action) + the
                     button that ends it, grouped so "Lock" has context. -->
                <!-- Status is borderless; only the button is a pill. Both used to be
                     rounded-full with the same border, so a non-interactive status
                     wrapper looked like a control and put two concentric borders 4px
                     apart around the thing that actually was one. -->
                <span v-if="isOwner"
                    class="inline-flex items-center gap-2 text-[12px] font-medium text-muted sm:ml-auto">
                    <span class="flex items-center gap-1.5">
                        <span class="size-1.5 rounded-full bg-neon" aria-hidden="true"></span>
                        Owner mode
                    </span>
                    <!-- "Exit", not "Lock". Beside the words "Owner mode" this reads
                         as "exit owner mode"; "Lock" beside a board reads as "make
                         this board locked", which is the opposite of what it does and
                         the misread that costs you your own access. The padlock icon
                         reinforced that wrong reading, so it's an exit arrow now. The
                         meaning can't live in a title tooltip — touch never sees it. -->
                    <button type="button" @click="confirmingExit = true" aria-label="Exit owner mode on this device"
                        class="op-press inline-flex min-h-11 cursor-pointer items-center gap-1 rounded-full border border-sky/50 bg-surface px-2.5 py-1 text-[12px] font-semibold text-ink sm:min-h-0">
                        <svg aria-hidden="true" class="size-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor"
                            stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M8 4H5.5A1.5 1.5 0 0 0 4 5.5v9A1.5 1.5 0 0 0 5.5 16H8" />
                            <path d="M12.5 13 16 10l-3.5-3" />
                            <path d="M16 10H8.5" />
                        </svg>
                        Exit
                    </button>
                </span>
            </div>

            <!-- Flash: course updated (edit redirects here) -->
            <div v-if="flash.created" class="op-toast mb-5 text-sm font-medium text-ink">
                <p>{{ flash.created }}</p>
            </div>

            <!-- Empty state -->
            <!-- Activation, not decoration: a board shared before it has a file is
                 a board a classmate opens once. Owners only, and only in the
                 gap between "has a course" and "has a file". -->
            <p v-if="isOwner && totalCourses > 0 && totalFiles === 0" class="mb-7 text-[13px] text-muted">
                Add a file before you share — a board that opens empty rarely gets opened twice.
            </p>

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
                <!-- Search + sort -->
                <h2 class="mb-2 text-[11px] font-semibold uppercase tracking-[0.08em] text-muted">Courses</h2>
                <div v-if="totalCourses > 3" class="mb-4 flex flex-col gap-2 sm:flex-row">
                    <input type="search" v-model="localSearch" :placeholder="`Search ${totalCourses} courses…`"
                        aria-label="Search courses"
                        class="box-border h-11 w-full min-w-0 appearance-none rounded-xl border border-sky bg-surface px-3.5 text-[14px] font-medium leading-none text-ink shadow-sm placeholder:font-normal placeholder:text-muted focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20 sm:flex-1">
                    <div class="relative">
                        <select v-model="localSort" aria-label="Sort courses"
                            class="box-border h-11 w-full appearance-none rounded-xl border border-sky bg-surface pl-3.5 pr-10 text-[14px] font-medium leading-none text-ink shadow-sm focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20 sm:w-auto">
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

                <!-- No results -->
                <p v-if="courses.length === 0" class="op-card px-6 py-8 text-center text-[14px] text-muted">
                    No courses match "<span class="font-semibold text-ink">{{ localSearch }}</span>".
                </p>

                <!-- Course list -->
                <!-- One card with hairline-separated rows, not a stack of floating
                     cards: less chrome per item, and the list scans as one column.
                     Hover tint comes from .op-row, so no per-card lift or shadow. -->
                <div v-else class="op-card overflow-hidden">
                    <div v-for="(course, idx) in ((draggable && courses.length > 1) ? dragList : courses)"
                        :key="course.id" :draggable="draggable && courses.length > 1"
                        @dragstart="draggable && courses.length > 1 && onDragStart($event, course.id)"
                        @dragover="draggable && courses.length > 1 && onDragOver($event, course.id)"
                        @drop="draggable && courses.length > 1 && onDrop()"
                        class="op-row group flex items-start gap-3 sm:gap-4">

                        <!-- Reorder controls (owner + manual sort + 2+ courses).
                             Up/down buttons work on touch & keyboard; the drag
                             handle is a desktop-only enhancement on top. -->
                        <div v-if="draggable && courses.length > 1" class="-my-1 flex shrink-0 flex-col items-center">
                            <button type="button" @click.stop.prevent="moveCourse(idx, -1)" :disabled="idx === 0"
                                :aria-label="`Move ${course.code} up`"
                                class="flex h-11 w-9 items-center justify-center rounded text-muted transition enabled:cursor-pointer enabled:hover:bg-sky/40 enabled:hover:text-neon disabled:opacity-30 sm:size-6">
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
                                class="flex h-11 w-9 items-center justify-center rounded text-muted transition enabled:cursor-pointer enabled:hover:bg-sky/40 enabled:hover:text-neon disabled:opacity-30 sm:size-6">
                                <svg class="size-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 6l4 4 4-4" />
                                </svg>
                            </button>
                        </div>

                        <Link :href="courseUrl(course.slug)" class="min-w-0 flex-1" @click.stop>
                            <div class="flex items-center gap-2">
                                <p
                                    class="teal-accent flex min-w-0 items-center gap-1.5 text-[15px] font-bold tracking-tight text-teal">
                                    <span class="truncate">{{ course.code }}</span>
                                    <svg aria-hidden="true"
                                        class="size-3.5 shrink-0 text-muted/50 transition group-hover:translate-x-0.5 group-hover:text-neon"
                                        viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M8 6l4 4-4 4" />
                                    </svg>
                                </p>
                                <!-- Sits next to the code, not flung right: the count
                                     belongs to the course, and across a wide row
                                     ml-auto left a dead gap between the two. Form
                                     matches the operator's count badge; the hue does
                                     not — red there means "needs attention", and a
                                     file count is neutral news. -->
                                <span v-if="course.new_count > 0"
                                    class="shrink-0 rounded-full bg-neon/10 px-2 py-0.5 text-[11px] font-semibold text-neon"
                                    :title="`${course.new_count} added since you last opened this course`">
                                    {{ course.new_count }} new
                                </span>
                                <span v-if="course.materials_count > 0"
                                    class="file-count-chip shrink-0 rounded-full bg-teal/10 px-2 py-0.5 text-[11px] font-semibold tabular-nums text-teal">
                                    {{ plural(course.materials_count, 'file') }}
                                </span>
                                <span v-else
                                    class="shrink-0 rounded-full border border-dashed border-muted/40 px-2 py-0.5 text-[11px] font-semibold text-muted">
                                    No files yet
                                </span>
                            </div>
                            <p class="mt-0.5 text-[13px] text-muted">{{ course.title }}</p>
                            <p v-if="course.materials_max_created_at" class="mt-1 flex items-center gap-1 text-[12px] text-muted">
                                <svg aria-hidden="true" class="size-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" />
                                </svg>
                                Updated {{ timeAgo(course.materials_max_created_at) }}
                            </p>
                        </Link>

                        <button v-if="isOwner" type="button" @click.stop.prevent="openEdit(course)"
                            :aria-label="`Edit ${course.code}`"
                            class="mt-0.5 flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-md text-muted transition hover:bg-sky/40 hover:text-neon focus:opacity-100 sm:size-auto sm:p-1.5 sm:opacity-0 sm:group-hover:opacity-100">
                            <svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor"
                                stroke-width="1.7">
                                <path d="M13.5 3.5l3 3L7 16l-4 1 1-4 9.5-9.5z" stroke-linecap="round"
                                    stroke-linejoin="round" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Reorder hint — only meaningful with 2+ courses to reorder -->
                <p v-if="draggable && courses.length > 1" class="mt-3 text-[13px] text-muted">
                    Use the arrows (or drag) to reorder — saved automatically.
                </p>
            </template>

            <!-- Owner: create-course slide-in panel -->
            <template v-if="isOwner">
                <div v-if="sheet" class="fixed inset-0 z-40" role="dialog" aria-modal="true"
                    :aria-label="editing ? 'Edit course' : 'New course'">
                    <!-- Black, not ink: --color-ink is near-white in dark mode, so an
                         ink scrim brightened the page to a mid-grey lighter than the
                         dialog itself. A scrim must always darken. -->
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
                            Add a class — like PHYS 101 or CS 250 — so its materials have a home.
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
                                    class="w-full rounded-xl border border-sky bg-base px-3.5 py-2.5 text-[15px] text-ink shadow-inner placeholder:text-muted focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20">
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
                                    class="w-full rounded-xl border border-sky bg-base px-3.5 py-2.5 text-[15px] text-ink shadow-inner placeholder:text-muted focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20">
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

                <!-- Recovery email nudge. Prominent only on an empty board where
                     nothing competes; once courses exist it collapses to a one-row
                     disclosure (same idiom as "Manage this board") so it doesn't
                     out-weigh the course list. -->
                <div v-if="recoveryAvailable" class="op-card mt-8 overflow-hidden">
                    <button v-if="totalCourses > 0" type="button" @click="recoveryOpen = !recoveryOpen"
                        class="flex w-full cursor-pointer items-center justify-between gap-3 px-5 py-3 text-left transition hover:bg-sky/30"
                        :aria-expanded="recoveryOpen" aria-controls="recoveryPanel">
                        <span class="flex flex-col">
                            <span class="text-[13px] font-semibold text-ink">
                                {{ needsRecoveryEmail ? 'No recovery email set' : 'Recovery email is set' }}
                            </span>
                            <!-- Stays visible when open. Hiding it only for the panel
                                 to restate the same thing at greater length left a
                                 gap between the heading and its own explanation. -->
                            <span class="text-[12px] text-muted">
                                {{ needsRecoveryEmail
                                    ? 'Add one so we can send your owner link back if you lose it.'
                                    : 'Lose your owner link and we\'ll email a fresh one.' }}
                            </span>
                        </span>
                        <svg aria-hidden="true" class="size-4 shrink-0 text-muted transition-transform"
                             :class="recoveryOpen ? 'rotate-180' : ''"
                             viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M6 8l4 4 4-4" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                    <div v-if="recoveryOpen" id="recoveryPanel" class="px-5 pb-4" :class="totalCourses === 0 ? 'pt-4' : 'pt-1'">
                        <p v-if="flash.recoverySaved" role="status"
                           class="teal-accent mb-3 flex items-center gap-1.5 rounded-lg bg-teal/10 px-3 py-2 text-[13px] font-semibold text-teal">
                            <svg aria-hidden="true" class="size-4 shrink-0" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10.5l4 4 8-9"/></svg>
                            {{ flash.recoverySaved }}
                        </p>
                        <!-- Only when there is no disclosure button above it — that
                             one renders only once the board has courses. With the
                             button present, this was a second, longer copy of the
                             summary line it already shows. -->
                        <template v-if="needsRecoveryEmail && totalCourses === 0">
                            <p class="text-[13px] font-semibold text-ink">No recovery email set</p>
                            <p class="mt-1 text-[12px] text-muted">
                                Add a recovery email so we can send your owner link back if you lose it.
                            </p>
                        </template>
                        <template v-else>
                            <div class="flex flex-wrap items-center gap-2">
                                <p v-if="totalCourses === 0" class="text-[13px] font-semibold text-ink">Recovery email is set</p>
                                <span v-if="currentRecoveryEmail"
                                    class="inline-flex rounded-full border border-sky bg-base px-2.5 py-0.5 text-[11px] font-medium text-muted">
                                    {{ currentRecoveryEmail }}
                                </span>
                            </div>
                            <p class="mt-1 text-[12px] text-muted">
                                Lose your owner link and we'll email a fresh one. Anyone with that inbox can control this
                                board.
                            </p>
                            <Link :href="recoveryUrl()"
                                class="mt-2 inline-flex items-center gap-1 text-[12px] font-semibold text-neon transition hover:underline">
                                Recover owner link
                                <svg aria-hidden="true" class="size-3 shrink-0" viewBox="0 0 20 20" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M8 6l4 4-4 4" />
                                </svg>
                            </Link>
                        </template>
                        <form @submit.prevent="saveRecoveryEmail" class="mt-3 flex flex-col gap-2 sm:flex-row">
                            <input type="email" v-model="recoveryForm.recoveryEmail" aria-label="Recovery email"
                                class="w-full min-w-0 rounded-xl border border-sky bg-base px-3.5 py-2.5 text-[13px] text-ink shadow-inner focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20 sm:flex-1">
                            <button type="submit" :disabled="recoveryForm.processing"
                                class="op-press w-full min-h-11 shrink-0 cursor-pointer rounded-full bg-neon px-5 text-[13px] font-semibold text-white disabled:opacity-60 sm:w-auto">
                                Save
                            </button>
                        </form>
                        <span v-if="errors.recoveryEmail" role="alert" class="mt-2 block text-[12px] text-danger">{{
                            errors.recoveryEmail[0]
                            }}</span>
                        <p v-if="needsRecoveryEmail" class="mt-2 text-[11px] text-muted">Only used to recover this board
                            — never shared.</p>
                        <!-- Visible, not a placeholder. "leave blank to remove" lived
                             in the placeholder, so the only instructions for deleting
                             your recovery email vanished the moment you typed — and
                             were never there at all for anyone who didn't read it
                             first. A destructive path needs standing text. -->
                        <p v-else class="mt-2 text-[11px] text-muted">Saving an empty field removes the current recovery
                            email.</p>
                    </div>
                </div>
            </template>

            <!-- QR overlay — scan-to-open for a room full of students. Lives outside
                 the owner block on purpose: the button that opens it is shown to
                 everyone with a course to share, so gating the dialog on isOwner
                 made the button silently do nothing for visitors. -->
            <!-- Exit owner mode confirm. The action is one click, instant, and the
                 only way back is the owner link — which the /start receipt screen
                 exists precisely because people lose. So the dialog's job is not
                 "are you sure" but "here is what you'll need to get back in". -->
            <div v-if="confirmingExit" class="fixed inset-0 z-50 flex items-end justify-center sm:items-center"
                 role="dialog" aria-modal="true" aria-label="Exit owner mode">
                <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" @click="confirmingExit = false"></div>
                <div class="relative max-h-[90vh] w-full max-w-md overflow-y-auto rounded-t-2xl bg-surface px-6 pb-[max(1.5rem,env(safe-area-inset-bottom))] pt-3 shadow-xl sm:rounded-2xl sm:pt-6">
                    <div class="mx-auto mb-3 h-1 w-10 rounded-full bg-muted/30 sm:hidden"></div>
                    <button type="button" @click="confirmingExit = false" aria-label="Close"
                            class="op-press absolute right-3 top-3 hidden size-9 cursor-pointer items-center justify-center rounded-full text-muted transition hover:bg-danger/10 hover:text-danger sm:flex">
                        <svg aria-hidden="true" class="size-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round">
                            <path d="M5 5l10 10M15 5L5 15" />
                        </svg>
                    </button>
                    <h2 class="pr-10 text-[15px] font-semibold text-ink">Exit owner mode on this device?</h2>
                    <p class="mt-2 text-[13px] leading-relaxed text-muted">
                        The board stays exactly as it is &mdash; this only signs you out here.
                        To manage it again you&rsquo;ll need your owner link, so make sure you still have it.
                    </p>
                    <div class="mt-5 flex items-center justify-end gap-2">
                        <button type="button" @click="confirmingExit = false"
                                class="op-press inline-flex min-h-11 cursor-pointer items-center rounded-full px-4 text-[14px] font-semibold text-muted transition hover:bg-sky/30 hover:text-ink">Stay in owner mode</button>
                        <button type="button" @click="lockBoard"
                                class="op-press inline-flex min-h-11 cursor-pointer items-center rounded-full bg-neon px-5 text-[14px] font-semibold text-white">
                            Exit
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="qrOpen" class="fixed inset-0 z-40 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-label="Board QR code">
                <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" @click="qrOpen = false"></div>
                <div class="relative flex w-full max-w-xs flex-col items-center rounded-2xl bg-surface px-6 pb-6 pt-11 shadow-xl">
                    <button type="button" @click="qrOpen = false" aria-label="Close"
                        class="op-press absolute right-3 top-3 flex size-11 cursor-pointer items-center justify-center rounded-full text-muted transition hover:bg-danger/10 hover:text-danger sm:size-9">
                        <svg aria-hidden="true" class="size-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round">
                            <path d="M5 5l10 10M15 5L5 15" />
                        </svg>
                    </button>
                    <img v-if="qrDataUrl" :src="qrDataUrl" alt="QR code for this board" class="size-52 rounded-xl bg-white p-2 shadow-sm" />
                    <!-- Same footprint as the image so the dialog doesn't jump when
                         it lands. On failure the link below still works, so the modal
                         degrades to "share this" rather than leaving a dead square. -->
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

            <!-- Non-owner: unlock panel -->
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
                    <div v-if="unlockOpen" id="unlockPanel" class="border-t border-sky/60 px-4 pb-4 pt-3.5">
                        <label for="ownerInput" class="block text-[13px] font-semibold text-ink">
                            Owner secret or link
                            <span class="font-normal text-muted">— either works</span>
                        </label>
                        <form @submit.prevent="unlockOwner" class="mt-2 flex flex-col gap-2 sm:flex-row">
                            <input id="ownerInput" ref="ownerField" type="text" v-model="unlockForm.ownerInput" autocomplete="off"
                                autocapitalize="off" spellcheck="false" placeholder="Paste your owner link or secret"
                                aria-describedby="ownerInputNote"
                                :aria-invalid="!!errors.ownerInput"
                                class="min-w-0 flex-1 rounded-xl border border-sky bg-base px-3.5 py-2.5 text-[13px] text-ink shadow-inner placeholder:text-muted focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20">
                            <button type="submit" :disabled="unlockForm.processing"
                                class="op-press w-full min-h-11 shrink-0 cursor-pointer rounded-full bg-neon px-5 text-[13px] font-semibold text-white disabled:opacity-60 sm:w-auto">
                                {{ unlockForm.processing ? 'Unlocking…' : 'Unlock' }}
                            </button>
                        </form>
                        <!-- The server's own message: this key carries both "wrong
                             secret" and "rate limited", and a hardcoded string told
                             a throttled user their secret was wrong — so they'd
                             retry, and stay throttled. -->
                        <p v-if="errors.ownerInput" role="alert"
                           class="mt-2 text-[12px] font-medium text-danger">{{ errors.ownerInput[0] }}</p>
                        <p id="ownerInputNote" class="mt-2 text-[11px] text-muted">
                            Goes only to this board · SlipNote never asks for it by email.
                        </p>
                    </div>
                </div>
            </template>

            <!-- Capacity is the owner's responsibility, so they always see it.
                 Anyone else only once it's close enough to bite — uploads are open
                 to any visitor, and a near-full board is why theirs would fail.
                 Below that, "0.2 of 500 MB" is chrome that looks like a signal. -->
            <div v-if="storageUsed > 0 && (isOwner || storagePct >= 75)"
                class="mt-8 flex items-center justify-center gap-2">
                <div class="h-1 w-32 overflow-hidden rounded-full bg-ink/15">
                    <div class="h-full rounded-full transition-all"
                         :class="storagePct >= 90 ? 'bg-danger' : storagePct >= 75 ? 'bg-neon' : 'bg-muted'"
                         :style="{ width: storagePct + '%' }"></div>
                </div>
                <p class="text-[11px]" :class="storageTone">
                    {{ mbUsed }} of {{ mbCap }} MB used<template v-if="storagePct >= 90"> · delete old files to free space</template>
                </p>
            </div>
        </div>
    </AppLayout>
</template>
