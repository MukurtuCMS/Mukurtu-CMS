import { test, expect, type Locator, type Page } from '@playwright/test';
import { ADMIN_STATE } from '~helpers/auth-state';

/**
 * Closing the media library or an entity browser without choosing anything
 * left focus on the page body, so keyboard and screen reader users lost their
 * place in the form (WCAG 2.4.3, #2772). Focus now goes back to the button
 * that opened the dialog.
 */

type Opened = { dialog: Locator; opener: Locator };

async function openWithKeyboard(page: Page, opener: Locator, dialogSelector: string): Promise<Opened> {
  const selector = await opener.getAttribute('data-drupal-selector');
  await opener.focus();
  await page.keyboard.press('Enter');
  const dialog = page.locator(dialogSelector).last();
  await expect(dialog).toBeVisible({ timeout: 20000 });
  // Focus moves into the dialog once it has loaded.
  await expect.poll(() => dialog.evaluate((element) => element.contains(document.activeElement))).toBe(true);
  return { dialog, opener: page.locator(`[data-drupal-selector="${selector}"]`) };
}

const dialogs: Record<string, (page: Page) => Promise<Opened>> = {
  'media library': async (page) => {
    await page.goto('/node/add/digital_heritage');
    const opener = page.locator('input.js-media-library-open-button:visible').first();
    return openWithKeyboard(page, opener, '.ui-dialog.media-library-widget-modal:visible');
  },
  'entity browser': async (page) => {
    await page.goto('/node/add/digital_heritage');
    // The Related Content field sits in its own form tab.
    await page.locator('a[href="#edit-group-related-content"]').first().click();
    const opener = page.locator('[data-drupal-selector="edit-field-related-content-entity-browser-entity-browser-open-modal"]');
    await expect(opener).toBeVisible();
    return openWithKeyboard(page, opener, '.ui-dialog:has(iframe.entity-browser-modal-iframe)');
  },
};

test.describe('Field dialog close focus', () => {
  test.use({ storageState: ADMIN_STATE });

  for (const [name, open] of Object.entries(dialogs)) {
    test(`Escape on the ${name} returns focus to its button`, async ({ page }) => {
      const { dialog, opener } = await open(page);
      await page.keyboard.press('Escape');
      await expect(dialog).toBeHidden();
      await expect(opener).toBeFocused();
    });

    test(`The ${name}'s Close button returns focus to its button`, async ({ page }) => {
      const { dialog, opener } = await open(page);
      await dialog.locator('.ui-dialog-titlebar-close').focus();
      await page.keyboard.press('Enter');
      await expect(dialog).toBeHidden();
      await expect(opener).toBeFocused();
    });
  }
});
