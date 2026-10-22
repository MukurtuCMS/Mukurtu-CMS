import { test, expect } from '@playwright/test';
import { ADMIN_STATE } from '~helpers/auth-state';
import { gotoReady } from '~helpers/preview';

/**
 * The term merge form's Tagify picker must be browsable by keyboard.
 *
 * term_merge 2.0.1 replaced its "Terms to merge" checkbox list with a
 * comma-separated autocomplete, so terms had to be known ahead of time
 * (#2438). mukurtu_taxonomy swaps in a Tagify picker that lists every term
 * on click. These checks pin the keyboard and screen reader behavior that
 * makes that list usable without a mouse:
 *
 * - Down Arrow opens the list and then moves through it. It used to re-fire
 *   the "open" click on every press, which refetched the list and reset the
 *   highlight, so only the first term could ever be picked (WCAG 2.1.1).
 * - Adding a term announces its name, not its ID (WCAG 4.1.3).
 * - Submitting with nothing selected returns Drupal's own error and marks
 *   the visible textbox invalid, rather than the browser's pop-up pointing
 *   at Tagify's hidden input (WCAG 3.3.1).
 * - Enter or Tab before the suggestions load leaves the typed text in the
 *   textbox. It used to become a red invalid tag that was dropped on submit.
 *   The same goes for Enter after Escape has closed the list. A blocked
 *   Enter is announced, so screen reader users know why nothing happened.
 *
 * Nothing is merged: the test stops at the first step of the form.
 */

const MERGE_URL = '/admin/structure/taxonomy/manage/category/merge';

/** Tagify fetches suggestions from the server; previews can be slow. */
const SLOW = { timeout: 20000 };

test.describe('Term merge Tagify picker', () => {
  test.use({ storageState: ADMIN_STATE });

  test('Down Arrow browses the list and Enter adds the highlighted term', async ({ page }) => {
    await gotoReady(page, MERGE_URL);
    await page.waitForLoadState('networkidle');

    const textbox = page.locator('tags.tagify .tagify__input');
    const options = page.locator('.tagify__dropdown [role=option]');
    const active = page.locator('.tagify__dropdown .tagify__dropdown__item--active');

    await textbox.focus();
    await page.keyboard.press('ArrowDown');
    await expect(options.nth(1), 'the list opens with at least two terms').toBeVisible(SLOW);
    await expect(active).toHaveCount(1);
    const first = (await active.textContent())!.trim();

    await page.keyboard.press('ArrowDown');
    await expect(active, 'the highlight moves past the first term').not.toHaveText(first, SLOW);
    await expect(options.nth(1)).toBeVisible();
    const second = (await active.textContent())!.trim();

    await page.keyboard.press('Enter');
    await expect(page.locator('tags.tagify tag')).toHaveAttribute('title', second, SLOW);
    await expect(
      page.locator('input.tagify-widget ~ [role=status]'),
      'the added term is announced by name',
    ).toHaveText(`Added ${second}`);
  });

  test('an empty submit reports a server-side error on the visible textbox', async ({ page }) => {
    await gotoReady(page, MERGE_URL);

    await expect(page.locator('form#taxonomy-merge-terms')).toHaveAttribute('novalidate', /.*/);
    // Gin copies the button into its sticky header and hides the original.
    await Promise.all([
      page.waitForResponse((response) => response.request().method() === 'POST'
        && response.url().endsWith(MERGE_URL), SLOW),
      page.locator('input[type=submit][value=Next]:visible').first().click({ noWaitAfter: true }),
    ]);
    await page.waitForLoadState('domcontentloaded');

    await expect(page).toHaveURL(new RegExp(`${MERGE_URL}$`));
    await expect(page.locator('tags.tagify .tagify__input')).toHaveAttribute('aria-invalid', 'true');
  });

  test('text typed before the suggestions load does not become an invalid tag', async ({ page }) => {
    // Hold every suggestions request so Enter and Tab land before any
    // returns, which is when Tagify committed the raw text as a tag.
    await page.route(/\/tagify_autocomplete\//, async (route) => {
      await new Promise((resolve) => setTimeout(resolve, 3000));
      await route.continue().catch(() => {});
    });
    await gotoReady(page, MERGE_URL);

    // Tagify removes an invalid tag about a second after adding it, so record
    // whether one ever appears rather than checking for one afterwards.
    await page.evaluate(() => {
      const tags = document.querySelector('tags.tagify')!;
      new MutationObserver(() => {
        if (tags.querySelector('tag')) {
          tags.dataset.tagSeen = 'true';
        }
      }).observe(tags, { childList: true });
    });

    const textbox = page.locator('tags.tagify .tagify__input');
    await textbox.click();
    await page.keyboard.type('Unmatched term');
    await page.keyboard.press('Enter');
    await page.keyboard.press('Tab');

    await expect(textbox).toHaveText('Unmatched term');
    await expect(page.locator('tags.tagify')).not.toHaveAttribute('data-tag-seen', 'true');
    await expect(page.locator('input.tagify-widget ~ [role=status]')).toHaveText('Loading...');
  });

  test('Enter with no matching suggestion is announced', async ({ page }) => {
    await page.route(/\/tagify_autocomplete\//, (route) => route.fulfill({ json: [] }));
    await gotoReady(page, MERGE_URL);

    const textbox = page.locator('tags.tagify .tagify__input');
    await textbox.click();
    await page.keyboard.type('Unmatched term');
    await expect(page.locator('.tagify--dropdown-item-no-match')).toBeVisible(SLOW);
    await page.keyboard.press('Enter');

    await expect(textbox).toHaveText('Unmatched term');
    await expect(page.locator('input.tagify-widget ~ [role=status]'))
      .toHaveText('No matching suggestions found for: Unmatched term');
  });

  test('Enter after Escape does not reuse the closed list\'s highlight', async ({ page }) => {
    // Answer with one made-up suggestion, so this needs no real terms.
    await page.route(/\/tagify_autocomplete\//, (route) => route.fulfill({
      json: [{ entity_id: '999999', label: 'Example term', attributes: {} }],
    }));
    await gotoReady(page, MERGE_URL);
    await page.evaluate(() => {
      const tags = document.querySelector('tags.tagify')!;
      new MutationObserver(() => {
        if (tags.querySelector('tag')) {
          tags.dataset.tagSeen = 'true';
        }
      }).observe(tags, { childList: true });
    });

    const textbox = page.locator('tags.tagify .tagify__input');
    await textbox.click();
    await page.keyboard.type('Exam');
    await expect(page.locator('.tagify__dropdown .tagify__dropdown__item--active')).toHaveText('Example term', SLOW);
    // A closed list keeps its highlighted item. Enter must not act on it.
    await page.keyboard.press('Escape');
    // Tagify finishes closing the list a moment after Escape.
    await page.waitForTimeout(300);
    await page.keyboard.type('x');
    await page.keyboard.press('Enter');

    await expect(textbox).toHaveText('Examx');
    await expect(page.locator('tags.tagify')).not.toHaveAttribute('data-tag-seen', 'true');
  });
});
