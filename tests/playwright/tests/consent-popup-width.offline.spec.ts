import { test, expect } from '@playwright/test';
import fs = require('fs');
import path = require('path');

/**
 * The consent popup (contrib consent_popup, restyled in _consent-popup.scss)
 * grew to fill the whole screen below about 1000px, leaving no backdrop
 * around it. The theme now keeps a gutter on every side, and lets a tall
 * popup scroll inside itself, since the module hides the body's overflow.
 *
 * Tests against the real compiled theme CSS with a static fixture (see
 * page-background.offline.spec.ts), so it needs no site and runs in the
 * `offline` project.
 */

const THEME_DIR = path.join(__dirname, '../../../themes/mukurtu_v4');
const THEME_CSS = fs.readFileSync(path.join(THEME_DIR, 'css/style.css'), 'utf8');

// The rules from vendor/drupal/consent_popup/css/consent_popup.css that size
// and place the popup. vendor/ isn't installed in the offline job, so they
// are copied here.
const MODULE_CSS = `
  body.consent-popup-opened { overflow: hidden; }
  .block-consent-popup {
    position: fixed; z-index: 1000; top: 0; left: 0; display: none;
    width: 100vw; height: 100vh; background-color: rgb(0, 0, 0, 0.8);
  }
  .consent-popup-opened .block-consent-popup {
    display: flex; align-items: center; justify-content: center;
  }
  #javali-popup {
    display: flex; flex-direction: column; row-gap: 2rem; align-items: center;
    justify-content: center; box-sizing: border-box; width: 20rem;
    min-height: 20rem; padding: 2rem 3rem; background-color: #fff;
  }
  .block-consent-popup .consent-buttons { display: flex; flex-wrap: wrap; gap: 1rem; }
  .block-consent-popup button { padding: 0.5rem 1rem; }
  @media (min-width: 48em) { #javali-popup { width: 28rem; } }
`;

// Mirrors block--consent-popup.html.twig and consent-popup.html.twig, with
// the text from a live site where the popup filled the screen.
const POPUP = `
  <div class="block-wrapper block-consent-popup">
    <div id="javali-popup">
      <h3>Appropriate Use Agreement</h3>
      <div class="consent-text"><p>By accessing this site, you agree to follow the terms of access, use, citation and attribution defined by each Tribal Nation for their cultural materials, traditional knowledge, and languages.</p></div>
      <div class="consent-buttons">
        <button class="accept">I accept</button>
        <button class="decline">I decline</button>
      </div>
    </div>
  </div>
`;

function html(): string {
  return `<!DOCTYPE html><html><head><title>Consent popup fixture</title><style>${MODULE_CSS}</style><style>${THEME_CSS}</style></head><body class="consent-popup-opened">${POPUP}</body></html>`;
}

for (const width of [320, 412, 768, 1024, 1440]) {
  test(`Consent popup leaves a gutter at ${width}px`, async ({ page }) => {
    await page.setViewportSize({ width, height: 900 });
    await page.setContent(html());

    const gutter = width >= 768 ? 32 : 16;
    const box = (await page.locator('#javali-popup').boundingBox())!;
    expect(box.x, 'gap on the left').toBeGreaterThanOrEqual(gutter - 1);
    expect(box.x + box.width, 'gap on the right').toBeLessThanOrEqual(width - gutter + 1);
    expect(box.width, 'no wider than its cap').toBeLessThanOrEqual(1000);

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow, 'no horizontal scroll').toBeLessThanOrEqual(1);
  });
}

test('Consent popup scrolls to its buttons on a short screen at 200% text', async ({ page }) => {
  // The browser's default font size stands in for the phone's text size
  // setting (see landing-page-reflow.offline.spec.ts).
  const cdp = await page.context().newCDPSession(page);
  await cdp.send('Page.setFontSizes', { fontSizes: { standard: 32 } });
  await page.setViewportSize({ width: 320, height: 568 });
  await page.setContent(html());

  const popup = page.locator('#javali-popup');
  const box = (await popup.boundingBox())!;
  expect(box.y, 'top stays on screen').toBeGreaterThanOrEqual(0);
  expect(box.y + box.height, 'bottom stays on screen').toBeLessThanOrEqual(568);
  expect(box.x, 'gap on the left').toBeGreaterThanOrEqual(15);
  expect(box.x + box.width, 'gap on the right').toBeLessThanOrEqual(320 - 15);

  // The module centres the popup's content, and centred content that
  // overflows spills past the top and left too, where scrolling can't reach
  // it. "Appropriate" alone is wider than the popup at this size.
  const heading = (await page.locator('#javali-popup h3').boundingBox())!;
  expect(heading.y, 'heading starts inside the popup').toBeGreaterThanOrEqual(box.y);
  expect(heading.x, 'heading starts inside the popup').toBeGreaterThanOrEqual(box.x);
  expect(heading.x + heading.width, 'heading ends inside the popup').toBeLessThanOrEqual(box.x + box.width + 1);
  const sideways = await page.locator('#javali-popup').evaluate((el) => el.scrollWidth - el.clientWidth);
  expect(sideways, 'popup content does not scroll sideways').toBeLessThanOrEqual(1);

  const decline = page.locator('.decline');
  await decline.scrollIntoViewIfNeeded();
  const button = (await decline.boundingBox())!;
  expect(button.y, 'button scrolls into the popup').toBeGreaterThanOrEqual(box.y);
  expect(button.y + button.height, 'button scrolls into the popup').toBeLessThanOrEqual(box.y + box.height + 1);
});
