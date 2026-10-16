import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';
import { checkTextZoom } from '~helpers/automated-checks';

/**
 * Tests two measurement defects in the automated checks (#2436).
 *
 * Both produced findings that were not defects, and both cost real effort:
 * the clipped-offender bug nearly got its own CSS fix on a page that could
 * not have scrolled, and the animation bug sent an investigation after
 * markup that was correct in every state a user can reach.
 *
 * The checks are report-only, so a filter that silently over-matched would
 * look exactly like a clean site. These pin both directions: the thing that
 * must still be reported, and the thing that must not.
 */

const RESULTS_DIR = path.join(__dirname, '../test-results/a11y-extra');

function readReport(slug: string) {
  const file = path.join(RESULTS_DIR, `${slug}.json`);
  const report = JSON.parse(fs.readFileSync(file, 'utf8'));
  fs.rmSync(file, { force: true });
  return report;
}

function zoomFinding(report: { findings: { check: string }[] }) {
  return report.findings.find((f) => f.check === 'text-zoom-200') as
    | { summary: string; offenders?: { tag: string }[]; clippedOffenders?: number }
    | undefined;
}

/** A page 2000px wide at a 600px viewport, so the check has something to find. */
const WIDE = `
  <style>
    body { margin: 0; }
    .clipper { overflow: hidden; width: 300px; }
    .clipper .inner { width: 2000px; background: #eee; }
  </style>
`;

test.describe('text-zoom offenders', () => {
  test('an element clipped by an ancestor is not blamed', async ({ page }, testInfo) => {
    // The shape that misled us: a wide box inside a container that clips it,
    // with the container itself comfortably inside the viewport, plus one
    // genuinely overflowing box so the check reports at all.
    await page.setViewportSize({ width: 600, height: 400 });
    await page.setContent(`${WIDE}
      <div class="clipper"><div class="inner" id="clipped">clipped</div></div>
      <div id="genuine" style="width: 1500px">genuinely wide</div>
    `);

    await checkTextZoom(page, testInfo, 'tmp-clipped');
    const finding = zoomFinding(readReport('tmp-clipped-text-zoom'));

    expect(finding, 'the check still reports the page as overflowing').toBeTruthy();
    const ids = (finding!.offenders ?? []).map((o) => (o as { id?: string }).id);
    expect(ids, 'the genuinely wide box is named').toContain('genuine');
    expect(ids, 'the clipped box is not named').not.toContain('clipped');
    expect(finding!.clippedOffenders, 'the exclusion is recorded, not silent')
      .toBeGreaterThan(0);
  });

  test('a clipping ancestor that overflows is still blamed', async ({ page }, testInfo) => {
    // The case the filter must not swallow: the clipper itself runs past the
    // viewport, so it is a real cause even though it clips its own child.
    await page.setViewportSize({ width: 600, height: 400 });
    await page.setContent(`${WIDE}
      <div class="clipper" id="wide-clipper" style="width: 1200px">
        <div class="inner" id="inside">inside</div>
      </div>
    `);

    await checkTextZoom(page, testInfo, 'tmp-wide-clipper');
    const finding = zoomFinding(readReport('tmp-wide-clipper-text-zoom'));

    expect(finding).toBeTruthy();
    const ids = (finding!.offenders ?? []).map((o) => (o as { id?: string }).id);
    expect(ids, 'the overflowing clipper is named on its own merits')
      .toContain('wide-clipper');
  });

  test('a page that fits reports nothing', async ({ page }, testInfo) => {
    await page.setViewportSize({ width: 600, height: 400 });
    await page.setContent('<div style="width: 200px">narrow</div>');

    await checkTextZoom(page, testInfo, 'tmp-narrow');
    const finding = zoomFinding(readReport('tmp-narrow-text-zoom'));

    expect(finding?.summary ?? '').not.toContain('Horizontal scroll required');
  });
});

test.describe('animation settling', () => {
  test('a transition that changes visibility is over before measuring', async ({ page }) => {
    // Reproduces the shape of the Gin sidebar defect: two elements whose
    // visibility is driven by one class but with different durations, so
    // mid-flight they disagree.
    await page.setContent(`
      <style>
        #slow { transition: visibility 0s linear 0.3s; visibility: visible; }
        #fast { transition: visibility 0s linear 0s;   visibility: visible; }
        .closed #slow, .closed #fast { visibility: hidden; }
      </style>
      <div id="slow">slow</div><div id="fast">fast</div>
    `);

    const state = () => page.evaluate(() => [
      getComputedStyle(document.getElementById('slow')!).visibility,
      getComputedStyle(document.getElementById('fast')!).visibility,
    ]);

    expect(await state(), 'both start visible').toEqual(['visible', 'visible']);

    await page.evaluate(() => document.body.classList.add('closed'));
    // Without waiting, the two disagree: this is the window that produced
    // four spurious findings (#2430).
    expect(await state(), 'mid-transition they disagree').toEqual(['visible', 'hidden']);

    // Waiting the way openForAudit does resolves it.
    await page.evaluate(async () => {
      const running = document.getAnimations().filter((a) => a.playState === 'running');
      await Promise.race([
        Promise.allSettled(running.map((a) => a.finished)),
        new Promise((r) => setTimeout(r, 1000)),
      ]);
    });

    expect(await state(), 'once settled they agree').toEqual(['hidden', 'hidden']);
  });

  test('an indefinite animation does not hang the wait', async ({ page }) => {
    // A spinner never finishes, so the wait has to be raced against a cap or
    // every scan of a page with one would stall.
    await page.setContent(`
      <style>
        @keyframes spin { to { transform: rotate(360deg); } }
        #spinner { animation: spin 1s linear infinite; width: 10px; height: 10px; }
      </style>
      <div id="spinner"></div>
    `);

    const started = Date.now();
    await page.evaluate(async () => {
      const running = document.getAnimations().filter((a) => a.playState === 'running');
      await Promise.race([
        Promise.allSettled(running.map((a) => a.finished)),
        new Promise((r) => setTimeout(r, 1000)),
      ]);
    });
    const elapsed = Date.now() - started;

    expect(await page.evaluate(() => document.getAnimations().length),
      'the fixture really does have a running animation').toBeGreaterThan(0);
    expect(elapsed, 'the cap released the wait').toBeLessThan(3_000);
  });
});
