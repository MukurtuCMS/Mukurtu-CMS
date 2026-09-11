import { Page } from '@playwright/test';

/**
 * The accessibility program's audit page inventory, shared by every
 * automated check (axe scans, reflow/zoom, focus-visibility, link text,
 * keyboard traps). See docs/accessibility/page-inventory.md at the profile
 * root — keep both in sync when a page or component is added.
 */

export const anonymousPages = [
  { slug: 'home', path: '/' },
  { slug: 'browse', path: '/browse' },
  { slug: 'digital-heritage-browse', path: '/digital-heritage' },
  { slug: 'collections-browse', path: '/collections' },
  { slug: 'communities', path: '/communities' },
  { slug: 'dictionary-browse', path: '/dictionary' },
  { slug: 'login', path: '/user/login' },
];

/**
 * Item pages discovered from a listing page, so checks work against any
 * site with default content and follow protocol-appropriate access.
 */
export const discoveredPages = [
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
export const memberPages = [
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
export const memberDiscoveredPages = [
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
export async function discoverItemUrl(page: Page, listPath: string, itemLink: string): Promise<string | null> {
  await page.goto(listPath);
  const hrefs = await page.locator(itemLink).evaluateAll(
    (links) => links.map((link) => link.getAttribute('href')),
  );
  // Protocol-protected items can render as login links; those audit the
  // login page instead of the item, so skip them.
  return hrefs.find((href) => href && !href.includes('/user/login')) ?? null;
}
