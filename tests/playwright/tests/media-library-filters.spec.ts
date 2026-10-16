import { test, expect, Locator } from '@playwright/test';
import { ADMIN_STATE } from '~helpers/auth-state';
import waitForAjax from '~helpers/ajax';
import { gotoReady } from '~helpers/preview';

/**
 * The media modal's "Apply filters" button must sit level with its fields
 * (#2401).
 *
 * Gin bottom-aligns an exposed form's actions with align-self: flex-end, so
 * the button follows the bottom of the flex line. mukurtu_gin_custom's
 * dialog-wide spacing rules put 1rem under every .js-form-item and 0.5rem
 * above every submit, which moved that line 16px below the Name and Sort by
 * fields and took the button with it. Fixed by cancelling both margins for
 * the media modal's exposed form only.
 *
 * Measured against the inputs rather than their wrappers, because the
 * wrappers' margins are exactly what went wrong.
 */

/** Subpixel rounding allowance, in px. */
const TOLERANCE = 1;

test.describe('Media modal filters', () => {
  test.use({ storageState: ADMIN_STATE });

  test('"Apply filters" lines up with the Name and Sort by fields', async ({ page }) => {
    await gotoReady(page, '/node/add/digital_heritage');
    await page.getByRole('button', { name: 'Add media' }).first().click();

    const modal = page.getByRole('dialog', { name: 'Add or select media' });
    const filters = modal.locator('.views-exposed-form');
    const name = filters.getByRole('textbox', { name: 'Name' });
    const sort = filters.getByRole('combobox', { name: 'Sort by' });
    const apply = filters.getByRole('button', { name: 'Apply filters' });
    // The modal is built by an AJAX request that can take well over
    // waitForAjax()'s 5 seconds on a cold cache, so wait for the dialog
    // itself first, then for the request to settle before measuring.
    await expect(apply).toBeVisible({ timeout: 30_000 });
    await waitForAjax(page);

    const bottom = async (locator: Locator) => {
      const box = await locator.boundingBox();
      expect(box, 'the element has a box to measure').not.toBeNull();
      return box!.y + box!.height;
    };

    const applyBottom = await bottom(apply);
    for (const [label, field] of [['Name', name], ['Sort by', sort]] as const) {
      const fieldBottom = await bottom(field);
      expect(Math.abs(applyBottom - fieldBottom),
        `"Apply filters" ends at ${Math.round(applyBottom)}px but ${label} `
        + `ends at ${Math.round(fieldBottom)}px`)
        .toBeLessThanOrEqual(TOLERANCE);
    }
  });
});
