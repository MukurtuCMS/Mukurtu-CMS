import { test, expect, type Page } from '@playwright/test';
import { ADMIN_STATE } from '~helpers/auth-state';
import { gotoReady } from '~helpers/preview';

/**
 * Paragraphs widget fixes in mukurtu_gin_custom:
 *
 * - #2760: the actions (⋮) icon vanished in forced colors.
 * - #2761: the actions menu opened off-screen in right-to-left languages on
 *   narrow screens.
 * - #2762: the classic widget's label (Knowledge Keepers) didn't wrap, so at
 *   320px it ran under the Remove button and the page scrolled sideways.
 *
 * #2760 and #2761 use the Person form's Biography sections, which every site
 * has.
 */

async function openBiography(page: Page) {
  await gotoReady(page, '/node/add/person');
  const tab = page.locator('a[href="#edit-group-biography"]:visible').first();
  if (await tab.count()) {
    await tab.click();
  }
  return page.locator('.field--name-field-sections').first();
}

test.describe('Paragraphs widget', () => {
  test.use({ storageState: ADMIN_STATE });

  test('Forced colors: the actions button shows its icon', async ({ page }) => {
    const field = await openBiography(page);
    const toggle = field.locator('.paragraphs-dropdown-toggle').first();
    await expect(toggle).toBeVisible();
    await page.emulateMedia({ forcedColors: 'active' });

    // What ButtonText resolves to on this page.
    const buttonText = await page.evaluate(() => {
      const probe = document.createElement('div');
      probe.style.cssText = 'forced-color-adjust: none; background: ButtonText;';
      document.body.append(probe);
      const color = getComputedStyle(probe).backgroundColor;
      probe.remove();
      return color;
    });
    const iconColor = () => toggle.evaluate((element) => getComputedStyle(element, '::after').backgroundColor);

    expect(await iconColor(), 'the icon at rest').toBe(buttonText);
    await toggle.focus();
    expect(await iconColor(), 'the icon on focus').toBe(buttonText);
  });

  for (const dir of ['rtl', 'ltr']) {
    test(`Reflow: the actions menu opens on screen at 320px (${dir})`, async ({ page }) => {
      const field = await openBiography(page);
      await page.evaluate((value) => document.documentElement.setAttribute('dir', value), dir);
      await page.setViewportSize({ width: 320, height: 720 });

      const toggle = field.locator('.paragraphs-dropdown-toggle').first();
      await toggle.scrollIntoViewIfNeeded();
      await toggle.click();
      const menu = field.locator('.paragraphs-dropdown.open .paragraphs-dropdown-actions').first();
      await expect(menu).toBeVisible();

      const layout = await menu.evaluate((element) => {
        const box = element.getBoundingClientRect();
        return {
          left: box.left,
          right: box.right,
          viewport: window.innerWidth,
          page: document.documentElement.scrollWidth,
        };
      });
      expect(layout.left, 'the menu starts on screen').toBeGreaterThanOrEqual(0);
      expect(layout.right, 'the menu ends on screen').toBeLessThanOrEqual(layout.viewport);
      expect(layout.page, 'the page does not scroll sideways').toBeLessThanOrEqual(layout.viewport);
    });
  }

  test('Reflow: the Knowledge Keepers label wraps clear of the Remove button at 320px', async ({ page }) => {
    await gotoReady(page, '/node/add/digital_heritage');
    const tab = page.locator('a[href="#edit-group-rights-and-permissions"]:visible').first();
    if (await tab.count()) {
      await tab.click();
    }
    await page.setViewportSize({ width: 320, height: 720 });

    const top = page.locator('.field--name-field-knowledge-keepers .paragraph-type-top').first();
    const title = top.locator('.paragraph-type-title');
    await title.scrollIntoViewIfNeeded();
    await expect(title).toBeVisible();

    // Compare the text itself, not its box, which a nowrap label overflows.
    const layout = await title.evaluate((element) => {
      const range = document.createRange();
      range.selectNodeContents(element);
      const text = range.getBoundingClientRect();
      const button = element.parentElement!.querySelector('.paragraphs-dropbutton-wrapper')!.getBoundingClientRect();
      return {
        textRight: text.right,
        textLeft: text.left,
        buttonLeft: button.left,
        viewport: window.innerWidth,
        page: document.documentElement.scrollWidth,
      };
    });
    expect(layout.textLeft, 'the label starts on screen').toBeGreaterThanOrEqual(0);
    expect(layout.textRight, 'the label stays clear of the Remove button').toBeLessThanOrEqual(layout.buttonLeft);
    expect(layout.page, 'the page does not scroll sideways').toBeLessThanOrEqual(layout.viewport);
  });
});
