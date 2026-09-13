<x-layouts.app title="Privacy" :indexable="true">
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
        <h1 class="op-title text-[2.15rem] font-bold text-ink">Privacy</h1>
        <p class="mt-1.5 text-[13px] text-muted">Last updated {{ \Illuminate\Support\Carbon::parse(config('noteshare.legal_updated', '2026-05-19'))->isoFormat('MMMM D, YYYY') }}</p>
    </header>

    <div class="space-y-6 text-[15px] leading-relaxed text-ink">
        <p>SlipNote is built to need as little of your data as possible. There are no accounts, no profile fields, and no third-party trackers.</p>

        <section>
            <h2 class="mb-2 text-[16px] font-semibold text-ink">Anything you upload is public</h2>
            <p class="text-[14px] text-ink">A board is protected by its link, not by a password. Anyone who has the link &mdash; or who is given it by someone else &mdash; can open the board, read every file on it, and download them. There is no sign-in on any of this. Boards are excluded from search engines, and the links are long enough not to be guessable, but that is the whole of the protection: treat a board link the way you would treat the files themselves. Don't upload anything you would mind a stranger reading, and don't upload other people's personal information.</p>
        </section>

        <section>
            <h2 class="mb-2 text-[16px] font-semibold text-ink">What we store</h2>
            <ul class="ml-5 list-disc space-y-1.5 text-[14px] text-ink">
                <li>The <strong>files you upload</strong>, their original filenames, and each file's size, kept on our server until someone deletes them.</li>
                <li>A <strong>display title</strong> for a file if you typed one instead of using the filename (optional, free text).</li>
                <li>The <strong>time each file was uploaded</strong>, shown publicly on the file's row.</li>
                <li>The <strong>workspace name</strong> you typed and the slug derived from it.</li>
                <li>The <strong>course code and title</strong> the owner created.</li>
                <li>An <strong>uploader name</strong> if you typed one (optional, free text).</li>
                <li>A one-time <strong>manage token</strong> for each upload. It is what the "delete your own file" link contains, so it stays with the file until the file is deleted.</li>
                <li>A <strong>content fingerprint</strong> (SHA-256 hash) of each file, used to skip duplicate uploads and to stop files the operator removed from being re-uploaded. Fingerprints of operator-removed files are kept after the file itself is gone; a fingerprint can't be turned back into the file.</li>
                <li>If you <strong>report a file</strong>: the report reason and your IP address, used only to review the report.</li>
                <li>Your <strong>IP address as a rate-limit counter</strong> when you create a board, upload a file, report a file, or try the operator login. These counters live in a short-lived cache and exist to blunt automated abuse, not to identify you.</li>
                <li>When a board was <strong>last opened</strong>. One timestamp per board, overwritten each visit &mdash; it tells the operator which boards are still in use. It records no visitor details and keeps no history.</li>
                <li>The board's <strong>owner secret</strong>, and an <strong>upload passphrase</strong> if the owner sets one — both stored only as one-way hashes, so we can't read them back.</li>
                <li>An optional <strong>recovery email</strong> if the owner opts in. It is stored encrypted at rest and used only to send a fresh owner link when requested.</li>
                <li>Standard <strong>server logs</strong> (IP address, request path, timestamp), used for security and debugging, not analytics.</li>
                <li>A <strong>session cookie</strong> to remember owner-mode unlocks and passphrase entries within a single visit, and the <strong>session record</strong> it points at on our server. That record holds your IP address and your browser's user-agent string (browser and operating system version), which is how the session is kept secure. It is deleted when the session expires.</li>
                <li>A small browser-side <strong>recent boards</strong> cookie if you open a board in owner mode, so this browser can show shortcuts back to boards you've recently managed.</li>
                <li>A short-lived <strong>referral</strong> cookie if you arrive through a campus ambassador's link, holding only that ambassador's code. It is used once, when a board is created, to note which ambassador introduced it, and is then removed. It records nothing about you.</li>
                <li>A small browser-side <strong>last visited</strong> cookie recording when this browser last opened each course, so files added since can be marked as new. It holds course ids and timestamps only, and never leaves your browser except to load the page.</li>
                <li>A browser-side <strong>theme preference</strong> (light, dark, or system) stored locally on this device only.</li>
            </ul>
        </section>

        <section>
            <h2 class="mb-2 text-[16px] font-semibold text-ink">How long we keep it</h2>
            <p class="text-[14px] text-ink">SlipNote does not auto-delete your content. Files, workspaces, and course details are kept until someone removes them — an uploader deleting their own file, the owner deleting files, or the operator removing content. A board that is never touched stays indefinitely. Report details, including the reporter's IP address, are retained only for as long as needed to review the report and are not used for anything else. The two things that do expire on their own are session records and the IP rate-limit counters, both of which are short-lived and discarded automatically.</p>
        </section>

        <section>
            <h2 class="mb-2 text-[16px] font-semibold text-ink">Age</h2>
            <p class="text-[14px] text-ink">SlipNote is intended for students in higher or further education and is not directed at children under 13. We don't knowingly collect personal information from children under 13. If you believe a child has uploaded personal information, report the file or contact the operator and it will be removed.</p>
        </section>

        <section>
            <h2 class="mb-2 text-[16px] font-semibold text-ink">Your rights</h2>
            <p class="text-[14px] text-ink">Depending on where you live (for example under GDPR or CCPA), you may have the right to access, correct, or delete personal information about you, or to object to its processing. Because SlipNote holds so little — no accounts, no profiles — most of this is self-service: delete your own file with its delete link, or ask the workspace owner or site operator. For anything else, contact the operator of the specific site.</p>
        </section>

        <section>
            <h2 class="mb-2 text-[16px] font-semibold text-ink">What we don't do</h2>
            <ul class="ml-5 list-disc space-y-1.5 text-[14px] text-ink">
                <li>No analytics product, no advertising, no profile building, and no tracking of you across boards or visits. The one usage figure kept is a single "last opened" timestamp per board, which records nothing about who opened it.</li>
                <li>No selling or sharing of data with third parties.</li>
                <li>No account, password, or email is required to browse, create a board, or upload files.</li>
            </ul>
        </section>

        <section>
            <h2 class="mb-2 text-[16px] font-semibold text-ink">Email delivery</h2>
            <p class="text-[14px] text-ink">If the operator enables recovery, recovery emails are sent through the site's configured mail provider. That provider may process message metadata needed to deliver the email. SlipNote itself does not use recovery emails for marketing or mailing lists.</p>
        </section>

        <section>
            <h2 class="mb-2 text-[16px] font-semibold text-ink">Telegram notifications (optional)</h2>
            <p class="text-[14px] text-ink">If the site operator has configured Telegram, a one-line notice (course, section, filename, link) is posted to the configured channel on every new upload. This is set by the operator, not per workspace.</p>
        </section>

        <section>
            <h2 class="mb-2 text-[16px] font-semibold text-ink">Deleting your data</h2>
            <p class="text-[14px] text-ink">An uploader can remove their own file via the one-time delete link shown after upload. The workspace owner can delete any file in their workspace and can add, change, or remove the workspace's recovery email. There is currently no UI to delete an entire workspace. If you need this, contact the site operator.</p>
        </section>

        <section>
            <h2 class="mb-2 text-[16px] font-semibold text-ink">Contact</h2>
            <p class="text-[14px] text-ink">SlipNote is open source. The code is at <a href="https://github.com/otatechie/slipnote" class="font-semibold text-neon hover:underline">github.com/otatechie/slipnote</a>. For privacy questions on a specific deployment, contact the operator of that site.</p>
        </section>
    </div>
</div>
</x-layouts.app>
