# Manual Accessibility Findings — TEMPLATE

> Copy this file to `findings/YYYY-MM-manual.md` for each manual testing pass.
> Everything in *italics* is instructions or example content — replace it.
> Automated (axe) results have their own findings files; this template is for
> what a human finds with a keyboard, a screen reader, and a contrast tool,
> following [../manual-checklist.md](../manual-checklist.md).

## Session details

| | |
|---|---|
| Date | *YYYY-MM-DD* |
| Auditor | *name* |
| Environment | *e.g. local DDEV (`mukurtu.ddev.site`), branch/commit* |
| Browser | *e.g. Firefox 141* |
| Screen reader | *e.g. NVDA 2026.1 / VoiceOver / Orca* |
| Account(s) used | *e.g. anonymous + `a11y_member` (community/protocol member)* |
| Zoom/reflow checked at | *e.g. 200% and 320px effective width* |

## Scope of this pass

*Which pages and components from the [page inventory](../page-inventory.md) were
covered, and which checklist sections ran. Be explicit about what was NOT
covered so the next pass knows where to start.*

- *e.g. Component #1: Leaflet maps (browse map + digital heritage item map)*
- *e.g. Component #2: content warning overlay / consent popup*
- *e.g. Per-page keyboard pass: home, browse, digital heritage item*
- *Not covered: carousels, lightbox, audio player (next pass)*

---

## Findings

*One entry per distinct defect. Number them sequentially (M1, M2, …) so issues
and the ACR can reference them. Delete the example below.*

### M1. *Map keyboard trap: focus cannot leave the browse map* — EXAMPLE

| | |
|---|---|
| WCAG SC | *2.1.2 No Keyboard Trap (Level A)* |
| Severity | *critical / serious / moderate / minor* |
| Component | *Leaflet browse map (`views.view.mukurtu_browse_by_map`)* |
| Page(s) | */browse (Map view), as anonymous and member* |
| Assistive tech | *Keyboard only; confirmed with NVDA + Firefox* |

**Steps to reproduce:**

1. *Go to /browse and switch to Map view.*
2. *Tab until focus enters the map container.*
3. *Continue pressing Tab.*

**Expected:** *Focus moves through the map controls and then out to the next
element on the page.*

**Actual:** *Focus cycles between the zoom controls and markers indefinitely;
Escape does nothing; the only exit is Shift+Tab back the way you came.*

**Suggested fix:** *Note the likely code location if known, e.g.
`modules/mukurtu_browse/js/mukurtu-leaflet-preview.js`, and the pattern to
apply (see ../manual-checklist.md → Maps).*

**Tracking:** *GitHub issue link once filed — title format
`[WCAG 2.1.2] Focus trapped in browse map`.*

---

### M2. *…next finding…*

---

## Checklist results

*Quick pass/fail record against the checklist sections that ran, so coverage
is auditable later. ✅ pass · ❌ fail (link the finding) · ➖ not applicable ·
⬜ not tested.*

| Checklist section | Page/component | Result | Finding |
|---|---|---|---|
| *Per-page keyboard pass* | *home* | *✅* | |
| *Per-page keyboard pass* | */browse (Map view)* | *❌* | *M1* |
| *Per-page screen reader pass* | *home* | *⬜* | |
| *Maps* | *browse map* | *❌* | *M1* |
| *Content warning overlay* | *digital heritage item* | *⬜* | |

## Contrast checks (from axe "incomplete" queue)

*Axe queues elements it can't judge (text over images, overlays) for human
review — the current queue is listed in the latest automated findings file.
Record measured ratios here.*

| Element | Page | Measured ratio | Required | Result |
|---|---|---|---|---|
| *Leaflet zoom "−" control* | *digital heritage item* | *e.g. 3.9:1* | *4.5:1* | *❌ → M3* |
| *Block h2 over hero image* | *home* | | *4.5:1* | |

## Triage handoff

*Filled in during the triage step of the [program cycle](../README.md):*

- [ ] *GitHub issue filed for each finding (label `accessibility`, `[WCAG x.x.x]` title prefix)*
- [ ] *ACR conformance levels updated in [../acr/mukurtu-acr.yaml](../acr/mukurtu-acr.yaml) for every SC touched by a finding (with issue links in the notes)*
- [ ] *Items added to the next-cycle actions list*

## Not covered / next pass

*What this session ran out of time for, plus anything discovered along the way
that needs a dedicated look.*
