# Bucket B (visual pass), part 1

Bucket B of the [#2242 triage](2026-10-06-criteria-triage.md) is the 12
criteria needing a browser and a ruler but no assistive technology.
**11 of 12 are decided**, against a clean install (`~/ddev/mukurtu-a11y`,
profile at `main`) plus source inspection.

Recorded in two passes: six first, then five more once PR #2380 removed a
PHP warning that was printing over the error messages 3.3.3 needed graded.
The twelfth, 1.4.4, is held deliberately rather than recorded on thin
evidence.

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

## Part 2: decided (5)

Run after PR #2380 removed a PHP warning that was printing over the very
error messages 3.3.3 needed graded.

| SC | Level | What decided it |
|---|---|---|
| 2.4.7 Focus Visible | supports | 35 tab stops across 3 pages, every one with a drawn indicator of at least 2px and at least 3:1 |
| 3.3.2 Labels or Instructions | supports | 31 of 47 hidden-label controls are labelled by a visible column header; the other 16 sit under a visible fieldset instruction and carry a placeholder or self-describing default |
| 3.3.3 Error Suggestion | supports | "The email address is not valid. Use the format user@example.com" names the problem and the expected form |
| 1.4.5 Images of Text | supports | The only image the product renders is the site logo, which the criterion exempts |
| **1.4.1 Use of Color** | **partially-supports** | System messages are distinguished only by background colour |

### 1.4.1: the one failure

Error, warning and status blocks differ **only** by background colour:

| Type | Background |
|---|---|
| error | `rgb(88, 51, 51)` |
| warning | `rgb(72, 62, 30)` |
| status | `rgb(20, 82, 66)` |

No icon on any of them (`::before` has no content, no background image, no
mask), the left border is an identical transparent 2px on all three, and
padding is identical. The only non-colour cue is a `.visually-hidden`
heading, which a sighted reader never sees.

The wording of a message usually makes its nature clear on its own, which
limits the practical impact, so this is `partially-supports` rather than
`does-not-support`. An icon per type, or making the existing heading
visible, would close it.

Required-field markers and form errors are fine: the asterisk is a glyph,
and errors name the field in text.

### 2.4.7: why a naive check gets this wrong

The main navigation sets `outline: 0` on focus and draws its ring with a
`::before` pseudo-element instead. A check that reads only the `outline`
property reports those links as having no indicator at all. Two further
traps: `.focus()` does not trigger `:focus-visible`, so programmatic focus
measures the wrong state; and an outline is painted outside the border box,
so its contrast must be computed against the *parent's* background, not the
element's own. Measuring both wrongly produced a 1.5:1 reading on a ring
that is actually about 8.4:1.

## Still open (1)

| SC | Waiting on |
|---|---|
| 1.4.4 Resize Text | Five admin pages are clean under real browser text sizing, but the inventory-wide check only measures that way once PR #2375 merges. Holding until a clean run exists rather than recording a level on five pages |

### Two investigations that nearly became false reports

**Focus-ring contrast.** Measured after `el.focus()`, the header search
button read 1.5:1, which would have contradicted 1.4.11's existing
`supports` claim. That element reports `outline-style: none` under
programmatic focus. Measured correctly, from the keyboard and against the
parent's background, it is about 8.4:1. See the 2.4.7 note above.

**A login with no error message.** Submitting bad credentials produced
nothing, which looked like a 3.3.1 defect. Request tracing showed no POST
to `/user/login` happened at all: the form carries an ALTCHA captcha and
the submit never left the browser. 3.3.2 and 3.3.3 were evaluated on
authenticated forms instead.

**A PHP warning standing in for the error message.** Saving content while
belonging to no cultural protocol printed `Undefined array key
"protocol_selection"` over the real validation messages. That was a product
bug, fixed in PR #2380, and 3.3.3 could not be graded until it was.
