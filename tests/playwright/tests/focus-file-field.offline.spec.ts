import { test, expect } from '@playwright/test';
import fs = require('fs');
import path = require('path');

/**
 * Keyboard focus stays in a file field after an upload or removal (#2648).
 *
 * The response replaces the field, taking the focused input or button with
 * it, and core's refocus lands on a wrapper that can't take focus.
 *
 * Runs the real focus-file-field.js against a static fixture. Core's tabbable
 * library isn't available here, so a small stand-in finds visible, focusable
 * elements in the same order.
 */

const REPO = path.join(__dirname, '../../..');
const COMMAND = fs.readFileSync(path.join(REPO, 'modules/mukurtu_core/js/focus-file-field.js'), 'utf8');

// The video field after an upload, and an image field with two files where
// the second was just added.
const FIXTURE = `
  <a href="#top" id="before">Before</a>
  <div id="ajax-wrapper-video">
    <div data-drupal-messages>Uploaded.</div>
    <input type="submit" id="upload-button" class="js-hide" style="display:none" value="Upload">
    <span class="file"><a href="/video.mp4" id="video-link">video.mp4</a></span>
    <input type="submit" id="video-remove" value="Remove">
  </div>
  <div id="ajax-wrapper-images">
    <div><a href="/one.jpg" id="first-link">one.jpg</a></div>
    <div class="ajax-new-content"><a href="/two.jpg" id="new-link">two.jpg</a></div>
  </div>
  <div id="ajax-wrapper-empty"><p>No controls.</p></div>
`;

test.beforeEach(async ({ page }) => {
  await page.setContent(FIXTURE);
  await page.addScriptTag({
    content: `
      window.Drupal = { AjaxCommands: function () {} };
      window.tabbable = {
        tabbable: (container) => [...container.querySelectorAll('a[href], button, input, select, textarea')]
          .filter((el) => !el.disabled && el.getClientRects().length > 0),
      };
    `,
  });
  await page.addScriptTag({ content: COMMAND });
});

const run = (page, wrapper?: string) => page.evaluate(
  (wrapper) => Drupal.AjaxCommands.prototype.mukurtuFocusFileField({ wrapper }),
  wrapper,
);

const focusedId = (page) => page.evaluate(() => document.activeElement?.id || document.activeElement?.tagName);

test('moves lost focus to the first control in the field', async ({ page }) => {
  expect(await focusedId(page)).toBe('BODY');

  await run(page, '#ajax-wrapper-video');

  expect(await focusedId(page)).toBe('video-link');
});

test('moves focus to the file just added in a field with several files', async ({ page }) => {
  await run(page, '#ajax-wrapper-images');

  expect(await focusedId(page)).toBe('new-link');
});

test('leaves focus alone when it is still on the page', async ({ page }) => {
  await page.locator('#before').focus();

  await run(page, '#ajax-wrapper-video');

  expect(await focusedId(page)).toBe('before');
});

test('does nothing without a wrapper or anything to focus', async ({ page }) => {
  await run(page);
  await run(page, '#missing');
  await run(page, '#ajax-wrapper-empty');

  expect(await focusedId(page)).toBe('BODY');
});
