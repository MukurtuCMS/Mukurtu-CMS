import { test, expect, Page } from '@playwright/test';
import fs = require('fs');
import path = require('path');

/**
 * Long lists in the term page's side panel (#2611).
 *
 * Runs the real term-panel.js against a fixture mirroring
 * taxonomy-records.html.twig. `once()` is reimplemented minimally, as in
 * multipage-announce.offline.spec.ts, because @drupal/core's copy lives under
 * the gitignored vendor/ directory.
 */

const REPO = path.join(__dirname, '../../..');
const SCRIPT = fs.readFileSync(path.join(REPO, 'modules/mukurtu_taxonomy/js/term-panel.js'), 'utf8');

const DRUPAL_STUB = `
  window.Drupal = { behaviors: {}, t: function (str) { return str; } };
  window.once = function (id, selector, context) {
    var root = context || document;
    var attr = 'data-once-' + id;
    var els = Array.prototype.filter.call(root.querySelectorAll(selector), function (el) {
      return !el.hasAttribute(attr);
    });
    els.forEach(function (el) { el.setAttribute(attr, ''); });
    return els;
  };
`;

const list = (prefix: string, count: number) =>
  `<ul class="taxonomy-term-panel__list">${Array.from({ length: count }, (_, i) => `<li><a href="#">${prefix} ${i + 1}</a></li>`).join('')}</ul>`;

async function load(page: Page, narrower: number, siblings: number) {
  await page.setContent(`<!doctype html><html lang="en"><head><title>Term</title></head><body>
    <aside class="taxonomy-term-panel">
      <h2 id="narrower">Narrower terms</h2>${list('Narrower', narrower)}
      <h2 id="siblings">Also in Washington</h2>${list('Sibling', siblings)}
    </aside></body></html>`);
  await page.addScriptTag({ content: DRUPAL_STUB });
  await page.addScriptTag({ content: SCRIPT });
  await page.evaluate(() => (window as any).Drupal.behaviors.mukurtuTermPanel.attach(document));
}

test.describe('Term panel script', () => {
  test('a list of 10 terms is left alone', async ({ page }) => {
    await load(page, 10, 3);
    await expect(page.getByRole('button', { name: 'See more' })).toHaveCount(0);
    await expect(page.locator('li:visible')).toHaveCount(13);
  });

  test('a longer list shows 10 terms, and the button shows the rest', async ({ page }) => {
    await load(page, 12, 3);
    await expect(page.locator('li:visible')).toHaveCount(13);
    const button = page.getByRole('button', { name: 'See more' });
    await expect(button).toHaveAccessibleDescription('Narrower terms');

    await button.click();
    await expect(page.locator('li:visible')).toHaveCount(15);
    await expect(button).toHaveCount(0);
    await expect(page.getByRole('link', { name: 'Narrower 11' })).toBeFocused();
  });

  test('each list has its own button', async ({ page }) => {
    await load(page, 11, 11);
    await expect(page.getByRole('button', { name: 'See more' })).toHaveCount(2);
    await page.getByRole('button', { name: 'See more' }).nth(1).click();
    await expect(page.getByRole('link', { name: 'Sibling 11' })).toBeFocused();
    await expect(page.getByRole('link', { name: 'Narrower 11' })).toBeHidden();
  });
});
