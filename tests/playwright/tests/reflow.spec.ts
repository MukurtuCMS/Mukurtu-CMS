import { test, expect, type Page } from "@playwright/test";
import { gotoReady } from "~helpers/preview";

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
    await gotoReady(page, path);
    const overflow = await page.evaluate(() => ({
      scrollWidth: document.documentElement.scrollWidth,
      clientWidth: document.documentElement.clientWidth,
    }));
    expect(overflow.scrollWidth).toBeLessThanOrEqual(overflow.clientWidth + 1);
  });
}

test('Reflow: header search input can shrink to fit the mobile header grid', async ({ page }) => {
  await gotoReady(page, '/');
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

// Issue #2198: the mobile search stretched to fill its grid track (544px at
// 928px) instead of keeping the desktop instance's size, and stopped short of
// the menu button. These hold for a logo, the site name, or both.
const formBox = (page: Page, instance: 'mobile' | 'desktop') =>
  page.locator(`.header-search--${instance} .header-search__form`).boundingBox();

// A site with a long menu collapses the nav even at 1600px, which hides the
// desktop search. Lifting the collapse just long enough to measure gives its
// width regardless: it is a fixed basis, and the same task reads it before
// nav-resize.js can react.
async function desktopSearchWidth(page: Page): Promise<number> {
  await page.setViewportSize({ width: 1600, height: 720 });
  await gotoReady(page, '/');
  const width = await page.evaluate(() => {
    const form = document.querySelector('.header-search--desktop .header-search__form');
    if (!form) {
      return null;
    }
    const collapsed = document.body.classList.contains('is-always-mobile-nav');
    document.body.classList.remove('is-always-mobile-nav');
    const measured = form.getBoundingClientRect().width;
    if (collapsed) {
      document.body.classList.add('is-always-mobile-nav');
    }
    return measured;
  });
  test.skip(width === null, 'Header search is turned off on this site.');
  return width!;
}

for (const width of [320, 390, 768, 928]) {
  test(`Reflow: mobile header search keeps the desktop size at ${width}px`, async ({ page }) => {
    const desktopWidth = await desktopSearchWidth(page);
    await page.setViewportSize({ width, height: 720 });
    await gotoReady(page, '/');

    const mobile = (await formBox(page, 'mobile'))!;
    const cell = (await page.locator('.mobile-buttons').boundingBox())!;
    expect(mobile.width, 'not wider than the desktop search').toBeLessThanOrEqual(desktopWidth + 1);

    // Sharing the row with a logo, it sits beside the menu button and may
    // shrink to fit. On a row of its own it is full size and ends where the
    // menu button's cell ends.
    if (mobile.y >= cell.y + cell.height - 1) {
      expect(mobile.width, 'full size on its own row').toBeGreaterThanOrEqual(Math.min(desktopWidth, width - 2 * 16) - 1);
      expect(Math.abs(mobile.x + mobile.width - (cell.x + cell.width)), 'lines up with the menu button').toBeLessThanOrEqual(1);
    }
    else if (width >= 768) {
      expect(mobile.width, 'full size beside the menu button').toBeGreaterThanOrEqual(desktopWidth - 1);
    }
  });
}

// nav-resize.js adds is-always-mobile-nav when the menu has too many items to
// fit, at any width. The search then got a full-width strip with a logo, and
// sat beside the menu button rather than under it with the site name. Now the
// header looks the way it does below lg: with a logo the search shares the
// row, to the left of the button; with the site name it is under the button.
test('Reflow: header search keeps its size beside or under the menu button when the nav collapses', async ({ page }) => {
  const desktopWidth = await desktopSearchWidth(page);
  await page.setViewportSize({ width: 1280, height: 720 });
  await gotoReady(page, '/');
  // The class only changes on resize, so it stays put once set.
  await page.evaluate(() => document.body.classList.add('is-always-mobile-nav'));

  const mobileBox = await formBox(page, 'mobile');
  expect(mobileBox, 'mobile search is shown').not.toBeNull();
  const mobile = mobileBox!;
  const cell = (await page.locator('.mobile-buttons').boundingBox())!;
  expect(Math.abs(mobile.width - desktopWidth), 'same size as the desktop search').toBeLessThanOrEqual(1);

  const hasSiteName = await page.locator('.header__logo-text').count() > 0;
  if (hasSiteName) {
    expect(mobile.y, 'below the menu button').toBeGreaterThanOrEqual(cell.y + cell.height - 1);
    expect(Math.abs(mobile.x + mobile.width - (cell.x + cell.width)), 'lines up with the menu button').toBeLessThanOrEqual(1);
  }
  else {
    expect(mobile.y, 'on the menu button\'s row').toBeLessThan(cell.y + cell.height);
    expect(mobile.x + mobile.width, 'left of the menu button').toBeLessThanOrEqual(cell.x);
  }

  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  expect(overflow, 'no horizontal scroll').toBeLessThanOrEqual(1);
});

// From 2xl the shared grid centres the content, and the collapsed header has
// to keep to the same edges rather than running to the viewport.
test('Reflow: collapsed header stays inside the page edges at 1920px', async ({ page }) => {
  await page.setViewportSize({ width: 1920, height: 720 });
  await gotoReady(page, '/');
  await page.evaluate(() => document.body.classList.add('is-always-mobile-nav'));
  test.skip(await page.locator('.header-search--mobile').count() === 0, 'Header search is turned off on this site.');

  const logo = (await page.locator('.header__logo').boundingBox())!;
  const cell = (await page.locator('.mobile-buttons').boundingBox())!;
  expect(logo.x, 'logo is not against the left edge').toBeGreaterThanOrEqual(24);
  expect(1920 - (cell.x + cell.width), 'menu button is not against the right edge').toBeGreaterThanOrEqual(24);
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  expect(overflow, 'no horizontal scroll').toBeLessThanOrEqual(1);
});

test('Reflow: page title and breadcrumb allow mid-word breaks for long unbreakable titles', async ({ page }) => {
  await gotoReady(page, '/browse');
  const titleOverflowWrap = await page.locator('.page__title h1').first().evaluate(
    (el) => getComputedStyle(el).overflowWrap
  );
  expect(titleOverflowWrap).toBe('anywhere');

  const breadcrumbOverflowWrap = await page.locator('.breadcrumb__list-item').first().evaluate(
    (el) => getComputedStyle(el).overflowWrap
  );
  expect(breadcrumbOverflowWrap).toBe('anywhere');
});

// The community cards overflowed /communities by 28px at 320px (348px of
// document against a 320px viewport). Two independent causes, so assert the
// end result rather than either property: the list kept the UA's 40px list
// indent despite hiding its markers, and `grid-template-columns: 1fr` is
// floored at the card's automatic minimum, which the card image's intrinsic
// inline size pushed past the width of the grid container itself.
test('Reflow: community cards do not overflow at 320px', async ({ page }) => {
  await page.setViewportSize({ width: 320, height: 720 });
  await page.goto('/communities');

  const cards = page.locator('.communities .communities__item');
  test.skip(await cards.count() === 0, 'No communities are visible to this account.');

  const overflow = await page.evaluate(() => ({
    scrollWidth: document.documentElement.scrollWidth,
    clientWidth: document.documentElement.clientWidth,
  }));
  expect(overflow.scrollWidth).toBeLessThanOrEqual(overflow.clientWidth + 1);

  // The track must also fit its own container, so a wider card image or a
  // longer title can't push it back out without failing the check above.
  const list = page.locator('.communities').first();
  const fits = await list.evaluate((el) => {
    const track = parseFloat(getComputedStyle(el).gridTemplateColumns.split(' ')[0]);
    const content = el.clientWidth - parseFloat(getComputedStyle(el).paddingInlineStart) -
      parseFloat(getComputedStyle(el).paddingInlineEnd);
    return { track, content };
  });
  expect(fits.track).toBeLessThanOrEqual(fits.content + 1);
});
