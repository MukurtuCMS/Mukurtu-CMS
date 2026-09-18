import { test } from '@playwright/test';
import { Login } from '~components/login';
import { managerAccount, memberAccount, noteFallbackAccount } from '~helpers/a11y-credentials';
import {
  checkReflow,
  checkTextZoom,
  checkFocusVisible,
  checkLinkText,
  checkKeyboardTrap,
} from '~helpers/automated-checks';
import {
  anonymousPages,
  discoveredPages,
  memberPages,
  memberDiscoveredPages,
  managePages,
  discoverItemUrl,
  discoverCommunityManageUrl,
  discoverProtocolUrl,
  openForAudit,
} from '~helpers/page-inventory';
import {
  SUBMISSION_FORM_PATH,
  SUBMISSION_THANK_YOU_PATH,
  SubmissionFormState,
  enableSubmissionForm,
  restoreSubmissionForm,
  submissionFormIsReachable,
} from '~helpers/submissions';

/**
 * Automated checks beyond axe-core: reflow/zoom, focus visibility, link
 * text quality, and a keyboard-trap smoke test. These push automation
 * further into territory the manual checklist used to cover exclusively —
 * see docs/accessibility/manual-checklist.md for what's been converted here
 * versus what still needs a human. Report-only, same as accessibility.spec.ts.
 */
async function runAutomatedChecks(page: import('@playwright/test').Page, testInfo: import('@playwright/test').TestInfo, slug: string): Promise<void> {
  await checkLinkText(page, testInfo, slug);
  await checkFocusVisible(page, testInfo, slug);
  await checkKeyboardTrap(page, testInfo, slug);
  // Reflow/zoom resize the viewport/fonts, so run them last.
  await checkReflow(page, testInfo, slug);
  await checkTextZoom(page, testInfo, slug);
}

test.describe('Automated checks: anonymous pages', () => {
  for (const { slug, path } of anonymousPages) {
    test(`automated checks: ${slug}`, async ({ page }, testInfo) => {
      const blocked = await openForAudit(page, path);
      test.skip(blocked !== null, blocked ?? '');
      await runAutomatedChecks(page, testInfo, slug);
    });
  }

  for (const { slug, listPath, itemLink, pathSuffix } of discoveredPages) {
    test(`automated checks: ${slug}`, async ({ page }, testInfo) => {
      const url = await discoverItemUrl(page, listPath, itemLink, pathSuffix);
      test.skip(url === null, `No item link matching "${itemLink}" found on ${listPath}. Seed default content first.`);
      const blocked = await openForAudit(page, url);
      test.skip(blocked !== null, blocked ?? '');
      await runAutomatedChecks(page, testInfo, slug);
    });
  }

  test('automated checks: protocol-local-contexts', async ({ page }, testInfo) => {
    const url = await discoverProtocolUrl(page, (slug) => `/protocol/${slug}/local-contexts`);
    test.skip(url === null, 'No community with a linked protocol found. Seed default content first.');
    const blocked = await openForAudit(page, url);
    test.skip(blocked !== null, blocked ?? '');
    await runAutomatedChecks(page, testInfo, 'protocol-local-contexts');
  });
});

test.describe('Automated checks: member pages', () => {
  test.beforeEach(async ({ page }, testInfo) => {
    const account = memberAccount();
    noteFallbackAccount(testInfo, account, 'member');
    const login = new Login(page);
    await login.login(account.username, account.password);
  });

  for (const { slug, path } of memberPages) {
    test(`automated checks: ${slug}`, async ({ page }, testInfo) => {
      const blocked = await openForAudit(page, path);
      test.skip(blocked !== null, blocked ?? '');
      await runAutomatedChecks(page, testInfo, slug);
    });
  }

  for (const { slug, listPath, itemLink } of memberDiscoveredPages) {
    test(`automated checks: ${slug}`, async ({ page }, testInfo) => {
      const url = await discoverItemUrl(page, listPath, itemLink);
      test.skip(url === null, `No item link matching "${itemLink}" found on ${listPath} for this member.`);
      const blocked = await openForAudit(page, url);
      test.skip(blocked !== null, blocked ?? '');
      await runAutomatedChecks(page, testInfo, slug);
    });
  }
});

/**
 * Manage-adjacent pages -- see the matching describe block in
 * accessibility.spec.ts for the full rationale.
 */
test.describe('Automated checks: manage-adjacent pages', () => {
  test.beforeEach(async ({ page }, testInfo) => {
    const account = managerAccount();
    noteFallbackAccount(testInfo, account, 'manage-adjacent');
    const login = new Login(page);
    await login.login(account.username, account.password);
  });

  for (const { slug, path } of managePages) {
    test(`automated checks: ${slug}`, async ({ page }, testInfo) => {
      const blocked = await openForAudit(page, path);
      test.skip(blocked !== null, blocked ?? '');
      await runAutomatedChecks(page, testInfo, slug);
    });
  }

  test('automated checks: manage-community-local-contexts-projects', async ({ page }, testInfo) => {
    const url = await discoverCommunityManageUrl(page, (slug) => `/communities/community/${slug}/local-contexts/projects`);
    test.skip(url === null, 'No community found. Seed default content first.');
    const blocked = await openForAudit(page, url);
    test.skip(blocked !== null, blocked ?? '');
    await runAutomatedChecks(page, testInfo, 'manage-community-local-contexts-projects');
  });

  test('automated checks: manage-protocol-local-contexts-projects', async ({ page }, testInfo) => {
    const url = await discoverProtocolUrl(page, (slug) => `/protocols/protocol/${slug}/local-contexts/projects`);
    test.skip(url === null, 'No community with a linked protocol found. Seed default content first.');
    const blocked = await openForAudit(page, url);
    test.skip(blocked !== null, blocked ?? '');
    await runAutomatedChecks(page, testInfo, 'manage-protocol-local-contexts-projects');
  });
});

/**
 * The public submission form (mukurtu_submissions).
 *
 * Kept out of anonymousPages because it ships disabled: the suite has to
 * turn it on before it can be scanned, and turn it back off afterwards.
 * See ~helpers/submissions for why this is driven through the admin UI
 * rather than drush.
 */
test.describe('Automated checks: public submission form', () => {
  // Serial, so both tests share one worker. beforeAll/afterAll run once
  // per worker, and fullyParallel is on: split across two workers, one
  // worker's teardown can disable the form while the other is still
  // scanning it, or its setup can re-enable after the other has already
  // restored -- leaving the site enabled when the run ends.
  test.describe.configure({ mode: 'serial' });

  let previousState: SubmissionFormState = null;

  test.beforeAll(async ({ browser }) => {
    previousState = await enableSubmissionForm(browser);
  });

  test.afterAll(async ({ browser }) => {
    await restoreSubmissionForm(browser, previousState);
  });

  test('automated checks: submission-form', async ({ page }, testInfo) => {
    const reachable = await submissionFormIsReachable(page);
    test.skip(!reachable, `${SUBMISSION_FORM_PATH} is not reachable. Submission forms ship disabled and this account could not enable one.`);
    await runAutomatedChecks(page, testInfo, 'submission-form');
  });

  test('automated checks: submission-thank-you', async ({ page }, testInfo) => {
    const blocked = await openForAudit(page, SUBMISSION_THANK_YOU_PATH);
    test.skip(blocked !== null, blocked ?? '');
    await runAutomatedChecks(page, testInfo, 'submission-thank-you');
  });
});
