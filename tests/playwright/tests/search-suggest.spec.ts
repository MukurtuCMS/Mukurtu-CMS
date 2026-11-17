import { test, expect, Page } from '@playwright/test';
import { gotoReady } from '~helpers/preview';

/**
 * Search word suggestions against the real site (#2473).
 *
 * search-suggest.offline.spec.ts covers the widget's behavior. This checks
 * that the server wires it up and only suggests words from content the
 * visitor can see. It relies on default-content.spec.ts: "strict" appears
 * only in the members-only items' summaries, "public" in the public ones.
 */

async function typeAndWait(page: Page, typed: string) {
  const input = page.getByRole('combobox', { name: 'Search' }).first();
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
