# SlipNote

A dead-simple materials board for students. No accounts, no login — anyone
with the link can browse, download, and contribute course files across four
sections: **Notes, Slides, Past Papers, Announcements**.

**Workspaces (multi-tenant).** The site root (`/`) lets anyone create or
open a *workspace* — an isolated board with its own courses, owner secret,
and optional upload passphrase. Each owner shares their workspace link with
their own classmates; workspaces never see each other. No accounts: creating
a workspace yields a one-time **owner link** (the only credential), the same
capability-URL idea as the per-file delete token.

Built with Laravel 13 + Inertia + Vue 3, Tailwind v4, SQLite.

## Features

- **Anonymous upload** — pick a section, optional "what is this?" title and
  your name, attach one or several files at once. PDF / Word / PowerPoint /
  image, up to 25 MB each. Selected files list with a per-file remove and a
  running count; files that would exceed the workspace cap are skipped while
  the rest save. A shared title applies only to a single-file upload.
- **Uploader delete** — each upload gets a secret token; the success banner
  shows a one-time "Remove it" link so the uploader can undo a mistake. No
  accounts: holding the token is the only credential. Seeded/legacy rows have
  no token and are not deletable.
- **Owner mode** — visit `/<workspace>?owner=SECRET` (or any course page in
  it) with the workspace's owner secret to unlock a per-row "Delete" on
  every file for the session — the cleanup escape hatch. The secret is
  **per-workspace** (owning one grants nothing in another) and is stored
  only as a bcrypt hash; the plaintext is shown once at creation. No login.
- **Optional upload passphrase** — each workspace can set its own upload
  passphrase (entered once per session, per workspace) to gate uploads.
  Empty = uploads open (the default).
- **Optional Telegram notifications** — set both `TELEGRAM_BOT_TOKEN` (from
  @BotFather) and `TELEGRAM_CHAT_ID` in `.env` to post a one-line notice
  (course, section, filename, link) to a channel on every new upload.
  Outbound only — no webhook/polling. Sent *after* the response and
  failure-swallowed, so a slow or down Telegram never blocks or breaks an
  upload. Leave either value empty to disable (the default).
- **Reporting & operator moderation** — every file has a Report button (styled
  modal with preset reasons). Reports are stored and surfaced on an operator
  dashboard at `/operator`, gated by `OPERATOR_SECRET` (held in session, never
  in a URL). The operator can **Remove** any file from any workspace (the
  kill-switch) or **Dismiss** false reports. Removing a file blocklists its
  content hash so the exact same bytes can't be re-uploaded. Reports also fire
  a Telegram notice if configured.
- **Abuse throttling** — per-IP rate limits on uploads (30 / 10 min) and
  workspace creation (10 / hr) blunt automated abuse and mass board-spinning.
- **Anonymous download** — files grouped by section, file-type icons for quick
  scanning. Files are addressed by an unguessable per-file token, not a
  sequential id, so they can't be enumerated across workspaces.
- **Live search + sort** — global filter (matches title *and* filename) that
  keeps the section grouping; sort by Newest / Oldest / A–Z. Designed for the
  find-fast student mental model at a full semester's volume (~60+ files).
- **Quick-jump nav** — sticky section pills with live counts.
- **Collapsible upload form** so the (mostly read-only) page leads with content.
- **Dark mode** — an in-app theme toggle (icon-only) on every browser-facing
  page, persisted in `localStorage` and defaulting to the OS
  `prefers-color-scheme`. Destructive/warning UI (red/amber) is retoned for
  dark, and file-count chips and the active section pill take a plum accent so
  the palette isn't grey-on-grey. Custom Strichpunkt Sans typeface throughout.
- **Mobile-optimised** across the landing, workspace, and course pages.
- Accessible: WCAG AA contrast, `aria` wiring on form errors, actionable
  empty states.

## Requirements

- PHP 8.3+, Composer
- Node 18+ / npm
- SQLite (default; no DB server needed)

## Setup

```bash
composer install
npm install

cp .env.example .env        # if .env is missing
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan storage:link     # required: downloads serve from public/storage

npm run build                # or `npm run dev` for hot reload
php artisan serve
```

## Workspaces & courses

The site root (`/`) is the workspace landing: **create** a workspace (you're
shown a one-time owner link — save it) or **open** an existing one by name.
Inside a workspace (`/<workspace>`) is its course list. Create courses in
**owner mode** — open `/<workspace>?owner=SECRET` and use "New course".
Owners can also edit a course's code/title (the slug stays fixed so shared
`/c/<slug>` links keep working), drag courses into a custom order, and bulk-
delete files on a course page. Course slugs are unique *within a workspace*
(two workspaces may both have `phys-201`).

### Owner recovery (opt-in)

Lose the owner link and the board is unrecoverable — unless the owner has
opted into recovery by setting a **recovery email** (owner-mode only). The
email is encrypted at rest. Visit `/<workspace>/recover`, enter that email,
and the workspace's owner secret is rotated and a fresh owner link is
emailed back (HTML with a plain-text part); the previous link stops working.
The recovery page renders
identical responses whether the email matches or not (no enumeration), and
is rate-limited to prevent guessing. Hidden entirely when the mail driver
is `log` or `array` — don't promise recovery the install can't deliver.

## Tests

```bash
php artisan test                          # full suite (108 passing)
php artisan test tests/Feature/UploadTest.php
```

`UploadTest` covers upload (valid / oversized / wrong-type), HTML-stripping of
user input, optional title, file-type bucketing, and search/sort behavior.

`WorkspaceIsolationTest.php` is the security crux — it proves workspace A
cannot read, reach, or mutate workspace B's data. `OwnerRecoveryTest.php`
covers the no-enumeration recovery flow. The existing suites run *inside*
a workspace via the `InteractsWithWorkspace` trait.

## Security

- **Owner secret** stored only as a bcrypt hash; plaintext is shown once at
  workspace creation and never persisted. Owner-unlock and `?owner=` checks
  are timing-safe via `Hash::check`.
- **Owner-unlock rate limit:** the "paste your owner secret" form is capped
  at 5 attempts per 10 minutes per workspace to defeat brute force.
- **Owner-link rotation** on recovery: `rotateOwnerSecret()` issues a new
  secret and stores a fresh hash; the prior owner link stops working.
- **Recovery email encrypted at rest** via Laravel's `encrypted` cast — a
  leaked DB/backup must not expose emails. Recovery responses are identical
  whether the email matches or not (no enumeration).
- **HTTP security headers** applied globally via `SecureHeaders` middleware:
  `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`,
  `Referrer-Policy: strict-origin-when-cross-origin`, and a restrictive
  `Permissions-Policy`. In **production only**, it also sets HSTS
  (`max-age=31536000; includeSubDomains`) and a locked-down
  `Content-Security-Policy` (`default-src 'self'`, fonts from
  `fonts.bunny.net`) — both omitted in dev, where the Vite dev server and
  plain `http://` would break under them.
- **Per-workspace session keys** (`ws_owner_{id}`, `ws_upload_ok_{id}`) so
  unlocking one workspace never leaks into another in the same browser.
- **Workspace isolation** enforced at the query layer by the
  `WorkspaceScope` global scope; `workspace_id` is never mass-assignable.
- **Operator kill-switch** gated by `OPERATOR_SECRET`: checked timing-safe,
  rate-limited (5 / 10 min per IP), held in session (never in a URL), and the
  whole `/operator` surface 404s when the secret is unset. There is no email
  reset by design — rotate by changing the env value and redeploying.
- **Unguessable downloads:** files are served via their random `manage_token`,
  not a sequential id, so they can't be enumerated across workspaces.
- **Abuse throttling:** per-IP rate limits on uploads and workspace creation;
  a fail-closed host-disk check; and a content-hash blocklist that refuses
  re-upload of any file an operator has removed.

For production: set `APP_DEBUG=false`, `SESSION_ENCRYPT=true`,
`SESSION_SECURE_COOKIE=true`, and serve over HTTPS.

## Architecture notes

- **Tenancy (hand-rolled, no package).** `App\Tenancy\Tenancy` is a
  request-scoped singleton holding the current workspace; `ResolveWorkspace`
  middleware sets it from the `{workspace}` route segment (bound by slug via
  `Route::bind`, *not* implicit id binding). `BelongsToWorkspace` (on
  `Course`) adds the named `WorkspaceScope` global scope — every query is
  auto-constrained to the current workspace and `workspace_id` is auto-set
  on create (never mass-assignable). Materials are isolated transitively
  through their course.
- The by-id `/download` and `/materials` routes deliberately run *without*
  the workspace scope; the delete route re-checks the owner session against
  the material's **own** workspace.
- **Inertia + Vue 3 pages** in `resources/js/pages/` (`CoursesPage.vue`,
  `CoursePage.vue`, etc.) talk to thin Laravel controllers; the marketing
  root (`/`) is server-rendered Blade for SEO.
- `Material::fileType()` derives the scanning icon from the filename extension.
- Search uses SQLite's `collate nocase` for A–Z sort — SQLite-specific; revisit
  if the DB driver ever changes.

## Known scope limits (v1)

- Owners can edit and reorder courses, but there is **no workspace edit/
  delete UI** (a workspace, once made, stays). One owner secret per
  workspace owns everything *in that workspace*; lose it and owner control
  is unrecoverable (same trade-off as the per-file delete token).
- No accounts. The owner secret / per-file token are the only credentials;
  no admin/bulk moderation, no soft-delete/restore (hard delete is permanent).
- No pagination; search keeps long lists manageable at expected volume.
- Telegram config is still global (`.env`), not per-workspace — deferred.
