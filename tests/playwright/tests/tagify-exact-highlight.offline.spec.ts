import { test, expect, Page } from '@playwright/test';
import path = require('path');

/**
 * On term fields that can create terms, Tagify highlighted the first
 * suggestion, so typing "Mary Jane" and pressing Enter added the existing
 * "Mary Jane Smith" instead of creating "Mary Jane" (#2661).
 * mukurtu-tagify-override.js now keeps the highlight only for an exact match.
 *
 * Runs the real Tagify library and override file, with the dropdown settings
 * copied from the Tagify module's js/tagify.js (vendor/ isn't installed in
 * the offline job). Drupal and once() are stubbed.
 */

const ROOT = path.join(__dirname, '../../..');
const TAGIFY_JS = path.join(ROOT, 'libraries/tagify/dist/tagify.js');
const TAGIFY_CSS = path.join(ROOT, 'libraries/tagify/dist/tagify.css');
const OVERRIDE_JS = path.join(ROOT, 'modules/mukurtu_core/js/mukurtu-tagify-override.js');

const TERMS = [
  { value: '1', label: 'Mary Jane Smith', entity_id: '1' },
  { value: '2', label: 'Mary Jones', entity_id: '2' },
];

async function setUp(page: Page, autocreate: boolean): Promise<void> {
  const classes = autocreate ? 'tagify-widget tagify--autocreate' : 'tagify-widget';
  await page.setContent(`<!DOCTYPE html><html><head><title>Tagify fixture</title></head><body>
    <label for="people">People</label>
    <input id="people" class="${classes}">
  </body></html>`);
  await page.addStyleTag({ path: TAGIFY_CSS });
  await page.addScriptTag({ path: TAGIFY_JS });
  await page.evaluate(() => {
    (window as any).Drupal = {
      behaviors: {},
      t: (text: string) => text,
      checkPlain: (text: string) => text,
    };
    (window as any).drupalSettings = {};
  });
  await page.addScriptTag({ path: OVERRIDE_JS });
  await page.evaluate(({ terms, autocreate }) => {
    const input = document.getElementById('people') as HTMLInputElement;
    // @ts-ignore Tagify is the global from tagify.js.
    new Tagify(input, {
      dropdown: {
        enabled: 1,
        highlightFirst: true,
        fuzzySearch: true,
        closeOnSelect: true,
        searchKeys: ['label', 'input'],
        mapValueTo: 'label',
      },
      whitelist: terms,
      tagTextProp: 'label',
      editTags: false,
      pasteAsTags: false,
      enforceWhitelist: !autocreate,
      skipInvalid: autocreate,
    });
    (window as any).Drupal.behaviors.mukurtuTagifyOverride.attach(document);
  }, { terms: TERMS, autocreate });
}

async function typeName(page: Page, name: string): Promise<void> {
  await page.locator('.tagify__input').click();
  await page.keyboard.type(name);
  await expect(page.locator('.tagify__dropdown')).toBeVisible();
}

function tags(page: Page): Promise<{ label: string, entity_id?: string }[]> {
  return page.evaluate(() => (document.getElementById('people') as any).__tagify.value
    .map((tag: any) => ({ label: tag.label ?? tag.value, entity_id: tag.entity_id })));
}

const active = (page: Page) => page.locator('.tagify__dropdown__item--active');

test.describe('Field that can create terms', () => {
  test.beforeEach(async ({ page }) => {
    await setUp(page, true);
  });

  test('Enter creates a name that is the start of an existing one', async ({ page }) => {
    await typeName(page, 'Mary Jane');
    await expect(active(page)).toHaveCount(0);
    await page.keyboard.press('Enter');
    expect(await tags(page)).toEqual([{ label: 'Mary Jane', entity_id: undefined }]);
  });

  test('Enter adds the existing term when its name is typed in full', async ({ page }) => {
    await typeName(page, 'mary jane smith');
    await expect(active(page)).toHaveText('Mary Jane Smith');
    await page.keyboard.press('Enter');
    expect(await tags(page)).toEqual([{ label: 'Mary Jane Smith', entity_id: '1' }]);
  });

  test('Arrow keys still pick a suggestion', async ({ page }) => {
    await typeName(page, 'Mary');
    await expect(active(page)).toHaveCount(0);
    await page.keyboard.press('ArrowDown');
    await expect(active(page)).toHaveText('Mary Jane Smith');
    await page.keyboard.press('Enter');
    expect(await tags(page)).toEqual([{ label: 'Mary Jane Smith', entity_id: '1' }]);
  });

  test('Clicking still picks a suggestion', async ({ page }) => {
    await typeName(page, 'Mary');
    await page.locator('.tagify__dropdown__item', { hasText: 'Mary Jones' }).click();
    expect(await tags(page)).toEqual([{ label: 'Mary Jones', entity_id: '2' }]);
  });
});

test('A field that cannot create terms still highlights the first suggestion', async ({ page }) => {
  await setUp(page, false);
  await typeName(page, 'Mary');
  await expect(active(page)).toHaveText('Mary Jane Smith');
  await page.keyboard.press('Enter');
  expect(await tags(page)).toEqual([{ label: 'Mary Jane Smith', entity_id: '1' }]);
});
