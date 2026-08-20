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

*Record an outcome for EVERY row — passes included, not just failures. A
missing or empty cell is ambiguous (passed? never tested?), and the ACR can
only move a criterion to `supports` on the strength of a recorded passing
test; detailed narrative entries above are for failures only. This mirrors
WCAG-EM / Trusted Tester practice: every test gets an explicit outcome.
✅ pass · ❌ fail (append the finding ID, e.g. ❌ M1) · ➖ not applicable ·
⬜ planned but not run. The rows below are the standing inventory — add rows
when new pages/components ship, don't remove any.*

### Per-page passes

*Columns are the three page-level passes in [../manual-checklist.md](../manual-checklist.md). **Zoom/reflow is now fully automated** — fill that column from `test-results/a11y-extra/<page>-reflow.json` and `*-text-zoom.json` (✅ if the file has zero findings) rather than testing by hand; same for the parts of Keyboard already covered by the focus-visible/keyboard-trap automated checks (see the "What's automated now" table in the checklist) — only record a manual ❌/✅ there for what those checks can't judge.*

| Page | Keyboard | Screen reader | Zoom/reflow |
|---|---|---|---|
| Home `/` (anonymous) | ⬜ | ⬜ | ⬜ |
| Browse `/browse` (incl. Map view) | ⬜ | ⬜ | ⬜ |
| Digital Heritage browse `/digital-heritage` | ⬜ | ⬜ | ⬜ |
| Collections browse `/collections` | ⬜ | ⬜ | ⬜ |
| Communities `/communities` | ⬜ | ⬜ | ⬜ |
| Dictionary browse `/dictionary` | ⬜ | ⬜ | ⬜ |
| Login `/user/login` (incl. error state) | ⬜ | ⬜ | ⬜ |
| Home `/` (member) | ⬜ | ⬜ | ⬜ |
| My content `/my-content` (member) | ⬜ | ⬜ | ⬜ |
| Personal collections `/user/personal-collections` (member) | ⬜ | ⬜ | ⬜ |
| Account `/user` (member) | ⬜ | ⬜ | ⬜ |
| Digital heritage item (member) | ⬜ | ⬜ | ⬜ |
| Collection page (member) | ⬜ | ⬜ | ⬜ |
| Community page (member) | ⬜ | ⬜ | ⬜ |
| Dictionary word (member) | ⬜ | ⬜ | ⬜ |

### Component checks

*One row per component section in [../manual-checklist.md](../manual-checklist.md),
at the page where the component actually renders (see the
[page inventory](../page-inventory.md) priority list).*

| # | Component | Where to test | Result | Finding |
|---|---|---|---|---|
| 1 | Leaflet map — browse | `/browse` Map view | ⬜ | |
| 1 | Leaflet map — item location | digital heritage item | ⬜ | |
| 2 | Content warning overlay | digital heritage item with warnings | ⬜ | |
| 2 | Consent popup | first visit (clear cookies) | ⬜ | |
| 3 | Carousel (Splide) | digital heritage item with multiple media | ⬜ | |
| 4 | Lightbox (GLightbox) | digital heritage item media | ⬜ | |
| 5 | Audio player | dictionary word sample sentences | ⬜ | |
| 6 | Tabs | dictionary word | ⬜ | |
| 7 | Cultural protocol widget | any node add/edit form (member) | ⬜ | |
| 8 | Dialogs (aria-modal, Local Contexts) | where dialogs trigger | ⬜ | |
| 9 | Autocomplete (Tagify / membership) | member-facing forms | ⬜ | |
| 10 | Toggles — view switchers | `/browse`, `/dictionary` | ⬜ | |
| 11 | Toggles — facets show-more / search collapse | `/digital-heritage` | ⬜ | |
| 12 | Masonry grid | browse Grid view | ⬜ | |
| 13 | Multipage navigation | a multipage item | ⬜ | |

### Platform capability checks (1.2.x media alternatives)

*See "Platform capability checks" in [../manual-checklist.md](../manual-checklist.md) —
these evaluate whether an author CAN meet the criterion with stock Mukurtu,
not whether current content does.*

| Capability | Where to test | Result | Finding |
|---|---|---|---|
| Transcript renders near media (1.2.1, 1.2.3) | DH item with audio/video + `field_transcription` | ⬜ | |
| Captions on local video (1.2.2) | video media authoring + player output | ⬜ | |
| Captions on remote video (1.2.2) | captioned YouTube/Vimeo embed | ⬜ | |
| Audio description path (1.2.5) | DH item with video | ⬜ | |
| No autoplay (1.4.2) | any page with media | ⬜ | |
| Authoring guidance for alternatives (ATAG B) | media + DH authoring forms | ⬜ | |

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
