import { test, expect, type Page } from '@playwright/test';
import { ADMIN_STATE } from '~helpers/auth-state';

/**
 * Closing the media library without inserting left focus on the page body,
 * so keyboard and screen reader users lost their place in the form (WCAG
 * 2.4.3, #2772). Focus now goes back to the "Add media" button that opened
 * it.
 */

async function openLibrary(page: Page) {
  await page.goto('/node/add/digital_heritage');
  const opener = page.locator('input.js-media-library-open-button:visible').first();
  const selector = await opener.getAttribute('data-drupal-selector');
  await opener.focus();
  await page.keyboard.press('Enter');
  const dialog = page.locator('.ui-dialog.media-library-widget-modal:visible');
  await expect(dialog).toBeVisible({ timeout: 20000 });
  // Core moves focus into the library once it has loaded.
  await expect.poll(() => dialog.evaluate((element) => element.contains(document.activeElement))).toBe(true);
  return { dialog, opener: page.locator(`[data-drupal-selector="${selector}"]`) };
}

test.describe('Media library close focus', () => {
  test.use({ storageState: ADMIN_STATE });

  test('Escape returns focus to the button that opened the library', async ({ page }) => {
    const { dialog, opener } = await openLibrary(page);
    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
    await expect(opener).toBeFocused();
  });

  test('The Close button returns focus to the button that opened the library', async ({ page }) => {
    const { dialog, opener } = await openLibrary(page);
    await dialog.locator('.ui-dialog-titlebar-close').focus();
    await page.keyboard.press('Enter');
    await expect(dialog).toBeHidden();
    await expect(opener).toBeFocused();
  });
});
