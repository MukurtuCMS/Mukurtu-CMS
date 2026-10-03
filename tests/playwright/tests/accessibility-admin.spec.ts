import { test } from '@playwright/test';
import { ADMIN_STATE } from '~helpers/auth-state';
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
  // ADMIN_STATE, not MEMBER_STATE: these are admin routes, and a
  // representative member must not be able to reach them. While the A11Y_*
  // secrets were unset, memberAccount() fell back to admin/admin and these
  // scans worked by accident; the moment a real member account was
  // configured every one of them 403'd and skipped silently.
  //
  // The session is saved once by tests/auth.setup.ts rather than logged in
  // per test; see src/helpers/auth-state.ts.
  test.use({ storageState: ADMIN_STATE });

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
