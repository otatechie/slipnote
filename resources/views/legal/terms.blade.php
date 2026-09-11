<x-layouts.app title="Terms" :indexable="true">
<div class="op mx-auto w-full max-w-2xl flex-1 px-4 pb-10 pt-8 sm:px-5 sm:pt-10">
    <header class="mb-7">
        <a href="{{ route('welcome') }}"
           class="op-kicker group mb-1 inline-flex items-center gap-1.5 py-1 text-[11px] font-semibold uppercase text-muted transition hover:text-neon">
            {{-- Same back-link as the board and course pages: muted until hover,
                 3.5 arrow, and py-1 so a standalone link clears a 24px target. --}}
            <svg aria-hidden="true"
                 class="size-3.5 shrink-0 transition-transform duration-200 group-hover:-translate-x-0.5"
                 viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 12H5" />
                <path d="M12 19l-7-7 7-7" />
            </svg>
            <span>SlipNote</span>
        </a>
        <h1 class="op-title text-[2.15rem] font-bold text-ink">Terms</h1>
        <p class="mt-1.5 text-[13px] text-muted">Last updated {{ \Illuminate\Support\Carbon::parse(config('noteshare.legal_updated', '2026-05-19'))->isoFormat('MMMM D, YYYY') }}</p>
    </header>

    <div class="space-y-6 text-[15px] leading-relaxed text-ink">
        <p>SlipNote exists to help students share their own course materials — notes, slides, past papers — with their own classmates, and nothing more. It's provided free, as-is, with no warranty. By using it, you accept the following.</p>

        <section>
            <h2 class="mb-2 text-[16px] font-semibold text-ink">Acceptable use</h2>
            <ul class="ml-5 list-disc space-y-1.5 text-[14px] text-ink">
                <li>Upload only what you have the right to share. Don't upload copyrighted material you don't own or have permission to redistribute.</li>
                <li>Don't upload other people's personal, private, or confidential information without their consent.</li>
                <li>Don't upload malware, or files designed to harm, deceive, or exploit anyone who downloads them.</li>
                <li>Don't upload illegal content or anything that could harm other users.</li>
                <li>The site operator may remove any content or workspace that violates these terms, without notice.</li>
            </ul>
        </section>

        <section>
            <h2 class="mb-2 text-[16px] font-semibold text-ink">No accounts, no recovery guarantees</h2>
            <p class="text-[14px] text-ink">SlipNote has no accounts. Access is controlled by capability URLs and secrets (the workspace link, the owner link or owner key, and per-file delete tokens). If you lose owner access and haven't set an opt-in recovery email, that access is gone. If recovery is enabled and a matching recovery email is on file, SlipNote can issue a fresh owner link; the previous owner link stops working once the replacement has been sent successfully.</p>
        </section>

        <section>
            <h2 class="mb-2 text-[16px] font-semibold text-ink">Your content is your responsibility</h2>
            <p class="text-[14px] text-ink">Uploading a file to a board makes it readable and downloadable by anyone who has that board's link. There is no sign-in on a board and no per-file permission; the link is the access. Upload only what you are content for a stranger to read. SlipNote is a neutral host. It does not pre-screen, review, or endorse anything users upload, and files are not moderated before they appear. Whoever uploads a file is solely responsible for it — for having the right to share it and for what it contains. Boards and their contents are shared by users, not published by the operator. When a problem is reported or found, the operator can remove the content, but SlipNote takes no responsibility for user-uploaded material and makes no claim that it is accurate, lawful, or safe.</p>
        </section>

        <section>
            <h2 class="mb-2 text-[16px] font-semibold text-ink">No warranty</h2>
            <p class="text-[14px] text-ink">The site is provided "as is" without warranty of any kind. We don't guarantee uptime, data retention, email delivery, or that files won't be lost. Files are not scanned for viruses or malware — you download them at your own risk, and you should treat any file as untrusted until you've checked it yourself. Keep your own copies of anything important, including any owner link or owner key you rely on.</p>
        </section>

        <section>
            <h2 class="mb-2 text-[16px] font-semibold text-ink">Your indemnity</h2>
            <p class="text-[14px] text-ink">If something you upload or share through SlipNote causes a claim against the site operator — for example from a copyright holder, or from someone whose information you posted — you agree to cover the reasonable costs the operator incurs in dealing with it, to the extent the law allows.</p>
        </section>

        <section>
            <h2 class="mb-2 text-[16px] font-semibold text-ink">Limitation of liability</h2>
            <p class="text-[14px] text-ink">To the extent permitted by law, the site operator is not liable for any loss arising from use of SlipNote, including lost files, lost time, or damages caused by content shared through the site.</p>
        </section>

        <section>
            <h2 class="mb-2 text-[16px] font-semibold text-ink">Reporting abuse</h2>
            <p class="text-[14px] text-ink">
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
            <h2 class="mb-2 text-[16px] font-semibold text-ink">Copyright takedowns</h2>
            <p class="text-[14px] text-ink">If you believe content on SlipNote infringes your copyright, report the file or contact the operator with a description of the material and its URL. The operator will remove infringing content promptly when verified.</p>
        </section>

        <section>
            <h2 class="mb-2 text-[16px] font-semibold text-ink">Governing law</h2>
            <p class="text-[14px] text-ink">These terms are governed by the laws of the jurisdiction in which the operator of this specific SlipNote deployment is based, without regard to conflict-of-law rules. Any dispute will be handled in the courts of that jurisdiction. Nothing here removes any consumer rights you have under the mandatory law of your own country.</p>
        </section>

        <section>
            <h2 class="mb-2 text-[16px] font-semibold text-ink">Changes</h2>
            <p class="text-[14px] text-ink">These terms may change. The "Last updated" date at the top reflects the most recent revision. Continued use after a change means you accept the new terms.</p>
        </section>
    </div>
</div>
</x-layouts.app>
