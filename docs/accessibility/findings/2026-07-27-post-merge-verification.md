# Accessibility Findings — Post-Merge Verification (2026-07-27)

Re-ran the full automated suite after merging `origin/main` into
`AM-accessibility-program` (7 commits: bot/spam protection — CAPTCHA,
reCAPTCHA, Cloudflare Turnstile, facet crawler blocking — Klaro cookie
consent, Visitors + Charts + GeoIP analytics, and a couple of unrelated
fixes) and adding recipe-based demo content for the five content types this
program hadn't covered yet (Collection, Person, Place, Basic page, Word
List/Dictionary Word — see `recipes/*_demo_content`). Method as in the [July
baseline](2026-07-baseline.md) and the
[previous post-merge pass](2026-07-22-post-merge-verification.md): axe-core
via `tests/playwright/tests/accessibility.spec.ts` and the second
`accessibility-automated-checks.spec.ts` layer, against a local DDEV site,
anonymous and as `a11y_member` (`authenticated` role only).

## Result: full inventory — still clean, and now fully covered

**All 19 inventory entries (anonymous + member, including every discovered
item page) scan at 0 WCAG violations**, matching every prior cycle. This is
better coverage than any previous cycle: the July baseline explicitly
recorded "Not yet scanned: Anonymous item pages (test site content is all
protocol-gated)" for `collection-page`, `community-page`, and
`dictionary-word` — this run scans all of them cleanly, anonymous and
member, for the first time (see the protocol-ID environment note below for
why they'd been gated). The `image-alt` PDF-thumbnail defect the July
baseline logged as "not yet fixed" is gone — no `image-alt` violation
anywhere in this run.

The `accessibility-automated-checks.spec.ts` layer surfaces the same
already-known findings and nothing new:

- **Reflow (1.4.10):** the sitewide ~5px overflow at 320px (325px vs 320px)
  and the larger content/item-page overflow (up to 527px) are both still
  present, unchanged, on every applicable page. Still not root-caused (see
  the July baseline's action item).
- **Text zoom (1.4.4):** `/dictionary` still overflows at 200% text size
  (1491px vs 1280px, same as baseline). **New this run, same root cause,
  wider coverage:** now that `/communities` has real content to render,
  `community-page` and `member-community-page` show the identical pattern
  (1507px vs 1280px) — evidence this is one shared layout issue, not
  something specific to the dictionary's alphabet index bar as originally
  suspected.
- **Focus visible (2.4.7):** the same 4 of ~47-55 checked elements on the
  dictionary word page (a word-type link, a contributor link, two
  `.button` elements) still have no visible focus indicator — unchanged.
- **Keyboard trap (2.1.2), still correctly flagged only for manual
  confirmation, not as a defect:** the native `<audio controls>` element on
  `browse`, `digital-heritage-item`, and `dictionary-word` (member and
  anonymous) reads as "focus not advancing," the same shadow-DOM limitation
  documented in the July baseline. No new false positives.
- **Link text (2.4.4/2.2):** zero findings everywhere, same as baseline.

## Environment notes (not accessibility findings)

Three things had to be fixed to get the above result — none are WCAG
defects, but all three would have produced misleading results (or blocked
the scan entirely) if left alone.

### 1. Duplicate `hook_update_N()` from the merge

Same class of conflict as the [previous post-merge
pass](2026-07-22-post-merge-verification.md#environment-note-not-an-accessibility-finding):
both this branch and `main` had added `mukurtu_core_update_40084()` — this
branch's Gin-accent-contrast fix (from the July baseline) and main's "install
bot protection module" hook. Renumbered this branch's hook to `40092` (see
`modules/mukurtu_core/mukurtu_core.install`); both ran cleanly on `drush
updb`.

### 2. Bot protection broke the Playwright login helper

The merge enabled two things on `user_login_form` that the login flow
depends on: Honeypot's `time_limit` (5s — rejects any submission faster than
that) and an ALTCHA "I'm not a robot" checkbox (rendered for every anonymous
visitor, since you're anonymous until login succeeds — the account's own
"skip CAPTCHA" permission doesn't apply yet). `tests/playwright/src/
components/login.ts` filled the form and clicked submit in under a second,
so every automated login — member-page scans here, and separately the
`default-content.spec.ts` seeding flow — silently failed and left the
session anonymous, with no visible error in a quick check (the login page
just re-renders).

**Fixed:** `login.ts` now checks the ALTCHA checkbox if present and waits 7s
before submitting. This is a test-infrastructure fix, not a workaround
baked into application code.

**Worth its own accessibility look, separately:** the ALTCHA widget itself
is now part of the `/user/login` page, which is already in this program's
Phase 1 inventory — this run's axe scan of `login` came back clean, but a
checkbox CAPTCHA gating every login is exactly the kind of interactive
component the [manual checklist](../manual-checklist.md) should verify by
keyboard and screen reader (its own accessible name, keyboard operability,
state announcement on verify). Not done in this pass — recommend adding an
ALTCHA row to the high-risk component list in
[page-inventory.md](../page-inventory.md).

### 3. Recipe-created Community/Protocol IDs didn't match this site's history, and one workaround was actively destructive

This local DDEV site has pre-existing `Community 1` / `Protocol 1` entities
from before the accessibility-demo-content recipes existed (`Protocol 1`'s
access mode is `strict`, not `open`). Every recipe's `field_cultural_
protocols` hardcodes `protocols: '|1|'` as a documented fresh-install
assumption (see `recipes/accessibility_demo_content/README.md`), so
Collection, Person, Place, Word List, and Dictionary Word content ended up
attached to the wrong, non-public protocol on this specific site —
correctly returning `403` for anonymous and for `a11y_member`, which is why
those pages were skipped ("no item link found") on the *first* attempt at
this re-verification. Digital Heritage was unaffected only because an
earlier session had already hand-patched its protocol ID for this specific
site.

**Fixed for this site only** (not a recipe change — the recipes' own
fresh-install assumption is correct and unchanged): re-pointed the affected
nodes/media at the recipe's own `Public Access (Accessibility Demo)`
protocol via a one-off script, switching to the uid-1 account first —
`CulturalProtocolItem::preSave()` silently reverts protocol changes made by
a user (including `drush php:script`'s default anonymous context) without
permission to apply them, which is worth remembering next time: **protocol
field changes made via `drush php:eval`/`php:script` need an explicit
`account_switcher` switch to an authorized user, or they appear to succeed
but don't persist.**

**Found and fixed a second, actually-destructive issue in the recipe
itself while chasing this:** `accessibility_demo_content/recipe.yml` had a
config action clearing `mukurtu_protocol.community_organization` to `{}`, to
work around a separate pre-existing bug (a stale placeholder entry that
makes the very first Community's `field_child_communities` fail validation
on a truly fresh install — still real, still documented in the recipe's
README). The problem: config actions re-apply every time this recipe runs
as a prerequisite of another recipe (`collection_demo_content`,
`person_demo_content`, etc.), unlike content, which is skipped once it
exists by UUID. `CommunitiesPageController` builds the entire `/communities`
page from this config (not a database query), so every downstream recipe
run silently wiped it back to empty — which is why `/communities` rendered
with a heading and nothing else, no error, until this was caught. **Fixed**
by removing the config action from `recipe.yml` and documenting the
one-time manual command in the README instead
(`drush config:set mukurtu_protocol.community_organization organization
'{}' --input-format=yaml`, run once, only on a truly fresh install). Also
manually restored this site's `organization` config to include both
existing communities.

## ACR status

No change. Nothing here moves a WCAG conformance level — the scan result is
identical to prior clean cycles, just with full inventory coverage for the
first time and one long-standing "not yet fixed" defect (PDF thumbnail
`image-alt`) confirmed resolved.

## Remaining actions carried forward

Everything the July baseline listed as not-yet-fixed is still open and
unchanged by this cycle: the sitewide/content-page reflow overflow, the
`/dictionary` (now also `/communities`) text-zoom overflow, and the 4
dictionary-word focus-visible failures. Manual pass and upstream toolbar
triage are also still outstanding. Newly added to the list:

1. Add the ALTCHA login checkbox to the high-risk component inventory and
   give it a manual keyboard/screen-reader pass.
2. File an upstream/local fix for `mukurtu_protocol.community_organization`
   shipping a stale placeholder entry, so neither the recipe nor the "Add
   community" UI form need a workaround at all.
