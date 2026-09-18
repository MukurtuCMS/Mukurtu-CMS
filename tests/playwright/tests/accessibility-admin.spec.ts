import { test } from '@playwright/test';
import { Login } from '~components/login';
import { memberAccount } from '~helpers/a11y-credentials';
import { auditPage } from '~helpers/axe';
import { discoverItemUrl, openForAudit } from '~helpers/page-inventory';
import { adminPages, adminDiscoveredPages } from '~helpers/page-inventory-admin';

/**
 * Phase 2 (admin/authoring, WCAG 2.1 AA + ATAG 2.0) automated accessibility
 * scans -- the admin-routes equivalent of accessibility.spec.ts. Visits
 * every page in docs/accessibility/page-inventory-admin.md and records axe
 * results. Report-only, same as the Phase 1 suite: see
 * docs/accessibility/README.md for the program this feeds.
 */

/**
 * Phase 2 results are written under a phase2- prefix. Both phases write
 * into the same test-results/a11y[-extra] directory keyed on slug alone,
 * so an admin page sharing a slug with a Phase 1 page would silently
 * overwrite it and the run would still go green.
 */
test.describe('Accessibility (admin): representative pages', () => {
  test.beforeEach(async ({ page }) => {
    const account = memberAccount();
    const login = new Login(page);
    await login.login(account.username, account.password);
  });

  for (const { slug, path } of adminPages) {
    test(`axe scan: ${slug}`, async ({ page }, testInfo) => {
      const blocked = await openForAudit(page, path);
      test.skip(blocked !== null, blocked ?? '');
      await auditPage(page, testInfo, `phase2-${slug}`);
    });
  }

  for (const { slug, listPath, itemLink, pathSuffix } of adminDiscoveredPages) {
    test(`axe scan: ${slug}`, async ({ page }, testInfo) => {
      const url = await discoverItemUrl(page, listPath, itemLink, pathSuffix);
      test.skip(url === null, `No item link matching "${itemLink}" found on ${listPath}. Seed default content first.`);
      const blocked = await openForAudit(page, url);
      test.skip(blocked !== null, blocked ?? '');
      await auditPage(page, testInfo, `phase2-${slug}`);
    });
  }
});
