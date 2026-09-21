import { expect, test } from '@playwright/test';
import { managerAccount, memberAccount, noteFallbackAccount } from '~helpers/a11y-credentials';
import { MANAGER_STATE, MEMBER_STATE } from '~helpers/auth-state';
import { auditPage } from '~helpers/axe';
import { gotoReady } from '~helpers/preview';
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
      const blocked = await openForAudit(page, path);
      test.skip(blocked !== null, blocked ?? '');
      await auditPage(page, testInfo, slug);
    });
  }

  for (const { slug, listPath, itemLink, pathSuffix } of discoveredPages) {
    test(`axe scan: ${slug}`, async ({ page }, testInfo) => {
      const url = await discoverItemUrl(page, listPath, itemLink, pathSuffix);
      test.skip(url === null, `No item link matching "${itemLink}" found on ${listPath}. Seed default content first.`);
      const blocked = await openForAudit(page, url);
      test.skip(blocked !== null, blocked ?? '');
      await auditPage(page, testInfo, slug);
    });
  }

  test('axe scan: protocol-local-contexts', async ({ page }, testInfo) => {
    const url = await discoverProtocolUrl(page, (slug) => `/protocol/${slug}/local-contexts`);
    test.skip(url === null, 'No community with a linked protocol found. Seed default content first.');
    const blocked = await openForAudit(page, url);
    test.skip(blocked !== null, blocked ?? '');
    await auditPage(page, testInfo, 'protocol-local-contexts');
  });

  /**
   * The one assertion in a file of report-only scans.
   *
   * Since #2280 the signed-in blocks get their session from
   * `test.use({ storageState: ... })`. A session reaching this block by
   * mistake -- a `storageState` set at project level, or a stray
   * `test.use` outside the block that meant to have one -- would not fail
   * any scan above: every one of them would quietly audit the logged-in
   * version of a page that is in the inventory precisely because visitors
   * see it. The run would stay green and the report would look complete.
   */
  test('anonymous scans are anonymous', async ({ page }) => {
    await gotoReady(page, '/user/login');
    await expect(page.getByLabel('Username')).toBeVisible();
  });
});

test.describe('Accessibility: member pages', () => {
  // A session saved once by tests/auth.setup.ts, rather than a login in
  // every test. See src/helpers/auth-state.ts.
  test.use({ storageState: MEMBER_STATE });

  test.beforeEach(async ({}, testInfo) => {
    // Kept when the login went away: the report has to say when these
    // results were gathered as admin/admin rather than as the role, and
    // that annotation is per test.
    noteFallbackAccount(testInfo, memberAccount(), 'member');
  });

  for (const { slug, path } of memberPages) {
    test(`axe scan: ${slug}`, async ({ page }, testInfo) => {
      const blocked = await openForAudit(page, path);
      test.skip(blocked !== null, blocked ?? '');
      await auditPage(page, testInfo, slug);
    });
  }

  for (const { slug, listPath, itemLink } of memberDiscoveredPages) {
    test(`axe scan: ${slug}`, async ({ page }, testInfo) => {
      const url = await discoverItemUrl(page, listPath, itemLink);
      test.skip(url === null, `No item link matching "${itemLink}" found on ${listPath} for this member.`);
      const blocked = await openForAudit(page, url);
      test.skip(blocked !== null, blocked ?? '');
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
  // A session saved once by tests/auth.setup.ts, rather than a login in
  // every test. See src/helpers/auth-state.ts.
  test.use({ storageState: MANAGER_STATE });

  test.beforeEach(async ({}, testInfo) => {
    // Kept when the login went away: the report has to say when these
    // results were gathered as admin/admin rather than as the role, and
    // that annotation is per test.
    noteFallbackAccount(testInfo, managerAccount(), 'manage-adjacent');
  });

  for (const { slug, path } of managePages) {
    test(`axe scan: ${slug}`, async ({ page }, testInfo) => {
      const blocked = await openForAudit(page, path);
      test.skip(blocked !== null, blocked ?? '');
      await auditPage(page, testInfo, slug);
    });
  }

  test('axe scan: manage-community-local-contexts-projects', async ({ page }, testInfo) => {
    const url = await discoverCommunityManageUrl(page, (slug) => `/communities/community/${slug}/local-contexts/projects`);
    test.skip(url === null, 'No community found. Seed default content first.');
    const blocked = await openForAudit(page, url);
    test.skip(blocked !== null, blocked ?? '');
    await auditPage(page, testInfo, 'manage-community-local-contexts-projects');
  });

  test('axe scan: manage-protocol-local-contexts-projects', async ({ page }, testInfo) => {
    const url = await discoverProtocolUrl(page, (slug) => `/protocols/protocol/${slug}/local-contexts/projects`);
    test.skip(url === null, 'No community with a linked protocol found. Seed default content first.');
    const blocked = await openForAudit(page, url);
    test.skip(blocked !== null, blocked ?? '');
    await auditPage(page, testInfo, 'manage-protocol-local-contexts-projects');
  });
});
