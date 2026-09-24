import { test } from '@playwright/test';
import { managerAccount, memberAccount, noteFallbackAccount } from '~helpers/a11y-credentials';
import { MANAGER_STATE, MEMBER_STATE } from '~helpers/auth-state';
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
  managePages,
  discoverItemUrl,
  discoverCommunityManageUrl,
  discoverProtocolUrl,
  openForAudit,
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
      const blocked = await openForAudit(page, path);
      test.skip(blocked !== null, blocked ?? '');
      await runAutomatedChecks(page, testInfo, slug);
    });
  }

  for (const { slug, listPath, itemLink, pathSuffix } of discoveredPages) {
    test(`automated checks: ${slug}`, async ({ page }, testInfo) => {
      const url = await discoverItemUrl(page, listPath, itemLink, pathSuffix);
      test.skip(url === null, `No item link matching "${itemLink}" found on ${listPath}. Seed default content first.`);
      const blocked = await openForAudit(page, url);
      test.skip(blocked !== null, blocked ?? '');
      await runAutomatedChecks(page, testInfo, slug);
    });
  }

  test('automated checks: protocol-local-contexts', async ({ page }, testInfo) => {
    const url = await discoverProtocolUrl(page, (slug) => `/protocol/${slug}/local-contexts`);
    test.skip(url === null, 'No community with a linked protocol found. Seed default content first.');
    const blocked = await openForAudit(page, url);
    test.skip(blocked !== null, blocked ?? '');
    await runAutomatedChecks(page, testInfo, 'protocol-local-contexts');
  });
});

test.describe('Automated checks: member pages', () => {
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
    test(`automated checks: ${slug}`, async ({ page }, testInfo) => {
      const blocked = await openForAudit(page, path);
      test.skip(blocked !== null, blocked ?? '');
      await runAutomatedChecks(page, testInfo, slug);
    });
  }

  for (const { slug, listPath, itemLink } of memberDiscoveredPages) {
    test(`automated checks: ${slug}`, async ({ page }, testInfo) => {
      const url = await discoverItemUrl(page, listPath, itemLink);
      test.skip(url === null, `No item link matching "${itemLink}" found on ${listPath} for this member.`);
      const blocked = await openForAudit(page, url);
      test.skip(blocked !== null, blocked ?? '');
      await runAutomatedChecks(page, testInfo, slug);
    });
  }
});

/**
 * Manage-adjacent pages -- see the matching describe block in
 * accessibility.spec.ts for the full rationale.
 */
test.describe('Automated checks: manage-adjacent pages', () => {
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
    test(`automated checks: ${slug}`, async ({ page }, testInfo) => {
      const blocked = await openForAudit(page, path);
      test.skip(blocked !== null, blocked ?? '');
      await runAutomatedChecks(page, testInfo, slug);
    });
  }

  test('automated checks: manage-community-local-contexts-projects', async ({ page }, testInfo) => {
    const url = await discoverCommunityManageUrl(page, (slug) => `/communities/community/${slug}/local-contexts/projects`);
    test.skip(url === null, 'No community found. Seed default content first.');
    const blocked = await openForAudit(page, url);
    test.skip(blocked !== null, blocked ?? '');
    await runAutomatedChecks(page, testInfo, 'manage-community-local-contexts-projects');
  });

  test('automated checks: manage-protocol-local-contexts-projects', async ({ page }, testInfo) => {
    const url = await discoverProtocolUrl(page, (slug) => `/protocols/protocol/${slug}/local-contexts/projects`);
    test.skip(url === null, 'No community with a linked protocol found. Seed default content first.');
    const blocked = await openForAudit(page, url);
    test.skip(blocked !== null, blocked ?? '');
    await runAutomatedChecks(page, testInfo, 'manage-protocol-local-contexts-projects');
  });
});
