<x-layouts.app title="Privacy">
<div class="mx-auto w-full max-w-2xl flex-1 px-5 pb-10 pt-10">
    <header class="mb-7">
        <a href="{{ route('home') }}"
           class="mb-2 inline-block text-xs font-semibold uppercase tracking-[0.08em] text-neon hover:underline">← SlipNote</a>
        <h1 class="text-3xl font-bold tracking-tight text-ink">Privacy</h1>
        <p class="mt-1.5 text-[13px] text-muted">Last updated {{ \Illuminate\Support\Carbon::parse(config('noteshare.legal_updated', '2026-05-19'))->isoFormat('MMMM D, YYYY') }}</p>
    </header>

    <div class="space-y-6 text-[15px] leading-relaxed text-ink">
        <p>SlipNote is built to need as little of your data as possible. There are no accounts, no profile fields, and no third-party trackers.</p>

        <section>
            <h2 class="mb-2 text-[15px] font-bold text-ink">What we store</h2>
            <ul class="ml-5 list-disc space-y-1.5 text-[14px] text-ink/90">
                <li>The <strong>files you upload</strong> and their original filenames, kept on our server until someone deletes them.</li>
                <li>The <strong>workspace name</strong> you typed and the slug derived from it.</li>
                <li>The <strong>course code and title</strong> the owner created.</li>
                <li>An <strong>uploader name</strong> if you typed one (optional, free text).</li>
                <li>A <strong>recovery email</strong> if the owner opts in. Stored encrypted at rest; used only to send the owner link if requested.</li>
                <li>Standard <strong>server logs</strong> (IP address, request path, timestamp) — used for security and debugging, not analytics.</li>
                <li>A <strong>session cookie</strong> to remember owner-mode unlocks and passphrase entries within a single visit. No tracking cookies.</li>
            </ul>
        </section>

        <section>
            <h2 class="mb-2 text-[15px] font-bold text-ink">What we don't do</h2>
            <ul class="ml-5 list-disc space-y-1.5 text-[14px] text-ink/90">
                <li>No analytics, no advertising, no profile building.</li>
                <li>No selling or sharing of data with third parties.</li>
                <li>No account, password, or email is required to use the site.</li>
            </ul>
        </section>

        <section>
            <h2 class="mb-2 text-[15px] font-bold text-ink">Telegram notifications (optional)</h2>
            <p class="text-[14px] text-ink/90">If the site operator has configured Telegram, a one-line notice (course, section, filename, link) is posted to the configured channel on every new upload. This is set by the operator, not per workspace.</p>
        </section>

        <section>
            <h2 class="mb-2 text-[15px] font-bold text-ink">Deleting your data</h2>
            <p class="text-[14px] text-ink/90">An uploader can remove their own file via the one-time delete link shown after upload. The workspace owner can delete any file in their workspace. There is currently no UI to delete an entire workspace — if you need this, contact the site operator.</p>
        </section>

        <section>
            <h2 class="mb-2 text-[15px] font-bold text-ink">Contact</h2>
            <p class="text-[14px] text-ink/90">SlipNote is open source — the code is at <a href="https://github.com/otatechie/slipnote" class="font-semibold text-neon hover:underline">github.com/otatechie/slipnote</a>. For privacy questions on a specific deployment, contact the operator of that site.</p>
        </section>
    </div>
</div>
</x-layouts.app>
