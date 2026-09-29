import { test, expect, Locator } from '@playwright/test';
import { Login } from '~components/login';
import { adminAccount } from '~helpers/a11y-credentials';

/**
 * Row selection in the content entity browser (the "Select Content" modal
 * used by Featured Content, collections, and other entity reference fields).
 *
 * entity_browser_view_decoration.js makes each row the checkbox and hides
 * the native input, so the only visible selection state is what
 * entity-browser-view-decoration.css draws in the input's column. That CSS
 * was once missing entirely: rows toggled correctly (aria-checked flipped,
 * the hidden input was checked) but nothing on screen changed, so selecting
 * content looked broken. These tests check the drawn state, not just the
 * attributes.
 *
 * The browser's iframe URL is loaded directly rather than through a field
 * widget's modal: the view, its libraries, and its markup are identical, and
 * this avoids depending on any particular form.
 */
const BROWSER_PATH = '/entity-browser/modal/mukurtu_content_browser';

/** Computed ::before/::after of a row's checkbox column. */
async function indicator(row: Locator): Promise<{ boxBg: string; check: string }> {
  return row.locator('td.views-field-entity-browser-select').evaluate((cell) => ({
    boxBg: getComputedStyle(cell, '::before').backgroundColor,
    check: getComputedStyle(cell, '::after').content,
  }));
}

test.describe('Content entity browser: row selection', () => {
  test.beforeEach(async ({ page }) => {
    const account = adminAccount();
    const login = new Login(page);
    await login.login(account.username, account.password);
    await page.goto(BROWSER_PATH);
    await expect(page.locator('.views-table tbody tr[role="checkbox"]').first()).toBeVisible({ timeout: 30000 });
  });

  test('clicking a row shows it as selected', async ({ page }) => {
    const row = page.locator('.views-table tbody tr[role="checkbox"]:not(.eb-already-selected)').first();

    // Negative control: an unselected row draws an empty box, no checkmark.
    await expect(row).toHaveAttribute('aria-checked', 'false');
    const before = await indicator(row);
    expect(before.check).toBe('none');

    // Click the row itself, away from the title and author links.
    await row.locator('td.views-field-type').click();

    await expect(row).toHaveAttribute('aria-checked', 'true');
    await expect(row.locator('td.views-field-entity-browser-select input')).toBeChecked();
    const after = await indicator(row);
    expect(after.check).not.toBe('none');
    expect(after.boxBg).not.toBe(before.boxBg);

    // A second click clears it again.
    await row.locator('td.views-field-type').click();
    await expect(row).toHaveAttribute('aria-checked', 'false');
    expect((await indicator(row)).check).toBe('none');
  });

  test('Space and Enter toggle the focused row', async ({ page }) => {
    const row = page.locator('.views-table tbody tr[role="checkbox"]:not(.eb-already-selected)').first();
    await row.focus();

    await page.keyboard.press('Space');
    await expect(row).toHaveAttribute('aria-checked', 'true');
    expect((await indicator(row)).check).not.toBe('none');

    await page.keyboard.press('Enter');
    await expect(row).toHaveAttribute('aria-checked', 'false');
  });

  test('the header row is not a checkbox', async ({ page }) => {
    // The header holds tableselect's hidden select-all input. Treating it
    // as a row made the header a focusable checkbox that selected nothing.
    const header = page.locator('.views-table thead tr');
    await expect(header).not.toHaveAttribute('role', 'checkbox');
    await expect(header).not.toHaveAttribute('tabindex', /.*/);

    // The select-all input itself is visually hidden, so it must not take
    // focus either (WCAG 2.4.7).
    const selectAll = header.locator('input[type="checkbox"]');
    if (await selectAll.count() > 0) {
      await expect(selectAll).toHaveAttribute('tabindex', '-1');
    }
  });
});
