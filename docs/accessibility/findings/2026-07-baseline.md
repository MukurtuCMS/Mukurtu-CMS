# Accessibility Findings — July 2026 Automated Baseline

The first full cycle of the accessibility program's automated scanning: baseline
audit, remediation, and re-verification, run 2026-07-10 through 2026-07-16.
Method: axe-core 4.x via `tests/playwright/tests/accessibility.spec.ts` (WCAG 2.1
A/AA rule tags, with best-practice findings recorded separately) against a local
DDEV site (`mukurtu.ddev.site`, Drupal 11.x, `AM-accessibility-program` branch).
Scans ran anonymously and as a regular member account (community + protocol
member, no admin roles) — member scans discover and cover the protocol-gated
item pages that anonymous visitors cannot reach.

## Where we are now

**Every page in the [audit inventory](../page-inventory.md) — 15 pages, anonymous
and member — scans at 0 WCAG violations and 0 best-practice violations.** Three
defects were found and fixed during the cycle (below). The only remaining axe
output is the "incomplete" queue: contrast checks axe cannot compute on its own,
handed to the manual pass.

| Coverage | Result |
|---|---|
| Anonymous: `/`, `/browse`, `/digital-heritage`, `/collections`, `/communities`, `/dictionary`, `/user/login` | Clean |
| Member: `/`, `/my-content`, `/user/personal-collections`, `/user` | Clean |
| Member, discovered item pages: digital heritage item, collection, community, dictionary word | Clean |
| Not yet scanned | Anonymous item pages (test site content is all protocol-gated); admin/authoring UI (later phase) |

No manual keyboard/screen-reader testing has happened yet — that is the next
phase, using [../manual-checklist.md](../manual-checklist.md) and the
[manual findings template](manual-findings-template.md).

## Defects found and fixed this cycle

### 1. Contrast failure on member table sort links — WCAG 1.4.3 (serious)

Table-header sort links on `/my-content` rendered at 4.48:1 (teal `#10857f` on
white), just under the 4.5:1 AA requirement. Cause: Mukurtu shipped Gin's "teal"
accent preset in `config/install/gin.settings.yml`, and `/my-content` is an
admin route rendered in Gin for admin users.

**Fixed:** custom accent `#0e7873` (5.3:1) in the install config, with
`mukurtu_core_update_40078()` migrating existing sites still on the default
preset (sites with their own accent choice are untouched). Note: for non-admin
members `/my-content` renders in the front-end theme, so regular members never
saw this — the fix protects admin-theme users and any future admin-route
exposure.

### 2. Page title outside landmarks — axe `region` best-practice (all pages)

The page-title region (the `<h1>`) rendered in a bare `div.region` between the
header and main landmarks on every page.

**Fixed:** `{{ page.page_title }}` moved inside `<main>` in
`themes/mukurtu_v4/templates/layout/page.html.twig` (which all page variants
extend) and `page--404.html.twig`. Also puts the `<h1>` inside the skip-link
target.

### 3. Unnamed, focusable map markers — WCAG 4.1.2-adjacent (Leaflet)

Leaflet rendered each location marker as `<img alt="" role="button"
tabindex="0">`: keyboard-focusable, announced as an unnamed button, while the
empty `alt` simultaneously marked it presentational (axe `aria-allowed-role` +
`presentation-role-conflict`).

**Fixed:** `MukurtuLeafletFormatter::viewElements()` now gives every feature
without a title one named for the entity whose location it shows ("Location of
<label>"; related-coverage markers use the related item's label). The contrib
leaflet JS turns the feature title into the marker's `alt`/`title` attributes —
verified in the DOM (`alt="Location of DH 1"`) and by re-scan. **Scope note:**
this covers maps rendered by the Mukurtu field formatter (item pages); the
views-based browse map (`views.view.mukurtu_browse_by_map`) builds markers by a
separate path and needs the same check once it has locatable content.

## Known open findings — upstream admin toolbar (authoring-tool scope)

Scanning as an **admin** (only) surfaces three violation rules, all confined to
the Drupal core/Gin admin toolbar; regular members and visitors never encounter
them:

- `aria-valid-attr` (critical): invalid attribute `aria-toolbar-link__labelledby`
  on three `ul.toolbar-block__content` menus
- `button-name` (critical): six toolbar buttons ("Extend" submenu toggles,
  sidebar toggle) with no computed accessible name — inner `<span>` text hidden
  from the accessibility tree
- `link-name` (serious): eight toolbar links (`/admin/content`, `/admin/people`,
  …) with no accessible name, same hidden-span cause

**Status:** recorded under the authoring-tool component of the
[ACR](../acr/mukurtu-acr.yaml) as `partially-supports` (4.1.2). Next step:
verify against current Drupal core/Gin releases, then file or link upstream
issues — not Mukurtu code, so no local override until upstream triage says so.

## Handed to the manual pass (axe "incomplete" queue)

Contrast checks axe could not compute (backgrounds are images/overlays or
map tiles) — measure with a contrast tool during the manual pass:

- Leaflet zoom controls and attribution links (digital heritage item page)
- Block `h2` headings over images (home, member home)
- One flagged element on the collection page
- Also: `aria-valid-attr-value` on `article` elements (home and member pages) —
  an ARIA reference axe couldn't resolve; inspect once manually

## ACR status after this cycle

First triage pass recorded in [../acr/mukurtu-acr.yaml](../acr/mukurtu-acr.yaml)
(version 2): web component `supports` with dated method notes for 1.1.1, 1.3.1,
1.4.3, 2.4.1, 2.4.2, 3.1.1, 4.1.2; authoring-tool 4.1.2 `partially-supports`
(toolbar findings above). All other criteria remain `not-evaluated` until the
manual pass and the platform capability checks (1.2.x media alternatives)
provide evidence — see the capability-testing section of the
[manual checklist](../manual-checklist.md).

## Remaining actions for the next cycle

1. **Manual pass** (keyboard, screen reader, zoom/reflow, contrast queue) using
   the [template](manual-findings-template.md) — priority order per the
   [page inventory](../page-inventory.md): Leaflet maps, content warnings,
   carousels, lightbox, audio player first.
2. **Platform capability checks** for author-provided media alternatives
   (1.2.x) — transcript rendering, local-video captions (expected gap), remote
   video captions, autoplay.
3. **Upstream:** verify + file the three toolbar issues; add issue links to the
   ACR notes.
4. **Coverage:** extend `default-content.spec.ts` with anonymous-visible content
   (digital heritage items with media, an open collection, open-protocol
   dictionary words) so anonymous item pages can be scanned; check the
   views-based browse map markers once locatable content exists.
5. **CI:** add the report-only axe job to `.github/workflows/playwright.yml`
   per the charter's ratchet plan.
6. **Triage:** fold manual results into the ACR — the remaining `not-evaluated`
   A/AA criteria are the gate for a publishable release ACR.

## Reproducing these scans

Per-page axe JSON is written to `tests/playwright/test-results/a11y/` on every
run (gitignored). Member scans need a regular community/protocol member account
— on the local site this is `a11y_member`; on any other environment create one
and pass it via env vars:

```bash
cd tests/playwright
PLAYWRIGHT_BASE_URL=https://mukurtu.ddev.site \
A11Y_USERNAME=a11y_member A11Y_PASSWORD=... \
npx playwright test accessibility --project=chromium
```
