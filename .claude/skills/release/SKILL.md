---
name: release
description: Push SlipNote commits to main with one annotated tag and one GitHub release per commit. Use when asked to push, tag, release or ship.
disable-model-invocation: true
---

# Releasing SlipNote

`main` deploys to the live site, so only do this when the user has asked in
this conversation. Approval for an earlier push doesn't carry over.

1. **Check.** The working tree is clean, `php artisan test --compact` passes,
   and `npm run build` succeeds. List the unreleased commits:
   `git log --oneline <latest tag>..HEAD`, where the latest tag is
   `git tag --sort=-v:refname | head -1`.
2. **Number each commit**, oldest first, from the latest tag: a minor bump
   (`v3.32.0`) for a feature, a patch bump (`v3.31.1`) for a fix.
3. **Merge and push.** From the branch: `git switch main`,
   `git merge --ff-only <branch>`, `git push origin main`.
4. **Tag each commit**, annotated, message = title:
   `git tag -a vX.Y.Z <sha> -m "vX.Y.Z — <commit subject>"`, then
   `git push origin --tags`.
5. **Release each tag**, oldest first. Write the notes to a file, then:
   `gh release create vX.Y.Z --verify-tag --title "vX.Y.Z — <subject>" --notes-file <file> --latest=false`.
   Pass `--latest` only on the newest one.
6. **Watch CI:** `gh run list --branch main --limit 1`, then `gh run watch`.
7. Delete the merged local branch and tell the user what shipped, plus
   anything to check after the deploy.

## Release notes

Written for the operator reading them later, not for developers. Open with
one line saying what changed for users and whether there are migrations
("No migrations." or "One migration: …"). Then short bullets in plain words:
what a student or owner now sees, and why. Describe the state at that commit.

## If git has no identity

Commit and tag as the repo's author, without changing global config:
`git -c user.name="Ato Augustine" -c user.email="30342985+otatechie@users.noreply.github.com" …`
