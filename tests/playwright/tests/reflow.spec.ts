import { test, expect } from "@playwright/test";

/**
 * Regression tests for issue #1997: content requiring horizontal scroll at
 * a 320px viewport width (WCAG 1.4.10 Reflow).
 *
 * Two categories of fix are covered:
 * - A real reflow check on pages that need no special content, confirming
 *   the sitewide header search box fix.
 * - Direct computed-style assertions for fixes whose overflow only
 *   manifests with specific content (a long/unbreakable title) that isn't
 *   guaranteed to exist in every environment's seeded content.
 */

const NO_CONTENT_DEPENDENCY_PAGES = ['/', '/browse', '/user/login'];

for (const path of NO_CONTENT_DEPENDENCY_PAGES) {
  test(`Reflow: no horizontal overflow at 320px on ${path}`, async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 720 });
    await page.goto(path);
    const overflow = await page.evaluate(() => ({
      scrollWidth: document.documentElement.scrollWidth,
      clientWidth: document.documentElement.clientWidth,
    }));
    expect(overflow.scrollWidth).toBeLessThanOrEqual(overflow.clientWidth + 1);
  });
}

test('Reflow: header search input can shrink to fit the mobile header grid', async ({ page }) => {
  await page.goto('/');
  // Two instances render (mobile and desktop, see _header-search.scss);
  // the mobile one is what overflows the narrow header grid column.
  const minInlineSize = await page.locator('.header-search--mobile .header-search__input').evaluate(
    (el) => getComputedStyle(el).minWidth
  );
  expect(minInlineSize).toBe('0px');
});

test('Reflow: page title and breadcrumb allow mid-word breaks for long unbreakable titles', async ({ page }) => {
  await page.goto('/browse');
  const titleOverflowWrap = await page.locator('.page__title h1').first().evaluate(
    (el) => getComputedStyle(el).overflowWrap
  );
  expect(titleOverflowWrap).toBe('anywhere');

  const breadcrumbOverflowWrap = await page.locator('.breadcrumb__list-item').first().evaluate(
    (el) => getComputedStyle(el).overflowWrap
  );
  expect(breadcrumbOverflowWrap).toBe('anywhere');
});
