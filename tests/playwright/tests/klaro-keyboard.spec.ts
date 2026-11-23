import { test, expect, Page } from '@playwright/test';

/**
 * Keyboard use of Klaro's consent settings (#2747).
 *
 * - Klaro added its consent settings button as the first element of
 *   <body>, ahead of "Skip to main content". It now comes after it.
 * - Its consent window is a modal dialog, but Tab left it while it stayed
 *   open, and Escape didn't close it. Focus now stays inside, starts on the
 *   first control, and returns to the button when the window closes.
 */
async function openPage(page: Page) {
  await page.goto('/');
  const toggle = page.locator('#klaro_toggle_dialog');
  // Klaro adds the button from JS, and only when the toggle is turned on.
  await toggle.waitFor({ state: 'attached', timeout: 10000 }).catch(() => {});
  test.skip(await toggle.count() === 0, 'Klaro toggle button is turned off on this site.');
  return toggle;
}

test('The skip link comes before the consent settings button', async ({ page }) => {
  const toggle = await openPage(page);
  await page.keyboard.press('Tab');
  await expect(page.locator('a[href="#main-content"]').first()).toBeFocused();
  await page.keyboard.press('Tab');
  await expect(toggle).toBeFocused();
});

test('Focus stays in the consent window and returns to the button', async ({ page }) => {
  const toggle = await openPage(page);
  await toggle.focus();
  await page.keyboard.press('Enter');
  const window = page.locator('.klaro .cm-modal[aria-modal="true"]');
  await expect(window).toBeVisible();

  // Opens on its first control, the Close button.
  const close = window.locator('.hide');
  await expect(close).toBeFocused();
  // Klaro's own ring is white on the window's near-white background.
  await expect(close).toHaveCSS('outline-style', 'solid');

  // Shift+Tab from the first control wraps to the last, and Tab from the
  // last wraps back, so focus never leaves while the window is open.
  await page.keyboard.press('Shift+Tab');
  await expect(window.locator(':focus')).toHaveCount(1);
  await page.keyboard.press('Tab');
  await expect(close).toBeFocused();
  for (let i = 0; i < 20; i++) {
    await page.keyboard.press('Tab');
    await expect(window.locator(':focus'), `Tab ${i + 1}`).toHaveCount(1);
  }

  // Escape closes it, and focus goes back to the button.
  await page.keyboard.press('Escape');
  await expect(window).toHaveCount(0);
  await expect(toggle).toBeFocused();
});
