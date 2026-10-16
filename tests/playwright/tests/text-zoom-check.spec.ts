import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';
import { checkTextZoom } from '~helpers/automated-checks';

/**
 * Tests the text-zoom check itself, against fixtures.
 *
 * checkTextZoom() is report-only, so nothing fails when it is wrong, and
 * for a long time it reported only two numbers: content width against
 * viewport width. That is enough to know something is wide and not enough
 * to do anything about it. Two Local Contexts pages failed on every main
 * run for a week, and finding out why cost two rounds of manual
 * measurement on the preview and three failed reproductions, none of which
 * identified the element (#2392).
 *
 * It now names what overflowed and says whether the page had settled when
 * it was measured. These fixtures pin that: a finding must carry the
 * offender, and a page that fits must produce nothing.
 */
function reportFor(slug: string) {
  const file = path.join(__dirname, '..', 'test-results', 'a11y-extra', `${slug}-text-zoom.json`);
  return JSON.parse(fs.readFileSync(file, 'utf8'));
}

test('names the element that overflows', async ({ page }, testInfo) => {
  await page.setViewportSize({ width: 600, height: 400 });
  await page.setContent(`
    <!DOCTYPE html><html lang="en"><head><title>Wide fixture</title></head><body>
      <div id="narrow">fits</div>
      <div id="too-wide" style="width: 2000px; height: 40px; background: #eee;">far too wide</div>
    </body></html>
  `);

  await checkTextZoom(page, testInfo, 'fixture-text-zoom-wide');
  const report = reportFor('fixture-text-zoom-wide');

  expect(report.summary.findings, 'the overflow is reported').toBe(1);
  const finding = report.findings[0];

  expect(finding.offenders, 'the finding names what overflowed').toBeTruthy();
  const ids = finding.offenders.map((o: { id: string }) => o.id);
  expect(ids, 'the wide element is named').toContain('too-wide');
  expect(ids, 'an element that fits is not named').not.toContain('narrow');

  // The diagnostics that tell a reader whether to trust the measurement.
  expect(finding.readyState, 'the page state is recorded').toBeTruthy();
  expect(finding).toHaveProperty('msSinceLoad');
});

test('reports nothing, and no offenders, when the page fits', async ({ page }, testInfo) => {
  await page.setViewportSize({ width: 600, height: 400 });
  await page.setContent(`
    <!DOCTYPE html><html lang="en"><head><title>Narrow fixture</title></head><body>
      <p>This page fits at any text size.</p>
    </body></html>
  `);

  await checkTextZoom(page, testInfo, 'fixture-text-zoom-narrow');
  expect(reportFor('fixture-text-zoom-narrow').summary.findings).toBe(0);
});
