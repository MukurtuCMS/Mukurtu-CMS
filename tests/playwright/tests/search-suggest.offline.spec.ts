import { test, expect, Page } from '@playwright/test';
import fs = require('fs');
import path = require('path');

/**
 * Search word suggestions on Browse, Digital Heritage and Dictionary (#2473).
 *
 * Runs the real search-suggest.js and compiled theme CSS against a fixture of
 * the views exposed search form, with the suggestion route mocked. `once()`
 * and Drupal.t() are reimplemented minimally, as in
 * multipage-announce.offline.spec.ts, because @drupal/core's copies live under
 * the gitignored vendor/ directory.
 *
 * Better Exposed Filters' auto-submit is stood in for by a few lines that
 * keep the parts the script depends on: a 500ms keyup debounce, the keys it
 * ignores, and skipping inputs with data-bef-auto-submit-exclude. Mukurtu's
 * form-disable-enter-submit.js, which stops Enter submitting the views
 * exposed forms, is stood in for the same way.
 */

const REPO = path.join(__dirname, '../../..');
const SCRIPT = fs.readFileSync(path.join(REPO, 'modules/mukurtu_browse/js/search-suggest.js'), 'utf8');
const CSS = fs.readFileSync(path.join(REPO, 'themes/mukurtu_v4/css/style.css'), 'utf8');

const DRUPAL_STUB = `
  window.Drupal = { behaviors: {} };
  window.Drupal.t = function (str, args) {
    Object.keys(args || {}).forEach(function (key) {
      var value = String(args[key]).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
      str = str.split(key).join(value);
    });
    return str;
  };
  window.Drupal.formatPlural = function (count, singular, plural) {
    return window.Drupal.t(count === 1 ? singular : plural, { '@count': count });
  };
  window.__announced = [];
  window.Drupal.announce = function (text) { window.__announced.push(text); };
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

const BEF_STUB = `
  window.__autoSubmits = 0;
  window.__submitted = [];
  (function () {
    var input = document.getElementById('edit-search-api-fulltext');
    var ignored = [9, 13, 16, 17, 18, 20, 27, 32, 33, 34, 35, 36, 37, 38, 39, 40];
    var timer;
    input.addEventListener('keyup', function (e) {
      clearTimeout(timer);
      timer = setTimeout(function () {
        if (input.hasAttribute('data-bef-auto-submit-exclude') || ignored.indexOf(e.keyCode) !== -1) {
          return;
        }
        window.__autoSubmits++;
      }, 500);
    });
    input.form.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
      }
    });
    input.form.addEventListener('submit', function (e) {
      e.preventDefault();
      window.__submitted.push(input.value);
    });
  })();
`;

// Mirrors the views exposed form markup for the Browse search box, with the
// attributes SearchSuggestionHooks adds.
const FIXTURE = `<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>Browse</title><style>${CSS}</style></head>
<body>
  <form action="/browse" method="get" class="views-exposed-form">
    <div class="js-form-item form-item js-form-type-textfield">
      <label for="edit-search-api-fulltext">Search</label>
      <input placeholder="Search all content" autocomplete="off"
        data-mukurtu-search-suggest="/search_api_autocomplete/mukurtu_browse?display=mukurtu_browse_block&amp;&amp;filter=search_api_fulltext"
        data-mukurtu-search-suggest-min="3" type="text" id="edit-search-api-fulltext"
        name="search_api_fulltext" value="" class="form-text">
    </div>
    <input data-bef-auto-submit-click type="submit" value="Apply">
  </form>
  <script>${DRUPAL_STUB}</script>
  <script>${SCRIPT}</script>
  <script>Drupal.behaviors.mukurtuSearchSuggest.attach(document);</script>
  <script>${BEF_STUB}</script>
</body></html>`;

const SUGGESTIONS: Record<string, string[]> = {
  buf: ['buffalo', 'bufflehead'],
  bas: ['basket'],
  xss: ['<img src=x onerror="window.__xss = 1">'],
};

async function openFixture(page: Page): Promise<string[]> {
  const requests: string[] = [];
  await page.route(/^https:\/\/suggest\.test\/search_api_autocomplete\//, async (route) => {
    const url = new URL(route.request().url());
    requests.push(url.search);
    const q = url.searchParams.get('q') ?? '';
    const words = SUGGESTIONS[q] ?? [];
    // Slower than Better Exposed Filters' 500ms delay, so its timer has
    // already fired, and been skipped, by the time the empty answer arrives.
    if (q === 'slow') {
      await new Promise((resolve) => setTimeout(resolve, 800));
    }
    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(words.map((value) => ({ value, label: `<div>${value}</div>` }))),
    });
  });
  await page.route('https://suggest.test/browse', (route) =>
    route.fulfill({ status: 200, contentType: 'text/html', body: FIXTURE }));
  await page.goto('https://suggest.test/browse');
  return requests;
}

const box = (page: Page) => page.getByRole('combobox', { name: 'Search' });
const list = (page: Page) => page.getByRole('listbox', { name: 'Search suggestions' });
const announced = (page: Page) => page.evaluate(() => (window as any).__announced as string[]);
const autoSubmits = (page: Page) => page.evaluate(() => (window as any).__autoSubmits as number);
const submitted = (page: Page) => page.evaluate(() => (window as any).__submitted as string[]);

test('the search box is a combobox that controls a named list', async ({ page }) => {
  await openFixture(page);
  const input = box(page);

  await expect(input).toHaveAttribute('aria-autocomplete', 'list');
  await expect(input).toHaveAttribute('aria-expanded', 'false');
  const listId = await input.getAttribute('aria-controls');
  await expect(page.locator(`#${listId}`)).toHaveAttribute('role', 'listbox');
  await expect(list(page)).toBeHidden();
});

test('typing shows suggestions and announces how many', async ({ page }) => {
  const requests = await openFixture(page);

  await box(page).pressSequentially('bu');
  await page.waitForTimeout(400);
  expect(requests).toEqual([]);

  await box(page).press('f');
  await expect(list(page)).toBeVisible();
  await expect(box(page)).toHaveAttribute('aria-expanded', 'true');
  await expect(list(page).getByRole('option')).toHaveText(['buffalo', 'bufflehead']);
  expect(await announced(page)).toEqual(['2 suggestions available.']);
  // The display and filter the server needs ride along with the typed text.
  const params = new URLSearchParams(requests[requests.length - 1]);
  expect(params.get('display')).toBe('mukurtu_browse_block');
  expect(params.get('filter')).toBe('search_api_fulltext');
  expect(params.get('q')).toBe('buf');
});

test('arrow keys move through the list and Enter searches for the word', async ({ page }) => {
  await openFixture(page);
  const input = box(page);
  await input.pressSequentially('buf');
  await expect(list(page)).toBeVisible();
  const options = list(page).getByRole('option');

  await input.press('ArrowDown');
  await expect(options.nth(0)).toHaveAttribute('aria-selected', 'true');
  await expect(input).toHaveAttribute('aria-activedescendant', (await options.nth(0).getAttribute('id'))!);

  await input.press('ArrowDown');
  await expect(options.nth(1)).toHaveAttribute('aria-selected', 'true');
  await expect(options.nth(0)).toHaveAttribute('aria-selected', 'false');

  // Wraps around both ways.
  await input.press('ArrowDown');
  await expect(options.nth(0)).toHaveAttribute('aria-selected', 'true');
  await input.press('ArrowUp');
  await expect(options.nth(1)).toHaveAttribute('aria-selected', 'true');

  // Focus never leaves the box.
  await expect(input).toBeFocused();

  await input.press('Enter');
  await expect(input).toHaveValue('bufflehead');
  await expect(list(page)).toBeHidden();
  await expect(input).toHaveAttribute('aria-expanded', 'false');
  await expect(input).not.toHaveAttribute('aria-activedescendant');
  expect(await submitted(page)).toEqual(['bufflehead']);
});

test('typing after moving through the list searches for what was typed', async ({ page }) => {
  await openFixture(page);
  const input = box(page);
  await input.pressSequentially('buf');
  await expect(list(page)).toBeVisible();
  await input.press('ArrowDown');
  await expect(input).toHaveAttribute('aria-activedescendant');

  // Checked at once, before the next request could replace the list.
  await input.press('z');
  expect(await input.getAttribute('aria-activedescendant')).toBeNull();
  await input.press('Enter');
  expect(await submitted(page)).toEqual(['bufz']);
});

test('Enter with no suggestion highlighted searches for what was typed', async ({ page }) => {
  await openFixture(page);
  const input = box(page);
  await input.pressSequentially('buf');
  await expect(list(page)).toBeVisible();

  await input.press('Enter');
  await expect(list(page)).toBeHidden();
  expect(await submitted(page)).toEqual(['buf']);
});

test('old suggestions do not come back after deleting below three letters', async ({ page }) => {
  await openFixture(page);
  const input = box(page);
  await input.pressSequentially('buf');
  await expect(list(page)).toBeVisible();

  await input.press('Backspace');
  await input.press('Backspace');
  await expect(list(page)).toBeHidden();
  await input.press('ArrowDown');
  await expect(list(page)).toBeHidden();
  await expect(list(page).getByRole('option')).toHaveCount(0);
  await expect(input).toHaveAttribute('aria-expanded', 'false');
});

test('Enter that finishes a character in an input method does not pick or search', async ({ page }) => {
  await openFixture(page);
  const input = box(page);
  await input.pressSequentially('buf');
  await expect(list(page)).toBeVisible();
  await input.press('ArrowDown');

  await input.evaluate((el) => el.dispatchEvent(new KeyboardEvent('keydown', {
    key: 'Enter', keyCode: 229, isComposing: true, bubbles: true, cancelable: true,
  })));
  await expect(input).toHaveValue('buf');
  await expect(list(page)).toBeVisible();
  expect(await submitted(page)).toEqual([]);
});

test('clicking a suggestion searches for it', async ({ page }) => {
  await openFixture(page);
  await box(page).pressSequentially('bas');
  await list(page).getByRole('option', { name: 'basket' }).click();

  await expect(box(page)).toHaveValue('basket');
  await expect(box(page)).toBeFocused();
  expect(await submitted(page)).toEqual(['basket']);
});

test('Escape closes the list, then clears the box', async ({ page }) => {
  await openFixture(page);
  const input = box(page);
  await input.pressSequentially('buf');
  await expect(list(page)).toBeVisible();

  await input.press('Escape');
  await expect(list(page)).toBeHidden();
  await expect(input).toHaveValue('buf');
  // Closing the list lets the search run on its own again.
  await expect.poll(() => autoSubmits(page), { timeout: 2000 }).toBe(1);

  await input.press('Escape');
  await expect(input).toHaveValue('');
  expect(await submitted(page)).toEqual([]);
});

test('the page does not auto-submit while suggestions are open', async ({ page }) => {
  await openFixture(page);
  await box(page).pressSequentially('buf');
  await expect(list(page)).toBeVisible();

  // Well past Better Exposed Filters' 500ms delay.
  await page.waitForTimeout(1000);
  expect(await autoSubmits(page)).toBe(0);
  expect(await submitted(page)).toEqual([]);
});

test('with nothing to suggest, the search still runs on its own', async ({ page }) => {
  await openFixture(page);
  await box(page).pressSequentially('zzq');

  await expect.poll(() => announced(page)).toEqual(['No suggestions.']);
  await expect(list(page)).toBeHidden();
  await expect(box(page)).toHaveAttribute('aria-expanded', 'false');
  await expect.poll(() => autoSubmits(page), { timeout: 2000 }).toBe(1);
});

test('a slow answer with nothing to suggest still lets the search run', async ({ page }) => {
  await openFixture(page);
  await box(page).pressSequentially('slow');

  await expect.poll(() => announced(page), { timeout: 3000 }).toEqual(['No suggestions.']);
  await expect.poll(() => autoSubmits(page), { timeout: 3000 }).toBe(1);
});

test('suggestions are shown as text, never as markup', async ({ page }) => {
  await openFixture(page);
  await box(page).pressSequentially('xss');
  await expect(list(page).getByRole('option')).toHaveText(['<img src=x onerror="window.__xss = 1">']);
  expect(await list(page).locator('img').count()).toBe(0);
  expect(await page.evaluate(() => (window as any).__xss)).toBeUndefined();
});

test('each suggestion is at least 24 pixels tall', async ({ page }) => {
  await openFixture(page);
  await box(page).pressSequentially('buf');
  const option = list(page).getByRole('option').first();
  await expect(option).toBeVisible();
  const boxSize = await option.boundingBox();
  expect(boxSize!.height).toBeGreaterThanOrEqual(24);
});
