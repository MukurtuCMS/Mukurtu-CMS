import { Page, TestInfo } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Automated checks that go beyond what axe-core can assert on its own —
 * axe cannot resize a viewport, judge whether a focus indicator is visible,
 * judge whether link text is meaningful, or notice a keyboard trap. These
 * are heuristic, best-effort smoke tests: they catch the most common
 * failure patterns for each criterion, but (unlike axe) can produce false
 * positives/negatives, and do not replace the manual checklist — see
 * docs/accessibility/manual-checklist.md for what still needs a human.
 *
 * Report-only, same as axe.ts: findings are written to
 * test-results/a11y-extra/<slug>.json and never fail the test.
 */

const RESULTS_DIR = path.join(__dirname, '../../test-results/a11y-extra');

interface CheckFinding {
  check: string;
  criterion: string;
  summary: string;
  detail: string;
}

function writeReport(slug: string, testInfo: TestInfo, findings: CheckFinding[]): void {
  const report = {
    slug,
    timestamp: new Date().toISOString(),
    summary: { findings: findings.length },
    findings,
  };
  fs.mkdirSync(RESULTS_DIR, { recursive: true });
  fs.writeFileSync(path.join(RESULTS_DIR, `${slug}.json`), JSON.stringify(report, null, 2));
  testInfo.annotations.push({
    type: 'a11y-extra',
    description: `${findings.length} automated-check finding(s) on ${slug}${
      findings.length ? ': ' + findings.map((f) => f.check).join(', ') : ''
    }`,
  });
}

/**
 * WCAG 1.4.10 Reflow: content must not require horizontal scrolling at a
 * 320 CSS px effective width (equivalent to 400% zoom on a 1280px design).
 * A real automatable check — no human judgment needed to detect a
 * horizontal scrollbar.
 */
export async function checkReflow(page: Page, testInfo: TestInfo, slug: string): Promise<void> {
  const findings: CheckFinding[] = [];
  const originalViewport = page.viewportSize();
  await page.setViewportSize({ width: 320, height: 720 });
  // Let responsive JS (menus, carousels) settle after the resize.
  await page.waitForTimeout(300);

  const overflow = await page.evaluate(() => ({
    scrollWidth: document.documentElement.scrollWidth,
    clientWidth: document.documentElement.clientWidth,
  }));

  if (overflow.scrollWidth > overflow.clientWidth + 1) {
    findings.push({
      check: 'reflow-320px',
      criterion: '1.4.10 Reflow',
      summary: `Horizontal scroll required at 320px width (content ${overflow.scrollWidth}px vs viewport ${overflow.clientWidth}px)`,
      detail: 'Compare against the 1280px baseline to find which element is too wide (fixed width, non-wrapping table, etc).',
    });
  }

  if (originalViewport) {
    await page.setViewportSize(originalViewport);
  }
  writeReport(`${slug}-reflow`, testInfo, findings);
}

/**
 * WCAG 1.4.4 Resize Text: content must not clip or overlap when text is
 * scaled 200%. Approximated by doubling the root font size (browser
 * text-zoom, unlike page zoom, doesn't scale layout containers) and
 * checking for horizontal overflow — a reasonable proxy, not a full
 * replacement for checking in a real browser's zoom feature.
 */
export async function checkTextZoom(page: Page, testInfo: TestInfo, slug: string): Promise<void> {
  const findings: CheckFinding[] = [];
  await page.addStyleTag({ content: 'html { font-size: 200% !important; }' });
  await page.waitForTimeout(300);

  const overflow = await page.evaluate(() => ({
    scrollWidth: document.documentElement.scrollWidth,
    clientWidth: document.documentElement.clientWidth,
  }));

  if (overflow.scrollWidth > overflow.clientWidth + 1) {
    findings.push({
      check: 'text-zoom-200',
      criterion: '1.4.4 Resize Text',
      summary: `Horizontal scroll required at 200% text size (content ${overflow.scrollWidth}px vs viewport ${overflow.clientWidth}px)`,
      detail: 'Approximated via root font-size doubling, not a real browser zoom — confirm with actual zoom during the manual pass before filing.',
    });
  }
  writeReport(`${slug}-text-zoom`, testInfo, findings);
}

/**
 * WCAG 2.4.7 Focus Visible: every focusable element should show some visible
 * indicator when focused. Axe has no rule for this at all (it cannot judge
 * "visible"), so this is a smoke test for the most common failure pattern —
 * `outline: none`/`box-shadow: none` with no visible replacement. It cannot
 * judge whether an indicator that IS present has sufficient contrast or
 * thickness — that stays a manual check.
 */
export async function checkFocusVisible(page: Page, testInfo: TestInfo, slug: string): Promise<void> {
  const findings: CheckFinding[] = [];
  const MAX_ELEMENTS = 60;

  const results = await page.evaluate((max) => {
    const selector = 'a[href], button, input, select, textarea, [tabindex]';
    const elements = Array.from(document.querySelectorAll<HTMLElement>(selector))
      .filter((el) => {
        const rect = el.getBoundingClientRect();
        return rect.width > 0 && rect.height > 0 && !el.hasAttribute('disabled');
      })
      .slice(0, max);

    return elements.map((el) => {
      el.focus();
      const style = getComputedStyle(el);
      const hasOutline = style.outlineStyle !== 'none' && parseFloat(style.outlineWidth) > 0;
      const hasBoxShadow = style.boxShadow !== 'none' && style.boxShadow !== '';
      const focused = document.activeElement === el;
      el.blur();
      return {
        focused,
        visible: hasOutline || hasBoxShadow,
        tag: el.tagName.toLowerCase(),
        identifier: el.id ? `#${el.id}` : el.className ? `.${String(el.className).split(' ')[0]}` : el.outerHTML.slice(0, 80),
      };
    });
  }, MAX_ELEMENTS);

  const invisible = results.filter((r) => r.focused && !r.visible);
  if (invisible.length) {
    findings.push({
      check: 'focus-visible',
      criterion: '2.4.7 Focus Visible',
      summary: `${invisible.length} of ${results.length} checked focusable elements have no visible outline/box-shadow when focused`,
      detail: invisible.slice(0, 15).map((r) => `${r.tag} ${r.identifier}`).join('; '),
    });
  }
  writeReport(`${slug}-focus-visible`, testInfo, findings);
}

/**
 * WCAG 2.4.4 Link Purpose (In Context): axe's `link-name` rule only checks
 * that a link has *some* accessible name, not whether it's meaningful out of
 * context. This flags common vague phrasing with no extra accessible
 * context (aria-label/aria-labelledby/title) alongside it.
 */
const VAGUE_LINK_TEXT = [
  'click here', 'here', 'read more', 'learn more', 'more', 'more info',
  'more information', 'link', 'this link', 'continue reading', 'details', 'more details',
];

export async function checkLinkText(page: Page, testInfo: TestInfo, slug: string): Promise<void> {
  const vague = await page.evaluate((phrases) => {
    const links = Array.from(document.querySelectorAll<HTMLAnchorElement>('main a[href]'));
    return links
      .map((link) => ({
        text: (link.textContent || '').trim().toLowerCase(),
        hasContext: !!(link.getAttribute('aria-label') || link.getAttribute('aria-labelledby') || link.getAttribute('title')),
        href: link.getAttribute('href'),
      }))
      .filter((l) => !l.hasContext && phrases.includes(l.text));
  }, VAGUE_LINK_TEXT);

  const findings: CheckFinding[] = [];
  if (vague.length) {
    findings.push({
      check: 'vague-link-text',
      criterion: '2.4.4 Link Purpose (In Context)',
      summary: `${vague.length} link(s) with vague text and no extra accessible context`,
      detail: vague.slice(0, 15).map((l) => `"${l.text}" -> ${l.href}`).join('; '),
    });
  }
  writeReport(`${slug}-link-text`, testInfo, findings);
}

/**
 * WCAG 2.1.2 No Keyboard Trap: tabs through the page looking for a short,
 * exactly-repeating cycle of focus stops — the signature of a trap. This is
 * a heuristic smoke test, not a guarantee: it can miss traps that only
 * activate after an interaction (e.g. opening a modal first), and it is not
 * a substitute for manually tabbing through each high-risk component (see
 * the manual checklist's per-component keyboard checks).
 */
export async function checkKeyboardTrap(page: Page, testInfo: TestInfo, slug: string): Promise<void> {
  const findings: CheckFinding[] = [];
  const focusableCount = await page.evaluate(
    () => document.querySelectorAll('a[href], button, input, select, textarea, [tabindex]').length,
  );
  const budget = Math.min(120, Math.max(40, focusableCount * 3));

  const signatures: string[] = [];
  for (let i = 0; i < budget; i++) {
    await page.keyboard.press('Tab');
    const sig = await page.evaluate(() => {
      const el = document.activeElement;
      if (!el || el === document.body) return '(body)';
      return el.tagName + '#' + el.id + '.' + Array.from(el.classList).join('.');
    });
    signatures.push(sig);
  }

  // A repeating cycle alone isn't proof of a trap: tabbing past the last
  // element on a page naturally wraps back to the first one, which looks
  // identical to a "cycle" from this vantage point. Only a cycle confined to
  // a SUBSET of the page's real tab stops (not the whole page's worth) is a
  // meaningful trap signature — compare against the total distinct stops
  // seen across the full run to tell the two apart.
  const totalDistinct = new Set(signatures).size;

  // Look for a short cycle (length 2-6) that repeats at least 3 times in a
  // row somewhere in the tab sequence.
  for (let cycleLen = 2; cycleLen <= 6; cycleLen++) {
    for (let start = 0; start + cycleLen * 3 <= signatures.length; start++) {
      const cycle = signatures.slice(start, start + cycleLen);
      const next1 = signatures.slice(start + cycleLen, start + cycleLen * 2);
      const next2 = signatures.slice(start + cycleLen * 2, start + cycleLen * 3);
      if (JSON.stringify(cycle) !== JSON.stringify(next1) || JSON.stringify(cycle) !== JSON.stringify(next2)) {
        continue;
      }

      const cycleIsSingleElement = new Set(cycle).size === 1;
      const cycleCoversWholePage = totalDistinct <= cycleLen + 1;

      if (cycleCoversWholePage && !cycleIsSingleElement) {
        // The "cycle" is just the page's entire tab sequence repeating —
        // ordinary end-of-page wraparound, not a trap. Not reported.
        writeReport(`${slug}-keyboard-trap`, testInfo, findings);
        return;
      }

      if (cycleIsSingleElement) {
        // Tab pressed repeatedly with document.activeElement never
        // changing. This is what a native compound control (audio/video
        // player, <select>) looks like from outside its shadow DOM — the
        // browser may be correctly moving focus among the control's
        // internal parts without that being visible here. Flagged as a
        // known automation blind spot, not a confirmed trap: a human must
        // tab through this element and confirm focus actually exits it.
        findings.push({
          check: 'keyboard-focus-not-advancing',
          criterion: '2.1.2 No Keyboard Trap (needs manual confirmation)',
          summary: `Tab did not change document.activeElement for ${cycleLen} consecutive presses starting at tab stop ${start + 1} — likely a compound native control (audio/video/select) whose internal focus isn't visible to this check, but confirm manually that Tab eventually exits it`,
          detail: cycle[0],
        });
      } else {
        findings.push({
          check: 'keyboard-trap-suspected',
          criterion: '2.1.2 No Keyboard Trap',
          summary: `Focus appears to cycle repeatedly between ${cycleLen} element(s) starting at tab stop ${start + 1}, without covering the rest of the page's tab stops`,
          detail: cycle.join(' -> '),
        });
      }
      writeReport(`${slug}-keyboard-trap`, testInfo, findings);
      return;
    }
  }
  writeReport(`${slug}-keyboard-trap`, testInfo, findings);
}
