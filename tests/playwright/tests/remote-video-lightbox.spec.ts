import { test, expect } from '@playwright/test';
import path = require('path');

/**
 * Tests the remote-video "Expand +" GLightbox integration in isolation from
 * the rest of the site: a static fixture reproducing the exact markup
 * `media--remote-video--media-assets.html.twig` renders, with the real
 * compiled theme CSS/JS and the real GLightbox library loaded against it.
 *
 * This avoids depending on live Digital Heritage content with a remote-video
 * media asset attached (Mukurtu's media-assets field widget only supports
 * selecting existing media, not creating a new remote-video item inline, so
 * building that fixture through the UI would need its own dedicated
 * media-creation route this profile does not expose) while still exercising
 * the real production assets, not reimplemented/mocked logic.
 *
 * `once()` is reimplemented minimally here rather than loaded from
 * @drupal/core (which lives under the gitignored vendor/ directory and isn't
 * portable to a fresh checkout/CI) - just enough of once()'s contract
 * (mark-and-filter by a per-id attribute) for media-asset-glightbox.js's
 * usage.
 */

const THEME_DIR = path.join(__dirname, '../../../themes/mukurtu_v4');

// Matches the real oEmbed formatter output (see
// core.entity_view_display.media.remote_video.media_assets.yml's
// max_width/max_height settings): a 16:9-ish 900x506 iframe. src is
// about:blank - only the width/height attributes matter for this test, and
// avoids any dependency on a live YouTube/Vimeo embed.
function remoteVideoFixtureHtml(mediaId: number): string {
  const iframe = `<iframe width="900" height="506" class="media-oembed-content" title="Test Video" src="about:blank"></iframe>`;
  return `
    <div class="media-asset--slide">
      <div class="media media--remote-video">
        <div class="media-asset--content">
          <div class="field field--name-field-media-oembed-video field--type-string field--label-hidden field__item">${iframe}</div>
        </div>
        <div id="remote-video-${mediaId}" class="media-asset--glightbox-inline">
          <div class="media media--remote-video">
            <div class="field field--name-field-media-oembed-video field--type-string field--label-hidden field__item">${iframe}</div>
          </div>
        </div>
      </div>
      <div class="media-asset--actions">
        <a href="#remote-video-${mediaId}" data-type="inline" class="media-asset--link button" aria-label="Expand Test Video video">Expand +</a>
      </div>
    </div>
  `;
}

/**
 * Loads the fixture markup plus the real GLightbox library and the real
 * compiled theme CSS/JS, then runs Drupal's attach cycle for the
 * mediaAssetGLightbox behavior.
 */
async function setUpLightboxFixture(page) {
  await page.setContent(`<!DOCTYPE html><html><head><title>Remote video lightbox fixture</title></head><body>${remoteVideoFixtureHtml(1)}</body></html>`);

  await page.addStyleTag({ path: path.join(THEME_DIR, 'libraries/glightbox/glightbox.min.css') });
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
  await page.addScriptTag({ path: path.join(THEME_DIR, 'libraries/glightbox/glightbox.min.js') });
  await page.addScriptTag({ path: path.join(THEME_DIR, 'js/media-asset-glightbox.js') });

  // mediaAssetGLightbox's global init runs GLightbox's own constructor after
  // a 100ms setTimeout (see media-asset-glightbox.js) to let media render
  // first; wait past that so `new GLightbox(...)` has actually run before
  // interacting with the trigger link below.
  await page.evaluate(() => (window as any).Drupal.behaviors.mediaAssetGLightbox.attach(document));
  await page.waitForTimeout(250);
}

test.describe('Remote-video GLightbox integration', () => {
  test('Expand+ opens the video at its own aspect ratio, filling the available space', async ({ page }) => {
    await page.setViewportSize({ width: 1400, height: 900 });
    await setUpLightboxFixture(page);

    await page.locator('a.media-asset--link').click();
    await expect(page.locator('.glightbox-container')).toBeVisible();

    const iframe = page.locator('.glightbox-container iframe.media-oembed-content');
    await expect(iframe).toBeVisible();
    // GLightbox's own opening transition (opacity/transform) hasn't
    // necessarily settled the instant the container becomes visible;
    // boundingBox() measured mid-transition returns a smaller interim size.
    await expect(async () => {
      const current = await iframe.boundingBox();
      expect(current!.width).toBeGreaterThan(1000);
    }).toPass({ timeout: 3000 });
    const box = await iframe.boundingBox();
    expect(box).not.toBeNull();

    // Real width/height attributes are 900x506 (~16:9). Confirm the
    // rendered box preserves that ratio (regression check for the iframe
    // stretching into a tall, narrow box when the ratio isn't preserved).
    const expectedRatio = 900 / 506;
    expect(box!.width / box!.height).toBeCloseTo(expectedRatio, 1);

    // Confirm it actually grew to fill the lightbox rather than collapsing
    // to a small fixed size (regression check for the shrink-wrap collapse
    // fixed alongside the aspect-ratio fix - previously measured at a fixed
    // ~300-400px box regardless of viewport size).
    expect(box!.width).toBeGreaterThan(1000);
  });

  test('Tab reaches the video inside the open lightbox (WCAG 2.1.1)', async ({ page }) => {
    await page.setViewportSize({ width: 1400, height: 900 });
    await setUpLightboxFixture(page);

    await page.locator('a.media-asset--link').click();
    await expect(page.locator('.glightbox-container')).toBeVisible();

    // Start from a known focus point (the close button) rather than
    // whatever GLightbox happens to focus on open, so this test isn't
    // coupled to that separate behavior. This fixture has only one slide,
    // so gnext/gprev get GLightbox's own `disabled` class (matching any
    // real single-item page) and drop out of the stops array, leaving
    // exactly two stops: the iframe and gclose - meaning *both* Tab and
    // Shift+Tab from gclose land on the iframe.
    await page.locator('.gclose').focus();
    await page.keyboard.press('Tab');

    const activeTag = await page.evaluate(() => document.activeElement?.tagName);
    const activeClass = await page.evaluate(() => document.activeElement?.className);
    expect(activeTag).toBe('IFRAME');
    expect(activeClass).toContain('media-oembed-content');

    // Tab from the iframe cycles back to gclose (verifies the stops
    // array's index arithmetic continues correctly, not just the single
    // gclose -> iframe jump above).
    await page.keyboard.press('Tab');
    const activeClassAfterSecondTab = await page.evaluate(() => document.activeElement?.className);
    expect(activeClassAfterSecondTab).toContain('gclose');

    // And Shift+Tab from gclose reaches the iframe too, the reverse
    // direction through the same two-stop cycle.
    await page.locator('.gclose').focus();
    await page.keyboard.press('Shift+Tab');
    const activeTagBack = await page.evaluate(() => document.activeElement?.tagName);
    expect(activeTagBack).toBe('IFRAME');
  });
});
