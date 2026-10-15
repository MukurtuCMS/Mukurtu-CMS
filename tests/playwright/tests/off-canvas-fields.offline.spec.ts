import { test, expect, Page } from '@playwright/test';
import path = require('path');

/**
 * Tests that fields in the front-end block edit dialog have a visible edge.
 *
 * The edit button from lb-frontend-edit.js opens core's off-canvas dialog.
 * Core's off-canvas form.css gives text inputs and selects a white fill and
 * a transparent border, so on Mukurtu's white dialog the fields had no
 * visible edge (WCAG 1.4.11). Reported in review of #2409.
 *
 * Offline: core is not in this repository, so CORE_FORM_CSS below copies
 * the rules that matter from core/misc/dialog/off-canvas/css/form.css. It
 * is added after lb-frontend-edit.css, the same order as on a real page,
 * where core's CSS arrives later with the dialog's AJAX response.
 */

const MODULE_DIR = path.join(__dirname, '../../../modules/mukurtu_gin_custom');

const CORE_FORM_CSS = `
  #drupal-off-canvas-wrapper {
    --drupal-off-canvas-input-background-color: #fff;
    --drupal-off-canvas-input-border: solid 1px transparent;
  }
  #drupal-off-canvas-wrapper input,
  #drupal-off-canvas-wrapper select {
    border: var(--drupal-off-canvas-input-border);
    background-color: var(--drupal-off-canvas-input-background-color);
  }
`;

async function setUpDialog(page: Page, withModuleCss: boolean) {
  await page.setContent(`<!DOCTYPE html><html lang="en"><head><title>Off-canvas fields fixture</title></head>
<body style="background: #fff">
  <div id="drupal-off-canvas-wrapper">
    <label for="info">Block description</label>
    <input id="info" type="text" value="Welcome">
    <label for="format">Text color</label>
    <select id="format"><option>Dark</option></select>
  </div>
</body></html>`);
  if (withModuleCss) {
    await page.addStyleTag({ path: path.join(MODULE_DIR, 'css/lb-frontend-edit.css') });
  }
  await page.addStyleTag({ content: CORE_FORM_CSS });
}

test.describe('Front-end block edit dialog fields', () => {
  for (const selector of ['#info', '#format']) {
    test(`${selector} has a visible border`, async ({ page }) => {
      await setUpDialog(page, true);
      const field = page.locator(selector);
      await expect(field).toHaveCSS('border-top-style', 'solid');
      await expect(field).toHaveCSS('border-top-color', 'rgb(118, 118, 118)');
    });
  }

  // Negative control: without the module's CSS, core's transparent border
  // wins, so the assertions above are not passing by accident.
  test('core alone leaves the border transparent', async ({ page }) => {
    await setUpDialog(page, false);
    await expect(page.locator('#format')).toHaveCSS('border-top-color', 'rgba(0, 0, 0, 0)');
  });
});
