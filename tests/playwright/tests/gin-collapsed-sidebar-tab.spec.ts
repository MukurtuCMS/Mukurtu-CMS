import { test, expect } from '@playwright/test';
import { Login } from '~components/login';
import { adminAccount } from '~helpers/a11y-credentials';

/**
 * Tab must not get stuck on "Expand sidebar" in Gin's collapsed sidebar.
 *
 * Gin's navigation.js sends Tab from "Expand sidebar" to the sidebar's user
 * menu button, which Gin's own CSS hides, so focus never moved and keyboard
 * users could not reach the top bar or the page. Mukurtu collapses the sidebar
 * by default. Fixed by mukurtu_gin_custom's gin-collapsed-tab-trap.js.
 */
test.describe('Gin collapsed sidebar keyboard navigation', () => {
  test.beforeEach(async ({ page }) => {
    const account = adminAccount();
    const login = new Login(page);
    await login.login(account.username, account.password);
  });

  test('Tab moves past "Expand sidebar" into the page', async ({ page }) => {
    await page.goto('/admin/content');
    await page.evaluate(() => localStorage.setItem('Drupal.navigation.sidebarExpanded', 'false'));
    await page.reload();

    await expect(page.locator('html')).not.toHaveClass(/admin-toolbar-expanded/);
    const toggle = page.locator('.admin-toolbar [aria-controls="admin-toolbar"]');
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');

    await toggle.focus();
    await page.keyboard.press('Tab');

    const focus = await page.evaluate(() => {
      const el = document.activeElement;
      return {
        inSidebar: !!el?.closest('.admin-toolbar'),
        isBody: el === document.body,
      };
    });
    expect(focus.inSidebar, 'Focus is still in the sidebar after Tab on "Expand sidebar".').toBe(false);
    expect(focus.isBody, 'Focus was lost to the page body.').toBe(false);
  });
});
