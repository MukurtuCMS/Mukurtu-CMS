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

// A stand-in for a CKEditor 5 instance on the "Definition" textarea: core's
// registry, the editor's DOM, and the parts of its API the palette uses.
const EDITOR = `
  <div class="js-form-item">
    <label for="definition">Definition</label>
    <textarea id="definition" name="definition" data-ckeditor5-id="ed1" style="display:none" data-mukurtu-character-palette="Definition"></textarea>
    <div class="ck-editor" style="position:relative"><div class="ck-editor__top"><div class="ck-toolbar">Toolbar</div></div>
      <div class="ck-editor__editable" contenteditable="true" tabindex="0">aabb</div></div>
  </div>
  <select data-editor-for="definition"><option>Basic HTML</option></select>
  <script>
    window.inserted = [];
    const editable = document.querySelector('.ck-editor__editable');
    Drupal.CKEditor5Instances = new Map([['ed1', {
      ui: {view: {editable: {element: editable}}},
      editing: {view: {focus() { editable.focus(); }}},
      model: {
        change(callback) { callback({createText: (text) => ({text})}); },
        insertContent(node) { window.inserted.push(node.text); },
      },
    }]]);
  </script>`;

// The theme's header search: a form marked as the palette's anchor, so the
// panel floats over the page instead of sitting in the layout.
const HEADER = `
  <form data-mukurtu-character-palette-anchor role="search" aria-label="Search">
    <input name="search_api_fulltext" type="search" aria-label="Search" data-mukurtu-character-palette="Search">
    <button type="submit">Go</button>
  </form>`;

// A stand-in for a Tagify field: its contenteditable box directly before the
// original input, an instance on the input, and Tagify's habit of turning
// typed text into a tag when the box loses focus.
const TAGIFY = `
  <span class="tagify" id="kw-scope"><span class="tagify__input" contenteditable="true" tabindex="0" role="textbox" aria-label="Keywords" style="display:inline-block;min-width:10em;min-height:1.5em"></span></span>
  <input id="kw" name="field_keywords" class="tagify-widget" tabindex="-1" style="position:absolute;left:-9999px" data-mukurtu-character-palette="Keywords">
  <script>
    (function () {
      const input = document.getElementById('kw');
      const scope = document.getElementById('kw-scope');
      const box = scope.querySelector('.tagify__input');
      window.tagged = [];
      window.tagifyInputs = 0;
      const tagify = {
        DOM: {input: box, scope: scope},
        settings: {addTagOnBlur: true, addTagOn: ['blur', 'enter']},
        addTags(text) { window.tagged.push(text); box.textContent = ''; },
        dropdown: {hide() {}},
        on() {},
      };
      box.addEventListener('blur', () => {
        const text = box.textContent.trim();
        if (text && tagify.settings.addTagOnBlur) { tagify.addTags(text); }
      });
      box.addEventListener('input', () => { window.tagifyInputs += 1; });
      input.__tagify = tagify;
    })();
  </script>`;

interface FixtureOptions {
  header?: boolean;
  tagify?: boolean;
}

function fixture(groups: typeof GROUPS, { header = false, tagify = false }: FixtureOptions = {}): string {
  return `<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Character palette fixture</title></head><body>
  ${header ? HEADER : ''}
  <label for="term">Term</label>
  <input id="term" name="term" type="text" data-mukurtu-character-palette="Term">
  <label for="hidden-data">Map data</label>
  <textarea id="hidden-data" name="hidden-data" style="display:none" data-mukurtu-character-palette="Map data"></textarea>
  <div id="announce" aria-live="polite"></div>
  <script>${STUBS}</script>
  ${EDITOR}
  ${tagify ? TAGIFY : ''}
  <script>window.drupalSettings = {mukurtuCharacterPalette: {groups: ${JSON.stringify(groups)}}};</script>
</body></html>`;
}

/** The Term field's panel, so locators skip the editor's. */
async function termPanel(page: Page) {
  const id = await page.getByRole('button', { name: 'Characters for Term' }).getAttribute('aria-controls');
  return page.locator('#' + id);
}

async function load(page: Page, groups = GROUPS, options: FixtureOptions = {}): Promise<void> {
  await page.unrouteAll();
  await page.route(`${ORIGIN}/**`, (route) => route.fulfill({ contentType: 'text/html; charset=utf-8', body: fixture(groups, options) }));
  await page.goto(`${ORIGIN}/`);
  await page.addStyleTag({ path: path.join(MODULE_DIR, 'css/character-palette.css') });
  await page.addScriptTag({ path: path.join(MODULE_DIR, 'js/character-palette.js') });
  await page.evaluate(() => (window as any).Drupal.behaviors.mukurtuCharacterPalette.attach(document));
}

test.describe('Character palette', () => {
  test('names its button after the field and starts closed', async ({ page }) => {
    await load(page);
    const toggle = page.getByRole('button', { name: 'Characters for Term' });
    // An icon, with its name for screen readers and a tooltip for the mouse.
    await expect(toggle).toHaveAttribute('title', 'Characters');
    await expect(toggle.locator('[aria-hidden="true"]')).toHaveText('\u00C1\u0331');
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(page.getByRole('group', { name: 'Character palette' })).toBeHidden();
  });

  test('skips an input that is hidden on purpose', async ({ page }) => {
    await load(page);
    await expect(page.locator('.mukurtu-character-palette__toggle')).toHaveCount(2);
    await expect(page.getByRole('button', { name: 'Characters for Map data' })).toHaveCount(0);
  });

  test('sits beside a plain field', async ({ page }) => {
    await load(page);
    const term = (await page.locator('#term').boundingBox())!;
    const toggle = (await page.getByRole('button', { name: 'Characters for Term' }).boundingBox())!;
    expect(toggle.x).toBeGreaterThan(term.x + term.width - 1);
    expect(Math.abs((toggle.y + toggle.height / 2) - (term.y + term.height / 2))).toBeLessThan(2);
  });

  test('sits in a rich text editor and inserts through it (#2561)', async ({ page }) => {
    await load(page);
    const toggle = page.getByRole('button', { name: 'Characters for Definition' });
    await expect(page.locator('.ck-editor__top > .mukurtu-character-palette__toggle')).toHaveCount(1);

    // A mouse click leaves focus, and the editor's own selection, in the editor.
    await page.locator('.ck-editor__editable').focus();
    await toggle.click();
    await page.locator('.ck-editor__editable').focus();
    const panel = page.locator('#' + await toggle.getAttribute('aria-controls'));
    await panel.getByRole('button', { name: 'k underbar' }).click();
    await expect(page.locator('.ck-editor__editable')).toBeFocused();
    expect(await page.evaluate(() => (window as any).inserted)).toEqual(['ḵ']);
    await expect(page.locator('#announce')).toHaveText('Inserted k underbar.');

    // Escape from the panel goes back to the editor, not the hidden textarea.
    await panel.getByRole('button', { name: 'k underbar' }).focus();
    await page.keyboard.press('Escape');
    await expect(page.locator('.ck-editor__editable')).toBeFocused();
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
    await expect((await termPanel(page)).getByLabel('Language')).toBeFocused();
    await page.keyboard.press('Tab');
    await expect((await termPanel(page)).getByRole('button', { name: 'x underbar ejective' })).toBeFocused();
    await page.keyboard.press('Enter');
    await page.keyboard.press('Tab');
    await page.keyboard.press('Enter');

    // Both land where the caret was, in order, and focus stays in the panel.
    await expect(term).toHaveValue('aax̱ʼḵbb');
    await expect((await termPanel(page)).getByRole('button', { name: 'k underbar' })).toBeFocused();
    await expect(page.locator('#announce')).toHaveText('Inserted k underbar.');

    await page.keyboard.press('Escape');
    await expect(term).toBeFocused();
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
  });

  test('a character with no name is read as itself', async ({ page }) => {
    await load(page);
    await page.getByRole('button', { name: 'Characters for Term' }).click();
    const plain = (await termPanel(page)).locator('.mukurtu-character-palette__character', { hasText: 'ʼ' }).last();
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
    await (await termPanel(page)).getByRole('button', { name: 'k underbar' }).click();
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
    await (await termPanel(page)).getByRole('button', { name: 'k underbar' }).click();
    expect(await page.evaluate(() => (window as any).heardInput)).toBe(true);
  });

  test('switches language and remembers it and the open panel', async ({ page }) => {
    await load(page);
    await page.getByRole('button', { name: 'Characters for Term' }).click();
    await (await termPanel(page)).getByLabel('Language').selectOption({ label: 'Osage' });
    await expect((await termPanel(page)).getByRole('button', { name: 'Osage capital A' })).toBeVisible();

    await load(page);
    await expect(page.getByRole('button', { name: 'Characters for Term' })).toHaveAttribute('aria-expanded', 'true');
    await expect((await termPanel(page)).getByLabel('Language')).toHaveValue('1');
  });

  test('has no language menu with only one language', async ({ page }) => {
    await load(page, [GROUPS[0]]);
    await page.getByRole('button', { name: 'Characters for Term' }).click();
    await expect((await termPanel(page)).getByLabel('Language')).toHaveCount(0);
  });

  test('a panel over the page closes when focus or a click leaves it, and never reopens by itself', async ({ page }) => {
    await load(page, GROUPS, { header: true });
    const toggle = page.getByRole('button', { name: 'Characters for Search' });
    const search = page.locator('input[name="search_api_fulltext"]');
    await expect(toggle.locator('xpath=..')).toHaveAttribute('role', 'search');

    // Inserting keeps it open; focus moving on to the next field closes it.
    await toggle.click();
    const panel = page.locator('#' + await toggle.getAttribute('aria-controls'));
    await panel.getByRole('button', { name: 'k underbar' }).click();
    await expect(search).toHaveValue('ḵ');
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await page.locator('#term').focus();
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');

    await toggle.click();
    await page.mouse.click(5, 5);
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');

    // Left open, it still starts closed on the next page, and doesn't open
    // another field with the same name.
    await toggle.click();
    await load(page, GROUPS, { header: true });
    await expect(page.getByRole('button', { name: 'Characters for Search' })).toHaveAttribute('aria-expanded', 'false');
    expect(await page.evaluate(() => localStorage.getItem('mukurtu.characterPalette.open') ?? '')).not.toContain('search_api_fulltext');
  });

  test('sits beside a Tagify box and types into it (#2561)', async ({ page }) => {
    await load(page, GROUPS, { tagify: true });
    const box = page.locator('#kw-scope .tagify__input');
    const toggle = page.getByRole('button', { name: 'Characters for Keywords' });
    expect(await toggle.evaluate((t) => t.parentElement.contains(document.getElementById('kw-scope')))).toBe(true);

    // Half a word, caret in the middle, then over to the palette by keyboard.
    await box.click();
    await page.keyboard.type('ab');
    await page.keyboard.press('ArrowLeft');
    await page.keyboard.press('Tab');
    await expect(toggle).toBeFocused();
    expect(await page.evaluate(() => (window as any).tagged)).toEqual([]);

    await page.keyboard.press('Enter');
    const panel = page.locator('#' + await toggle.getAttribute('aria-controls'));
    await panel.getByRole('button', { name: 'k underbar' }).focus();
    await page.keyboard.press('Enter');
    await expect(box).toHaveText('aḵb');
    expect(await page.evaluate(() => (window as any).tagifyInputs)).toBeGreaterThan(0);
    expect(await page.evaluate(() => (window as any).tagged)).toEqual([]);

    // Escape goes back to the box, still not a tag.
    await page.keyboard.press('Escape');
    await expect(box).toBeFocused();
    expect(await page.evaluate(() => (window as any).tagged)).toEqual([]);
  });

  test('a Tagify word becomes a tag when focus leaves the palette for elsewhere', async ({ page }) => {
    await load(page, GROUPS, { tagify: true });
    const box = page.locator('#kw-scope .tagify__input');
    const toggle = page.getByRole('button', { name: 'Characters for Keywords' });
    await box.click();
    await page.keyboard.type('cc');
    await page.keyboard.press('Tab');
    await page.keyboard.press('Enter');
    const panel = page.locator('#' + await toggle.getAttribute('aria-controls'));
    await panel.getByRole('button', { name: 'k underbar' }).focus();
    await page.keyboard.press('Enter');
    expect(await page.evaluate(() => (window as any).tagged)).toEqual([]);

    await page.locator('#term').focus();
    expect(await page.evaluate(() => (window as any).tagged)).toEqual(['ccḵ']);
  });

  test('buttons are at least 24 by 24 pixels', async ({ page }) => {
    await load(page);
    await page.getByRole('button', { name: 'Characters for Term' }).click();
    for (const button of await page.locator('.mukurtu-character-palette__toggle:visible, .mukurtu-character-palette__character:visible').all()) {
      const box = (await button.boundingBox())!;
      expect(box.width).toBeGreaterThanOrEqual(24);
      expect(box.height).toBeGreaterThanOrEqual(24);
    }
  });
});
