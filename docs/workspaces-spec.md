# Multi-tenant Workspaces — Design Spec (v1)

**Status:** proposed, not implemented. Approve before any code.

## Problem

Today the app is single-tenant: one global course list at `/`, one global
`OWNER_SECRET`, one global `UPLOAD_PASSPHRASE`. Multiple unrelated people
(different programs, different levels) each want their *own* board to share
with *their own* classmates — and it must spread by link with zero setup
(no deploying, no accounts), because the users are students, not developers.

Separate deployments were rejected: the users won't self-host, and the goal
("so their friends can use it") is unbounded. Multi-tenancy is the only model
that preserves the app's core property — spread by link, zero setup.

Frontend stays **Livewire**. Tenancy is a backend scoping/routing concern;
the view layer is orthogonal and needs no rewrite.

## Core model

A **Workspace** is an isolated space: its own courses, materials, owner
credential, and optional upload passphrase. Workspaces never see each other.

No user accounts. A workspace is created from a link; the creator receives a
one-time **owner link** (same philosophy as the existing per-file
`manage_token`). Holding the owner secret = control of that workspace only.

### Data model

New table `workspaces`:

| column            | type                          | notes                                            |
|-------------------|-------------------------------|--------------------------------------------------|
| id                | pk                            |                                                  |
| name              | string                        | display name, e.g. "CS Masters 2026"             |
| slug              | string, unique                | URL segment; derived from name, collision suffix |
| owner_secret_hash | string                        | `Hash::make()` of the owner secret (never plain) |
| upload_passphrase | string, nullable              | per-workspace; null/empty = open uploads         |
| created_at        | timestamp                     |                                                  |

`courses` gets `workspace_id` (fk, indexed, cascade on workspace delete).
`materials` inherits scope transitively through `course` (no direct column —
a material's workspace is its course's workspace; keeps the model honest and
avoids a denormalised field that could drift).

Migrations (new files, do not edit existing):
1. `create_workspaces_table`
2. `add_workspace_id_to_courses_table` — nullable first, backfill, then
   enforce. A data migration assigns all existing courses to a single
   "legacy" workspace so nothing 404s post-deploy.

### Models

- `Workspace`: `hasMany(Course)`. Methods: `verifyOwner(string $given): bool`
  (`Hash::check`), `uniqueSlug()` (moved/shared with course slug logic).
- `Course`: add `belongsTo(Workspace)`. **`$fillable` unchanged** — workspace
  is set via the relationship, never mass-assigned from request input.
- `Material`: add `workspace()` accessor via `course->workspace` for
  convenience (read-only; not a stored column).

## Routing

| now                | becomes                          |
|--------------------|----------------------------------|
| `/`                | landing: create or open a workspace |
| `/` (course list)  | `/{workspace}` — that workspace's course list |
| `/c/{slug}`        | `/{workspace}/c/{slug}`          |
| `/download/{m}`    | unchanged (material id is globally unique; route still verifies existence) |
| `/materials/{m}/{token}` | unchanged (token/owner-session check stays) |

The `{workspace}` segment resolves via route-model binding on `slug`
(`firstOrFail` → 404 on unknown workspace). Livewire components read it in
`mount()`, exactly as `course-page` already takes `$slug`.

**Owner mode** moves from global config to per-workspace:
`/{workspace}?owner=SECRET` → `Workspace::verifyOwner()` (timing-safe via
`Hash::check`) → `session(['ws_owner_'.$workspace->id => true])`. Session key
is **per-workspace** so unlocking one never unlocks another.

**Upload passphrase** moves from global config to the workspace row; the
existing session-unlock becomes per-workspace
(`ws_upload_ok_<workspaceId>`).

## Workspace creation (no accounts)

`/` shows: "Open a workspace" (enter/remember a slug) and "Create a workspace".

Create flow:
1. Enter a name → slug derived (collision → numeric suffix, reusing existing
   `uniqueSlug` logic).
2. Server generates a strong random owner secret (`Str::random(40)`), stores
   only `Hash::make()` of it.
3. Creator is shown the owner link **once**:
   `/{slug}?owner=<secret>` — same one-time-receipt pattern as the uploader
   `manageUrl`. We never store or re-show the plaintext secret.
4. Optional: set an upload passphrase at creation (or later in owner mode).

This keeps the no-accounts ethos: a workspace is a capability URL + an owner
capability URL. Losing the owner secret = losing owner control (documented;
acceptable for v1, mirrors the existing per-file token model).

## Security — the crux

Tenant isolation is the one thing that must be provably correct. Rules:

1. **Every course/material query is workspace-scoped.** No bare `Course::` /
   `Material::` in the components — always `$workspace->courses()...`. A
   missed scope = cross-tenant data leak. This is the #1 review focus.
2. **Owner session is per-workspace** (`ws_owner_<id>`). Verified: owning A
   gives zero rights in B.
3. **Upload passphrase is per-workspace.** Unlocking A's uploads never
   unlocks B's.
4. **Download route** (`/download/{material}`) is by global id. It already
   only checks file existence. Decision: keep it open (a material id is
   unguessable enough? — NO, ids are sequential). **Change required:**
   downloads must verify the material belongs to a workspace the request can
   reach, OR switch material routes to be workspace-prefixed and
   slug/token-based. Flagged as an open decision below.
5. **Delete route** keeps token-or-owner logic, but the owner check must be
   scoped to the material's workspace, not a global session flag.
6. Owner secret stored **hashed** (`Hash::make`), never plaintext, never
   logged. The existing token-redaction discipline extends here.

### Open decision (needs your call before build)

Sequential material ids mean `/download/5` is enumerable across tenants
today. Two options:
- **(a)** Add a workspace check to the download/delete routes (smaller
  change, ids stay sequential but access is gated).
- **(b)** Move material routes under `/{workspace}/...` and bind by a random
  token instead of id (cleaner, larger change, also fixes enumeration).

Recommendation: **(a)** for v1 (contained, testable), revisit (b) if
enumeration is a real threat for the content.

## Test plan (isolation is the deliverable)

New `tests/Feature/WorkspaceIsolationTest.php`, covering:

- Workspace A's course list never shows B's courses.
- `/{a}/c/{slug}` 404s for a slug that exists only in B.
- Owner secret for A does **not** unlock owner mode in B.
- A's upload passphrase does **not** unlock uploads in B.
- Download/delete of B's material is denied from A's context (per open
  decision above).
- Slug collision across workspaces is allowed (A and B can both have
  `phys-201`); collision *within* a workspace is suffixed.
- Workspace creation: secret is hashed, shown once, never persisted plain.
- Existing single-tenant tests are migrated to run inside a workspace
  (legacy-workspace fixture in `setUp`).

Existing 37 tests must be adapted (wrap in a workspace), not deleted —
behaviour inside a workspace is unchanged.

## Scope boundaries (v1)

- No workspace edit/delete UI (mirrors "no course edit/delete" v1 limit).
- No per-workspace branding/theming.
- No accounts, no email — owner secret is the only credential.
- No cross-workspace search or directory (workspaces are private by link).
- Telegram config: becomes per-workspace (token + chat id move from `.env`
  to the workspace row, nullable). Out of scope for v1 *unless* wanted —
  flag: keep global Telegram off by default, defer per-workspace Telegram.

## Migration / rollout

1. Ship migrations with a backfill into a single "legacy" workspace so
   existing URLs keep working (legacy at a reserved slug, e.g. `main`).
2. Old `/` and `/c/{slug}` → redirect to `/main` and `/main/c/{slug}` so no
   shared link breaks.
3. README + `.env.example` updated: global `OWNER_SECRET` /
   `UPLOAD_PASSPHRASE` become legacy-only (used to seed the `main`
   workspace on first migrate); new workspaces carry their own.

## Effort estimate (honest)

Backend-only; Livewire unaffected as a framework. Roughly:
- Migrations + models + backfill: small.
- Routing + per-workspace owner/passphrase: medium.
- Component query re-scoping (both Livewire files): medium, highest risk.
- Creation flow + one-time owner link: small-medium.
- Isolation test suite + migrating existing 37 tests: medium.

Biggest risk is (3): a single un-scoped query is a tenant leak. Mitigated by
the isolation test suite being written *first* (red), then made green.

## Decisions needed from you before code

1. Download/delete enumeration: option **(a)** gate, or **(b)** tokenised
   workspace-prefixed routes? (Spec recommends a.)
2. Legacy data: is there real data to preserve, or can the "legacy
   workspace" be skipped entirely (fresh start, no backfill)?
3. Per-workspace Telegram: defer (recommended) or include in v1?
4. Confirm Livewire (no Inertia) — spec assumes Livewire; nothing here
   needs otherwise.
