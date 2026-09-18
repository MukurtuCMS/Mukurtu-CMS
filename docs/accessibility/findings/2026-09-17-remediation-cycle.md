# Remediation cycle — September 2026

*Cycle run 2026-09-14 through 2026-09-17. Scope: the defect backlog carried
forward from the July and August automated cycles, plus defects filed during
the landing-page authoring audit (#1009).*

Unlike the earlier findings documents in this directory, which are organised
by scan cycle and by defect, **this one is organised by WCAG success
criterion** so the conformance report can cite it directly. The ACR notes
updated in this cycle (`acr/mukurtu-acr.yaml` v3) point here for evidence.

## Summary

Nine issues closed, all verified present on `main` rather than trusted from a
merge label. Four criteria changed state in the ACR: 1.4.10 and 1.4.11 moved
from `not-evaluated` to `supports`; 1.4.3 and 1.3.1 kept `supports` with
materially narrowed residual risk; authoring-tool 4.1.2 stays
`partially-supports` with its named defects resolved.

No manual keyboard or screen reader testing happened in this cycle. That
remains the critical path — see the note at the end.

---

## 1.4.10 Reflow (AA) — moved to `supports`

**Defect.** Between 480px and 520px the page scrolled horizontally by 1-7px
(#2197). At `>=sm` the mobile nav button's label un-hides and the button
grows from 32px to ~83px, but it occupied a single column of the header's
six-column grid — about 52px at that viewport — so it overhung the right edge
and extended the document.

**Fix.** PR #2224. The button's grid area spans two columns from the same
breakpoint the label appears at. A second, less obvious half was needed: the
button relies on `margin-inline-start: auto` to sit flush right, and that
computes to 0 once the button becomes `inline-flex` at the same breakpoint —
previously masked because the button was wider than its column and had
nowhere to move.

**Evidence.** Swept viewport widths 320px to 980px (20px steps, plus the
465-535px band in 5px steps) across `/`, `/digital-heritage`, `/collections`,
`/dictionary` and `/user/login`: no horizontal overflow at any width, where
480-520px previously overflowed on every page. Button geometry re-measured at
460/480/520/700px to confirm it stays flush right.

**Why this supports an automated-only claim.** `checkReflow` is a real
overflow assertion with no human judgement (see `manual-checklist.md`'s
"What's automated now" table), and `reflow.spec.ts` additionally gates it in
CI.

**Residual.** None known at Level AA for this criterion. Text-resize (1.4.4)
is a separate criterion and remains open.

---

## 1.4.11 Non-text Contrast (AA) — moved to `supports` (focus indicators)

**Defect.** `--focus-color` was `#66afe9`, the outline colour for every link,
button, input and select in the theme. It measured 1.59-2.37:1 against every
light surface the theme uses and only passed on the dark footer and brand red
(#2199). No single opaque colour clears 3:1 against both ends of that palette.

**Fix.** PR #2221. The token is now `currentcolor`, so each ring inherits the
element's own text colour — already chosen to contrast with whatever it sits
on. Two cases need a hard-coded ring instead and are documented in the token's
comment: elements over user-uploaded imagery, and white-on-dark fills (an
outline paints *outside* the element, so a white label on a dark button would
otherwise put a white ring on the light page behind it — and the global
`button:focus` rule turns *every* button's label white while focused).

**Evidence.** A probe that detects indicators drawn via `outline`,
`box-shadow` **or** a `::before`/`::after` pseudo-element, measured against the
surface the ring actually abuts: 25 components x 2 palettes in an isolated
fixture on the real compiled CSS (50/50 pass), plus 101 focusable elements
across seven running pages (0 failures). Before/after spot values: body links
2.37 to 4.70:1, inputs 2.37 to 21:1, breadcrumbs 1.16 to 4.62:1, footer logo
2.01 to 5.88:1.

**Method note worth keeping.** An outline-only check is what produced the
false positive in #2187, where a focus indicator drawn as a `::before`
pseudo-element was reported as missing. An earlier version of the probe also
credited an element's own fill when `outline-offset > 0`, which is wrong — a
gap of page background sits between ring and fill.

**Residual.** Scope of this claim is focus indicators. Non-text contrast of
other graphical objects (icon-only affordances, map controls) is queued for
the manual audit.

---

## 1.4.3 Contrast (Minimum) (AA) — stays `supports`, residual narrowed

Two of the three cases the July cycle queued as "automated tools cannot judge"
are now measured and fixed.

**Hero text over photographs (#2175).** The `full_image_with_description`
block rendered text directly over an author-uploaded image with only a binary
light/dark text toggle and a `text-shadow`, which is not a recognised contrast
technique. Measured against a realistic mid-tone photograph neither setting
passed across the whole block. Fixed in PR #2207 with an opaque per-glyph
stroke (`-webkit-text-stroke`, `paint-order: stroke fill`, with a layered
`text-shadow` fallback). Because the stroke fully separates the fill colour
from the photograph on all sides, its contrast is fixed and provable
independent of the uploaded image: white stroke against the two built-in
`--brand-primary-dark` values gives **5.00:1** (`#107996`) and **8.39:1**
(`#9a1134`); the light-text variant uses a black stroke at **21:1**. Verified
independently of the implementation's own comments.

**Horizontal panel per palette (#2178).** `.block--image-with-description--horizontal`
used `--brand-secondary` behind `--brand-primary-dark` text, which is 5.65:1
on Red and bone but **2.45:1** on Blue and gold. Fixed in the same PR with a
dedicated token defaulting to the brand colour and overridden per palette,
reaching **4.78:1** on Blue and gold.

**Residual.** Map controls remain queued for manual contrast measurement.
This is the one item keeping 1.4.3's note from being unqualified.

---

## 1.3.1 Info and Relationships (A) — stays `supports`, residual narrowed

**Defect.** All three image-with-description block templates set their
heading level from `is_front ? 'h1' : 'h2'` (#2176). Once those bundles became
individually placeable, a landing page with several of them emitted several
first-level headings; a landing page with none emitted no first-level heading
at all, because the real page title is deliberately suppressed there.

**Fix.** PR #2207. Heading level is now fixed rather than derived, and a
visually-hidden first-level heading carrying the node title is guaranteed on
the front page. Keyed on `isFrontPage()` rather than the landing-page content
type: a bundle-based check would have double-emitted on the non-front-page
landing pages the install routine creates and orphans. Kernel test
`HiddenFrontPageTitleTest` covers that case.

**Related (ATAG B.2.2).** The same PR made the "Display title" checkbox
functional on those bundles (#2177); it had been silently ignored, so authors
were handed a control that did nothing.

**Residual.** Heading outline *quality* and reading order still require the
manual audit.

---

## 4.1.2 Name, Role, Value (A), authoring-tool — stays `partially-supports`

**Defect.** On `/admin/content` with the toolbar collapsed — which is the
default for every new Mukurtu admin — axe reported 7 `button-name`, 8
`link-name` and 3 `aria-valid-attr` violations (#2003). Gin hides collapsed
toolbar labels with `display: none`, which removes them from the accessibility
tree rather than just from view; separately its templates wrote
`aria-toolbar-link__labelledby`, a typo for `aria-labelledby`.

**Fix.** PR #2225. The label hiding is overridden with a visually-hidden
technique, leaving the icon-only rail pixel-identical. The invalid attribute
is corrected by carrying Gin's own reviewed fix (merge request !809) as a
composer patch until it is released upstream; the label fix is a local
override because the corresponding upstream merge request has been stalled
since 2025-08.

**Evidence.** Re-scan of `/admin/content`, toolbar collapsed, admin session:
**18 violations before, 0 after** across those three rules. All three toolbar
menus now resolve `aria-labelledby` to real headings.

**Residual.** The full authoring interface has still not been evaluated, so
this stays a partial claim pending Phase 2 (#1975).

---

## Not conformance defects, fixed in the same cycle

- **#2208** — the Landing Pages URL alias pattern selected the `page` bundle
  instead of `landing_page`, so landing pages got no alias. Fixed with an
  update hook that rewrites only the exact shipped value (PR #2227).
- **#2209** — removed an unreferenced duplicate of the Blue and gold palette
  (PR #2226).

## Carried forward

- **The manual audit has still not been run.** It is the only thing that can
  discharge the remaining `not-evaluated` criteria, since automated checks
  cannot decide them. `manual-checklist.md`'s component items were tagged with
  success criteria in this cycle so a completed pass can be traced back to ACR
  rows.
- **CI accessibility output is not currently representative.** The specs run
  on every PR, but no `A11Y_*` credentials are set, so member, manage-adjacent
  and admin scans all run as `admin`. Results are also discarded rather than
  uploaded.
- **Map control contrast** (1.4.3, 1.4.11) is the narrowest remaining
  automated-evidence gap.
- **#2211** — the ALTCHA v3 re-land is blocked on an upstream stable release.
