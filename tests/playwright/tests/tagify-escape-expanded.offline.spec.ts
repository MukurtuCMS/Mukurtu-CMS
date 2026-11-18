import { test, expect, Page } from '@playwright/test';
import path = require('path');

/**
 * Two Tagify behaviors mukurtu-tagify-override.js corrects:
 * - Escape with the suggestion list closed blurred the input, so focus fell
 *   to the page body (WCAG 2.4.3, #2719), and on fields that create terms
 *   the typed text became a new term. It now clears the text instead.
 * - Tagify set aria-expanded on its <tags> wrapper, which has no role, so
 *   the attribute isn't allowed there (WCAG 4.1.2, #2720).
 * - Escape that closed the list also closed a dialog around the field
 *   (#2746). It's now marked as handled, which jQuery UI dialogs respect.
 * - Down Arrow right after Escape didn't reopen the list (#2748).
 *
 * Runs the real Tagify library with the override and combobox files, and
 * the Tagify module's dropdown settings. Drupal is stubbed.
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

/**
 * A Keywords field, and a "show all" field like a select-style Tagify
 * widget, which lists every option on Down Arrow.
 */
async function setUp(page: Page): Promise<void> {
  await page.setContent(`<!DOCTYPE html><html lang="en"><head><title>Tagify fixture</title></head><body>
    <main id="outer">
      <label for="keywords">Keywords</label>
      <input id="keywords" class="tagify-widget tagify--autocreate">
      <label for="type">Word type</label>
      <input id="type" class="tagify-widget">
      <button type="button">After</button>
    </main>
  </body></html>`);
  await page.addStyleTag({ path: TAGIFY_CSS });
  await page.addScriptTag({ path: TAGIFY_JS });
  await page.evaluate(() => {
    const w = window as any;
    w.Drupal = {
      behaviors: {},
      t: (text: string) => text,
      checkPlain: (text: string) => String(text),
      formatPlural: (count: number, singular: string, plural: string) => (count === 1 ? singular : plural.replace('@count', String(count))),
      announce: () => {},
    };
    w.drupalSettings = {
      tagify: { information_message: { no_matching_suggestions: 'No matching suggestions found for:' } },
    };
    // Stands in for a dialog around the field, such as the media library's.
    // Like jQuery UI's, it closes on an Escape that isn't marked as handled.
    w.escapesReachedPage = 0;
    w.dialogClosings = 0;
    document.getElementById('outer')!.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        w.escapesReachedPage += 1;
        if (!event.defaultPrevented) {
          w.dialogClosings += 1;
        }
      }
    });
  });
  await page.addScriptTag({ path: OVERRIDE_JS });
  await page.addScriptTag({ path: COMBOBOX_JS });
  await page.evaluate((terms) => {
    const settings = {
      dropdown: {
        enabled: 1,
        highlightFirst: true,
        fuzzySearch: true,
        closeOnSelect: true,
        searchKeys: ['label'],
        mapValueTo: 'label',
      },
      tagTextProp: 'label',
      editTags: false,
      skipInvalid: true,
    };
    // @ts-ignore Tagify is the global from tagify.js.
    new Tagify(document.getElementById('keywords'), { ...settings, whitelist: terms });
    // @ts-ignore Tagify is the global from tagify.js.
    const type = new Tagify(document.getElementById('type'), {
      ...settings,
      dropdown: { ...settings.dropdown, enabled: 0 },
      whitelist: terms,
    });
    // The Tagify module marks select-style widgets this way.
    type.DOM.scope.classList.add('tagify-select');
    const w = window as any;
    w.Drupal.behaviors.mukurtuTagifyOverride.attach(document);
    w.Drupal.behaviors.mukurtuTagifyCombobox.attach(document);
  }, TERMS);
  await page.evaluate(() => new Promise((resolve) => setTimeout(resolve, 10)));
}

const keywords = (page: Page) => page.getByRole('combobox', { name: 'Keywords' });
const wordType = (page: Page) => page.getByRole('combobox', { name: 'Word type' });
const focusedName = (page: Page) => page.evaluate(() => document.activeElement?.getAttribute('aria-label') ?? document.activeElement?.tagName);
const listOpen = (page: Page, id: string) => page.evaluate((field) => Boolean((document.getElementById(field) as any).__tagify.state.dropdown.visible), id);

test.describe('Tagify Escape and aria-expanded', () => {
  test.beforeEach(async ({ page }) => {
    await setUp(page);
  });

  test('Escape closes the list, then clears the text and leaves focus in the field', async ({ page }) => {
    await keywords(page).click();
    await page.keyboard.type('bas');
    await expect.poll(() => listOpen(page, 'keywords')).toBe(true);
    // Tagify redraws the list after each letter. A person's Escape comes
    // well after that; one sent within milliseconds can land mid-redraw.
    await page.waitForTimeout(150);

    await page.keyboard.press('Escape');
    await expect.poll(() => listOpen(page, 'keywords')).toBe(false);
    expect(await focusedName(page)).toBe('Keywords');
    // The first Escape only closes the list.
    await expect(keywords(page)).toHaveText('bas');

    await page.keyboard.press('Escape');
    await page.waitForTimeout(50);
    expect(await focusedName(page)).toBe('Keywords');
    await expect(keywords(page)).toHaveText('');
    // Keywords creates terms, and the text didn't become one.
    await expect(page.locator('tags').first().locator('tag')).toHaveCount(0);

    // The field still works after clearing.
    await page.keyboard.type('bead');
    await expect.poll(() => listOpen(page, 'keywords')).toBe(true);
  });

  test('Escape before suggestions arrive clears the text, and late suggestions stay closed', async ({ page }) => {
    await keywords(page).click();
    // Nothing matches, so no list opens, as while a request is still out.
    await page.keyboard.type('zzq');
    await page.waitForTimeout(150);
    expect(await listOpen(page, 'keywords')).toBe(false);

    await page.keyboard.press('Escape');
    await expect(keywords(page)).toHaveText('');
    expect(await focusedName(page)).toBe('Keywords');

    // The Tagify module opens the list when its answer arrives.
    await page.evaluate(() => (document.getElementById('keywords') as any).__tagify.dropdown.show('zzq'));
    await expect.poll(() => listOpen(page, 'keywords')).toBe(false);

    // Typing again shows suggestions as usual.
    await page.keyboard.type('bas');
    await expect.poll(() => listOpen(page, 'keywords')).toBe(true);
  });

  test('late suggestions do not reopen a list Escape closed', async ({ page }) => {
    await keywords(page).click();
    await page.keyboard.type('bas');
    await expect.poll(() => listOpen(page, 'keywords')).toBe(true);
    await page.waitForTimeout(150);
    await page.keyboard.press('Escape');
    await expect.poll(() => listOpen(page, 'keywords')).toBe(false);

    await page.evaluate(() => (document.getElementById('keywords') as any).__tagify.dropdown.show('bas'));
    await page.waitForTimeout(150);
    expect(await listOpen(page, 'keywords')).toBe(false);
    await expect(keywords(page)).toHaveText('bas');
  });

  test('Escape that cancels an input method character leaves the text', async ({ page }) => {
    await keywords(page).click();
    await page.keyboard.type('bas');
    await page.waitForTimeout(150);
    await page.keyboard.press('Escape');
    await expect.poll(() => listOpen(page, 'keywords')).toBe(false);
    await page.waitForTimeout(150);
    await keywords(page).evaluate((el) => el.dispatchEvent(new KeyboardEvent('keydown', {
      key: 'Escape', isComposing: true, bubbles: true, cancelable: true,
    })));
    await expect(keywords(page)).toHaveText('bas');
  });

  test('Escape still reaches a dialog around the field', async ({ page }) => {
    await keywords(page).click();
    await page.keyboard.press('Escape');
    await page.keyboard.press('Escape');
    expect(await page.evaluate(() => (window as any).escapesReachedPage)).toBe(2);
    expect(await focusedName(page)).toBe('Keywords');
  });

  test('Escape that closes the list leaves a dialog open, and the next one closes it', async ({ page }) => {
    const closings = () => page.evaluate(() => (window as any).dialogClosings);
    await keywords(page).click();
    await page.keyboard.type('bas');
    await expect.poll(() => listOpen(page, 'keywords')).toBe(true);
    await page.waitForTimeout(150);

    await page.keyboard.press('Escape');
    await expect.poll(() => listOpen(page, 'keywords')).toBe(false);
    expect(await closings()).toBe(0);
    expect(await focusedName(page)).toBe('Keywords');

    await page.keyboard.press('Escape');
    expect(await closings()).toBe(1);
  });

  test('Down Arrow right after Escape reopens the list', async ({ page }) => {
    await keywords(page).click();
    await page.keyboard.type('bas');
    await expect.poll(() => listOpen(page, 'keywords')).toBe(true);
    await page.waitForTimeout(150);
    const showing = () => page.evaluate(() => Boolean(document.querySelector('.tagify__dropdown')?.isConnected));

    // No wait between the keys.
    await page.keyboard.press('Escape');
    await page.keyboard.press('ArrowDown');
    await expect.poll(showing).toBe(true);

    // The same on a show-all field.
    await page.keyboard.press('Escape');
    await wordType(page).focus();
    await page.keyboard.press('ArrowDown');
    await expect.poll(() => listOpen(page, 'type')).toBe(true);
    await page.waitForTimeout(150);
    await page.keyboard.press('Escape');
    await page.keyboard.press('ArrowDown');
    await expect.poll(showing).toBe(true);
  });

  test('blur works again after Escape', async ({ page }) => {
    await keywords(page).click();
    await page.keyboard.press('Escape');
    await page.waitForTimeout(50);
    await page.evaluate(() => (document.querySelector('[aria-label="Keywords"]') as HTMLElement).blur());
    expect(await focusedName(page)).toBe('BODY');
  });

  test('the <tags> wrapper never has aria-expanded; the combobox does', async ({ page }) => {
    const wrapper = page.locator('tags').first();
    await expect(wrapper).not.toHaveAttribute('aria-expanded', /.*/);

    await keywords(page).click();
    await page.keyboard.type('bas');
    await expect.poll(() => listOpen(page, 'keywords')).toBe(true);
    // Tagify redraws the list after each letter. A person's Escape comes
    // well after that; one sent within milliseconds can land mid-redraw.
    await page.waitForTimeout(150);
    await expect(keywords(page)).toHaveAttribute('aria-expanded', 'true');
    await expect(wrapper).not.toHaveAttribute('aria-expanded', /.*/);

    await page.keyboard.press('Escape');
    await expect(keywords(page)).toHaveAttribute('aria-expanded', 'false');
    await expect(wrapper).not.toHaveAttribute('aria-expanded', /.*/);
  });

  test('Down Arrow opens a show-all list, then moves through it', async ({ page }) => {
    await wordType(page).focus();
    await page.keyboard.press('ArrowDown');
    await expect.poll(() => listOpen(page, 'type')).toBe(true);
    const active = () => page.evaluate(() => {
      const tagify = (document.getElementById('type') as any).__tagify;
      return tagify.DOM.dropdown.querySelector('.tagify__dropdown__item--active')?.textContent?.trim();
    });
    const first = await active();
    await page.keyboard.press('ArrowDown');
    // Reopening would reset the highlight to the first option.
    await expect.poll(active).not.toBe(first);
  });
});
