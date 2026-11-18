import { test, expect } from '@playwright/test';

/**
 * Klaro adds its consent settings button as the first element of <body>, so
 * it was the first Tab stop on every page, ahead of "Skip to main content"
 * (#2747). mukurtu_core moves it to just after the skip link.
 */
test('The skip link comes before the consent settings button', async ({ page }) => {
  await page.goto('/');
  const toggle = page.locator('#klaro_toggle_dialog');
  // Klaro adds the button from JS, and only when the toggle is turned on.
  await toggle.waitFor({ state: 'attached', timeout: 10000 }).catch(() => {});
  test.skip(await toggle.count() === 0, 'Klaro toggle button is turned off on this site.');

  await page.keyboard.press('Tab');
  await expect(page.locator('a[href="#main-content"]').first()).toBeFocused();
  await page.keyboard.press('Tab');
  await expect(toggle).toBeFocused();

  // Still opens the consent settings.
  await page.keyboard.press('Enter');
  await expect(page.locator('.klaro .cookie-modal')).toBeVisible();
});
