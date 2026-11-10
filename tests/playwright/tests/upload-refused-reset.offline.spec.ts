import { test, expect } from '@playwright/test';
import fs = require('fs');
import path = require('path');

/**
 * A refused upload clears its file field and announces why (#2645).
 *
 * The browser keeps the file selected after the refusal, and core's file.js
 * only stops Save from sending it again on mousedown. Saving from the keyboard
 * therefore sent a request over the limit, and the form reloaded blank.
 *
 * Runs the real reset-refused-upload.js against a static fixture, because
 * reaching the real command needs a server with a low upload limit.
 */

const REPO = path.join(__dirname, '../../..');
const COMMAND = fs.readFileSync(path.join(REPO, 'modules/mukurtu_core/js/reset-refused-upload.js'), 'utf8');

const FIXTURE = `
  <div id="ajax-wrapper-video">
    <div data-drupal-messages>Earlier message</div>
    <input type="file" id="video">
  </div>
  <div id="ajax-wrapper-thumbnail">
    <div data-drupal-messages>Other field's message</div>
    <input type="file" id="thumbnail">
  </div>
`;

test.beforeEach(async ({ page }) => {
  await page.setContent(FIXTURE);
  await page.addScriptTag({
    content: `
      window.__announced = [];
      window.Drupal = {
        AjaxCommands: function () {},
        announce: (text, priority) => window.__announced.push([text, priority]),
      };
    `,
  });
  await page.addScriptTag({ content: COMMAND });
  const file = { name: 'too-large.mp4', mimeType: 'video/mp4', buffer: Buffer.from('video') };
  await page.locator('#video').setInputFiles(file);
  await page.locator('#thumbnail').setInputFiles(file);
});

const run = (page, wrapper?: string) => page.evaluate(
  (wrapper) => Drupal.AjaxCommands.prototype.mukurtuResetRefusedUpload({ wrapper }, { message: 'Too large' }),
  wrapper,
);

const fileCount = (page, id: string) => page.locator(id).evaluate((input: HTMLInputElement) => input.files.length);

test('clears the refused field and its earlier message', async ({ page }) => {
  await run(page, '#ajax-wrapper-video');

  expect(await fileCount(page, '#video')).toBe(0);
  await expect(page.getByText('Earlier message')).toHaveCount(0);
});

test('leaves other file fields alone', async ({ page }) => {
  await run(page, '#ajax-wrapper-video');

  expect(await fileCount(page, '#thumbnail')).toBe(1);
  await expect(page.getByText("Other field's message")).toHaveCount(1);
});

test('announces the message', async ({ page }) => {
  await run(page, '#ajax-wrapper-video');

  expect(await page.evaluate(() => window.__announced)).toEqual([['Too large', 'assertive']]);
});

test('still announces on a form with no upload wrapper', async ({ page }) => {
  await run(page);

  expect(await fileCount(page, '#video')).toBe(1);
  await expect(page.getByText('Earlier message')).toHaveCount(1);
  expect(await page.evaluate(() => window.__announced)).toHaveLength(1);
});
