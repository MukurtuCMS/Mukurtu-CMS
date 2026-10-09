import { test, expect } from '@playwright/test';
import * as fs from 'fs'; import * as path from 'path';
import { checkTextZoom } from '../src/helpers/automated-checks';
const DIR = path.join(__dirname, '../test-results/a11y-extra');
const read = (s: string) => { const f = path.join(DIR, `${s}.json`); return fs.existsSync(f) ? JSON.parse(fs.readFileSync(f,'utf8')).findings : []; };
test.use({ storageState: 'playwright/.auth/admin.json' });

test('the four former false positives now report clean', async ({ page }, ti) => {
  for (const [slug, url] of [['manage-content','/admin/content'], ['manage-people-list','/admin/people'],
      ['import-upload','/admin/import'], ['submissions-settings-edit','/admin/config/mukurtu/submissions/digital_heritage']]) {
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.goto(url, { waitUntil: 'networkidle' });
    await checkTextZoom(page, ti, slug);
    const f = read(`${slug}-text-zoom`);
    console.log(`  ${slug.padEnd(26)} findings=${f.length}` + (f[0] ? '  ' + f[0].summary.slice(0, 60) : ''));
    expect(f.length, `${slug} should be clean under real text sizing`).toBe(0);
  }
});

test('positive control: a page that genuinely fails is still caught', async ({ page }, ti) => {
  // A fixed-width element that cannot fit once the text doubles. Served as a
  // real navigation so the reload path is exercised, not the fallback.
  await page.route('**/zoom-fixture', (r) => r.fulfill({ contentType: 'text/html', body:
    `<html><body style="margin:0"><div style="width:60em;background:#eee">
      wide fixed-width block measured in em, so it doubles with the text
     </div></body></html>` }));
  await page.setViewportSize({ width: 800, height: 600 });
  await page.goto('https://mukurtu-a11y.ddev.site/zoom-fixture', { waitUntil: 'networkidle' });
  await checkTextZoom(page, ti, 'fixture');
  const f = read('fixture-text-zoom');
  console.log(`  fixture findings=${f.length}` + (f[0] ? '\n       ' + f[0].summary.slice(0, 70) + '\n       detail: ' + f[0].detail.slice(0, 72) : ''));
  expect(f.length, 'a genuine 200% overflow is still caught').toBeGreaterThan(0);
  expect(f[0].detail, 'the finding says it used real text sizing').toContain("browser's own default font size");
});

test('font size is restored for later pages', async ({ page }, ti) => {
  await page.setViewportSize({ width: 1280, height: 900 });
  await page.goto('/admin/content', { waitUntil: 'networkidle' });
  const before = await page.evaluate(() => getComputedStyle(document.body).fontSize);
  await checkTextZoom(page, ti, 'restore');
  await page.goto('/admin/people', { waitUntil: 'networkidle' });
  const after = await page.evaluate(() => getComputedStyle(document.body).fontSize);
  console.log(`  body font before=${before} after=${after}`);
  expect(after, 'the next page is not left at 200%').toBe(before);
});
