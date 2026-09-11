<script setup>
import { computed, ref, watch, nextTick } from 'vue'
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3'
import AppLayout from '@/components/AppLayout.vue'

const props = defineProps({
    workspace: Object,
    course: Object,
    isOwner: Boolean,
    storageFull: Boolean,
    storageUsed: Number,
    storageCap: Number,
    storagePct: Number,
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

// "Download all" — zips a whole section (the exam-time need). Plain GET link.
function sectionZipUrl(key) {
    return '/' + props.workspace.slug + '/c/' + props.course.slug + '/download/' + key
}

const isFiltered = computed(() => localSearch.value.trim() !== '' || localSection.value !== '')

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
    if (props.storagePct >= 90) return 'text-danger'
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
                    onSuccess: () => { selected.value = [] },
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
    <AppLayout>
        <div class="op mx-auto flex w-full max-w-3xl flex-1 flex-col px-5 pb-10">

            <!-- Deliberately NOT .op-top (sticky) like the board page: the filter
                 bar below already claims the sticky slot at top-0, and the bulk bar
                 offsets from its height. On a file list, search that stays reachable
                 beats a title that does — so this keeps the look, not the pinning. -->
            <header class="mb-4 flex items-center justify-between gap-4 border-b border-sky/60 pt-8 pb-3">
                <div class="min-w-0">
                    <Link :href="courseListUrl()"
                          class="op-kicker group mb-1 inline-flex items-center gap-1.5 text-[11px] font-semibold uppercase text-muted transition hover:text-neon">
                        <svg aria-hidden="true"
                             class="size-3.5 shrink-0 transition-transform duration-200 group-hover:-translate-x-0.5"
                             viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 12H5" />
                            <path d="M12 19l-7-7 7-7" />
                        </svg>
                        <span class="truncate">{{ workspace.name }}</span>
                    </Link>
                    <h1 class="op-title truncate text-[1.85rem] font-bold text-ink sm:text-[2.15rem]">{{ course.code }}</h1>
                </div>
                <!-- Desktop only — on mobile the FAB below covers this from the thumb
                     zone. Hidden when the board is full (can't upload) and on an empty
                     course, where the empty-state card's CTA is the sole invitation. -->
                <button v-if="!storageFull && resultCount > 0" type="button" @click="uploadOpen = true"
                        class="op-press hidden h-11 shrink-0 cursor-pointer items-center gap-1 rounded-full bg-neon px-4 text-[13px] font-semibold text-white sm:inline-flex sm:h-8">
                    <span class="text-base leading-none">+</span> Add file
                </button>
            </header>

            <p class="mb-5 text-[13px] text-muted">{{ course.title }}</p>

            <!-- Owner mode — quiet inline note, not a full banner -->
            <div v-if="isOwner" class="mb-5">
                <p class="inline-flex items-center gap-1.5 text-[12px] font-medium text-muted">
                    <span class="inline-block size-1.5 rounded-full bg-neon" aria-hidden="true"></span>
                    Owner mode — you can remove any file.
                </p>
                <!-- Storage meter — makes the soft per-board cap legible before uploads bounce. -->
                <div v-if="storageUsed > 0" class="mt-1.5 flex items-center gap-2">
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

            <!-- Course created — confirmation only; the empty-state card owns the next-step CTA. -->
            <div v-if="flash.created"
                 class="mb-5 rounded-lg border border-neon/40 bg-neon/10 px-4 py-3 text-sm font-medium text-ink">
                {{ flash.created }}
            </div>

            <!-- Upload receipt. manageUrl is only set for a single-file upload
                 (one token, one file) — the uploader's only way to delete
                 their own upload later, since there's no login to recover it. -->
            <div v-if="flash.uploaded"
                 class="teal-accent mb-5 rounded-xl border border-sky bg-sky/40 px-4 py-3 text-sm font-medium text-teal">
                {{ flash.uploaded }}
                <template v-if="flash.manageUrl">
                    <form ref="undoForm" :action="flash.manageUrl" method="POST" class="inline">
                        <input type="hidden" name="_token" :value="$page.props.csrf_token ?? ''">
                        <input type="hidden" name="_method" value="DELETE">
                        <button type="button" @click="confirmingUndo = true"
                                class="op-press ml-1 inline-flex min-h-11 cursor-pointer items-center font-semibold underline hover:no-underline sm:min-h-0">Undo upload</button>
                    </form>
                    <span class="mt-1 block text-xs font-semibold text-danger">Only works right now — refresh or leave, and it's gone.</span>
                </template>
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
            <div v-if="resultCount > 0" class="z-30 -mx-5 mb-5 space-y-3 bg-base/95 px-5 py-3 backdrop-blur sm:sticky sm:top-0">
                <!-- Searching 2 files you can already see is chrome that outweighs
                     the list it filters. Same threshold the board page uses for
                     courses. The section pills stay — they say what kinds of file
                     a course holds, which is useful at any count. -->
                <div v-if="resultCount > 3" class="flex flex-col gap-2 sm:flex-row">
                    <input type="text" inputmode="search" v-model="localSearch"
                           :placeholder="`Search ${resultCount} ${resultCount === 1 ? 'file' : 'files'} by name…`"
                           aria-label="Search files"
                           class="box-border w-full min-w-0 flex-1 appearance-none rounded-xl border border-sky bg-surface px-3.5 py-2.5 text-[14px] font-medium text-ink shadow-sm placeholder:font-normal placeholder:text-muted focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20">
                    <div class="relative w-full sm:w-auto">
                        <select v-model="localSort" aria-label="Sort files"
                                class="box-border w-full appearance-none rounded-xl border border-sky bg-surface py-2.5 pl-3.5 pr-10 text-[14px] font-medium text-ink shadow-sm focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20">
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
                                class="section-pill op-press inline-flex min-h-11 cursor-pointer items-center gap-1.5 rounded-full px-3.5 py-1 text-[13px] font-semibold transition sm:min-h-0 sm:px-3"
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
                            class="op-press inline-flex min-h-11 cursor-pointer items-center rounded-full px-3 py-1 text-[13px] font-semibold text-muted underline-offset-2 transition hover:text-teal hover:underline sm:min-h-0">
                        Clear filter
                    </button>
                </nav>
            </div>

            <!-- Empty filtered state -->
            <p v-if="isFiltered && materials.length === 0"
               class="op-card px-6 py-8 text-center text-[14px] text-muted">
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
            <!-- Not my-auto: this is a flex child of a flex-1 column, so auto margins
                 centred it in every remaining pixel of viewport. With a header, a
                 subtitle, an owner note and a flash banner stacked above, that opened
                 ~300px of void between the message and the card it belongs to. -->
            <div v-if="resultCount === 0 && !isFiltered"
                 class="op-card px-6 py-14 text-center">
                <p class="text-[16px] font-semibold tracking-tight text-ink">No files yet</p>
                <p class="mx-auto mt-1.5 max-w-sm text-[14px] leading-relaxed text-muted">
                    Be the first to add notes, slides, or past papers for
                    <span class="font-semibold text-ink">{{ course.code }}</span>.
                </p>
                <button type="button" @click="uploadOpen = true"
                        class="op-press mt-5 inline-flex min-h-11 cursor-pointer items-center gap-1 rounded-full bg-neon px-5 text-[14px] font-semibold text-white">
                    <span class="text-lg leading-none">+</span> Add the first file
                </button>
            </div>

            <!-- Bulk action bar (owner, selection active) -->
            <!-- top-2 on phones (filter bar is static there); below the sticky
                 filter bar's desktop height from sm up. -->
            <div v-if="isOwner && selectedCount > 0"
                 class="sticky top-2 z-20 mb-4 flex flex-col gap-2 rounded-xl border border-sky bg-surface px-4 py-2.5 shadow-sm sm:top-29 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
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
                    <button type="button" @click="selected = []"
                            class="op-press inline-flex min-h-11 cursor-pointer items-center text-[13px] font-medium text-muted hover:text-ink sm:min-h-0">
                        Cancel
                    </button>
                    <button type="button" @click="askBulkDelete" :disabled="bulkForm.processing"
                            class="btn-danger op-press inline-flex min-h-11 cursor-pointer items-center rounded-full px-4 text-[13px] font-semibold disabled:opacity-60 sm:min-h-0 sm:py-1.5">
                        Delete {{ selectedCount === 1 ? '1 file' : selectedCount + ' files' }}
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
                         ring-black/3, which was invisible on a dark background. -->
                    <section :id="'sec-' + key" class="op-card mb-4 scroll-mt-20 overflow-hidden">
                        <div class="flex items-center justify-between gap-3 border-b border-sky/60 px-4 py-3 sm:px-5">
                            <h2 class="text-[11px] font-semibold uppercase tracking-[0.08em] text-muted">
                                {{ label }}
                            </h2>
                            <a v-if="sectionCounts[key] > 1" :href="sectionZipUrl(key)"
                               class="op-press inline-flex min-h-11 shrink-0 items-center gap-1 text-[12px] font-semibold text-neon hover:underline sm:min-h-0">
                                <svg aria-hidden="true" class="size-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M10 3v9m0 0 3.5-3.5M10 12 6.5 8.5M4 15h12" />
                                </svg>
                                Download all ({{ sectionCounts[key] }})
                            </a>
                        </div>
                        <div v-for="material in materialsBySection[key]" :key="material.id"
                             class="op-row flex items-center justify-between gap-2 sm:gap-3"
                             :class="isSelected(material.id) ? 'bg-teal/5' : ''">
                            <div class="flex min-w-0 items-start gap-2.5 sm:gap-3">
                                <!-- Bulk select checkbox (owner only). The label carries
                                     a 44px tap area on phones — the filename beside it
                                     is a link, so it can't be folded in the way the
                                     select-all count can. -->
                                <!-- h-11 w-9: the tap area stays 44px tall, but 44px
                                     wide around a 16px box was 28px of pure padding
                                     taken straight off the filename. -->
                                <label v-if="isOwner"
                                       class="-my-2 flex h-11 w-9 shrink-0 cursor-pointer items-center justify-center sm:my-0 sm:mt-1 sm:size-4">
                                    <input type="checkbox"
                                           :checked="isSelected(material.id)"
                                           @change="toggleSelect(material.id)"
                                           :aria-label="`Select ${material.displayName} to delete`"
                                           title="Select to delete"
                                           class="size-4 shrink-0 cursor-pointer accent-teal">
                                </label>
                                <!-- Type badge hidden on phones — the row width goes to the
                                     filename; the extension is usually in the name anyway. -->
                                <span class="mt-0.5 hidden shrink-0 rounded bg-sky/50 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted sm:inline"
                                      :title="material.fileTypeLabel + ' file'">
                                    {{ material.fileTypeLabel }}
                                </span>
                                <div class="min-w-0">
                                    <!-- Title opens the preview (look inside — the library mental model);
                                         the ↗ icon signals it opens in a new tab so it isn't a silent
                                         trapdoor. Non-previewable types (docx/ppt) have no preview, so the
                                         title downloads and shows no icon. The pill stays the download action. -->
                                    <a v-if="material.preview_url" :href="material.preview_url"
                                       target="_blank" rel="noopener"
                                       :title="`Preview ${material.displayName} (opens in a new tab)`"
                                       class="flex items-center gap-1 text-[15px] font-semibold text-ink underline decoration-muted/40 underline-offset-4 hover:decoration-ink/40">
                                        <span class="truncate">{{ material.displayName }}</span>
                                        <svg aria-hidden="true" class="size-3.5 shrink-0 text-muted" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M8 5H5v10h10v-3M12 4h4v4M16 4l-7 7" />
                                        </svg>
                                    </a>
                                    <a v-else-if="material.download_url" :href="material.download_url"
                                       class="block truncate text-[15px] font-semibold text-ink underline decoration-muted/40 underline-offset-4 hover:decoration-ink/40">
                                        {{ material.displayName }}
                                    </a>
                                    <span v-else class="block truncate text-[15px] font-semibold text-ink">
                                        {{ material.displayName }}
                                    </span>
                                    <div class="mt-0.5 truncate text-[12px] text-muted">
                                        {{ material.uploader_name || 'Anonymous' }} · {{ material.created_at_human }}<template v-if="material.file_size"> · {{ fileSize(material.file_size) }}</template>
                                    </div>
                                </div>
                            </div>
                            <div class="flex shrink-0 items-center gap-1">
                                <template v-if="isOwner && !selectedCount">
                                    <form :action="material.delete_url" method="POST"
                                          :data-name="material.displayName"
                                          @submit="askDeleteFile">
                                        <input type="hidden" name="_token" :value="$page.props.csrf_token ?? ''">
                                        <input type="hidden" name="_method" value="DELETE">
                                        <button type="submit" aria-label="Delete file" title="Delete file"
                                                class="flex size-10 shrink-0 cursor-pointer items-center justify-center rounded-full text-muted transition hover:bg-danger/10 hover:text-danger sm:size-9">
                                            <!-- Redrawn on the app's 20x20 / 1.7-stroke
                                                 grid — it was the last icon left at
                                                 viewBox 24, and its 1px corner arcs
                                                 muddied at 16px. Drawn to ~72% of the
                                                 viewBox to match the optical size of
                                                 the old one; the first redraw filled
                                                 only 60% and read as shrunken next to
                                                 the download arrow. -->
                                            <svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M2.8 5.4h14.4" />
                                                <path d="M7.4 5.4V3.6a1.1 1.1 0 0 1 1.1-1.1h3a1.1 1.1 0 0 1 1.1 1.1v1.8" />
                                                <path d="M4.7 5.4l.75 10.5a1.2 1.2 0 0 0 1.2 1.1h6.7a1.2 1.2 0 0 0 1.2-1.1l.75-10.5" />
                                                <path d="M8.2 8.8v5.1M11.8 8.8v5.1" />
                                            </svg>
                                        </button>
                                    </form>
                                </template>
                                <button v-if="!isOwner && !selectedCount" type="button"
                                        @click="openReport(material)"
                                        aria-label="Report this file"
                                        title="Report this file"
                                        class="flex size-10 shrink-0 cursor-pointer items-center justify-center rounded-full text-muted transition hover:bg-danger/10 hover:text-danger sm:size-9">
                                    <svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M5 17V3.5M5 4h9l-2 3 2 3H5"/>
                                    </svg>
                                </button>
                                <!-- Icon-only on mobile so the filename keeps its width;
                                     text pill from sm up.
                                     Soft grey, not the accent: Download repeats on every
                                     row, and an accent-coloured control running down the
                                     whole list stops reading as "the action here". bg-base
                                     because bg-surface was the same tone as the op-card
                                     behind it, which left the blue arrow carrying the
                                     entire affordance (border-sky is 1.23:1 in dark). -->
                                <a v-if="!selectedCount && material.download_url" :href="material.download_url"
                                   aria-label="Download" title="Download"
                                   class="op-press flex size-10 items-center justify-center rounded-full border border-sky bg-base text-muted transition hover:bg-sky/40 hover:text-ink sm:h-auto sm:w-auto sm:px-3.5 sm:py-2">
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
            <section id="add-file" class="mt-7 scroll-mt-6">
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
                    <button v-if="!uploadOpen && resultCount > 0" type="button" @click="uploadOpen = true"
                            aria-label="Add a file"
                            class="fixed bottom-6 right-6 z-20 flex h-14 w-14 cursor-pointer items-center justify-center rounded-full bg-neon text-2xl font-bold text-white shadow-lg transition hover:brightness-125 sm:hidden">
                        +
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
                                       class="w-full rounded-xl border border-sky bg-base px-3.5 py-2.5 text-[15px] text-ink shadow-inner placeholder:text-muted focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20">
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
                                <label for="ufile"
                                       class="op-press teal-accent inline-flex min-h-11 cursor-pointer items-center gap-1.5 rounded-full border border-sky bg-surface px-4 text-[13px] font-semibold text-teal transition hover:bg-sky/40">
                                    <svg aria-hidden="true" class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round">
                                        <path d="M10 4v12M4 10h12"/>
                                    </svg>
                                    {{ uploadForm.files.length > 0 ? 'Add more files' : 'Choose files' }}
                                </label>
                                <p class="mt-1.5 text-xs text-muted">PDF, Word, PowerPoint, or image · up to {{ maxFileMb }}&nbsp;MB each · pick several at once</p>
                                <div v-if="uploadForm.files.length > 0" class="mt-2 rounded-lg border border-teal/20 bg-teal/5 px-3 py-2.5">
                                    <p class="mb-1.5 text-[11px] font-semibold uppercase tracking-[0.06em] text-teal/70">
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
                                <span v-if="errors['files.0']" role="alert" class="mt-1.5 block text-[13px] text-danger">{{ errors['files.0'] }}</span>
                            </div>

                            <!-- Section and Name share a row. Section is a three-option
                                 select that never needs full width, and pairing them
                                 keeps the form short enough to see the Upload button.
                                 Section leads and is width-capped rather than a 50/50
                                 split: Name disappears on a multi-file selection, and
                                 this way Section doesn't move or stretch when it does.
                                 items-end keeps both inputs aligned when one helper
                                 line wraps and the other doesn't. -->
                            <div class="flex flex-col gap-3.5 sm:flex-row sm:items-end sm:gap-3">
                                <div class="sm:w-44 sm:shrink-0">
                                    <label for="usection" class="mb-0.5 block text-[13px] font-semibold text-ink">Section</label>
                                    <p class="mb-1.5 text-[12px] text-muted">What kind of file is this?</p>
                                    <select id="usection" v-model="uploadForm.section"
                                            class="w-full rounded-xl border border-sky bg-base px-3.5 py-2.5 text-[15px] text-ink shadow-inner focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20">
                                        <option v-for="(label, key) in sections" :key="key" :value="key">{{ label }}</option>
                                    </select>
                                </div>

                                <!-- Only meaningful for a single file -->
                                <div v-if="uploadForm.files.length <= 1" class="min-w-0 flex-1">
                                    <label for="utitle" class="mb-0.5 block text-[13px] font-semibold text-ink">Name this file (optional)</label>
                                    <p class="mb-1.5 text-[12px] text-muted">Helps classmates find it if the filename isn't clear.</p>
                                    <input id="utitle" type="text" v-model="uploadForm.title"
                                           placeholder="e.g. Week 7 quiz solutions"
                                           :aria-invalid="!!errors.title"
                                           class="w-full rounded-xl border border-sky bg-base px-3.5 py-2.5 text-[15px] text-ink shadow-inner placeholder:text-muted focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20">
                                    <span v-if="errors.title" role="alert" class="mt-1.5 block text-[13px] text-danger">{{ errors.title[0] }}</span>
                                </div>
                            </div>

                            <!-- Width-capped: the answer is a first name, and a
                                 full-bleed box invites a sentence. -->
                            <div class="sm:max-w-xs">
                                <label for="uploaderName" class="mb-1.5 block text-[13px] font-semibold text-ink">Your name (optional)</label>
                                <input id="uploaderName" type="text" v-model="uploadForm.uploaderName"
                                       :aria-invalid="!!errors.uploaderName"
                                       class="w-full rounded-xl border border-sky bg-base px-3.5 py-2.5 text-[15px] text-ink shadow-inner placeholder:text-muted focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20">
                                <span v-if="errors.uploaderName" role="alert" class="mt-1.5 block text-[13px] text-danger">{{ errors.uploaderName[0] }}</span>
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
                              class="mt-1.5 w-full resize-none rounded-xl border border-sky bg-base px-3.5 py-2.5 text-[14px] text-ink shadow-inner placeholder:text-muted focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20"></textarea>
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
    </AppLayout>
</template>
