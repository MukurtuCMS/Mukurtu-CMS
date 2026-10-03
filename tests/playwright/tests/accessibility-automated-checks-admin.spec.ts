import { test } from '@playwright/test';
import { ADMIN_STATE } from '~helpers/auth-state';
import {
  checkReflow,
  checkTextZoom,
  checkFocusVisible,
  checkLinkText,
  checkKeyboardTrap,
} from '~helpers/automated-checks';
import { discoverItemUrl, openForAudit } from '~helpers/page-inventory';
import { adminPages, adminDiscoveredPages } from '~helpers/page-inventory-admin';

/**
 * Phase 2 (admin/authoring) equivalent of accessibility-automated-checks
 * .spec.ts -- reflow/zoom, focus visibility, link text quality, and a
 * keyboard-trap smoke test against the admin-routes inventory. Report-only,
 * same as the Phase 1 suite.
 */
async function runAutomatedChecks(page: import('@playwright/test').Page, testInfo: import('@playwright/test').TestInfo, slug: string): Promise<void> {
  await checkLinkText(page, testInfo, slug);
  await checkFocusVisible(page, testInfo, slug);
  await checkKeyboardTrap(page, testInfo, slug);
  // Reflow/zoom resize the viewport/fonts, so run them last.
  await checkReflow(page, testInfo, slug);
  await checkTextZoom(page, testInfo, slug);
}

/**
 * Phase 2 results are written under a phase2- prefix. Both phases write
 * into the same test-results/a11y[-extra] directory keyed on slug alone,
 * so an admin page sharing a slug with a Phase 1 page would silently
 * overwrite it and the run would still go green.
 */
test.describe('Automated checks (admin): representative pages', () => {
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
    test(`automated checks: ${slug}`, async ({ page }, testInfo) => {
      // Admin content-creation forms have far more focusable fields than
      // Phase 1's visitor pages, so checkKeyboardTrap's tab budget and
      // checkFocusVisible's per-element loop take longer -- the default
      // 60s test timeout isn't enough for the full 5-check pipeline here.
      testInfo.setTimeout(120_000);
      const blocked = await openForAudit(page, path);
      test.skip(blocked !== null, blocked ?? '');
      await runAutomatedChecks(page, testInfo, `phase2-${slug}`);
    });
  }

  for (const { slug, listPath, itemLink, pathSuffix } of adminDiscoveredPages) {
    test(`automated checks: ${slug}`, async ({ page }, testInfo) => {
      testInfo.setTimeout(120_000);
      const url = await discoverItemUrl(page, listPath, itemLink, pathSuffix);
      test.skip(url === null, `No item link matching "${itemLink}" found on ${listPath}. Seed default content first.`);
      const blocked = await openForAudit(page, url);
      test.skip(blocked !== null, blocked ?? '');
      await runAutomatedChecks(page, testInfo, `phase2-${slug}`);
    });
  }
});
