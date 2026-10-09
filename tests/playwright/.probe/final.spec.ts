import { test } from '@playwright/test';
import * as fs from 'fs'; import * as path from 'path';
import { checkLabelInName, checkInputPurpose } from '../src/helpers/automated-checks';
const DIR = path.join(__dirname, '../test-results/a11y-extra');
const read = (s: string) => { const f = path.join(DIR, `${s}.json`); return fs.existsSync(f) ? JSON.parse(fs.readFileSync(f,'utf8')).findings : []; };
const slugify = (u: string) => u.replace(/[^a-z0-9]+/gi, '-');

test.describe('1.3.5 user-data forms', () => {
  test.use({ storageState: 'playwright/.auth/admin.json' });
  test('every form that collects the user\'s own information', async ({ page }, ti) => {
    for (const url of ['/user/login', '/user/register', '/user/password', '/user/1/edit']) {
      const r = await page.goto(url, { waitUntil: 'networkidle' }).catch(() => null);
      const fields = await page.evaluate(() => [...document.querySelectorAll<HTMLInputElement>('input:not([type=hidden])')]
        .filter((i) => ['text','email','password','tel'].includes(i.type) && i.offsetParent !== null)
        .map((i) => `${i.name}:${i.getAttribute('autocomplete') ?? 'NONE'}`));
      await checkInputPurpose(page, ti, slugify(url));
      const f = read(slugify(url) + '-input-purpose');
      console.log(`  ${url.padEnd(16)} HTTP ${r?.status()}  findings=${f.length}  fields=${fields.join(' ') || '(none)'}`);
      f.forEach((x: any) => console.log('        ' + x.summary.slice(0, 84)));
    }
  });
});

test.describe('2.5.3 anonymous', () => {
  test('anonymous pages', async ({ page }, ti) => {
    let t = 0;
    for (const url of ['/', '/browse', '/user/login', '/user/register', '/communities']) {
      const r = await page.goto(url, { waitUntil: 'networkidle' }).catch(() => null);
      await checkLabelInName(page, ti, 'a' + slugify(url));
      const f = read('a' + slugify(url) + '-label-in-name'); t += f.length;
      console.log(`  anon ${url.padEnd(14)} HTTP ${r?.status()} findings=${f.length}`);
      f.forEach((x: any) => console.log('        ' + x.summary.slice(0, 84)));
    }
    console.log(`  anonymous total: ${t}`);
  });
});

test.describe('2.5.3 admin', () => {
  test.use({ storageState: 'playwright/.auth/admin.json' });
  test('admin pages', async ({ page }, ti) => {
    let t = 0;
    for (const url of ['/admin', '/admin/content', '/admin/people', '/node/add/digital_heritage',
                       '/admin/config/mukurtu/submissions', '/admin/communities-protocols']) {
      const r = await page.goto(url, { waitUntil: 'networkidle' }).catch(() => null);
      await checkLabelInName(page, ti, 'd' + slugify(url));
      const f = read('d' + slugify(url) + '-label-in-name'); t += f.length;
      console.log(`  admin ${url.padEnd(38)} HTTP ${r?.status()} findings=${f.length}`);
      f.forEach((x: any) => console.log('        ' + x.summary.slice(0, 92)));
    }
    console.log(`  admin total: ${t}`);
  });
});
