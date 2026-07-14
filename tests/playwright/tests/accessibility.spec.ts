import { test, Page } from '@playwright/test';
import { Login } from '~components/login';
import { auditPage } from '~helpers/axe';

/**
 * Automated accessibility scans (axe-core, WCAG 2.1 A/AA).
 *
 * Visits every page in the audit inventory (docs/accessibility/
 * page-inventory.md at the profile root) and records axe results. Scans are
 * report-only: they never fail, results land in test-results/a11y/ and are
 * attached to the Playwright report. See docs/accessibility/README.md for
 * the program this feeds.
 */

/**
 * Pages reachable anonymously at fixed paths.
 */
const anonymousPages = [
  { slug: 'home', path: '/' },
  { slug: 'browse', path: '/browse' },
  { slug: 'digital-heritage-browse', path: '/digital-heritage' },
  { slug: 'collections-browse', path: '/collections' },
  { slug: 'communities', path: '/communities' },
  { slug: 'dictionary-browse', path: '/dictionary' },
  { slug: 'login', path: '/user/login' },
];

/**
 * Item pages discovered from a listing page, so the scan works against any
 * site with default content and follows protocol-appropriate access.
 */
const discoveredPages = [
  {
    slug: 'digital-heritage-item',
    listPath: '/digital-heritage',
    itemLink: 'main a[href*="/digital-heritage/"]',
  },
  {
    slug: 'collection-page',
    listPath: '/collections',
    itemLink: 'main a[href*="/collection"]',
  },
  {
    slug: 'community-page',
    listPath: '/communities',
    itemLink: '.communities__item a',
  },
  {
    slug: 'dictionary-word',
    listPath: '/dictionary',
    itemLink: 'main a[href*="/dictionary-word"]',
  },
];

/**
 * Pages audited as a logged-in member. Override the account with
 * A11Y_USERNAME/A11Y_PASSWORD; results are most representative with a
 * regular community member account rather than an administrator.
 */
const memberPages = [
  { slug: 'member-home', path: '/' },
  { slug: 'member-my-content', path: '/my-content' },
  { slug: 'member-personal-collections', path: '/user/personal-collections' },
  { slug: 'member-account', path: '/user' },
];

/**
 * Item pages discovered while logged in. On protocol-heavy sites most
 * content is only reachable as a member, so these cover the gated item
 * views (protocol fields, content warnings) the anonymous pass can't see.
 */
const memberDiscoveredPages = [
  {
    slug: 'member-digital-heritage-item',
    listPath: '/digital-heritage',
    itemLink: 'main a[href*="/digital-heritage/"]',
  },
  {
    slug: 'member-collection-page',
    listPath: '/collections',
    itemLink: 'main a[href*="/collection"]',
  },
  {
    slug: 'member-community-page',
    listPath: '/communities',
    itemLink: '.communities__item a',
  },
  {
    slug: 'member-dictionary-word',
    listPath: '/dictionary',
    itemLink: 'main a[href*="/dictionary-word"]',
  },
];

/**
 * Find the first item link on a listing page and return its URL.
 */
async function discoverItemUrl(page: Page, listPath: string, itemLink: string): Promise<string | null> {
  await page.goto(listPath);
  const hrefs = await page.locator(itemLink).evaluateAll(
    (links) => links.map((link) => link.getAttribute('href')),
  );
  // Protocol-protected items can render as login links; those audit the
  // login page instead of the item, so skip them.
  return hrefs.find((href) => href && !href.includes('/user/login')) ?? null;
}

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
