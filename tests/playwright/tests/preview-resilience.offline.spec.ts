import { expect, test, Page } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';
import {
  gotoReady,
  tugboatPreviewState,
  PreviewUnavailableError,
} from '~helpers/preview';

/**
 * Tests for the Tugboat holding-page tolerance in src/helpers/preview.ts.
 *
 * Runs entirely against page.route(), with no site and no preview: the thing
 * under test is how the suite behaves when the environment is *not* there,
 * which is impossible to arrange on demand against a real preview. That is
 * also why this spec lives in its own `offline` project in
 * playwright.config.ts -- the `chromium` project depends on
 * default-content.spec.ts, which needs a live site.
 *
 * Both directions are pinned on purpose. A detector that quietly stops
 * matching would put the suite straight back to auditing Tugboat's own page
 * and reporting it clean, and nothing in a green run would say so.
 */

const SUSPENDED_PAGE = fs.readFileSync(
  path.join(__dirname, '..', 'fixtures', 'tugboat-preview-suspended.html'),
  'utf8',
);

/** A stand-in for the real thing: any page whose title is not Tugboat's. */
const REAL_PAGE = `<!DOCTYPE html><html lang="en"><head><title>Log in | Mukurtu CMS</title></head>
<body><form id="user-login-form"><label for="edit-name">Username</label>
<input id="edit-name" name="name" required></form></body></html>`;

/** Tugboat's wording for a state it will not come back from on its own. */
const FAILED_PAGE = SUSPENDED_PAGE
  .replace('Tugboat - Preview is suspended', 'Tugboat - Preview is failed')
  .replace('>suspended<', '>failed<');

/**
 * The holding page as Tugboat really behaves: it reloads itself when the
 * preview comes back, rather than waiting to be asked again.
 */
const SUSPENDED_THEN_RELOAD = SUSPENDED_PAGE.replace(
  '</body>',
  '<script>setTimeout(function () { location.reload(true); }, 60);</script></body>',
);

/**
 * The same thing at its worst timing. The reload is scheduled far enough out
 * that gotoReady()'s next navigation is already in flight (and held open by
 * serve()'s delay) when it fires, which is what makes page.goto() throw
 * rather than return null.
 */
const SUSPENDED_THEN_LATE_RELOAD = SUSPENDED_PAGE.replace(
  '</body>',
  '<script>setTimeout(function () { location.reload(true); }, 200);</script></body>',
);

const URL = 'http://preview.test/user/login';

type Served = string | { body: string; delayMs: number };

/**
 * Serves each body in turn, repeating the last one for every later request,
 * and reports how many requests were made.
 *
 * A delay holds a response open, which is how the "in flight" case below
 * becomes reliable rather than a race against a setTimeout.
 */
async function serve(page: Page, bodies: Served[]): Promise<() => number> {
  let served = 0;
  await page.route('**/*', async (route) => {
    const next = bodies[Math.min(served, bodies.length - 1)];
    served += 1;
    const { body, delayMs } = typeof next === 'string' ? { body: next, delayMs: 0 } : next;
    if (delayMs > 0) {
      await new Promise((resolve) => setTimeout(resolve, delayMs));
    }
    // 200, as Tugboat's proxy serves it.
    await route.fulfill({ status: 200, contentType: 'text/html', body });
  });
  return () => served;
}

test('waits out a suspended preview and returns the real page', async ({ page }) => {
  const served = await serve(page, [SUSPENDED_PAGE, SUSPENDED_PAGE, REAL_PAGE]);

  const response = await gotoReady(page, URL, { timeoutMs: 10_000, pollIntervalMs: 50 });

  expect(response?.status()).toBe(200);
  await expect(page).toHaveTitle('Log in | Mukurtu CMS');
  await expect(page.getByLabel('Username')).toBeVisible();
  expect(served()).toBe(3);
});

test('gives up with a message naming the preview state', async ({ page }) => {
  await serve(page, [SUSPENDED_PAGE]);

  const failure = await gotoReady(page, URL, { timeoutMs: 200, pollIntervalMs: 50 })
    .then(() => null, (error: Error) => error);

  expect(failure).toBeInstanceOf(PreviewUnavailableError);
  expect(failure?.message).toContain('stopped serving the site mid-run');
  expect(failure?.message).toContain('suspended');
  expect(failure?.message).toContain(URL);
});

test('does not wait out a state Tugboat will not recover from', async ({ page }) => {
  const served = await serve(page, [FAILED_PAGE]);

  const failure = await gotoReady(page, URL, { timeoutMs: 30_000, pollIntervalMs: 50 })
    .then(() => null, (error: Error) => error);

  expect(failure).toBeInstanceOf(PreviewUnavailableError);
  expect(failure?.message).toContain('will not recover on its own');
  // One attempt, not a 30s wait for a state that is never coming back.
  expect(served()).toBe(1);
});

test('returns a usable Response even when Tugboat reloads the page itself', async ({ page }) => {
  // Without the second navigation in gotoReady(), this comes back as null:
  // Tugboat's own location.reload(true) supersedes our navigation, so
  // page.goto() has no Response to report. openForAudit() reads that as
  // "no HTTP response; nothing to audit" and skips the scan, and
  // submissionFormIsReachable() reads it as unreachable -- a page silently
  // not scanned, on a preview that was up the whole time.
  await serve(page, [SUSPENDED_THEN_RELOAD, REAL_PAGE]);

  const response = await gotoReady(page, URL, { timeoutMs: 10_000, pollIntervalMs: 50 });

  expect(response).not.toBeNull();
  expect(response?.status()).toBe(200);
  await expect(page.getByLabel('Username')).toBeVisible();
});

test('survives Tugboat reloading over a navigation in flight', async ({ page }) => {
  // Playwright throws "interrupted by another navigation" here. Left
  // uncaught it fails the test outright, which is worse than the timeout it
  // replaced: the run goes red with a message about navigation rather than
  // about a preview that was merely on its way back.
  await serve(page, [SUSPENDED_THEN_LATE_RELOAD, { body: REAL_PAGE, delayMs: 600 }]);

  const response = await gotoReady(page, URL, { timeoutMs: 10_000, pollIntervalMs: 50 });

  expect(response).not.toBeNull();
  await expect(page.getByLabel('Username')).toBeVisible();
});

test('negative control: a real page is never taken for a holding page', async ({ page }) => {
  const served = await serve(page, [REAL_PAGE]);

  const response = await gotoReady(page, URL, { timeoutMs: 10_000, pollIntervalMs: 50 });

  expect(await tugboatPreviewState(page)).toBeNull();
  expect(response?.status()).toBe(200);
  // Exactly one navigation: no retry loop on a page that was fine already.
  expect(served()).toBe(1);
});
