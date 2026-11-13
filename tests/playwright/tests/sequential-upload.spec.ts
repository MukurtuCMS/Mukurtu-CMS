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

/**
 * Holds the first upload request until the returned function is called.
 *
 * Keeps an upload running for as long as a test needs. Lists the files in
 * every upload request, like watchUploads().
 */
async function holdFirstUpload(page: Page): Promise<{ uploads: string[][], release: () => void }> {
  const uploads: string[][] = [];
  let release = () => {};
  const held = new Promise<void>((resolve) => {
    release = resolve;
  });
  await page.route(/element_parents=binary_files/, async (route) => {
    const body = route.request().postDataBuffer()?.toString('latin1') ?? '';
    uploads.push([...body.matchAll(/; filename="([^"]+)"/g)].map((match) => match[1]));
    if (uploads.length === 1) {
      await held;
    }
    // The browser may have cancelled the request while it was held.
    await route.continue().catch(() => {});
  });
  return { uploads, release };
}

/**
 * Clicks a link away from the page.
 *
 * A real click, because browsers ask before leaving only after the person
 * has interacted with the page.
 */
async function clickAway(page: Page) {
  await page.evaluate(() => {
    const link = document.createElement('a');
    link.href = '/admin';
    link.id = 'test-leave-link';
    link.textContent = 'Leave';
    document.body.prepend(link);
  });
  await page.locator('#test-leave-link').click({ noWaitAfter: true });
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
  // Each announcement replaces the last one in the live region, so record
  // every call instead.
  await page.evaluate(() => {
    const announce = Drupal.announce;
    window.__announced = [];
    Drupal.announce = (text, priority) => {
      window.__announced.push(text);
      return announce(text, priority);
    };
  });
  await page.locator('[data-drupal-selector="edit-binary-files-upload"]')
    .setInputFiles([IMAGE, NOT_ALLOWED, AUDIO_1]);

  await expect(announced(page)).toContainText(
    '1 of 3 files could not be uploaded.',
    { timeout: SETTLE },
  );
  // The refusal named the file, and the upload after it didn't replace it.
  await expect(page.locator('[data-mukurtu-upload-messages]')).toContainText('not-allowed.xyz');
  // Screen readers hear which file failed, not only the summary.
  expect(await page.evaluate(() => window.__announced)).toContainEqual(
    expect.stringContaining('The specified file not-allowed.xyz could not be uploaded.'),
  );
  await expect(listed(page, 'image-test', 'png')).toBeVisible();
  await expect(listed(page, 'mukurtu-intro1', 'mp3')).toBeVisible();
});

test('doesn\'t send a file over the size limit', async ({ page }) => {
  // The server gives its own limit.
  expect(await page.evaluate(() => drupalSettings.mukurtuSequentialUpload?.maxFilesize))
    .toBeGreaterThan(0);

  // A file over the real limit would be about 100 MB, so lower it to fall
  // between the two audio files. Each response merges the server's limit
  // back into drupalSettings, so ignore those writes.
  await page.evaluate(() => {
    const settings = drupalSettings.mukurtuSequentialUpload;
    Object.defineProperty(settings, 'maxFilesize', { get: () => 48500, set: () => {}, enumerable: true });
    Object.defineProperty(settings, 'maxFilesizeLabel', { get: () => '48.5 KB', set: () => {}, enumerable: true });
    const announce = Drupal.announce;
    window.__announced = [];
    Drupal.announce = (text, priority) => {
      window.__announced.push(text);
      return announce(text, priority);
    };
  });
  const uploads = await watchUploads(page);

  // mukurtu-intro1.mp3 is 49,197 bytes; the other two are under 48,500.
  await page.locator('[data-drupal-selector="edit-binary-files-upload"]')
    .setInputFiles([IMAGE, AUDIO_1, AUDIO_2]);

  await expect(announced(page)).toContainText(
    '1 of 3 files could not be uploaded.',
    { timeout: SETTLE },
  );
  expect(uploads).toEqual([['image-test.png'], ['mukurtu-intro2.mp3']]);
  const refusal = 'The file mukurtu-intro1.mp3 could not be saved because it exceeds 48.5 KB, the maximum allowed size for uploads.';
  await expect(page.locator('[data-mukurtu-upload-messages]')).toContainText(refusal);
  expect(await page.evaluate(() => window.__announced)).toContainEqual(
    expect.stringContaining('could not be saved because it exceeds'),
  );
  // Announced once: the message on screen isn't an alert as well.
  await expect(page.locator('[data-mukurtu-upload-messages] [role="alert"]')).toHaveCount(0);
  await expect(page.getByText('Oops, something went wrong')).toHaveCount(0);
  await expect(listed(page, 'image-test', 'png')).toBeVisible();
  await expect(listed(page, 'mukurtu-intro2', 'mp3')).toBeVisible();
});

test('asks before leaving while files upload, and keeps uploading on Stay', async ({ page }) => {
  const { uploads, release } = await holdFirstUpload(page);
  await page.locator('[data-drupal-selector="edit-binary-files-upload"]')
    .setInputFiles([IMAGE, AUDIO_1]);
  await expect.poll(() => uploads.length).toBe(1);

  // The click doesn't finish until the question is answered, so answer it
  // from a listener.
  let asked = '';
  page.once('dialog', async (dialog) => {
    asked = dialog.type();
    await dialog.dismiss();
  });
  await clickAway(page);
  expect(asked).toBe('beforeunload');
  release();

  await expect(announced(page)).toContainText('Finished uploading 2 files.', { timeout: SETTLE });
  expect(uploads).toEqual([['image-test.png'], ['mukurtu-intro1.mp3']]);
});

test('sends no more files once the page is left', async ({ page }) => {
  const { uploads, release } = await holdFirstUpload(page);
  await page.locator('[data-drupal-selector="edit-binary-files-upload"]')
    .setInputFiles([IMAGE, AUDIO_1, AUDIO_2]);
  await expect.poll(() => uploads.length).toBe(1);

  page.once('dialog', (dialog) => dialog.accept());
  const left = page.waitForURL(/\/admin$/);
  await clickAway(page);
  await left;
  release();

  // Before, the queue sent the next file as the page was leaving.
  await page.waitForTimeout(3000);
  expect(uploads).toEqual([['image-test.png']]);

  // Back to the form for afterEach's reset.
  await gotoReady(page, '/admin/import');
});
