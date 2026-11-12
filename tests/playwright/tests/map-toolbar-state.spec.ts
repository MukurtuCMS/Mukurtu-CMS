import { test, expect, Page } from '@playwright/test';
import { ADMIN_STATE } from '~helpers/auth-state';

/**
 * Screen reader state on the Map Points widget (#2687).
 *
 * Leaflet-Geoman marks the active drawing or editing mode only with an
 * "active" class on a wrapping div, so modules/mukurtu_core's
 * mukurtu-leaflet-widget.js copies it onto each focusable toolbar button as
 * aria-pressed. It also gives the map container role="region", so its name,
 * "Interactive location map", is announced.
 *
 * Buttons are found by Geoman's icon classes, not their titles, so the test
 * doesn't depend on the toolbar's wording.
 */
test.describe('Map toolbar state', () => {
  test.use({ storageState: ADMIN_STATE });

  const openMap = async (page: Page) => {
    await page.goto('/node/add/digital_heritage');
    // The map is in the Locations group: a tab on wide screens, an
    // accordion on narrow ones.
    const tab = page.locator("a[href='#edit-group-additional-fields']");
    if (await tab.isVisible()) {
      await tab.click();
    }
    else {
      const closed = page.locator('details:not([open]):has(.leaflet-pm-toolbar) > summary');
      while (await closed.count()) {
        await closed.first().click();
      }
    }
    await expect(page.locator('.leaflet-pm-toolbar').first()).toBeVisible();
  };

  const toolbarButton = (page: Page, icon: string) =>
    page.locator(`.button-container:has(.leaflet-pm-icon-${icon}) a.leaflet-buttons-control-button`).first();

  test('Map toolbar state: mode buttons report whether they are on', async ({ page }) => {
    await openMap(page);
    const edit = toolbarButton(page, 'edit');
    const marker = toolbarButton(page, 'marker');

    await expect(edit).toHaveAttribute('aria-pressed', 'false');
    await expect(marker).toHaveAttribute('aria-pressed', 'false');

    await edit.focus();
    await page.keyboard.press('Enter');
    await expect(edit).toHaveAttribute('aria-pressed', 'true');

    // Switching to another mode turns the first one off.
    await marker.click();
    await expect(marker).toHaveAttribute('aria-pressed', 'true');
    await expect(edit).toHaveAttribute('aria-pressed', 'false');

    // Escape leaves the mode.
    await page.keyboard.press('Escape');
    await expect(marker).toHaveAttribute('aria-pressed', 'false');
  });

  test('Map toolbar state: the map is a named region', async ({ page }) => {
    await openMap(page);
    await expect(page.getByRole('region', { name: 'Interactive location map' })).toBeVisible();
  });
});
