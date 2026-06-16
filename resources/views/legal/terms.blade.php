<x-layouts.app title="Terms" :indexable="true">
<div class="mx-auto w-full max-w-2xl flex-1 px-4 pb-10 pt-8 sm:px-5 sm:pt-10">
    <header class="mb-7">
        <a href="{{ route('welcome') }}"
           class="group mb-2 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.08em] text-neon transition hover:underline">
            <svg aria-hidden="true"
                 class="size-5 shrink-0 transition-transform duration-200 group-hover:-translate-x-1"
                 viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M13 9a1 1 0 0 1-1-1V4.707a.707.707 0 0 0-1.207-.5l-6.94 6.94a1.207 1.207 0 0 0 0 1.707l6.94 6.94a.707.707 0 0 0 1.207-.5V16a1 1 0 0 1 1-1h2a1 1 0 0 0 1-1v-4a1 1 0 0 0-1-1z"/>
                <path d="M20 9v6"/>
            </svg>
            <span>SlipNote</span>
        </a>
        <h1 class="text-3xl font-bold tracking-tight text-ink">Terms</h1>
        <p class="mt-1.5 text-[13px] text-muted">Last updated {{ \Illuminate\Support\Carbon::parse(config('noteshare.legal_updated', '2026-05-19'))->isoFormat('MMMM D, YYYY') }}</p>
    </header>

    <div class="space-y-6 text-[15px] leading-relaxed text-ink">
        <p>SlipNote is provided free, as-is, with no warranty. By using it, you accept the following.</p>

        <section>
            <h2 class="mb-2 text-[15px] font-bold text-ink">Acceptable use</h2>
            <ul class="ml-5 list-disc space-y-1.5 text-[14px] text-ink/90">
                <li>Upload only what you have the right to share. Don't upload copyrighted material you don't own or have permission to redistribute.</li>
                <li>Don't upload illegal content, malware, or anything that could harm other users.</li>
                <li>The site operator may remove any content or workspace that violates these terms, without notice.</li>
            </ul>
        </section>

        <section>
            <h2 class="mb-2 text-[15px] font-bold text-ink">No accounts, no recovery guarantees</h2>
            <p class="text-[14px] text-ink/90">SlipNote has no accounts. Access is controlled by capability URLs and secrets (the workspace link, the owner link or owner key, and per-file delete tokens). If you lose owner access and haven't set an opt-in recovery email, that access is gone. If recovery is enabled and a matching recovery email is on file, SlipNote can issue a fresh owner link; the previous owner link stops working once the replacement has been sent successfully.</p>
        </section>

        <section>
            <h2 class="mb-2 text-[15px] font-bold text-ink">No warranty</h2>
            <p class="text-[14px] text-ink/90">The site is provided "as is" without warranty of any kind. We don't guarantee uptime, data retention, email delivery, or that files won't be lost. Keep your own copies of anything important, including any owner link or owner key you rely on.</p>
        </section>

        <section>
            <h2 class="mb-2 text-[15px] font-bold text-ink">Limitation of liability</h2>
            <p class="text-[14px] text-ink/90">To the extent permitted by law, the site operator is not liable for any loss arising from use of SlipNote, including lost files, lost time, or damages caused by content shared through the site.</p>
        </section>

        <section>
            <h2 class="mb-2 text-[15px] font-bold text-ink">Reporting abuse</h2>
            <p class="text-[14px] text-ink/90">
                Every file has a <strong>Report</strong> button that flags it to the site operator for review, the fastest way to surface a problem.
                @if (filled(config('noteshare.contact_email')))
                    For anything urgent or requiring a direct reply, email the operator at
                    <a href="mailto:{{ config('noteshare.contact_email') }}" class="font-semibold text-neon hover:underline">{{ config('noteshare.contact_email') }}</a>.
                @else
                    For anything urgent, contact the operator of the specific site.
                @endif
                Include the file's URL and what's wrong.
            </p>
        </section>

        <section>
            <h2 class="mb-2 text-[15px] font-bold text-ink">Copyright takedowns</h2>
            <p class="text-[14px] text-ink/90">If you believe content on SlipNote infringes your copyright, report the file or contact the operator with a description of the material and its URL. The operator will remove infringing content promptly when verified.</p>
        </section>

        <section>
            <h2 class="mb-2 text-[15px] font-bold text-ink">Changes</h2>
            <p class="text-[14px] text-ink/90">These terms may change. The "Last updated" date at the top reflects the most recent revision. Continued use after a change means you accept the new terms.</p>
        </section>
    </div>
</div>
</x-layouts.app>
