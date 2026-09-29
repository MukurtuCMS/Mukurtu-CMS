import { test, expect, Locator, Page } from '@playwright/test';
import { AxeBuilder } from '@axe-core/playwright';
import { Login } from '~components/login';
import { adminAccount } from '~helpers/a11y-credentials';

/**
 * Row selection in the content entity browser (the "Select Content" modal
 * used by Featured Content, collections, and other entity reference fields).
 *
 * The native checkbox is the control. entity_browser_view_decoration.js lets
 * a click anywhere else in the row toggle it too, and tints checked rows.
 * Earlier versions made the row itself a role="checkbox", which nested the
 * title and author links inside another control, and at one point hid the
 * checkbox without drawing any other selected state, so selecting content
 * looked broken.
 *
 * The browser's iframe URL is loaded directly rather than through a field
 * widget's modal: the view, its libraries, and its markup are identical, and
 * this avoids depending on any particular form.
 */
const BROWSER_PATH = '/entity-browser/modal/mukurtu_content_browser';

function selectableRows(page: Page): Locator {
  return page.locator('.views-table tbody tr:not(.eb-already-selected)');
}

function checkboxOf(row: Locator): Locator {
  return row.locator('td.views-field-entity-browser-select input[type="checkbox"]');
}

test.describe('Content entity browser: row selection', () => {
  test.beforeEach(async ({ page }) => {
    const account = adminAccount();
    const login = new Login(page);
    await login.login(account.username, account.password);
    await page.goto(BROWSER_PATH);
    await expect(page.locator('.views-table tbody tr').first()).toBeVisible({ timeout: 30000 });
  });

  test('the checkbox is the visible, focusable control', async ({ page }) => {
    const row = selectableRows(page).first();
    const checkbox = checkboxOf(row);

    await expect(checkbox).toBeVisible();
    await expect(checkbox).not.toHaveAttribute('tabindex', '-1');
    await expect(checkbox).not.toHaveAttribute('aria-hidden', 'true');
    // Named by its own label, "Select item <title>".
    await expect(checkbox).toHaveAccessibleName(/\S/);

    // The row keeps table semantics and is not a second focus stop.
    await expect(row).not.toHaveAttribute('role', /.*/);
    await expect(row).not.toHaveAttribute('tabindex', /.*/);

    await checkbox.focus();
    await page.keyboard.press('Space');
    await expect(checkbox).toBeChecked();
    await expect(row).toHaveClass(/\bchecked\b/);
  });

  test('clicking elsewhere in the row toggles its checkbox', async ({ page }) => {
    const row = selectableRows(page).first();
    const checkbox = checkboxOf(row);

    // Negative control: unchecked and untinted to begin with.
    await expect(checkbox).not.toBeChecked();
    await expect(row).not.toHaveClass(/\bchecked\b/);

    // Click the row itself, away from the title and author links.
    await row.locator('td.views-field-type').click();
    await expect(checkbox).toBeChecked();
    await expect(row).toHaveClass(/\bchecked\b/);

    await row.locator('td.views-field-type').click();
    await expect(checkbox).not.toBeChecked();
    await expect(row).not.toHaveClass(/\bchecked\b/);
  });

  test('select all checks and tints every row', async ({ page }) => {
    await page.locator('.views-table thead input[type="checkbox"]').check();
    const rows = selectableRows(page);
    const count = await rows.count();
    expect(count).toBeGreaterThan(0);
    for (let i = 0; i < count; i++) {
      await expect(checkboxOf(rows.nth(i))).toBeChecked();
      await expect(rows.nth(i)).toHaveClass(/\bchecked\b/);
    }
  });

  test('a row already in the field shows an "Already added" badge and cannot be checked', async ({ page }) => {
    const value = await checkboxOf(selectableRows(page).first()).getAttribute('value');
    // Pin the row by its value: selectableRows() stops matching it once it
    // is marked as already added.
    const row = page.locator('.views-table tbody tr').filter({ has: page.locator(`input[value="${value}"]`) });
    const checkbox = checkboxOf(row);

    // The browser marks rows whose entity the parent form's widget already
    // lists ([data-entity-id]). Loaded directly there is no parent form, so
    // stand one in and re-run the behaviors.
    await page.evaluate((id) => {
      const marker = document.createElement('div');
      marker.setAttribute('data-entity-id', id!);
      document.body.appendChild(marker);
      (window as any).Drupal.attachBehaviors(document, (window as any).drupalSettings);
    }, value);

    await expect(row).toHaveClass(/\beb-already-selected\b/);
    await expect(checkbox).toBeDisabled();
    await expect(row.locator('.eb-already-added')).toBeVisible();
    await expect(row.locator('.eb-already-added')).toHaveText('Already added');
    // Re-running the behaviors must not stack a second badge.
    await page.evaluate(() => (window as any).Drupal.attachBehaviors(document, (window as any).drupalSettings));
    await expect(row.locator('.eb-already-added')).toHaveCount(1);
  });

  test('no nested controls, missing alt text, or undersized targets', async ({ page }) => {
    const results = await new AxeBuilder({ page })
      .include('.views-table')
      .withRules(['nested-interactive', 'image-alt', 'target-size'])
      .analyze();
    expect(results.violations.map((v) => `${v.id}: ${v.nodes.length}`)).toEqual([]);
  });
});
