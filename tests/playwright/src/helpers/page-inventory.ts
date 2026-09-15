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
  { slug: 'local-contexts', path: '/local-contexts' },
  { slug: 'access-denied', path: '/mukurtu/access-denied' },
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
  {
    slug: 'community-local-contexts',
    listPath: '/communities',
    itemLink: '.communities__item a',
    pathSuffix: '/local-contexts',
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
  { slug: 'member-dashboard', path: '/dashboard/mukurtu_dashboard' },
  { slug: 'member-notifications', path: '/notifications' },
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
 * Pages reachable by non-admin community/protocol roles (Community
 * Managers, protocol contributors/curators/stewards) that are neither a
 * plain member page nor part of the admin/authoring (ATAG) surface --
 * a Phase 1 coverage gap, not Phase 2 scope. Override the account with
 * A11Y_MANAGER_USERNAME/A11Y_MANAGER_PASSWORD; the admin/admin fallback
 * can reach these routes but adds Drupal-toolbar noise and isn't
 * representative of the actual non-admin roles that use them.
 */
export const managePages = [
  { slug: 'manage-content', path: '/admin/content' },
  { slug: 'manage-people-list', path: '/admin/people/list' },
  { slug: 'manage-create-user', path: '/admin/communities/create-user' },
];

/**
 * Find the first item link on a listing page and return its URL. An
 * optional pathSuffix is appended to the discovered URL -- used to reach a
 * sub-page of the discovered item (e.g. a community's Local Contexts
 * directory) rather than the item itself.
 */
export async function discoverItemUrl(
  page: Page,
  listPath: string,
  itemLink: string,
  pathSuffix?: string,
): Promise<string | null> {
  await page.goto(listPath);
  const hrefs = await page.locator(itemLink).evaluateAll(
    (links) => links.map((link) => link.getAttribute('href')),
  );
  // Protocol-protected items can render as login links; those audit the
  // login page instead of the item, so skip them.
  const href = hrefs.find((href) => href && !href.includes('/user/login')) ?? null;
  if (!href) return null;
  return pathSuffix ? `${href}${pathSuffix}` : href;
}

/**
 * Discover a community's machine name/slug (from its public page URL,
 * e.g. /community/some-slug) and build a manage-scoped URL from it --
 * used for pages like /communities/community/{community}/... that share
 * no path prefix with the public community page, so a simple pathSuffix
 * isn't enough.
 */
export async function discoverCommunityManageUrl(page: Page, buildPath: (slug: string) => string): Promise<string | null> {
  const communityUrl = await discoverItemUrl(page, '/communities', '.communities__item a');
  const slug = communityUrl?.split('/').filter(Boolean).pop();
  return slug ? buildPath(slug) : null;
}

/**
 * Discover a protocol's slug and build a URL from it, by navigating to a
 * community first (there's no public protocol listing page), then finding
 * a protocol link on that community's page. Returns null if the
 * discovered community has no protocols -- callers should skip the test
 * in that case, same as any other discovery miss.
 */
export async function discoverProtocolUrl(page: Page, buildPath: (slug: string) => string): Promise<string | null> {
  const communityUrl = await discoverItemUrl(page, '/communities', '.communities__item a');
  if (!communityUrl) return null;
  await page.goto(communityUrl);
  const hrefs = await page.locator('a[href*="/protocols/protocol/"]').evaluateAll(
    (links) => links.map((link) => link.getAttribute('href')),
  );
  const protocolUrl = hrefs.find((href) => href && !href.includes('/add') && !href.includes('/user/login')) ?? null;
  const slug = protocolUrl?.split('/').filter(Boolean).pop();
  return slug ? buildPath(slug) : null;
}
