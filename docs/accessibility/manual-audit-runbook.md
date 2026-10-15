# Manual audit runbook: the keyboard and screen reader passes

The last 11 criteria of [#2242](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/2242).
Everything decidable from code, a browser or a ruler is already recorded; see
[findings/](findings/). What is left genuinely needs a person driving a
keyboard and a screen reader.

| Bucket | Criteria |
|---|---|
| **C — keyboard** (6) | 2.1.1 Keyboard · 2.1.2 No Keyboard Trap · 2.4.3 Focus Order · 3.2.1 On Focus · 3.2.2 On Input · 1.4.13 Content on Hover or Focus |
| **D — screen reader** (5) | 1.3.2 Meaningful Sequence · 2.4.4 Link Purpose · 2.4.6 Headings and Labels · 3.3.1 Error Identification · 4.1.3 Status Messages |

## Generate the cards first

`scripts/audit-prep/` walks the site and writes one Markdown card per page
holding the things you would otherwise have to hunt for: the tab order with
coordinates, the heading outline, links sharing text but not targets, live
regions, hover-revealed content, and every form control with its accessible
name.

It decides nothing. The judgement is the part only a person can do; this
exists so your time goes on that rather than on inventory.

```bash
cd tests/playwright
npm ci

# Anonymous pages only:
AUDIT_BASE_URL=https://your-site.ddev.site \
  npx playwright test -c scripts/audit-prep/playwright.config.ts

# Including authenticated pages, using a one-time login link:
AUDIT_BASE_URL=https://your-site.ddev.site \
AUDIT_SESSION="$(ddev drush uli --no-browser)" \
  npx playwright test -c scripts/audit-prep/playwright.config.ts
```

Cards land in `tests/playwright/test-results/manual-audit/`, which is
gitignored: they are a working surface, not a record. Start at `index.md`.

The tool is deliberately outside `tests/`, which CI runs wholesale. It is an
operator tool and must never run in a pipeline.

## Before you start

- **Run the automated layer first** and read its output. Half of what a
  manual pass used to cover is already machine-checked; the table in
  [manual-checklist.md](manual-checklist.md) draws the line per criterion.
  Do not re-check what a machine already decided.
- **One screen reader is enough per pass.** NVDA + Firefox, VoiceOver +
  Safari, or Orca + Firefox. Rotate across passes rather than doing all
  three at once.
- **Use a real keyboard**, not a script. Programmatic focus does not
  trigger `:focus-visible`, and synthetic events skip the behaviour you are
  there to observe.

## Bucket C: the keyboard pass

Work one card at a time. Tab from the top of the page to the bottom.

### 2.1.1 Keyboard
Reach every control. Then operate it: Enter on links, Enter or Space on
buttons, arrows inside composite widgets (tabs, carousels, the protocol
widget, maps).

**Fails if** anything interactive cannot be reached, or can be reached but
not operated.

The card's tab-stop count is the target. Something interactive that is
*absent* from that list is the thing to look for.

### 2.1.2 No Keyboard Trap
Tab through, then **open things first** and tab again: modals, the media
lightbox, dropdowns, the entity browser, date pickers.

**Fails if** focus enters something it cannot leave by keyboard alone.

The automated smoke test only catches traps present on load. Compound
native controls (audio, video, `<select>`) are flagged "needs manual
confirmation" because their internal focus is invisible from outside.

### 2.4.3 Focus Order
Compare the card's tab order against what you see.

**Fails if** the order would confuse someone who cannot see the layout.

The card flags stops that sit *above* the previous one. Those are candidates,
not verdicts: a skip link legitimately comes first while sitting at the top,
whereas a control that is visually last and focused first is a real problem.

### 3.2.1 On Focus
While tabbing, watch for anything that happens on arrival.

**Fails if** focus alone navigates, submits, opens a dialog or changes
context.

### 3.2.2 On Input
Change a value in every select, checkbox and radio on the card's control
list, without pressing a submit button.

**Fails if** changing a value navigates or submits without warning. AJAX
that updates part of the page is fine; being taken somewhere is not.

Likeliest candidates: the cultural protocol widget and the community browser
select.

### 1.4.13 Content on Hover or Focus
For each entry in the card's hover/focus section, reveal it and check three
things: **dismissible** without moving the pointer (Escape), **hoverable**
so you can move onto it without it vanishing, and **persistent** until you
dismiss it or move away.

**Fails if** any of the three is false.

## Bucket D: the screen reader pass

Walk each page with reading commands, not just Tab.

### 1.3.2 Meaningful Sequence
Read the page top to bottom.

**Fails if** the reading order changes the meaning compared with the visual
order. The card's tab order and coordinates will usually have warned you.

### 2.4.4 Link Purpose (In Context)
Pull up the links list and read each one alone.

**Fails if** a link's purpose cannot be determined from its text plus its
immediate context.

The card lists links sharing text but pointing somewhere different. Those
are the ones that read fine one at a time and fail in a links list.

### 2.4.6 Headings and Labels
Read the card's heading outline aloud.

**Fails if** a heading or label does not describe what it introduces.
Non-skipping levels are already machine-checked; this is about whether the
outline *means* anything.

### 3.3.1 Error Identification
Submit each form wrong: empty required fields, then a malformed value.

**Fails if** an error is shown visually but not announced, or does not say
which field is wrong.

Note that forms carrying a captcha may block submission entirely, which
looks like a missing error but is not one.

### 4.1.3 Status Messages
Trigger each live region in the card: save something, filter a view, add an
item to a list.

**Fails if** a status is shown but not announced, or if announcing it steals
focus.

## Recording what you find

Each card ends with a **Findings** section. One line per failure:

```
- 2.1.2 — opened the media lightbox, Tab cycled between the close button and
  the image forever; expected Tab to leave the dialog.
```

Criterion, what you did, what happened, what you expected. That is enough to
file an issue from, and enough to write an ACR note from.

When a pass is finished, copy the findings into a dated file under
[findings/](findings/) following
[manual-findings-template.md](findings/manual-findings-template.md), and
raise ACR levels in a **separate** change once the evidence is recorded.
Evidence precedes the claim: see the 1.4.10 history for why.

## What a clean pass means

A criterion moves to `supports` only when the pass actually ran and its
evidence is in a findings file. "No findings" from a tool is not the same
as "a person checked and it was fine", and the ACR notes are expected to say
which of the two they rest on.
