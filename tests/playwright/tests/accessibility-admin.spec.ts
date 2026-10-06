import { test } from '@playwright/test';
import { Login } from '~components/login';
import { adminAccount } from '~helpers/a11y-credentials';
import { auditPage } from '~helpers/axe';
import { discoverNodeManageUrl, openForAudit, skipOrFailCoverage } from '~helpers/page-inventory';
import { adminPages } from '~helpers/page-inventory-admin';

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
    // adminAccount(), not memberAccount(): these are admin routes, and a
    // representative member must not be able to reach them. While the
    // A11Y_* secrets were unset, memberAccount() fell back to admin/admin
    // and these scans worked by accident; the moment a real member account
    // was configured every one of them 403'd and skipped silently.
    const account = adminAccount();
    const login = new Login(page);
    await login.login(account.username, account.password);
  });

  for (const { slug, path } of adminPages) {
    test(`axe scan: ${slug}`, async ({ page }, testInfo) => {
      const blocked = await openForAudit(page, path);
      skipOrFailCoverage(slug, blocked !== null, blocked ?? '');
      await auditPage(page, testInfo, `phase2-${slug}`);
    });
  }

  // CollectionOrganizationController, regression coverage for the fixed
  // #1978 defect. Built from a node id rather than discovered off
  // /collections: the route is /node/{node}/organization with an
  // entity:node parameter, so appending /organization to a collection's
  // path alias gives a 404. That is what this scan did until now, and it
  // never once ran. See issue #2250.
  test('axe scan: phase2-collection-organization', async ({ page }, testInfo) => {
    const url = await discoverNodeManageUrl(page, 'collection', (nid) => `/node/${nid}/organization`);
    skipOrFailCoverage('collection-organization', url === null, 'No collection found in /admin/content. Seed default content first.');
    const blocked = await openForAudit(page, url);
    skipOrFailCoverage('collection-organization', blocked !== null, blocked ?? '');
    await auditPage(page, testInfo, 'phase2-collection-organization');
  });
});
