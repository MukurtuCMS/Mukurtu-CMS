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

    // How many levels up to look for the indicator. Widgets routinely draw
    // the ring on a wrapper rather than on the focusable node itself:
    // Tagify puts the focusable contenteditable inside <tags.tagify>, and
    // the ring lands on the wrapper via a .tagify--focus class. Three is
    // enough for the patterns in this theme without reaching so far up
    // that an unrelated ancestor's styling masks a real failure.
    const ANCESTOR_DEPTH = 3;

    // A signature of what would actually be *drawn* as a focus indicator,
    // for the element, its pseudo-elements and its nearest ancestors.
    // Compared before and after focus: what matters is not whether a ring
    // exists in the abstract but whether focusing changed the appearance,
    // which also avoids crediting a permanent border as a focus indicator.
    //
    // Normalised rather than raw computed values, because several
    // properties move on focus without rendering anything. A UA stylesheet
    // shifts outline-offset from 0px to 1px on a focused anchor while
    // outline-style stays "none": comparing raw properties reads that as
    // an indicator and silently clears a genuine failure.
    const drawn = (node: HTMLElement, pseudo: string | null): string => {
      const s = getComputedStyle(node, pseudo);

      const outline = s.outlineStyle !== 'none' && parseFloat(s.outlineWidth) > 0
        ? `outline:${s.outlineStyle},${s.outlineWidth},${s.outlineColor},${s.outlineOffset}`
        : '';
      const shadow = s.boxShadow && s.boxShadow !== 'none' ? `shadow:${s.boxShadow}` : '';
      const border = parseFloat(s.borderTopWidth) > 0 || parseFloat(s.borderBottomWidth) > 0
        || parseFloat(s.borderLeftWidth) > 0 || parseFloat(s.borderRightWidth) > 0
        ? `border:${s.borderWidth},${s.borderColor},${s.borderStyle}`
        : '';
      // A pseudo-element only renders when it has content at all; without
      // that its geometry is irrelevant. This is the shape of indicator
      // that produced the false positive in issue #2187.
      const box = pseudo && s.content !== 'none'
        ? `box:${s.content},${s.width},${s.height},${s.backgroundColor}`
        : '';

      return [outline, shadow, border, box].filter(Boolean).join('|');
    };

    const signature = (el: HTMLElement): string => {
      const parts: string[] = [];
      let node: HTMLElement | null = el;
      for (let i = 0; i <= ANCESTOR_DEPTH && node; i++) {
        for (const pseudo of [null, '::before', '::after']) {
          parts.push(drawn(node, pseudo));
        }
        node = node.parentElement;
      }
      return parts.join(';');
    };

    return elements.map((el) => {
      const before = signature(el);
      el.focus();
      const after = signature(el);
      const focused = document.activeElement === el;

      // Keep the old own-element reading too, so a rule that is present
      // whether or not the element is focused still counts. Some
      // components style :focus-within on a wrapper that was already
      // styled, producing no delta but a genuine ring.
      const own = getComputedStyle(el);
      const hasOutline = own.outlineStyle !== 'none' && parseFloat(own.outlineWidth) > 0;
      const hasBoxShadow = own.boxShadow !== 'none' && own.boxShadow !== '';

      el.blur();
      return {
        focused,
        visible: before !== after || hasOutline || hasBoxShadow,
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

/**
 * WCAG 2.5.3 Label in Name: a control's accessible name must contain its
 * visible label, so that someone speaking the label they can see actually
 * activates the control.
 *
 * Fully machine-decidable in principle, heuristic in practice: this
 * approximates the accessible name computation rather than implementing all
 * of accname. It only reports controls that have a visible text label AND an
 * accessible name that does not contain it, which is the failure that breaks
 * speech input. Controls named only by an icon are a different criterion
 * (4.1.2) and are left to axe.
 */
export async function checkLabelInName(page: Page, testInfo: TestInfo, slug: string): Promise<void> {
  const findings: CheckFinding[] = [];
  const MAX_ELEMENTS = 120;

  const mismatches = await page.evaluate((max) => {
    const normalise = (s: string) =>
      s.toLowerCase()
        // Punctuation and symbols differ harmlessly between the two (an
        // ellipsis, a trailing colon), so compare on words alone.
        .replace(/[^\p{L}\p{N}\s]/gu, ' ')
        .replace(/\s+/g, ' ')
        .trim();

    const visible = (el: Element) => {
      const s = getComputedStyle(el);
      if (s.display === 'none' || s.visibility === 'hidden' || s.opacity === '0') return false;
      const r = el.getBoundingClientRect();
      return r.width > 0 && r.height > 0;
    };

    // The visible label is the text a sighted user reads on the control.
    // Text hidden for screen-reader-only purposes is deliberately excluded:
    // it is not what someone would speak.
    const visibleText = (el: Element): string => {
      let out = '';
      const walk = (n: Node) => {
        if (n.nodeType === Node.TEXT_NODE) { out += ' ' + (n.textContent || ''); return; }
        if (n.nodeType !== Node.ELEMENT_NODE) return;
        const e = n as Element;
        if (!visible(e)) return;
        if (e.getAttribute('aria-hidden') === 'true') return;
        n.childNodes.forEach(walk);
      };
      el.childNodes.forEach(walk);
      return out;
    };

    const accName = (el: Element): { name: string; from: string } => {
      const labelledby = el.getAttribute('aria-labelledby');
      if (labelledby) {
        const txt = labelledby.split(/\s+/)
          .map((id) => document.getElementById(id)?.textContent || '')
          .join(' ');
        if (txt.trim()) return { name: txt, from: 'aria-labelledby' };
      }
      const label = el.getAttribute('aria-label');
      if (label && label.trim()) return { name: label, from: 'aria-label' };
      if (el.id) {
        const l = document.querySelector(`label[for="${CSS.escape(el.id)}"]`);
        if (l && l.textContent?.trim()) return { name: l.textContent, from: '<label for>' };
      }
      const wrapping = el.closest('label');
      if (wrapping && wrapping.textContent?.trim()) return { name: wrapping.textContent, from: 'wrapping <label>' };
      const tag = el.tagName.toLowerCase();
      if (tag === 'input') {
        const type = (el as HTMLInputElement).type;
        if (type === 'submit' || type === 'button' || type === 'reset') {
          const v = (el as HTMLInputElement).value;
          if (v) return { name: v, from: 'value' };
        }
      }
      const title = el.getAttribute('title');
      if (title && title.trim()) return { name: title, from: 'title' };
      return { name: el.textContent || '', from: 'text content' };
    };

    const SELECTOR = [
      'button', 'a[href]', 'input:not([type="hidden"])', 'select', 'textarea', 'summary',
      '[role="button"]', '[role="link"]', '[role="checkbox"]', '[role="radio"]',
      '[role="tab"]', '[role="menuitem"]', '[role="switch"]',
    ].join(',');

    const out: { selector: string; visibleLabel: string; accessibleName: string; from: string }[] = [];
    const seen = new Set<Element>();
    for (const el of Array.from(document.querySelectorAll(SELECTOR)).slice(0, max)) {
      if (seen.has(el)) continue;
      seen.add(el);
      if (!visible(el)) continue;

      const vis = normalise(visibleText(el));
      // Nothing visible to speak, or the control is labelled from outside its
      // own box (a <label> elsewhere) - neither is a 2.5.3 failure.
      if (!vis) continue;

      const { name, from } = accName(el);
      const acc = normalise(name);
      if (!acc) continue;
      if (acc.includes(vis)) continue;

      const describe = (e: Element) =>
        e.tagName.toLowerCase() +
        (e.id ? `#${e.id}` : '') +
        (typeof e.className === 'string' && e.className
          ? '.' + e.className.trim().split(/\s+/).slice(0, 2).join('.')
          : '');
      out.push({ selector: describe(el), visibleLabel: vis, accessibleName: acc, from });
    }
    return out;
  }, MAX_ELEMENTS);

  for (const m of mismatches) {
    findings.push({
      check: 'label-in-name',
      criterion: '2.5.3 Label in Name',
      summary: `Accessible name does not contain the visible label on ${m.selector}`,
      detail: `Visible label: "${m.visibleLabel}". Accessible name: "${m.accessibleName}" (from ${m.from}). Speech input users say the visible label, so the accessible name must contain it. Confirm manually: this approximates accname and does not implement all of it.`,
    });
  }
  writeReport(`${slug}-label-in-name`, testInfo, findings);
}

/**
 * WCAG 1.3.5 Identify Input Purpose: inputs that collect information about
 * the user must carry the matching `autocomplete` token, so that the browser
 * and assistive technology can fill or explain them.
 *
 * Only fields about the *user* are in scope - a search box or a content
 * title is not. Rather than guess from arbitrary field names, this matches
 * the specific inputs Drupal and Mukurtu actually render for user data,
 * which keeps false positives near zero at the cost of not being exhaustive.
 */
export async function checkInputPurpose(page: Page, testInfo: TestInfo, slug: string): Promise<void> {
  const findings: CheckFinding[] = [];

  // name attribute (or id) -> the autocomplete token WCAG expects.
  const EXPECTED: Record<string, string[]> = {
    name: ['username'],
    mail: ['email'],
    pass: ['current-password', 'new-password'],
    'pass[pass1]': ['new-password'],
    'pass[pass2]': ['new-password'],
    current_pass: ['current-password'],
  };

  const results = await page.evaluate((expected) => {
    const out: { field: string; got: string | null; want: string[] }[] = [];
    document.querySelectorAll<HTMLInputElement>('input:not([type="hidden"])').forEach((el) => {
      const type = el.type;
      if (!['text', 'email', 'password', 'tel'].includes(type)) return;
      const key = el.name || el.id;
      const want = expected[key];
      if (!want) return;
      const r = el.getBoundingClientRect();
      if (r.width === 0 && r.height === 0) return;
      const got = el.getAttribute('autocomplete');
      if (got && want.includes(got)) return;
      out.push({ field: key, got, want });
    });
    return out;
  }, EXPECTED);

  for (const r of results) {
    findings.push({
      check: 'input-purpose',
      criterion: '1.3.5 Identify Input Purpose',
      summary: `Input "${r.field}" has ${r.got ? `autocomplete="${r.got}"` : 'no autocomplete attribute'}, expected one of: ${r.want.join(', ')}`,
      detail: 'Fields collecting information about the user need the matching autocomplete token so browsers and assistive technology can fill and explain them.',
    });
  }
  writeReport(`${slug}-input-purpose`, testInfo, findings);
}

/**
 * WCAG 1.4.12 Text Spacing: no loss of content or function when the reader
 * overrides line height to 1.5, letter spacing to 0.12em, word spacing to
 * 0.16em and paragraph spacing to 2em.
 *
 * Detects both ways that fails: the page overflowing horizontally, and
 * individual elements clipping their own content because they have a fixed
 * height with hidden overflow. The second is the common one and is what a
 * reflow check alone would miss.
 *
 * Removes its own override before returning, so later checks are not run
 * against respaced text.
 */
export async function checkTextSpacing(page: Page, testInfo: TestInfo, slug: string): Promise<void> {
  const findings: CheckFinding[] = [];
  const STYLE_ID = 'a11y-text-spacing-override';

  const before = await page.evaluate(() => ({
    scrollWidth: document.documentElement.scrollWidth,
    clientWidth: document.documentElement.clientWidth,
  }));

  await page.addStyleTag({
    content: `#${STYLE_ID}-marker{}
      * { line-height: 1.5 !important; letter-spacing: 0.12em !important; word-spacing: 0.16em !important; }
      p, li, dd, blockquote { margin-block-end: 2em !important; }`,
  });
  // Tag the style element so it can be removed again precisely.
  await page.evaluate((id) => {
    const tags = document.querySelectorAll('style');
    const last = tags[tags.length - 1];
    if (last) last.id = id;
  }, STYLE_ID);
  await page.waitForTimeout(300);

  const after = await page.evaluate(() => {
    const clipped: { selector: string; scrollHeight: number; clientHeight: number; text: string }[] = [];
    document.querySelectorAll<HTMLElement>('body *').forEach((el) => {
      const s = getComputedStyle(el);
      if (s.overflowY !== 'hidden' && s.overflow !== 'hidden') return;
      // Only a fixed or capped height can clip; auto height grows instead.
      if (s.height === 'auto' && s.maxHeight === 'none') return;
      if (el.scrollHeight <= el.clientHeight + 1) return;
      if (el.clientHeight === 0) return;
      // Ignore elements with no text of their own to lose.
      const text = (el.textContent || '').trim();
      if (!text) return;
      clipped.push({
        selector: el.tagName.toLowerCase() +
          (el.id ? `#${el.id}` : '') +
          (typeof el.className === 'string' && el.className
            ? '.' + el.className.trim().split(/\s+/).slice(0, 2).join('.')
            : ''),
        scrollHeight: el.scrollHeight,
        clientHeight: el.clientHeight,
        text: text.slice(0, 60),
      });
    });
    return {
      scrollWidth: document.documentElement.scrollWidth,
      clientWidth: document.documentElement.clientWidth,
      clipped: clipped.slice(0, 10),
    };
  });

  if (after.scrollWidth > after.clientWidth + 1 && before.scrollWidth <= before.clientWidth + 1) {
    findings.push({
      check: 'text-spacing-overflow',
      criterion: '1.4.12 Text Spacing',
      summary: `Horizontal scroll appears once text spacing is overridden (content ${after.scrollWidth}px vs viewport ${after.clientWidth}px)`,
      detail: 'The page did not overflow before the override, so the spacing itself caused it.',
    });
  }

  for (const c of after.clipped) {
    findings.push({
      check: 'text-spacing-clipped',
      criterion: '1.4.12 Text Spacing',
      summary: `Content clips with overridden text spacing in ${c.selector} (needs ${c.scrollHeight}px, has ${c.clientHeight}px)`,
      detail: `Text starting "${c.text}" is cut off. A fixed or capped height with hidden overflow cannot grow to fit respaced text.`,
    });
  }

  await page.evaluate((id) => document.getElementById(id)?.remove(), STYLE_ID);
  await page.waitForTimeout(100);
  writeReport(`${slug}-text-spacing`, testInfo, findings);
}
