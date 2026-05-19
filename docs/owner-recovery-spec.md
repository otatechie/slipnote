# Owner Link Recovery — Design Spec (v1)

**Status:** proposed, not implemented. Approve before any code.

## Problem

The owner secret/link is shown once and stored only as a bcrypt hash — by
design, unrecoverable. This is the right default for a no-accounts
capability model, but owner-link *loss* is a real, recurring pain (the
`fish` workspace died exactly this way). We want an **opt-in recovery
channel** that does NOT convert the app into an accounts product and does
NOT add friction for classmates.

Non-goals: this is not login, not sessions, not identity, not billing. It is
"email me my owner link back" — nothing more.

## Model

A workspace MAY have one **recovery email**. Optional. Opt-in. If absent,
behaviour is exactly as today (no recovery — the documented trade-off).
Classmates and the create flow are completely unaffected.

### Data

`workspaces` gets one nullable column:

| column         | type             | notes                                  |
|----------------|------------------|----------------------------------------|
| recovery_email | string, nullable | `encrypted` cast — never plaintext at rest |

- Added to `$fillable`? **No** — set via a dedicated method, not mass
  assignment, same discipline as `workspace_id`/owner secret.
- Added to `$hidden` — never serialized into a Livewire snapshot or response.
- `encrypted` cast: a leaked DB/backup must not expose owners' emails.
- Never displayed back in any UI (write-only from the user's view), so a
  shared screen can't leak it.

## Where the email is collected

**NOT at workspace creation.** The receipt screen's single job is "save the
unrecoverable link"; a second input there competes with the critical action
and forces a privacy decision under pressure.

**In the existing owner-only "Manage this board" panel** (course page,
owner mode). A quiet, optional "Recovery email (optional)" subsection:
- Field + Save.
- Plain copy stating exactly what it does and its honest limitation:
  "If you lose your owner link, we'll email it to this address. Anyone with
  access to that inbox can then control this board."
- Opt-in: empty = no recovery, no nagging.

Saving requires being in owner mode (server-side `abort_unless($this->isOwner())`,
same gate as course creation).

## Recovery request flow

A public "lost your owner link?" entry point (small link on the workspace
course page, near the existing "Manage this board" — visible to non-owners
since a locked-out owner is, by definition, not in owner mode).

Flow:
1. User enters an email.
2. Server looks up the current workspace's `recovery_email`.
3. **Only if** the entered email matches the stored one: email the owner
   link to the **stored** address (never to the address just typed).
4. Response to the user is **identical whether or not it matched**:
   "If that email is on file for this board, the owner link has been sent."

### Security design (the crux — must be right)

- **No enumeration oracle.** Identical response on match/no-match. Never
  reveal whether a workspace has a recovery email or what it is.
- **Send only to the stored address**, never the attacker-supplied input.
  (Prevents using the form to mail an owner link to an arbitrary inbox.)
- **Rate limit** per workspace + per IP (e.g. Laravel `RateLimiter`,
  a few attempts/hour) so the form can't be used to spam the owner's inbox
  with owner links or brute-probe emails.
- Timing: the matched path does extra work (send mail). Accept minor timing
  difference, or queue the send so both paths return fast — **queue it**
  (also matches the existing `afterResponse` notifier discipline).
- The recovery email re-sends the **existing** owner link (we can't
  reconstruct the secret — only the hash is stored). So the column that
  actually enables recovery is *the email*; the link itself is derived from
  the stored owner-secret... which we DON'T have.

**DECIDED: (a) rotate on recovery.** We only store the bcrypt *hash* of the
owner secret, so the original link cannot be re-sent. On a successful
recovery request we generate a NEW owner secret, replace `owner_secret_hash`,
and email the NEW owner link. The previous link permanently stops working.
This preserves the core guarantee — *the owner secret is never stored, only
its hash* — and a lost link dying is desirable, not a regression. UI/email
copy must state that recovering invalidates the old link.

## Mailer dependency (unavoidable, honest)

Recovery requires real transactional email. Adds a mailer to the stack
(the README's "no DB server needed" minimalism gains an email dependency):
- Config: `MAIL_*` in `.env` (Postmark/SES/Mailgun/SMTP). Document in
  `.env.example` and README.
- If no mailer configured → the recovery feature must **degrade safely**:
  the "add recovery email" UI is hidden/disabled, so we never promise
  recovery we can't deliver. (Mirror the Telegram "no-op unless configured"
  pattern.)
- Email content: plain, clear sender, minimal, with "check spam" guidance —
  university inboxes aggressively filter external mail; a recovery mail in
  spam is a real failure mode.

## Test plan

`tests/Feature/OwnerRecoveryTest.php`:
- Owner can set a recovery email (owner-mode gated); non-owner cannot.
- `recovery_email` is stored encrypted (cast round-trips; raw column is not
  plaintext).
- Recovery with the correct email rotates the secret and queues a mail to
  the **stored** address; the old owner secret no longer verifies.
- Recovery with a wrong/absent email returns the **identical** response and
  sends nothing (no enumeration; assert `Mail::assertNothingSent`).
- Recovery never sends to the attacker-supplied address (assert the queued
  mail's recipient is the stored email, even if a different one was typed).
- Rate limiting: N+1th attempt is throttled.
- **Isolation:** a recovery request under workspace A can never trigger a
  send for, or reveal, workspace B's recovery email (extends
  `WorkspaceIsolationTest` discipline).
- Mailer-not-configured: the set-email UI is hidden and recovery no-ops.

## Adoption (the part that actually makes this feature real)

A recovery feature nobody opts into is theatre. The mechanism's value
depends entirely on owners setting an email *before* they lose the link.
Two required items:

1. **Non-blocking "no recovery email set" notice** in the owner panel.
   When an owner is in owner mode and `recovery_email` is null, show a quiet
   (not modal, not nagging) line: *"No recovery email set — if you lose your
   owner link, this board can't be recovered. Add one?"* with the field
   right there. This nudge — not the recovery flow — is what drives the
   opt-in that makes the feature matter.

2. **Do NOT soften the creation-time "shown once, not recoverable"
   warning.** It remains literally true for any owner who hasn't opted in.
   Overselling recovery at creation ("don't worry, we can email it back")
   would be the worst outcome: an owner believes they're safe, isn't, and
   loses the board. The warning stays; recovery is framed only as an
   after-the-fact opt-in safety net in the owner panel.

## Scope boundaries (v1)

- One recovery email per workspace. No multi-owner, no change history.
- No sessions/login. Recovery returns control by re-issuing the (rotated)
  owner link, nothing more.
- Opt-in only. No email = today's behaviour, unchanged. The zero-PII
  default remains the default.
- Classmates and the workspace-creation flow are untouched.

## Decisions needed from you before code

1. **Open decision A:** rotate the owner secret on recovery (recommended)
   vs. additionally store it encrypted to re-send the same link.
2. Confirm placement: owner-panel, not creation (recommended).
3. Confirm the honest UI copy that recovery emails the link and whoever
   controls that inbox controls the board (no overselling it as "secure").
4. Acknowledge the new mailer dependency + the no-mailer graceful-degrade
   behaviour.
