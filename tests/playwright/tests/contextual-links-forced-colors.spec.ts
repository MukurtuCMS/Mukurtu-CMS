import { test, expect } from '@playwright/test';
import { ADMIN_STATE } from '~helpers/auth-state';

/**
 * The pencil buttons of contextual links show focus with a box-shadow ring
 * and a blue icon, and forced colors removes both, so a focused button
 * looked like an unfocused one (WCAG 2.4.7, #2740). The public theme gives
 * them an outline in forced colors.
 */
test.describe('Contextual links', () => {
  test.use({ storageState: ADMIN_STATE });

  test('A focused contextual link button keeps an outline in forced colors', async ({ page }) => {
    await page.emulateMedia({ forcedColors: 'active' });
    await page.goto('/');
    await expect(page.locator('body')).not.toHaveAttribute('data-gin-layout-density');
    // Contextual links build their buttons from JavaScript.
    const trigger = page.locator('.contextual button.trigger').first();
    await trigger.waitFor({ state: 'attached' });
    await trigger.focus();
    await page.keyboard.press('Shift+Tab');
    await page.keyboard.press('Tab');
    await expect(trigger).toBeFocused();
    await expect(trigger).toHaveCSS('outline-style', 'solid');

    // The menu the button opens: its links' focus ring is a box-shadow too.
    await page.keyboard.press('Enter');
    await expect(trigger).toHaveAttribute('aria-pressed', 'true');
    await page.keyboard.press('Tab');
    const link = page.locator('.contextual-links a:focus');
    await expect(link).toHaveCount(1);
    await expect(link).toHaveCSS('outline-style', 'solid');
  });
});
