import { test, expect } from '@playwright/test';
import { ADMIN_STATE } from '~helpers/auth-state';

/**
 * Paragraphs could only be reordered by dragging (#2738). mukurtu_core adds
 * arrow buttons beside each drag handle, named after the paragraph's Title
 * field, or its type when it has no title.
 *
 * Uses the Person form's Biography sections, which every site has.
 */
test.describe('Paragraph reorder arrows', () => {
  test.use({ storageState: ADMIN_STATE });

  test('Arrows move a paragraph, save its weight and announce it', async ({ page }) => {
    await page.goto('/node/add/person');
    const tab = page.locator('a[href="#edit-group-biography"]:visible').first();
    if (await tab.count()) {
      await tab.click();
    }
    const field = page.locator('.field--name-field-sections').first();
    const titles = field.locator('input[name$="[subform][field_title][0][value]"]');
    if ((await titles.count()) < 2) {
      await field.locator('input[value="Add Biography section"]').click();
      await expect(titles).toHaveCount(2);
    }
    await titles.nth(0).fill('Early life');
    // The second keeps no title, so its arrows are named by its type.
    await titles.nth(1).fill('');
    await titles.nth(0).dispatchEvent('change');

    const first = field.locator('tr.draggable').nth(0);
    await expect(first.getByRole('button', { name: 'Move Early life earlier' })).toBeDisabled();
    const later = first.getByRole('button', { name: 'Move Early life later' });
    await expect(later).toBeEnabled();
    await expect(field.locator('tr.draggable').nth(1).getByRole('button', { name: 'Move Biography section later' })).toBeDisabled();

    await later.click();
    await expect(titles.nth(1)).toHaveValue('Early life');
    // At the end of the list the button is disabled, so focus moves to the
    // other arrow of the same paragraph.
    await expect(page.getByRole('button', { name: 'Move Early life earlier' })).toBeFocused();
    await expect(page.getByRole('button', { name: 'Move Early life later' })).toBeDisabled();
    await expect(page.locator('#drupal-live-announce')).toHaveText('Early life moved to position 2 of 2.');

    // The weights follow the new order, as after a drag.
    const weights = await field.locator('[name$="[_weight]"]').evaluateAll(
      (elements) => elements.map((element) => Number((element as HTMLInputElement).value)),
    );
    expect(weights[0]).toBeLessThan(weights[1]);
    await expect(field.locator('tr.draggable').nth(1).locator('.tabledrag-changed')).toHaveCount(1);
  });

  // Gin draws focus as a box-shadow, which forced colors removes.
  test('A focused arrow keeps an outline in forced colors', async ({ page }) => {
    await page.emulateMedia({ forcedColors: 'active' });
    await page.goto('/node/add/person');
    const tab = page.locator('a[href="#edit-group-biography"]:visible').first();
    if (await tab.count()) {
      await tab.click();
    }
    const field = page.locator('.field--name-field-sections').first();
    if ((await field.locator('tr.draggable').count()) < 2) {
      await field.locator('input[value="Add Biography section"]').click();
    }
    const arrow = field.locator('.mukurtu-paragraphs-move__later').first();
    await arrow.focus();
    await page.keyboard.press('Shift+Tab');
    await page.keyboard.press('Tab');
    await expect(arrow).toBeFocused();
    await expect(arrow).toHaveCSS('outline-style', 'solid');
  });

  // Plain multiple-value fields inside a paragraph use the same kind of
  // table, but their rows aren't paragraphs and have no name to give arrows.
  test('Multiple-value fields inside a paragraph get no arrows', async ({ page }) => {
    await page.goto('/node/add/dictionary_word');
    const entries = page.locator('.field--name-field-additional-word-entries').first();
    const pane = await entries.evaluate((element) => element.closest('.horizontal-tabs-pane, details[id]')?.id);
    if (pane) {
      const link = page.locator(`a[href="#${pane}"]:visible`).first();
      if (await link.count()) {
        await link.click();
      }
    }
    const translations = entries.locator('.field--name-field-translation table.field-multiple-table').first();
    await entries.locator('input[name*="field_translation_add_more"]').first().click();
    await expect(translations.locator(':scope > tbody > tr.draggable')).toHaveCount(2);
    await expect(translations.locator('.mukurtu-paragraphs-move__button')).toHaveCount(0);
  });
});
