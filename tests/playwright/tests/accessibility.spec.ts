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
