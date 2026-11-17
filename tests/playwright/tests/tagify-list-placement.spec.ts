import { test, expect } from '@playwright/test';
import { ADMIN_STATE } from '~helpers/auth-state';

/**
 * Tagify added its suggestion list to the end of <body>, outside every
 * landmark (#2721). mukurtu_core adds it to the field's wrapper instead, and
 * corrects its position there, since Tagify adds the page's scroll offset.
 *
 * Uses the "weaving" keyword from the accessibility demo content.
 */
test.describe('Tagify suggestion list placement', () => {
  test.use({ storageState: ADMIN_STATE });

  test('The list opens inside the main landmark, right under its field', async ({ page }) => {
    await page.goto('/node/add/digital_heritage');
    const tab = page.locator('.horizontal-tab-button a:visible').filter({ hasText: 'Additional' }).first();
    if (await tab.count()) {
      await tab.click();
    }
    const field = page.locator('tags:has(+ input#edit-field-keywords)');
    const input = field.locator('.tagify__input');
    // Scrolled down the page, where Tagify's own position was wrong.
    await input.scrollIntoViewIfNeeded();
    expect(await page.evaluate(() => window.scrollY)).toBeGreaterThan(0);
    await input.click();
    await page.keyboard.type('wea');

    const list = page.locator('.tagify__dropdown');
    await expect(list.locator('.tagify__dropdown__item', { hasText: 'weaving' })).toBeVisible();
    expect(await list.evaluate((element) => !!element.closest('main'))).toBe(true);

    const [listBox, fieldBox] = await Promise.all([list.boundingBox(), field.boundingBox()]);
    const gap = (await list.getAttribute('placement')) === 'top'
      ? fieldBox!.y - (listBox!.y + listBox!.height)
      : listBox!.y - (fieldBox!.y + fieldBox!.height);
    expect(Math.abs(gap), `list ${JSON.stringify(listBox)}, field ${JSON.stringify(fieldBox)}`).toBeLessThan(4);
  });
});
