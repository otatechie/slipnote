# SlipNote

A dead-simple materials board for students. No accounts, no login — anyone
with the link can browse, download, and contribute course files across three
sections: **Notes, Slides, Past Papers**.

**Workspaces (multi-tenant).** The site root (`/`) lets anyone create or
open a *workspace* — an isolated board with its own courses, owner secret,
and optional upload passphrase. Workspaces never see each other. Creating one
yields a one-time **owner link** (the only credential), the same capability-URL
idea as the per-file delete token.

Built with Laravel 13 + Inertia + Vue 3, Tailwind v4, SQLite.

## Features

- **Anonymous upload** — pick a section, optional title and your name, attach
  one or several files at once. PDF / Word / PowerPoint / image, up to 25 MB
  each. Files that would exceed the workspace cap are skipped while the rest
  save.
- **Uploader delete** — each upload gets a secret token; the success banner
  shows a one-time "Remove it" link. Holding the token is the only credential.
- **Owner mode** — visit `/<workspace>?owner=SECRET` to unlock a per-row
  "Delete" on every file for the session; "Lock board" leaves owner mode on
  the current device. The secret is per-workspace, stored only as a bcrypt
  hash, and shown once at creation.
- **Optional upload passphrase** — each workspace can gate uploads behind its
  own passphrase (entered once per session). Empty = open (the default).
- **Optional Telegram notifications** — set `TELEGRAM_BOT_TOKEN` and
  `TELEGRAM_CHAT_ID` to post a one-line notice on every upload. Outbound only,
  sent after the response and failure-swallowed. Empty = disabled.
- **Reporting & operator moderation** — every file has a Report button; reports
  surface on an operator dashboard at `/operator` (gated by `OPERATOR_SECRET`).
  The operator can **Remove** any file (blocklisting its content hash) or
  **Dismiss** false reports.
- **Anonymous download** — files grouped by section with file-type icons,
  addressed by an unguessable per-file token (not a sequential id).
- **Live search + sort** — filter by title and filename; sort Newest / Oldest /
  A–Z.
- Dark mode, mobile-optimised, and accessible (WCAG AA contrast, `aria` on
  form errors).

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
# No storage:link needed — uploads live on the private disk and stream
# through the app.

npm run build                # or `npm run dev` for hot reload
php artisan serve
```

## Workspaces & courses

The site root (`/`) is the workspace landing: **create** a workspace (save the
one-time owner link) or **open** an existing one by name. Create and manage
courses in **owner mode** (`/<workspace>?owner=SECRET`): add, edit code/title
(the slug stays fixed so shared `/c/<slug>` links keep working), reorder, and
bulk-delete files. Course slugs are unique *within* a workspace.

### Owner recovery (opt-in)

Lose the owner link and the board is unrecoverable — unless the owner set a
**recovery email** (encrypted at rest, owner-mode only). Visit
`/<workspace>/recover`, enter that email, and the owner secret is rotated and a
fresh link emailed back; the old link stops working. Responses are identical
whether the email matches or not (no enumeration), rate-limited, and the
feature is hidden when the mail driver is `log` or `array`.

## Tests

```bash
php artisan test                          # full suite (109 passing)
```

`WorkspaceIsolationTest` is the security crux — it proves workspace A cannot
read, reach, or mutate workspace B's data. Suites run *inside* a workspace via
the `InteractsWithWorkspace` trait.

## Security

- **Owner secret** stored only as a bcrypt hash; checks are timing-safe via
  `Hash::check`. The owner-unlock form is rate-limited per workspace. Recovery
  rotates the secret, invalidating the old link.
- **Recovery email encrypted at rest**; recovery responses don't reveal whether
  an email matched (no enumeration).
- **HTTP security headers** via `SecureHeaders` middleware: `nosniff`,
  `SAMEORIGIN`, `Referrer-Policy`, restrictive `Permissions-Policy`. Production
  also adds HSTS and a locked-down CSP (`default-src 'self'`, fonts from
  `fonts.bunny.net`) — omitted in dev where Vite and plain `http://` would
  break under them.
- **Workspace isolation** enforced at the query layer by the `WorkspaceScope`
  global scope; `workspace_id` is never mass-assignable. Per-workspace session
  keys (`ws_owner_{id}`, `ws_upload_ok_{id}`) so unlocking one never leaks into
  another.
- **Operator kill-switch** gated by `OPERATOR_SECRET`: timing-safe, rate-limited,
  session-held (never in a URL); `/operator` 404s when unset.
- **Abuse throttling** — per-IP limits on uploads and workspace creation, a
  fail-closed host-disk check, and a content-hash blocklist refusing re-upload
  of operator-removed files.

For production: set `APP_DEBUG=false`, `SESSION_ENCRYPT=true`,
`SESSION_SECURE_COOKIE=true`, and serve over HTTPS.

## Architecture notes

- **Tenancy (hand-rolled, no package).** `App\Tenancy\Tenancy` is a
  request-scoped singleton; `ResolveWorkspace` middleware sets it from the
  `{workspace}` slug. `BelongsToWorkspace` adds the `WorkspaceScope` global
  scope so every query is auto-constrained and `workspace_id` auto-set.
- The by-id `/download` and `/materials` routes deliberately run *without* the
  scope; the delete route re-checks the owner session against the material's
  own workspace.
- **Inertia + Vue 3 pages** in `resources/js/pages/` talk to thin controllers;
  the marketing root (`/`) is server-rendered Blade for SEO.
- Search uses SQLite's `collate nocase` for A–Z sort — revisit if the driver
  changes.

## Known scope limits

- No workspace edit/delete UI (a workspace, once made, stays).
- No accounts. Bulk moderation is limited to owner bulk-delete and the operator
  kill-switch; no soft-delete/restore (hard delete is permanent).
- No pagination; search keeps long lists manageable.
- Telegram config is global (`.env`), not per-workspace — deferred.
