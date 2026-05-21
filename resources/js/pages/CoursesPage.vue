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
    }).catch(() => {})
}

// Course create sheet
const sheet = ref(false)
const codeField = ref(null)
watch(sheet, (v) => {
    if (v) {
        setTimeout(() => codeField.value?.focus(), 50)
    }
})
function closeSheet(e) {
    if (e.key === 'Escape') sheet.value = false
}

const courseForm = useForm({ code: '', title: '' })
function createCourse() {
    courseForm.post('/' + props.workspace.slug + '/courses', {
        onSuccess: () => { sheet.value = false },
    })
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
</script>

<template>
    <Head :title="workspace.name + ' · Courses'" />
    <AppLayout>
        <div class="mx-auto w-full max-w-3xl flex-1 px-5 pb-10 pt-10"
             @keydown.escape.window="sheet = false">

            <header class="mb-7 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="mb-1.5 text-xs font-semibold uppercase tracking-[0.08em] text-muted">SlipNote</p>
                    <h1 class="text-3xl font-bold tracking-tight text-ink">Courses</h1>
                    <p class="mt-1.5 text-[15px] text-muted">
                        <template v-if="totalCourses > 0">Pick a course to browse and share materials.</template>
                        <template v-else>Course materials for your board, all in one place.</template>
                    </p>
                </div>
                <div class="flex shrink-0 flex-wrap items-center gap-2">
                    <button type="button" @click="share"
                            class="inline-flex shrink-0 cursor-pointer items-center gap-1.5 rounded-lg bg-neon px-4 py-2.5 text-[14px] font-semibold text-white shadow-sm transition hover:brightness-110">
                        <span v-if="!shareCopied">Share with classmates</span>
                        <span v-else>Link copied ✓</span>
                    </button>
                    <button v-if="isOwner && totalCourses > 0" type="button" @click="sheet = true"
                            class="inline-flex shrink-0 cursor-pointer items-center gap-1.5 rounded-lg bg-neon px-4 py-2.5 text-[14px] font-bold text-white shadow-sm transition hover:brightness-125">
                        <span class="text-lg leading-none">+</span> New course
                    </button>
                </div>
            </header>

            <!-- Flash: course created -->
            <div v-if="flash.created"
                 class="mb-5 rounded-lg border border-sky bg-sky/40 px-4 py-3 text-sm font-medium text-teal">
                {{ flash.created }}
            </div>

            <!-- Empty state -->
            <template v-if="totalCourses === 0">
                <div class="rounded-2xl border border-sky/30 bg-surface px-6 py-10 text-center shadow-sm">
                    <p class="text-[15px] font-semibold text-ink">No courses yet</p>
                    <template v-if="isOwner">
                        <p class="mt-1.5 text-[14px] text-muted">Add the first one to get this board started.</p>
                        <button type="button" @click="sheet = true"
                                class="mt-4 inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-neon px-4 py-2.5 text-[14px] font-bold text-white shadow-sm transition hover:brightness-125">
                            <span class="text-lg leading-none">+</span> New course
                        </button>
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
                    <input type="search" v-model="localSearch"
                           :placeholder="`Search ${totalCourses} courses…`"
                           aria-label="Search courses"
                           class="h-11 flex-1 rounded-lg border border-sky/30 bg-surface px-3.5 text-[15px] text-ink shadow-sm placeholder:text-muted focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20">
                    <select v-model="localSort" aria-label="Sort courses"
                            class="h-11 rounded-lg border border-sky/30 bg-surface px-3.5 text-[15px] font-medium text-ink shadow-sm focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20">
                        <option value="active">Most recently active</option>
                        <option value="az">A–Z</option>
                    </select>
                </div>

                <!-- No results -->
                <p v-if="courses.length === 0"
                   class="rounded-2xl border border-sky/30 bg-surface px-6 py-8 text-center text-[15px] text-muted shadow-sm">
                    No courses match "<span class="font-semibold text-ink">{{ localSearch }}</span>".
                </p>

                <!-- Course list -->
                <div v-else class="space-y-2.5">
                    <Link v-for="course in courses" :key="course.id"
                          :href="courseUrl(course.slug)"
                          class="group flex items-center justify-between gap-4 rounded-2xl border border-sky/30 bg-surface px-6 py-4 shadow-sm transition hover:-translate-y-0.5 hover:border-neon hover:shadow-md">
                        <div class="min-w-0">
                            <p class="flex items-center gap-1.5 text-[15px] font-bold tracking-tight text-teal">
                                {{ course.code }}
                                <span aria-hidden="true" class="opacity-0 transition group-hover:translate-x-0.5 group-hover:opacity-100">→</span>
                            </p>
                            <p class="mt-0.5 truncate text-[13px] text-muted">{{ course.title }}</p>
                            <p v-if="course.materials_max_created_at" class="mt-0.5 text-[12px] text-muted/80">
                                Updated {{ timeAgo(course.materials_max_created_at) }}
                            </p>
                        </div>
                        <span v-if="course.materials_count > 0"
                              class="shrink-0 rounded-full bg-sky/30 px-2.5 py-0.5 text-xs font-medium tabular-nums text-muted">
                            {{ plural(course.materials_count, 'file') }}
                        </span>
                        <span v-else
                              class="shrink-0 rounded-full border border-dashed border-muted/50 px-2.5 py-0.5 text-xs font-medium text-muted">
                            No files yet — be the first
                        </span>
                    </Link>
                </div>
            </template>

            <!-- Owner: create-course slide-in panel -->
            <template v-if="isOwner">
                <div v-if="sheet" class="fixed inset-0 z-40" role="dialog" aria-modal="true" aria-label="New course">
                    <div class="absolute inset-0 bg-ink/30 backdrop-blur-sm" @click="sheet = false"></div>
                    <div class="absolute right-0 top-0 flex h-full w-full max-w-md flex-col bg-surface px-6 py-6 shadow-xl
                                transition-transform duration-200"
                         style="transform: translateX(0)">
                        <div class="mb-5 flex items-center justify-between">
                            <h2 class="text-xs font-bold uppercase tracking-[0.06em] text-muted">New course</h2>
                            <button type="button" @click="sheet = false"
                                    class="cursor-pointer text-[13px] font-semibold text-muted hover:text-neon">Close</button>
                        </div>
                        <form @submit.prevent="createCourse" class="flex flex-col gap-3.5">
                            <div>
                                <label for="code" class="mb-1.5 block text-[13px] font-semibold text-ink">Course code</label>
                                <input id="code" type="text" v-model="courseForm.code" placeholder="e.g. PHYS 101"
                                       ref="codeField"
                                       :aria-invalid="!!courseForm.errors.code"
                                       class="w-full rounded-lg border border-sky/30 bg-base px-3 py-2.5 text-[15px] text-ink placeholder:text-muted focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20">
                                <span v-if="courseForm.errors.code" role="alert" class="mt-1.5 block text-[13px] text-red-600">{{ courseForm.errors.code }}</span>
                            </div>
                            <div>
                                <label for="ctitle" class="mb-1.5 block text-[13px] font-semibold text-ink">Title</label>
                                <input id="ctitle" type="text" v-model="courseForm.title" placeholder="e.g. Introductory Physics"
                                       :aria-invalid="!!courseForm.errors.title"
                                       class="w-full rounded-lg border border-sky/30 bg-base px-3 py-2.5 text-[15px] text-ink placeholder:text-muted focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20">
                                <span v-if="courseForm.errors.title" role="alert" class="mt-1.5 block text-[13px] text-red-600">{{ courseForm.errors.title }}</span>
                            </div>
                            <button type="submit" :disabled="courseForm.processing"
                                    class="mt-1 cursor-pointer rounded-lg bg-neon py-3 text-[15px] font-bold text-white transition hover:brightness-125 disabled:opacity-60">
                                Create course
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Recovery email nudge -->
                <div v-if="recoveryAvailable"
                     class="mt-8 rounded-xl border px-5 py-4"
                     :class="needsRecoveryEmail ? 'border-neon/40 bg-neon/5' : 'border-sky bg-surface/50'">
                    <p v-if="flash.recoverySaved" class="mb-2 text-[13px] font-semibold text-teal">{{ flash.recoverySaved }}</p>
                    <template v-if="needsRecoveryEmail">
                        <p class="text-[13px] font-semibold text-ink">No recovery email set</p>
                        <p class="mt-1 text-[12px] text-muted">
                            If you lose your owner link, this board can't be recovered.
                            Add an email and we can send the link back to it.
                        </p>
                    </template>
                    <template v-else>
                        <p class="text-[13px] font-semibold text-ink">Recovery email is set</p>
                        <p class="mt-1 text-[12px] text-muted">
                            If you lose the owner link, request it from the board's recovery page
                            and we'll email a fresh link (the old one stops working). Anyone with
                            that inbox can control this board.
                        </p>
                    </template>
                    <form @submit.prevent="saveRecoveryEmail" class="mt-3 flex flex-col gap-2 sm:flex-row">
                        <input type="email" v-model="recoveryForm.recoveryEmail"
                               aria-label="Recovery email"
                               :placeholder="needsRecoveryEmail ? 'you@example.com' : 'new email, or blank to remove'"
                               class="h-9 flex-1 rounded-lg border border-sky/30 bg-base px-3 text-[13px] text-ink placeholder:text-muted focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20">
                        <button type="submit" :disabled="recoveryForm.processing"
                                class="h-9 shrink-0 cursor-pointer rounded-lg bg-neon px-4 text-[13px] font-semibold text-white transition hover:brightness-125 disabled:opacity-60">
                            Save
                        </button>
                    </form>
                    <span v-if="errors.recoveryEmail" role="alert" class="mt-2 block text-[12px] text-red-600">{{ errors.recoveryEmail[0] }}</span>
                </div>
            </template>

            <!-- Non-owner: unlock panel -->
            <template v-if="!isOwner">
                <div class="mt-10 overflow-hidden rounded-xl border border-sky transition-colors"
                     :class="unlockOpen ? 'bg-sky/30' : ''">
                    <button type="button" @click="unlockOpen = !unlockOpen"
                            class="flex w-full cursor-pointer items-center justify-between gap-3 px-4 py-3 text-left transition hover:bg-sky/30"
                            :aria-expanded="unlockOpen">
                        <span class="text-[13px] font-semibold text-ink">Manage this board</span>
                        <span class="text-[12px] text-muted">{{ unlockOpen ? 'Close' : "I'm the owner" }}</span>
                    </button>
                    <div v-if="unlockOpen" class="border-t border-sky/60 px-4 pb-4 pt-3.5">
                        <label for="ownerInput" class="block text-[12px] text-muted">
                            Paste the owner secret or link you saved when you created it.
                        </label>
                        <form @submit.prevent="unlockOwner" class="mt-2 flex gap-1.5">
                            <input id="ownerInput" type="text" v-model="unlockForm.ownerInput"
                                   autocomplete="off" autocapitalize="off" spellcheck="false"
                                   placeholder="Owner secret or link"
                                   class="h-9 flex-1 rounded-lg border border-sky/30 bg-base px-3 text-[13px] text-ink placeholder:text-muted shadow-sm focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20">
                            <button type="submit" :disabled="unlockForm.processing"
                                    class="h-9 shrink-0 cursor-pointer rounded-lg bg-neon px-4 text-[13px] font-semibold text-white transition hover:brightness-125 disabled:opacity-60">
                                Unlock
                            </button>
                        </form>
                        <p v-if="errors.ownerInput" role="alert" class="mt-2 text-[12px] font-medium text-muted">Not a match — check you copied the whole thing.</p>
                        <p class="mt-2 text-[11px] text-muted/60">
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
