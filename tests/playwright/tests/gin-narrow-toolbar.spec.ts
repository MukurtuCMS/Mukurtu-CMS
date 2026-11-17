import { test, expect } from '@playwright/test';
import { ADMIN_STATE } from '~helpers/auth-state';

/**
 * Gin's toolbar on narrow screens (#2742, #2743).
 *
 * - Its logo link has no visible content. Gin hides it, but core's toolbar
 *   CSS outranked that, leaving a 0px tall link in the tab order.
 * - Its icons are a fill cut to shape with a mask, and forced colors
 *   repainted the fill to match the bar, so they vanished.
 */
test.describe('Gin narrow toolbar', () => {
  test.use({ storageState: ADMIN_STATE, viewport: { width: 400, height: 700 } });

  test('The empty logo link is hidden and out of the tab order', async ({ page }) => {
    await page.goto('/admin/content');
    await expect(page.locator('#toolbar-item-administration')).toBeVisible();
    const logo = page.locator('a.toolbar-menu__logo');
    await expect(logo).toHaveCount(1);
    await expect(logo).toHaveCSS('display', 'none');
  });

  test('Toolbar icons keep a visible fill in forced colors', async ({ page }) => {
    await page.emulateMedia({ forcedColors: 'active' });
    await page.goto('/admin/content');
    const manage = page.locator('#toolbar-item-administration');
    await expect(manage).toBeVisible();
    const [fill, bar] = await manage.evaluate((element) => [
      getComputedStyle(element, '::before').backgroundColor,
      getComputedStyle(element.closest('.toolbar-bar')!).backgroundColor,
    ]);
    // The icon is drawn in its fill color, which must differ from the bar.
    expect(fill).not.toBe(bar);
    expect(fill).not.toMatch(/rgba\(255, 255, 255/);
  });
});
