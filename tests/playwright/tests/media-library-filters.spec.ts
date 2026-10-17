import { test, expect, Locator, Page } from '@playwright/test';
import { ADMIN_STATE } from '~helpers/auth-state';
import { gotoReady } from '~helpers/preview';

/**
 * The media library dialog opened from a node form (#2401).
 *
 * Alignment: Gin bottom-aligns an exposed form's actions with align-self:
 * flex-end, so the button follows the bottom of the flex line.
 * mukurtu_gin_custom's dialog-wide spacing rules put 1rem under every
 * .js-form-item and 0.5rem above every submit, which moved that line 16px
 * below the Name and Sort by fields and took the button with it. Measured
 * against the inputs rather than their wrappers, because the wrappers'
 * margins are exactly what went wrong.
 *
 * Focus (WCAG 2.4.3): after "Apply filters", core's refocus looked the button
 * up by a data-drupal-selector that changes on every render, landed outside
 * the dialog, and core's dialog.ajax.js then sent focus to "All media".
 *
 * Reflow (WCAG 1.4.10): at 320px the dialog subtracted Gin's 245px sticky
 * header from the window height twice, leaving the library 11px tall, and
 * Claro's side-by-side menu and content ran 50px past the dialog.
 */

/** Subpixel rounding allowance, in px. */
const TOLERANCE = 1;

/**
 * How long to let the dialog's AJAX settle, in ms. Assertions retry until it
 * has, rather than waitForAjax()'s fixed 5 seconds, which a cold or busy
 * site overruns.
 */
const SETTLE = 30_000;

async function openMediaLibrary(page: Page) {
  await gotoReady(page, '/node/add/digital_heritage');
  await page.getByRole('button', { name: 'Add media' }).first().click();

  const modal = page.getByRole('dialog', { name: 'Add or select media' });
  const filters = modal.locator('.views-exposed-form');
  const apply = filters.getByRole('button', { name: 'Apply filters' });
  await expect(apply).toBeVisible({ timeout: SETTLE });

  return {
    modal,
    name: filters.getByRole('textbox', { name: 'Name' }),
    sort: filters.getByRole('combobox', { name: 'Sort by' }),
    apply,
  };
}

test.describe('Media modal filters', () => {
  test.use({ storageState: ADMIN_STATE });

  test('"Apply filters" lines up with the Name and Sort by fields', async ({ page }) => {
    const { name, sort, apply } = await openMediaLibrary(page);

    const bottom = async (locator: Locator) => {
      const box = await locator.boundingBox();
      expect(box, 'the element has a box to measure').not.toBeNull();
      return box!.y + box!.height;
    };

    await expect(async () => {
      const applyBottom = await bottom(apply);
      for (const [label, field] of [['Name', name], ['Sort by', sort]] as const) {
        const fieldBottom = await bottom(field);
        expect(Math.abs(applyBottom - fieldBottom),
          `"Apply filters" ends at ${Math.round(applyBottom)}px but ${label} `
          + `ends at ${Math.round(fieldBottom)}px`)
          .toBeLessThanOrEqual(TOLERANCE);
      }
    }).toPass({ timeout: SETTLE });
  });

  test('focus stays on "Apply filters" after the results reload', async ({ page }) => {
    const { name, apply } = await openMediaLibrary(page);

    await name.fill('no media has this name');
    await apply.focus();
    // The button is focused before the reload too, so wait for its
    // replacement, which gets a new id, before checking focus.
    const before = await apply.getAttribute('id');
    await page.keyboard.press('Enter');
    await expect(apply).not.toHaveAttribute('id', before!, { timeout: SETTLE });

    await expect(apply).toBeFocused({ timeout: SETTLE });
  });

  test('the dialog reflows at 320px', async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 640 });
    const { modal } = await openMediaLibrary(page);

    await expect(async () => {
      const content = await modal.locator('.ui-dialog-content').evaluate((el) => ({
        scrollWidth: el.scrollWidth,
        clientWidth: el.clientWidth,
        clientHeight: el.clientHeight,
      }));

      expect(content.scrollWidth,
        `the library is ${content.scrollWidth}px wide in a ${content.clientWidth}px dialog`)
        .toBeLessThanOrEqual(content.clientWidth + TOLERANCE);
      // Measured 420px with the fix and 11px without it. A third of the
      // window leaves room for differences between environments.
      expect(content.clientHeight,
        `the library has ${content.clientHeight}px of a 640px window`)
        .toBeGreaterThanOrEqual(640 / 3);
    }).toPass({ timeout: SETTLE });
  });
});
