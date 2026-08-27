import { test } from '@playwright/test';
import { Login } from '~components/login';
import { auditPage } from '~helpers/axe';
import { discoverItemUrl } from '~helpers/page-inventory';
import { adminPages, adminDiscoveredPages } from '~helpers/page-inventory-admin';

/**
 * Phase 2 (admin/authoring, WCAG 2.1 AA + ATAG 2.0) automated accessibility
 * scans -- the admin-routes equivalent of accessibility.spec.ts. Visits
 * every page in docs/accessibility/page-inventory-admin.md and records axe
 * results. Report-only, same as the Phase 1 suite: see
 * docs/accessibility/README.md for the program this feeds.
 */

test.describe('Accessibility (admin): representative pages', () => {
  test.beforeEach(async ({ page }) => {
    const login = new Login(page);
    await login.login(
      process.env.A11Y_USERNAME ?? 'admin',
      process.env.A11Y_PASSWORD ?? 'admin',
    );
  });

  for (const { slug, path } of adminPages) {
    test(`axe scan: ${slug}`, async ({ page }, testInfo) => {
      await page.goto(path);
      await auditPage(page, testInfo, slug);
    });
  }

  for (const { slug, listPath, itemLink, pathSuffix } of adminDiscoveredPages) {
    test(`axe scan: ${slug}`, async ({ page }, testInfo) => {
      const url = await discoverItemUrl(page, listPath, itemLink, pathSuffix);
      test.skip(url === null, `No item link matching "${itemLink}" found on ${listPath}. Seed default content first.`);
      await page.goto(url);
      await auditPage(page, testInfo, slug);
    });
  }
});
