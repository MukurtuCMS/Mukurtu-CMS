import { test, expect, Page, Route } from '@playwright/test';
import { managerAccount } from '~helpers/a11y-credentials';
import { MANAGER_STATE } from '~helpers/auth-state';

/**
 * The "Search Address" control on the map widget.
 *
 * Leaflet only adds it for users with "access geocoder api endpoints", which
 * no Mukurtu role used to ship with, so only administrators could search.
 * mukurtu_update_40034() grants it to authenticated users, and
 * modules/mukurtu_core's leaflet widget CSS/JS fixes the accessibility gaps
 * that only admins could reach before. #2545 covers the rest: the combobox
 * pattern, failed-lookup and translatable announcements, Down Arrow on the
 * last result, the input border and the locate button's focus ring.
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

  type Geocode = (route: Route) => Promise<void>;
  const withResults = (results: object[]): Geocode => (route) => route.fulfill({ json: results });

  const openSearch = async (page: Page, geocode: Geocode = withResults(RESULTS)) => {
    await page.route('**/geocoder/api/geocode?**', (route) => geocode(route));
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

  test('Map address search: the input and results follow the combobox pattern', async ({ page }) => {
    const input = await openSearch(page);
    await expect(input).toHaveAttribute('role', 'combobox');
    await expect(input).toHaveAttribute('aria-expanded', 'false');

    await input.click();
    await input.pressSequentially('Pullman');
    const listbox = page.locator(`#${await input.getAttribute('aria-controls')}`);
    await expect(listbox).toHaveAttribute('role', 'listbox');
    await expect(listbox.getByRole('option')).toHaveCount(2);
    await expect(input).toHaveAttribute('aria-expanded', 'true');
    // Cancelling jQuery UI's repeat of the highlighted result must not also
    // swallow the result count.
    await expect(page.locator('.ui-helper-hidden-accessible[role="status"]').filter({ hasText: '2 results are available, use up and down arrow keys to navigate.' })).toHaveCount(1);

    // autoFocus highlights the first result; the input must point at it.
    const first = listbox.getByRole('option').first();
    await expect(first).toHaveAttribute('aria-selected', 'true');
    await expect(input).toHaveAttribute('aria-activedescendant', (await first.getAttribute('id'))!);

    await page.keyboard.press('ArrowDown');
    const second = listbox.getByRole('option').nth(1);
    await expect(second).toHaveAttribute('aria-selected', 'true');
    await expect(first).toHaveAttribute('aria-selected', 'false');
    await expect(input).toHaveAttribute('aria-activedescendant', (await second.getAttribute('id'))!);

    await page.keyboard.press('Escape');
    await expect(input).toHaveAttribute('aria-expanded', 'false');
    await expect(input).not.toHaveAttribute('aria-activedescendant');
  });

  test('Map address search: Down Arrow keeps a single result chosen', async ({ page }) => {
    const input = await openSearch(page, withResults(RESULTS.slice(0, 1)));
    await input.click();
    await input.pressSequentially('Pullman');
    const option = page.locator('.mukurtu-geocoder-autocomplete-menu [role="option"]');
    await expect(option).toHaveCount(1);
    await expect(page.locator('.ui-helper-hidden-accessible[role="status"]').filter({ hasText: '1 result is available, use up and down arrow keys to navigate.' })).toHaveCount(1);

    await page.keyboard.press('ArrowDown');
    await expect(option).toHaveAttribute('aria-selected', 'true');
    await page.keyboard.press('Enter');
    await expect(page.locator('#drupal-live-announce')).toContainText('Map moved to Pullman, Washington, United States.');
  });

  test('Map address search: a failed lookup is not announced as no results', async ({ page }) => {
    let fail = true;
    const input = await openSearch(page, (route) => (fail ? route.fulfill({ status: 503, body: '' }) : route.fulfill({ json: [] })));
    // jQuery UI's own live regions, which announce the outcome of a search.
    // Every autocomplete field on the form has one, so match on the text.
    const announced = (text: string) => page.locator('.ui-helper-hidden-accessible[role="status"]').filter({ hasText: text });

    await input.click();
    await input.pressSequentially('Pullman');
    await expect(announced("Address search isn't available right now. Try again later, or add a marker on the map.")).toHaveCount(1);

    // The next successful search with nothing found goes back to the usual
    // message.
    fail = false;
    await input.fill('');
    await input.pressSequentially('Nowhere at all');
    await expect(announced('No search results.')).toHaveCount(1);
    await expect(announced("Address search isn't available right now.")).toHaveCount(0);
  });

  test('Map address search: the input border and the locate button meet contrast and focus requirements', async ({ page }) => {
    const input = await openSearch(page);
    await expect(input).toHaveCSS('border-top-color', 'rgb(89, 89, 89)');

    await page.locator('.leaflet-control-zoom-out').first().focus();
    await page.keyboard.press('Tab');
    await expect(input).toBeFocused();
    await page.keyboard.press('Tab');
    const locate = page.locator('.leaflet-control-locate a').first();
    await expect(locate).toBeFocused();
    await expect(locate).toHaveCSS('outline-style', 'solid');
  });

  test('Map address search: the draw toolbar keeps its focus ring under Gin', async ({ page }) => {
    await openSearch(page);
    // Gin's ":where(*) .page-wrapper *:focus { outline: none }" ties with an
    // unscoped selector and loads later, so this went missing once already.
    const marker = page.locator('.leaflet-pm-toolbar .leaflet-buttons-control-button').first();
    await page.locator('.leaflet-control-locate a').first().focus();
    await page.keyboard.press('Tab');
    await expect(marker).toBeFocused();
    await expect(marker).toHaveCSS('outline-style', 'solid');
  });

  test('Map address search: the search box leaves the zoom buttons uncovered at 320px', async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 700 });
    await openSearch(page);

    for (const selector of ['.leaflet-control-zoom-in', '.leaflet-control-zoom-out']) {
      const button = page.locator(selector).first();
      // elementFromPoint only sees what's in the viewport.
      await button.scrollIntoViewIfNeeded();
      const hit = await button.evaluate((button) => {
        const r = button.getBoundingClientRect();
        // Every corner and the centre must be the button itself.
        return [[r.left + 1, r.top + 1], [r.right - 1, r.top + 1], [r.left + 1, r.bottom - 1], [r.right - 1, r.bottom - 1], [r.left + r.width / 2, r.top + r.height / 2]]
          .every(([x, y]) => button.contains(document.elementFromPoint(x, y)));
      });
      expect(hit, `nothing covers ${selector}`).toBe(true);
    }
  });
});
