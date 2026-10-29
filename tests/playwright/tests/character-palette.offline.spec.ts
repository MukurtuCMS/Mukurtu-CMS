import { test, expect, Page } from '@playwright/test';
import path = require('path');

/**
 * The character palette's button and panel (#2560).
 *
 * Offline: the page is a stand-in served from a fake origin, so browser
 * storage works, with minimal Drupal, once() and drupalSettings stand-ins and
 * the module's real character-palette.js and CSS. Which inputs get the
 * palette is decided server side and covered by CharacterPaletteTest.
 */

const MODULE_DIR = path.join(__dirname, '../../../modules/mukurtu_core');
const ORIGIN = 'http://palette.test';

const GROUPS = [
  {
    label: 'Tlingit',
    characters: [
      { character: 'x̱ʼ', name: 'x underbar ejective' },
      { character: 'ḵ', name: 'k underbar' },
      { character: 'ʼ', name: '' },
    ],
  },
  { label: 'Osage', characters: [{ character: '𐒰', name: 'Osage capital A' }] },
];

// Just enough of Drupal for the script: Drupal.t() escapes @ placeholders
// like the real one, and Drupal.announce() writes HTML into a live region.
const STUBS = `
  window.Drupal = {
    behaviors: {},
    t(str, args) {
      return str.replace(/@\\w+/g, (key) => String((args || {})[key]).replace(/[&<>"']/g, (c) => '&#' + c.charCodeAt(0) + ';'));
    },
    announce(text) { document.getElementById('announce').innerHTML = text; },
  };
  window.once = (id, selector, context) => [...context.querySelectorAll(selector)].filter((el) => {
    const ids = (el.dataset.once || '').split(' ');
    if (ids.includes(id)) { return false; }
    el.dataset.once = (el.dataset.once ? el.dataset.once + ' ' : '') + id;
    return true;
  });
`;

function fixture(groups: typeof GROUPS): string {
  return `<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Character palette fixture</title></head><body>
  <label for="term">Term</label>
  <input id="term" name="term" type="text" data-mukurtu-character-palette="Term">
  <label for="hidden-data">Map data</label>
  <textarea id="hidden-data" name="hidden-data" style="display:none" data-mukurtu-character-palette="Map data"></textarea>
  <div id="announce" aria-live="polite"></div>
  <script>${STUBS} window.drupalSettings = {mukurtuCharacterPalette: {groups: ${JSON.stringify(groups)}}};</script>
</body></html>`;
}

async function load(page: Page, groups = GROUPS): Promise<void> {
  await page.route(`${ORIGIN}/**`, (route) => route.fulfill({ contentType: 'text/html; charset=utf-8', body: fixture(groups) }));
  await page.goto(`${ORIGIN}/`);
  await page.addStyleTag({ path: path.join(MODULE_DIR, 'css/character-palette.css') });
  await page.addScriptTag({ path: path.join(MODULE_DIR, 'js/character-palette.js') });
  await page.evaluate(() => (window as any).Drupal.behaviors.mukurtuCharacterPalette.attach(document));
}

test.describe('Character palette', () => {
  test('names its button after the field and starts closed', async ({ page }) => {
    await load(page);
    const toggle = page.getByRole('button', { name: 'Characters for Term' });
    await expect(toggle).toHaveText('Characters');
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(page.getByRole('group', { name: 'Character palette' })).toBeHidden();
  });

  test('skips an input that is hidden on purpose', async ({ page }) => {
    await load(page);
    await expect(page.locator('.mukurtu-character-palette__toggle')).toHaveCount(1);
  });

  test('works from the keyboard alone', async ({ page }) => {
    await load(page);
    const term = page.locator('#term');
    await term.fill('aabb');
    await term.evaluate((el: HTMLInputElement) => el.setSelectionRange(2, 2));

    await page.keyboard.press('Tab');
    const toggle = page.getByRole('button', { name: 'Characters for Term' });
    await expect(toggle).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');

    // Language menu, then the first character.
    await page.keyboard.press('Tab');
    await expect(page.getByLabel('Language')).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(page.getByRole('button', { name: 'x underbar ejective' })).toBeFocused();
    await page.keyboard.press('Enter');
    await page.keyboard.press('Tab');
    await page.keyboard.press('Enter');

    // Both land where the caret was, in order, and focus stays in the panel.
    await expect(term).toHaveValue('aax̱ʼḵbb');
    await expect(page.getByRole('button', { name: 'k underbar' })).toBeFocused();
    await expect(page.locator('#announce')).toHaveText('Inserted k underbar.');

    await page.keyboard.press('Escape');
    await expect(term).toBeFocused();
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
  });

  test('a character with no name is read as itself', async ({ page }) => {
    await load(page);
    await page.getByRole('button', { name: 'Characters for Term' }).click();
    const plain = page.locator('.mukurtu-character-palette__character', { hasText: 'ʼ' }).last();
    await expect(plain).not.toHaveAttribute('aria-label', /.*/);
    await plain.click();
    await expect(page.locator('#announce')).toHaveText('Inserted ʼ.');
  });

  test('a mouse click keeps focus and the caret in the field', async ({ page }) => {
    await load(page);
    const term = page.locator('#term');
    await page.getByRole('button', { name: 'Characters for Term' }).click();
    await term.fill('ab');
    await term.evaluate((el: HTMLInputElement) => el.setSelectionRange(1, 1));
    await page.getByRole('button', { name: 'k underbar' }).click();
    await expect(term).toBeFocused();
    await expect(term).toHaveValue('aḵb');
    expect(await term.evaluate((el: HTMLInputElement) => el.selectionStart)).toBe(2);
  });

  test('fires input so other scripts see the change', async ({ page }) => {
    await load(page);
    await page.locator('#term').evaluate((el) => {
      el.addEventListener('input', () => { (window as any).heardInput = true; });
    });
    await page.getByRole('button', { name: 'Characters for Term' }).click();
    await page.getByRole('button', { name: 'k underbar' }).click();
    expect(await page.evaluate(() => (window as any).heardInput)).toBe(true);
  });

  test('switches language and remembers it and the open panel', async ({ page }) => {
    await load(page);
    await page.getByRole('button', { name: 'Characters for Term' }).click();
    await page.getByLabel('Language').selectOption({ label: 'Osage' });
    await expect(page.getByRole('button', { name: 'Osage capital A' })).toBeVisible();

    await load(page);
    await expect(page.getByRole('button', { name: 'Characters for Term' })).toHaveAttribute('aria-expanded', 'true');
    await expect(page.getByLabel('Language')).toHaveValue('1');
  });

  test('has no language menu with only one language', async ({ page }) => {
    await load(page, [GROUPS[0]]);
    await page.getByRole('button', { name: 'Characters for Term' }).click();
    await expect(page.getByLabel('Language')).toHaveCount(0);
  });

  test('buttons are at least 24 by 24 pixels', async ({ page }) => {
    await load(page);
    await page.getByRole('button', { name: 'Characters for Term' }).click();
    for (const button of await page.locator('.mukurtu-character-palette__toggle, .mukurtu-character-palette__character').all()) {
      const box = (await button.boundingBox())!;
      expect(box.width).toBeGreaterThanOrEqual(24);
      expect(box.height).toBeGreaterThanOrEqual(24);
    }
  });
});
