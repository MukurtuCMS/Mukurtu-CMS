import { test, expect, Page } from '@playwright/test';
import { managerAccount } from '~helpers/a11y-credentials';
import { MANAGER_STATE } from '~helpers/auth-state';

/**
 * The "Search Address" control on the map widget.
 *
 * Leaflet only adds it for users with "access geocoder api endpoints", which
 * no Mukurtu role used to ship with, so only administrators could search.
 * mukurtu_update_40034() grants it to authenticated users, and
 * modules/mukurtu_core's leaflet widget CSS/JS fixes the accessibility gaps
 * that only admins could reach before.
 *
 * Runs as the manage-adjacent account: a protocol steward with no site roles,
 * so it can open the Digital Heritage form without being an administrator.
 * The CI member account is only a protocol member and can't.
 *
 * The geocoder endpoint is stubbed, so the run never calls Nominatim and the
 * results are the same every time.
 */
test.describe('Map address search', () => {
  test.use({ storageState: MANAGER_STATE });

  const RESULTS = [
    { formatted_address: 'Pullman, Washington, United States', geometry: { location: { lat: 46.7313, lng: -117.1796 } } },
    { formatted_address: 'Pullman, Michigan, United States', geometry: { location: { lat: 42.4767, lng: -86.0947 } } },
  ];

  const openSearch = async (page: Page) => {
    await page.route('**/geocoder/api/geocode?**', (route) => route.fulfill({ json: RESULTS }));
    await page.goto('/node/add/digital_heritage');
    expect(page.url(), 'still on the add form, not redirected to login').not.toContain('/user/login');
    // The map is in the Locations group: a tab on wide screens, an
    // accordion on narrow ones.
    const tab = page.locator("a[href='#edit-group-additional-fields']");
    if (await tab.isVisible()) {
      await tab.click();
    }
    else {
      const closed = page.locator('details:not([open]):has(.leaflet-control-geocoder-container) > summary');
      while (await closed.count()) {
        await closed.first().click();
      }
    }
    const input = page.locator('.leaflet-control-geocoder-container input').first();
    await expect(input).toBeVisible();
    return input;
  };

  test('Map address search: a non-administrator can search', async ({ page }) => {
    test.skip(managerAccount().isFallback, 'No manager credentials: the account falls back to the administrator, who always had the search.');
    await openSearch(page);
  });

  test('Map address search: the input is named by its visible text and shows focus', async ({ page }) => {
    const input = await openSearch(page);

    const placeholder = await input.getAttribute('placeholder');
    expect(placeholder).toBeTruthy();
    await expect(input).toHaveAttribute('aria-label', placeholder!);

    // :focus-visible needs keyboard focus, so arrive by Tab from the
    // control before it rather than calling focus().
    await page.locator('.leaflet-control-zoom-out').first().focus();
    await page.keyboard.press('Tab');
    await expect(input).toBeFocused();
    await expect(input).toHaveCSS('outline-style', 'solid');
  });

  test('Map address search: results fit inside the map at 320px and selection is announced', async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 700 });
    const input = await openSearch(page);

    await input.click();
    await input.pressSequentially('Pullman');
    const menu = page.locator('.mukurtu-geocoder-autocomplete-menu');
    await expect(menu.locator('li').first()).toBeVisible();

    const bounds = await menu.evaluate((m) => {
      const map = m.closest('.leaflet-container')!.getBoundingClientRect();
      const r = m.getBoundingClientRect();
      return { left: r.left - map.left, right: map.right - r.right };
    });
    expect(bounds.left, 'dropdown starts inside the map').toBeGreaterThanOrEqual(0);
    expect(bounds.right, 'dropdown ends inside the map').toBeGreaterThanOrEqual(0);

    await expect(menu.locator('.ui-state-active')).toHaveCSS('outline-style', 'solid');

    await page.keyboard.press('Enter');
    await expect(page.locator('#drupal-live-announce')).toContainText('Map moved to Pullman, Washington, United States.');
  });
});
