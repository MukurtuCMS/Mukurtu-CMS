import { test, expect, type Page } from '@playwright/test';
import { ADMIN_STATE } from '~helpers/auth-state';
import { gotoReady } from '~helpers/preview';

/**
 * With WCAG 1.4.12 text spacing applied, node forms scrolled sideways at
 * 320px (#2763):
 *
 * - On every node form, a hidden Gin tooltip in the sticky Save bar grew with
 *   the letter spacing but kept its old position, so it ran past the edge.
 * - On the dictionary word form, the Translation table was wider than the
 *   page. It now scrolls inside its own box.
 */

const TEXT_SPACING = `
  * {
    line-height: 1.5 !important;
    letter-spacing: 0.12em !important;
    word-spacing: 0.16em !important;
  }
  p {
    margin-bottom: 2em !important;
  }
`;

async function pageWidth(page: Page) {
  return page.evaluate(() => ({
    page: document.documentElement.scrollWidth,
    viewport: document.documentElement.clientWidth,
  }));
}

test.describe('Text spacing (admin)', () => {
  test.use({ storageState: ADMIN_STATE, viewport: { width: 320, height: 720 } });

  for (const path of ['/node/add/person', '/node/add/dictionary_word']) {
    test(`${path} doesn't scroll sideways at 320px`, async ({ page }) => {
      await gotoReady(page, path);
      await page.addStyleTag({ content: TEXT_SPACING });
      await expect.poll(async () => {
        const { page: width, viewport } = await pageWidth(page);
        return width <= viewport;
      }, { message: 'the page is no wider than the viewport' }).toBe(true);
    });
  }

  test('The sidebar tooltip still shows on hover and focus', async ({ page }) => {
    await gotoReady(page, '/node/add/person');
    await page.addStyleTag({ content: TEXT_SPACING });
    const trigger = page.locator('.meta-sidebar__trigger').first();
    const tooltip = trigger.locator('xpath=following-sibling::*[1][contains(@class, "gin-tooltip")]');

    await expect(tooltip).toBeHidden();
    await trigger.hover();
    await expect(tooltip).toBeVisible();
    const box = await tooltip.boundingBox();
    const { viewport } = await pageWidth(page);
    expect(box!.x, 'the tooltip starts on screen').toBeGreaterThanOrEqual(0);
    expect(box!.x + box!.width, 'the tooltip ends on screen').toBeLessThanOrEqual(viewport);

    await page.mouse.move(0, 700);
    await expect(tooltip).toBeHidden();
    await trigger.focus();
    await expect(tooltip).toBeVisible();
  });
});
