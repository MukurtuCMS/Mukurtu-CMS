import { test, expect } from '@playwright/test';
import { ADMIN_STATE } from '~helpers/auth-state';

/**
 * In the media library dialog's table, each row is the control: it takes
 * focus with a visible outline, and Space or Enter selects it. The row's
 * checkbox is visually hidden, but it still took focus, an extra Tab stop on
 * every row that showed nothing (WCAG 2.4.7, #2745).
 */
test.describe('Media library rows', () => {
  test.use({ storageState: ADMIN_STATE });

  test('Tab skips the hidden checkboxes, and the row still selects', async ({ page }) => {
    await page.goto('/node/add/digital_heritage');
    await page.locator('input.js-media-library-open-button:visible').first().click();
    const dialog = page.locator('.ui-dialog:visible').last();
    const rows = dialog.locator('.views-table tbody tr[tabindex]');
    await rows.first().waitFor({ timeout: 20000 }).catch(() => {});
    test.skip((await rows.count()) === 0, 'The media library has no media on this site.');

    const row = rows.first();
    await row.focus();
    await expect(row).toHaveCSS('outline-style', 'solid');

    // Every focusable element after the row is visible: no hidden checkbox.
    for (let i = 0; i < 4; i++) {
      await page.keyboard.press('Tab');
      const focused = page.locator(':focus');
      await expect(focused).not.toHaveAttribute('type', 'checkbox');
      await expect(focused).toBeVisible();
    }

    await row.focus();
    await page.keyboard.press('Space');
    await expect(row).toHaveAttribute('aria-selected', 'true');
    await expect(row.locator('input[type="checkbox"]')).toBeChecked();
  });
});
