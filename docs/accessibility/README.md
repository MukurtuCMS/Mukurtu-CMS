# Mukurtu CMS Accessibility Program

This document is the charter for Mukurtu's ongoing accessibility program. It explains what we are aiming for, what is in scope, how audits run, and the cycle we repeat to keep improving.

For the list of pages and components under audit, see [page-inventory.md](page-inventory.md). For hands-on keyboard and screen reader testing, see [manual-checklist.md](manual-checklist.md). Dated audit results live in [findings/](findings/). The conformance report lives in [acr/](acr/).

---

## Conformance target

**WCAG 2.1 Level AA.**

This matches Drupal core's own accessibility commitment and the requirements of Section 508 (US) and most institutional policies that Mukurtu's community partners — universities, libraries, archives, and museums — operate under. WCAG 2.2 criteria are noted informationally when we encounter them but are not yet gating.

## Scope

The program runs in two phases, against two W3C standards that cover different
things:

- **WCAG** ([Web Content Accessibility Guidelines](https://www.w3.org/TR/WCAG21/)) applies to the *pages Mukurtu
  renders* — it is the standard for both phases, since visitors and authors
  alike experience rendered pages.
- **ATAG 2.0** ([Authoring Tool Accessibility Guidelines](https://www.w3.org/TR/ATAG20/))
  applies to *software used to create web content* — which is what a CMS is. It
  has two halves: **Part A** requires the authoring interface itself to be
  accessible (a screen reader or keyboard-only user must be able to *be an
  author* — create items, upload media, manage protocols); **Part B** requires
  the tool to help authors *produce* accessible content (prompting for alt
  text, preserving accessibility information through workflows, guiding
  authors toward transcripts and captions). Drupal core has committed to
  ATAG 2.0 AA for its administration interface, which sets the precedent
  Mukurtu follows.

**Phase 1 (current): visitor and logged-in member experiences, against WCAG
2.1 AA.** Everything an anonymous visitor or an authenticated
community/protocol member sees: browsing, searching, digital heritage items,
collections, dictionaries, communities, maps, and account pages.

**Phase 2 (later): the authoring and administrative experience, against WCAG
2.1 AA *and* ATAG 2.0.** Content creation forms, bulk media upload,
import/export, dashboards.

**Why are we doing this in stages:** the visitor/member experience is the smaller, self-contained
surface with the largest audience, and it is measured against a single
standard — so it can reach a defensible conformance claim first. The authoring
experience is a much larger surface (every form, workflow, and admin screen),
is measured against two standards at once, and much of it is inherited from
Drupal core and the Gin admin theme — meaning findings there often need
upstream coordination rather than local fixes (see the admin toolbar findings
already recorded under the authoring-tool component of the ACR). Sequencing
keeps each phase's evidence, fixes, and ACR claims clean instead of half-done
everywhere. Part B capability checks that overlap Phase 1 content (media
alternatives an author can provide) are already folded into the
[manual checklist](manual-checklist.md) rather than waiting for Phase 2.

## How conformance is tracked

We follow the approach of [CivicActions' Drupal-ACR project](https://github.com/civicactions/Drupal-ACR): findings are organized by WCAG Success Criterion, each criterion is assigned a conformance level, and the result is published as an **OpenACR** report — the machine-readable, GSA-standardized successor to the VPAT.

- The report lives at [acr/mukurtu-acr.yaml](acr/mukurtu-acr.yaml).
- Conformance levels per criterion: `supports`, `partially-supports`, `does-not-support`, `not-applicable`, or `not-evaluated`.
- Validate the report with the OpenACR CLI (see [acr/README.md](acr/README.md)).

## How audits run

### Automated scans (axe-core via Playwright)

The Playwright suite in `tests/playwright/` includes `tests/accessibility.spec.ts`, which runs [axe-core](https://github.com/dequelabs/axe-core) against every page in the [page inventory](page-inventory.md) using the WCAG 2.1 A/AA rule tags (WCAG 2.2 rule tags are also scanned and reported separately, informationally — see Scope).

```bash
cd tests/playwright
npm install
# Point at your local DDEV site if it isn't the default URL:
PLAYWRIGHT_BASE_URL=https://mukurtu.ddev.site npx playwright test accessibility --project=chromium
```

The scans are **report-only**: violations never fail the tests. Each page's full axe results are written to `test-results/a11y/<page>.json` and attached to the Playwright HTML report. This is deliberate — see "The ratchet" below.

### Automated checks beyond axe (reflow, focus, links, keyboard traps)

`tests/accessibility-automated-checks.spec.ts` runs a second layer that axe-core can't do on its own: real WCAG 1.4.10 reflow and approximated 1.4.4 text-zoom checks (both fully automated, no human judgment needed), a WCAG 2.4.7 focus-visibility smoke test, a WCAG 2.4.4 vague-link-text heuristic, and a WCAG 2.1.2 keyboard-trap smoke test. These narrow the manual checklist down further — see [manual-checklist.md](manual-checklist.md)'s "What's automated now" table for exactly what each one catches and what still needs a human.

```bash
cd tests/playwright
PLAYWRIGHT_BASE_URL=https://mukurtu.ddev.site npx playwright test accessibility-automated-checks --project=chromium
```

Also report-only; results land in `test-results/a11y-extra/<page>-<check>.json`.

Between the two layers, automated scanning now catches roughly 30–40% of WCAG issues outright (missing alt text, form labels, contrast, ARIA misuse, duplicate landmarks) plus smoke-test coverage of several more (reflow, focus visibility, keyboard traps). The rest requires manual testing.

### Manual testing (keyboard + screen reader)

Interactive components — maps, carousels, lightboxes, dialogs, tab panels, autocompletes — need a human at the keyboard and a screen reader running. [manual-checklist.md](manual-checklist.md) walks through what to verify for each component type. Record results in a dated findings document.

## The program cycle

Repeat each release cycle (or quarterly, whichever comes first):

1. **Scan** — run the automated suite against a site with representative content.
2. **Test manually** — work through the manual checklist for high-risk components (prioritized in the page inventory).
3. **Triage** — consolidate results into a dated file in `findings/`, grouped by WCAG Success Criterion with severity and affected components. File a GitHub issue per distinct defect.
4. **Update the ACR** — adjust conformance levels in `acr/mukurtu-acr.yaml` to reflect what the audit actually found.
5. **Remediate** — fix issues in priority order (user-blocking first, then AA-failing, then best-practice). Re-run the scan for affected pages before merging.

**At each tagged release**, snapshot the ACR as a release artifact: the in-tree `acr/mukurtu-acr.yaml` is the living working copy that tracks development, but a conformance report is a claim about a specific version. Attach the ACR (and optionally its rendered markdown — see [acr/README.md](acr/README.md)) to the GitHub release so anyone can answer "is Mukurtu X.Y.Z conformant?" without digging through git history.

### Suggested GitHub issue conventions

- Label: `accessibility`
- Title prefix with the Success Criterion, e.g. `[WCAG 1.1.1] Media browse cards missing alt text`
- Body: affected page(s), the axe rule ID or manual test step, severity (`critical`/`serious`/`moderate`/`minor` — axe's own scale), and a suggested fix.

### The ratchet (path to CI enforcement)

Axe checks start report-only so a red wall of pre-existing violations doesn't block unrelated PRs. The plan:

1. **Now:** scans run locally and produce reports; findings drive remediation.
2. **Next:** the same spec runs in CI (`.github/workflows/playwright.yml`, against the Tugboat preview) and uploads the report as an artifact — still non-blocking.
3. **Then:** once a page reaches zero violations, it is moved to the "clean" list and any new violation on it fails CI. Page by page, the whole inventory becomes gating.

## Status

| Milestone | Status |
|---|---|
| Program charter, inventory, checklists | In place (July 2026) |
| Automated axe scan infrastructure | In place (July 2026); member-view + gated item pages covered |
| Baseline automated audit | Done — all 15 inventory pages scan clean after first remediations; see [findings/](findings/) |
| First remediations | Done (July 2026): Gin accent contrast, page-title landmark, Leaflet marker names |
| OpenACR report | First triage pass done (v2): 7 web criteria `supports`, authoring-tool 4.1.2 `partially-supports`; rest `not-evaluated` pending manual evidence |
| Manual audit of high-risk components | Not started — template ready ([findings/manual-findings-template.md](findings/manual-findings-template.md)); axe "incomplete" contrast queue folded in |
| CI integration (report-only) | Not started |
| Admin/authoring (ATAG) scope | Not started |
