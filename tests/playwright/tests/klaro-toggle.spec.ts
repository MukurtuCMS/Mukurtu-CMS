import { test, expect } from "@playwright/test";

/**
 * Klaro's own high-contrast styles swap its consent toggle button to a white
 * icon but keep the white background, leaving a blank white circle. The
 * mukurtu_v4 theme gives the button a dark fill in that mode (see
 * _consent-popup.scss).
 */
for (const contrast of ['no-preference', 'more'] as const) {
  test(`Klaro toggle button icon contrasts with its background (prefers-contrast: ${contrast})`, async ({ page }) => {
    await page.emulateMedia({ contrast });
    await page.goto('/');

    const toggle = page.locator('#klaro_toggle_dialog');
    // Klaro adds the button from JS, and only when the toggle is turned on.
    await toggle.waitFor({ timeout: 10000 }).catch(() => {});
    test.skip(await toggle.count() === 0, 'Klaro toggle button is turned off on this site.');

    const { backgroundColor, backgroundImage } = await toggle.evaluate((el) => {
      const style = getComputedStyle(el);
      return { backgroundColor: style.backgroundColor, backgroundImage: style.backgroundImage };
    });
    const whiteIcon = backgroundImage.includes('privacy-white.svg');
    expect(whiteIcon).toBe(contrast === 'more');
    // A white icon needs a dark button; the default dark icon sits on white.
    expect(backgroundColor).toBe(whiteIcon ? 'rgb(0, 0, 0)' : 'rgb(255, 255, 255)');
  });
}
