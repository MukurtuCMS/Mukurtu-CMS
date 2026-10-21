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
 * margins are exactly what went wrong. The button also wrapped under the
 * fields wherever the Name input's size="30" left the row too short: in CI's
 * Linux Chromium at 1280px, and in Layout Builder's version of the dialog on
 * every platform.
 *
 * Focus (WCAG 2.4.3): after "Apply filters", core's refocus looked the button
 * up by a data-drupal-selector that changes on every render, landed outside
 * the dialog, and core's dialog.ajax.js then sent focus to "All media".
 *
 * Reflow (WCAG 1.4.10): at 320px the dialog subtracted Gin's 245px sticky
 * header from the window height twice, leaving the library 11px tall, and
 * Claro's side-by-side menu and content ran 50px past the dialog. With media
 * in the library, the media table also widened the whole dialog body at
 * every width up to 768px. The suite's default content adds media, so that
 * case is covered once default-content.spec.ts has run.
 */

/** Subpixel rounding allowance, in px. */
const TOLERANCE = 1;

/**
 * How long to let the dialog's AJAX settle, in ms. Assertions retry until it
 * has, rather than waitForAjax()'s fixed 5 seconds, which a cold or busy
 * site overruns.
 */
const SETTLE = 30_000;

/**
 * Opens the media widget on an "Image with Description" block in Layout
 * Builder, where gin_lb renders its own markup for the dialog's filters.
 *
 * Uses the homepage, which the install profile creates as a landing page,
 * and reads its path from the site settings rather than assuming a node ID.
 * Nothing is saved, so the layout is left as it was.
 */
async function openBlockMediaWidget(page: Page) {
  await gotoReady(page, '/admin/config/system/site-information');
  const front = await page.getByRole('textbox', { name: 'Default front page' }).inputValue();
  await gotoReady(page, `${front}/layout`);
  await page.getByRole('link', { name: /^Add block/ }).first().click();
  // The tray loads over AJAX. mukurtu_gin_custom lists content blocks in it
  // directly, so there is no "Add a block" step.
  const tray = page.locator('#drupal-off-canvas');
  await tray.getByRole('link', { name: 'Image with Description', exact: true }).click({ timeout: SETTLE });
}

async function openMediaLibrary(page: Page, where: 'node form' | 'Layout Builder' = 'node form') {
  if (where === 'node form') {
    await gotoReady(page, '/node/add/digital_heritage');
  }
  else {
    await openBlockMediaWidget(page);
  }
  await page.getByRole('button', { name: 'Add media' }).first().click({ timeout: SETTLE });

  const modal = page.getByRole('dialog', { name: 'Add or select media' });
  // gin_lb renders .glb-views-exposed-form instead of .views-exposed-form.
  const filters = modal.locator('form[data-drupal-selector^="views-exposed-form-media-library"]');
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

  for (const where of ['node form', 'Layout Builder'] as const) {
    test(`"Apply filters" lines up with the Name and Sort by fields (${where})`, async ({ page }) => {
      // Two AJAX requests and two dialogs before the filters appear, which
      // a busy site can stretch past the default timeout.
      test.slow(where === 'Layout Builder', 'Layout Builder opens the library in several steps');
      const { name, sort, apply } = await openMediaLibrary(page, where);

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
  }

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

  // 320px is the stacked layout; 768px is side by side, where the media
  // table also pushed the dialog body sideways.
  for (const width of [320, 768]) {
    test(`the dialog reflows at ${width}px`, async ({ page }) => {
      await page.setViewportSize({ width, height: 640 });
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
        // Measured 420px at 320px wide with the fix and 11px without it. A
        // third of the window leaves room for differences between
        // environments.
        expect(content.clientHeight,
          `the library has ${content.clientHeight}px of a 640px window`)
          .toBeGreaterThanOrEqual(640 / 3);
      }).toPass({ timeout: SETTLE });
    });
  }

  // 200% text on a 320px screen. Doubling the root font size stands in for
  // the browser's text size setting, which the rem-based dialog follows.
  for (const where of ['node form', 'Layout Builder'] as const) {
    test(`the dialog reflows at 320px with 200% text (${where})`, async ({ page }) => {
      test.slow(where === 'Layout Builder', 'Layout Builder opens the library in several steps');
      await page.setViewportSize({ width: 320, height: 640 });
      const { modal } = await openMediaLibrary(page, where);
      await page.addStyleTag({ content: 'html { font-size: 200% !important; }' });
      // Core sizes the dialog's content on window resize.
      await page.evaluate(() => window.dispatchEvent(new Event('resize')));

      await expect(async () => {
        const content = await modal.locator('.ui-dialog-content').evaluate((el) => ({
          scrollWidth: el.scrollWidth,
          clientWidth: el.clientWidth,
          clientHeight: el.clientHeight,
        }));

        // Without the fix, "Apply filters" ran 34px past the node form's
        // dialog and gin_lb's Grid and Table links 61px past Layout
        // Builder's.
        expect(content.scrollWidth,
          `the library is ${content.scrollWidth}px wide in a ${content.clientWidth}px dialog`)
          .toBeLessThanOrEqual(content.clientWidth + TOLERANCE);
        // Measured 185px in the node form with the fix and 0px without it:
        // the title bar and buttons took the whole dialog.
        expect(content.clientHeight,
          `the library has ${content.clientHeight}px of a 640px window`)
          .toBeGreaterThanOrEqual(640 / 6);
      }).toPass({ timeout: SETTLE });
    });
  }
});
