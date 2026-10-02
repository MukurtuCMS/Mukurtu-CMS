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
// text size squeezed the header's columns until the logo covered the search
// box, and at very large sizes the search button slid under the menu button.
// The browser's default font size stands in for the phone's text size
// setting; unlike a root font-size override, it also moves rem media
// queries, which is how the header decides to put search on its own row.
type Box = { x: number; y: number; width: number; height: number };
// Allow 1px for subpixel rounding.
const overlaps = (a: Box, b: Box) =>
  a.x + 1 < b.x + b.width && b.x + 1 < a.x + a.width &&
  a.y + 1 < b.y + b.height && b.y + 1 < a.y + a.height;

for (const width of [320, 360, 412, 600]) {
  for (const textScale of [1, 1.5, 2]) {
    test(`Reflow: mobile header logo, search and menu button do not overlap at ${width}px, ${textScale * 100}% text`, async ({ page, browserName }) => {
      test.skip(browserName !== 'chromium', 'Setting the default font size needs the Chrome DevTools Protocol.');
      const cdp = await page.context().newCDPSession(page);
      await cdp.send('Page.setFontSizes', { fontSizes: { standard: 16 * textScale } });
      await page.setViewportSize({ width, height: 720 });
      await page.goto('/');

      test.skip(await page.locator('.header-search--mobile').count() === 0, 'Header search is turned off on this site.');
      const logoImage = page.locator('.header__logo img');
      test.skip(await logoImage.count() === 0, 'No site branding block in the header.');

      const boxes: Record<string, Box | null> = {
        logo: await logoImage.boundingBox(),
        input: await page.locator('.header-search--mobile .header-search__input').boundingBox(),
        submit: await page.locator('.header-search--mobile .header-search__button').boundingBox(),
        menu: await page.locator('.mobile-nav-button').boundingBox(),
      };
      const names = Object.keys(boxes);
      for (const name of names) {
        expect(boxes[name], `${name} is rendered`).not.toBeNull();
        expect(boxes[name]!.x + boxes[name]!.width, `${name} fits in the viewport`).toBeLessThanOrEqual(width + 1);
      }
      for (let i = 0; i < names.length; i++) {
        for (let j = i + 1; j < names.length; j++) {
          expect(overlaps(boxes[names[i]]!, boxes[names[j]]!), `${names[i]} overlaps ${names[j]}`).toBe(false);
        }
      }
      // The input still has room for text, not only its padding. 3rem is what
      // a full-width search row leaves at 320px and 200% text.
      expect(boxes.input!.width, 'input has room for text').toBeGreaterThanOrEqual(3 * 16 * textScale);
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
