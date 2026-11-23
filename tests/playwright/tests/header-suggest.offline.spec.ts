import { test, expect, Page } from '@playwright/test';
import fs = require('fs');
import path = require('path');

/**
 * Item suggestions in the header search, grouped by content type (#2473).
 *
 * Runs the real search-suggest.js and compiled theme CSS against a fixture of
 * the header search form, with /browse/suggest mocked. search-suggest.offline
 * .spec.ts covers what both modes share (typing, Escape, the minimum length);
 * this covers what the header's "items" mode adds.
 */

const REPO = path.join(__dirname, '../../..');
const SCRIPT = fs.readFileSync(path.join(REPO, 'modules/mukurtu_browse/js/search-suggest.js'), 'utf8');
const CSS = fs.readFileSync(path.join(REPO, 'themes/mukurtu_v4/css/style.css'), 'utf8');

const DRUPAL_STUB = `
  window.Drupal = { behaviors: {} };
  window.Drupal.t = function (str, args) {
    Object.keys(args || {}).forEach(function (key) {
      var value = String(args[key]).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
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

// Mirrors the desktop header search in block--mukurtu-v4-main-menu.html.twig,
// with the attributes the module adds. The margin puts the box where the
// header does: toward the end of the row, with room on the start side only.
const FIXTURE = `<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Home</title><style>${CSS}</style></head>
<body>
  <div class="header-search" style="display:flex; justify-content:flex-end; padding-inline-end:64px">
    <form class="header-search__form" action="/browse" method="get" role="search" aria-label="Search" style="inline-size:220px">
      <input class="header-search__input" type="search" name="search_api_fulltext" placeholder="Search" aria-label="Search"
        autocomplete="off" data-mukurtu-search-suggest="/browse/suggest" data-mukurtu-search-suggest-mode="items"
        data-mukurtu-search-suggest-min="3" />
      <button class="header-search__button" type="submit" aria-label="Search">Go</button>
    </form>
  </div>
  <script>${DRUPAL_STUB}</script>
  <script>${SCRIPT}</script>
  <script>Drupal.behaviors.mukurtuSearchSuggest.attach(document);</script>
</body></html>`;

type Group = { label: string; count: number; more_url: string | null; items: { label: string; url: string }[] };

const item = (label: string) => ({ label, url: `/item/${label.toLowerCase().replace(/\W+/g, '-')}` });

const ANSWERS: Record<string, { groups: Group[]; all_url: string | null }> = {
  buf: {
    groups: [
      { label: 'Dictionary Word', count: 4, more_url: '/browse?type=dictionary_word', items: [item('buffalo'), item('bufflehead'), item('buffet')] },
      { label: 'Place', count: 1, more_url: null, items: [item('Buffalo Gap')] },
    ],
    all_url: '/browse?search_api_fulltext=buf',
  },
  'a&b': {
    groups: [{ label: 'Person & "Kin"', count: 2, more_url: '/browse?more', items: [item('Ann')] }],
    all_url: '/browse?all',
  },
  many: {
    groups: ['Digital Heritage', 'Dictionary Word', 'Place', 'Person', 'Collection'].map((label) => (
      { label, count: 9, more_url: `/browse?type=${label}`, items: [item(`${label} one`), item(`${label} two`), item(`${label} three`)] })),
    all_url: '/browse?search_api_fulltext=many',
  },
};

async function openFixture(page: Page) {
  await page.route(/^https:\/\/suggest\.test\/browse\/suggest/, (route) => {
    const q = new URL(route.request().url()).searchParams.get('q') ?? '';
    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(ANSWERS[q] ?? (q.startsWith('many') ? ANSWERS.many : { groups: [], all_url: '/browse' })),
    });
  });
  // Any page an option opens, and the form's own target.
  await page.route(/^https:\/\/suggest\.test\/(item\/|browse(\?|$))/, (route) =>
    route.fulfill({ status: 200, contentType: 'text/html', body: '<title>Opened</title>' }));
  await page.route('https://suggest.test/', (route) =>
    route.fulfill({ status: 200, contentType: 'text/html', body: FIXTURE }));
  await page.goto('https://suggest.test/');
}

const box = (page: Page) => page.getByRole('combobox', { name: 'Search' });
const list = (page: Page) => page.getByRole('listbox', { name: 'Search suggestions' });
const announced = (page: Page) => page.evaluate(() => (window as any).__announced as string[]);

test('items are grouped by content type, each group named by its heading', async ({ page }) => {
  await openFixture(page);
  await box(page).pressSequentially('buf');

  await expect(list(page)).toMatchAriaSnapshot(`
    - listbox "Search suggestions":
      - group "Dictionary Word":
        - text: Dictionary Word
        - option "buffalo"
        - option "bufflehead"
        - option "buffet"
        - option "More Dictionary Word results (4)"
      - group "Place":
        - text: Place
        - option "Buffalo Gap"
      - option "See all results for “buf”"
  `);
  // Counts the items, not the links to more.
  expect(await announced(page)).toEqual(['4 suggestions available.']);
});

test('arrow keys reach every option in order, and Enter opens it', async ({ page }) => {
  await openFixture(page);
  const input = box(page);
  await input.pressSequentially('buf');
  await expect(list(page)).toBeVisible();

  const visited: string[] = [];
  for (let i = 0; i < 7; i++) {
    await input.press('ArrowDown');
    const id = await input.getAttribute('aria-activedescendant');
    visited.push((await page.locator(`#${id}`).textContent())!);
  }
  expect(visited).toEqual([
    'buffalo', 'bufflehead', 'buffet', 'More Dictionary Word results (4)', 'Buffalo Gap', 'See all results for “buf”', 'buffalo',
  ]);

  await input.press('ArrowUp');
  await input.press('ArrowUp');
  await expect(page.locator(`#${await input.getAttribute('aria-activedescendant')}`)).toHaveText('Buffalo Gap');
  await input.press('Enter');
  await expect(page).toHaveURL('https://suggest.test/item/buffalo-gap');
});

test('the group and footer links open their searches', async ({ page }) => {
  await openFixture(page);
  await box(page).pressSequentially('buf');
  await list(page).getByRole('option', { name: 'More Dictionary Word results (4)' }).click();
  await expect(page).toHaveURL('https://suggest.test/browse?type=dictionary_word');

  await openFixture(page);
  await box(page).pressSequentially('buf');
  await list(page).getByRole('option', { name: 'See all results for “buf”' }).click();
  await expect(page).toHaveURL('https://suggest.test/browse?search_api_fulltext=buf');
});

test('Enter with nothing highlighted searches as before', async ({ page }) => {
  await openFixture(page);
  await box(page).pressSequentially('buf');
  await expect(list(page)).toBeVisible();
  await box(page).press('Enter');
  await expect(page).toHaveURL('https://suggest.test/browse?search_api_fulltext=buf');
});

test('typed text and type names are shown as text', async ({ page }) => {
  await openFixture(page);
  await box(page).pressSequentially('a&b');

  await expect(list(page).getByRole('option')).toHaveText([
    'Ann',
    'More Person & "Kin" results (2)',
    'See all results for “a&b”',
  ]);
});

test('with nothing found, no list opens', async ({ page }) => {
  await openFixture(page);
  await box(page).pressSequentially('zzq');
  await expect.poll(() => announced(page)).toEqual(['No suggestions.']);
  await expect(list(page)).toBeHidden();
  await expect(box(page)).toHaveAttribute('aria-expanded', 'false');
});

test('the list stays on screen on a narrow phone', async ({ page }) => {
  await page.setViewportSize({ width: 320, height: 640 });
  await openFixture(page);
  await box(page).pressSequentially('buf');
  await expect(list(page)).toBeVisible();

  const rect = (await list(page).boundingBox())!;
  expect(rect.x).toBeGreaterThanOrEqual(16);
  expect(rect.x + rect.width).toBeLessThanOrEqual(320 - 16);
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBe(320);
});

test('"See all results" stays in view while a long list scrolls', async ({ page }) => {
  await page.setViewportSize({ width: 1024, height: 600 });
  await openFixture(page);
  const input = box(page);
  await input.pressSequentially('many');
  await expect(list(page)).toBeVisible();

  const footer = list(page).getByRole('option', { name: 'See all results for “many”' });
  const inList = async (locator: ReturnType<Page['locator']>) => {
    const l = (await list(page).boundingBox())!;
    const o = (await locator.boundingBox())!;
    return o.y >= l.y - 1 && o.y + o.height <= l.y + l.height + 1;
  };
  expect(await list(page).evaluate((el) => el.scrollHeight > el.clientHeight)).toBe(true);
  expect(await inList(footer)).toBe(true);

  // Scrolled to with the keyboard, an option near the end isn't hidden
  // behind the pinned footer.
  for (let i = 0; i < 18; i++) {
    await input.press('ArrowDown');
  }
  const active = page.locator(`#${await input.getAttribute('aria-activedescendant')}`);
  await expect(active).toHaveText('Collection two');
  const a = (await active.boundingBox())!;
  const f = (await footer.boundingBox())!;
  expect(a.y + a.height).toBeLessThanOrEqual(f.y + 1);
  expect(await inList(footer)).toBe(true);
});

test('no option hides behind "See all results" when it wraps onto several lines', async ({ page }) => {
  await page.setViewportSize({ width: 320, height: 640 });
  await openFixture(page);
  const input = box(page);
  // The footer repeats what was typed, so a long search wraps it.
  await input.fill('many');
  await input.pressSequentially(' buffalo buffalo buffalo buffalo buffalo');
  await expect(list(page)).toBeVisible();

  const footer = list(page).locator('.mukurtu-search-suggest__option--all');
  const footerBox = (await footer.boundingBox())!;
  expect(footerBox.height).toBeGreaterThan(60);

  const count = await list(page).getByRole('option').count();
  for (let i = 0; i < count - 1; i++) {
    await input.press('ArrowDown');
    const active = page.locator(`#${await input.getAttribute('aria-activedescendant')}`);
    const a = (await active.boundingBox())!;
    const f = (await footer.boundingBox())!;
    expect(a.y + a.height, `${await active.textContent()} is behind the footer`).toBeLessThanOrEqual(f.y + 1);
  }
});
