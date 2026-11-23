import { test, expect, Page } from '@playwright/test';
import path = require('path');

/**
 * The character palette (#2560, #2561): one button in a corner of the page.
 *
 * Offline: the page is a stand-in served from a fake origin, so browser
 * storage works, with minimal Drupal, once() and drupalSettings stand-ins,
 * stand-in CKEditor and Tagify instances, and the module's real
 * character-palette.js and CSS. Which inputs get the palette is decided
 * server side and covered by CharacterPaletteTest.
 *
 * Simulated key presses never reach the operating system, so these tests
 * can't show that a shortcut is free on a real machine. That has to be tried
 * by hand: macOS keeps Ctrl+Shift+Space for itself, and only real keys show it.
 */

const MODULE_DIR = path.join(__dirname, '../../../modules/mukurtu_core');
const TAGIFY_DIR = path.join(__dirname, '../../../libraries/tagify/dist');
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

// A stand-in for a CKEditor 5 instance on the "Definition" textarea.
const EDITOR = `
  <div class="js-form-item">
    <label for="definition">Definition</label>
    <textarea id="definition" name="definition" data-ckeditor5-id="ed1" style="display:none" data-mukurtu-character-palette="Definition"></textarea>
    <div class="ck-editor"><div class="ck-editor__editable" contenteditable="true" tabindex="0" style="min-height:2em">aabb</div></div>
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

// A stand-in for a Tagify field, including its habit of turning typed text
// into a tag when the box loses focus.
const TAGIFY = `
  <span class="tagify" id="kw-scope"><span class="tagify__input" contenteditable="true" tabindex="0" role="textbox" aria-label="Keywords" style="display:inline-block;min-width:10em;min-height:1.5em"></span></span>
  <input id="kw" name="field_keywords" class="tagify-widget" tabindex="-1" style="position:absolute;left:-9999px" data-mukurtu-character-palette="Keywords">
  <script>
    (function () {
      const input = document.getElementById('kw');
      const scope = document.getElementById('kw-scope');
      const box = scope.querySelector('.tagify__input');
      window.tagged = [];
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
      input.__tagify = tagify;
    })();
  </script>`;

function fixture(groups: typeof GROUPS): string {
  return `<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Character palette fixture</title></head><body>
  <style>.visually-hidden { position: absolute !important; clip: rect(1px, 1px, 1px, 1px); overflow: hidden; height: 1px; width: 1px; }</style>
  <div id="announce" aria-live="polite"></div>
  <script>${STUBS}</script>
  <label for="term">Term</label>
  <input id="term" name="term" type="text" data-mukurtu-character-palette="Term">
  <label for="source">Source</label>
  <input id="source" name="source" type="text" data-mukurtu-character-palette="Source">
  <label for="plain">Not marked</label>
  <input id="plain" name="plain" type="text">
  <label for="under">Under the panel</label>
  <input id="under" name="under" type="text" style="position:fixed;right:3rem;bottom:10rem" data-mukurtu-character-palette>
  ${EDITOR}
  ${TAGIFY}
  <script>window.drupalSettings = {mukurtuCharacterPalette: {groups: ${JSON.stringify(groups)}}};</script>
</body></html>`;
}

// The platform is set so the shortcut under test doesn't depend on the
// machine running the suite.
async function load(page: Page, groups = GROUPS, platform = 'Linux x86_64'): Promise<void> {
  await page.addInitScript((value) => {
    Object.defineProperty(navigator, 'platform', { get: () => value, configurable: true });
  }, platform);
  await page.unrouteAll();
  await page.route(`${ORIGIN}/**`, (route) => route.fulfill({ contentType: 'text/html; charset=utf-8', body: fixture(groups) }));
  await page.goto(`${ORIGIN}/`);
  await page.addStyleTag({ path: path.join(MODULE_DIR, 'css/character-palette.css') });
  await page.addScriptTag({ path: path.join(MODULE_DIR, 'js/character-palette.js') });
  await page.evaluate(() => (window as any).Drupal.behaviors.mukurtuCharacterPalette.attach(document));
}

// Adds a field run by the real Tagify library, for behavior a stand-in can't
// show: Tagify keeps its own idea of whether it has focus.
async function addRealTagify(page: Page, closedList = false): Promise<void> {
  await page.addStyleTag({ path: path.join(TAGIFY_DIR, 'tagify.css') });
  await page.addScriptTag({ path: path.join(TAGIFY_DIR, 'tagify.js') });
  await page.addScriptTag({ path: path.join(MODULE_DIR, 'js/mukurtu-tagify-override.js') });
  await page.evaluate((enforceWhitelist) => {
    const input = document.createElement('input');
    input.id = 'real';
    input.className = 'tagify-widget';
    input.setAttribute('data-mukurtu-character-palette', '');
    document.body.prepend(input);
    const tagify = new (window as any).Tagify(input, {
      whitelist: [],
      enforceWhitelist,
      addTagOn: ['blur', 'tab', 'enter'],
      dropdown: { enabled: 1, highlightFirst: true },
    });
    // Commas can be part of a term, so this turns delimiters off. With
    // them on, Tagify makes a tag out of a new line.
    (window as any).Drupal.behaviors.mukurtuTagifyOverride.attach(document);
    tagify.on('dropdown:show', () => {
      (window as any).suggested = tagify.state.dropdown.query;
    });
    // Suggestions arrive later, as they do from the Tagify module's fetch.
    tagify.on('input', (event: any) => {
      const { value } = event.detail;
      window.setTimeout(() => {
        tagify.whitelist = ['helloḵ'];
        tagify.dropdown.show(value);
      }, 200);
    });
    (window as any).Drupal.behaviors.mukurtuCharacterPalette.attach(document);
  }, closedList);
}

// A full-page dialog at the bottom of Klaro's z-index range (999 to 1001).
// Earlier in the page, so a tie would go to the palette.
async function addDialog(page: Page): Promise<void> {
  await page.evaluate(() => {
    const dialog = document.createElement('div');
    dialog.id = 'consent';
    dialog.style.cssText = 'position:fixed;inset:0;z-index:999;background:#fff';
    document.body.prepend(dialog);
  });
}

const corner = (page: Page) => page.getByRole('button', { name: 'Character palette' });
const panel = (page: Page) => page.locator('#mukurtu-character-palette');

test.describe('Character palette', () => {
  test('has one corner button, named and described, and no button per field', async ({ page }) => {
    await load(page);
    await expect(page.locator('.mukurtu-character-palette__toggle')).toHaveCount(1);
    await expect(corner(page)).toHaveAttribute('title', 'Character palette (Ctrl+Shift+Space)');
    await expect(corner(page)).toHaveAccessibleDescription('Inserts characters into the field you were last typing in. Shortcut: Ctrl+Shift+Space. Press Escape to go back to the field.');
    // Read only as the button's description, not on its own.
    await expect(page.locator('#mukurtu-character-palette-description')).toBeHidden();
    await expect(corner(page)).toHaveAttribute('aria-expanded', 'false');
    await expect(corner(page)).toHaveCSS('position', 'fixed');
  });

  test('a page with nothing to type in gets no button', async ({ page }) => {
    await page.unrouteAll();
    await page.route(`${ORIGIN}/**`, (route) => route.fulfill({
      contentType: 'text/html; charset=utf-8',
      body: `<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>No fields</title></head><body>
        <div id="announce"></div><script>${STUBS} window.drupalSettings = {mukurtuCharacterPalette: {groups: ${JSON.stringify(GROUPS)}}};</script>
        <p>Nothing to type in.</p></body></html>`,
    }));
    await page.goto(`${ORIGIN}/`);
    await page.addScriptTag({ path: path.join(MODULE_DIR, 'js/character-palette.js') });
    await page.evaluate(() => (window as any).Drupal.behaviors.mukurtuCharacterPalette.attach(document));
    await expect(page.locator('.mukurtu-character-palette__toggle')).toHaveCount(0);
  });

  test('Ctrl+Shift+Space jumps from a field to the palette, Escape goes back', async ({ page }) => {
    await load(page);
    const term = page.locator('#term');
    await term.fill('aabb');
    await term.evaluate((el: HTMLInputElement) => el.setSelectionRange(2, 2));

    await page.keyboard.press('Control+Shift+Space');
    await expect(corner(page)).toHaveAttribute('aria-expanded', 'true');
    await expect(panel(page).getByRole('button', { name: 'x underbar ejective' })).toBeFocused();
    await page.keyboard.press('Enter');
    await page.keyboard.press('Tab');
    await page.keyboard.press('Enter');
    await expect(term).toHaveValue('aax̱ʼḵbb');
    await expect(page.locator('#announce')).toHaveText('Inserted k underbar.');

    await page.keyboard.press('Escape');
    await expect(term).toBeFocused();
    // 'aa' + x, a combining macron below and a modifier apostrophe + ḵ.
    expect(await term.evaluate((el: HTMLInputElement) => el.selectionStart)).toBe(6);
    await expect(panel(page)).toBeHidden();
  });

  test('the shortcut goes back to the field from the palette too, closing it', async ({ page }) => {
    await load(page);
    await page.locator('#term').focus();
    await page.keyboard.press('Control+Shift+Space');
    await page.keyboard.press('Control+Shift+Space');
    await expect(page.locator('#term')).toBeFocused();
    await expect(panel(page)).toBeHidden();
  });

  test('Escape in the field closes a panel opened with the mouse', async ({ page }) => {
    await load(page);
    await page.locator('#term').focus();
    await corner(page).click();
    await expect(panel(page)).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(page.locator('#term')).toBeFocused();
    await expect(panel(page)).toBeHidden();
  });

  test('Escape goes back to the button when no field has been used', async ({ page }) => {
    await load(page);
    await corner(page).click();
    await panel(page).getByRole('button', { name: 'k underbar' }).focus();
    await page.keyboard.press('Escape');
    await expect(corner(page)).toBeFocused();
  });

  test('never stays over the focused field, even when kept open', async ({ page }) => {
    await load(page);
    await page.locator('#term').focus();
    await page.keyboard.press('Control+Shift+Space');
    await panel(page).getByLabel('Keep open').check();
    await page.keyboard.press('Escape');
    await expect(panel(page)).toBeVisible();

    // A field under the panel that the page can't scroll out from under it.
    await page.locator('#under').focus();
    await expect(panel(page)).toBeHidden();
    await expect(panel(page).getByLabel('Keep open')).toBeChecked();
  });

  test('a mouse keeps focus and the caret in the field', async ({ page }) => {
    await load(page);
    const term = page.locator('#term');
    await term.fill('ab');
    await term.evaluate((el: HTMLInputElement) => el.setSelectionRange(1, 1));
    await corner(page).click();
    await expect(term).toBeFocused();
    await panel(page).getByRole('button', { name: 'k underbar' }).click();
    await expect(term).toBeFocused();
    await expect(term).toHaveValue('aḵb');
  });

  test('characters go into the field used last, and only marked fields count', async ({ page }) => {
    await load(page);
    await page.locator('#term').focus();
    await page.locator('#source').focus();
    await page.locator('#plain').focus();
    await page.keyboard.press('Control+Shift+Space');
    await page.keyboard.press('Enter');
    await expect(page.locator('#source')).toHaveValue('x̱ʼ');
    await expect(page.locator('#term')).toHaveValue('');
    await expect(page.locator('#plain')).toHaveValue('');
  });

  test('says so when no field has been used yet', async ({ page }) => {
    await load(page);
    await corner(page).click();
    await panel(page).getByRole('button', { name: 'k underbar' }).click();
    await expect(page.locator('#announce')).toHaveText('Choose a field to type in first.');
  });

  test('closes when focus or a click leaves it, unless kept open', async ({ page }) => {
    await load(page);
    await page.locator('#term').focus();
    await page.keyboard.press('Control+Shift+Space');
    await page.locator('#plain').focus();
    await expect(panel(page)).toBeHidden();

    await corner(page).click();
    await page.mouse.click(5, 5);
    await expect(panel(page)).toBeHidden();

    // Kept open: Escape goes back to the field but the panel stays, and it
    // stays through clicks elsewhere and a reload.
    await page.locator('#term').focus();
    await page.keyboard.press('Control+Shift+Space');
    await panel(page).getByLabel('Keep open').check();
    await page.keyboard.press('Escape');
    await expect(page.locator('#term')).toBeFocused();
    await expect(panel(page)).toBeVisible();
    await page.mouse.click(5, 5);
    await expect(panel(page)).toBeVisible();
    await load(page);
    await expect(panel(page).getByLabel('Keep open')).toBeChecked();
  });

  test('inserts through a rich text editor', async ({ page }) => {
    await load(page);
    await page.locator('.ck-editor__editable').focus();
    await corner(page).click();
    await panel(page).getByRole('button', { name: 'k underbar' }).click();
    await expect(page.locator('.ck-editor__editable')).toBeFocused();
    expect(await page.evaluate(() => (window as any).inserted)).toEqual(['ḵ']);

    await page.keyboard.press('Control+Shift+Space');
    await page.keyboard.press('Escape');
    await expect(page.locator('.ck-editor__editable')).toBeFocused();
  });

  test('types into a Tagify box without making a tag until focus leaves the palette', async ({ page }) => {
    await load(page);
    const box = page.locator('#kw-scope .tagify__input');
    await box.click();
    await page.keyboard.type('ab');
    await page.keyboard.press('ArrowLeft');
    await page.keyboard.press('Control+Shift+Space');
    expect(await page.evaluate(() => (window as any).tagged)).toEqual([]);

    await panel(page).getByRole('button', { name: 'k underbar' }).focus();
    await page.keyboard.press('Enter');
    await expect(box).toHaveText('aḵb');
    expect(await page.evaluate(() => (window as any).tagged)).toEqual([]);

    await page.locator('#plain').focus();
    expect(await page.evaluate(() => (window as any).tagged)).toEqual(['aḵb']);
  });

  test('Enter makes a tag after a mouse insert into a real Tagify box', async ({ page }) => {
    await load(page);
    await addRealTagify(page);
    const box = page.locator('tags .tagify__input').first();
    await box.click();
    await page.keyboard.type('hello');
    await corner(page).click();
    await panel(page).getByRole('button', { name: 'k underbar' }).click();
    await expect(box).toHaveText('helloḵ');
    await expect(box).toBeFocused();
    // Suggestions for the finished text being open is what took Enter away
    // from Tagify.
    await page.waitForFunction(() => (window as any).suggested === 'helloḵ');

    await page.keyboard.press('Enter');
    await expect(page.locator('tags .tagify__tag')).toHaveCount(1);
    await expect(page.locator('tags .tagify__tag')).toContainText('helloḵ');
    await expect(box).toHaveText('');
  });

  test('Enter takes the suggestion in a closed list after a mouse insert', async ({ page }) => {
    await load(page);
    await addRealTagify(page, true);
    const box = page.locator('tags .tagify__input').first();
    await box.click();
    await page.keyboard.type('hello');
    await corner(page).click();
    await panel(page).getByRole('button', { name: 'k underbar' }).click();
    await page.waitForFunction(() => (window as any).suggested === 'helloḵ');

    await page.keyboard.press('Enter');
    await expect(page.locator('tags .tagify__tag')).toHaveCount(1);
    await expect(box).toHaveText('');
  });

  test('uses Option+Shift+Space on a Mac, where macOS keeps Ctrl+Shift+Space', async ({ page }) => {
    await load(page, GROUPS, 'MacIntel');
    await expect(corner(page)).toHaveAttribute('title', 'Character palette (Option+Shift+Space)');
    await expect(corner(page)).toHaveAccessibleDescription('Inserts characters into the field you were last typing in. Shortcut: Option+Shift+Space. Press Escape to go back to the field.');

    const term = page.locator('#term');
    await term.focus();
    await page.keyboard.press('Control+Shift+Space');
    await expect(panel(page)).toBeHidden();

    await page.keyboard.press('Alt+Shift+Space');
    await expect(panel(page).getByRole('button', { name: 'x underbar ejective' })).toBeFocused();
    await page.keyboard.press('Alt+Shift+Space');
    await expect(term).toBeFocused();
    await expect(term).toHaveValue('');
  });

  test('ignores Option+Shift+Space off a Mac', async ({ page }) => {
    await load(page);
    await page.locator('#term').focus();
    await page.keyboard.press('Alt+Shift+Space');
    await expect(panel(page)).toBeHidden();
  });

  test('reopens on the next page when kept open, without taking focus', async ({ page }) => {
    await load(page);
    await corner(page).click();
    await panel(page).getByLabel('Keep open').check();

    await load(page);
    await expect(panel(page)).toBeVisible();
    await expect(corner(page)).toHaveAttribute('aria-expanded', 'true');
    await expect(panel(page).getByLabel('Keep open')).toBeChecked();
    expect(await page.evaluate(() => document.activeElement === document.body)).toBe(true);

    // Still never stays over the focus.
    await page.locator('#under').focus();
    await expect(panel(page)).toBeHidden();

    // Unchecked, it stays closed.
    await page.locator('#term').focus();
    await corner(page).click();
    await panel(page).getByLabel('Keep open').uncheck();
    await load(page);
    await expect(panel(page)).toBeHidden();
  });

  test('stays under the cookie consent dialog', async ({ page }) => {
    await load(page);
    await corner(page).click();
    await addDialog(page);

    for (const element of [panel(page), corner(page)]) {
      const box = await element.boundingBox();
      const onTop = await page.evaluate(
        ([x, y]) => document.elementFromPoint(x, y)?.id,
        [box!.x + box!.width / 2, box!.y + box!.height / 2],
      );
      expect(onTop).toBe('consent');
    }
  });

  test('the shortcut leaves focus alone while a dialog covers the palette', async ({ page }) => {
    await load(page);
    await page.locator('#term').focus();
    await addDialog(page);

    await page.keyboard.press('Control+Shift+Space');
    await expect(page.locator('#term')).toBeFocused();
    await expect(panel(page)).toBeHidden();
  });

  test('scrolls a field out from under the panel, even with smooth scrolling', async ({ page }) => {
    await load(page);
    await page.addStyleTag({ content: 'html { scroll-behavior: smooth; }' });
    await corner(page).click();
    await panel(page).getByLabel('Keep open').check();
    const scrolled = await page.evaluate(() => {
      document.body.style.minBlockSize = '5000px';
      const field = document.getElementById('term') as HTMLElement;
      field.style.cssText = 'position:absolute;right:3rem;top:2000px';
      const area = document.getElementById('mukurtu-character-palette')!.getBoundingClientRect();
      const offset = 2000 - area.top - 20;
      document.documentElement.style.scrollBehavior = 'auto';
      window.scrollTo(0, offset);
      document.documentElement.style.scrollBehavior = 'smooth';
      return offset;
    });

    await page.locator('#term').focus();
    await expect.poll(() => page.evaluate(() => window.scrollY)).toBeGreaterThan(scrolled);
    await expect(panel(page)).toBeVisible();
    const field = (await page.locator('#term').boundingBox())!;
    const area = (await panel(page).boundingBox())!;
    expect(field.y + field.height).toBeLessThanOrEqual(area.y);
  });

  test('doesn\'t scroll the page away when focus is fixed under the panel', async ({ page }) => {
    await load(page);
    await corner(page).click();
    await panel(page).getByLabel('Keep open').check();
    await page.evaluate(() => {
      document.body.style.minBlockSize = '5000px';
      window.scrollTo(0, 600);
    });

    await page.locator('#under').focus();
    await expect(panel(page)).toBeHidden();
    expect(await page.evaluate(() => window.scrollY)).toBe(600);
  });

  test('switches language and remembers it', async ({ page }) => {
    await load(page);
    await corner(page).click();
    await panel(page).getByLabel('Language').selectOption({ label: 'Osage' });
    await expect(panel(page).getByRole('button', { name: 'Osage capital A' })).toBeVisible();
    await load(page);
    await expect(panel(page).getByLabel('Language')).toHaveValue('1');
  });

  test('has no language menu with only one language', async ({ page }) => {
    await load(page, [GROUPS[0]]);
    await corner(page).click();
    await expect(panel(page).getByLabel('Language')).toHaveCount(0);
  });

  test('buttons are at least 24 by 24 pixels', async ({ page }) => {
    await load(page);
    await corner(page).click();
    for (const button of await page.locator('.mukurtu-character-palette__toggle:visible, .mukurtu-character-palette__character:visible').all()) {
      const box = (await button.boundingBox())!;
      expect(box.width).toBeGreaterThanOrEqual(24);
      expect(box.height).toBeGreaterThanOrEqual(24);
    }
  });
});
