import { test, expect, Page } from "@playwright/test";
import { gotoReady } from "~helpers/preview";

/**
 * Check the browsing experience (Grid/List/Map) for Digital Heritage items.
 */
test('Browse tests - Digital Heritage', async ({ page, browserName }) => {
  await gotoReady(page, '/digital-heritage');
  // /digital-heritage renders the list, grid, and map view displays all at
  // once (see issue #2011), which can take longer than the default 5s
  // actionTimeout to become clickable on resource-constrained CI runners.
  await page.getByText('Grid', { exact: true }).click({ timeout: 15000 });
  await page.getByText('List', { exact: true }).click({ timeout: 15000 });
  // @todo Re-enable Map clicks when there's default content with location data.
  //await page.getByText('Map', { exact: true }).click();
  // @todo Check default content within each tab.
});

/**
 * Regression test for issue #2001: decorative card media links are marked
 * aria-hidden="true" to avoid a screen reader announcing the card's
 * destination twice (once for the image, once for the title text), so they
 * must also carry tabindex="-1" to stay out of the tab order. See
 * themes/mukurtu_v4/templates/content/_card-media.html.twig.
 */
test('Browse tests - decorative card links are not keyboard-focusable', async ({ page }) => {
  await gotoReady(page, '/browse');
  const untabbableAriaHiddenLinks = page.locator('a[aria-hidden="true"]:not([tabindex="-1"])');
  await expect(untabbableAriaHiddenLinks).toHaveCount(0);
});

/**
 * Regression test for issue #1569: the theme hides the native <details>
 * marker on sidebar facet headers, so each <summary> carries a plus/minus
 * glyph instead (themes/mukurtu_v4/components/04-templates/browse-pages/
 * _browse.scss). It must show "+" when collapsed and "−" when expanded.
 */
test('Browse tests - facet headers show a collapse indicator', async ({ page }) => {
  await gotoReady(page, '/browse');
  const details = page.locator('.browse-facets details.form-item').first();
  const summary = details.locator('> summary');
  await expect(summary).toBeVisible();

  const indicator = () => summary.evaluate((el) => getComputedStyle(el, '::after').content);
  const isOpen = () => details.evaluate((el) => (el as HTMLDetailsElement).open);

  // Facets are configured open_by_default.
  expect(await isOpen()).toBe(true);
  expect(await indicator()).toContain('−');

  await summary.click();
  expect(await isOpen()).toBe(false);
  expect(await indicator()).toContain('+');

  await summary.focus();
  await page.keyboard.press('Enter');
  expect(await isOpen()).toBe(true);
  expect(await indicator()).toContain('−');
});

/**
 * Regression tests for issue #2619: the filters, sort, Display options and
 * search box used to reload the page as soon as they changed (WCAG 3.2.2).
 * They now wait for Apply, and focus comes back to Apply, or to the search
 * box on Enter, after the reload, with the number of results announced
 * (modules/mukurtu_browse/js/facet-focus.js).
 */
const FILTERS = 'form[id^="views-exposed-form-"]';

/** Counts full page loads from here on. */
function countLoads(page: Page): () => number {
  let loads = 0;
  page.on('request', (request) => {
    if (request.isNavigationRequest() && request.frame() === page.mainFrame()) {
      loads += 1;
    }
  });
  return () => loads;
}

for (const path of ['/browse', '/digital-heritage', '/dictionary']) {
  test(`Browse tests - ${path} waits for Apply`, async ({ page }) => {
    await gotoReady(page, path);
    const form = page.locator(FILTERS).first();
    const loads = countLoads(page);

    const checkbox = form.locator('input[type="checkbox"][data-drupal-selector]:visible').first();
    if (await checkbox.count()) {
      await checkbox.check();
    }
    await form.locator('.search-collapse-toggle__input').first().press('Space');
    await form.locator('input[name="search_api_fulltext"], input[name="keywords"]').first().pressSequentially('zzz');
    const sort = form.locator('select[name="sort_by"]');
    if (await sort.count()) {
      await sort.selectOption({ index: 1 });
    }
    // Well past Better Exposed Filters' old 500ms auto-submit delay.
    await page.waitForTimeout(1500);
    expect(loads(), 'A control reloaded the page before Apply.').toBe(0);

    const apply = form.locator('input[type="submit"][value="Apply"]');
    await apply.focus();
    await Promise.all([page.waitForURL(/zzz/), page.keyboard.press('Enter')]);
    await page.waitForLoadState('load');
    expect(loads()).toBe(1);
    // The Display options setting goes with the other filters.
    expect(page.url()).toMatch(/(mpi|cr)_collapse=/);
    await expect.poll(() => page.evaluate(() => (document.activeElement as HTMLInputElement | null)?.value)).toBe('Apply');
    // Focus is on Apply, not the results, so the count is announced. No
    // result has "zzz" in it.
    await expect(page.locator('#drupal-live-announce')).toHaveText('0 results.');
  });
}

test('Browse tests - Enter in the search box applies and keeps focus there', async ({ page }) => {
  await gotoReady(page, '/browse');
  const search = page.locator(`${FILTERS} input[name="search_api_fulltext"]`).first();
  await search.pressSequentially('zzzz');
  await Promise.all([page.waitForURL(/search_api_fulltext=zzzz/), search.press('Enter')]);
  await page.waitForLoadState('load');
  await expect.poll(() => page.evaluate(() => (document.activeElement as HTMLInputElement | null)?.name)).toBe('search_api_fulltext');
});

// Choosing a search suggestion (#2473) fills in the box and waits for Enter
// or Apply, like everything else.
test('Browse tests - choosing a search suggestion does not reload the page', async ({ page }) => {
  await gotoReady(page, '/browse');
  const search = page.locator(`${FILTERS} input[name="search_api_fulltext"]`).first();
  const item = page.locator('.ui-autocomplete li:visible').first();
  let found = false;
  for (const letters of ['sam', 'wov', 'mul', 'the']) {
    await search.fill('');
    await search.pressSequentially(letters, { delay: 50 });
    found = await item.waitFor({ timeout: 3000 }).then(() => true, () => false);
    if (found) {
      break;
    }
  }
  test.skip(!found, 'No suggestions for the content on this site.');
  const loads = countLoads(page);
  await item.click();
  await page.waitForTimeout(1500);
  expect(loads(), 'Choosing a suggestion reloaded the page.').toBe(0);
  expect(await search.inputValue()).not.toBe('');
});

// Reset isn't shown once nothing is filtered, and on /dictionary it's a link,
// so focus comes back to Apply.
for (const [path, reset] of [
  ['/browse?search_api_fulltext=zzz', `${FILTERS} input[type="submit"][value="Reset"]`],
  ['/dictionary?keywords=zzz', '.browse-links .filter-reset'],
]) {
  test(`Browse tests - Reset on ${path.split('?')[0]} brings focus back to Apply`, async ({ page }) => {
    await gotoReady(page, path);
    await page.locator(reset).first().focus();
    await Promise.all([page.waitForURL((url) => !url.search.includes('zzz')), page.keyboard.press('Enter')]);
    await page.waitForLoadState('load');
    await expect.poll(() => page.evaluate(() => (document.activeElement as HTMLInputElement | null)?.value)).toBe('Apply');
  });
}

test('Browse tests - Apply stays in view while the filters scroll', async ({ page }) => {
  await page.setViewportSize({ width: 1400, height: 800 });
  await gotoReady(page, '/browse');
  const actions = page.locator(`${FILTERS} .form-actions`).first();
  const form = await page.locator(FILTERS).first().boundingBox();
  test.skip(!form || form.height < 900, 'The filters fit in the window, so nothing scrolls.');
  // Scroll so the form's top is gone but its end is still below the window.
  await page.evaluate((top) => window.scrollTo({ top, behavior: 'instant' }), form!.y + 100);
  const box = await actions.boundingBox();
  expect(box!.y + box!.height).toBeLessThanOrEqual(800);
  expect(box!.y).toBeGreaterThan(600);
});
