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

*Last verified 2026-08-10, after resolving a divergent-history merge conflict
(see "Merge notes" below) that brought in another 36-commit `origin/main`
update plus parallel recipe-content work. One new real finding from this
cycle (`aria-hidden-focus` on browse cards, below); everything else matches
the prior known state.*

**19 of 19 pages in the [audit inventory](../page-inventory.md) scan — 12
with zero findings of any kind, 7 with known, already-triaged issues** (the
PDF `image-alt` finding, the ALTCHA widget bugs, and the new browse-card
finding, all below). The two automated-checks layers add roughly 23 more
findings across the inventory (reflow/text-zoom overflow, 4 focus-visible
failures, and the audio-player "needs manual confirmation" flags) — see the
automated-checks section below. Nothing here is a regression; all of it is
tracked with an owner action in "Remaining actions for the next cycle."

| Coverage | Result |
|---|---|
| Anonymous: `/`, `/browse`, `/digital-heritage`, `/collections`, `/communities`, `/dictionary`, `/user/login`, plus discovered digital heritage item, collection, community, and dictionary word pages | Clean, except `browse` (new `aria-hidden-focus`), `login` (ALTCHA), `dictionary-word` (PDF `image-alt`) |
| Member: `/`, `/my-content`, `/user/personal-collections`, `/user`, plus discovered item pages | Clean, except `member-collection-page` (ALTCHA), `member-dictionary-word` and `member-digital-heritage-item` (ALTCHA + PDF `image-alt`) |
| Not yet scanned | Admin/authoring UI (later phase) |

### Merge notes (2026-08-10)

This branch's remote (`origin/AM-accessibility-program`) had been updated
independently of this local checkout — both merged `origin/main` from
slightly different starting points, so reconciling them produced a real
conflict in `mukurtu_core.install` (not the usual silent-duplicate-hook kind
from earlier cycles). Worth recording because the conflict had already been
"resolved" (staged, ready to commit) before this check ran, and the
resolution had two real defects that a plain `git commit` would have shipped:
a stray literal `>>>>>>>>> Temporary merge branch 2` fragment left inside a
live `if` block (would have been a PHP parse error, breaking every install),
and this branch's entire Gin-accent WCAG 1.4.3 fix silently missing from the
merged file (confirmed by diffing function inventories against both merge
parents — present in both, absent from the "resolved" result). Both fixed
before completing the merge: removed the stray fragment, restored the missing
hook (renumbered to `40099` to match the number the other side had
independently chosen for it, avoiding a needless third renumbering). Lesson:
after any merge — especially one already marked "conflicts fixed" — diff the
resolved file's function/hook inventory against both parents, not just
`php -l` and a duplicate-name grep. A file can lint clean and still be
silently missing an entire fix.

**2026-08-11:** merged a further 45 `origin/main` commits (a large Local
Contexts widget-consolidation feature, plus a new stale-base-branch CI check)
— clean merge, no conflicts, no composer/recipe changes, so a plain
`drush updb` sufficed instead of a full reinstall. Zero new regressions.
One automated-checks shuffle worth naming so it isn't mistaken for a new
defect: `keyboard-focus-not-advancing` (the benign native-control classifier)
now flags `digital-heritage-browse` instead of the digital-heritage-item
pages — consistent with the content-order-dependency already logged above,
not a new bug category.

**2026-08-13:** merged a further 80 `origin/main` commits (Leaflet path-fill
fix, empty-paragraph pruning, browse facet link fix, admin local-task
visibility, user profile page fixes, and — notably — a round of **lightbox
keyboard/focus-ring accessibility fixes**, directly relevant to this
program's own Lightbox component checklist item). This merge is the reason
the "diff function bodies against both parents" step above exists: git
reported **zero textual conflicts**, but both branches had independently
added a differently-named-in-neither-but-same-numbered
`mukurtu_core_update_40099()` — ours (the Gin accent fix) and main's (grant
Language Stewards comment permissions) — and git's line-based merge silently
kept one body under that name and discarded the other, with no conflict
marker at all. A same-name-different-body collision like this passes both
"any conflict markers?" and "any duplicate function names?" checks; only
comparing the actual body content against both parents caught it. Restored
main's hook under `40099` and moved ours to `40101` (see commit
`2ebcc9058`). One side effect specific to *this dev site's* database: since
its schema-version tracking already recorded "hook 40099 has run" (under the
old, wrong body), a plain `drush updb` silently skipped the real Language
Stewards hook — worked around with a full reinstall, which doesn't consult
run-history. Not a defect in the shipped code, purely a local artifact of
repeated manual conflict resolution on one long-lived dev database.

Scan results otherwise match the prior known state closely: one new
`region` (best-practice) finding — the new lightbox zoom-hint text
("Press Enter to zoom...") isn't contained in a landmark — a minor,
easily-fixed side effect of that positive accessibility improvement, not a
regression. Everything else, including the automated-checks layer's 27
findings, matches exactly.
**Tracked as [#2002](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/2002).**

**2026-08-14:** merged a further 13 `origin/main` commits (Leaflet
draw-circle map feature, #860). Another hook collision — this time main
had independently hit and partly self-corrected the *exact same* class of
problem this program has hit twice before (see their own "Restore previous
update hook order" commit), which shifted their hook numbering into direct
collision with this branch's `40101`. Unlike the two prior incidents,
nothing was silently dropped this time — both bodies survived, just under
the same function name (`mukurtu_core_update_40101()` declared twice), which
would have been a PHP fatal "cannot redeclare function" if left as committed.
Fixed by renumbering the Gin accent hook to `40102` (commit `094a8132b`,
separate from the merge commit). Re-scan: 39/39, zero new findings — results
match 2026-08-13 exactly, confirming the draw-circle feature introduced no
regressions.

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
`mukurtu_core_update_40102()` migrating existing sites still on the default
preset (sites with their own accent choice are untouched; renumbered several
times across merges — see "Merge notes" for why — current number is
`40102`). Note: for non-admin
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
**Tracked as [#2003](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/2003).**

## New finding (2026-07-23): unnamed PDF thumbnail in the media carousel

The `recipes/accessibility_demo_content` recipe (see below) made anonymous
digital-heritage-item and dictionary-word pages scannable for the first time —
previously always skipped for lack of anonymously-visible content. That
coverage immediately surfaced a real defect:

**`image-alt` (critical, WCAG 1.1.1)** — the auto-generated preview image for
the PDF document in the media carousel (`sample-field-notes_thumbnail.png`)
renders with no `alt` attribute at all (not even `alt=""`):
`<img loading="lazy" src=".../sample-field-notes_thumbnail.png..." width="155" height="200">`.
Present on both the digital heritage item and dictionary word pages (same
reused media asset). Also flagged: `aria-allowed-role` (best-practice, minor)
on the Splide carousel's slide elements — lower priority, needs a quick check
of whether it's a Splide library quirk or Mukurtu markup.

**Not yet fixed** — logged here as a new finding for triage, not remediated in
this pass. **Tracked as [#1995](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1995)
(image-alt) and [#1996](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1996)
(carousel aria-allowed-role).**

## New finding (2026-07-23): second automated-checks layer, first results

Added `tests/accessibility-automated-checks.spec.ts` — a set of checks beyond
what axe-core can assert on its own: real WCAG 1.4.10 reflow and approximated
1.4.4 text-zoom (both need no human judgment to detect), a 2.4.7
focus-visibility smoke test, a 2.4.4 vague-link-text heuristic, and a 2.1.2
keyboard-trap smoke test. See the "What's automated now" table in
[../manual-checklist.md](../manual-checklist.md) for exactly what each one
covers and what still needs a human — this converts several previously
fully-manual checklist rows into "run the script, then spot-check what it
flags."

First run against the recipe-seeded site surfaced:

- **Reflow (1.4.10), confirmed real:** nearly every page overflows by ~5px at
  320px width (325px content in a 320px viewport) — small, consistent across
  unrelated page types, so almost certainly one shared layout element rather
  than N separate bugs. Content/item pages (collection, dictionary word,
  digital heritage item) overflow considerably more (up to 527px) — likely an
  additional, distinct sidebar/widget issue on top of the sitewide one. Not
  yet root-caused or fixed.
- **Text zoom (1.4.4):** one isolated case — `/dictionary` overflows at 200%
  text size (1491px vs 1280px). Worth a look at the alphabet index bar,
  which is the likely culprit (many single-letter items that may not wrap).
- **Focus visible (2.4.7):** 4 of 46 checked elements on the dictionary word
  page have no visible outline or box-shadow when focused (a word-type link,
  a contributor link, and two `.button` elements). First concrete instance of
  this criterion failing anywhere in the program so far.
- **Keyboard trap (2.1.2), correctly *not* flagged as a defect:** the first
  version of this check produced two false-positive patterns before I
  tightened it — (1) a short admin-route page wrapping normally from its last
  focusable element back to the first (not a trap, just reaching the end of
  the page) and (2) the native `<audio controls>` element on the digital
  heritage item and dictionary word pages, whose internal play/seek/volume
  controls live in a shadow DOM the check can't see into, making Tab *look*
  like it never leaves the element. Both are now handled: whole-page
  wraparounds are suppressed entirely (not a trap), and the audio-player
  pattern is downgraded to "needs manual confirmation" rather than reported
  as a suspected trap. This is exactly the residual instance the manual
  checklist's audio player section now calls out explicitly: confirm by hand
  that Tab actually exits the player.
- **Link text, WCAG 2.2 (informational):** zero findings across the full
  inventory for both — no vague link text, and no WCAG 2.2-only rule
  violations anywhere yet.

**Not yet fixed:** the reflow findings above. Logged for triage.
**Tracked as [#1997](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1997)
(reflow), [#1998](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1998)
(text-zoom), and [#1999](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1999)
(focus-visible).**

## New finding (2026-07-28): third-party ALTCHA widget accessibility bugs

Merging `origin/main`'s bot-protection work (ALTCHA/Honeypot/reCAPTCHA/Turnstile/CAPTCHA)
brought two new WCAG violations, both inside the **ALTCHA** widget's own
third-party markup, not Mukurtu code:

- **`aria-hidden-focus` (serious):** the widget's "altcha.org" logo link is
  `aria-hidden="true"` but remains keyboard-focusable — a screen reader user
  tabs to a link the accessibility tree says doesn't exist.
- **`color-contrast` (serious):** the widget's footer text and an adjacent
  "opens in a new window" link fail contrast.

**Where it shows up, and why that's odd:** `/user/login` (expected — it's the
CAPTCHA), plus the **member**-view digital heritage item, dictionary word, and
collection pages. Their anonymous counterparts and every other member page
(my-content, personal-collections, account, home) are unaffected. The pattern
doesn't point cleanly at "every page with a form" or "every protected route" —
worth a follow-up to find which specific widget/form on those three content
types is pulling ALTCHA in for logged-in users, before filing upstream.

Full inventory otherwise still clean: this run also got `community-page`
scanned anonymously for the first time (previously always skipped — see the
recipe fix below), and it shows zero violations.

**Not yet fixed** — logged for triage. Likely an upstream ALTCHA/Drupal
module issue rather than something to patch locally; same "verify upstream
before overriding" approach as the admin toolbar findings.
**Tracked as [#2000](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/2000).**

## New finding (2026-07-28): recipe fix resolves the `/communities` mystery

The `/communities` anomaly flagged back on 2026-07-23 (a public, published
community not appearing on the anonymous listing) is now explained and fixed
upstream, in the same push that merged `origin/main` into this branch: the
listing's `CommunitiesPageController` reads community IDs from the
`mukurtu_protocol.community_organization` config, not a database query.
`accessibility_demo_content`'s recipe used to clear that config as a
config action to unblock a fresh-install bug (see the recipe's README) — but
config actions re-apply every time the recipe runs as a prerequisite of
another one, so it was silently re-wiping `organization` (and therefore the
`/communities` listing) on every downstream recipe application. Replaced with
a one-time manual `drush config:set` documented in the recipe README, run
once before recipes on a fresh install. Verified: `/communities` now lists the
seeded community anonymously, and `community-page` scans clean (see above).

## New finding (2026-08-10): unnamed decorative link focusable in horizontal-card component

`browse` (and likely the person/place grid-browse cards that `origin/main`
added templates for this cycle) shows a new `aria-hidden-focus` (serious)
violation: `.horizontal-card__media > a[aria-hidden="true"]` — a card's image
link is marked `aria-hidden="true"` (presumably to avoid a screen reader
announcing the same destination twice, once for the image and once for the
title text) but has no `tabindex="-1"` pulling it out of the tab order either,
so a keyboard/screen-reader user can still land on a link the accessibility
tree says doesn't exist. **Not yet fixed** — logged for triage.
**Tracked as [#2001](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/2001).**

## Note (2026-08-10): item-page scan coverage is content-order-dependent

The still-open PDF-thumbnail `image-alt` finding (documented above) briefly
looked "fixed" in this run's `digital-heritage-item` result — it wasn't.
`discoverItemUrl()` always scans whichever item link appears **first** on the
listing page, and a new multipage item added by this merge's recipe content
now sorts before the original `woven-basket-maker-unknown-sample-item` (the
one with the bug). Confirmed directly: the woven-basket item's PDF thumbnail
still ships with no `alt` attribute at all — the bug is unchanged, our
coverage of it just silently lapsed. Logged as a follow-up: either pin
discovery to a stable, named item per content type, or scan all discovered
items rather than only the first, so new content can't quietly shrink
coverage like this again.

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

1. **Manual pass** (keyboard, screen reader, contrast queue — zoom/reflow is
   now automated, see above) using the [template](manual-findings-template.md)
   — priority order per the [page inventory](../page-inventory.md): Leaflet
   maps, content warnings, carousels, lightbox, audio player first. For the
   audio player specifically, confirm Tab actually exits it (see the new
   finding above).
2. **Platform capability checks** for author-provided media alternatives
   (1.2.x) — transcript rendering, local-video captions (expected gap), remote
   video captions, autoplay.
3. **Upstream:** verify + file the three toolbar issues; add issue links to the
   ACR notes. Tracked as [#2003](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/2003).
4. ~~**Coverage:** extend content so anonymous item pages can be scanned~~ —
   **done 2026-07-23** via `recipes/accessibility_demo_content` and its
   dependents, not a spec change. Still open: check the views-based browse
   map's markers once it has locatable content — the recipe seeds a map on
   the item page (Mukurtu formatter path, already fixed), not the separate
   browse map view.
5. **Fix:** the sitewide ~5px reflow overflow at 320px, plus the larger
   content/item-page overflow (up to ~527px) and the `/dictionary` text-zoom
   overflow — all new findings above, not yet root-caused. Tracked as
   [#1997](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1997) and
   [#1998](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1998).
6. **Fix:** PDF thumbnail missing `alt` in the media carousel, and the 4
   focus-visible failures on the dictionary word page — both new findings
   above. Tracked as [#1995](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1995)
   and [#1999](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1999).
7. **CI:** add the report-only axe job (and the automated-checks job) to
   `.github/workflows/playwright.yml` per the charter's ratchet plan.
8. **Triage:** fold manual results into the ACR — the remaining `not-evaluated`
   A/AA criteria are the gate for a publishable release ACR.
9. **Investigate:** find which form/widget pulls the ALTCHA bot-protection
   widget into the member-view digital heritage item, dictionary word, and
   collection pages (not their anonymous counterparts, not other member
   pages) — new finding above. Then decide whether to file the two ALTCHA
   markup bugs upstream or override locally. Tracked as
   [#2000](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/2000).
10. **Fix:** the `aria-hidden-focus` finding on browse/grid cards — new
    finding above. Tracked as [#2001](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/2001).
11. **Test methodology:** stop `discoverItemUrl()` from always picking the
    first item link on a listing page — new content silently displaces
    coverage of older items (see the 2026-08-10 note above, which caught the
    PDF `image-alt` finding almost falling out of coverage). Either pin
    discovery to named/stable items or scan every discovered item.
12. **Fix:** wrap the new lightbox zoom-hint text in a landmark — new finding
    above (2026-08-13). Tracked as [#2002](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/2002).
13. **Cross-reference:** the Lightbox component in
    [../manual-checklist.md](../manual-checklist.md) already has upstream
    keyboard/focus-ring fixes in progress (see the 2026-08-13 merge note) —
    re-check that checklist's Lightbox items against current behavior before
    starting the manual pass there, some may already be resolved.

## Filed as GitHub issues (2026-08-14)

All confirmed, not-yet-fixed defects above are now filed with the `accessibility`
label, to be tackled independently of this program branch:

| Issue | Finding |
|---|---|
| [#1995](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1995) | PDF thumbnail missing alt text in media carousel |
| [#1996](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1996) | Splide carousel slide ARIA role conflict |
| [#1997](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1997) | Reflow overflow at 320px width |
| [#1998](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1998) | Dictionary browse overflows at 200% text zoom |
| [#1999](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1999) | Missing focus indicator, dictionary word page |
| [#2000](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/2000) | ALTCHA widget: focusable hidden link + contrast |
| [#2001](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/2001) | Decorative card link focusable despite aria-hidden |
| [#2002](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/2002) | Lightbox zoom-hint text not in a landmark |
| [#2003](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/2003) | Admin toolbar: upstream ARIA/naming bugs (verify first) |

**Not filed as issues** (deliberately excluded — not confirmed defects yet):
the axe "incomplete" contrast queue above (needs a human with a contrast
tool before it's even confirmed as a defect), and the process/infrastructure
items in "Remaining actions" (CI wiring, ACR triage, the `discoverItemUrl()`
test-methodology gap, the manual-pass planning items) — those aren't
accessibility defects in the product, they're this program's own follow-up
work.

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
