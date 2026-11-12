import { test, expect, Page } from '@playwright/test';
import fs = require('fs');
import path = require('path');

/**
 * The filter and the expand and collapse buttons on long vocabulary pages
 * (#2612).
 *
 * Runs the real vocabulary-page.js against a fixture mirroring
 * mukurtu-vocabulary-page.html.twig, because the shipped content has no
 * vocabulary long enough to show them. `once()` is reimplemented minimally, as
 * in multipage-announce.offline.spec.ts, because @drupal/core's copy lives
 * under the gitignored vendor/ directory.
 */

const REPO = path.join(__dirname, '../../..');
const SCRIPT = fs.readFileSync(path.join(REPO, 'modules/mukurtu_taxonomy/js/vocabulary-page.js'), 'utf8');
const STYLES = fs.readFileSync(path.join(REPO, 'modules/mukurtu_taxonomy/css/vocabulary-page.css'), 'utf8');

const DRUPAL_STUB = `
  window.Drupal = { behaviors: {} };
  // Like Drupal.t(): @placeholders are HTML-escaped.
  window.Drupal.t = function (str, args) {
    Object.keys(args || {}).forEach(function (key) {
      var value = String(args[key]).replace(/&/g, '&amp;').replace(/'/g, '&#039;').replace(/</g, '&lt;');
      str = str.split(key).join(value);
    });
    return str;
  };
  window.Drupal.formatPlural = function (count, one, many, args) {
    return (count === 1 ? one : many).replace('@count', count).replace('@total', args['@total']);
  };
  window.Drupal.debounce = function (fn) { return fn; };
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

const term = (name: string, nested = '') =>
  `<li data-vocabulary-term><a href="#" data-vocabulary-term-name>${name}</a>${nested}</li>`;
const list = (...terms: string[]) => `<ul class="vocabulary-page__list" role="list">${terms.join('')}</ul>`;

const fixture = (groups: string) => `<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>Location</title><style>${STYLES}</style></head>
<body>
  <div class="vocabulary-page" data-vocabulary-page>
    <div class="vocabulary-page__filter" data-vocabulary-filter hidden>
      <label for="vocabulary-filter">Filter terms</label>
      <input id="vocabulary-filter" type="search">
    </div>
    <p class="vocabulary-page__status" role="status" data-vocabulary-status></p>
    <nav aria-label="Jump to a letter" data-vocabulary-letters><a href="#vocabulary-letter-1">A</a></nav>
    ${groups}
  </div>
</body></html>`;

const card = (heading: string, nested = '') =>
  `<section data-vocabulary-group><h2><a href="#" data-vocabulary-card-term>${heading}</a></h2>${nested}</section>`;

const NESTED = `
  ${card('Alaska')}
  ${card("O'Brien County", list(term('Town', list(term('Street')))))}
  ${card('Washington', list(term('Spokane', list(term('Hillyard'))), term('Seattle')))}`;

const FLAT = `<section data-vocabulary-group><h2>A</h2>${list(term('Ants'), term('Élan'))}</section>`;

async function load(page: Page, groups: string) {
  await page.setContent(fixture(groups));
  await page.addScriptTag({ content: DRUPAL_STUB });
  await page.addScriptTag({ content: SCRIPT });
  await page.evaluate(() => (window as any).Drupal.behaviors.mukurtuVocabularyPage.attach(document));
}

const visibleTerms = (page: Page) =>
  page.locator('[data-vocabulary-card-term]:visible, [data-vocabulary-term-name]:visible').allTextContents();

test.describe('Vocabulary page script', () => {
  test('cards stay open, and nested parents start closed', async ({ page }) => {
    await load(page, NESTED);
    expect(await visibleTerms(page)).toEqual(['Alaska', "O'Brien County", 'Town', 'Washington', 'Spokane', 'Seattle']);

    const spokane = page.getByRole('button', { name: 'Show terms under Spokane' });
    await expect(spokane).toHaveAttribute('aria-expanded', 'false');
    await spokane.click();

    expect(await visibleTerms(page)).toContain('Hillyard');
    const open = page.getByRole('button', { name: 'Hide terms under Spokane' });
    await expect(open).toHaveAttribute('aria-expanded', 'true');
    await expect(open).toBeFocused();
  });

  test('button names show plain text, not escaped text', async ({ page }) => {
    await load(page, `<section data-vocabulary-group><h2>O</h2>${list(term("O'Brien County", list(term('Town'))))}</section>`);
    await expect(page.getByRole('button', { name: "Show terms under O'Brien County" })).toBeVisible();
  });

  test('Expand all and Collapse all change every branch', async ({ page }) => {
    await load(page, NESTED);
    await page.getByRole('button', { name: 'Expand all' }).click();
    expect(await visibleTerms(page)).toContain('Hillyard');
    await page.getByRole('button', { name: 'Collapse all' }).click();
    expect(await visibleTerms(page)).not.toContain('Hillyard');
    expect(await visibleTerms(page)).toContain('Spokane');
  });

  test('filtering opens matching branches, and clearing restores them', async ({ page }) => {
    await load(page, NESTED);
    await page.getByRole('button', { name: 'Show terms under Town' }).click();

    await page.getByLabel('Filter terms').fill('hill');
    expect(await visibleTerms(page)).toEqual(['Washington', 'Spokane', 'Hillyard']);
    await expect(page.locator('[data-vocabulary-status]')).toHaveText('1 of 8 terms matches.');
    await expect(page.getByRole('button', { name: 'Expand all' })).toBeHidden();
    await expect(page.locator('[data-vocabulary-letters]')).toBeHidden();
    await expect(page.locator('.vocabulary-page__toggle:visible')).toHaveCount(0);

    await page.getByLabel('Filter terms').fill('');
    expect(await visibleTerms(page)).toEqual(['Alaska', "O'Brien County", 'Town', 'Street', 'Washington', 'Spokane', 'Seattle']);
    await expect(page.locator('[data-vocabulary-status]')).toBeEmpty();
    await expect(page.getByRole('button', { name: 'Expand all' })).toBeVisible();
  });

  test('a card heading can match by itself, with no empty list exposed', async ({ page }) => {
    await load(page, NESTED);
    await page.getByLabel('Filter terms').fill('washington');
    expect(await visibleTerms(page)).toEqual(['Washington']);
    await expect(page.locator('[data-vocabulary-status]')).toHaveText('1 of 8 terms matches.');
    await expect(page.locator('ul:visible:not(:has(li:visible))')).toHaveCount(0);
  });

  test('a card with no list of its own still filters', async ({ page }) => {
    await load(page, NESTED);
    await page.getByLabel('Filter terms').fill('alaska');
    expect(await visibleTerms(page)).toEqual(['Alaska']);
  });

  test('a flat vocabulary has no expand controls, and accents are ignored', async ({ page }) => {
    await load(page, FLAT);
    await expect(page.locator('.vocabulary-page__toggle')).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Expand all' })).toHaveCount(0);

    await page.getByLabel('Filter terms').fill('elan');
    expect(await visibleTerms(page)).toEqual(['Élan']);
  });

  test('a query with no matches hides every card', async ({ page }) => {
    await load(page, NESTED);
    await page.getByLabel('Filter terms').fill('zzz');
    await expect(page.locator('[data-vocabulary-group]:visible')).toHaveCount(0);
    await expect(page.locator('[data-vocabulary-status]')).toHaveText('0 of 8 terms match.');
  });
});

test.describe('Long letter cards', () => {
  // Letter cards as mukurtu-vocabulary-page.html.twig renders them: the
  // heading has an id and no link.
  const letterCard = (id: number, letter: string, count: number) =>
    `<section data-vocabulary-group><h2 id="vocabulary-letter-${id}">${letter}</h2>${list(
      ...Array.from({ length: count }, (_, i) => term(`${letter}${String(i + 1).padStart(2, '0')}`)),
    )}</section>`;
  const LONG = letterCard(1, 'A', 14) + letterCard(2, 'B', 10);

  test('show their first 10 terms and a See more button', async ({ page }) => {
    await load(page, LONG);
    const terms = await visibleTerms(page);
    expect(terms.filter((name) => name.startsWith('A'))).toHaveLength(10);
    expect(terms.filter((name) => name.startsWith('B'))).toHaveLength(10);
    const more = page.getByRole('button', { name: 'See more' });
    await expect(more).toHaveCount(1);
    await expect(more).toHaveAttribute('aria-describedby', 'vocabulary-letter-1');
  });

  test('See more shows the rest and moves focus to the first one', async ({ page }) => {
    await load(page, LONG);
    await page.getByRole('button', { name: 'See more' }).click();
    expect((await visibleTerms(page)).filter((name) => name.startsWith('A'))).toHaveLength(14);
    await expect(page.getByRole('link', { name: 'A11' })).toBeFocused();
    await expect(page.getByRole('button', { name: 'See more' })).toHaveCount(0);
  });

  test('filtering finds hidden terms, and clearing shortens the card again', async ({ page }) => {
    await load(page, LONG);
    const filter = page.getByLabel('Filter terms');
    await filter.fill('a13');
    expect(await visibleTerms(page)).toEqual(['A13']);
    await expect(page.getByRole('button', { name: 'See more' })).toBeHidden();

    await filter.fill('');
    expect((await visibleTerms(page)).filter((name) => name.startsWith('A'))).toHaveLength(10);
    await expect(page.getByRole('button', { name: 'See more' })).toBeVisible();
  });

  test('an opened card stays open after filtering', async ({ page }) => {
    await load(page, LONG);
    await page.getByRole('button', { name: 'See more' }).click();
    const filter = page.getByLabel('Filter terms');
    await filter.fill('a0');
    await filter.fill('');
    expect((await visibleTerms(page)).filter((name) => name.startsWith('A'))).toHaveLength(14);
  });

  test('cards in a nested vocabulary are left whole', async ({ page }) => {
    await load(page, card('Washington', list(...Array.from({ length: 12 }, (_, i) => term(`Town ${i + 1}`)))));
    expect(await visibleTerms(page)).toHaveLength(13);
    await expect(page.getByRole('button', { name: 'See more' })).toHaveCount(0);
  });
});
