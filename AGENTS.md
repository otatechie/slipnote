# Working on SlipNote

Read this before changing anything. It's the part of the project that isn't in
the code: how it's run, what must never break, and how changes ship. The repo
is public, so nothing secret or infrastructure-specific belongs here.

SlipNote (slipnote.co) lets a class share course files on a board, with no
accounts. One person builds and runs it.

## Stack

Laravel 13, Inertia v3 + Vue 3 (`<script setup>`), Tailwind v4, SQLite in
production. The operator dashboard is Blade. Docker image:
`serversideup/php` (fpm-nginx), deployed with Dokploy behind Cloudflare.

## Commands

- Tests: `php artisan test --compact`. CI runs the same, plus `npm run build`.
- Build assets: `npm run build`.
- Local dev serves at `http://slipnote.test` (lerd). Lerd's PHP can't read
  `/tmp`: put throwaway PHP files or SQLite copies under `storage/` and delete
  them after.
- Browser checks: Playwright from `node_modules`, with
  `executablePath: '/usr/bin/google-chrome'` if its own browser isn't installed.

## Rules that protect users

- **Board isolation.** Course data is scoped to the current board by
  `WorkspaceScope` (`app/Tenancy`, trait `BelongsToWorkspace`). Never query
  across boards without it, except by-id routes that resolve their own tenant
  through `withoutWorkspaceScope()`. A cross-board test goes with any new
  route.
- **No accounts, only capability links.** The owner holds a secret
  (`?owner=`, bcrypt-hashed). An uploader holds a per-file `manage_token`. The
  operator holds `OPERATOR_SECRET`. Never log, display or share these, and
  never hand a visitor a `manage_token`.
- **Uploads stay private.** Files live on the `local` disk
  (`storage/app/private`) and download only through their `download_token`
  route. Nothing goes on the public disk.
- **Production runs a strict CSP; local dev doesn't.** See
  `SecureHeaders.php`. Anything that loads blob:, external or inline resources
  has to be checked against that policy, or it works locally and fails live.
- **Links are built from `APP_URL`**, never the request's Host header.

## Rules that protect the data

- **Migrations run on the live SQLite file at container start**, from
  `docker/entrypoint.d/99-slipnote-migrate.sh`. The database is copied first;
  if the copy or a migration fails, the start aborts and the old container
  keeps serving. So a migration must be safe on existing rows: no unique index
  that existing data might break, no destructive change without a backfill.
- **Never edit a migration that has shipped.** Add a new one.
- Production keeps the database and the uploads in one volume, backed up
  nightly off-site. A change to storage paths must keep both in that volume.

## Mistakes already made here

Each of these shipped or nearly shipped once. Check for them.

- Testing only locally what the production CSP blocks. The photo shrinker
  silently fell back to full-size photos, GPS included, from the day it
  shipped.
- The `local` disk has `throw => false`: a failed write returns `false`, not
  an exception. Check the result of `store()` and friends.
- Inertia is v3: the error events are `httpException` and `networkError`
  (visit options `onHttpException`, `onNetworkError`), not v1's `invalid`
  and `exception`. Return `false` to stop the raw-HTML error pop-up.
- In feature tests, `withHeaders()` persists for every later request in the
  same test. Call `flushHeaders()` between requests that need different ones.
- A GET carrying `X-Inertia` without the asset version gets a 409, not the
  page.
- A unique index or `NOT NULL` column added to a table that has live rows
  will abort the deploy if any existing row breaks it.

## How to change things

- Behaviour changes come with tests. Run the whole suite before committing.
- Match the code around you. Comments say *why*, briefly; no comment
  restating the code.
- UI and error copy is plain and short, addressed to a student on a phone:
  say what happened and what to do next. Use typographic apostrophes (’) in
  PHP strings shown to users.
- Work on a branch. Commit only when asked. Never push, tag, release or deploy
  without being asked each time: `main` deploys to a site people are using.

## Releases

One commit → one annotated tag → one GitHub release, titled
`vX.Y.Z — <commit subject>`. Minor bump for a feature, patch bump for a fix.
The `release` skill in `.claude/skills/release/` has the steps.
