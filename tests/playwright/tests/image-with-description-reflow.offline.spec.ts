import { test, expect } from '@playwright/test';
import fs = require('fs');
import path = require('path');

/**
 * Issue #2487: the horizontal "Image with description" block scrolled the
 * page sideways at large text sizes (WCAG 1.4.10 Reflow).
 *
 * The block's grid track was a bare `1fr`, which is `minmax(auto, 1fr)` and so
 * can't shrink below its content's min-content width. The heading and the
 * card's padding are in rem, so that floor grew with the text size until it
 * was wider than a phone-width viewport.
 *
 * Tests against the real compiled theme CSS with a static fixture (see
 * page-background.offline.spec.ts) rather than the seeded home page, so it
 * needs no site and runs in the `offline` project.
 */

const THEME_DIR = path.join(__dirname, '../../../themes/mukurtu_v4');
const THEME_CSS = fs.readFileSync(path.join(THEME_DIR, 'css/style.css'), 'utf8');

// Mirrors block--block-content--type--image-with-description.html.twig as the
// default home page renders it: no image, and the heading and body that ship
// with the install.
const BLOCK = `
  <div class="block-wrapper block--image-with-description block--image-with-description--horizontal">
    <div class="block-wrapper__first"></div>
    <div class="block-wrapper__second">
      <h2>Welcome to Your Mukurtu CMS Site</h2>
      <div class="field field--name-body field--type-text-with-summary field--label-hidden field__item"><p>To start using your Mukurtu site, create a community, cultural protocol, and category.</p></div>
    </div>
  </div>
`;

// The landing page's one-column layout adds its own rem side padding to any
// block with a body field (_landing-page.scss), on top of the card's.
const PAGE = `
  <div class="layout-container">
    <main role="main">
      <div class="layout layout--onecol">
        <div class="layout__region layout__region--content">${BLOCK}</div>
      </div>
    </main>
  </div>
`;

function html(): string {
  return `<!DOCTYPE html><html><head><title>Image with description fixture</title><style>${THEME_CSS}</style></head><body>${PAGE}</body></html>`;
}

for (const width of [320, 412]) {
  for (const textScale of [1, 1.5, 2]) {
    test(`Image with description block fits ${width}px at ${textScale * 100}% text`, async ({ page }) => {
      // The browser's default font size stands in for the phone's text size
      // setting; unlike a root font-size override, it also moves rem media
      // queries, so the block stays in its narrow layout as it would on a
      // phone.
      const cdp = await page.context().newCDPSession(page);
      await cdp.send('Page.setFontSizes', { fontSizes: { standard: 16 * textScale } });
      await page.setViewportSize({ width, height: 720 });
      await page.setContent(html());

      const block = page.locator('.block--image-with-description');
      const box = (await block.boundingBox())!;
      expect(box.x + box.width, 'block fits in the viewport').toBeLessThanOrEqual(width + 1);

      const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
      expect(overflow, 'no horizontal scroll').toBeLessThanOrEqual(1);

      // The heading must wrap inside its card rather than spill out of it.
      const heading = await page.locator('.block--image-with-description h2').evaluate((el) => ({
        scrollWidth: el.scrollWidth,
        clientWidth: el.clientWidth,
      }));
      expect(heading.scrollWidth, 'heading fits its card').toBeLessThanOrEqual(heading.clientWidth + 1);

      // Not scrolling isn't enough on its own: with the paddings in rem, the
      // text column fell to 32px at 320px and 200% text. It is 176px at the
      // default text size, so hold it near that.
      expect(heading.clientWidth, 'text column stays readable').toBeGreaterThanOrEqual(160);
    });
  }
}

test('Image with description block keeps two equal columns from md', async ({ page }) => {
  await page.setViewportSize({ width: 1024, height: 720 });
  await page.setContent(html());
  const columns = await page.locator('.block--image-with-description').evaluate(
    (el) => getComputedStyle(el).gridTemplateColumns.split(' ').map(parseFloat)
  );
  expect(columns).toHaveLength(2);
  expect(Math.abs(columns[0] - columns[1])).toBeLessThanOrEqual(1);
});
