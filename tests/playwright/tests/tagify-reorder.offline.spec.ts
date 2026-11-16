import { test, expect, Page } from '@playwright/test';
import path = require('path');

/**
 * Tagify fields had a second, hidden copy of the field that screen readers
 * read as JSON, and tags could only be reordered by dragging (#2710).
 * mukurtu-tagify-override.js hides the original input from assistive
 * technology and shows its required error on the visible field.
 * tagify-reorder.js adds arrow buttons that move a tag without dragging.
 *
 * Runs the real Tagify library and both files. The Tagify module's own
 * js/tagify.js (and its Sortable drag) isn't loaded, since vendor/ isn't
 * installed in the offline job. Drupal is stubbed, with Drupal.t() filling
 * in and escaping placeholders like the real one.
 */

const ROOT = path.join(__dirname, '../../..');
const TAGIFY_JS = path.join(ROOT, 'libraries/tagify/dist/tagify.js');
const TAGIFY_CSS = path.join(ROOT, 'libraries/tagify/dist/tagify.css');
const OVERRIDE_JS = path.join(ROOT, 'modules/mukurtu_core/js/mukurtu-tagify-override.js');
const REORDER_JS = path.join(ROOT, 'modules/mukurtu_core/js/tagify-reorder.js');
const REORDER_CSS = path.join(ROOT, 'modules/mukurtu_core/css/tagify-reorder.css');

interface Options {
  tags?: string[];
  required?: boolean;
  limited?: boolean;
}

async function setUp(page: Page, { tags = [], required = false, limited = false }: Options = {}): Promise<void> {
  const value = JSON.stringify(tags.map((label, i) => ({ value: String(i + 1), label })));
  await page.setContent(`<!DOCTYPE html><html lang="en"><head><title>Tagify fixture</title></head><body>
    <form id="form" action="about:blank">
      <label for="before">Title</label> <input id="before">
      <div class="form-item">
        <label for="keywords">Keywords</label>
        <input id="keywords" name="keywords" class="tagify-widget${limited ? ' tagify--limited' : ''}"
          ${required ? 'required' : ''} value='${value}'>
      </div>
      <button type="submit">Save</button>
    </form>
  </body></html>`);
  await page.addStyleTag({ path: TAGIFY_CSS });
  await page.addStyleTag({ path: REORDER_CSS });
  await page.addScriptTag({ path: TAGIFY_JS });
  await page.evaluate(() => {
    const escape = (text: string) => String(text)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    const w = window as any;
    w.announced = [];
    w.Drupal = {
      behaviors: {},
      t: (text: string, args: Record<string, unknown> = {}) => Object.keys(args)
        .reduce((out, key) => out.split(key).join(escape(String(args[key]))), text),
      checkPlain: escape,
      announce: (message: string) => w.announced.push(message),
    };
    w.drupalSettings = {};
  });
  await page.addScriptTag({ path: OVERRIDE_JS });
  await page.addScriptTag({ path: REORDER_JS });
  await page.evaluate(() => {
    const input = document.getElementById('keywords') as HTMLInputElement;
    // @ts-ignore Tagify is the global from tagify.js.
    new Tagify(input, { tagTextProp: 'label', editTags: false, whitelist: [] });
    const w = window as any;
    w.Drupal.behaviors.mukurtuTagifyOverride.attach(document);
    w.Drupal.behaviors.mukurtuTagifyReorder.attach(document);
  });
  // Both behaviors finish at the end of the tick.
  await page.evaluate(() => new Promise((resolve) => setTimeout(resolve, 10)));
}

function order(page: Page): Promise<string[]> {
  return page.evaluate(() => (document.getElementById('keywords') as any).__tagify.value
    .map((tag: any) => tag.label));
}

function savedOrder(page: Page): Promise<string[]> {
  return page.evaluate(() => JSON.parse((document.getElementById('keywords') as HTMLInputElement).value || '[]')
    .map((tag: any) => tag.label));
}

const button = (page: Page, name: string) => page.getByRole('button', { name, exact: true });

test.describe('Hidden original input', () => {
  test('screen readers find one Keywords field, with no JSON value', async ({ page }) => {
    await setUp(page, { tags: ['Baskets'] });
    await expect(page.locator('#keywords')).toHaveAttribute('aria-hidden', 'true');
    // The Title field and Tagify's own input. The original isn't a third.
    await expect(page.getByRole('textbox')).toHaveCount(2);
    await expect(page.getByRole('textbox', { name: 'Keywords' })).toHaveCount(1);
    const tree = await page.locator('body').ariaSnapshot();
    expect(tree).not.toContain('"value"');
  });

  test('the original input is still submitted', async ({ page }) => {
    await setUp(page, { tags: ['Baskets'] });
    const data = await page.evaluate(() => new FormData(document.getElementById('form') as HTMLFormElement).get('keywords'));
    expect(JSON.parse(String(data))[0].label).toBe('Baskets');
  });
});

test.describe('Required field', () => {
  test('the visible field says it is required', async ({ page }) => {
    await setUp(page, { required: true });
    await expect(page.getByRole('textbox', { name: 'Keywords' })).toHaveAttribute('aria-required', 'true');
  });

  test('clicking the label focuses the visible field', async ({ page }) => {
    await setUp(page, { tags: ['Baskets'] });
    await page.getByText('Keywords', { exact: true }).click();
    await expect(page.getByRole('textbox', { name: 'Keywords' })).toBeFocused();
  });
});

test.describe('Required field left empty', () => {
  test.beforeEach(async ({ page }) => {
    await setUp(page, { required: true });
    await page.evaluate(() => {
      const w = window as any;
      w.submitted = false;
      document.getElementById('form')!.addEventListener('submit', (event) => {
        w.submitted = true;
        event.preventDefault();
      });
    });
    await page.getByRole('button', { name: 'Save' }).click();
  });

  test('shows an error tied to the visible field, and focuses it', async ({ page }) => {
    const error = page.locator('#keywords--required-error');
    await expect(error).toHaveText('Keywords field is required.');
    await expect(error).toBeVisible();
    const textbox = page.getByRole('textbox', { name: 'Keywords' });
    await expect(textbox).toBeFocused();
    await expect(textbox).toHaveAttribute('aria-invalid', 'true');
    await expect(textbox).toHaveAccessibleDescription(/Keywords field is required\./);
    // The browser still blocked the submit.
    expect(await page.evaluate(() => (window as any).submitted)).toBe(false);
  });

  test('clears the error once a tag is added', async ({ page }) => {
    await page.evaluate(() => (document.getElementById('keywords') as any).__tagify.addTags([{ value: '1', label: 'Baskets' }]));
    await expect(page.locator('#keywords--required-error')).toHaveCount(0);
    const textbox = page.getByRole('textbox', { name: 'Keywords' });
    await expect(textbox).not.toHaveAttribute('aria-invalid', 'true');
  });
});

test.describe('Reorder buttons', () => {
  test.beforeEach(async ({ page }) => {
    await setUp(page, { tags: ['Alpha', 'Beta', 'Gamma'] });
  });

  test('each tag has named arrows, disabled at the ends', async ({ page }) => {
    await expect(button(page, 'Move Alpha earlier')).toBeDisabled();
    await expect(button(page, 'Move Alpha later')).toBeEnabled();
    await expect(button(page, 'Move Beta earlier')).toBeEnabled();
    await expect(button(page, 'Move Gamma later')).toBeDisabled();
  });

  test('a single click moves a tag and saves the order', async ({ page }) => {
    await button(page, 'Move Alpha later').click();
    expect(await order(page)).toEqual(['Beta', 'Alpha', 'Gamma']);
    expect(await savedOrder(page)).toEqual(['Beta', 'Alpha', 'Gamma']);
    await expect(page.locator('tags .tagify__tag')).toHaveText(['Beta', 'Alpha', 'Gamma'].map((t) => new RegExp(t)));
    expect(await page.evaluate(() => (window as any).announced)).toEqual(['Alpha moved to position 2 of 3.']);
    await expect(button(page, 'Move Alpha later')).toBeFocused();
  });

  test('a tag can be moved to any position with the keyboard', async ({ page }) => {
    await button(page, 'Move Gamma earlier').focus();
    await page.keyboard.press('Enter');
    await page.keyboard.press('Space');
    expect(await savedOrder(page)).toEqual(['Gamma', 'Alpha', 'Beta']);
    // Moving to the start disables this arrow, so focus goes to the other.
    await expect(button(page, 'Move Gamma later')).toBeFocused();
    await page.keyboard.press('Enter');
    expect(await savedOrder(page)).toEqual(['Alpha', 'Gamma', 'Beta']);
    expect(await page.evaluate(() => (window as any).announced)).toEqual([
      'Gamma moved to position 2 of 3.',
      'Gamma moved to position 1 of 3.',
      'Gamma moved to position 2 of 3.',
    ]);
  });

  test('Tab reaches every arrow and remove button, in order', async ({ page }) => {
    await page.locator('#before').focus();
    const stops: string[] = [];
    for (let i = 0; i < 10; i++) {
      await page.keyboard.press('Tab');
      stops.push(await page.evaluate(() => {
        const el = document.activeElement as HTMLElement;
        return el.getAttribute('aria-label') || el.className;
      }));
    }
    // Disabled arrows are skipped. The library's own remove buttons aren't
    // focusable; the Tagify module's template makes them so, and they're
    // checked on a real form.
    const arrowsAndInput = stops.filter((stop) => !stop.startsWith('remove '));
    expect(arrowsAndInput.slice(0, 5)).toEqual([
      'Move Alpha later',
      'Move Beta earlier', 'Move Beta later',
      'Move Gamma earlier',
      'Keywords',
    ]);
  });

  test('a focused arrow shows a focus outline', async ({ page }) => {
    await page.locator('#before').focus();
    await page.keyboard.press('Tab');
    await expect(button(page, 'Move Alpha later')).toBeFocused();
    const outline = await button(page, 'Move Alpha later').evaluate((el) => getComputedStyle(el).outlineStyle);
    expect(outline).toBe('solid');
  });

  test('removing a tag with its remove button puts focus on the input', async ({ page }) => {
    await page.locator('tags .tagify__tag').first().locator('.tagify__tag__removeBtn').click();
    await expect.poll(() => order(page)).toEqual(['Beta', 'Gamma']);
    await expect(page.locator('.tagify__input')).toBeFocused();
  });

  test('clicking an arrow neither removes the tag nor focuses the input', async ({ page }) => {
    await button(page, 'Move Beta earlier').click();
    expect(await order(page)).toEqual(['Beta', 'Alpha', 'Gamma']);
    await expect(page.getByRole('textbox', { name: 'Keywords' })).not.toBeFocused();
  });

  test('added and removed tags get arrows for their new positions', async ({ page }) => {
    await page.evaluate(() => (document.getElementById('keywords') as any).__tagify.addTags([{ value: '4', label: 'Delta' }]));
    await expect(button(page, 'Move Delta later')).toBeDisabled();
    await expect(button(page, 'Move Gamma later')).toBeEnabled();
    await page.evaluate(() => {
      const tagify = (document.getElementById('keywords') as any).__tagify;
      tagify.removeTags(tagify.getTagElms()[0], true);
    });
    await expect(button(page, 'Move Beta earlier')).toBeDisabled();
  });

  test('names with markup are read as text', async ({ page }) => {
    await page.evaluate(() => (document.getElementById('keywords') as any).__tagify.addTags([{ value: '5', label: 'Arts & Crafts' }]));
    await expect(button(page, 'Move Arts & Crafts earlier')).toBeEnabled();
  });
});

test.describe('Fields with nothing to reorder', () => {
  test('a single tag shows no arrows', async ({ page }) => {
    await setUp(page, { tags: ['Alpha'] });
    await expect(page.locator('.mukurtu-tagify-move')).toBeHidden();
  });

  test('a single-value field gets no arrows', async ({ page }) => {
    await setUp(page, { tags: ['Alpha'], limited: true });
    await expect(page.locator('.mukurtu-tagify-move')).toHaveCount(0);
  });
});
