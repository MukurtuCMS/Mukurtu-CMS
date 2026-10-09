# Bucket B (visual pass), part 1

Bucket B of the [#2242 triage](2026-10-06-criteria-triage.md) is the 12
criteria needing a browser and a ruler but no assistive technology. This is
the first half: **6 decided**, against a clean install
(`~/ddev/mukurtu-a11y`, profile at `main`) plus source inspection.

The other 6 are listed at the end with what each is waiting on, rather than
recorded on thinner evidence than the first six.

## Decided (6)

| SC | Level | What decided it |
|---|---|---|
| 2.2.2 Pause, Stop, Hide | **not-applicable** | Nothing moves, blinks, scrolls or auto-updates. Both carousels are instantiated with no `autoplay` option (the library defaults it off), no media autoplays, and our stylesheets define no `@keyframes` |
| 2.5.1 Pointer Gestures | supports | Maps are the only candidate. Pinch-zoom exists on touch, but `zoomControlPosition` ships on every map display, so the +/- buttons are a single-pointer alternative. The lightbox deliberately does not implement pinch-zoom |
| 2.5.2 Pointer Cancellation | supports | No `mousedown` or `pointerdown` handlers anywhere; activation is on click, which fires on the up event and can be aborted |
| 3.2.3 Consistent Navigation | supports | Main menu identical in content and order across home, browse, communities and login, measured as a visitor |
| 3.2.4 Consistent Identification | supports | Search button, skip link and logo target identical on the same four pages |
| 1.4.12 Text Spacing | supports | Zero findings across 6 pages with the corrected check |

### Why 1.4.12's clean result is trustworthy now

The first run of `checkTextSpacing` reported **125 findings**. 121 of them
were `.visually-hidden` elements, which are one-pixel clipped boxes by
design and so match "content grew past its box" every single time. The check
was corrected in PR #2366 to detect hidden boxes by shape rather than class
name, and to skip deliberately scrollable containers. This evaluation ran
after that fix, so the zero reflects the content rather than a broken
measurement.

## Still open (6)

| SC | Waiting on |
|---|---|
| 1.4.4 Resize Text | Five admin pages are clean under real browser text sizing, but the inventory-wide check only measures that way once PR #2375 merges. Holding until a clean run exists, rather than recording a level on five pages |
| 2.4.7 Focus Visible | A keyboard-driven pass. `.focus()` does not trigger `:focus-visible`, which is where the theme puts its ring, so computed styles read after programmatic focus describe the wrong state. Folds naturally into bucket C |
| 3.3.2 Labels or Instructions | A form that reliably fails validation without a captcha |
| 3.3.3 Error Suggestion | Same |
| 1.4.1 Use of Color | Partial: required fields are marked with a `*` glyph via `::after`, not colour alone. Status and validation colours are unchecked and are the likelier risk |
| 1.4.5 Images of Text | The theme's images look like icons and UI graphics rather than text images, but that has not actually been confirmed by looking at them |

### A measurement that nearly became a false report

Measuring focus-ring contrast after `el.focus()` gave 1.5:1 on the header
search button, which would have contradicted 1.4.11's existing `supports`
claim. Checking that element directly showed `outline-style: none` under
programmatic focus: the colours being measured were not the focus ring at
all. **`.focus()` does not trigger `:focus-visible`.** Any 2.4.7 or 1.4.11
re-measurement has to drive focus from the keyboard.

### Why the login form could not be used for 3.3.2 and 3.3.3

Submitting bad credentials produced no error message, which looked like a
3.3.1 defect. It is not: the form carries an **ALTCHA captcha**, and request
tracing showed **no POST to `/user/login` happened at all**. The missing
error was the test being blocked, not the product failing.
