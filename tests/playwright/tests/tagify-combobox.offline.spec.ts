import { test, expect, Page } from '@playwright/test';
import path = require('path');

/**
 * Tagify's input was a plain textbox, not tied to its suggestion list, so
 * arrowing through suggestions announced nothing (#2631).
 * tagify-combobox.js makes the input an ARIA combobox that controls the
 * list, points aria-activedescendant at the highlighted suggestion, and
 * announces how many suggestions there are, or that none match.
 *
 * Runs the real Tagify library with the override and combobox files, with
 * the dropdown settings copied from the Tagify module's js/tagify.js
 * (vendor/ isn't installed in the offline job). Like the module,
 * suggestions load 250ms after typing stops. Drupal is stubbed.
 */

const ROOT = path.join(__dirname, '../../..');
const TAGIFY_JS = path.join(ROOT, 'libraries/tagify/dist/tagify.js');
const TAGIFY_CSS = path.join(ROOT, 'libraries/tagify/dist/tagify.css');
const OVERRIDE_JS = path.join(ROOT, 'modules/mukurtu_core/js/mukurtu-tagify-override.js');
const COMBOBOX_JS = path.join(ROOT, 'modules/mukurtu_core/js/tagify-combobox.js');

const TERMS = [
  { value: '1', label: 'Baskets', entity_id: '1' },
  { value: '2', label: 'Basketry', entity_id: '2' },
  { value: '3', label: 'Beadwork', entity_id: '3' },
];

async function setUp(page: Page): Promise<void> {
  await page.setContent(`<!DOCTYPE html><html lang="en"><head><title>Tagify fixture</title></head><body>
    <label for="keywords">Keywords</label>
    <input id="keywords" class="tagify-widget tagify--autocreate">
  </body></html>`);
  await page.addStyleTag({ path: TAGIFY_CSS });
  await page.addScriptTag({ path: TAGIFY_JS });
  await page.evaluate(() => {
    const escape = (text: string) => String(text)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    const w = window as any;
    w.announced = [];
    w.Drupal = {
      behaviors: {},
      t: (text: string) => text,
      checkPlain: escape,
      formatPlural: (count: number, singular: string, plural: string) => (count === 1 ? singular : plural.replace('@count', String(count))),
      announce: (message: string) => w.announced.push(message),
    };
    w.drupalSettings = {
      tagify: { information_message: { no_matching_suggestions: 'No matching suggestions found for:' } },
    };
  });
  await page.addScriptTag({ path: OVERRIDE_JS });
  await page.addScriptTag({ path: COMBOBOX_JS });
  await page.evaluate((terms) => {
    const input = document.getElementById('keywords') as HTMLInputElement;
    // @ts-ignore Tagify is the global from tagify.js.
    const tagify = new Tagify(input, {
      dropdown: {
        enabled: 1,
        highlightFirst: true,
        fuzzySearch: true,
        closeOnSelect: true,
        searchKeys: ['label', 'input'],
        mapValueTo: 'label',
      },
      whitelist: [],
      // The module's "no match" row (js/tagify.js), which makes Tagify open
      // the list, and report no match, when nothing matches.
      templates: {
        dropdownItemNoMatch: (data: any) => `<div class="tagify__dropdown__item tagify--dropdown-item-no-match" value="noMatch" tabindex="0" role="option">
          <p>No matching suggestions found for:</p><strong class="tagify--value">${data.value}</strong></div>`,
      },
      tagTextProp: 'label',
      editTags: false,
      pasteAsTags: false,
      skipInvalid: true,
    });
    // Stands in for the module's debounced fetch of matching terms.
    let timer: number | undefined;
    tagify.on('input', (event: any) => {
      const value = event.detail?.value ?? '';
      window.clearTimeout(timer);
      timer = window.setTimeout(() => {
        const typed = value.toLowerCase();
        tagify.whitelist = terms
          .filter((term) => term.label.toLowerCase().includes(typed))
          .map((term) => ({ ...term, input: tagify.state.inputText }));
        tagify.loading(false).dropdown.show(value);
      }, 250);
    });
    const w = window as any;
    w.Drupal.behaviors.mukurtuTagifyOverride.attach(document);
    w.Drupal.behaviors.mukurtuTagifyCombobox.attach(document);
  }, TERMS);
  await page.evaluate(() => new Promise((resolve) => setTimeout(resolve, 10)));
}

const combobox = (page: Page) => page.getByRole('combobox', { name: 'Keywords' });

async function typeText(page: Page, text: string): Promise<void> {
  await combobox(page).click();
  const shown = page.evaluate(() => new Promise<void>((resolve) => {
    (document.getElementById('keywords') as any).__tagify.on('dropdown:show', () => resolve());
  }));
  await page.keyboard.type(text);
  await shown;
}

const announced = (page: Page) => page.evaluate(() => (window as any).announced as string[]);

test.describe('Tagify combobox', () => {
  test.beforeEach(async ({ page }) => {
    await setUp(page);
  });

  test('the input is a collapsed combobox that controls the suggestion list', async ({ page }) => {
    await expect(combobox(page)).toHaveCount(1);
    await expect(combobox(page)).toHaveAttribute('aria-expanded', 'false');
    await expect(combobox(page)).toHaveAttribute('aria-autocomplete', 'list');
    // Tagify adds the list to the page only while it's open.
    const controls = await combobox(page).getAttribute('aria-controls');
    expect(controls).toBeTruthy();
    expect(await page.evaluate(() => (document.getElementById('keywords') as any).__tagify.DOM.dropdown.id)).toBe(controls);
  });

  test('the combobox has no aria-placeholder, which combobox does not allow', async ({ page }) => {
    await expect(combobox(page)).not.toHaveAttribute('aria-placeholder', /.*/);
  });

  test('placeholder text is kept as a description', async ({ page }) => {
    await page.evaluate(() => {
      const input = document.createElement('input');
      input.id = 'people';
      input.className = 'tagify-widget';
      input.placeholder = 'Add a name';
      const label = document.createElement('label');
      label.htmlFor = 'people';
      label.textContent = 'People';
      document.body.append(label, input);
      // @ts-ignore Tagify is the global from tagify.js.
      new Tagify(input, { placeholder: 'Add a name' });
      (window as any).Drupal.behaviors.mukurtuTagifyCombobox.attach(document);
    });
    // The override names the input; only the combobox file runs here.
    const people = page.locator('#people').locator('xpath=preceding-sibling::tags[1]').locator('.tagify__input');
    await expect(people).toHaveAttribute('role', 'combobox');
    await expect(people).toHaveAttribute('aria-description', 'Add a name');
    await expect(people).not.toHaveAttribute('aria-placeholder', /.*/);
  });

  test('opening the list expands the combobox and announces the count', async ({ page }) => {
    await typeText(page, 'bas');
    await expect(combobox(page)).toHaveAttribute('aria-expanded', 'true');
    await expect(page.getByRole('listbox', { name: 'Keywords' })).toBeVisible();
    await expect(page.getByRole('option')).toHaveCount(2);
    await expect.poll(() => announced(page)).toContain('2 suggestions available.');
  });

  test('one match is announced in the singular', async ({ page }) => {
    await typeText(page, 'bead');
    await expect.poll(() => announced(page)).toContain('1 suggestion available.');
  });

  test('arrow keys point the combobox at each suggestion in turn', async ({ page }) => {
    await typeText(page, 'b');
    await expect(page.getByRole('option')).toHaveCount(3);
    const activeLabel = () => page.evaluate(() => {
      const input = document.querySelector('.tagify__input')!;
      const id = input.getAttribute('aria-activedescendant');
      const option = id ? document.getElementById(id) : null;
      return option ? `${option.getAttribute('role')}:${option.textContent!.trim()}` : null;
    });
    const seen: (string | null)[] = [];
    for (let i = 0; i < 3; i++) {
      await page.keyboard.press('ArrowDown');
      seen.push(await activeLabel());
    }
    expect(new Set(seen).size).toBe(3);
    seen.forEach((value) => expect(value).toMatch(/^option:/));
  });

  test('no match is announced with the typed text, once', async ({ page }) => {
    await typeText(page, 'zzz');
    await expect.poll(() => announced(page)).toEqual(['No matching suggestions found for: zzz']);
  });

  test('the count is announced once the suggestions settle, not on each change', async ({ page }) => {
    await typeText(page, 'b');
    await expect.poll(() => announced(page)).toEqual(['3 suggestions available.']);
    await page.keyboard.type('as');
    await expect.poll(() => announced(page)).toEqual(['3 suggestions available.', '2 suggestions available.']);
    // Arrowing through doesn't repeat the count.
    await page.keyboard.press('ArrowDown');
    await page.waitForTimeout(600);
    expect(await announced(page)).toEqual(['3 suggestions available.', '2 suggestions available.']);
  });

  test('picking a suggestion announces no count afterwards', async ({ page }) => {
    await typeText(page, 'bead');
    await expect.poll(() => announced(page)).toEqual(['1 suggestion available.']);
    await page.keyboard.press('ArrowDown');
    await page.keyboard.press('Enter');
    await page.waitForTimeout(600);
    expect(await announced(page)).toEqual(['1 suggestion available.']);
  });

  test('closing the list collapses the combobox', async ({ page }) => {
    await typeText(page, 'bas');
    // Wait for the fetched matches, or they reopen the list after Escape.
    await expect(page.getByRole('option')).toHaveCount(2);
    await page.keyboard.press('ArrowDown');
    await page.keyboard.press('Escape');
    await expect(combobox(page)).toHaveAttribute('aria-expanded', 'false');
    await expect(combobox(page)).not.toHaveAttribute('aria-activedescendant', /.+/);
  });

  test('picking a suggestion still adds it, and announces it', async ({ page }) => {
    await typeText(page, 'bead');
    await expect(page.getByRole('option', { name: 'Beadwork' })).toBeVisible();
    await page.keyboard.press('ArrowDown');
    await page.keyboard.press('Enter');
    const tags = await page.evaluate(() => (document.getElementById('keywords') as any).__tagify.value.map((t: any) => t.label));
    expect(tags).toEqual(['Beadwork']);
    await expect(combobox(page)).toHaveAttribute('aria-expanded', 'false');
  });
});
