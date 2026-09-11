import { test } from '@playwright/test';
import { Login } from '~components/login';
import { auditPage } from '~helpers/axe';
import {
  anonymousPages,
  discoveredPages,
  memberPages,
  memberDiscoveredPages,
  discoverItemUrl,
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

  for (const { slug, listPath, itemLink } of discoveredPages) {
    test(`axe scan: ${slug}`, async ({ page }, testInfo) => {
      const url = await discoverItemUrl(page, listPath, itemLink);
      test.skip(url === null, `No item link matching "${itemLink}" found on ${listPath}. Seed default content first.`);
      await page.goto(url);
      await auditPage(page, testInfo, slug);
    });
  }
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
