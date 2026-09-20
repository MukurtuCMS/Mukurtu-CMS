# Mukurtu CMS Accessibility Program

This document is the roadmap for Mukurtu's ongoing accessibility program. It explains what we are aiming for, what is in scope, how audits run, and the cycle we repeat to maintain continual improvements.

For the list of pages and components under audit, see [page-inventory.md](page-inventory.md) (Phase 1: visitor/member/manage-adjacent) and [page-inventory-admin.md](page-inventory-admin.md) (Phase 2: admin/authoring). For hands-on keyboard and screen reader testing, see [manual-checklist.md](manual-checklist.md). Dated audit results live in [findings/](findings/). The conformance report lives in [acr/](acr/).

---

## Conformance target

**WCAG 2.1 Level AA.**

This matches Drupal core's own accessibility commitment and the requirements of Section 508 (US) and most institutional policies that Mukurtu's community partners — universities, libraries, archives, and museums — operate under. WCAG 2.2 criteria are noted informationally when we encounter them but are not yet measuring against.

## Scope

The program runs in two phases, against two W3C standards that cover different
things:

- **WCAG** ([Web Content Accessibility Guidelines](https://www.w3.org/TR/WCAG21/)) applies to the *pages Mukurtu
  renders* — it is the standard for both phases, since visitors and authors
  alike experience rendered pages.
- **ATAG 2.0** ([Authoring Tool Accessibility Guidelines](https://www.w3.org/TR/ATAG20/))
  applies to *software used to create web content* — which is applicable to Mukurtu CMS. It
  has two halves: **Part A** requires the authoring interface itself to be
  accessible (a screen reader or keyboard-only user must be able to *be an
  author* — create items, upload media, manage protocols); **Part B** requires
  the tool to help authors *produce* accessible content (prompting for alt
  text, preserving accessibility information through workflows, guiding
  authors toward transcripts and captions). Drupal core has committed to
  ATAG 2.0 AA for its administration interface, which sets the precedent
  Mukurtu CMS follows.

**Phase 1 (current): visitor and logged-in member experiences, against WCAG
2.1 AA.** Everything an anonymous visitor or an authenticated
community/protocol member sees: browsing, searching, digital heritage items,
collections, dictionaries, communities, maps, and account pages.

**Phase 2 (later/soon): the authoring and administrative experience, against WCAG
2.1 AA *and* ATAG 2.0.** Content creation forms, bulk media upload,
import/export, dashboards.

**How the two phases relate.** They are one program, not two. There is a
single remediation cycle (below), a single conformance report, and one
inventory per phase rather than one per team: Phase 1 owns
[page-inventory.md](page-inventory.md) and Phase 2 owns
[page-inventory-admin.md](page-inventory-admin.md). The ACR carries both as
separate components — `web` for what a visitor or member sees, and
`authoring-tool` for the admin/authoring surface — so each criterion can hold
a different claim per phase, and neither phase's evidence is blocked on the
other's. A defect found in either phase is filed, triaged and remediated the
same way. Phase 2's tracking issue is
[#1975](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1975).

**Why are we doing this in stages:** the visitor/member experience is the smaller, self-contained
surface with the largest audience. The authoring
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

The scans are **report-only**: violations never fail the tests. Each page's full axe results are written to `test-results/a11y/<page>.json` and attached to the Playwright HTML report.

### Automated checks beyond axe (reflow, focus, links, keyboard traps)

`tests/accessibility-automated-checks.spec.ts` runs a second layer that axe-core can't do on its own: real WCAG 1.4.10 reflow and approximated 1.4.4 text-zoom checks (both fully automated, no human judgment needed), a WCAG 2.4.7 focus-visibility smoke test, a WCAG 2.4.4 vague-link-text heuristic, and a WCAG 2.1.2 keyboard-trap smoke test. These narrow the manual checklist down further — see [manual-checklist.md](manual-checklist.md)'s "What's automated now" table for exactly what each one catches and what still needs a human.

```bash
cd tests/playwright
PLAYWRIGHT_BASE_URL=https://mukurtu.ddev.site npx playwright test accessibility-automated-checks --project=chromium
```

Also report-only; results land in `test-results/a11y-extra/<page>-<check>.json`.

Between the two layers, automated scanning now catches roughly 30–40% of WCAG issues outright (missing alt text, form labels, contrast, ARIA misuse, duplicate landmarks) plus smoke-test coverage of several more (reflow, focus visibility, keyboard traps). The rest requires manual testing.

### The public submission form

The submission form ships disabled, so it isn't in the page inventory the two suites above walk. `tests/submission-form.spec.ts` enables it, runs both the axe scan and the automated checks against it and its thank-you page, then restores the setting. It's a standalone file rather than living in either suite above specifically so there is only one place enabling/disabling this shared, site-wide setting — see the file's own comment for why splitting it across two files caused every CI run to fail with a login timeout.

```bash
cd tests/playwright
PLAYWRIGHT_BASE_URL=https://mukurtu.ddev.site npx playwright test submission-form --project=chromium
```

### Phase 2 (admin/authoring) automated scans

`tests/accessibility-admin.spec.ts` and `tests/accessibility-automated-checks-admin.spec.ts` are the admin-routes equivalents of the two suites above, run against the curated set in [page-inventory-admin.md](page-inventory-admin.md):

```bash
cd tests/playwright
PLAYWRIGHT_BASE_URL=https://mukurtu.ddev.site npx playwright test accessibility-admin --project=chromium
PLAYWRIGHT_BASE_URL=https://mukurtu.ddev.site npx playwright test accessibility-automated-checks-admin --project=chromium
```

Note: Playwright's `test` CLI filters by substring match on filename, so the bare `accessibility`/`accessibility-automated-checks` commands above now also pick up these admin spec files (all four filenames contain those substrings) — use the full `-admin` suffix to run Phase 2 in isolation, or the bare prefix to run everything together. `tests/submission-form.spec.ts` matches none of these substrings and has to be run by its own name (or via an unfiltered `npx playwright test`).

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
| Automated axe scan infrastructure | In place (July 2026); member-view + all discovered item pages covered anonymously and as a member |
| Baseline automated audit | Done — all 19 inventory pages scan clean (full anonymous + member item-page coverage as of 2026-07-27); see [findings/](findings/) |
| First remediations | Done (July 2026): Gin accent contrast, page-title landmark, Leaflet marker names |
| OpenACR report | Second pass done (v3, 2026-09-17): 9 web criteria `supports`, authoring-tool 4.1.2 `partially-supports`, 90 `not-evaluated`. 1.4.10 Reflow and 1.4.11 Non-text Contrast moved up on merged, measured evidence; 1.4.3 and 1.3.1 keep `supports` with narrowed residual risk. Evidence recorded per success criterion in [findings/2026-09-17-remediation-cycle.md](findings/2026-09-17-remediation-cycle.md). The remaining `not-evaluated` rows need the manual pass, not more automation |
| Manual audit of high-risk components | Not started, tracked in [#2242](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/2242) — template ready ([findings/manual-findings-template.md](findings/manual-findings-template.md)); axe "incomplete" contrast queue folded in. **This is the critical path to a publishable ACR**: the manual pass is the only thing that can discharge the bulk of the remaining `not-evaluated` criteria, since automated checks cannot decide them |
| CI integration (report-only) | Partly done, and further along than this table previously claimed. The remaining work is tracked in [#2241](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/2241). All of the accessibility specs already run on every PR and every push to `main` — `.github/workflows/playwright.yml` runs an unfiltered `npx playwright test`, and `playwright.config.ts` only ignores `default-content.spec.ts`. `reflow.spec.ts` is additionally already *gating* (it asserts rather than reports), so one WCAG 1.4.10 ratchet exists ahead of the plan below. Results are uploaded as the `accessibility-results-*` artifact on both the PR and main-push jobs, Phase 2 writes its slugs under a `phase2-` prefix so the two phases no longer co-mingle, and `globalTimeout` is 30 minutes. `A11Y_USERNAME`/`A11Y_PASSWORD`/`A11Y_MANAGER_*` were set on 2026-09-20, so the member and manage-adjacent scans should no longer fall back to `admin`/`admin` — the failure mode flagged in bold in [findings/2026-07-22-post-merge-verification.md](findings/2026-07-22-post-merge-verification.md). [#2249](https://github.com/MukurtuCMS/Mukurtu-CMS/pull/2249) provisions the matching accounts during the Tugboat build and [#2257](https://github.com/MukurtuCMS/Mukurtu-CMS/pull/2257) fixed the empty-string fallback that would otherwise have made setting them log in with an empty username. To confirm a given run used the real accounts, check its report for `a11y-account` annotations: the suite records one per scan that fell back, so none means the credentials resolved and the accounts existed |
| Admin/authoring (ATAG) scope | **Underway independently, tracked in [#1975](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1975)** — see that issue for current status rather than this table. In place so far: a form-by-form audit of all 135 custom admin form classes (4 defects found and fixed), [page-inventory-admin.md](page-inventory-admin.md), and the two `-admin` spec files. Its findings land in the `authoring-tool` component of the shared ACR |
