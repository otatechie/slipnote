<x-layouts.app title="Operator">
<div class="mx-auto w-full max-w-4xl flex-1 px-5 pb-10 pt-10">
    <header class="mb-7 flex items-start justify-between gap-4">
        <div>
            <p class="mb-1.5 text-xs font-semibold uppercase tracking-[0.08em] text-muted">SlipNote</p>
            <h1 class="text-3xl font-bold tracking-tight text-ink">Reported files</h1>
            <p class="mt-1.5 text-[15px] text-muted">
                {{ $materials->count() === 1 ? '1 file' : $materials->count().' files' }} flagged for review.
            </p>
        </div>
        <form method="POST" action="{{ route('operator.logout') }}">
            @csrf
            <button type="submit" class="cursor-pointer text-[13px] font-semibold text-muted hover:text-neon">Log out</button>
        </form>
    </header>

    @if (session('done'))
        <div class="mb-5 rounded-lg border border-sky bg-sky/40 px-4 py-3 text-sm font-medium text-teal">
            {{ session('done') }}
        </div>
    @endif

    @if ($materials->isNotEmpty())
        <div class="divide-y divide-sky/40 overflow-hidden rounded-xl border border-sky/40 bg-surface">
            @foreach ($materials as $material)
                @php($reasons = $material->reports->whereNotNull('reason'))
                <div class="flex flex-col gap-3 px-4 py-3.5 sm:flex-row sm:items-start sm:justify-between sm:gap-4 sm:px-5">
                    {{-- Content: file + board + reasons. Course info in teal. --}}
                    <div class="min-w-0 flex-1">
                        <p class="flex items-center gap-2 text-[14px] font-semibold text-teal">
                            <span class="truncate">{{ $material->displayName() }}</span>
                            <span class="shrink-0 text-[12px] font-semibold tabular-nums text-red-600/80"
                                  title="{{ $material->reports_count }} {{ $material->reports_count === 1 ? 'report' : 'reports' }}">
                                {{ $material->reports_count }} {{ $material->reports_count === 1 ? 'report' : 'reports' }}
                            </span>
                        </p>
                        <p class="truncate text-[12px] text-teal/70">
                            {{ $material->course->workspace->name }} · {{ $material->course->code }} ·
                            <span class="uppercase">{{ $material->section }}</span>
                        </p>

                        {{-- Reasons: latest inline, full history collapsed --}}
                        @if ($reasons->isNotEmpty())
                            <details class="group mt-2">
                                <summary class="flex cursor-pointer list-none items-center gap-1.5 text-[12px] text-muted marker:hidden">
                                    <span class="truncate">
                                        “{{ \Illuminate\Support\Str::limit($reasons->first()->reason, 80) }}”
                                        @if ($reasons->count() > 1)<span class="text-muted">+{{ $reasons->count() - 1 }} more</span>@endif
                                    </span>
                                    <svg class="size-3 shrink-0 transition group-open:rotate-180" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 8l4 4 4-4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </summary>
                                <ul class="mt-1.5 space-y-1 border-l-2 border-sky/50 pl-3">
                                    @foreach ($reasons as $report)
                                        <li class="text-[12px] text-ink/90">
                                            <span class="text-muted">{{ $report->created_at->diffForHumans() }}:</span>
                                            {{ $report->reason }}
                                        </li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif
                    </div>

                    {{-- Actions: inline top-right on desktop, equal-width bar on mobile --}}
                    <div class="grid shrink-0 grid-cols-3 items-center gap-1.5 sm:flex sm:gap-1.5">
                        @if (filled($material->manage_token))
                            <a href="{{ route('material.download', ['token' => $material->manage_token]) }}"
                               class="inline-flex h-9 cursor-pointer items-center justify-center rounded-md border border-sky/40 bg-base px-3 text-[13px] font-semibold text-teal transition hover:bg-sky/40 sm:h-8 sm:text-[12px]">
                                View
                            </a>
                        @endif
                        <button type="button" onclick="document.getElementById('dismiss-{{ $material->id }}').showModal()"
                                class="inline-flex h-9 w-full cursor-pointer items-center justify-center rounded-md border border-sky/40 bg-base px-3 text-[13px] font-semibold text-muted transition hover:bg-sky/40 sm:h-8 sm:w-auto sm:text-[12px]">
                            Dismiss
                        </button>
                        <button type="button" onclick="document.getElementById('remove-{{ $material->id }}').showModal()"
                                class="inline-flex h-9 w-full cursor-pointer items-center justify-center rounded-md bg-red-600/90 px-3 text-[13px] font-semibold text-white transition hover:bg-red-600 sm:h-8 sm:w-auto sm:text-[12px]">
                            Remove
                        </button>
                    </div>

                    {{-- Dismiss confirmation modal (native <dialog>) --}}
                    <dialog id="dismiss-{{ $material->id }}"
                            class="m-auto w-[calc(100%-2rem)] max-w-sm rounded-2xl bg-surface p-0 shadow-xl backdrop:bg-ink/30 backdrop:backdrop-blur-sm">
                        <div class="px-6 py-6">
                            <h2 class="text-[15px] font-bold text-ink">Dismiss the reports?</h2>
                            <p class="mt-1 truncate text-[13px] text-muted">{{ $material->displayName() }}</p>
                            <p class="mt-3 text-[13px] text-ink/80">This clears the reports on this file. The file stays up and visible to everyone.</p>
                            <div class="mt-5 flex items-center justify-end gap-2">
                                <button type="button" onclick="this.closest('dialog').close()"
                                        class="inline-flex min-h-11 cursor-pointer items-center rounded-lg px-4 text-[14px] font-semibold text-muted transition hover:bg-sky/40 hover:text-ink">Cancel</button>
                                <form method="POST" action="{{ route('operator.dismiss', $material->id) }}">
                                    @csrf
                                    <button type="submit"
                                            class="inline-flex min-h-11 cursor-pointer items-center rounded-lg bg-teal px-5 text-[14px] font-semibold text-white transition hover:brightness-110">
                                        Dismiss reports
                                    </button>
                                </form>
                            </div>
                        </div>
                    </dialog>

                    {{-- Remove confirmation modal (native <dialog>) --}}
                    <dialog id="remove-{{ $material->id }}"
                            class="m-auto w-[calc(100%-2rem)] max-w-sm rounded-2xl bg-surface p-0 shadow-xl backdrop:bg-ink/30 backdrop:backdrop-blur-sm">
                        <div class="px-6 py-6">
                            <h2 class="text-[15px] font-bold text-ink">Remove this file?</h2>
                            <p class="mt-1 truncate text-[13px] text-muted">{{ $material->displayName() }}</p>
                            <p class="mt-3 text-[13px] text-ink/80">This permanently deletes the file and its reports. This can’t be undone.</p>
                            <div class="mt-5 flex items-center justify-end gap-2">
                                <button type="button" onclick="this.closest('dialog').close()"
                                        class="inline-flex min-h-11 cursor-pointer items-center rounded-lg px-4 text-[14px] font-semibold text-muted transition hover:bg-sky/40 hover:text-ink">Cancel</button>
                                <form method="POST" action="{{ route('operator.remove', $material->id) }}">
                                    @csrf
                                    <button type="submit"
                                            class="inline-flex min-h-11 cursor-pointer items-center rounded-lg bg-red-600/90 px-5 text-[14px] font-semibold text-white transition hover:bg-red-600">
                                        Remove file
                                    </button>
                                </form>
                            </div>
                        </div>
                    </dialog>
                </div>
            @endforeach
        </div>
    @else
        <div class="rounded-xl border border-sky/40 bg-surface px-6 py-12 text-center">
            <p class="text-[16px] font-semibold text-ink">Nothing reported</p>
            <p class="mx-auto mt-1.5 max-w-sm text-[14px] text-muted">No files are currently flagged. Reports show up here when someone uses the report button on a file.</p>
        </div>
    @endif
</div>
</x-layouts.app>
