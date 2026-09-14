# Accessibility Findings — Post-Merge Verification (2026-07-22)

Re-ran the automated scan after merging `origin/main` into
`AM-accessibility-program` (114 commits, including a Drupal core bump to
11.4.4, a `drupal/gin` upgrade to 5.0.15, and a new "Super Admin Warning"
banner in `mukurtu_core`). Method as in the [July baseline](2026-07-baseline.md):
axe-core via `tests/playwright/tests/accessibility.spec.ts` against a local
DDEV site, anonymous and as a real community/protocol member account
(`a11y_member`, `authenticated` role only — no admin/manager roles).

**Note on methodology:** an earlier pass of this re-verification accidentally
scanned the "member" pages while logged in as the site's UID 1 administrator
(the test suite falls back to `admin`/`admin` when `A11Y_USERNAME`/
`A11Y_PASSWORD` aren't set). That surfaced admin-toolbar findings that looked
new but were already recorded in the July baseline's "Known open findings"
section. Re-running with a dedicated non-admin member account gave the
correct comparison below — worth remembering next time: **always pass
`A11Y_USERNAME`/`A11Y_PASSWORD` for a real member, or the "member" suite
silently tests as an admin instead.**

## Result: Phase 1 scope (anonymous + member) — still fully clean

Every anonymous and real-member page scans at 0 WCAG violations and 0
best-practice violations, matching the July baseline exactly. No regression
from the merged changes for the visitor/member experience.

## Result: admin-only (UID 1) scan — one new finding, three unchanged

Scanning as UID 1 (still out of Phase 1/2 scope, tracked here only because it
came up during verification):

**Unchanged** — the three toolbar findings already recorded in the [July
baseline](2026-07-baseline.md#known-open-findings--upstream-admin-toolbar-authoring-tool-scope)
(`aria-valid-attr`, `button-name`, `link-name`, all WCAG 4.1.2/2.4.4, traced to
a typo — `aria-toolbar-link__labelledby` instead of `aria-labelledby` — in
Gin's own `templates/navigation/menu-region--top.html.twig` and
`menu-region--middle.html.twig`) are still present, unchanged, after the Gin
5.0.12 → 5.0.15 upgrade. Still upstream, still no local override.

**New** — `landmark-no-duplicate-contentinfo` / `landmark-contentinfo-is-top-level`
(axe best-practice, not a WCAG failure): the new UID-1-only "Super Admin
Warning" banner
(`modules/mukurtu_core/src/Controller/SuperAdminWarningController.php`,
wired up in `mukurtu_core.module`) is rendered as a Drupal core `error`-type
status message. Core gives `error` messages `role="contentinfo"`, which
collides with the page's `<footer role="contentinfo">` — two contentinfo
landmarks on one page. Only ever visible to UID 1, since the controller's
`access()` restricts the dismiss link (and by extension the warning) to
`(int) $account->id() === 1`.

Also newly observed: `landmark-unique` on an empty
`<nav class="toolbar-lining clearfix" role="navigation">` in the admin
toolbar chrome (best-practice, no accessible name to disambiguate it from
the page's other nav landmarks) — likely the same toolbar markup family as
the three known findings above, not separately triaged yet.

**Fix options for the new finding** (not applied — this is Mukurtu's own
code, so a local fix is in scope, but the banner is brand new and outside
this program's current phase): render the banner as a `warning` or `status`
message instead of `error` (Drupal core does not give those types
`role="contentinfo"`), or wrap it in an element with an explicit
non-conflicting role.

## Environment note (not an accessibility finding)

This merge also required fixing a duplicate `hook_update_N()` — both the
July baseline's Gin-accent-contrast update and a new hook from `main` had
landed as `mukurtu_core_update_40076()`. Renumbered this branch's hook to
`40078` (see `modules/mukurtu_core/mukurtu_core.install`); the July baseline
doc's function reference was updated to match.

## ACR status

No change. The new UID-1-only finding doesn't move ACR conformance levels —
it's best-practice, not a WCAG SC failure, and admin/UID-1 experience isn't
in the ACR's Phase 1 web-component scope.
