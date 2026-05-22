<x-layouts.app title="Terms" :indexable="true">
<div class="mx-auto w-full max-w-2xl flex-1 px-5 pb-10 pt-10">
    <header class="mb-7">
        <a href="{{ route('welcome') }}"
           class="mb-2 inline-block text-xs font-semibold uppercase tracking-[0.08em] text-neon hover:underline">‹ SlipNote</a>
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
            <p class="text-[14px] text-ink/90">SlipNote has no accounts. Access is controlled by capability URLs (the workspace link, the owner link, per-file delete tokens). If you lose a link and haven't set an opt-in recovery email, that access is gone. We have no way to recover it for you.</p>
        </section>

        <section>
            <h2 class="mb-2 text-[15px] font-bold text-ink">No warranty</h2>
            <p class="text-[14px] text-ink/90">The site is provided "as is" without warranty of any kind. We don't guarantee uptime, data retention, or that files won't be lost. Keep your own copies of anything important.</p>
        </section>

        <section>
            <h2 class="mb-2 text-[15px] font-bold text-ink">Limitation of liability</h2>
            <p class="text-[14px] text-ink/90">To the extent permitted by law, the site operator is not liable for any loss arising from use of SlipNote, including lost files, lost time, or damages caused by content shared through the site.</p>
        </section>

        <section>
            <h2 class="mb-2 text-[15px] font-bold text-ink">Reporting abuse</h2>
            <p class="text-[14px] text-ink/90">
                Every file has a <strong>Report</strong> button that flags it to the site operator for review — the fastest way to surface a problem.
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
