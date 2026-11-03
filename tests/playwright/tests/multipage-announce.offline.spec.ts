import { test, expect, Page } from '@playwright/test';
import fs = require('fs');
import path = require('path');

/**
 * Multipage items announce the page they switch to (#2557).
 *
 * Switching pages replaces #mukurtu-multipage-item-selected-page over AJAX,
 * which nothing announced. multipage-nav.js now calls Drupal.announce() when
 * the new page has loaded, and skips a page the reader has already moved on
 * from.
 *
 * Runs the real multipage-nav.js and Splide against a static fixture, with
 * Drupal.ajax() stubbed so the test decides when each page "finishes"
 * loading. `once()` is reimplemented minimally, as in copy-citation.spec.ts,
 * because @drupal/core's copy lives under the gitignored vendor/ directory.
 * The fixture is served from a fake origin rather than set with setContent(),
 * because the script calls history.replaceState(), which about:blank refuses.
 */

const REPO = path.join(__dirname, '../../..');
const SPLIDE = fs.readFileSync(path.join(REPO, 'modules/mukurtu_core/libraries/splide/splide.min.js'), 'utf8');
const NAV = fs.readFileSync(path.join(REPO, 'modules/mukurtu_multipage_items/js/multipage-nav.js'), 'utf8');

const PAGES: Array<[string, string]> = [
  ['5', 'Field Notebook'],
  ['6', 'Field Notebook, page 2'],
  ['7', 'Field Notebook, page 3'],
];

const DRUPAL_STUB = `
  window.Drupal = { behaviors: {} };
  // Like Drupal.t(): @placeholders are HTML-escaped.
  window.Drupal.t = function (str, args) {
    Object.keys(args || {}).forEach(function (key) {
      var value = String(args[key]).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
      str = str.split(key).join(value);
    });
    return str;
  };
  window.__announced = [];
  window.Drupal.announce = function (text) { window.__announced.push(text); };
  // Each request waits until the test calls __finish(url).
  window.__pending = {};
  window.Drupal.ajax = function (options) {
    return {
      execute: function () {
        return new Promise(function (resolve) { window.__pending[options.url] = resolve; });
      },
    };
  };
  window.__finish = function (url) { window.__pending[url](); delete window.__pending[url]; };
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

// Mirrors multipage-item-book-view.html.twig.
const FIXTURE = `<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>Field Notebook</title></head>
<body>
  <div class="multipage-items">
    <div class="splide__wrapper">
      <section class="splide multipage-carousel" aria-label="Multipage Item Carousel">
        <div class="splide__track" id="mukurtu-multipage-item-page-nav">
          <ul class="splide__list">
            ${PAGES.map(([id, title]) => `<li class="splide__slide" data-id="${id}">${title}</li>`).join('')}
          </ul>
        </div>
      </section>
    </div>
    <div class="multipage-carousel__navigation">
      <label for="multipage-item-table-of-contents">Jump to page</label>
      <select id="multipage-item-table-of-contents">
        ${PAGES.map(([id, title]) => `<option value="${id}">${title}</option>`).join('')}
      </select>
    </div>
    <div id="mukurtu-multipage-item-selected-page"></div>
  </div>
  <script>${DRUPAL_STUB}</script>
  <script>${SPLIDE}</script>
  <script>${NAV}</script>
  <script>Drupal.behaviors.mukurtuMultipageNav.attach(document);</script>
</body></html>`;

async function openFixture(page: Page): Promise<void> {
  await page.route(/^https:\/\/multipage\.test\//, (route) =>
    route.fulfill({ status: 200, contentType: 'text/html', body: FIXTURE }));
  await page.goto('https://multipage.test/node/5');
}

const announced = (page: Page) => page.evaluate(() => (window as any).__announced as string[]);
const pending = (page: Page) => page.evaluate(() => Object.keys((window as any).__pending));
const finish = (page: Page, url: string) => page.evaluate((u) => (window as any).__finish(u), url);

test('announces the page once it has loaded', async ({ page }) => {
  await openFixture(page);
  expect(await announced(page)).toEqual([]);

  await page.selectOption('#multipage-item-table-of-contents', '7');
  await expect.poll(() => pending(page)).toEqual(['/multipageitem/7/ajax']);
  // Nothing is said before the content has arrived.
  expect(await announced(page)).toEqual([]);

  await finish(page, '/multipageitem/7/ajax');
  await expect.poll(() => announced(page)).toEqual(['Page 3 of 3: Field Notebook, page 3']);
});

test('skips a page the reader has already moved on from', async ({ page }) => {
  await openFixture(page);

  await page.selectOption('#multipage-item-table-of-contents', '6');
  await expect.poll(() => pending(page)).toEqual(['/multipageitem/6/ajax']);
  await page.selectOption('#multipage-item-table-of-contents', '7');
  await expect.poll(() => pending(page)).toContain('/multipageitem/7/ajax');

  await finish(page, '/multipageitem/6/ajax');
  await finish(page, '/multipageitem/7/ajax');
  await expect.poll(() => announced(page)).toEqual(['Page 3 of 3: Field Notebook, page 3']);
});
