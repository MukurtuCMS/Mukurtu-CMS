import { test, expect, FrameLocator, Locator, Page } from '@playwright/test';
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

  test('the filters and select-all have valid names', async ({ page }) => {
    // Inside the browser's form the exposed form renders as a <div>, which
    // needs a role for its aria-label to count.
    const filters = page.locator('.views-exposed-form');
    await expect(filters).toHaveAttribute('role', 'search');
    await expect(filters).toHaveAttribute('aria-label', 'Filter content');

    // Select-all keeps one name while core swaps its title.
    const selectAll = page.locator('.views-table thead input[type="checkbox"]');
    await expect(selectAll).toHaveAccessibleName('Select all rows in this table');
    await selectAll.check();
    await expect(selectAll).toHaveAccessibleName('Select all rows in this table');
  });

  test('no nested controls, missing names or alt text, or undersized targets', async ({ page }) => {
    const results = await new AxeBuilder({ page })
      .include('.views-table')
      .include('.views-exposed-form')
      .withRules(['nested-interactive', 'image-alt', 'target-size', 'label-title-only', 'aria-prohibited-attr'])
      .analyze();
    expect(results.violations.map((v) => `${v.id}: ${v.nodes.length}`)).toEqual([]);
  });
});

/**
 * "Already added" marking, driven through a real form.
 *
 * entity_browser_already_added.js reads the items of the field that opened
 * the browser, so it can only be exercised from a parent form. The
 * Collection form has several content browser fields, which also shows the
 * marking is scoped to the field that opened the browser.
 */
test.describe('Entity browsers: already added items', () => {
  test.beforeEach(async ({ page }) => {
    const account = adminAccount();
    const login = new Login(page);
    await login.login(account.username, account.password);
  });

  /** Opens the browser for a field and returns its frame. */
  async function openBrowser(page: Page, field: string): Promise<FrameLocator> {
    // Some fields sit in collapsed field-group tabs, so click from script.
    await page.locator(`[data-uuid][id^="edit-${field}-entity-browser"]`).first().evaluate((el: HTMLElement) => el.click());
    const frame = page.frameLocator('.ui-dialog iframe').last();
    await expect(frame.locator('.views-table tbody tr').first()).toBeVisible({ timeout: 30000 });
    return frame;
  }

  async function closeBrowser(page: Page): Promise<void> {
    await page.locator('.ui-dialog-titlebar-close:visible').click();
    await expect(page.locator('.ui-dialog iframe')).toHaveCount(0);
  }

  test('an item in the field is marked only in that field\'s browser', async ({ page }) => {
    await page.goto('/node/add/collection');

    let frame = await openBrowser(page, 'field-items-in-collection');
    const value = await frame.locator('.views-table tbody td.views-field-entity-browser-select input').first().getAttribute('value');
    await frame.locator('.views-table tbody tr').first().locator('td.views-field-type').click();
    await frame.locator('.is-entity-browser-submit').click();
    await expect(page.locator(`[data-entity-id="${value}"]`).first()).toBeAttached({ timeout: 30000 });

    // Same field: the row is disabled and badged, once.
    frame = await openBrowser(page, 'field-items-in-collection');
    const row = frame.locator('.views-table tbody tr').filter({ has: frame.locator(`input[value="${value}"]`) });
    await expect(row).toHaveClass(/\beb-already-selected\b/);
    await expect(row.locator('td.views-field-entity-browser-select input')).toBeDisabled();
    await expect(row.locator('.eb-already-added')).toHaveText('Already added');
    await expect(row.locator('.eb-already-added')).toHaveCount(1);
    // Only the checkbox is disabled; the row's links still work.
    await expect(row.locator('td.views-field-title a')).toHaveCSS('pointer-events', 'auto');

    // Select all must not check it: core's select-all collected its
    // checkboxes before this one was disabled.
    await frame.locator('.views-table thead input[type="checkbox"]').check();
    await expect(row.locator('td.views-field-entity-browser-select input')).not.toBeChecked();
    await closeBrowser(page);

    // Another field on the same form: not marked.
    frame = await openBrowser(page, 'field-related-content');
    const other = frame.locator('.views-table tbody tr').filter({ has: frame.locator(`input[value="${value}"]`) });
    await expect(other.locator('td.views-field-entity-browser-select input')).toBeEnabled();
    await expect(frame.locator('.eb-already-added')).toHaveCount(0);
  });
});

/**
 * The community select browser, through a stand-in parent page.
 *
 * Its only field (a protocol's Communities) needs an existing protocol,
 * which the suite has no reliable way to find. The browser only reads the
 * parent's opener button (data-uuid) and that widget's items
 * (data-entity-id), so a same-origin page with those two things exercises
 * the real browser code.
 */
test.describe('Community select browser', () => {
  test.beforeEach(async ({ page }) => {
    const account = adminAccount();
    const login = new Login(page);
    await login.login(account.username, account.password);
  });

  /** Loads the browser in an iframe under a fake widget holding `ids`. */
  async function standIn(page: Page, ids: string[]): Promise<FrameLocator> {
    await page.goto('/admin');
    await page.evaluate((items) => {
      const widget = (uuid: string, entities: string[]) =>
        `<div><div class="entities-list">${entities.map((id) => `<div data-entity-id="${id}"></div>`).join('')}</div>`
        + `<input type="button" data-uuid="${uuid}"></div>`;
      // A second widget whose items must be ignored.
      document.body.innerHTML = widget('opener', items) + widget('other', ['community:999999'])
        + '<iframe title="Community browser" style="width:1000px;height:600px" '
        + 'src="/entity-browser/modal/mukurtu_community_select?uuid=opener"></iframe>';
    }, ids);
    const frame = page.frameLocator('iframe');
    await expect(frame.locator('.views-row').first()).toBeVisible({ timeout: 30000 });
    return frame;
  }

  test('cards use their checkbox, and an already-added community is badged', async ({ page }) => {
    let frame = await standIn(page, []);
    const card = frame.locator('.views-row').first();
    const checkbox = card.locator('.views-field-entity-browser-select input');

    // The native checkbox is the control; the card is not a second one.
    await expect(checkbox).toBeVisible();
    await expect(checkbox).not.toHaveAttribute('tabindex', '-1');
    await expect(card).not.toHaveAttribute('role', /.*/);
    await expect(card).not.toHaveAttribute('tabindex', /.*/);
    await card.locator('.views-field-name').click();
    await expect(checkbox).toBeChecked();
    await expect(card).toHaveClass(/\bis-selected\b/);

    const value = await checkbox.getAttribute('value');
    frame = await standIn(page, [value!]);
    const added = frame.locator('.views-row').filter({ has: frame.locator(`input[value="${value}"]`) });
    await expect(added.locator('.eb-already-added')).toHaveText('Already added');
    await expect(added.locator('input')).toBeDisabled();
    await added.locator('.views-field-name').click({ force: true });
    await expect(added.locator('input')).not.toBeChecked();
    // Only the opener's widget counts.
    await expect(frame.locator('.eb-already-added')).toHaveCount(1);
  });
});
