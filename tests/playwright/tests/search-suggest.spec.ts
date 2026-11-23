import { test, expect, Page } from '@playwright/test';
import { gotoReady } from '~helpers/preview';

/**
 * Search suggestions against the real site (#2473).
 *
 * search-suggest.offline.spec.ts and header-suggest.offline.spec.ts cover the
 * widget's behavior. This checks that the server wires it up and only
 * suggests words and items from content the visitor can see. It relies on
 * default-content.spec.ts: "strict" appears only in the members-only items'
 * summaries, "public" in the public ones, and both have a Digital Heritage
 * item with "digital" in its title.
 */

async function typeAndWait(page: Page, typed: string) {
  // The page's own search box, not the header's.
  const input = page.locator('form[id^="views-exposed-form-"]').getByRole('combobox', { name: 'Search' });
  const response = page.waitForResponse((r) =>
    r.url().includes('/search_api_autocomplete/') && new URL(r.url()).searchParams.get('q') === typed);
  await input.fill('');
  await input.pressSequentially(typed);
  await response;
  return input;
}

test('Browse suggests words from public content', async ({ page }) => {
  await gotoReady(page, '/browse');
  const input = await typeAndWait(page, 'pub');
  const list = page.getByRole('listbox', { name: 'Search suggestions' });

  await expect(list.getByRole('option', { name: 'public', exact: true })).toBeVisible();
  await expect(input).toHaveAttribute('aria-expanded', 'true');
});

test('Browse never suggests words from members-only content to visitors', async ({ page }) => {
  await gotoReady(page, '/browse');
  await typeAndWait(page, 'stri');

  await expect(page.getByRole('option', { name: 'strict', exact: true })).toHaveCount(0);
});

test('the header suggests public items, grouped by content type, never members-only ones', async ({ page }) => {
  await gotoReady(page, '/');
  const input = page.getByRole('search').getByRole('combobox', { name: 'Search' }).first();
  const response = page.waitForResponse((r) =>
    r.url().includes('/browse/suggest') && new URL(r.url()).searchParams.get('q') === 'digital');
  await input.pressSequentially('digital');
  await response;

  const list = page.getByRole('listbox', { name: 'Search suggestions' });
  const group = list.getByRole('group', { name: 'Digital Heritage' });
  await expect(group.getByRole('option', { name: 'Public digital heritage item' })).toBeVisible();
  await expect(list.getByRole('option', { name: 'Members-only digital heritage item' })).toHaveCount(0);
  await expect(list.getByRole('option', { name: 'See all results for “digital”' })).toBeVisible();
});
