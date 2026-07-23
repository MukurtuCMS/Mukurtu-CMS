import { test } from '@playwright/test';
import { Login } from '~components/login';
import {
  checkReflow,
  checkTextZoom,
  checkFocusVisible,
  checkLinkText,
  checkKeyboardTrap,
} from '~helpers/automated-checks';
import {
  anonymousPages,
  discoveredPages,
  memberPages,
  memberDiscoveredPages,
  discoverItemUrl,
} from '~helpers/page-inventory';

/**
 * Automated checks beyond axe-core: reflow/zoom, focus visibility, link
 * text quality, and a keyboard-trap smoke test. These push automation
 * further into territory the manual checklist used to cover exclusively —
 * see docs/accessibility/manual-checklist.md for what's been converted here
 * versus what still needs a human. Report-only, same as accessibility.spec.ts.
 */
async function runAutomatedChecks(page: import('@playwright/test').Page, testInfo: import('@playwright/test').TestInfo, slug: string): Promise<void> {
  await checkLinkText(page, testInfo, slug);
  await checkFocusVisible(page, testInfo, slug);
  await checkKeyboardTrap(page, testInfo, slug);
  // Reflow/zoom resize the viewport/fonts, so run them last.
  await checkReflow(page, testInfo, slug);
  await checkTextZoom(page, testInfo, slug);
}

test.describe('Automated checks: anonymous pages', () => {
  for (const { slug, path } of anonymousPages) {
    test(`automated checks: ${slug}`, async ({ page }, testInfo) => {
      await page.goto(path);
      await runAutomatedChecks(page, testInfo, slug);
    });
  }

  for (const { slug, listPath, itemLink } of discoveredPages) {
    test(`automated checks: ${slug}`, async ({ page }, testInfo) => {
      const url = await discoverItemUrl(page, listPath, itemLink);
      test.skip(url === null, `No item link matching "${itemLink}" found on ${listPath}. Seed default content first.`);
      await page.goto(url);
      await runAutomatedChecks(page, testInfo, slug);
    });
  }
});

test.describe('Automated checks: member pages', () => {
  test.beforeEach(async ({ page }) => {
    const login = new Login(page);
    await login.login(
      process.env.A11Y_USERNAME ?? 'admin',
      process.env.A11Y_PASSWORD ?? 'admin',
    );
  });

  for (const { slug, path } of memberPages) {
    test(`automated checks: ${slug}`, async ({ page }, testInfo) => {
      await page.goto(path);
      await runAutomatedChecks(page, testInfo, slug);
    });
  }

  for (const { slug, listPath, itemLink } of memberDiscoveredPages) {
    test(`automated checks: ${slug}`, async ({ page }, testInfo) => {
      const url = await discoverItemUrl(page, listPath, itemLink);
      test.skip(url === null, `No item link matching "${itemLink}" found on ${listPath} for this member.`);
      await page.goto(url);
      await runAutomatedChecks(page, testInfo, slug);
    });
  }
});
