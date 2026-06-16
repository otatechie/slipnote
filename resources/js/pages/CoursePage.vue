<script setup>
import { computed, ref, watch, nextTick } from 'vue'
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3'
import AppLayout from '@/components/AppLayout.vue'

const props = defineProps({
    workspace: Object,
    course: Object,
    isOwner: Boolean,
    storageFull: Boolean,
    passphraseNeeded: Boolean,
    sections: Object,   // { notes: 'Notes', slides: 'Slides', ... }
    sectionCounts: Object,
    materials: Array,
    resultCount: Number,
    search: String,
    sort: String,
    activeSection: String,
})

const page = usePage()
const flash = computed(() => page.props.flash)
const errors = computed(() => page.props.errors)

// Search / sort / section filter — server-side via router
const localSearch = ref(props.search)
const localSort = ref(props.sort)
const localSection = ref(props.activeSection)

let searchTimer = null
watch(localSearch, (val) => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => visitFilter(), 250)
})
watch([localSort, localSection], () => visitFilter())

function visitFilter() {
    router.visit(window.location.pathname, {
        data: {
            search: localSearch.value,
            sort: localSort.value,
            section: localSection.value,
        },
        preserveState: true,
        replace: true,
    })
}

function toggleSection(key) {
    localSection.value = localSection.value === key ? '' : key
}

// Grouped materials by section
const materialsBySection = computed(() => {
    const map = {}
    for (const key of Object.keys(props.sections)) {
        map[key] = props.materials.filter(m => m.section === key)
    }
    return map
})

const isFiltered = computed(() => localSearch.value.trim() !== '' || localSection.value !== '')

// Hard per-file limit, mirrors the server's `max:10240` (KB) upload rule.
const MAX_FILE_BYTES = 10 * 1024 * 1024

function fileSize(bytes) {
    if (bytes < 1024) return bytes + ' B'
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(0) + ' KB'
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

const hasOversizedFile = computed(() =>
    uploadForm.files.some(f => f.size > MAX_FILE_BYTES))

// Upload form
const uploadOpen = ref(false)
const uploadForm = useForm({
    section: 'notes',
    title: '',
    uploaderName: '',
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
if (window.location.hash === '#add-file' || (errors.value && Object.keys(errors.value).length > 0)) {
    uploadOpen.value = true
}

window.addEventListener('hashchange', () => {
    if (window.location.hash === '#add-file') uploadOpen.value = true
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

// Delete: POST with _method=DELETE using a plain form submit for simplicity
// (Inertia router.delete works but needs confirmation modal — keep it native)
function confirmDelete(e) {
    const form = e.target.closest('form')
    const name = form.dataset.name
    if (!confirm('Remove "' + name + '"? This can\'t be undone.')) {
        e.preventDefault()
    }
}

function courseListUrl() {
    return '/' + props.workspace.slug
}

// Bulk delete (owner only)
const selected = ref([])
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

function bulkDelete() {
    if (!selectedCount.value) return
    const count = selectedCount.value
    const label = count === 1 ? '1 file' : `${count} files`
    if (!confirm(`Remove ${label}? This can't be undone.`)) return

    bulkForm.transform(() => ({ ids: selected.value }))
        .delete('/' + props.workspace.slug + '/c/' + props.course.slug + '/materials', {
            preserveScroll: true,
            onSuccess: () => { selected.value = [] },
        })
}

// Clear selection when materials list changes (after delete / filter)
watch(() => props.materials, () => { selected.value = [] })
</script>

<template>
    <Head :title="course.code + ' · ' + workspace.name" />
    <AppLayout>
        <div class="mx-auto w-full max-w-3xl flex-1 px-5 pb-10 pt-10">

            <header class="mb-7">
                <Link :href="courseListUrl()"
                      class="group mb-1.5 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.08em] text-neon transition hover:underline">
                    <svg aria-hidden="true"
                         class="size-5 shrink-0 transition-transform duration-200 group-hover:-translate-x-1"
                         viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M13 9a1 1 0 0 1-1-1V4.707a.707.707 0 0 0-1.207-.5l-6.94 6.94a1.207 1.207 0 0 0 0 1.707l6.94 6.94a.707.707 0 0 0 1.207-.5V16a1 1 0 0 1 1-1h2a1 1 0 0 0 1-1v-4a1 1 0 0 0-1-1z"/>
                        <path d="M20 9v6"/>
                    </svg>
                    <span>{{ workspace.name }}</span>
                </Link>
                <h1 class="text-3xl font-bold tracking-tight text-ink">{{ course.code }}</h1>
                <p class="mt-1.5 text-[15px] text-muted">{{ course.title }}</p>
            </header>

            <!-- Owner mode — quiet inline note, not a full banner -->
            <p v-if="isOwner" class="mb-5 inline-flex items-center gap-1.5 text-[12px] font-medium text-muted">
                <span class="inline-block size-1.5 rounded-full bg-neon" aria-hidden="true"></span>
                Owner mode — you can remove any file.
            </p>

            <!-- Course created — confirm + nudge first upload -->
            <div v-if="flash.created"
                 class="mb-5 rounded-lg border border-neon/40 bg-neon/10 px-4 py-3 text-sm font-medium text-ink">
                {{ flash.created }} Add your first files below, then share the board with your class.
            </div>

            <!-- Upload receipt -->
            <div v-if="flash.uploaded"
                 class="mb-5 rounded-lg border border-sky bg-sky/40 px-4 py-3 text-sm font-medium text-teal">
                {{ flash.uploaded }}
            </div>

            <!-- Report receipt -->
            <div v-if="flash.reported"
                 class="mb-5 rounded-lg border border-sky bg-sky/40 px-4 py-3 text-sm font-medium text-teal">
                {{ flash.reported }}
            </div>

            <!-- Sticky filter bar — only useful once the course has files -->
            <div v-if="resultCount > 0" class="sticky top-0 z-30 -mx-5 mb-5 space-y-3 bg-base/95 px-5 py-3 backdrop-blur">
                <div class="flex flex-col gap-2 sm:flex-row">
                    <input type="search" v-model="localSearch"
                           :placeholder="`Search ${resultCount} ${resultCount === 1 ? 'file' : 'files'} by name…`"
                           aria-label="Search files"
                           class="box-border h-12 w-full flex-1 appearance-none rounded-lg border border-sky bg-surface px-3.5 text-[15px] font-medium leading-none text-ink shadow-sm placeholder:font-normal placeholder:text-muted focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20">
                    <div class="relative w-full sm:w-auto">
                        <select v-model="localSort" aria-label="Sort files"
                                class="box-border h-12 w-full appearance-none rounded-lg border border-sky bg-surface pl-3.5 pr-10 text-[15px] font-medium leading-none text-ink shadow-sm focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20">
                            <option value="newest">Newest first</option>
                            <option value="oldest">Oldest first</option>
                            <option value="az">A–Z</option>
                        </select>
                        <svg class="pointer-events-none absolute right-3.5 top-1/2 size-4 -translate-y-1/2 text-muted" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M6 8l4 4 4-4" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                </div>

                <!-- Section filter pills -->
                <nav aria-label="Filter by section" class="flex flex-wrap gap-1.5">
                    <template v-for="(label, key) in sections" :key="key">
                        <button type="button" @click="toggleSection(key)"
                                :disabled="!sectionCounts[key] && localSection !== key"
                                :aria-pressed="localSection === key ? 'true' : 'false'"
                                class="section-pill inline-flex cursor-pointer items-center gap-1.5 rounded-full px-3 py-1 text-[13px] font-semibold transition"
                                :class="localSection === key
                                    ? 'bg-teal text-white'
                                    : (sectionCounts[key] ? 'bg-sky text-teal hover:brightness-95' : 'cursor-not-allowed bg-surface text-muted')">
                            {{ label }}
                            <span class="rounded-full px-1.5 text-xs tabular-nums"
                                  :class="localSection === key
                                      ? 'bg-white/25 text-white'
                                      : (sectionCounts[key] ? 'bg-base text-teal' : 'bg-sky/40 text-muted')">
                                {{ sectionCounts[key] ?? 0 }}
                            </span>
                        </button>
                    </template>
                    <button v-if="localSection !== ''" type="button" @click="localSection = ''"
                            class="inline-flex cursor-pointer items-center rounded-full px-3 py-1 text-[13px] font-semibold text-muted underline-offset-2 transition hover:text-teal hover:underline">
                        Clear filter
                    </button>
                </nav>
            </div>

            <!-- Empty filtered state -->
            <p v-if="isFiltered && materials.length === 0"
               class="rounded-2xl border border-sky/30 bg-surface px-6 py-8 text-center text-[15px] text-muted shadow-sm">
                <template v-if="localSearch.trim() !== ''">
                    No files match "<span class="font-semibold text-ink">{{ localSearch }}</span>"<template v-if="localSection !== ''"> in <span class="font-semibold text-ink">{{ sections[localSection] }}</span></template>.
                </template>
                <template v-else>
                    No files in <span class="font-semibold text-ink">{{ sections[localSection] }}</span> yet.
                </template>
            </p>

            <!-- Whole-course empty state: one friendly card instead of four
                 empty section stubs. Only when nothing's uploaded and no
                 filter is active. -->
            <div v-if="resultCount === 0 && !isFiltered"
                 class="rounded-2xl border border-sky/30 bg-surface px-6 py-12 text-center shadow-sm">
                <p class="text-[16px] font-semibold text-ink">No files yet</p>
                <p class="mx-auto mt-1.5 max-w-sm text-[14px] text-muted">
                    Be the first to add notes, slides, or past papers for
                    <span class="font-semibold text-ink">{{ course.code }}</span>.
                </p>
                <button type="button" @click="uploadOpen = true"
                        class="mt-5 inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-neon px-5 py-3 text-[14px] font-bold text-white shadow-sm transition hover:brightness-125">
                    <span class="text-lg leading-none">+</span> Add the first file
                </button>
            </div>

            <!-- Bulk action bar (owner, selection active) -->
            <div v-if="isOwner && selectedCount > 0"
                 class="sticky top-[116px] z-20 mb-4 flex flex-col gap-2 rounded-xl border border-sky bg-surface px-4 py-2.5 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:gap-3">
                <div class="flex items-center gap-3">
                    <input type="checkbox"
                           :checked="allSelected"
                           :indeterminate="selectedCount > 0 && !allSelected"
                           @change="toggleSelectAll"
                           class="size-4 cursor-pointer accent-teal">
                    <span class="text-xs font-medium text-muted">
                        {{ selectedCount }} {{ selectedCount === 1 ? 'file' : 'files' }} selected
                    </span>
                </div>
                <div class="flex items-center gap-4">
                    <button type="button" @click="selected = []"
                            class="cursor-pointer text-[13px] font-medium text-muted hover:text-ink">
                        Cancel
                    </button>
                    <button type="button" @click="bulkDelete" :disabled="bulkForm.processing"
                            class="cursor-pointer rounded-lg bg-red-600/90 px-3.5 py-1.5 text-[13px] font-semibold text-white transition hover:bg-red-600 disabled:opacity-60">
                        Delete {{ selectedCount === 1 ? '1 file' : selectedCount + ' files' }}
                    </button>
                </div>
            </div>

            <!-- Materials grouped by section. Empty sections aren't shown —
                 the filter chips above already report which sections are empty,
                 so per-section "nothing here" stubs would just duplicate that. -->
            <template v-for="(label, key) in sections" :key="key">
                <template v-if="materialsBySection[key]?.length > 0">
                    <section :id="'sec-' + key" class="mb-4 scroll-mt-20 rounded-2xl border border-sky/30 bg-surface px-4 py-5 shadow-md ring-1 ring-black/3 sm:px-6">
                        <h2 class="mb-3.5 text-xs font-bold uppercase tracking-[0.06em] text-muted">
                            {{ label }}
                        </h2>
                        <div v-for="material in materialsBySection[key]" :key="material.id"
                             class="flex items-center justify-between gap-3 border-b border-sky/60 py-3 first:pt-0 last:border-0 last:pb-0"
                             :class="isSelected(material.id) ? 'bg-teal/5 -mx-4 px-4 sm:-mx-6 sm:px-6' : ''">
                            <div class="flex min-w-0 items-start gap-3">
                                <!-- Bulk select checkbox (owner only) -->
                                <input v-if="isOwner" type="checkbox"
                                       :checked="isSelected(material.id)"
                                       @change="toggleSelect(material.id)"
                                       :aria-label="`Select ${material.displayName} to delete`"
                                       title="Select to delete"
                                       class="mt-1 size-4 shrink-0 cursor-pointer accent-teal">
                                <span class="mt-0.5 shrink-0 rounded bg-sky/50 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted"
                                      :title="material.fileTypeLabel + ' file'">
                                    {{ material.fileTypeLabel }}
                                </span>
                                <div class="min-w-0">
                                    <a v-if="material.download_url" :href="material.download_url"
                                       class="block truncate text-[15px] font-semibold text-neon hover:underline">
                                        {{ material.displayName }}
                                    </a>
                                    <span v-else class="block truncate text-[15px] font-semibold text-ink">
                                        {{ material.displayName }}
                                    </span>
                                    <div class="mt-0.5 truncate text-[12px] text-muted">
                                        {{ material.uploader_name || 'Anonymous' }} · {{ material.created_at_human }}
                                    </div>
                                </div>
                            </div>
                            <div class="flex shrink-0 items-center gap-1">
                                <template v-if="isOwner && !selectedCount">
                                    <form :action="material.delete_url" method="POST"
                                          :data-name="material.displayName"
                                          @submit="confirmDelete">
                                        <input type="hidden" name="_token" :value="$page.props.csrf_token ?? ''">
                                        <input type="hidden" name="_method" value="DELETE">
                                        <button type="submit" aria-label="Delete file"
                                                class="cursor-pointer rounded-full p-2 text-muted transition hover:bg-red-50 hover:text-red-600">
                                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M3 6h18M8 6V4a1 1 0 011-1h6a1 1 0 011 1v2m2 0v14a1 1 0 01-1 1H7a1 1 0 01-1-1V6"/>
                                            </svg>
                                        </button>
                                    </form>
                                </template>
                                <button v-if="!isOwner && !selectedCount" type="button"
                                        @click="openReport(material)"
                                        aria-label="Report this file"
                                        title="Report this file"
                                        class="flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-full text-muted/60 transition hover:bg-red-50 hover:text-red-600">
                                    <svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M5 17V3.5M5 4h9l-2 3 2 3H5"/>
                                    </svg>
                                </button>
                                <a v-if="!selectedCount && material.download_url" :href="material.download_url"
                                   class="rounded-full bg-neon px-3.5 py-1.5 text-[13px] font-semibold text-white shadow-sm transition hover:brightness-125">
                                    Download
                                </a>
                            </div>
                        </div>
                    </section>
                </template>
            </template>

            <!-- Upload section -->
            <section id="add-file" class="mt-7 scroll-mt-6">
                <!-- Board full -->
                <div v-if="storageFull" class="rounded-2xl border border-red-200 bg-red-50 px-6 py-5 text-center">
                    <p class="text-[14px] font-semibold text-red-700">This board is full</p>
                    <p class="mt-1 text-[13px] text-red-600">
                        Ask the owner to delete old files before new uploads can go up.
                    </p>
                </div>

                <template v-else>
                    <!-- FAB -->
                    <button v-if="!uploadOpen" type="button" @click="uploadOpen = true"
                            aria-label="Add a file"
                            class="fixed bottom-6 right-6 z-20 flex h-14 w-14 cursor-pointer items-center justify-center rounded-full bg-neon text-2xl font-bold text-white shadow-lg transition hover:brightness-125">
                        +
                    </button>

                    <!-- Collapsed button — hidden on an empty course, where the
                         empty-state card's "Add the first file" is the CTA. -->
                    <button v-if="!uploadOpen && resultCount > 0" type="button" @click="uploadOpen = true"
                            class="flex w-full cursor-pointer items-center justify-center gap-2 rounded-2xl border border-dashed border-teal/50 bg-surface px-6 py-4 text-[15px] font-semibold text-neon shadow-sm transition hover:bg-sky/30">
                        <span class="text-lg leading-none">+</span> Add a file
                    </button>

                    <!-- Expanded form -->
                    <div v-if="uploadOpen"
                         class="rounded-2xl border border-dashed border-teal/50 bg-surface px-4 py-5 shadow-sm sm:px-6">
                        <div class="mb-3.5 flex items-center justify-between">
                            <h2 class="text-xs font-bold uppercase tracking-[0.06em] text-muted">Add a file</h2>
                            <button type="button" @click="uploadOpen = false"
                                    class="cursor-pointer text-[13px] font-semibold text-muted hover:text-neon">Close</button>
                        </div>

                        <form @submit.prevent="upload" class="flex flex-col gap-3.5">
                            <!-- Passphrase -->
                            <div v-if="passphraseNeeded">
                                <label for="passphrase" class="mb-1.5 block text-[13px] font-semibold text-ink">Course passphrase</label>
                                <input id="passphrase" type="password" v-model="uploadForm.passphrase"
                                       placeholder="Ask your course rep"
                                       :aria-invalid="!!errors.passphrase"
                                       class="w-full rounded-lg border border-sky/30 bg-base px-3 py-2.5 text-[15px] text-ink placeholder:text-muted focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20">
                                <span v-if="errors.passphrase" role="alert" class="mt-1.5 block text-[13px] text-red-600">{{ errors.passphrase[0] }}</span>
                            </div>

                            <!-- Title — only meaningful for a single file -->
                            <div v-if="uploadForm.files.length <= 1">
                                <label for="utitle" class="mb-0.5 block text-[13px] font-semibold text-ink">Name this file (optional)</label>
                                <p class="mb-1.5 text-[12px] text-muted">Helps classmates find it if the filename isn't clear.</p>
                                <input id="utitle" type="text" v-model="uploadForm.title"
                                       placeholder="e.g. Week 7 quiz solutions"
                                       :aria-invalid="!!errors.title"
                                       class="w-full rounded-lg border border-sky/30 bg-base px-3 py-2.5 text-[15px] text-ink placeholder:text-muted focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20">
                                <span v-if="errors.title" role="alert" class="mt-1.5 block text-[13px] text-red-600">{{ errors.title[0] }}</span>
                            </div>

                            <!-- Section -->
                            <div>
                                <label for="usection" class="mb-0.5 block text-[13px] font-semibold text-ink">Section</label>
                                <p class="mb-1.5 text-[12px] text-muted">What kind of file is this?</p>
                                <select id="usection" v-model="uploadForm.section"
                                        class="w-full rounded-lg border border-sky/30 bg-base px-3 py-2.5 text-[15px] text-ink focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20">
                                    <option v-for="(label, key) in sections" :key="key" :value="key">{{ label }}</option>
                                </select>
                            </div>

                            <!-- Uploader name -->
                            <div>
                                <label for="uploaderName" class="mb-1.5 block text-[13px] font-semibold text-ink">Your name (optional)</label>
                                <input id="uploaderName" type="text" v-model="uploadForm.uploaderName"
                                       placeholder="e.g. Alex"
                                       :aria-invalid="!!errors.uploaderName"
                                       class="w-full rounded-lg border border-sky/30 bg-base px-3 py-2.5 text-[15px] text-ink placeholder:text-muted focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20">
                                <span v-if="errors.uploaderName" role="alert" class="mt-1.5 block text-[13px] text-red-600">{{ errors.uploaderName[0] }}</span>
                            </div>

                            <!-- Files -->
                            <div>
                                <span class="mb-1.5 block text-[13px] font-semibold text-ink">
                                    Files <span class="text-red-500" aria-hidden="true">*</span>
                                </span>
                                <!-- Native input is visually hidden; the label below
                                     drives it so the browser's "No file chosen" text
                                     never contradicts the managed list. -->
                                <input id="ufile" type="file" multiple @change="onFileChange"
                                       :aria-invalid="!!errors.files" class="sr-only">
                                <label for="ufile"
                                       class="inline-flex min-h-11 cursor-pointer items-center gap-1.5 rounded-lg border border-teal/30 bg-surface px-4 py-2.5 text-[14px] font-semibold text-teal transition hover:bg-sky/50">
                                    <svg aria-hidden="true" class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round">
                                        <path d="M10 4v12M4 10h12"/>
                                    </svg>
                                    {{ uploadForm.files.length > 0 ? 'Add more files' : 'Choose files' }}
                                </label>
                                <p class="mt-1.5 text-xs text-muted">PDF, Word, PowerPoint, or image · up to 10&nbsp;MB each · pick several at once</p>
                                <div v-if="uploadForm.files.length > 0" class="mt-2 rounded-lg border border-teal/20 bg-teal/5 px-3 py-2.5">
                                    <p class="mb-1.5 text-[11px] font-semibold uppercase tracking-[0.06em] text-teal/70">
                                        {{ uploadForm.files.length }} {{ uploadForm.files.length === 1 ? 'file' : 'files' }} selected
                                    </p>
                                    <ul class="space-y-0.5">
                                        <li v-for="(f, i) in uploadForm.files" :key="i"
                                            class="flex items-center gap-2 text-[12px] text-ink">
                                            <span class="min-w-0 flex-1 truncate">{{ f.name }}</span>
                                            <span class="shrink-0 tabular-nums"
                                                  :class="f.size > MAX_FILE_BYTES ? 'font-semibold text-red-600' : 'text-muted'">
                                                {{ fileSize(f.size) }}<template v-if="f.size > MAX_FILE_BYTES"> · too big</template>
                                            </span>
                                            <button type="button" @click="removeFile(i)"
                                                    :aria-label="`Remove ${f.name}`"
                                                    class="-my-2 -mr-1 flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-full text-muted transition hover:bg-red-50 hover:text-red-600">
                                                <svg class="size-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round">
                                                    <path d="M4 4l8 8M12 4l-8 8"/>
                                                </svg>
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                                <span v-if="errors.files" role="alert" class="mt-1.5 block text-[13px] text-red-600">{{ errors.files[0] }}</span>
                                <span v-if="errors['files.0']" role="alert" class="mt-1.5 block text-[13px] text-red-600">{{ errors['files.0'] }}</span>
                            </div>

                            <button type="submit" :disabled="uploadForm.processing || uploadForm.files.length === 0 || hasOversizedFile"
                                    class="relative overflow-hidden rounded-lg py-3 text-[15px] font-bold transition
                                           enabled:cursor-pointer enabled:bg-neon enabled:text-white enabled:hover:brightness-125
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
                            <p v-else-if="hasOversizedFile" class="-mt-1 text-center text-[12px] text-red-600">
                                Remove the file over 10&nbsp;MB to upload.
                            </p>
                        </form>
                    </div>
                </template>
            </section>

            <!-- Report modal -->
            <div v-if="reportTarget" class="fixed inset-0 z-50 flex items-end justify-center sm:items-center"
                 role="dialog" aria-modal="true" aria-label="Report file"
                 @keydown.escape.window="reportTarget = null">
                <div class="absolute inset-0 bg-ink/30 backdrop-blur-sm" @click="reportTarget = null"></div>
                <div class="relative max-h-[90vh] w-full max-w-md overflow-y-auto rounded-t-2xl bg-surface px-6 pb-[max(1.5rem,env(safe-area-inset-bottom))] pt-3 shadow-xl sm:rounded-2xl sm:pt-6">
                    <!-- Mobile grab handle -->
                    <div class="mx-auto mb-3 h-1 w-10 rounded-full bg-muted/30 sm:hidden"></div>
                    <h2 class="text-[15px] font-bold text-ink">Report this file</h2>
                    <p class="mt-1 truncate text-[13px] text-muted">{{ reportTarget.displayName }}</p>

                    <fieldset class="mt-4 space-y-1.5">
                        <legend class="sr-only">Reason</legend>
                        <label v-for="r in REPORT_REASONS" :key="r"
                               class="flex min-h-11 cursor-pointer items-center gap-2.5 rounded-lg border px-3 py-2.5 text-[14px] transition"
                               :class="reportReason === r ? 'border-neon bg-neon/5 text-ink' : 'border-sky/40 text-ink/90 hover:bg-sky/30'">
                            <input type="radio" name="report-reason" :value="r" v-model="reportReason"
                                   class="size-4 accent-neon">
                            {{ r }}
                        </label>
                    </fieldset>

                    <textarea v-model="reportNote" rows="2" maxlength="280"
                              placeholder="Add a note (optional)"
                              class="mt-3 w-full resize-none rounded-lg border border-sky bg-base px-3 py-2.5 text-[14px] text-ink shadow-inner placeholder:text-muted focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20"></textarea>

                    <div class="mt-4 flex items-center justify-end gap-2">
                        <button type="button" @click="reportTarget = null"
                                class="inline-flex min-h-11 cursor-pointer items-center rounded-lg px-4 text-[14px] font-semibold text-muted transition hover:bg-sky/30 hover:text-ink">Cancel</button>
                        <button type="button" @click="submitReport" :disabled="!reportReason || reportSubmitting"
                                class="inline-flex min-h-11 cursor-pointer items-center rounded-lg bg-red-600/90 px-5 text-[14px] font-semibold text-white transition hover:bg-red-600 disabled:opacity-50">
                            Report file
                        </button>
                    </div>
                    <p class="mt-3 text-[11px] text-muted/70">Goes to the site operator for review. Files aren't removed automatically.</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
