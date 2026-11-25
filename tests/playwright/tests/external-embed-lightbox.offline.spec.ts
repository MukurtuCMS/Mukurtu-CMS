import { test, expect, Page } from '@playwright/test';
import path = require('path');

/**
 * Issue #336: Sketchfab's embed code with "Fixed size" off has no height on
 * its iframe and adds a credit line of links under it. These check the 16:9
 * sizing on the page and in the lightbox, the lightbox Tab order through the
 * credit links and Klaro's consent notice, and the credit line's colors.
 *
 * Same approach as remote-video-lightbox.spec.ts: markup mirroring
 * media--external-embed--media-assets.html.twig, with the real GLightbox
 * library and the real compiled theme CSS and JS.
 */

const THEME_DIR = path.join(__dirname, '../../../themes/mukurtu_v4');

// The Full HTML filter keeps these attributes (see
// mukurtu_core_update_40232()); style attributes are stripped, so none here.
// src is about:blank so the test needs no network.
const IFRAME = '<iframe title="Bowl" frameborder="0" allowfullscreen allow="autoplay; fullscreen; xr-spatial-tracking" src="about:blank"></iframe>';
const CREDIT = '<p><a href="https://example.com/model" class="ext">Bowl</a> by <a href="https://example.com/author" class="ext">Author</a> on <a href="https://example.com/" class="ext">Sketchfab</a></p>';

// What Klaro leaves before consent: the iframe hidden in place, and a notice
// with a button and a link.
const BLOCKED = `
  <div>
    <div class="klaro cm-as-context-notice"><div class="context-notice">
      <p>Load external content supplied by Sketchfab?</p>
      <p class="cm-buttons"><button class="cm-btn cm-btn-success">Yes (this time)</button></p>
      <p class="ps"><a href="#" class="cm-link">Manage privacy settings</a></p>
    </div></div>
    <iframe title="Bowl" frameborder="0" allowfullscreen allow="autoplay; fullscreen" data-src="about:blank" data-name="sketchfab" style="display: none"></iframe>
  </div>`;

function fixture(embed: string): string {
  const field = `<div class="field field--name-field-media-external-embed field__item"><div class="sketchfab-embed-wrapper">${embed}</div></div>`;
  return `
    <div class="media-asset--slide">
      <div class="media media--external_embed">
        <div class="media-asset--content">${field}</div>
        <div id="external-embed-1" class="media-asset--glightbox-inline">
          <div class="media media--external-embed">${field}</div>
        </div>
      </div>
      <div class="media-asset--actions">
        <a href="#external-embed-1" data-type="inline" class="media-asset--link button">Expand +</a>
      </div>
    </div>`;
}

async function load(page: Page, embed: string) {
  await page.setContent(`<!DOCTYPE html><html><head><title>External embed lightbox fixture</title></head><body><main style="inline-size: 843px">${fixture(embed)}</main></body></html>`);
  await page.addStyleTag({ path: path.join(THEME_DIR, 'libraries/glightbox/glightbox.min.css') });
  await page.addStyleTag({ path: path.join(THEME_DIR, 'css/style.css') });
  await page.addScriptTag({
    content: `
      window.Drupal = { behaviors: {} };
      window.once = function (id, selector, context) {
        var attr = 'data-once-' + id;
        var els = Array.prototype.filter.call((context || document).querySelectorAll(selector), function (el) {
          return !el.hasAttribute(attr);
        });
        els.forEach(function (el) { el.setAttribute(attr, ''); });
        return els;
      };
    `,
  });
  await page.addScriptTag({ path: path.join(THEME_DIR, 'libraries/glightbox/glightbox.min.js') });
  await page.addScriptTag({ path: path.join(THEME_DIR, 'js/media-stop.js') });
  await page.addScriptTag({ path: path.join(THEME_DIR, 'js/media-asset-glightbox.js') });
  await page.evaluate(() => (window as any).Drupal.behaviors.mediaAssetGLightbox.attach(document));
  // Past the behavior's 100ms delay before it creates the lightbox.
  await page.waitForTimeout(250);
}

async function open(page: Page) {
  await page.locator('a.media-asset--link').click();
  await expect(page.locator('.glightbox-container')).toBeVisible();
}

async function active(page: Page): Promise<string> {
  return page.evaluate(() => {
    const el = document.activeElement as HTMLElement;
    return el.classList.contains('gclose') ? 'Close' : `${el.tagName} ${el.textContent!.trim()}`.trim();
  });
}

test('An embed without a height is 16:9 on the page, not 150px tall', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await load(page, IFRAME + CREDIT);

  const box = (await page.locator('.media-asset--content iframe').boundingBox())!;
  expect(box.width).toBeCloseTo(843, 0);
  expect(box.width / box.height).toBeCloseTo(16 / 9, 1);
});

for (const [width, height] of [[320, 640], [768, 1024], [1440, 900], [1440, 600]]) {
  test(`In the lightbox it is 16:9 and fits inside the slide at ${width}x${height}`, async ({ page }) => {
    await page.setViewportSize({ width, height });
    await load(page, IFRAME + CREDIT);
    await open(page);

    const iframe = page.locator('.glightbox-container iframe');
    // Measured once GLightbox's opening transition has settled.
    await expect(async () => {
      const box = (await iframe.boundingBox())!;
      expect(box.width / box.height).toBeCloseTo(16 / 9, 1);
    }).toPass({ timeout: 3000 });

    const fits = await iframe.evaluate((el) => {
      const r = el.getBoundingClientRect();
      const slide = el.closest('.ginlined-content')!.getBoundingClientRect();
      return r.left >= slide.left - 0.5 && r.right <= slide.right + 0.5 && r.bottom <= window.innerHeight;
    });
    expect(fits).toBe(true);
  });
}

test('Tab moves from the viewer through the credit links to Close', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await load(page, IFRAME + CREDIT);
  await open(page);

  await page.locator('.gclose').focus();
  await page.keyboard.press('Tab');
  expect(await active(page)).toBe('IFRAME');

  // Leaving a cross-origin iframe, the browser itself moves focus to the
  // next element in the page, the first credit link. From there, Tab used to
  // jump back to the iframe, so Close was never reached.
  await page.locator('.glightbox-container .ginlined-content a').first().focus();
  const order: string[] = [];
  for (let i = 0; i < 4; i++) {
    await page.keyboard.press('Tab');
    order.push(await active(page));
  }
  expect(order).toEqual(['A Author', 'A Sketchfab', 'Close', 'IFRAME']);

  await page.locator('.gclose').focus();
  await page.keyboard.press('Shift+Tab');
  expect(await active(page)).toBe('A Sketchfab');
});

test('Before consent, Tab reaches the consent notice and Close (WCAG 2.1.1)', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await load(page, BLOCKED + CREDIT);
  await open(page);

  await page.locator('.gclose').focus();
  const order: string[] = [];
  for (let i = 0; i < 6; i++) {
    await page.keyboard.press('Tab');
    order.push(await active(page));
  }
  expect(order).toEqual([
    'BUTTON Yes (this time)',
    'A Manage privacy settings',
    'A Bowl',
    'A Author',
    'A Sketchfab',
    'Close',
  ]);
});

test('The credit line is white in the lightbox, and Klaro keeps its own colors (WCAG 1.4.3)', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await load(page, BLOCKED + CREDIT);
  await open(page);

  const slide = page.locator('.glightbox-container .ginlined-content');
  for (const el of await slide.locator(':scope .sketchfab-embed-wrapper > p, :scope .sketchfab-embed-wrapper > p a').all()) {
    await expect(el).toHaveCSS('color', 'rgb(255, 255, 255)');
  }
  await expect(slide.locator('.klaro p').first()).not.toHaveCSS('color', 'rgb(255, 255, 255)');
});
