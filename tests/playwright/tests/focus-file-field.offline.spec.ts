import { test, expect } from '@playwright/test';
import fs = require('fs');
import path = require('path');

/**
 * Keyboard focus stays in a file field after an upload or removal (#2648).
 *
 * The response replaces the field, taking the focused input or button with
 * it, and core's refocus looks for the old wrapper, which is gone.
 *
 * Runs the real focus-file-field.js against a static fixture shaped like the
 * Gin media form. Core's tabbable library isn't available here, so a small
 * stand-in finds visible, focusable elements in the same order.
 */

const REPO = path.join(__dirname, '../../..');
const COMMAND = fs.readFileSync(path.join(REPO, 'modules/mukurtu_core/js/focus-file-field.js'), 'utf8');

// The video field after an upload, inside a details element; a field with
// several files where the second was just added; and a wrapper with nothing
// to focus.
const FIXTURE = `
  <a href="#top" id="before">Before</a>
  <div id="ajax-wrapper--video">
    <details open>
      <summary id="video-summary">Video file</summary>
      <div class="js-form-managed-file">
        <input type="submit" id="upload-button" class="js-hide" style="display:none" value="Upload">
        <span class="file"><a href="/video.mp4" id="video-link">video.mp4</a></span>
        <input type="submit" id="video-remove" value="Remove">
      </div>
    </details>
  </div>
  <div id="tracks-ajax-wrapper">
    <table>
      <tr><td><a href="#" class="tabledrag-handle" id="first-handle" title="Change order"></a></td>
        <td><div class="js-form-managed-file"><a href="/one.vtt" id="first-link">one.vtt</a></div></td></tr>
      <tr class="ajax-new-content"><td><a href="#" class="tabledrag-handle" id="new-handle" title="Change order"></a></td>
        <td><div class="js-form-managed-file ajax-new-content"><a href="/two.vtt" id="new-link">two.vtt</a></div></td></tr>
    </table>
  </div>
  <div id="ajax-wrapper--empty"><p>No controls.</p></div>
`;

test.beforeEach(async ({ page }) => {
  await page.setContent(FIXTURE);
  await page.addScriptTag({
    content: `
      window.Drupal = { AjaxCommands: function () {} };
      window.jQuery = (el) => ({ data: (key, value) => { el.dataset.jqueryData = key + '=' + value; } });
      window.tabbable = {
        tabbable: (container) => [...container.querySelectorAll('a[href], button, input, select, summary, textarea')]
          .filter((el) => !el.disabled && el.getClientRects().length > 0),
      };
    `,
  });
  await page.addScriptTag({ content: COMMAND });
});

// The button that sent the request, already removed from the page.
const run = (page, selector: string) => page.evaluate(
  (selector) => {
    window.sender = document.createElement('input');
    Drupal.AjaxCommands.prototype.mukurtuFocusFileField({ element: window.sender }, { selector });
  },
  selector,
);

const refocusDisabled = (page) => page.evaluate(() => window.sender.dataset.jqueryData === 'disable-refocus=true');

const focusedId = (page) => page.evaluate(() => document.activeElement?.id || document.activeElement?.tagName);

test('moves lost focus into the file field, not the details summary', async ({ page }) => {
  expect(await focusedId(page)).toBe('BODY');

  await run(page, '#ajax-wrapper--video');

  expect(await focusedId(page)).toBe('video-link');
});

test('moves focus to the file just added, not its drag handle', async ({ page }) => {
  await run(page, '#tracks-ajax-wrapper');

  expect(await focusedId(page)).toBe('new-link');
});

test("stops core moving focus on to the button's replacement", async ({ page }) => {
  // In a field with several files, the file that moves up after a removal
  // takes the removed button's data-drupal-selector, which core refocuses.
  await run(page, '#tracks-ajax-wrapper');

  expect(await refocusDisabled(page)).toBe(true);
});

test('leaves focus alone when it is still on the page', async ({ page }) => {
  await page.locator('#before').focus();

  await run(page, '#ajax-wrapper--video');

  expect(await focusedId(page)).toBe('before');
  expect(await refocusDisabled(page)).toBe(false);
});

test('does nothing when the wrapper is missing or has nothing to focus', async ({ page }) => {
  await run(page, '#missing');
  await run(page, '#ajax-wrapper--empty');

  expect(await focusedId(page)).toBe('BODY');
});
