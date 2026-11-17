import { test, expect } from '@playwright/test';
import { ADMIN_STATE } from '~helpers/auth-state';

/**
 * Tagify added its suggestion list to the end of <body>, outside every
 * landmark (#2721). mukurtu_core adds it to the field's wrapper instead, and
 * corrects its position there, since Tagify adds the page's scroll offset.
 *
 * Types text that matches no keyword, so the list shows its "No matching
 * suggestions" row on any site, whatever terms it has.
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
    await page.keyboard.type('zqxj');

    const list = page.locator('.tagify__dropdown');
    await expect(list.locator('.tagify__dropdown__item')).toBeVisible();
    expect(await list.evaluate((element) => !!element.closest('main'))).toBe(true);

    const [listBox, fieldBox] = await Promise.all([list.boundingBox(), field.boundingBox()]);
    const gap = (await list.getAttribute('placement')) === 'top'
      ? fieldBox!.y - (listBox!.y + listBox!.height)
      : listBox!.y - (fieldBox!.y + fieldBox!.height);
    expect(Math.abs(gap), `list ${JSON.stringify(listBox)}, field ${JSON.stringify(fieldBox)}`).toBeLessThan(4);
  });

  // In a scrolling container, such as a dialog's content, the list scrolls
  // with the container. Tagify measured the room below the field against the
  // window, so near the container's bottom edge the list opened below the
  // field and was cut off.
  test('Near the bottom of a scrolling container, the list opens above its field', async ({ page }) => {
    await page.goto('/node/add/digital_heritage');
    const tab = page.locator('.horizontal-tab-button a:visible').filter({ hasText: 'Additional' }).first();
    if (await tab.count()) {
      await tab.click();
    }
    const field = page.locator('tags:has(+ input#edit-field-keywords)');
    // Ends the form's main region 10px below the field, scrolling the rest.
    await field.evaluate((element) => {
      const region = element.closest('.layout-region-node-main') as HTMLElement;
      const height = element.getBoundingClientRect().bottom - region.getBoundingClientRect().top + 10;
      region.style.cssText = `block-size: ${height}px; overflow: auto`;
    });
    await field.locator('.tagify__input').click();
    await page.keyboard.type('zqxj');

    const list = page.locator('.tagify__dropdown');
    const option = list.locator('.tagify__dropdown__item');
    await expect(option).toBeVisible();
    await expect(list).toHaveAttribute('placement', 'top');
    // The option is drawn where it is, not hidden by the container's edge.
    // Polled, because the list slides into place as it opens.
    await expect.poll(() => option.evaluate((element) => {
      const box = element.getBoundingClientRect();
      return element.contains(document.elementFromPoint(box.left + 10, box.top + box.height / 2));
    })).toBe(true);
  });
});
