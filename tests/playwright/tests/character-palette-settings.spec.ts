import { Locator, Page, expect, test } from '@playwright/test';
import { ADMIN_STATE } from '~helpers/auth-state';

/**
 * Reordering characters on the Character palette settings page (#2563).
 *
 * The arrow buttons are the WCAG 2.5.7 alternative to dragging, and rows carry
 * numbered labels for screen readers, so these check what a keyboard or
 * screen reader user gets as well as the saved order.
 *
 * Previews have no characters, so each test fills in the empty language at
 * the end of the form, and the one that saves deletes it again.
 */
const LANGUAGE = 'Reorder test';

const fillLanguage = async (page: Page): Promise<Locator> => {
  await page.goto('/admin/config/mukurtu/character-palette');
  const group = page.locator('fieldset:has(.mukurtu-character-table)').last();
  await group.getByLabel('Language name').fill(LANGUAGE);

  for (const [index, character] of ['a', 'b', 'c'].entries()) {
    if (index > 0) {
      await group.locator('input[type="submit"]').click();
      await expect(group.locator('tbody tr')).toHaveCount(index + 1);
      await expect(group.locator('tbody tr').last().locator('input[type="text"]').first()).toBeFocused();
    }
    await group.locator('tbody tr').nth(index).locator('input[type="text"]').first().fill(character);
  }
  return group;
};

// Gin's sticky header holds a copy of the button that covers the real one, and
// the page can take a while to come back.
const save = async (page: Page): Promise<void> => {
  const saved = page.waitForEvent('load', { timeout: 60000 });
  await page.locator('[data-drupal-selector="edit-submit"]').first().evaluate((button: HTMLElement) => button.click());
  await saved;
  await expect(page.getByText('The configuration options have been saved.')).toBeVisible();
};

const characters = (group: Locator): Promise<string[]> =>
  group
    .locator('tbody tr')
    .evaluateAll((rows) => rows.map((row) => row.querySelector('input')!.value));

test.describe('Character palette settings: reordering', () => {
  test.use({ storageState: ADMIN_STATE });

  test('Arrow buttons move a row, keep focus and announce it', async ({ page }) => {
    const group = await fillLanguage(page);
    const rows = group.locator('tbody tr');

    await expect(rows.nth(0).getByRole('button', { name: 'Move character 1 up' })).toBeDisabled();
    await expect(rows.nth(2).getByRole('button', { name: 'Move character 3 down' })).toBeDisabled();

    const down = rows.nth(0).getByRole('button', { name: 'Move character 1 down' });
    await down.focus();
    await page.keyboard.press('Enter');

    expect(await characters(group)).toEqual(['b', 'a', 'c']);
    const moved = rows.nth(1).getByRole('button', { name: 'Move character 2 down' });
    await expect(moved).toBeFocused();
    await expect(page.locator('#drupal-live-announce')).toContainText('Moved to position 2 of 3.');
    await expect(rows.nth(1).locator('input[type="text"]').first()).toHaveAccessibleName(
      'Character 2 in New language',
    );
    await expect(group.locator('.tabledrag-changed-warning')).toBeVisible();

    // At the end of the list its own button is disabled, so focus moves to
    // the other arrow instead of being lost.
    await moved.click();
    expect(await characters(group)).toEqual(['b', 'c', 'a']);
    await expect(rows.nth(2).getByRole('button', { name: 'Move character 3 up' })).toBeFocused();
  });

  test('The drag handle reorders by keyboard and renumbers the rows', async ({ page }) => {
    const group = await fillLanguage(page);
    const rows = group.locator('tbody tr');

    await rows.nth(2).locator('a.tabledrag-handle').focus();
    await page.keyboard.press('ArrowUp');
    await page.keyboard.press('Tab');

    expect(await characters(group)).toEqual(['a', 'c', 'b']);
    await expect(rows.nth(1).getByRole('button', { name: 'Move character 2 up' })).toBeEnabled();
    await expect(rows.nth(2).getByRole('button', { name: 'Move character 3 down' })).toBeDisabled();
  });

  test('Drag handles stay when row weights were turned on elsewhere', async ({ page }) => {
    // Core remembers this site-wide, and this page hides the link to undo it.
    await page.addInitScript(() => localStorage.setItem('Drupal.tableDrag.showWeight', '1'));
    const group = await fillLanguage(page);

    await expect(group.locator('a.tabledrag-handle').first()).toBeVisible();
    await expect(group.locator('input[type="number"]').first()).toBeHidden();
  });

  test('Add new row keeps unsaved moves, and saving keeps the order', async ({ page }) => {
    const group = await fillLanguage(page);
    const rows = group.locator('tbody tr');

    await rows.nth(2).getByRole('button', { name: 'Move character 3 up' }).click();
    await group.locator('input[type="submit"]').click();
    await expect(rows).toHaveCount(4);
    expect(await characters(group)).toEqual(['a', 'c', 'b', '']);
    await expect(rows.last().locator('input[type="text"]').first()).toBeFocused();
    await expect(group.locator('.tabledrag-changed-warning'), 'moves are still unsaved').toBeVisible();

    await save(page);

    const saved = page.locator(`fieldset:has(input[value="${LANGUAGE}"])`);
    expect(await characters(saved)).toEqual(['a', 'c', 'b', '']);

    // With two languages on the page, only the changed one gets the warning.
    await saved.getByRole('button', { name: 'Move character 1 down' }).click();
    await expect(page.locator('.tabledrag-changed-warning')).toHaveCount(1);
    await expect(saved.locator('.tabledrag-changed-warning')).toBeVisible();
    await saved.getByRole('button', { name: 'Move character 2 up' }).click();

    // Leave the site as it was found.
    await saved.getByLabel('Delete this language').check();
    await save(page);
    await expect(page.locator(`fieldset:has(input[value="${LANGUAGE}"])`)).toHaveCount(0);
  });
});
