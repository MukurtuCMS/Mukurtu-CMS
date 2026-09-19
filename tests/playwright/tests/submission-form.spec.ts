import { test } from '@playwright/test';
import { auditPage } from '~helpers/axe';
import {
  checkReflow,
  checkTextZoom,
  checkFocusVisible,
  checkLinkText,
  checkKeyboardTrap,
} from '~helpers/automated-checks';
import {
  SUBMISSION_FORM_PATH,
  SUBMISSION_THANK_YOU_PATH,
  SubmissionFormState,
  enableSubmissionForm,
  restoreSubmissionForm,
  submissionFormIsReachable,
} from '~helpers/submissions';

async function runAutomatedChecks(page: import('@playwright/test').Page, testInfo: import('@playwright/test').TestInfo, slug: string): Promise<void> {
  await checkLinkText(page, testInfo, slug);
  await checkFocusVisible(page, testInfo, slug);
  await checkKeyboardTrap(page, testInfo, slug);
  // Reflow/zoom resize the viewport/fonts, so run them last.
  await checkReflow(page, testInfo, slug);
  await checkTextZoom(page, testInfo, slug);
}

/**
 * The public submission form (mukurtu_submissions).
 *
 * Kept out of anonymousPages because it ships disabled: the suite has to
 * turn it on before it can be scanned, and turn it back off afterwards.
 * See ~helpers/submissions for why this is driven through the admin UI
 * rather than drush.
 *
 * This page gets its own file combining both the axe-core scan
 * (accessibility.spec.ts elsewhere) and the automated checks
 * (accessibility-automated-checks.spec.ts elsewhere), rather than living in
 * either of those. Both of those files used to carry an identical "public
 * submission form" describe block, each with its own beforeAll/afterAll
 * enabling and restoring the same site-wide setting. With
 * playwright.config.ts's fullyParallel:true, the two files' blocks always
 * ran concurrently in separate workers, each independently logging in and
 * toggling the same shared setting -- a login timeout every run.
 * Consolidating into one describe block in one file removes the double
 * execution: there is exactly one beforeAll/afterAll pair for this setting
 * across the whole suite.
 */
test.describe('Public submission form', () => {
  // Serial, so all four tests below share one worker and run in the order
  // written. beforeAll/afterAll run once per worker, and fullyParallel is
  // on: without this, Playwright could still schedule these tests onto
  // different workers, each running its own beforeAll/afterAll against the
  // same shared setting -- the failure mode this file exists to eliminate.
  test.describe.configure({ mode: 'serial' });

  let previousState: SubmissionFormState = null;

  // The default 60s hook timeout (playwright.config.ts's `timeout`, which
  // also governs beforeAll/afterAll) isn't enough headroom on top of
  // LOGIN_TIMEOUT_MS's own 60s: a login that takes close to that long left
  // no time for the rest of the hook (navigating to the settings form,
  // checking boxes, saving), so the hook itself got killed mid-flight
  // instead of the login failing cleanly. 120s gives that margin.
  test.beforeAll(async ({ browser }, testInfo) => {
    testInfo.setTimeout(120000);
    previousState = await enableSubmissionForm(browser);
  });

  test.afterAll(async ({ browser }, testInfo) => {
    testInfo.setTimeout(120000);
    await restoreSubmissionForm(browser, previousState);
  });

  test('axe scan: submission-form', async ({ page }, testInfo) => {
    const reachable = await submissionFormIsReachable(page);
    test.skip(!reachable, `${SUBMISSION_FORM_PATH} is not reachable. Submission forms ship disabled and this account could not enable one.`);
    await auditPage(page, testInfo, 'submission-form');
  });

  test('axe scan: submission-thank-you', async ({ page }, testInfo) => {
    await page.goto(SUBMISSION_THANK_YOU_PATH);
    await auditPage(page, testInfo, 'submission-thank-you');
  });

  test('automated checks: submission-form', async ({ page }, testInfo) => {
    const reachable = await submissionFormIsReachable(page);
    test.skip(!reachable, `${SUBMISSION_FORM_PATH} is not reachable. Submission forms ship disabled and this account could not enable one.`);
    await runAutomatedChecks(page, testInfo, 'submission-form');
  });

  test('automated checks: submission-thank-you', async ({ page }, testInfo) => {
    await page.goto(SUBMISSION_THANK_YOU_PATH);
    await runAutomatedChecks(page, testInfo, 'submission-thank-you');
  });
});
