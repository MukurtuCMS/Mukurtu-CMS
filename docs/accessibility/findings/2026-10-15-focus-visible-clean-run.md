# 2.4.7 Focus Visible: the clean run

**One level moves: 2.4.7 from `partially-supports` to `supports`.**

The criterion was downgraded on 13 October when an audit of a new confirm
form found that Gin leaves every admin form's original action buttons in the
tab order with none of their box on screen. Two fixes and two rebuilds later,
the inventory is clean and the evidence is a run rather than a merge.

## The run

[38752434462](https://github.com/MukurtuCMS/Mukurtu-CMS/actions/runs/38752434462),
on `main` at `05ca5a668`. 193 passed, 2 skipped, 0 failed. The two skips are
the documented `protocol-local-contexts` exception, which has no anonymous
navigation path.

| Pages | Focus findings |
|---|---|
| Visitor and member (phase 1) | 31 pages, 0 |
| Administrative (phase 2) | 14 pages, 0 |
| **Total** | **45 pages, 0** |

## And the control still fires

A clean result from a check that has quietly stopped working looks identical
to a clean site, so the same run is the proof that it has not:

```
fixture-focus-visible: 3 of 6 checked focusable elements have no visible
outline/box-shadow when focused
  elements: button #bad-button; a #bad-anchor; input #bad-input
```

Those three are deliberately unstyled fixtures. The check still catches them.

## What was fixed

**The defect, in the product.** Gin copies a form's action buttons into the
sticky header and hides the originals with the clip-rect kind of
visually-hidden, which keeps their subtree focusable. Tab stopped on controls
with 0% of their box visible, measured by `elementFromPoint` sampling rather
than computed style, on two unmodified Drupal pages:

| Page | Control | `tabIndex` | Visible |
|---|---|---|---|
| `/admin/config/system/site-information` | `#edit-submit` | 0 | 0% |
| `/user/<uid>/cancel` | `#edit-submit` | 0 | 0% |
| `/user/<uid>/cancel` | `#edit-cancel` | 0 | 0% |

On a confirm form the only Cancel reachable by Tab was the invisible one.
Fixed in #2394 by taking the hidden originals out of the tab order under
Gin's own selector, and gated by `admin-focus-visible.spec.ts` so it cannot
return (#2389).

**The defect, in the measurement.** With that fixed, the run at `786cef1cb`
still reported `div .ck` on all three pages carrying a rich text editor. That
was not real: the element is CKEditor's toolbar container at `tabindex="-1"`,
which is out of the tab order, and `checkFocusVisible` selected on
`[tabindex]` without excluding negative values. The editable beside it does
indicate: on focus it gains `ck-focused`, a 4px blue box-shadow ring and a
blue border. Fixed in #2419, with the case added to the fixture so it fails
without the fix.

That second one is the more instructive of the two. A green run whose only
findings are spurious supports no conclusion in either direction, so the
criterion stayed where it was until the check could be trusted.

## What this evidence does not cover

- **Containers focused programmatically.** A dialog that takes focus on open
  carries `tabindex="-1"` and is no longer inspected. Whether such a
  container needs a visible ring is a judgement rather than something to
  assert from a selector; it belongs to the manual keyboard pass (#2242).
- **Indicator quality.** The check asks whether focusing changes what is
  drawn, not whether the result is big enough or contrasted enough. Those
  were measured separately for 1.4.11 in September.
- **Non-outline indicators** remain a standing trap: the main navigation
  draws a pseudo-element ring and CKEditor a box-shadow, so any future check
  reading only `outline` will report both as missing. It has happened twice
  (#2187, #2250).
