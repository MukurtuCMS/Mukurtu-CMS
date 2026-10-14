# Automated evidence for the manual audit (2026-10-06)

Not a manual pass. This records what the automated layer already answers, so
the manual pass (#2242) starts from the rows a human actually has to judge
rather than re-testing what CI measured.

Source: the `accessibility-results-main-37515975909` artifact from the
successful `main` push run of 2026-10-06 19:02 UTC, which is the evidence CI
preserves under `test-results/a11y-extra/`. 46 pages x 5 checks.

The checklist's "What's automated now" table is the authority for which
column a result can fill. Reflow is fully machine-decidable and fills its
column outright; the keyboard column is only partly covered, so it is marked
`auto` to show the automated half passed and a human still owes the rest.

## Real failures found

Three, all fully machine-decidable, so none needs a human to confirm.

| Page | Criterion | Measured |
|---|---|---|
| `/communities` | **1.4.10 Reflow** | horizontal scroll at 320px, content 349px |
| `/browse` | **1.4.4 Text resize** | horizontal scroll at 200% text, content 1341px vs 1280px |
| Community page (member) | **1.4.4 Text resize** | horizontal scroll at 200% text, content 1507px vs 1280px |

`/communities` matters most: **1.4.10 was claimed `supports` in the ACR while
this page failed it.** Corrected to `partially-supports` in the same change
as this document. The claim had generalised from a five-page sweep
(#2197 / PR #2224) that did not include `/communities`, and the phrase "across
the page inventory" described the report-only check rather than the gating
one, which deliberately covers only `/`, `/browse` and `/user/login`.

1.4.4 was already `not-evaluated`, so nothing was overclaimed there, but it
cannot move to `supports` while these stand. Beyond Phase 1, both
manage-adjacent Local Contexts pages also overflow at 200% (1557px), as do
several Phase 2 admin pages.

## Needing a human, not a failure

Nine pages report the keyboard-trap check's known inconclusive case:

> Tab did not change document.activeElement for 2 consecutive presses ...
> likely a compound native control (audio/video/select) whose internal focus
> isn't visible to this check, but confirm manually that Tab eventually
> exits it

These are **not** recorded as failures. The check cannot see inside a native
control's shadow DOM, which the checklist documents. They are marked
`⬜ confirm` so the keyboard pass knows exactly where to look: home
(anonymous and member), browse, personal collections, account, and the
member views of digital heritage item, collection, community and dictionary
word.

## Per-page passes

Fill the Screen reader column by hand. Keyboard shows the automated half
only. Zoom/reflow is complete and should not be re-tested manually.

| Home `/` (anonymous) | ⬜ confirm | ⬜ | ✅ |
| Browse `/browse` (incl. Map view) | ⬜ confirm | ⬜ | ❌ |
| Digital Heritage browse `/digital-heritage` | ✅ auto | ⬜ | ✅ |
| Collections browse `/collections` | ✅ auto | ⬜ | ✅ |
| Communities `/communities` | ✅ auto | ⬜ | ❌ |
| Dictionary browse `/dictionary` | ✅ auto | ⬜ | ✅ |
| Login `/user/login` (incl. error state) | ✅ auto | ⬜ | ✅ |
| Home `/` (member) | ⬜ confirm | ⬜ | ✅ |
| My content `/my-content` (member) | ✅ auto | ⬜ | ✅ |
| Personal collections `/user/personal-collections` (member) | ⬜ confirm | ⬜ | ✅ |
| Account `/user` (member) | ⬜ confirm | ⬜ | ✅ |
| Digital heritage item (member) | ⬜ confirm | ⬜ | ✅ |
| Collection page (member) | ⬜ confirm | ⬜ | ✅ |
| Community page (member) | ⬜ confirm | ⬜ | ❌ |
| Dictionary word (member) | ⬜ confirm | ⬜ | ✅ |
Legend: ✅ auto = the automated part of this column passed, a human still
owes the rest · ⬜ confirm = automated result inconclusive, named above ·
❌ = a real failure · ⬜ = not covered by automation at all.

## What this does not cover

Every criterion the checklist marks Human-only: alt text *quality*, reading
order, screen reader announcements, component ARIA *behaviour*, language of
parts, and the media-alternative capability checks. Also 2.4.7 indicator
contrast and thickness, which the focus check cannot judge even though it
now detects indicators drawn on a wrapper or as a pseudo-element (PR #2275).

Re-generate this document from a later artifact when the automated layer
changes; it is a snapshot, not a standing claim.
