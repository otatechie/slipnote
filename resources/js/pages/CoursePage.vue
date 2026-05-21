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

// Upload form
const uploadOpen = ref(false)
const uploadForm = useForm({
    section: 'notes',
    title: '',
    uploaderName: '',
    passphrase: '',
    file: null,
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
    uploadForm.file = e.target.files[0] ?? null
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

function exitOwner() {
    router.post('/' + props.workspace.slug + '/c/' + props.course.slug + '/exit-owner', {}, {
        preserveScroll: true,
    })
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
</script>

<template>
    <Head :title="course.code + ' · ' + workspace.name" />
    <AppLayout>
        <div class="mx-auto w-full max-w-3xl flex-1 px-5 pb-10 pt-10">

            <header class="mb-7">
                <Link :href="courseListUrl()"
                      class="mb-1.5 inline-block text-xs font-semibold uppercase tracking-[0.08em] text-neon hover:underline">
                    ← {{ workspace.name }}
                </Link>
                <h1 class="text-3xl font-bold tracking-tight text-ink">{{ course.code }}</h1>
                <p class="mt-1.5 text-[15px] text-muted">{{ course.title }}</p>
            </header>

            <!-- Owner mode banner -->
            <div v-if="isOwner"
                 class="mb-5 flex flex-wrap items-center justify-between gap-x-3 gap-y-1 rounded-lg border border-neon/40 bg-neon/10 px-4 py-3 text-sm font-medium text-neon">
                <span>Owner mode — you can remove any file.</span>
                <button type="button" @click="exitOwner"
                        class="cursor-pointer font-semibold underline-offset-2 hover:underline">Exit</button>
            </div>

            <!-- Upload receipt -->
            <div v-if="flash.uploaded"
                 class="mb-5 flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg border border-sky bg-sky/40 px-4 py-3 text-sm font-medium text-teal">
                <span>{{ flash.uploaded }}</span>
                <template v-if="flash.manageUrl">
                    <form :action="flash.manageUrl" method="POST"
                          @submit.prevent="e => { if(confirm('Remove this file? This can\'t be undone.')) { e.target.submit() } }">
                        <input type="hidden" name="_token" :value="$page.props.csrf_token ?? ''">
                        <input type="hidden" name="_method" value="DELETE">
                        <button type="submit" class="cursor-pointer font-semibold text-neon underline-offset-2 hover:underline">
                            Uploaded the wrong file? Remove it
                        </button>
                    </form>
                </template>
            </div>

            <!-- Sticky filter bar -->
            <div class="sticky top-0 z-30 -mx-5 mb-5 space-y-3 bg-base/95 px-5 py-3 backdrop-blur">
                <div class="flex flex-col gap-2 sm:flex-row">
                    <input type="search" v-model="localSearch"
                           :placeholder="`Search ${resultCount} files by name…`"
                           aria-label="Search files"
                           class="h-11 flex-1 rounded-lg border border-sky/30 bg-surface px-3.5 text-[15px] text-ink shadow-sm placeholder:text-muted focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20">
                    <select v-model="localSort" aria-label="Sort files"
                            class="h-11 rounded-lg border border-sky/30 bg-surface px-3.5 text-[15px] font-medium text-ink shadow-sm focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20">
                        <option value="newest">Newest first</option>
                        <option value="oldest">Oldest first</option>
                        <option value="az">A–Z</option>
                    </select>
                </div>

                <!-- Section filter pills -->
                <nav aria-label="Filter by section" class="flex flex-wrap gap-1.5">
                    <template v-for="(label, key) in sections" :key="key">
                        <button type="button" @click="toggleSection(key)"
                                :disabled="!sectionCounts[key] && localSection !== key"
                                :aria-pressed="localSection === key ? 'true' : 'false'"
                                class="inline-flex cursor-pointer items-center gap-1.5 rounded-full px-3 py-1 text-[13px] font-semibold transition"
                                :class="localSection === key
                                    ? 'bg-neon text-white'
                                    : (sectionCounts[key] ? 'bg-sky text-neon hover:brightness-95' : 'cursor-not-allowed bg-surface text-muted')">
                            {{ label }}
                            <span class="rounded-full px-1.5 text-xs tabular-nums"
                                  :class="localSection === key
                                      ? 'bg-white/25 text-white'
                                      : (sectionCounts[key] ? 'bg-base text-neon' : 'bg-sky/40 text-muted')">
                                {{ sectionCounts[key] ?? 0 }}
                            </span>
                        </button>
                    </template>
                    <button v-if="localSection !== ''" type="button" @click="localSection = ''"
                            class="inline-flex cursor-pointer items-center rounded-full px-3 py-1 text-[13px] font-semibold text-muted underline-offset-2 transition hover:text-neon hover:underline">
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

            <!-- Materials grouped by section -->
            <template v-for="(label, key) in sections" :key="key">
                <template v-if="materialsBySection[key]?.length === 0">
                    <!-- Hidden while filtered -->
                    <section v-if="!isFiltered" :id="'sec-' + key"
                             class="mb-3 flex scroll-mt-20 items-baseline justify-between gap-3 rounded-xl border border-sky/30 bg-surface/50 px-5 py-3">
                        <h2 class="text-xs font-bold uppercase tracking-[0.06em] text-muted">{{ label }}</h2>
                        <p class="text-[13px] text-muted">
                            Empty —
                            <a href="#add-file" @click="uploadOpen = true" class="font-medium text-neon hover:underline">be the first to upload</a>
                        </p>
                    </section>
                </template>
                <template v-else>
                    <section :id="'sec-' + key" class="mb-4 scroll-mt-20 rounded-2xl border border-sky/30 bg-surface px-6 py-5 shadow-md ring-1 ring-black/3">
                        <h2 class="mb-3.5 flex items-baseline justify-between text-xs font-bold uppercase tracking-[0.06em] text-muted">
                            <span>{{ label }}</span>
                            <span class="rounded-full border border-sky bg-sky/40 px-2.5 py-0.5 text-xs font-semibold normal-case tracking-normal text-teal">
                                {{ materialsBySection[key].length }}
                            </span>
                        </h2>
                        <div v-for="material in materialsBySection[key]" :key="material.id"
                             class="flex items-center justify-between gap-4 border-b border-black/5 py-3 first:pt-0 last:border-0 last:pb-0">
                            <div class="flex min-w-0 items-start gap-3">
                                <span class="mt-1 shrink-0 rounded bg-sky/30 px-1 py-px text-[10px] font-semibold tracking-wide text-muted"
                                      :title="material.fileTypeLabel + ' file'">
                                    {{ material.fileTypeLabel }}
                                </span>
                                <div class="min-w-0">
                                    <a :href="material.download_url"
                                       class="break-words text-[15px] font-semibold text-neon hover:underline">
                                        {{ material.displayName }}
                                    </a>
                                    <div class="mt-0.5 text-[13px] text-muted">
                                        <template v-if="material.title">{{ material.original_filename }} · </template>
                                        {{ material.uploader_name || 'Anonymous' }} · {{ material.created_at_human }}
                                    </div>
                                </div>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <template v-if="isOwner">
                                    <form :action="material.delete_url" method="POST"
                                          :data-name="material.displayName"
                                          @submit="confirmDelete">
                                        <input type="hidden" name="_token" :value="$page.props.csrf_token ?? ''">
                                        <input type="hidden" name="_method" value="DELETE">
                                        <button type="submit"
                                                class="cursor-pointer rounded-full px-3 py-1.5 text-[13px] font-semibold text-muted transition hover:bg-red-50 hover:text-red-600">
                                            Delete
                                        </button>
                                    </form>
                                </template>
                                <a :href="material.download_url"
                                   class="rounded-full bg-neon px-4 py-1.5 text-[13px] font-semibold text-white shadow-sm transition hover:brightness-125">
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

                    <!-- Collapsed button -->
                    <button v-if="!uploadOpen" type="button" @click="uploadOpen = true"
                            class="flex w-full cursor-pointer items-center justify-center gap-2 rounded-2xl border border-dashed border-teal/50 bg-surface px-6 py-4 text-[15px] font-semibold text-neon shadow-sm transition hover:bg-sky/30">
                        <span class="text-lg leading-none">+</span> Add a file
                    </button>

                    <!-- Expanded form -->
                    <div v-if="uploadOpen"
                         class="rounded-2xl border border-dashed border-teal/50 bg-surface px-6 py-5 shadow-sm">
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

                            <!-- Title -->
                            <div>
                                <label for="utitle" class="mb-1.5 block text-[13px] font-semibold text-ink">What is this? (optional)</label>
                                <input id="utitle" type="text" v-model="uploadForm.title"
                                       placeholder="e.g. Week 7 quiz solutions"
                                       :aria-invalid="!!errors.title"
                                       class="w-full rounded-lg border border-sky/30 bg-base px-3 py-2.5 text-[15px] text-ink placeholder:text-muted focus:border-neon focus:outline-none focus:ring-2 focus:ring-neon/20">
                                <span v-if="errors.title" role="alert" class="mt-1.5 block text-[13px] text-red-600">{{ errors.title[0] }}</span>
                            </div>

                            <!-- Section -->
                            <div>
                                <label for="usection" class="mb-1.5 block text-[13px] font-semibold text-ink">Section</label>
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

                            <!-- File -->
                            <div>
                                <label for="ufile" class="mb-1.5 block text-[13px] font-semibold text-ink">File</label>
                                <input id="ufile" type="file" @change="onFileChange"
                                       :aria-invalid="!!errors.file"
                                       class="w-full text-sm text-muted file:mr-3 file:rounded-md file:border-0 file:bg-sky file:px-3 file:py-1.5 file:text-[13px] file:font-semibold file:text-teal">
                                <p class="mt-1.5 text-xs text-muted">PDF, Word, PowerPoint, or image · up to 10&nbsp;MB</p>
                                <span v-if="errors.file" role="alert" class="mt-1.5 block text-[13px] text-red-600">{{ errors.file[0] }}</span>
                            </div>

                            <button type="submit" :disabled="uploadForm.processing"
                                    class="cursor-pointer rounded-lg bg-neon py-3 text-[15px] font-bold text-white transition hover:brightness-125 disabled:cursor-progress disabled:opacity-60">
                                <span v-if="!uploadForm.processing">Upload</span>
                                <span v-else>Saving…</span>
                            </button>
                        </form>
                    </div>
                </template>
            </section>
        </div>
    </AppLayout>
</template>
