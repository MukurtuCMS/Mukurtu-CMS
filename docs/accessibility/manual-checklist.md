# Manual Accessibility Testing Checklist

Automated scans catch only a third or so of WCAG issues. This checklist covers the rest: what a human verifies with a keyboard and a screen reader. Work through it per page for the general checks, and per component for the high-risk widgets in the [page inventory](page-inventory.md).

Record results in a dated file under [findings/](findings/) — copy [findings/manual-findings-template.md](findings/manual-findings-template.md) to `findings/YYYY-MM-manual.md` and fill it in as you go.

**Screen readers to test with:** NVDA + Firefox (Windows), VoiceOver + Safari (macOS), or Orca + Firefox (Linux). One is enough per audit pass; rotate across passes.

---

## Per-page keyboard pass

Tab from the top of the page to the bottom. Verify:

- [ ] **Skip link** — first Tab reveals a "skip to main content" link that works (2.4.1)
- [ ] **Logical order** — focus moves in reading order, no surprising jumps (2.4.3)
- [ ] **Visible focus** — you can always see where focus is, on every element (2.4.7)
- [ ] **Everything reachable** — every link, button, form control, and custom widget can receive focus (2.1.1)
- [ ] **Everything operable** — Enter activates links; Enter/Space activates buttons; arrows work inside composite widgets (2.1.1)
- [ ] **No traps** — you can Tab out of every component, including embedded maps and players (2.1.2)
- [ ] **No surprise on focus** — focusing a control never triggers navigation or submission by itself (3.2.1)

## Per-page screen reader pass

Walk the page with the screen reader's reading commands (not just Tab). Verify:

- [ ] **Page title** announces and describes the page (2.4.2)
- [ ] **Landmarks** — header/nav/main/footer are announced; content lives in landmarks (1.3.1)
- [ ] **Headings** form a sensible outline; levels don't skip arbitrarily (1.3.1, 2.4.6)
- [ ] **Images** — meaningful images have useful alt text; decorative ones are silent (1.1.1)
- [ ] **Links** make sense read alone — no bare "read more" without context (2.4.4)
- [ ] **Form fields** announce a label, required state, and any error text (1.3.1, 3.3.1, 3.3.2)
- [ ] **Dynamic updates** — AJAX results (facets, view switches) are announced or focus moves to them (4.1.3)
- [ ] **Language** — page language is set; passages in Indigenous languages carry `lang` attributes where language codes exist (3.1.1, 3.1.2)

## Per-page zoom/reflow pass

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
