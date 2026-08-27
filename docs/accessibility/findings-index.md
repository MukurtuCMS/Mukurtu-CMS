# Findings Index

Status of every sub-issue under [#2008](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/2008), the accessibility program's umbrella issue. Last updated 2026-08-27.

## Fixed (PR open, pending merge)

| Issue | Title | PR |
|---|---|---|
| [#1976](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1976) | Import mapping table: unlabeled Remove buttons, no focus/live-region | [#1981](https://github.com/MukurtuCMS/Mukurtu-CMS/pull/1981) |
| [#1977](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1977) | Generic entity-browser row selection lacks role/aria-checked | [#1984](https://github.com/MukurtuCMS/Mukurtu-CMS/pull/1984) |
| [#1978](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1978) | Collection organization form: unlabeled Remove buttons, no AJAX focus/live-region | [#1983](https://github.com/MukurtuCMS/Mukurtu-CMS/pull/1983) |
| [#1979](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1979) | Content warnings settings: unlabeled Remove buttons, no AJAX focus/live-region | [#1983](https://github.com/MukurtuCMS/Mukurtu-CMS/pull/1983) |
| [#1995](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1995) | PDF thumbnail missing alt text in media carousel | [#2045](https://github.com/MukurtuCMS/Mukurtu-CMS/pull/2045) |
| [#1997](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1997) | Horizontal scroll required at 320px width (reflow) | [#2046](https://github.com/MukurtuCMS/Mukurtu-CMS/pull/2046) |
| [#1998](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1998) | Dictionary browse overflows at 200% text zoom | [#2050](https://github.com/MukurtuCMS/Mukurtu-CMS/pull/2050) |
| [#1999](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1999) | Missing focus indicator on 4 elements, dictionary word page | [#2045](https://github.com/MukurtuCMS/Mukurtu-CMS/pull/2045) |
| [#1996](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1996) | Splide carousel slide has ARIA role conflict | [#2061](https://github.com/MukurtuCMS/Mukurtu-CMS/pull/2061) |
| [#2000](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/2000) | ALTCHA widget: focusable hidden link + contrast failure | [#2062](https://github.com/MukurtuCMS/Mukurtu-CMS/pull/2062) (local fix *and* filed upstream: [altcha-org/altcha#197](https://github.com/altcha-org/altcha/issues/197)) |
| [#2002](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/2002) | Lightbox zoom-hint text not contained in a landmark | [#2045](https://github.com/MukurtuCMS/Mukurtu-CMS/pull/2045) |

Note: #1998's PR (#2050) also carries the fix for #1996-adjacent glossary overflow found along the way; #1998's *originally suspected* cause (the alphabet glossary bar) turned out not to be the actual cause of the reported number — the real cause (the exposed filter form's CSS Grid) was found by bisecting the page, not by trusting the issue's own hypothesis. See the issue/PR for the full trail.

## Closed — no code fix needed

| Issue | Title | Outcome |
|---|---|---|
| [#2001](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/2001) | Decorative card link focusable despite aria-hidden | Investigated in [#2045](https://github.com/MukurtuCMS/Mukurtu-CMS/pull/2045); not reproducible — the correct `tabindex="-1"` pairing was already in place everywhere checked. Regression test added instead of a fix. |
| [#2051](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/2051) | Main navigation doesn't collapse under text-only zoom | Closed: disproved during #1998 investigation. Hiding the entire `<header>` (nav included) made zero measurable difference to the actual page overflow — the nav wasn't the cause. |
| [#1913](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1913) | Map Points (Leaflet draw) widget isn't keyboard/screen-reader accessible | Closed prior to this pass of the program. |

## Tracked upstream — no local fix needed or possible

| Issue | Title | Upstream links |
|---|---|---|
| [#2003](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/2003) | Admin toolbar: invalid ARIA attribute + unnamed buttons/links | All three findings are 100% Gin/Drupal core contrib code, already tracked: Gin [#3598613](https://git.drupalcode.org/project/gin/-/work_items/3598613) (aria-toolbar-link__labelledby typo, fix ready/RTBC) + core [#3615331](https://www.drupal.org/project/drupal/issues/3615331) (currently postponed pending confirmation the templates are actually used — which we've now confirmed via live testing); Gin [#3539420](https://git.drupalcode.org/project/gin/-/work_items/3539420) (collapsed-toolbar labels use `display:none` instead of a proper visually-hidden technique, stripping button/link names). Left open as a placeholder until these land in a Gin/core update. |

## Parent/tracking issues (not individual findings)

| Issue | Title |
|---|---|
| [#1786](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1786) | Site-wide accessibility check — the original umbrella issue that spawned this program's baseline scan |
| [#1975](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1975) | Accessibility Phase 2: admin/authoring inventory (complementary to #1817) — parent of #1976-#1979, all now fixed above |
