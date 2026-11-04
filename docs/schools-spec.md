# School Spaces — Design Spec (v1)

**Status:** proposed, not implemented. Build only when the trigger below is met.

## Trigger: when to build

At least one school has replied through `/schools` and agreed to pilot it.
Replies land in the operator dashboard's **Suggestions** tab, marked
`from /schools` (and in Telegram when it is set up). Until then, this file is
the plan, not a task list.

## What we have already promised in public

`/schools` (resources/views/schools.blade.php) lists four things under
**Become a pilot school**, and makes one offer. Building this spec is how we
keep them. Changing any of them is a business decision, not a copy edit; the
page's test pins the wording.

1. Each teacher manages their own class's board.
2. The school stays in control when staff leave or lose a link.
3. One page shows every class.
4. More storage, and help setting up.

**Offer:** pilot schools get a lower price for life.
Record which schools were pilots (see Data) so the promise can be honoured
when pricing exists.

## Problem

Every board has one owner secret. A school with twenty classes has twenty
unrelated boards and twenty owner links, usually held by whoever made each
board. When a teacher leaves or loses the link, nobody can manage that class's
board; with mail off, recovery doesn't help either. A head teacher sees that
risk at once, and it is what they would pay to remove.

Non-goals: accounts, passwords, single sign-on, grading, messaging, parent
access, per-pupil tracking. SlipNote stays "a link with the files on it".

## Model

A **school** owns many boards. It has:

- one **school admin link**: a secret held by the head or IT lead, bcrypt-hashed
  like an owner secret. It opens the school page and can do anything an owner
  can, on every board in the school;
- **staff links**: one per teacher per board, issued from the school page.
  A staff link unlocks owner mode on its board only, and the school can revoke
  it at any time without touching anyone else's.

Boards keep their own owner secret, so nothing changes for a board that never
joins a school.

### Data (all additive, safe on live rows)

- `schools`: id, name, slug (random suffix, like boards), admin_secret_hash,
  pilot (boolean), storage_bytes (nullable: null = sum of its boards' caps),
  notes (operator-only), timestamps.
- `workspaces.school_id`: nullable foreign key, indexed. Null for every
  existing board.
- `staff_links`: id, workspace_id (indexed), label ("Mrs Asante, English"),
  token_hash (unique), created_at, revoked_at (nullable), last_used_at.

Migrations must follow AGENTS.md: nullable columns, no unique index that live
rows could break, a new migration for every change.

## Features, in build order

### 1. Staff links (promise 1 and 2)

- From the school page: pick a board, type a label, get a link
  `/{board}?staff=TOKEN`. Shown once, like the owner link.
- Redeeming works like `?owner=`: one GET, redirect to the clean URL, session
  holds the unlock. Store only a hash of the token.
- Revoke: sets `revoked_at`; any session unlocked by that link stops working on
  its next request (same fingerprint approach as owner-secret rotation).
- A staff link can't see or change other boards, the school page, or other
  staff links.

### 2. School page (promise 3)

`/school/{slug}`, unlocked by the admin link. One row per board: name,
courses, files, storage used and percent, last opened, visits in 30 days,
open reports, staff links (with revoke). No pupil-level data, ever.

### 3. School recovery (promise 2, "lose a link")

From the school page the admin can: issue a fresh owner link for any of its
boards (retiring the old one), and add a board to the school by pasting that
board's current owner link (proof of control). Losing the admin link itself:
the operator restores it by hand after checking with the school, until there's
something better.

### 4. Storage and setup (promise 4)

- Shared storage: a school's boards draw on one pool when
  `schools.storage_bytes` is set; `Workspace::storageRemaining()` checks the
  pool for boards with a `school_id`.
- Setup help is a service, not code: we make the boards and send one printable
  QR poster per class. A poster page (`/{board}/poster`, print stylesheet,
  board name + QR + short instructions) would make that quick.

### Later, only if pilot schools ask

- Reports on a school's boards shown on the school page, so the school can act
  first.
- Start-of-term setup: create a board per class from a list; archive last
  term's boards in one action (archiving already exists).

## Operator side

- Operator dashboard: create a school, mark it as a pilot, attach boards, see
  its total storage. Archive/delete keep working per board.
- The Suggestions tab is the sales inbox until something better is needed.

## Children (policy already published)

Terms and Privacy allow under-13s only on a teacher-run board with uploads
locked. School boards with younger pupils should default to uploads locked,
and the school page should show any board where they aren't.

## Test plan

- Cross-school isolation: an admin link for school A can't see, unlock or
  change anything in school B; a staff link for board X can't touch board Y,
  even inside the same school.
- Revoked staff links stop working on the next request, including in sessions
  already unlocked.
- Tokens and secrets: only hashes stored, nothing in props, logs or Telegram.
- Boards without a school behave exactly as today (existing suite stays green).
- Storage pool: uploads stop at the pool limit, not the per-board limit.
- Every new route gets a cross-board test (AGENTS.md).

## Decisions needed before code

- Price and billing (per school per term? by number of classes?) and what
  "lower price for life" means in numbers for pilots.
- Whether a school admin can delete boards, or only archive them.
- Where the admin link is kept safe if mail stays off.
