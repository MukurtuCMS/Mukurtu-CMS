import { test, expect, Page } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';
import { ADMIN_STATE } from '~helpers/auth-state';
import { gotoReady } from '~helpers/preview';

/**
 * Several files picked at once upload one request at a time.
 *
 * Core sent every picked file in one request, so the server's upload limit
 * applied to all of them together: on the import page, files that were each
 * well under the limit failed as a batch, and the oversized request broke the
 * session. mukurtu_core/sequential-upload sends each file on its own.
 */

test.use({ storageState: ADMIN_STATE });

// Both tests upload into the same account's import, so they can't overlap.
test.describe.configure({ mode: 'serial' });

/** Time for a run of uploads to finish on a cold or busy preview, in ms. */
const SETTLE = 60_000;

const RESOURCES = path.join(__dirname, '..', 'resources');

const file = (name: string, mimeType: string) => ({
  name,
  mimeType,
  buffer: fs.readFileSync(path.join(RESOURCES, name)),
});

const IMAGE = file('image-test.png', 'image/png');
const AUDIO_1 = file('mukurtu-intro1.mp3', 'audio/mpeg');
const AUDIO_2 = file('mukurtu-intro2.mp3', 'audio/mpeg');
const NOT_ALLOWED = {
  name: 'not-allowed.xyz',
  mimeType: 'application/octet-stream',
  buffer: Buffer.from('not a media file'),
};

/**
 * Lists the files in each upload request the Media/Binary Files field sends.
 *
 * Read in a route handler, because a request event doesn't carry the body of
 * a multipart upload.
 */
async function watchUploads(page: Page): Promise<string[][]> {
  const uploads: string[][] = [];
  await page.route(/element_parents=binary_files/, async (route) => {
    const body = route.request().postDataBuffer()?.toString('latin1') ?? '';
    uploads.push([...body.matchAll(/; filename="([^"]+)"/g)].map((match) => match[1]));
    await route.continue();
  });
  return uploads;
}

/** Text Drupal.announce() has passed to screen readers. */
const announced = (page: Page) => page.locator('#drupal-live-announce');

/** Lists the file the field shows for an upload, allowing a renamed copy. */
const listed = (page: Page, base: string, extension: string) => page
  .getByRole('link', { name: new RegExp(`^${base}(_\\d+)?\\.${extension}$`) });

/**
 * Clears this account's import.
 *
 * Gin moves Reset into a menu, so this clicks the form's own button.
 */
async function reset(page: Page) {
  // Wait for the reload itself: the URL doesn't change, so waitForURL()
  // would return before the page is replaced.
  const reloaded = page.waitForEvent('framenavigated', (frame) => frame === page.mainFrame());
  await page.locator('#edit-reset').evaluate((button: HTMLInputElement) => button.click());
  await reloaded;
  await page.waitForLoadState('load');
}

// Start from an empty form, and leave one for admin-reflow's scan of this page.
test.beforeEach(async ({ page }) => {
  await gotoReady(page, '/admin/import');
  await reset(page);
});

test.afterEach(async ({ page }) => {
  await reset(page);
});

test('sends each picked file in its own request', async ({ page }) => {
  const uploads = await watchUploads(page);

  await page.locator('[data-drupal-selector="edit-binary-files-upload"]')
    .setInputFiles([IMAGE, AUDIO_1, AUDIO_2]);

  await expect(announced(page)).toContainText('Finished uploading 3 files.', { timeout: SETTLE });
  expect(uploads).toEqual([
    ['image-test.png'],
    ['mukurtu-intro1.mp3'],
    ['mukurtu-intro2.mp3'],
  ]);
  await expect(listed(page, 'image-test', 'png')).toBeVisible();
  await expect(listed(page, 'mukurtu-intro1', 'mp3')).toBeVisible();
  await expect(listed(page, 'mukurtu-intro2', 'mp3')).toBeVisible();
});

test('keeps a refused file\'s error while the rest upload', async ({ page }) => {
  await page.locator('[data-drupal-selector="edit-binary-files-upload"]')
    .setInputFiles([IMAGE, NOT_ALLOWED, AUDIO_1]);

  await expect(announced(page)).toContainText(
    '1 of 3 files could not be uploaded.',
    { timeout: SETTLE },
  );
  // The refusal named the file, and the upload after it didn't replace it.
  await expect(page.locator('[data-mukurtu-upload-messages]')).toContainText('not-allowed.xyz');
  await expect(listed(page, 'image-test', 'png')).toBeVisible();
  await expect(listed(page, 'mukurtu-intro1', 'mp3')).toBeVisible();
});
