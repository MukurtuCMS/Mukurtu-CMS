import { test, expect, Page } from '@playwright/test';
import fs = require('fs');
import path = require('path');

/**
 * Issue #2583: the consent popup (contrib consent_popup) only toggled a body
 * class, so the page behind its backdrop stayed in the tab order, focus
 * started behind it, and it wasn't exposed as a dialog. The theme's template
 * now marks it up as a modal dialog, and js/consent-popup.js makes the rest
 * of the page inert while it's open.
 *
 * Runs the real theme behavior against a static fixture, so it needs no
 * site and runs in the `offline` project. vendor/ isn't installed in the
 * offline job, so the fixture stands in for the module's script: it adds
 * the body class on load, and its buttons do what the module's do.
 */

const THEME_DIR = path.join(__dirname, '../../../themes/mukurtu_v4');
const BEHAVIOR = fs.readFileSync(path.join(THEME_DIR, 'js/consent-popup.js'), 'utf8');

// Mirrors templates/misc/consent-popup.html.twig inside
// block--consent-popup.html.twig, placed in the footer as a site might.
function popup(title: boolean): string {
  const labelledby = title ? 'consent-popup-title' : 'consent-popup-text';
  return `
    <div class="block-wrapper block-consent-popup">
      <div id="javali-popup" role="dialog" aria-modal="true" tabindex="-1"
        aria-labelledby="${labelledby}" ${title ? 'aria-describedby="consent-popup-text"' : ''}>
        ${title ? '<h2 id="consent-popup-title" class="h3">Appropriate Use Agreement</h2>' : ''}
        <div id="consent-popup-text" class="consent-text"><p>By accessing this site, you agree to follow the terms of access.</p></div>
        <div class="consent-buttons">
          <button class="accept">I accept</button>
          <button class="decline">I decline</button>
        </div>
        <a class="visually-hidden" href="/" tabindex="-1" aria-hidden="true">Keep browsing our site</a>
      </div>
    </div>
  `;
}

// Minimal stand-ins for Drupal and once(), plus the module's own behavior
// reduced to what matters here (js/consent-popup.js in drupal/consent_popup).
const SHIMS = `
  window.Drupal = { behaviors: {} };
  window.once = (id, selector, context) => Array.from(context.querySelectorAll(selector));
`;
const MODULE = `
  document.body.classList.add('consent-popup-opened');
  document.querySelector('#javali-popup .accept').addEventListener('click', () => {
    document.body.classList.remove('consent-popup-opened');
  });
  document.querySelector('#javali-popup .decline').addEventListener('click', () => {
    document.querySelector('#javali-popup .consent-text').innerHTML = '<h2>You declined.</h2>';
    document.querySelector('#javali-popup .consent-buttons').remove();
    document.querySelector('#javali-popup a').classList.remove('visually-hidden');
  });
`;

async function load(page: Page, { title = true } = {}) {
  await page.setContent(`<!DOCTYPE html><html><head><title>Consent popup dialog fixture</title></head><body>
    <a href="#main" class="skip">Skip to main content</a>
    <header><a href="/user/login">Log in</a><input type="search" aria-label="Search"></header>
    <main id="main"><a href="/browse">Browse</a></main>
    <footer class="footer"><a href="/about">About</a>${popup(title)}</footer>
    <script>${SHIMS}</script>
    <script>${BEHAVIOR}</script>
    <script>${MODULE}</script>
    <script>Drupal.behaviors.consentPopupDialog.attach(document);</script>
  </body></html>`);
}

test('Consent popup is a named modal dialog', async ({ page }) => {
  await load(page);
  const dialog = page.getByRole('dialog', { name: 'Appropriate Use Agreement' });
  await expect(dialog).toHaveAttribute('aria-modal', 'true');
  await expect(dialog).toHaveAccessibleDescription(/By accessing this site/);
  // The "keep browsing" link is visually hidden until "I decline".
  await expect(page.getByRole('link', { name: 'Keep browsing our site' })).toHaveCount(0);
});

test('Consent popup without a title is named by its text', async ({ page }) => {
  await load(page, { title: false });
  await expect(page.getByRole('dialog', { name: /By accessing this site/ })).toBeVisible();
});

test('Consent popup takes focus and keeps Tab inside while open', async ({ page }) => {
  await load(page);
  // The module adds the body class on load; the behavior picks it up.
  await expect(page.locator('#javali-popup')).toBeFocused();

  // Tab cycles through the two buttons and never reaches the page behind
  // the popup, nor the visually hidden "keep browsing" link.
  await page.keyboard.press('Tab');
  await expect(page.getByRole('button', { name: 'I accept' })).toBeFocused();
  await page.keyboard.press('Tab');
  await expect(page.getByRole('button', { name: 'I decline' })).toBeFocused();
  await page.keyboard.press('Tab');
  const escaped = await page.evaluate(() => document.activeElement?.closest('body > :not(footer), footer > a, #javali-popup a') ? document.activeElement.textContent : null);
  expect(escaped, 'third Tab leaves for the browser, not the page').toBeNull();

  for (const selector of ['.skip', 'header', 'main', 'footer > a']) {
    expect(await page.locator(selector).evaluate((el) => el.closest('[inert]') !== null), `${selector} is inert`).toBe(true);
  }
  expect(await page.locator('#javali-popup').evaluate((el) => el.closest('[inert]') === null), 'popup is not inert').toBe(true);
});

test('Accepting the consent popup gives the page back', async ({ page }) => {
  await load(page);
  await page.getByRole('button', { name: 'I accept' }).focus();
  await page.keyboard.press('Enter');
  expect(await page.locator('[inert]').count(), 'nothing left inert').toBe(0);
  // The popup sits in the footer; Tab starts again from the top of the page
  // rather than from where it was.
  await page.keyboard.press('Tab');
  await expect(page.locator('.skip')).toBeFocused();
});

test('Declining the consent popup moves focus to the message', async ({ page }) => {
  await load(page);
  await page.getByRole('button', { name: 'I decline' }).focus();
  await page.keyboard.press('Enter');
  await expect(page.locator('#javali-popup .consent-text')).toBeFocused();
  await page.keyboard.press('Tab');
  await expect(page.getByRole('link', { name: 'Keep browsing our site' })).toBeFocused();
  // The page stays blocked after declining.
  expect(await page.locator('header').evaluate((el) => el.closest('[inert]') !== null)).toBe(true);
});
