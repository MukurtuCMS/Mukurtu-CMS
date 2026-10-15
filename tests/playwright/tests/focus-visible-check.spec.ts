import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';
import { checkFocusVisible } from '~helpers/automated-checks';

/**
 * Tests the focus-visibility check itself, against fixtures.
 *
 * checkFocusVisible() is report-only, so nothing fails when it is wrong.
 * It has been wrong in both directions: it missed indicators drawn as a
 * ::before pseudo-element (the false positive in issue #2187) and ones
 * drawn on a wrapper rather than the focusable node (Tagify, issue #2250),
 * and while fixing those it briefly stopped catching a genuinely
 * unstyled anchor, because a UA stylesheet moves outline-offset on focus
 * while outline-style stays "none".
 *
 * A check that reports nothing looks exactly like a clean site, so these
 * fixtures pin both directions: the patterns it must accept, and the
 * failures it must still catch.
 *
 * The newest of those: an element with tabindex="-1" is out of the tab
 * order, so it cannot fail 2.4.7 by drawing nothing on focus. The check
 * used to inspect them, and CKEditor's toolbar container made all three
 * rich text pages report a finding that was not real.
 */

/** Reads the findings the check wrote for a slug. */
function findingsFor(slug: string): { summary: { findings: number }, findings: { detail?: string }[] } {
  const file = path.join(__dirname, '..', 'test-results', 'a11y-extra', `${slug}-focus-visible.json`);
  return JSON.parse(fs.readFileSync(file, 'utf8'));
}

test('catches elements with no focus indicator, and only those', async ({ page }, testInfo) => {
  await page.setContent(`
    <style>
      /* Genuinely nothing on focus. */
      .bad, .bad:focus { outline: none !important; box-shadow: none !important; border: 1px solid #ccc; }
      /* A ring on the element itself. */
      .good:focus { outline: 2px solid #000; }
      /* A ring on an ancestor: the Tagify pattern, where the focusable
         node is a contenteditable inside <tags.tagify>. */
      .wrap:focus-within { outline: 2px solid #000; }
      /* A ring drawn as a pseudo-element: the #2187 pattern. */
      .pseudo:focus::before { content: ''; position: absolute; inset: -4px; border: 2px solid #000; }
    </style>
    <button class="bad" id="bad-button">no indicator</button>
    <a href="#" class="bad" id="bad-anchor">no indicator</a>
    <input class="bad" id="bad-input">
    <button class="good" id="good-own">ring on itself</button>
    <div class="wrap"><button id="good-ancestor">ring on wrapper</button></div>
    <button class="pseudo" id="good-pseudo">ring as pseudo-element</button>
    <!-- Out of the tab order, so not a 2.4.7 failure however it looks.
         This is CKEditor's toolbar pattern, which reported on every page
         with a rich text editor before the check skipped these. -->
    <div class="bad" tabindex="-1" id="not-tabbable">programmatic focus only</div>
  `);

  await checkFocusVisible(page, testInfo, 'fixture-focus-visible');
  const report = findingsFor('fixture-focus-visible');

  expect(report.summary.findings, 'the three unstyled elements are reported').toBe(1);
  const detail = report.findings[0].detail ?? '';

  for (const id of ['#bad-button', '#bad-anchor', '#bad-input']) {
    expect(detail, `${id} has no indicator and must be caught`).toContain(id);
  }
  for (const id of ['#good-own', '#good-ancestor', '#good-pseudo']) {
    expect(detail, `${id} has a real indicator and must not be reported`).not.toContain(id);
  }
  expect(detail, 'tabindex="-1" is not in the tab order and must not be reported')
    .not.toContain('#not-tabbable');
});

test('reports nothing when every element has an indicator', async ({ page }, testInfo) => {
  await page.setContent(`
    <style>button:focus { outline: 2px solid #000; }</style>
    <button id="a">a</button><button id="b">b</button>
  `);

  await checkFocusVisible(page, testInfo, 'fixture-focus-visible-clean');
  expect(findingsFor('fixture-focus-visible-clean').summary.findings).toBe(0);
});
