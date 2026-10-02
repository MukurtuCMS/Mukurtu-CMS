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

// The logo is a fixed 100px but the gutters and padding are rem, so a larger
// phone text size (Chrome on Android scales rem with it) squeezed the
// header's columns until the logo covered the search box. 150% root font
// size stands in for that setting.
for (const width of [320, 412, 600]) {
  for (const rootFontSize of ['100%', '150%']) {
    test(`Reflow: mobile header logo, search and menu button do not overlap at ${width}px, ${rootFontSize} text`, async ({ page }) => {
      await page.setViewportSize({ width, height: 720 });
      await page.goto('/');
      await page.addStyleTag({ content: `html { font-size: ${rootFontSize} !important; }` });

      const search = page.locator('.header-search--mobile');
      test.skip(await search.count() === 0, 'Header search is turned off on this site.');
      // With the always-mobile-nav setting the search box gets its own row.
      test.skip(
        await page.locator('body.is-always-mobile-nav').count() > 0,
        'Header search is on its own row on this site.'
      );
      const logoImage = page.locator('.header__logo img');
      test.skip(await logoImage.count() === 0, 'No site branding block in the header.');

      const logo = await logoImage.boundingBox();
      const searchBox = await search.boundingBox();
      const menuButton = await page.locator('.mobile-nav-button').boundingBox();
      expect(logo).not.toBeNull();
      expect(searchBox).not.toBeNull();
      expect(menuButton).not.toBeNull();

      // Allow 1px for subpixel rounding.
      expect(logo!.x + logo!.width).toBeLessThanOrEqual(searchBox!.x + 1);
      expect(searchBox!.x + searchBox!.width).toBeLessThanOrEqual(menuButton!.x + 1);
    });
  }
}

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
