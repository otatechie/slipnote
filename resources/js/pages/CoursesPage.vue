<script setup>
import { computed, ref, watch } from 'vue'
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3'
import AppLayout from '@/components/AppLayout.vue'

const props = defineProps({
    workspace: Object,
    courses: Array,
    totalCourses: Number,
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
function share() {
    window.copyText(workspaceUrl.value).then(() => {
        shareCopied.value = true
        setTimeout(() => { shareCopied.value = false }, 2000)
    }).catch(() => { })
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

// Recovery email form
const recoveryForm = useForm({ recoveryEmail: '' })
function saveRecoveryEmail() {
    recoveryForm.post('/' + props.workspace.slug + '/recovery-email', {
        preserveScroll: true,
        onSuccess: () => { recoveryForm.reset() },
    })
}

// Owner unlock form
const unlockOpen = ref(!!(errors.value?.ownerInput))
const unlockForm = useForm({ ownerInput: '' })
function unlockOwner() {
    unlockForm.post('/' + props.workspace.slug + '/unlock', {
        preserveScroll: true,
        onSuccess: () => { unlockForm.reset() },
    })
}

// Storage display
const mbUsed = computed(() => {
    const v = props.storageUsed
    return (v / 1048576).toFixed(v >= 10485760 ? 0 : 1)
})
const mbCap = computed(() => Math.round(props.storageCap / 1048576))
const storageTone = computed(() => {
    if (props.storagePct >= 90) return 'text-red-600'
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
        <div class="mx-auto w-full max-w-3xl flex-1 px-5 pb-10 pt-10" @keydown.escape.window="sheet = false">

            <header class="mb-7 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <a href="/start"
                        class="group mb-1.5 inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-[0.08em] text-muted transition hover:text-neon">
                        <svg aria-hidden="true"
                            class="size-4 shrink-0 transition-transform duration-200 group-hover:-translate-y-0.5"
                            viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8" />
                            <path d="M3 10a2 2 0 0 1 .709-1.528l7-6a2 2 0 0 1 2.582 0l7 6A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                        </svg>
                        SlipNote
                    </a>
                    <h1 class="text-3xl font-bold tracking-tight text-ink">{{ workspace.name }}</h1>
                    <p class="mt-1.5 text-[15px] text-muted">
                        <template v-if="totalCourses > 0">Pick a course to browse and share materials.</template>
                        <template v-else>Your board's courses live here — add one to get started.</template>
                    </p>
                </div>
                <div class="flex w-full shrink-0 flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap sm:items-center">
                    <button type="button" @click="share" :class="[
                        'inline-flex w-full shrink-0 cursor-pointer items-center justify-center gap-1.5 rounded-lg border px-4 py-2.5 text-[14px] font-semibold shadow-sm transition sm:w-auto sm:justify-start',
                        totalCourses === 0
                            ? 'border-sky bg-surface text-muted hover:bg-sky/40'
                            : 'border-teal/30 bg-surface text-teal hover:bg-sky/40',
                    ]">
                        <svg aria-hidden="true" class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor"
                            stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M8 11a3 3 0 0 0 4.5.4l2.6-2.6a3 3 0 1 0-4.2-4.2l-1 1" />
                            <path d="M12 9a3 3 0 0 0-4.5-.4L4.9 11.2a3 3 0 1 0 4.2 4.2l1-1" />
                        </svg>
                        <span v-if="!shareCopied">Share board</span>
                        <span v-else>Link copied ✓</span>
                    </button>
                    <button v-if="isOwner && totalCourses > 0" type="button" @click="openCreate"
                        class="inline-flex w-full shrink-0 cursor-pointer items-center justify-center gap-1.5 rounded-lg bg-neon px-4 py-2.5 text-[14px] font-bold text-white shadow-sm transition hover:brightness-125 sm:w-auto">
                        <span class="text-lg leading-none">+</span> New course
                    </button>
                </div>
            </header>

            <!-- Flash: course updated (edit redirects here) -->
            <div v-if="flash.created"
                class="mb-5 rounded-lg border border-sky bg-sky/40 px-4 py-3 text-sm font-medium text-teal">
                {{ flash.created }}
            </div>

            <!-- Empty state -->
            <template v-if="totalCourses === 0">
                <div class="mt-8 rounded-2xl border border-sky/30 bg-surface px-6 py-14 text-center shadow-sm sm:mt-16">
                    <p class="text-[15px] font-semibold text-ink">No courses yet</p>
                    <template v-if="isOwner">
                        <p class="mx-auto mt-1.5 max-w-sm text-[14px] text-muted">
                            A course is one class — like <span class="font-semibold text-ink">PHYS 101</span> or
                            <span class="font-semibold text-ink">CS 250</span>. Add one and
                            classmates can drop their notes, slides, and past papers into it.
                        </p>
                        <button type="button" @click="openCreate"
                            class="mt-4 inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-neon px-4 py-2.5 text-[14px] font-bold text-white shadow-sm transition hover:brightness-125">
                            <span class="text-lg leading-none">+</span> New course
                        </button>
                        <p class="mt-3 text-[12px] text-muted/80">Add a course first, then share the board with your
                            class.</p>
                    </template>
                    <template v-else>
                        <p class="mx-auto mt-1.5 max-w-sm text-[14px] text-muted">
                            Courses are added by whoever set this board up. If that's you,
                            open it with your <span class="font-semibold text-ink">owner link</span>
                            (the one shown when you created it). Otherwise, check back soon.
                        </p>
                    </template>
                </div>
            </template>

            <template v-else>
                <!-- Search + sort -->
                <div v-if="totalCourses > 3" class="mb-4 flex flex-col gap-2 sm:flex-row">
                    <input type="search" v-model="localSearch" :placeholder="`Search ${totalCourses} courses…`"
                        aria-label="Search courses"
                        class="box-border h-12 flex-1 appearance-none rounded-lg border border-sky bg-surface px-3.5 text-[15px] font-medium leading-none text-ink shadow-sm placeholder:font-normal placeholder:text-muted focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20">
                    <div class="relative">
                        <select v-model="localSort" aria-label="Sort courses"
                            class="box-border h-12 w-full appearance-none rounded-lg border border-sky bg-surface pl-3.5 pr-10 text-[15px] font-medium leading-none text-ink shadow-sm focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20 sm:w-auto">
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
                <p v-if="courses.length === 0"
                    class="rounded-2xl border border-sky/30 bg-surface px-6 py-8 text-center text-[15px] text-muted shadow-sm">
                    No courses match "<span class="font-semibold text-ink">{{ localSearch }}</span>".
                </p>

                <!-- Course list -->
                <div v-else class="space-y-2.5">
                    <div v-for="(course, idx) in ((draggable && courses.length > 1) ? dragList : courses)"
                        :key="course.id" :draggable="draggable && courses.length > 1"
                        @dragstart="draggable && courses.length > 1 && onDragStart($event, course.id)"
                        @dragover="draggable && courses.length > 1 && onDragOver($event, course.id)"
                        @drop="draggable && courses.length > 1 && onDrop()"
                        class="group flex items-start gap-3 rounded-2xl border border-sky/30 bg-surface px-4 py-4 shadow-sm transition hover:-translate-y-0.5 hover:border-neon hover:shadow-md sm:gap-4 sm:px-6">

                        <!-- Reorder controls (owner + manual sort + 2+ courses).
                             Up/down buttons work on touch & keyboard; the drag
                             handle is a desktop-only enhancement on top. -->
                        <div v-if="draggable && courses.length > 1" class="-my-1 flex shrink-0 flex-col items-center">
                            <button type="button" @click.stop.prevent="moveCourse(idx, -1)" :disabled="idx === 0"
                                :aria-label="`Move ${course.code} up`"
                                class="flex size-6 items-center justify-center rounded text-muted transition enabled:cursor-pointer enabled:hover:bg-sky/40 enabled:hover:text-neon disabled:opacity-30">
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
                                class="flex size-6 items-center justify-center rounded text-muted transition enabled:cursor-pointer enabled:hover:bg-sky/40 enabled:hover:text-neon disabled:opacity-30">
                                <svg class="size-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 6l4 4 4-4" />
                                </svg>
                            </button>
                        </div>

                        <Link :href="courseUrl(course.slug)" class="min-w-0 flex-1" @click.stop>
                            <div class="flex items-center gap-2">
                                <p
                                    class="flex min-w-0 items-center gap-1.5 text-[15px] font-bold tracking-tight text-teal">
                                    <span class="truncate">{{ course.code }}</span>
                                    <span aria-hidden="true"
                                        class="shrink-0 text-muted/50 transition group-hover:translate-x-0.5 group-hover:text-neon">›</span>
                                </p>
                                <span v-if="course.materials_count > 0"
                                    class="file-count-chip ml-auto shrink-0 rounded-full border border-teal/30 bg-teal/10 px-2.5 py-0.5 text-xs font-medium tabular-nums text-teal">
                                    {{ plural(course.materials_count, 'file') }}
                                </span>
                                <span v-else
                                    class="ml-auto shrink-0 rounded-full border border-dashed border-muted/50 px-2.5 py-0.5 text-xs font-medium text-muted">
                                    No files yet
                                </span>
                            </div>
                            <p class="mt-0.5 text-[13px] text-muted">{{ course.title }}</p>
                            <p v-if="course.materials_max_created_at" class="mt-1 flex items-center gap-1 text-[12px] text-muted/80">
                                <svg aria-hidden="true" class="size-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" />
                                </svg>
                                Updated {{ timeAgo(course.materials_max_created_at) }}
                            </p>
                        </Link>

                        <button v-if="isOwner" type="button" @click.stop.prevent="openEdit(course)"
                            :aria-label="`Edit ${course.code}`"
                            class="mt-0.5 shrink-0 cursor-pointer rounded-md p-1.5 text-muted/60 transition hover:bg-sky/40 hover:text-neon focus:opacity-100 sm:opacity-0 sm:group-hover:opacity-100">
                            <svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor"
                                stroke-width="1.7">
                                <path d="M13.5 3.5l3 3L7 16l-4 1 1-4 9.5-9.5z" stroke-linecap="round"
                                    stroke-linejoin="round" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Reorder hint — only meaningful with 2+ courses to reorder -->
                <p v-if="draggable && courses.length > 1" class="mt-2 text-center text-[12px] text-muted/60">
                    Use the arrows (or drag) to reorder — saved automatically
                </p>
            </template>

            <!-- Owner: create-course slide-in panel -->
            <template v-if="isOwner">
                <div v-if="sheet" class="fixed inset-0 z-40" role="dialog" aria-modal="true"
                    :aria-label="editing ? 'Edit course' : 'New course'">
                    <div class="absolute inset-0 bg-ink/30 backdrop-blur-sm" @click="sheet = false"></div>
                    <div class="absolute right-0 top-0 flex h-full w-full max-w-md flex-col bg-surface px-5 py-6 shadow-xl
                                transition-transform duration-200 sm:px-6" style="transform: translateX(0)">
                        <div class="mb-1.5 flex items-start justify-between">
                            <h2 class="text-xs font-bold uppercase tracking-[0.06em] text-muted">{{ editing ? 'Edit course' : 'New course' }}</h2>
                            <button type="button" @click="sheet = false" aria-label="Close"
                                class="-mr-1.5 -mt-1.5 flex size-9 shrink-0 cursor-pointer items-center justify-center rounded-lg text-muted transition hover:bg-sky/40 hover:text-ink">
                                <svg aria-hidden="true" class="size-5" viewBox="0 0 20 20" fill="none"
                                    stroke="currentColor" stroke-width="1.75" stroke-linecap="round">
                                    <path d="M5 5l10 10M15 5L5 15" />
                                </svg>
                            </button>
                        </div>
                        <p v-if="!editing" class="mb-5 text-[13px] leading-relaxed text-muted">
                            Add a class so people can upload to it — like PHYS 101 or CS 250.
                        </p>
                        <div v-else class="mb-5"></div>
                        <form @submit.prevent="submitCourse" class="flex flex-col gap-3.5">
                            <div>
                                <label for="code" class="mb-1 block text-[13px] font-semibold text-ink">
                                    Course code <span class="text-red-500" aria-hidden="true">*</span>
                                </label>
                                <p class="mb-1.5 text-[12px] text-muted">The short code your class uses.</p>
                                <input id="code" type="text" v-model="courseForm.code" placeholder="e.g. PHYS 101"
                                    ref="codeField" required :aria-invalid="!!courseForm.errors.code"
                                    class="w-full rounded-lg border border-sky/30 bg-base px-3 py-2.5 text-[15px] text-ink placeholder:text-muted focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20">
                                <span v-if="courseForm.errors.code" role="alert"
                                    class="mt-1.5 block text-[13px] text-red-600">{{ courseForm.errors.code }}</span>
                                <span v-else-if="codeDuplicate" class="mt-1.5 block text-[13px] text-amber-600">This
                                    board already has a course with this code.</span>
                                <span v-else-if="codeLooksOff" class="mt-1.5 block text-[13px] text-amber-600">A course
                                    code usually includes letters, like “PHYS 101”.</span>
                            </div>
                            <div>
                                <label for="ctitle" class="mb-1 block text-[13px] font-semibold text-ink">
                                    Title <span class="text-red-500" aria-hidden="true">*</span>
                                </label>
                                <p class="mb-1.5 text-[12px] text-muted">The full course name.</p>
                                <input id="ctitle" type="text" v-model="courseForm.title"
                                    placeholder="e.g. Introductory Physics" required
                                    :aria-invalid="!!courseForm.errors.title"
                                    class="w-full rounded-lg border border-sky/30 bg-base px-3 py-2.5 text-[15px] text-ink placeholder:text-muted focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20">
                                <span v-if="courseForm.errors.title" role="alert"
                                    class="mt-1.5 block text-[13px] text-red-600">{{ courseForm.errors.title }}</span>
                            </div>
                            <button type="submit" :disabled="courseForm.processing"
                                class="mt-1 cursor-pointer rounded-lg bg-neon py-3 text-[15px] font-bold text-white transition hover:brightness-125 disabled:opacity-60">
                                {{ editing ? 'Save changes' : 'Create course' }}
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Recovery email nudge -->
                <div v-if="recoveryAvailable" class="mt-8 rounded-xl border px-5 py-4"
                    :class="needsRecoveryEmail ? 'border-neon/40 bg-neon/5' : 'border-sky bg-surface/50'">
                    <p v-if="flash.recoverySaved" role="status"
                       class="mb-3 flex items-center gap-1.5 rounded-lg bg-teal/10 px-3 py-2 text-[13px] font-semibold text-teal">
                        <svg aria-hidden="true" class="size-4 shrink-0" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10.5l4 4 8-9"/></svg>
                        {{ flash.recoverySaved }}
                    </p>
                    <template v-if="needsRecoveryEmail">
                        <p class="text-[13px] font-semibold text-ink">No recovery email set</p>
                        <p class="mt-1 text-[12px] text-muted">
                            Add a recovery email so we can send your owner link back if you lose it.
                        </p>
                    </template>
                    <template v-else>
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-[13px] font-semibold text-ink">Recovery email is set</p>
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
                            <span aria-hidden="true">›</span>
                        </Link>
                    </template>
                    <form @submit.prevent="saveRecoveryEmail" class="mt-3 flex flex-col gap-2 sm:flex-row">
                        <input type="email" v-model="recoveryForm.recoveryEmail" aria-label="Recovery email"
                            :placeholder="needsRecoveryEmail ? 'you@example.com' : 'New email, or leave blank to remove'"
                            class="h-11 flex-1 rounded-lg border border-sky/30 bg-base px-3 text-[13px] text-ink placeholder:text-muted focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20 sm:h-9">
                        <button type="submit" :disabled="recoveryForm.processing"
                            class="h-11 shrink-0 cursor-pointer rounded-lg bg-neon px-4 text-[13px] font-semibold text-white transition hover:brightness-125 disabled:opacity-60 sm:h-9">
                            Save
                        </button>
                    </form>
                    <span v-if="errors.recoveryEmail" role="alert" class="mt-2 block text-[12px] text-red-600">{{
                        errors.recoveryEmail[0]
                        }}</span>
                    <p v-if="needsRecoveryEmail" class="mt-2 text-[11px] text-muted/80">Only used to recover this board
                        — never shared.</p>
                </div>
            </template>

            <!-- Non-owner: unlock panel -->
            <template v-if="!isOwner">
                <div class="mt-10 overflow-hidden rounded-xl border border-sky transition-colors"
                    :class="unlockOpen ? 'bg-sky/30' : ''">
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
                        <form @submit.prevent="unlockOwner" class="mt-2 flex gap-1.5">
                            <input id="ownerInput" type="text" v-model="unlockForm.ownerInput" autocomplete="off"
                                autocapitalize="off" spellcheck="false" placeholder="owner-… or https://…"
                                aria-describedby="ownerInputNote"
                                :aria-invalid="!!errors.ownerInput"
                                class="h-11 flex-1 rounded-lg border border-sky/30 bg-base px-3 text-[13px] text-ink placeholder:text-muted shadow-sm focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20 sm:h-9">
                            <button type="submit" :disabled="unlockForm.processing"
                                class="h-11 shrink-0 cursor-pointer rounded-lg bg-neon px-4 text-[13px] font-semibold text-white transition hover:brightness-125 disabled:opacity-60 sm:h-9">
                                {{ unlockForm.processing ? 'Unlocking…' : 'Unlock' }}
                            </button>
                        </form>
                        <p v-if="errors.ownerInput" role="alert" class="mt-2 text-[12px] font-medium text-muted">Not a
                            match — check you copied the whole thing.</p>
                        <p id="ownerInputNote" class="mt-2 text-[11px] text-muted/60">
                            Goes only to this board · SlipNote never asks for it by email.
                        </p>
                    </div>
                </div>
            </template>

            <!-- Storage indicator -->
            <p v-if="storageUsed > 0" class="mx-auto mt-8 max-w-sm text-center text-[11px]" :class="storageTone">
                Storage: {{ mbUsed }} of {{ mbCap }} MB used ({{ storagePct }}%)
                <template v-if="storagePct >= 90"> · delete old files to free space</template>
            </p>
        </div>
    </AppLayout>
</template>
