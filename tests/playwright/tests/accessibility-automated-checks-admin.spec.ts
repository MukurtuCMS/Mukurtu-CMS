import { test } from '@playwright/test';
import { Login } from '~components/login';
import {
  checkReflow,
  checkTextZoom,
  checkFocusVisible,
  checkLinkText,
  checkKeyboardTrap,
} from '~helpers/automated-checks';
import { discoverItemUrl } from '~helpers/page-inventory';
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

test.describe('Automated checks (admin): representative pages', () => {
  test.beforeEach(async ({ page }) => {
    const login = new Login(page);
    await login.login(
      process.env.A11Y_USERNAME ?? 'admin',
      process.env.A11Y_PASSWORD ?? 'admin',
    );
  });

  for (const { slug, path } of adminPages) {
    test(`automated checks: ${slug}`, async ({ page }, testInfo) => {
      // Admin content-creation forms have far more focusable fields than
      // Phase 1's visitor pages, so checkKeyboardTrap's tab budget and
      // checkFocusVisible's per-element loop take longer -- the default
      // 60s test timeout isn't enough for the full 5-check pipeline here.
      testInfo.setTimeout(120_000);
      await page.goto(path);
      await runAutomatedChecks(page, testInfo, slug);
    });
  }

  for (const { slug, listPath, itemLink, pathSuffix } of adminDiscoveredPages) {
    test(`automated checks: ${slug}`, async ({ page }, testInfo) => {
      testInfo.setTimeout(120_000);
      const url = await discoverItemUrl(page, listPath, itemLink, pathSuffix);
      test.skip(url === null, `No item link matching "${itemLink}" found on ${listPath}. Seed default content first.`);
      await page.goto(url);
      await runAutomatedChecks(page, testInfo, slug);
    });
  }
});
