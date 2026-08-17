# Stacked PRs and the orphaned-merge trap

Sometimes a PR is opened against another open PR's branch instead of `main`, so review can happen
incrementally on a large piece of work (e.g. #1816 → #1818, #1809 → #1811, #1853 → #1854). This is a
normal and useful pattern. The trap is what happens *after* the base PR merges.

## The failure mode

1. PR A is opened with base branch `main`. PR B is opened with base branch `A`'s head branch, to review
   B's changes incrementally on top of A.
2. PR A merges into `main`. Its head branch is left in place (or is retargeted automatically - see
   below).
3. PR B is later merged. GitHub merges it into whatever its base ref *currently* points to - if that
   branch was never retargeted to `main`, B merges into the now-stale leftover branch, not `main`.
4. PR B shows **Merged** on GitHub and auto-closes its linked issues. Its code never reaches `main`.
   Nobody notices until someone asks "why can't I see this feature that was supposedly merged?"

This has happened three times in this repo: PR #1811, PR #1854, and PR #1818. Each was only caught
because a user later reported the feature missing, and each needed a manual conflict-resolution recovery
PR (#1876, #1921, #1988 respectively).

## The fix

**Primary defense:** this repo has `delete_branch_on_merge` enabled. When a PR's head branch is deleted
on merge, GitHub automatically retargets any other open PR that was based on that branch to the deleted
branch's own base. This closes the exact gap above - PR B gets moved to target `main` (or wherever A's
own base was) the moment A merges, with no manual step required.

**Backstop:** [`.github/workflows/stale-base-check.yml`](../.github/workflows/stale-base-check.yml) is a
visibility layer on top of the above, because native retargeting is a silent timeline event, not a
comment, and it can't retroactively help a PR that was already open before `delete_branch_on_merge` was
turned on. It:

- Checks a PR's own base whenever the PR is opened, reopened, or pushed to.
- Immediately sweeps for other open PRs when a PR merges, in case any of them were stacked on it.
- Runs a weekly full sweep of all open PRs as a backstop.
- Posts a PR comment (does not currently block merging - see below) when a PR's base branch's own PR has
  already merged elsewhere, or was closed without merging (a distinct "abandoned stack" warning).

**If you get flagged:** retarget the PR's base branch to `main` (or to wherever the base PR's changes
actually landed, if it's part of a longer stack) before merging. If GitHub reports this PR as part of a
detected "stack," you may need to call the `.../stacks/{number}/unstack` API before `gh pr edit --base`
will work.

## What this doesn't catch

- **A base branch merged via direct push**, never having its own PR. The check above looks for a merged
  *PR* with a matching head branch name; a direct push leaves no such PR to find. This is a known,
  accepted blind spot - defending against it would require re-running the same expensive content-diff
  audit described below on every check, which is disproportionate for a scenario that's already a
  workflow-policy violation on its own.
- **A base branch that gets renamed** after a PR is opened against it, before that branch's own PR
  merges. Not special-cased; considered unlikely enough not to be worth it.

## Auditing history for hidden instances

If you suspect there may be other undiscovered instances of this pattern, don't rely on
`git merge-base --is-ancestor <mergeCommit> origin/main` alone - every PR in this repo is squash-merged,
which rewrites history and makes that check produce **false positives** on perfectly fine PRs (verified
directly: this exact check flagged 6 fine PRs alongside the 3 real incidents during the audit that
produced this document). Use the PR-metadata check the CI workflow above uses instead (does a *merged* PR
exist with `head == this PR's base`?), or fall back to comparing actual file content between the PR's
commit and current `main` when in doubt.
