import { test, expect, Page } from "@playwright/test";
import { gotoReady } from "~helpers/preview";

/**
 * Check the browsing experience (Grid/List/Map) for Digital Heritage items.
 */
test('Browse tests - Digital Heritage', async ({ page, browserName }) => {
  await gotoReady(page, '/digital-heritage');
  // /digital-heritage renders the list, grid, and map view displays all at
  // once (see issue #2011), which can take longer than the default 5s
  // actionTimeout to become clickable on resource-constrained CI runners.
  await page.getByText('Grid', { exact: true }).click({ timeout: 15000 });
  await page.getByText('List', { exact: true }).click({ timeout: 15000 });
  // @todo Re-enable Map clicks when there's default content with location data.
  //await page.getByText('Map', { exact: true }).click();
  // @todo Check default content within each tab.
});

/**
 * Regression test for issue #2001: decorative card media links are marked
 * aria-hidden="true" to avoid a screen reader announcing the card's
 * destination twice (once for the image, once for the title text), so they
 * must also carry tabindex="-1" to stay out of the tab order. See
 * themes/mukurtu_v4/templates/content/_card-media.html.twig.
 */
test('Browse tests - decorative card links are not keyboard-focusable', async ({ page }) => {
  await gotoReady(page, '/browse');
  const untabbableAriaHiddenLinks = page.locator('a[aria-hidden="true"]:not([tabindex="-1"])');
  await expect(untabbableAriaHiddenLinks).toHaveCount(0);
});

/**
 * Regression test for issue #1569: the theme hides the native <details>
 * marker on sidebar facet headers, so each <summary> carries a plus/minus
 * glyph instead (themes/mukurtu_v4/components/04-templates/browse-pages/
 * _browse.scss). It must show "+" when collapsed and "−" when expanded.
 */
test('Browse tests - facet headers show a collapse indicator', async ({ page }) => {
  await gotoReady(page, '/browse');
  const details = page.locator('.browse-facets details.form-item').first();
  const summary = details.locator('> summary');
  await expect(summary).toBeVisible();

  const indicator = () => summary.evaluate((el) => getComputedStyle(el, '::after').content);
  const isOpen = () => details.evaluate((el) => (el as HTMLDetailsElement).open);

  // Facets are configured open_by_default.
  expect(await isOpen()).toBe(true);
  expect(await indicator()).toContain('−');

  await summary.click();
  expect(await isOpen()).toBe(false);
  expect(await indicator()).toContain('+');

  await summary.focus();
  await page.keyboard.press('Enter');
  expect(await isOpen()).toBe(true);
  expect(await indicator()).toContain('−');
});
