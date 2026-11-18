import { test, expect, type Page } from '@playwright/test';
import { ADMIN_STATE } from '~helpers/auth-state';
import { gotoReady } from '~helpers/preview';

/**
 * On Gin pages, the Tagify suggestion picked with the arrow keys was marked
 * only by its background, which forced colors removes, so it looked like the
 * others (WCAG 2.4.7, #2744). It now uses the system highlight colors.
 *
 * The term merge form's picker lists every category term on Down Arrow. The
 * highlighted one must use the system Highlight and HighlightText colors.
 * Comparing it with the other suggestions isn't enough: in dark mode
 * Chromium reports different background values that forced colors paints
 * the same.
 */

const MERGE_URL = '/admin/structure/taxonomy/manage/category/merge';
const SLOW = { timeout: 20000 };

async function colorsOf(page: Page) {
  const textbox = page.locator('tags.tagify .tagify__input');
  const active = page.locator('.tagify__dropdown .tagify__dropdown__item--active');

  await textbox.focus();
  await page.keyboard.press('ArrowDown');
  await expect(active, 'the list opens with the first term highlighted').toBeVisible(SLOW);

  const style = (element: Element) => {
    const computed = getComputedStyle(element);
    return { background: computed.backgroundColor, color: computed.color };
  };
  // What the system colors resolve to on this page.
  const system = await page.evaluate(() => {
    const probe = document.createElement('div');
    probe.style.cssText = 'forced-color-adjust: none; background: Highlight; color: HighlightText;';
    document.body.append(probe);
    const computed = getComputedStyle(probe);
    const colors = { background: computed.backgroundColor, color: computed.color };
    probe.remove();
    return colors;
  });
  return { active: await active.evaluate(style), system };
}

test.describe('Gin Tagify highlight in forced colors', () => {
  test.use({ storageState: ADMIN_STATE });

  for (const dark of [false, true]) {
    test(`The highlighted suggestion stands out (${dark ? 'dark' : 'light'} mode)`, async ({ page }) => {
      await page.emulateMedia({ forcedColors: 'active' });
      await gotoReady(page, MERGE_URL);
      await page.waitForLoadState('networkidle');
      await expect(page.locator('body')).toHaveAttribute('data-gin-layout-density');
      if (dark) {
        await page.evaluate(() => document.documentElement.classList.add('gin--dark-mode'));
      }

      const { active, system } = await colorsOf(page);
      expect(system.background).not.toBe(system.color);
      expect(active).toEqual(system);
    });
  }
});
