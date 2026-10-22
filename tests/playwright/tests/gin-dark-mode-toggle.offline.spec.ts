import { test, expect, Page } from '@playwright/test';
import path = require('path');

/**
 * Tests that a checked Layout Builder toggle is colored in Gin's dark mode.
 *
 * Gin's dark mode declares --gin-switch: var(--gin-color-primary) on
 * <html class="gin--dark-mode">, but --gin-color-primary is only defined
 * under [data-gin-accent], which is on <body>. The var() resolves on <html>,
 * where it is invalid, so --gin-switch is empty everywhere and gin_lb's
 * checked toggle ("Display title" in the block form) stays the same gray as
 * an unchecked one (#1826).
 *
 * Offline: Gin and gin_lb are not in this repository, so GIN_CSS below
 * copies the rules that matter from Gin 5.0.16's theme/variables.css and
 * theme/accent.css, and gin_lb 3.0.0-beta1's toggle rules from
 * styles/components/_form.scss. The module's CSS is added after them, the
 * same order as on a real page.
 */

const MODULE_DIR = path.join(__dirname, '../../../modules/mukurtu_gin_custom');

const GIN_CSS = `
  :root { --gin-switch: #26a769; }
  .gin--dark-mode { --gin-switch: var(--gin-color-primary); }
  [data-gin-accent] { --gin-color-primary: rgb(var(--gin-color-primary-rgb)); }
  [data-gin-accent="custom"] { --gin-color-primary-rgb: 14, 120, 115; }
  .glb-checkbox-toggle { display: inline-block; width: 40px; height: 24px; background-color: #909090; }
  .glb-checkbox-toggle__inner { display: block; width: 100%; height: 100%; }
  input:checked ~ .glb-checkbox-toggle .glb-checkbox-toggle__inner {
    background-color: var(--gin-switch);
  }
`;

async function setUpToggle(page: Page, darkMode: boolean, withModuleCss: boolean) {
  await page.setContent(`<!DOCTYPE html>
<html lang="en"${darkMode ? ' class="gin--dark-mode"' : ''}><head><title>Gin toggle fixture</title></head>
<body data-gin-accent="custom">
  <div class="glb-form-type--checkbox">
    <input id="label-display" type="checkbox" checked>
    <span class="glb-checkbox-toggle"><span class="glb-checkbox-toggle__inner"></span></span>
    <label for="label-display">Display title</label>
  </div>
</body></html>`);
  await page.addStyleTag({ content: GIN_CSS });
  if (withModuleCss) {
    await page.addStyleTag({ path: path.join(MODULE_DIR, 'css/mukurtu-gin-custom.css') });
  }
}

test.describe('Gin dark mode toggle switch', () => {
  test('a checked toggle is filled with the dark-mode accent color', async ({ page }) => {
    await setUpToggle(page, true, true);
    // #15b7af, the dark-mode accent set earlier in mukurtu-gin-custom.css.
    await expect(page.locator('.glb-checkbox-toggle__inner')).toHaveCSS('background-color', 'rgb(21, 183, 175)');
  });

  test('light mode keeps Gin\'s green', async ({ page }) => {
    await setUpToggle(page, false, true);
    await expect(page.locator('.glb-checkbox-toggle__inner')).toHaveCSS('background-color', 'rgb(38, 167, 105)');
  });

  // Negative control: without the module's CSS, Gin's own rules leave the
  // checked fill transparent in dark mode, so the first test is not
  // passing by accident.
  test('Gin alone leaves the dark-mode checked fill transparent', async ({ page }) => {
    await setUpToggle(page, true, false);
    await expect(page.locator('.glb-checkbox-toggle__inner')).toHaveCSS('background-color', 'rgba(0, 0, 0, 0)');
  });
});
