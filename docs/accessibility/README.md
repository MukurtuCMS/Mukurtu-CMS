# Accessibility Program

Mukurtu must be WCAG 2.1 AA compliant (should be WCAG 2.2 AA) and ATAG 2.0 compliant. This directory documents how accessibility findings get filed, triaged, and closed out.

Findings live as GitHub issues under the umbrella issue [#2008](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/2008). See `findings-index.md` in this directory for the current status of every sub-issue.

## Scan tooling

Some early findings issues reference an automated helper (`checkTextZoom` in `tests/playwright/src/helpers/automated-checks.ts`) and a baseline scan file (`findings/2026-07-baseline.md`). **Neither exists in this repo.** They were referenced by issue text but never actually committed — don't assume they exist, and don't spend time looking for them. Confirmed missing during work on issues #1998 and #2003.

`axe-core` isn't vendored in this repo either. Live verification during this program's work has used `axe-core` loaded ad hoc from a CDN (`https://cdn.jsdelivr.net/npm/axe-core@4/axe.min.js`) inside a throwaway Playwright script against a running DDEV instance — not a committed test. If a permanent, CI-integrated a11y test harness gets built, that's a real gap worth closing (raised as a possible follow-up, not yet scoped as its own issue).

## Filing a finding

- Title format: `[WCAG X.Y.Z] Short description` for a specific success criterion, or `[best-practice]` for an axe best-practice rule that isn't a numbered WCAG failure.
- Link it as a sub-issue of #2008.
- Include: the axe rule name, severity/impact, affected page(s), and enough markup context to reproduce without re-scanning.

## Triaging a finding

1. **Verify live, don't trust the issue text alone.** Findings can be stale, imprecise (see #2003's "six" vs. the seven actually reproduced), or describe the wrong root cause entirely (see #1998, where the originally-suspected cause turned out not to be the real one — bisecting by hiding page sections and re-measuring found the true source). Reproduce with a real scan or real browser interaction before writing a fix.
2. **Identify whose code it is.** If the flagged markup comes from Mukurtu's own theme/module code, fix it locally. If it comes from a vendored/contrib dependency (a Composer package, an npm-vendored JS bundle, a contrib theme like Gin), check whether it's already fixed in a newer available version first.
3. **For vendored/contrib bugs:** search the upstream project's issue queue before filing — it's often already reported (see #2003, where all three findings turned out to already be tracked in Gin's and Drupal core's own queues). Link existing upstream issues rather than duplicating them. Only apply a local override/patch when the bug is genuinely blocking and no upstream fix is imminent (see #2000's ALTCHA fix: a small local CSS/JS patch was applied *and* the bugs were also reported upstream, since the local fix doesn't remove the need to fix the actual source).
4. **Watch for scope creep discoveries.** Investigating one finding sometimes surfaces a second, unrelated bug (see #1998 → #2051 → the real fix landing back in #1998's own PR once #2051 turned out to be a false lead; see #2003 surfacing three separate but related upstream issues). File what's genuinely separate as its own issue rather than silently expanding the PR in front of you.

## Closing a finding

- If fixed locally: reference the fixing PR, close.
- If it's a vendored/contrib bug fully resolved by a local override: reference the PR *and* the upstream issue link, close.
- If it's a vendored/contrib bug with no local fix applied (fix belongs entirely upstream): leave it open as a tracking placeholder with the upstream issue link(s), to be closed once the dependency is updated and the fix is verified. Don't close prematurely just because there's nothing left for Mukurtu to do right now.
