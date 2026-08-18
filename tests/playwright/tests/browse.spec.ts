import { test, expect, Page } from "@playwright/test";

/**
 * Check the browsing experience (Grid/List/Map) for Digital Heritage items.
 */
test('Browse tests - Digital Heritage', async ({ page, browserName }) => {
  await page.goto('/digital-heritage');
  // /digital-heritage renders the list, grid, and map view displays all at
  // once (see issue #2011), which can take longer than the default 5s
  // actionTimeout to become clickable on resource-constrained CI runners.
  await page.getByText('Grid', { exact: true }).click({ timeout: 15000 });
  await page.getByText('List', { exact: true }).click({ timeout: 15000 });
  // @todo Re-enable Map clicks when there's default content with location data.
  //await page.getByText('Map', { exact: true }).click();
  // @todo Check default content within each tab.
});
