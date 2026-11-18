import { test, expect } from '@playwright/test';
import { ADMIN_STATE } from '~helpers/auth-state';

/**
 * Gin lays a copy of a table's header row over the real one, so the header
 * stays pinned while the page scrolls. Keyboard users reached every header
 * control twice, once in the copy and once in the real row hidden under it,
 * where its focus could not be seen (WCAG 2.4.11, #2741). The copy is now for
 * mouse users only.
 */
test.describe('Gin sticky table header', () => {
  test.use({ storageState: ADMIN_STATE });

  test('Header controls are reached once by Tab, with their focus in view', async ({ page }) => {
    await page.goto('/admin/content');
    const copy = page.locator('table.gin--sticky-table-header');
    await expect(copy).toBeVisible();
    await expect(copy).toHaveAttribute('aria-hidden', 'true');

    const realHeader = page.locator('.gin-table-scroll-wrapper table thead');
    const sortLinks = realHeader.locator('a[href]');
    await expect(sortLinks.first()).toBeVisible();

    // Core's tableselect adds the select-all checkbox to both rows after
    // load; wait for it, then check nothing in the copy takes Tab.
    await expect(copy.locator('th.select-all input')).toHaveCount(1);
    await expect.poll(() => copy.evaluate((element) =>
      [...element.querySelectorAll('a[href], button, input, select, textarea, [tabindex]')]
        .filter((control) => control.getAttribute('tabindex') !== '-1').length,
    )).toBe(0);

    // Tab from the select-all checkbox through the sort links: each lands in
    // the real row, and the copy steps aside so the focus shows.
    const selectAll = realHeader.locator('th.select-all input');
    await selectAll.focus();
    await page.keyboard.press('Shift+Tab');
    await page.keyboard.press('Tab');
    await expect(selectAll).toBeFocused();
    await expect(copy).toHaveCSS('visibility', 'hidden');

    const count = await sortLinks.count();
    for (let i = 0; i < count; i++) {
      await page.keyboard.press('Tab');
      await expect(sortLinks.nth(i)).toBeFocused();
      await expect(copy).toHaveCSS('visibility', 'hidden');
    }

    // Leaving the header brings the copy back.
    await page.keyboard.press('Tab');
    await expect(realHeader.locator(':focus')).toHaveCount(0);
    await expect(copy).toHaveCSS('visibility', 'visible');
  });

  test('The copy still takes clicks', async ({ page }) => {
    await page.goto('/admin/content');
    const link = page.locator('table.gin--sticky-table-header thead a[href*="order=title"]');
    await expect(link).toBeVisible();
    await link.click();
    await expect(page).toHaveURL(/order=title/);
  });
});
