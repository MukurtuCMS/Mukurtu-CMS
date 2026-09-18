import { test } from '@playwright/test';
import { Login } from '~components/login';
import { auditPage } from '~helpers/axe';
import {
  anonymousPages,
  discoveredPages,
  memberPages,
  memberDiscoveredPages,
  managePages,
  discoverItemUrl,
  discoverCommunityManageUrl,
  discoverProtocolUrl,
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
 * Automated accessibility scans (axe-core, WCAG 2.1 A/AA).
 *
 * Visits every page in the audit inventory (docs/accessibility/
 * page-inventory.md at the profile root) and records axe results. Scans are
 * report-only: they never fail, results land in test-results/a11y/ and are
 * attached to the Playwright report. See docs/accessibility/README.md for
 * the program this feeds.
 */

test.describe('Accessibility: anonymous pages', () => {
  for (const { slug, path } of anonymousPages) {
    test(`axe scan: ${slug}`, async ({ page }, testInfo) => {
      await page.goto(path);
      await auditPage(page, testInfo, slug);
    });
  }

  for (const { slug, listPath, itemLink, pathSuffix } of discoveredPages) {
    test(`axe scan: ${slug}`, async ({ page }, testInfo) => {
      const url = await discoverItemUrl(page, listPath, itemLink, pathSuffix);
      test.skip(url === null, `No item link matching "${itemLink}" found on ${listPath}. Seed default content first.`);
      await page.goto(url);
      await auditPage(page, testInfo, slug);
    });
  }

  test('axe scan: protocol-local-contexts', async ({ page }, testInfo) => {
    const url = await discoverProtocolUrl(page, (slug) => `/protocol/${slug}/local-contexts`);
    test.skip(url === null, 'No community with a linked protocol found. Seed default content first.');
    await page.goto(url);
    await auditPage(page, testInfo, 'protocol-local-contexts');
  });
});

test.describe('Accessibility: member pages', () => {
  test.beforeEach(async ({ page }) => {
    const login = new Login(page);
    await login.login(
      process.env.A11Y_USERNAME ?? 'admin',
      process.env.A11Y_PASSWORD ?? 'admin',
    );
  });

  for (const { slug, path } of memberPages) {
    test(`axe scan: ${slug}`, async ({ page }, testInfo) => {
      await page.goto(path);
      await auditPage(page, testInfo, slug);
    });
  }

  for (const { slug, listPath, itemLink } of memberDiscoveredPages) {
    test(`axe scan: ${slug}`, async ({ page }, testInfo) => {
      const url = await discoverItemUrl(page, listPath, itemLink);
      test.skip(url === null, `No item link matching "${itemLink}" found on ${listPath} for this member.`);
      await page.goto(url);
      await auditPage(page, testInfo, slug);
    });
  }
});

/**
 * Pages reachable by non-admin community/protocol roles (Community
 * Managers, protocol contributors/curators/stewards) -- a Phase 1
 * coverage gap distinct from both plain member pages and the
 * admin/authoring (ATAG) surface covered separately by
 * accessibility-admin.spec.ts. Override the account with
 * A11Y_MANAGER_USERNAME/A11Y_MANAGER_PASSWORD for representative results;
 * the admin/admin fallback can reach these routes but adds Drupal-toolbar
 * noise and isn't representative of the actual roles that use them.
 */
test.describe('Accessibility: manage-adjacent pages', () => {
  test.beforeEach(async ({ page }) => {
    const login = new Login(page);
    await login.login(
      process.env.A11Y_MANAGER_USERNAME ?? 'admin',
      process.env.A11Y_MANAGER_PASSWORD ?? 'admin',
    );
  });

  for (const { slug, path } of managePages) {
    test(`axe scan: ${slug}`, async ({ page }, testInfo) => {
      await page.goto(path);
      await auditPage(page, testInfo, slug);
    });
  }

  test('axe scan: manage-community-local-contexts-projects', async ({ page }, testInfo) => {
    const url = await discoverCommunityManageUrl(page, (slug) => `/communities/community/${slug}/local-contexts/projects`);
    test.skip(url === null, 'No community found. Seed default content first.');
    await page.goto(url);
    await auditPage(page, testInfo, 'manage-community-local-contexts-projects');
  });

  test('axe scan: manage-protocol-local-contexts-projects', async ({ page }, testInfo) => {
    const url = await discoverProtocolUrl(page, (slug) => `/protocols/protocol/${slug}/local-contexts/projects`);
    test.skip(url === null, 'No community with a linked protocol found. Seed default content first.');
    await page.goto(url);
    await auditPage(page, testInfo, 'manage-protocol-local-contexts-projects');
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
test.describe('Accessibility: public submission form', () => {
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

  test('axe scan: submission-form', async ({ page }, testInfo) => {
    const reachable = await submissionFormIsReachable(page);
    test.skip(!reachable, `${SUBMISSION_FORM_PATH} is not reachable. Submission forms ship disabled and this account could not enable one.`);
    await auditPage(page, testInfo, 'submission-form');
  });

  test('axe scan: submission-thank-you', async ({ page }, testInfo) => {
    await page.goto(SUBMISSION_THANK_YOU_PATH);
    await auditPage(page, testInfo, 'submission-thank-you');
  });
});
