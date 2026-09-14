# Accessibility Findings — Post-Merge Verification (2026-09-08)

Re-ran the full automated suite after merging `origin/main` into
`AM-accessibility-program` (71 commits: 4.0.0-rc version bump, Local Contexts
Hub production endpoint, comment-management streamlining, dashboard/Visitors
link cleanup, the #1761 default-settings review, Layout Builder fixes,
content-moderation workflow split by bundle, CSV import/export
translation-awareness (#1260 Phase 5), Search API view migrations, several
contrib/security bumps, and misc fixes — see `git log
9abf50547..origin/main` for the full list). Method as in the [July 27
post-merge pass](2026-07-27-post-merge-verification.md): axe-core via
`tests/playwright/tests/accessibility.spec.ts` and the second
`accessibility-automated-checks.spec.ts` layer, against a local DDEV site,
anonymous and as `a11y_member` (`authenticated` role only).

## Result: full inventory — still clean, zero new findings

**All 19 inventory entries (anonymous + member, 38 total test cases across
both spec layers) pass**, with only the same already-known, already-filed
findings present — nothing new from this merge.

- **`image-alt` (critical), axe:** `dictionary-word` and
  `member-dictionary-word` only. Same PDF-thumbnail defect as every prior
  cycle. [#1995](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1995).
- **`aria-hidden-focus` (serious), axe:** `browse` only, same decorative
  card-link defect as every prior cycle.
  [#2001](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/2001).
- **Reflow (1.4.10):** the sitewide ~5px overflow at 320px, unchanged, on
  every applicable page. Still not root-caused.
  [#1997](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1997).
- **Text zoom (1.4.4):** `dictionary-browse`, `community-page`, and
  `member-community-page` overflow at 200% text size (1491–1507px vs
  1280px), same shared layout issue and same three pages as the July 27
  pass. [#1998](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1998).
- **Focus visible (2.4.7):** same dictionary-word-page elements, unchanged.
  [#1999](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1999).
- **Keyboard trap (2.1.2), still correctly flagged only for manual
  confirmation, not a defect:** `browse`, `digital-heritage-browse`, and
  `dictionary-word` (both anonymous and member) report "focus not
  advancing" on native `<audio>`/lazy-media elements — the same shadow-DOM
  detection limitation documented since the July baseline. No new false
  positives, no new pages affected.
- **Link text (2.4.4/2.2):** zero findings everywhere, same as baseline.

## Environment notes (not accessibility findings)

Two things came up getting to the above result — neither is a WCAG defect,
but both would have produced misleading results (or blocked the scan
entirely) if left alone.

### 1. Duplicate `hook_update_N()` from the merge

Same recurring class of conflict as every prior merge cycle: both this
branch and `main` had independently added `mukurtu_core_update_40106()` —
this branch's Gin-accent-contrast fix (carried since the July baseline) and
main's content-browser view language-fallback filter
(`views.view.mukurtu_content_browser`). Unlike most previous cycles this one
surfaced as a real conflict (not a silent same-name overwrite), so git
stopped and flagged it correctly. Resolved by keeping main's entire
40106–40122 block intact and renumbering this branch's hook to `40123` —
verified by comparing the merged file's function count (127) against the
sum of each parent's unique additions (126 main + 1 ours), not just by
grepping for duplicate names. `MukurtuLeafletFormatter.php` also textually
overlapped (main's PHP 8 attribute migration vs. this branch's marker-naming
fix) but merged automatically and correctly — confirmed by reading the
merged file directly, both changes present.

### 2. Stale hook-implementation cache broke `/admin/content` after the merge

Main's commit `b3f4a48e7` removed `mukurtu_workflows_views_post_build()`
(replacing a Views post-build hook that "silently never executed" with a
working `hook_form_alter()` — see that commit's own message) as part of
this merge. The local site's compiled cache still referenced the old
procedural hook by name, so any page routing through it fataled with
`InvalidArgumentException: Class "mukurtu_workflows_views_post_build" does
not exist`. Not a code defect — resolved with the standard post-merge
`drush cr` + `drush updb -y` (applies all pending update hooks, including
this branch's renumbered `40123`); confirmed fixed by reloading
`/admin/content` as an authenticated admin and checking `drush
watchdog:show` for a fresh (not stale) occurrence.

## ACR status

No change. Scan result is identical to every prior clean cycle — same known
findings, same pages, nothing newly broken or newly fixed by this merge.

## Remaining actions carried forward

Unchanged from the July 27 pass: the sitewide/content-page reflow overflow,
the `dictionary-browse`/`community-page`/`member-community-page` text-zoom
overflow, and the dictionary-word focus-visible failures are all still open.
Manual pass and upstream toolbar triage are also still outstanding. Also
still open from that pass: the ALTCHA login-checkbox manual keyboard/screen
reader pass, and the `mukurtu_protocol.community_organization` stale-
placeholder fix.

This branch (`AM-accessibility-program`) and the shared
`origin/AM-accessibility-program` branch (where Michael Wynne has been
independently merging `origin/main`, e.g. through the 4.0.0-rc bump) remain
diverged as of this cycle — this merge pulled from `origin/main` directly,
per instruction, not from the shared branch. Reconciling the two is still
outstanding.
