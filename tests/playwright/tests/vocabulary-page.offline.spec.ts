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

const NESTED = `
  <section data-vocabulary-group><h2>A</h2>${list(term('Alaska'))}</section>
  <section data-vocabulary-group><h2>O</h2>${list(term("O'Brien County", list(term('Town'))))}</section>
  <section data-vocabulary-group><h2>W</h2>${list(
    term('Washington', list(term('Spokane', list(term('Hillyard'))), term('Seattle'))),
  )}</section>`;

const FLAT = `<section data-vocabulary-group><h2>A</h2>${list(term('Ants'), term('Élan'))}</section>`;

async function load(page: Page, groups: string) {
  await page.setContent(fixture(groups));
  await page.addScriptTag({ content: DRUPAL_STUB });
  await page.addScriptTag({ content: SCRIPT });
  await page.evaluate(() => (window as any).Drupal.behaviors.mukurtuVocabularyPage.attach(document));
}

const visibleTerms = (page: Page) =>
  page.locator('[data-vocabulary-term-name]:visible').allTextContents();

test.describe('Vocabulary page script', () => {
  test('parents start closed, and a button opens one level', async ({ page }) => {
    await load(page, NESTED);
    expect(await visibleTerms(page)).toEqual(['Alaska', "O'Brien County", 'Washington']);

    const washington = page.getByRole('button', { name: 'Show terms under Washington' });
    await expect(washington).toHaveAttribute('aria-expanded', 'false');
    await washington.click();

    expect(await visibleTerms(page)).toEqual(['Alaska', "O'Brien County", 'Washington', 'Spokane', 'Seattle']);
    const open = page.getByRole('button', { name: 'Hide terms under Washington' });
    await expect(open).toHaveAttribute('aria-expanded', 'true');
    await expect(open).toBeFocused();
  });

  test('button names show plain text, not escaped text', async ({ page }) => {
    await load(page, NESTED);
    await expect(page.getByRole('button', { name: "Show terms under O'Brien County" })).toBeVisible();
  });

  test('Expand all and Collapse all change every branch', async ({ page }) => {
    await load(page, NESTED);
    await page.getByRole('button', { name: 'Expand all' }).click();
    expect(await visibleTerms(page)).toContain('Hillyard');
    await page.getByRole('button', { name: 'Collapse all' }).click();
    expect(await visibleTerms(page)).toEqual(['Alaska', "O'Brien County", 'Washington']);
  });

  test('filtering opens matching branches, and clearing restores them', async ({ page }) => {
    await load(page, NESTED);
    await page.getByRole('button', { name: 'Show terms under Washington' }).click();

    await page.getByLabel('Filter terms').fill('hill');
    expect(await visibleTerms(page)).toEqual(['Washington', 'Spokane', 'Hillyard']);
    await expect(page.locator('[data-vocabulary-status]')).toHaveText('1 of 7 terms matches.');
    await expect(page.getByRole('button', { name: 'Expand all' })).toBeHidden();
    await expect(page.locator('[data-vocabulary-letters]')).toBeHidden();
    await expect(page.locator('.vocabulary-page__toggle:visible')).toHaveCount(0);

    await page.getByLabel('Filter terms').fill('');
    expect(await visibleTerms(page)).toEqual(['Alaska', "O'Brien County", 'Washington', 'Spokane', 'Seattle']);
    await expect(page.locator('[data-vocabulary-status]')).toBeEmpty();
    await expect(page.getByRole('button', { name: 'Expand all' })).toBeVisible();
  });

  test('no empty nested list stays exposed when only a parent matches', async ({ page }) => {
    await load(page, NESTED);
    await page.getByLabel('Filter terms').fill('washington');
    expect(await visibleTerms(page)).toEqual(['Washington']);
    await expect(page.locator('ul:visible:not(:has(li:visible))')).toHaveCount(0);
  });

  test('a flat vocabulary has no expand controls, and accents are ignored', async ({ page }) => {
    await load(page, FLAT);
    await expect(page.locator('.vocabulary-page__toggle')).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Expand all' })).toHaveCount(0);

    await page.getByLabel('Filter terms').fill('elan');
    expect(await visibleTerms(page)).toEqual(['Élan']);
  });

  test('a query with no matches hides every group', async ({ page }) => {
    await load(page, NESTED);
    await page.getByLabel('Filter terms').fill('zzz');
    await expect(page.locator('[data-vocabulary-group]:visible')).toHaveCount(0);
    await expect(page.locator('[data-vocabulary-status]')).toHaveText('0 of 7 terms match.');
  });
});
