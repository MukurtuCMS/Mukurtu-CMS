# Manual Accessibility Testing Checklist

Automated scans catch only a third or so of WCAG issues. This checklist covers the rest: what a human verifies with a keyboard and a screen reader. Work through it per page for the general checks, and per component for the high-risk widgets in the [page inventory](page-inventory.md).

Two layers of automated scanning already run before any of this: **axe-core** (`tests/playwright/tests/accessibility.spec.ts`) for standard WCAG rule violations, and **automated checks** (`tests/playwright/tests/accessibility-automated-checks.spec.ts`) for a handful of things axe can't assert on its own — reflow/zoom, focus visibility, vague link text, and a keyboard-trap smoke test. Results land in `test-results/a11y/` and `test-results/a11y-extra/` respectively (gitignored; re-run before a session, don't rely on stale files). **Run both before starting a manual pass** — don't manually re-check anything they already cover; use their output as your starting point instead. See the "What's automated now" table below for exactly where the line sits.

Record results in a dated file under [findings/](findings/) — copy [findings/manual-findings-template.md](findings/manual-findings-template.md) to `findings/YYYY-MM-manual.md` and fill it in as you go.

**Screen readers to test with:** NVDA + Firefox (Windows), VoiceOver + Safari (macOS), or Orca + Firefox (Linux). One is enough per audit pass; rotate across passes.

---

## What's automated now

The honest line between "a script checked this" and "only a human can confirm this," as of the second automated-checks layer (2026-07). Update this table whenever a check's coverage changes — it's the thing that keeps the checklist below from silently duplicating work the machines already do.

| Checklist item | Status | How | What still needs a human |
|---|---|---|---|
| Skip link exists | Automated | axe `skip-link` rule (best-practice tag) | Whether it's visible on focus and actually moves focus when activated |
| No positive `tabindex` | Automated | axe `tabindex` rule (best-practice tag) | — |
| Heading order (no skipped levels) | Automated | axe `heading-order` rule (best-practice tag) | Whether the outline is *meaningful*, not just non-skipping |
| Landmarks present, content contained | Automated | axe `region` rule (best-practice tag) | Whether landmark labeling makes sense read aloud |
| Zoom disabled in viewport meta | Automated | axe `meta-viewport` rule (wcag2aa tag) | — |
| **Reflow at 320px (1.4.10)** | **Automated** | `checkReflow` — real horizontal-overflow assertion, no human judgment needed | — |
| **Text resize to 200% (1.4.4)** | **Semi-automated** | `checkTextZoom` — approximates zoom via root font-size, not true browser zoom | Confirm any flagged page in a real browser's zoom before filing |
| **Focus visible everywhere (2.4.7)** | **Semi-automated** | `checkFocusVisible` — flags `outline:none`/no `box-shadow` on focus | Whether an indicator that *is* present has sufficient contrast/thickness |
| **Vague link text (2.4.4)** | **Semi-automated** | `checkLinkText` — flags exact-match generic phrases ("click here", "read more"…) with no extra accessible context | Link text that's *specifically* misleading rather than generically vague (the heuristic only catches the common phrasing list) |
| **No keyboard trap (2.1.2)** | **Semi-automated, smoke test only** | `checkKeyboardTrap` — tabs through the page, flags a cycle confined to a subset of the page's tab stops | Traps that only appear after an interaction (e.g. opening a modal first); compound native controls (audio/video/`<select>`) are flagged separately as "confirm manually" since their internal focus isn't visible outside their shadow DOM |
| Everything keyboard-reachable/operable (2.1.1) | Human | — | Arrow-key/Enter/Space behavior inside composite widgets requires real interaction |
| Logical/visual reading order (2.4.3) | Human | — | No reliable general heuristic for "does DOM order match visual order" |
| No surprise on focus (3.2.1) | Human | — | Requires observing actual navigation/submission side effects |
| Alt text *quality*, not just presence (1.1.1) | Human | — | Axe/checks confirm an `alt` attribute exists — not that it's accurate or useful |
| Screen reader announcements (headings read sensibly, dynamic updates, form errors) | Human | — | Requires an actual screen reader session |
| Language of parts for Indigenous-language content (3.1.2) | Human | — | Depends on editorial content, not markup a script can verify generically |
| Component interaction patterns (ARIA roles' *behavior*, not just presence) | Human | — | See the component sections below |
| Media alternatives (captions, transcripts, audio description) | Human (capability test) | — | See "Platform capability checks" below |

---

## Per-page keyboard pass

Tab from the top of the page to the bottom. Check the automated reports first — skip-link existence, no-traps, and focus-visible all have a machine-generated starting point now (see the table above); this pass is about what they can't judge:

- [ ] **Skip link works** — activating it (not just its presence) actually moves focus into `#main-content` (2.4.1)
- [ ] **Logical order** — focus moves in reading order, no surprising jumps (2.4.3)
- [ ] **Focus indicator quality** — where the automated check found a visible outline/box-shadow, confirm it has enough contrast and thickness to actually see (2.4.7)
- [ ] **Everything reachable** — every link, button, form control, and custom widget can receive focus (2.1.1)
- [ ] **Everything operable** — Enter activates links; Enter/Space activates buttons; arrows work inside composite widgets (2.1.1)
- [ ] **No traps after interaction** — the automated smoke test only catches traps present on page load; open modals/dropdowns/carousels first, then confirm Tab still escapes (2.1.2). For any page the automated check flagged as "needs manual confirmation" (native audio/video/select controls), confirm Tab actually exits that control.
- [ ] **No surprise on focus** — focusing a control never triggers navigation or submission by itself (3.2.1)

## Per-page screen reader pass

Walk the page with the screen reader's reading commands (not just Tab). Landmark presence, non-skipping heading levels, and page title existence are already axe-checked (see the table above) — this pass is about whether they're actually *meaningful* read aloud:

- [ ] **Page title** announces and describes the page, not just exists (2.4.2)
- [ ] **Landmarks** are labeled sensibly when there's more than one of a kind (1.3.1)
- [ ] **Headings** form a sensible outline, not just a non-skipping one (1.3.1, 2.4.6)
- [ ] **Images** — meaningful images have useful alt text; decorative ones are silent (1.1.1)
- [ ] **Links** make sense read alone — the automated check only catches generic phrases like "read more" with zero extra context; confirm link text is specifically accurate, not just non-generic (2.4.4)
- [ ] **Form fields** announce a label, required state, and any error text (1.3.1, 3.3.1, 3.3.2)
- [ ] **Dynamic updates** — AJAX results (facets, view switches) are announced or focus moves to them (4.1.3)
- [ ] **Language** — page language is set; passages in Indigenous languages carry `lang` attributes where language codes exist (3.1.1, 3.1.2)

## Per-page zoom/reflow pass

**Fully automated** — `checkReflow` and `checkTextZoom` in `accessibility-automated-checks.spec.ts` assert both directly (real horizontal-overflow checks, no human judgment needed for reflow; text-zoom is a close approximation). Pull results from `test-results/a11y-extra/*-reflow.json` and `*-text-zoom.json` instead of testing this by hand. Only re-check manually if you want to confirm a flagged page in a real browser's zoom control rather than the approximated version:

- [ ] At 200% browser zoom, no text is cut off and nothing overlaps (1.4.4)
- [ ] At 320px effective width (400% zoom on a 1280px window), content reflows to one column with no horizontal scrolling (1.4.10)

---

## Component-specific checks

### Maps (Leaflet — browse map, item location)

- [ ] Map container is reachable and escapable with the keyboard
- [ ] Zoom controls are real buttons with labels, keyboard-operable
- [ ] Markers/popups can be opened via keyboard and are announced
- [ ] Information conveyed by the map is available another way (the list view counts — verify the equivalence and link between them)
- [ ] Bounding-box "search this area" has a keyboard-accessible alternative

### Content warning overlay / consent popup

- [ ] When the overlay appears, focus moves into it and is announced
- [ ] Focus is trapped inside while open; Escape or an explicit button dismisses it
- [ ] On dismissal, focus returns to a sensible place
- [ ] The warning text itself is read (not just the buttons)
- [ ] Page content behind it is hidden from the screen reader while open (`aria-modal` or `inert`)

### Carousel (Splide)

- [ ] Prev/next controls are labeled buttons
- [ ] Arrow-key navigation works; current slide is announced
- [ ] No auto-advance, or it can be paused (2.2.2)
- [ ] Off-screen slides are hidden from Tab and the screen reader

### Lightbox (GLightbox)

- [ ] Opens: focus moves in; trapped while open; Escape closes; focus returns to the trigger
- [ ] Close/prev/next controls are labeled
- [ ] Media inside (image alt, video captions) survives the transfer into the lightbox

### Audio player

- [ ] Play/pause is a button with a label that reflects state
- [ ] All controls keyboard-operable; state changes announced
- [ ] A transcript or text alternative is available for spoken audio content (1.2.1)
- [ ] **Tab actually exits the player.** The automated keyboard-trap check
      can't see inside a native `<audio controls>` element's shadow DOM — from
      outside, its internal play/seek/volume controls look like focus never
      advances at all, which the check flags as "needs manual confirmation"
      rather than a confirmed trap. Tab through the player by hand and confirm
      focus does eventually move to the next page element (2.1.2).

### Tabs (dictionary word)

- [ ] `tablist`/`tab`/`tabpanel` roles present; `aria-selected` tracks state
- [ ] Arrow keys move between tabs; Tab moves into the panel (roving tabindex)
- [ ] Inactive panels hidden from keyboard and screen reader

### Autocomplete (Tagify, membership autocomplete)

- [ ] Follows the combobox pattern: suggestions announced as you type, arrow keys select, Enter commits, Escape dismisses
- [ ] Chosen tags are announced and individually removable via keyboard

### Toggles (view switchers, facet show-more, collapse buttons)

- [ ] Toggle is a button, not a div/link with a click handler
- [ ] `aria-expanded` (or `aria-pressed`) reflects state
- [ ] The revealed content is adjacent in the focus order

### Dialogs (aria-modal dialogs, Local Contexts dialog)

- [ ] `role="dialog"` + `aria-modal="true"` + accessible name
- [ ] Focus in on open, trapped while open, restored on close; Escape closes

### Masonry grid

- [ ] DOM order matches visual reading order closely enough that keyboard traversal isn't disorienting (1.3.2, 2.4.3)

---

## Platform capability checks (author-provided alternatives)

Criteria like captions (1.2.2) and media alternatives (1.2.1, 1.2.3, 1.2.5)
depend on content that site owners add later. For a platform ACR the test is
**capability**: can an author, using only stock Mukurtu, produce content that
passes? (VPAT "Supports" = "at least one method that meets the criterion.")
Test as an author — seed one local video, one remote video, and one audio item,
then attempt each path:

- [ ] **Transcript path (1.2.1, 1.2.3):** fill `field_transcription` on a
      digital heritage item with audio/video → verify the transcript actually
      renders on the item page near the media (searchable-but-hidden does not
      satisfy the criterion)
- [ ] **Captions, local video (1.2.2):** attempt to attach a caption/subtitle
      file to an uploaded video → does any mechanism exist? Does the player
      output a `<track>` element? (Known Drupal core gap — issue #3002770)
- [ ] **Captions, remote video (1.2.2):** embed a captioned YouTube/Vimeo
      video → verify the provider's caption control survives the embed
- [ ] **Audio description (1.2.5):** is there any path (descriptive audio
      version as an alternate file, or 1.2.3-style alternative)?
- [ ] **Audio control (1.4.2):** confirm no media autoplays anywhere
- [ ] **Authoring guidance (ATAG B, authoring-tool component):** are the
      alternative fields visible, labeled, and explained in the authoring
      forms — would an author know to use them?

Record outcomes in the findings file and write the ACR notes in capability
language: "Authors can satisfy X by …" / "No mechanism exists for X — issue #N."

## Quick reference: WCAG 2.1 AA criteria most often failed manually

| SC | What to check |
|---|---|
| 1.1.1 | Alt text quality (not just presence — axe checks presence) |
| 1.2.x | Captions and transcripts on audio/video |
| 1.3.2 / 2.4.3 | Reading and focus order, especially masonry/grid layouts |
| 1.4.1 | Color is never the only signal (link styling, map markers, required fields) |
| 2.1.1 / 2.1.2 | Full keyboard operation, no traps |
| 2.4.7 | Focus visible everywhere |
| 3.3.1 / 3.3.2 | Form errors identified, labeled, and announced |
| 4.1.2 | Custom widgets expose name, role, value |
| 4.1.3 | Status messages announced without stealing focus |
