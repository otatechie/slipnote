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

Built with Laravel 13 + Livewire 4 (single-file components), Tailwind v4, SQLite.

## Features

- **Anonymous upload** — pick a section, optional "what is this?" title and
  your name, attach a file. PDF / Word / PowerPoint / image, up to 10 MB.
  Validated the moment a file is picked, not just on submit.
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
- **Anonymous download** — files grouped by section, file-type icons for quick
  scanning.
- **Live search + sort** — global filter (matches title *and* filename) that
  keeps the section grouping; sort by Newest / Oldest / A–Z. Designed for the
  find-fast student mental model at a full semester's volume (~60+ files).
- **Quick-jump nav** — sticky section pills with live counts.
- **Collapsible upload form** so the (mostly read-only) page leads with content.
- Accessible: WCAG AA contrast, `aria` wiring on form errors, actionable
  empty states.

## Requirements

- PHP 8.2+, Composer
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
php artisan db:seed          # creates the single demo course

npm run build                # or `npm run dev` for hot reload
php artisan serve
```

> **Gotcha:** if you run `npm run dev` and the process is force-killed, a stale
> `public/hot` file can remain and break asset loading (the page goes JS-dead).
> Delete `public/hot` and use `npm run build`, or restart `npm run dev`.

### Demo data (to test the UI at realistic volume)

```bash
php artisan db:seed --class=DemoMaterialsSeeder
```

Generates a full semester's worth of materials (~61 files: 30 notes, 20 slides,
8 past papers, 3 announcements) with real files on disk so downloads work.
Idempotent — re-running wipes and regenerates the demo course's materials.

## Workspaces & courses

The site root (`/`) is the workspace landing: **create** a workspace (you’re
shown a one-time owner link — save it) or **open** an existing one by name.
Inside a workspace (`/<workspace>`) is its course list. Create courses in
**owner mode** — open `/<workspace>?owner=SECRET` and use "New course".
Course slugs are unique *within a workspace* (two workspaces may both have
`phys-201`). There is no workspace/course edit or delete UI.

## Tests

```bash
php artisan test tests/Feature/UploadTest.php
```

Covers upload (valid / oversized / wrong-type), HTML-stripping of user input,
optional title, file-type bucketing, and search/sort behavior.

`WorkspaceIsolationTest.php` is the security crux — it proves workspace A
cannot read, reach, or mutate workspace B's data. The existing suites run
*inside* a workspace via the `InteractsWithWorkspace` trait.

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
- Single Livewire 4 component: `resources/views/components/⚡course-page.blade.php`
  (logic + template in one file). The submit method is `save()` — **not**
  `upload()`, which Livewire reserves for its JS file-upload mechanism.
- `Material::fileType()` derives the scanning icon from the filename extension.
- Search uses SQLite's `collate nocase` for A–Z sort — SQLite-specific; revisit
  if the DB driver ever changes.

## Known scope limits (v1)

- Workspaces isolate boards, but there is **no workspace or course edit/
  delete UI** (once made, it stays). One owner secret per workspace owns
  everything *in that workspace*; lose it and owner control is unrecoverable
  (same trade-off as the per-file delete token).
- No accounts. The owner secret / per-file token are the only credentials;
  no admin/bulk moderation, no soft-delete/restore (hard delete is permanent).
- No pagination; search keeps long lists manageable at expected volume.
- Telegram config is still global (`.env`), not per-workspace — deferred.
