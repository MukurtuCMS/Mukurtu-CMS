import { test, expect, Page } from "@playwright/test";

/**
 * Check the browsing experience (Grid/List/Map) for Digital Heritage items.
 */
test('Browse tests - Digital Heritage', async ({ page, browserName }) => {
  await page.goto('/digital-heritage');
  await page.getByText('Grid', { exact: true }).click();
  await page.getByText('List', { exact: true }).click();
  // @todo Re-enable Map clicks when there's default content with location data.
  //await page.getByText('Map', { exact: true }).click();
  // @todo Check default content within each tab.
});
