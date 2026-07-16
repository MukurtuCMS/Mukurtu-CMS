# Accessibility Findings — July 2026 Baseline

First automated scan of the accessibility program. axe-core 4.x via `tests/playwright/tests/accessibility.spec.ts` (WCAG 2.1 A/AA tags + best-practice), run 2026-07-10 against a local DDEV site (`mukurtu.ddev.site`, Drupal 11.3, Mukurtu profile at commit on `AM-accessibility-program`).

**Caveats for this baseline:**

- The test site had very little content: no digital heritage items, no anonymous-visible dictionary words, collections, or community pages. All four discovered-item scans skipped. Anonymous pages were scanned mostly empty, so the clean anonymous results are weaker evidence than they look.
- Member pages were scanned as **admin** (`admin`/`admin`), so they include the Drupal admin toolbar, which a regular member never sees. Three of the four violation rules below live entirely in that toolbar.
- No manual (keyboard/screen reader) testing yet — see [../manual-checklist.md](../manual-checklist.md) for the next pass.

## Coverage

| Scanned | Result |
|---|---|
| `/`, `/browse`, `/digital-heritage`, `/collections`, `/communities`, `/dictionary`, `/user/login` (anonymous) | 0 WCAG violations |
| `/`, `/my-content`, `/user/personal-collections`, `/user` (as admin) | 3–4 WCAG violation rules each |
| Digital heritage item, collection, community, dictionary word pages | **Not scanned — no accessible content** |

## Findings by WCAG Success Criterion

### 4.1.2 Name, Role, Value — invalid ARIA attribute (axe: `aria-valid-attr`, critical)

`aria-toolbar-link__labelledby="menu--create"` (an invalid ARIA attribute name) on three `ul.toolbar-block__content` menus. Present on every page rendered with the admin toolbar.

- **Source:** Drupal core Navigation module / Gin admin toolbar — **not Mukurtu code**. Verify against the installed core/Gin versions and check for an upstream issue before writing any local override.
- **Affected users:** admins/authors only.
- **Suggested action:** file or link an upstream issue; track under the authoring-tool component of the ACR.

### 4.1.2 Name, Role, Value — buttons without discernible text (axe: `button-name`, critical)

Six admin toolbar buttons (`.toolbar-link--*` "Extend" submenu toggles and the sidebar toggle) have no computed accessible name — their inner `<span>` text appears to be hidden from the accessibility tree.

- **Source:** same admin toolbar as above; upstream candidate.
- **Affected users:** admins/authors only.

### 2.4.4 Link Purpose / 4.1.2 — links without discernible text (axe: `link-name`, serious)

Eight admin toolbar links (`/admin/content`, `/admin/people`, etc.) with no computed accessible name; same hidden-span cause as above.

- **Source:** same admin toolbar; upstream candidate.

### 1.4.3 Contrast (Minimum) — table sort links at 4.48:1 (axe: `color-contrast`, serious)

On `/my-content`, table-header sort links render in teal `#10857f` on white — contrast 4.48:1, just under the 4.5:1 AA requirement for 14px text.

- **Source:** `#10857f` is Gin's "teal" accent preset (`/my-content` is an admin route, so it renders in the Gin theme). Mukurtu shipped `preset_accent_color: teal` in `config/install/gin.settings.yml`.
- **Affected users:** all logged-in members using `/my-content`.
- **✅ Fixed 2026-07-10:** switched to a custom accent `#0e7873` (5.3:1) in `config/install/gin.settings.yml`, with `mukurtu_core_update_40073()` migrating existing sites still on the default teal preset (sites with their own accent choice are untouched). Re-scan confirms the violation is gone.

## Advisory findings (axe best-practice, not WCAG failures)

- **`region` — content outside landmarks** on every anonymous listing page (`.layout-container > .region`): the page-title region (the `<h1>`) rendered between the header and main landmarks.
  - **✅ Fixed 2026-07-10:** moved `{{ page.page_title }}` inside `<main>` in `themes/mukurtu_v4/templates/layout/page.html.twig` (which all page variants extend) and `page--404.html.twig`. Also puts the `<h1>` inside the skip-link target. Re-scan shows all anonymous pages fully clean (0 WCAG, 0 best-practice).

## Needs human review (axe "incomplete")

- **Contrast on the home page:** block `h2` headings and the Leaflet map controls/attribution links — axe couldn't compute backgrounds (images/overlays). Check with a contrast tool during the manual pass.
- **`aria-valid-attr-value` on `article` elements** (home and member pages) — axe flagged an ARIA reference it couldn't resolve; inspect during the manual pass.

## Actions for the next cycle

1. ~~**Fix:** Gin accent contrast on `/my-content` (WCAG 1.4.3)~~ — **done 2026-07-10**, see above.
2. ~~**Fix:** landmark wrapping for the flagged `.region` in `mukurtu_v4`~~ — **done 2026-07-10**, see above.
3. **Upstream:** verify the three toolbar rules against current Drupal core/Gin; file or link issues; record under authoring-tool in the ACR.
4. **Coverage:** extend `default-content.spec.ts` with anonymous-visible digital heritage items (with media for carousel/lightbox coverage), an open collection, community landing content, and dictionary words on an open protocol — then re-run the item-page scans.
5. ~~**Coverage:** create a non-admin member test account and re-run member scans with `A11Y_USERNAME`/`A11Y_PASSWORD` for toolbar-free results~~ — **done 2026-07-13**, see addendum below.
6. **Manual pass:** work through [../manual-checklist.md](../manual-checklist.md) starting with the priority-1 components (Leaflet maps, content warnings) from the [page inventory](../page-inventory.md).
7. **Triage into ACR:** first pass done 2026-07-16 — seven criteria with strong automated evidence assigned levels in [../acr/mukurtu-acr.yaml](../acr/mukurtu-acr.yaml) (web: 1.1.1, 1.3.1, 1.4.3, 2.4.1, 2.4.2, 3.1.1, 4.1.2 `supports` with manual-verification caveats; authoring-tool 4.1.2 `partially-supports` for the upstream toolbar findings). Remaining criteria stay `not-evaluated` until the manual pass provides evidence.

## Addendum: non-admin member scan (2026-07-13)

Re-ran the suite as a regular member account (community + protocol member, no admin
roles) via `A11Y_USERNAME`/`A11Y_PASSWORD`, and extended the spec with member-side
item-page discovery (`memberDiscoveredPages`) — on protocol-heavy sites, item pages
are only reachable logged-in, so the anonymous pass can't cover them.

**Results:**

- **Zero WCAG violations on every member page.** All three violation rules from the
  baseline member scan (invalid ARIA attribute, unnamed buttons/links) are confirmed
  to be the admin toolbar only — regular members never see them. `/my-content` also
  renders in the front-end theme for non-admins, so the (already fixed) Gin accent
  issue never reached regular members there.
- **New coverage:** digital heritage item, dictionary word, and community pages
  (protocol-gated, member view). Collection page still skips — no collections exist
  on the test site.
- **New findings — all in the Leaflet map on the digital heritage item page**
  (matches the #1-priority component in the [page inventory](../page-inventory.md)):
  - **Map markers are unnamed focusable buttons** (axe: `aria-allowed-role` +
    `presentation-role-conflict`, minor): Leaflet renders each marker as
    `<img alt="" role="button" tabindex="0">` — keyboard-focusable, announced as an
    unnamed button, while the empty `alt` simultaneously marks it presentational.
    WCAG 4.1.2-adjacent.
    - **✅ Fixed 2026-07-13** in `MukurtuLeafletFormatter::viewElements()`: every
      feature without a title now gets one named after the entity whose location it
      shows ("Location of <label>"; related-coverage markers use the related item's
      label). The contrib leaflet JS turns the feature title into the marker's
      `alt`/`title` attributes. Verified in the DOM
      (`alt="Location of DH 1"`) and by re-scan — both axe findings cleared, so the
      digital heritage item page now has 0 violations of any kind. Note this covers
      maps rendered by the Mukurtu formatter (item pages); the **views-based browse
      map** (`views.view.mukurtu_browse_by_map`) renders markers by its own path and
      should be checked for the same issue when it has locatable content.
  - **Leaflet control/attribution contrast** flagged for human review (axe
    "incomplete") — check the zoom controls and attribution links with a contrast
    tool during the manual pass.

**Test account for future runs:** `a11y_member` (member of all communities/protocols
on the local site). Re-create on any environment with a community/protocol member
account and pass `A11Y_USERNAME`/`A11Y_PASSWORD`.

## Raw data

Per-page axe JSON is written to `tests/playwright/test-results/a11y/` on every run (not committed). Re-generate with:

```bash
cd tests/playwright
PLAYWRIGHT_BASE_URL=https://mukurtu.ddev.site npx playwright test accessibility --project=chromium
```
