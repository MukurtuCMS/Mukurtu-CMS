import { test, expect } from '@playwright/test';
import path = require('path');

/**
 * Tests that media stops when the viewer leaves the slide it's playing in:
 * moving to another lightbox slide, opening the lightbox over the page's
 * copy, and moving the media carousel.
 *
 * Same isolated-fixture approach as remote-video-lightbox.spec.ts: static
 * markup matching the media-assets templates, with the real GLightbox and
 * Splide libraries and the real theme JS loaded against it.
 *
 * Headless browsers don't play media without a real source, so
 * HTMLMediaElement's paused/pause() are stubbed to report "playing" and to
 * record which copy (page or lightbox) was paused. Remote videos are iframes
 * whose data: page posts a message to the parent each time it loads, so a
 * reload is counted per copy.
 */

const ROOT_DIR = path.join(__dirname, '../../..');
const THEME_DIR = path.join(ROOT_DIR, 'themes/mukurtu_v4');

const IFRAME_SRC = `data:text/html,<script>parent.postMessage('loaded', '*')</script>`;

// One media asset as media--video--media-assets.html.twig renders it, with a
// remote-video iframe alongside the native player so both kinds are covered
// by the same slide.
function mediaAssetHtml(id: string): string {
  const media = `
    <video data-test="${id}" width="320" height="180" controls></video>
    <iframe data-test="${id}" width="320" height="180" class="media-oembed-content" title="Video ${id}" src="${IFRAME_SRC}"></iframe>
  `;
  return `
    <div class="media-asset--slide">
      <div class="media media--video">
        <div class="media-asset--content">${media}</div>
        <div id="video-${id}" class="media-asset--glightbox-inline">
          <div class="media media--video">${media}</div>
        </div>
      </div>
      <div class="media-asset--actions">
        <a href="#video-${id}" data-type="inline" class="media-asset--link button">Expand +</a>
      </div>
    </div>
  `;
}

// Stubs media playback and records pauses and iframe loads on window. It
// goes in the fixture's <head> so it runs before the page's iframes load.
const INSTRUMENT_MEDIA = `<script>
  window.pauses = [];
  window.loads = [];
  function copyOf(el) {
    if (el.closest('.glightbox-container')) return 'lightbox:' + el.dataset.test;
    if (el.closest('.media-asset--glightbox-inline')) return 'hidden:' + el.dataset.test;
    return 'page:' + el.dataset.test;
  }
  Object.defineProperty(HTMLMediaElement.prototype, 'paused', {
    configurable: true,
    get() { return false; },
  });
  HTMLMediaElement.prototype.pause = function () {
    window.pauses.push(copyOf(this));
  };
  window.addEventListener('message', function (event) {
    var frame = Array.prototype.find.call(document.querySelectorAll('iframe'), function (el) {
      return el.contentWindow === event.source;
    });
    if (frame) window.loads.push(copyOf(frame));
  });
</script>`;

/**
 * Loads the shared Drupal/once() shim and the theme's media-stop helper.
 */
async function loadDrupalShim(page) {
  await page.addStyleTag({ path: path.join(THEME_DIR, 'css/style.css') });
  await page.addScriptTag({
    content: `
      window.Drupal = window.Drupal || { behaviors: {} };
      window.once = function (id, selector, context) {
        var root = context || document;
        var attr = 'data-once-' + id;
        var els = Array.prototype.filter.call(root.querySelectorAll(selector), function (el) {
          return !el.hasAttribute(attr);
        });
        els.forEach(function (el) { el.setAttribute(attr, ''); });
        return els;
      };
    `,
  });
  await page.addScriptTag({ path: path.join(THEME_DIR, 'js/media-stop.js') });
}

async function setUpLightboxFixture(page) {
  await page.setContent(`<!DOCTYPE html><html><head><title>Media stop lightbox fixture</title>${INSTRUMENT_MEDIA}</head><body>${mediaAssetHtml('a')}${mediaAssetHtml('b')}</body></html>`);
  await page.addStyleTag({ path: path.join(THEME_DIR, 'libraries/glightbox/glightbox.min.css') });
  await loadDrupalShim(page);
  await page.addScriptTag({ path: path.join(THEME_DIR, 'libraries/glightbox/glightbox.min.js') });
  await page.addScriptTag({ path: path.join(THEME_DIR, 'js/media-asset-glightbox.js') });

  // GLightbox is constructed after a 100ms setTimeout in
  // media-asset-glightbox.js; wait past that before clicking.
  await page.evaluate(() => (window as any).Drupal.behaviors.mediaAssetGLightbox.attach(document));
  await page.waitForTimeout(250);
  // Each asset's visible page copy and hidden source copy has loaded once.
  await expect.poll(() => page.evaluate(() => (window as any).loads.length)).toBe(4);
}

test.describe('Stopping media when leaving a slide', () => {
  test('Opening the lightbox stops the page copy but not the lightbox copy', async ({ page }) => {
    await setUpLightboxFixture(page);

    await page.locator('a.media-asset--link').first().click();
    await expect(page.locator('.glightbox-container .gslide.current iframe')).toBeVisible();
    await page.waitForTimeout(500);

    const pauses: string[] = await page.evaluate(() => (window as any).pauses);
    expect(pauses).toContain('page:a');
    expect(pauses).toContain('page:b');
    expect(pauses).not.toContain('lightbox:a');

    // Page iframes each reloaded once more; the lightbox's own copy loaded
    // once when GLightbox inserted it, and was not reloaded after that.
    const loads: string[] = await page.evaluate(() => (window as any).loads);
    expect(loads.filter((l) => l === 'page:a')).toHaveLength(2);
    expect(loads.filter((l) => l === 'page:b')).toHaveLength(2);
    expect(loads.filter((l) => l === 'lightbox:a')).toHaveLength(1);
    // The hidden source copies never play, so they aren't reloaded.
    expect(loads.filter((l) => l.startsWith('hidden:'))).toHaveLength(2);
  });

  test('Moving to the next lightbox slide stops the previous slide', async ({ page }) => {
    await setUpLightboxFixture(page);

    await page.locator('a.media-asset--link').first().click();
    await expect(page.locator('.glightbox-container .gslide.current iframe')).toBeVisible();
    await page.waitForTimeout(500);
    await page.evaluate(() => {
      (window as any).pauses = [];
      (window as any).loads = [];
    });

    await page.locator('.gnext').click();
    await expect(page.locator('.glightbox-container .gslide.current iframe[data-test="b"]')).toBeVisible();
    await page.waitForTimeout(500);

    const pauses: string[] = await page.evaluate(() => (window as any).pauses);
    expect(pauses).toEqual(['lightbox:a']);
    // GLightbox preloads the next slide on open, so slide b had already
    // loaded and only slide a's copy reloads.
    const loads: string[] = await page.evaluate(() => (window as any).loads);
    expect(loads).toEqual(['lightbox:a']);
  });

  test('An iframe without a src is left alone', async ({ page }) => {
    await page.setContent(`<!DOCTYPE html><html><body>
      <iframe id="klaro" width="320" height="180" data-src="https://example.com/"></iframe>
      <iframe id="blank" width="320" height="180" src="about:blank"></iframe>
    </body></html>`);
    await loadDrupalShim(page);

    const after = await page.evaluate(() => {
      (window as any).Drupal.mukurtuStopMedia(document.body);
      return {
        klaro: document.getElementById('klaro')!.getAttribute('src'),
        blank: document.getElementById('blank')!.getAttribute('src'),
      };
    });
    expect(after).toEqual({ klaro: null, blank: 'about:blank' });
  });

  test('Moving the media carousel stops the previous slide', async ({ page }) => {
    const slide = (id: string) => `<li class="field__item splide__slide">${mediaAssetHtml(id)}</li>`;
    const thumb = (id: string) => `<li class="splide__slide"><img alt="Thumbnail ${id}" width="60" height="44" src="data:image/gif;base64,R0lGODlhAQABAAAAACw="></li>`;
    await page.setContent(`<!DOCTYPE html><html><head><title>Media stop carousel fixture</title>${INSTRUMENT_MEDIA}</head><body>
      <div class="media-carousels">
        <section class="splide media-carousel" aria-label="Media Assets Carousel">
          <div class="splide__track"><ul class="splide__list">${slide('a')}${slide('b')}</ul></div>
        </section>
        <section class="splide thumbnail-carousel" aria-label="Thumbnail Carousel">
          <div class="splide__track"><ul class="splide__list">${thumb('a')}${thumb('b')}</ul></div>
        </section>
      </div>
    </body></html>`);
      await page.addStyleTag({ path: path.join(ROOT_DIR, 'modules/mukurtu_core/libraries/splide/splide.min.css') });
    await loadDrupalShim(page);
    await page.addScriptTag({ path: path.join(ROOT_DIR, 'modules/mukurtu_core/libraries/splide/splide.min.js') });
    await page.addScriptTag({ path: path.join(THEME_DIR, 'js/media-asset-carousel.js') });
    await page.evaluate(() => (window as any).Drupal.behaviors.mediaAssetCarousel.attach(document));
    await expect.poll(() => page.evaluate(() => (window as any).loads.length)).toBeGreaterThanOrEqual(1);
    await page.waitForTimeout(250);
    await page.evaluate(() => {
      (window as any).pauses = [];
      (window as any).loads = [];
    });

    await page.locator('.thumbnail-carousel__slide-button').nth(1).click();
    await page.waitForTimeout(500);

    const pauses: string[] = await page.evaluate(() => (window as any).pauses);
    expect(pauses).toEqual(['page:a']);
    const loads: string[] = await page.evaluate(() => (window as any).loads);
    expect(loads).toEqual(['page:a']);
  });
});
