# Issue #2242: triage of the 41 unevaluated web criteria

Step 2 of the #2242 audit plan. The ACR currently reports **8 supports, 1
partially-supports and 41 not-evaluated** for the `web` component. This
document sorts those 41 by *what it physically takes to decide them*, so a
session can be planned around one tool at a time instead of walking the page
inventory once per criterion.

Read this with [2026-10-06-automated-evidence-prefill.md](2026-10-06-automated-evidence-prefill.md),
which carries the automated results that several of these start from, and
with [../manual-checklist.md](../manual-checklist.md), which is the procedure.

**This is a plan, not a result.** Nothing here changes an ACR level. A
criterion moves out of `not-evaluated` only when a pass has actually run and
its evidence is recorded in a dated findings file.

## Totals

| Bucket | Count | What it needs |
|---|---|---|
| A. Desk review | 18 | Codebase, config and content. No running site. |
| B. Visual | 12 | A browser, a ruler and real zoom. No assistive tech. |
| C. Keyboard | 6 | A keyboard and a focus indicator. |
| D. Screen reader | 5 | NVDA/VoiceOver/Orca. |

The shape matters: **18 of 41 need no running site at all**, and another 12
need only a browser. The screen reader pass, which is the expensive one to
schedule and the hardest to do well, is only 5 criteria. Do A first; it is
the cheapest and it shrinks what the later passes have to carry.

## A. Desk review (18)

Decidable from the codebase, config or editorial content.

| SC | Name | How to decide | Evidence already in hand |
|---|---|---|---|
| 1.2.1 | Audio-only / Video-only (Prerecorded) | Capability test: can an author attach a transcript or alternative to a media entity? | — |
| 1.2.2 | Captions (Prerecorded) | Capability test: does the video media type accept a caption track, and does the player expose it? | — |
| 1.2.3 | Audio Description or Media Alternative | Capability test, as 1.2.1 | — |
| 1.2.4 | Captions (Live) | Almost certainly not applicable: no live-streaming feature. Confirm, then mark `not-applicable`, not `supports` | — |
| 1.2.5 | Audio Description (Prerecorded) | Capability test, as 1.2.3 | — |
| 1.3.3 | Sensory Characteristics | Read the UI strings for instructions that depend on shape, size or position ("the button on the right") | — |
| 1.3.4 | Orientation | No orientation lock found. `_include-media.scss` defines `portrait`/`landscape` breakpoint aliases; confirm nothing *restricts* to one | Only the two alias definitions, no usage that locks |
| 1.3.5 | Identify Input Purpose | `autocomplete` tokens on fields collecting the *user's own* info (login, register, account). The `autocomplete` hits in the codebase are Drupal's entity-reference widget, which is a different thing | Needs a DOM scan; **automatable** |
| 1.4.2 | Audio Control | Nothing autoplays: the dictionary audio display sets `autoplay: false`, GLightbox sets `autoplayVideos: false`. The YouTube oEmbed iframe lists `autoplay` in `allow=` (permission, not instruction) and the URL sets no `autoplay=1` | Strong; write it up and close |
| 2.1.4 | Character Key Shortcuts | 15 files bind `keydown`/`keypress`. Confirm none bind a bare printable character without a modifier or a focus requirement | File list gathered; **automatable as a lint** |
| 2.2.1 | Timing Adjustable | Session lifetime and any auto-refresh/auto-save timers | — |
| 2.3.1 | Three Flashes or Below | No flashing content; confirm no animation exceeds the threshold | — |
| 2.4.5 | Multiple Ways | Site search, main navigation and browse all exist. Confirm two independent ways reach every page type | — |
| 2.5.3 | Label in Name | Accessible name must contain the visible label | **Automatable** — highest-value addition to the automated layer |
| 2.5.4 | Motion Actuation | No `devicemotion`, `deviceorientation` or `DeviceMotionEvent` anywhere | Conclusive; likely `not-applicable` |
| 3.1.2 | Language of Parts | Indigenous-language content carries `lang`. Editorial, not markup | — |
| 3.3.4 | Error Prevention (Legal, Financial, Data) | Reversible or confirmed submissions. Relevant to deletion and to the submission workflow | — |
| 4.1.1 | Parsing | Removed in WCAG 2.2 and always satisfied in HTML5. Record the reasoning rather than testing | — |

## B. Visual (12)

A browser, a ruler, real browser zoom. No assistive technology.

| SC | Name | How to decide | Starting point |
|---|---|---|---|
| 1.4.1 | Use of Color | Grayscale the page; confirm nothing is conveyed by hue alone (status badges, required-field marks, chart keys) | — |
| 1.4.4 | Resize Text | Real browser zoom to 200% | `checkTextZoom` flags `/browse` and a member community page. Confirm both in a real browser before filing |
| 1.4.5 | Images of Text | Look for text baked into images (logos exempt) | — |
| 1.4.12 | Text Spacing | Apply the text-spacing override and look for clipping | **Automatable** |
| 2.2.2 | Pause, Stop, Hide | Any carousel or auto-updating region has a control | — |
| 2.4.7 | Focus Visible | Indicator *contrast and thickness*, which the automated check cannot judge | `checkFocusVisible` already proves an indicator is drawn |
| 2.5.1 | Pointer Gestures | Path-based or multipoint gestures need a single-pointer alternative. **The maps are the risk here** | Leaflet pinch-zoom; needs the zoom buttons to be a real alternative |
| 2.5.2 | Pointer Cancellation | Nothing activates on the down-event | — |
| 3.2.3 | Consistent Navigation | Navigation in the same relative order across pages | — |
| 3.2.4 | Consistent Identification | Same function, same label, across pages | — |
| 3.3.2 | Labels or Instructions | Every input has a visible label or instruction | axe covers the programmatic half |
| 3.3.3 | Error Suggestion | Error messages suggest a correction where one is known | Pairs with 3.3.1 in the screen reader pass |

## C. Keyboard (6)

| SC | Name | How to decide | Starting point |
|---|---|---|---|
| 2.1.1 | Keyboard | Everything reachable and operable, including arrow keys inside composite widgets | The 7 high-risk components in [../page-inventory.md](../page-inventory.md) |
| 2.1.2 | No Keyboard Trap | Tab escapes everything, **including after opening a modal** | 9 pages inconclusive from the automated smoke test (compound native controls) |
| 2.4.3 | Focus Order | Focus follows reading order | — |
| 3.2.1 | On Focus | Focus alone never navigates or submits | — |
| 3.2.2 | On Input | Changing a value never navigates or submits without warning | Cultural protocol widget and the community browser select are the ones to check first |
| 1.4.13 | Content on Hover or Focus | Tooltips and popovers are dismissible, hoverable and persistent | — |

## D. Screen reader (5)

| SC | Name | How to decide |
|---|---|---|
| 1.3.2 | Meaningful Sequence | Reading order matches visual order |
| 2.4.4 | Link Purpose (In Context) | Links make sense read alone. The automated check only catches the generic-phrase list |
| 2.4.6 | Headings and Labels | Headings and labels are *descriptive*, not merely present and non-skipping |
| 3.3.1 | Error Identification | Errors are announced, not only shown |
| 4.1.3 | Status Messages | Live regions announce without moving focus |

## The cheaper path through this

Four of these are machine-decidable and would give **standing evidence that
re-runs on every build**, rather than a one-off human result that goes stale
the next time the markup changes:

- **2.5.3 Label in Name** — compare each control's accessible name against its
  visible label. Pure DOM, no judgment.
- **1.3.5 Identify Input Purpose** — check the `autocomplete` token on fields
  collecting the user's own information.
- **1.4.12 Text Spacing** — apply the override, then re-use the existing
  overflow detection from `checkReflow`.
- **2.1.4 Character Key Shortcuts** — lint the 15 files that bind key events
  for a bare printable character with no modifier.

Each is a smaller job than auditing the page inventory by hand for the same
criterion, and none of them needs a human to repeat it later. Worth doing
before the manual passes, not after.

The honest caveat that applies to all four, and to the existing automated
layer: an automated check earns `supports` only for what it actually covers.
The 1.4.10 correction in PR #2325 is exactly the failure mode to avoid —
a report-only check across the inventory was cited as if it gated.
