import { test, expect, type Page } from '@playwright/test';
import { ADMIN_STATE } from '~helpers/auth-state';
import { gotoReady } from '~helpers/preview';

/**
 * On Gin pages, the Tagify suggestion picked with the arrow keys was marked
 * only by its background, which forced colors removes, so it looked like the
 * others (WCAG 2.4.7, #2744). It now uses the system highlight colors.
 *
 * The term merge form's picker lists every category term on Down Arrow, and
 * a fresh site has at least two.
 */

const MERGE_URL = '/admin/structure/taxonomy/manage/category/merge';
const SLOW = { timeout: 20000 };

async function colorsOf(page: Page) {
  const textbox = page.locator('tags.tagify .tagify__input');
  const options = page.locator('.tagify__dropdown [role=option]');
  const active = page.locator('.tagify__dropdown .tagify__dropdown__item--active');

  await textbox.focus();
  await page.keyboard.press('ArrowDown');
  await expect(options.nth(1), 'the list opens with at least two terms').toBeVisible(SLOW);
  await expect(active).toHaveCount(1);

  const style = (element: Element) => {
    const computed = getComputedStyle(element);
    return { background: computed.backgroundColor, color: computed.color };
  };
  const other = page.locator('.tagify__dropdown [role=option]:not(.tagify__dropdown__item--active)').first();
  return { active: await active.evaluate(style), other: await other.evaluate(style) };
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

      const { active, other } = await colorsOf(page);
      expect(active.background, 'the highlight has a fill').not.toBe('rgba(0, 0, 0, 0)');
      expect(active.background, 'the highlight differs from the other suggestions').not.toBe(other.background);
      expect(active.color, 'its text differs from its fill').not.toBe(active.background);
    });
  }
});
