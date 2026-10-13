import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import path = require('path');

/**
 * Tests the dismiss button on status, warning, and error messages in
 * isolation: a static fixture reproducing the markup that
 * templates/misc/status-messages.html.twig renders, with the real compiled
 * theme CSS, the real js/messages.js, and the real js/message.theme.js (the
 * AJAX-inserted message markup) loaded against it.
 *
 * Drupal.Message is stubbed down to getMessageTypeLabels(), the only part of
 * core's message.js that message.theme.js calls, because @drupal/core lives
 * under the gitignored vendor/ directory.
 */

const THEME_DIR = path.join(__dirname, '../../../themes/mukurtu_v4');

const DISMISS = 'Dismiss message';
const X_ICON = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M2 2l12 12M14 2L2 14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';

const LABELS: Record<string, string> = {
  status: 'Status message',
  warning: 'Warning message',
  error: 'Error message',
};

// Mirrors templates/misc/status-messages.html.twig.
function messageHtml(type: string, text: string): string {
  return `
    <div class="messages-list__item messages messages--${type}" data-drupal-selector="messages" role="contentinfo" aria-label="${LABELS[type]}">
      <div class="messages__container" data-drupal-selector="messages-container"${type === 'error' ? ' role="alert"' : ''}>
        <div class="messages__header">
          <h2 class="visually-hidden">${LABELS[type]}</h2>
        </div>
        <div class="messages__content">${text}</div>
      </div>
      <button type="button" class="messages__close" data-drupal-selector="messages-close">
        ${X_ICON}
        <span class="visually-hidden">${DISMISS}</span>
      </button>
    </div>
  `;
}

const STATUS_TEXT = 'Full Image With Description <em class="placeholder">Welcome</em> has been updated.';
const ERROR_TEXT = 'The title field is required.';

function messagesListHtml(messages: [string, string][]): string {
  return `
    <div data-drupal-messages class="messages-list">
      <div class="messages__wrapper layout-container">
        ${messages.map(([type, text]) => messageHtml(type, text)).join('')}
      </div>
    </div>
  `;
}

// inDialog renders the list inside a stand-in for jQuery UI's dialog wrapper
// (role="dialog", tabindex="-1"), as the quick-action and media edit dialogs
// do, instead of in the page's highlighted region.
async function setUpFixture(page, messages: [string, string][], { inDialog = false } = {}) {
  await page.setViewportSize({ width: 1280, height: 900 });
  await page.setContent(`<!DOCTYPE html><html lang="en"><head><title>Status messages fixture</title></head><body>
    <div class="layout-container">
      <div class="region region__highlighted">
        ${inDialog ? '' : messagesListHtml(messages)}
      </div>
      <main id="main-content" role="main" tabindex="-1"><h1>Page title</h1></main>
    </div>
    ${inDialog ? `
      <div class="ui-dialog" role="dialog" tabindex="-1" aria-label="Edit">
        <div class="ui-dialog-content">${messagesListHtml(messages)}<p>Form</p></div>
      </div>` : ''}
  </body></html>`);
  await page.addStyleTag({ path: path.join(THEME_DIR, 'css/style.css') });
  await page.addScriptTag({
    content: `
      window.Drupal = window.Drupal || { behaviors: {}, theme: {} };
      window.Drupal.t = function (str) { return str; };
      window.Drupal.Message = { getMessageTypeLabels: function () { return ${JSON.stringify(LABELS)}; } };
    `,
  });
  await page.addScriptTag({ path: path.join(THEME_DIR, 'js/messages.js') });
  await page.addScriptTag({ path: path.join(THEME_DIR, 'js/message.theme.js') });
}

function message(page, type: string) {
  return page.locator(`.messages--${type}`);
}

test.describe('Dismissable status messages', () => {
  test('each message has a named dismiss button at its right edge with a 44px target', async ({ page }) => {
    await setUpFixture(page, [['status', STATUS_TEXT], ['error', ERROR_TEXT]]);

    for (const type of ['status', 'error']) {
      const box = (await message(page, type).boundingBox())!;
      const button = message(page, type).getByRole('button', { name: DISMISS, exact: true });
      await expect(button).toBeVisible();
      const buttonBox = (await button.boundingBox())!;

      expect(buttonBox.width).toBeGreaterThanOrEqual(44);
      expect(buttonBox.height).toBeGreaterThanOrEqual(44);
      // Inside the message box, at its right edge, past the message text.
      expect(buttonBox.x + buttonBox.width).toBeLessThanOrEqual(box.x + box.width);
      expect(box.x + box.width - (buttonBox.x + buttonBox.width)).toBeLessThanOrEqual(20);
      const textBox = (await message(page, type).locator('.messages__content').boundingBox())!;
      expect(buttonBox.x).toBeGreaterThanOrEqual(textBox.x + textBox.width);
    }
  });

  test('the X takes the message type\'s text color', async ({ page }) => {
    await setUpFixture(page, [['status', STATUS_TEXT], ['error', ERROR_TEXT]]);
    for (const type of ['status', 'error']) {
      const messageColor = await message(page, type).evaluate((el) => getComputedStyle(el).color);
      await expect(message(page, type).locator('.messages__close')).toHaveCSS('color', messageColor);
    }
  });

  test('clicking dismisses only that message and moves focus to the next one', async ({ page }) => {
    await setUpFixture(page, [['status', STATUS_TEXT], ['error', ERROR_TEXT]]);

    await message(page, 'status').getByRole('button', { name: DISMISS }).click();
    await expect(message(page, 'status')).toHaveCount(0);
    // Negative control: the other message stays.
    await expect(message(page, 'error')).toBeVisible();
    await expect(message(page, 'error').getByRole('button', { name: DISMISS })).toBeFocused();
  });

  test('dismissing a middle message moves focus to the one after it, not the first', async ({ page }) => {
    await setUpFixture(page, [['status', STATUS_TEXT], ['warning', 'Check this.'], ['error', ERROR_TEXT]]);

    await message(page, 'warning').getByRole('button', { name: DISMISS }).click();
    await expect(message(page, 'warning')).toHaveCount(0);
    await expect(message(page, 'error').getByRole('button', { name: DISMISS })).toBeFocused();
  });

  test('dismissing the last message in a list falls back to the previous one', async ({ page }) => {
    await setUpFixture(page, [['status', STATUS_TEXT], ['error', ERROR_TEXT]]);

    await message(page, 'error').getByRole('button', { name: DISMISS }).click();
    await expect(message(page, 'status').getByRole('button', { name: DISMISS })).toBeFocused();
  });

  test('in a dialog, the button keeps its layout and focus stays in the dialog', async ({ page }) => {
    await setUpFixture(page, [['status', STATUS_TEXT]], { inDialog: true });

    const button = message(page, 'status').getByRole('button', { name: DISMISS });
    const buttonBox = (await button.boundingBox())!;
    const textBox = (await message(page, 'status').locator('.messages__content').boundingBox())!;
    expect(buttonBox.width).toBeGreaterThanOrEqual(44);
    // Beside the text, not wrapped below it with the global button fill.
    expect(buttonBox.x).toBeGreaterThanOrEqual(textBox.x + textBox.width);
    await expect(button).toHaveCSS('background-color', 'rgba(0, 0, 0, 0)');

    await button.click();
    await expect(message(page, 'status')).toHaveCount(0);
    await expect(page.locator('.ui-dialog')).toBeFocused();
  });

  test('the button is outside the live region, so it is not read with the message', async ({ page }) => {
    await setUpFixture(page, [['error', ERROR_TEXT]]);
    await page.evaluate(() => {
      const el = (window as any).Drupal.theme.message({ text: 'Saved.' }, { type: 'status', id: 'm1' });
      document.querySelector('[data-drupal-messages] .messages__wrapper')!.appendChild(el);
    });

    for (const type of ['error', 'status']) {
      const liveRegion = message(page, type).locator('[role="alert"], [role="status"]');
      await expect(liveRegion).toHaveCount(1);
      await expect(liveRegion.locator('.messages__close')).toHaveCount(0);
      await expect(liveRegion).not.toContainText(DISMISS);
    }
  });

  test('keyboard: Enter dismisses the last message and focus lands on the main content', async ({ page }) => {
    await setUpFixture(page, [['status', STATUS_TEXT]]);

    await message(page, 'status').getByRole('button', { name: DISMISS }).focus();
    await page.keyboard.press('Enter');
    await expect(message(page, 'status')).toHaveCount(0);
    await expect(page.locator('#main-content')).toBeFocused();
  });

  test('keyboard: Space also dismisses', async ({ page }) => {
    await setUpFixture(page, [['warning', 'Check this.']]);

    await message(page, 'warning').getByRole('button', { name: DISMISS }).focus();
    await page.keyboard.press('Space');
    await expect(message(page, 'warning')).toHaveCount(0);
  });

  test('AJAX-inserted messages get a working dismiss button too', async ({ page }) => {
    await setUpFixture(page, []);
    await page.evaluate(() => {
      const el = (window as any).Drupal.theme.message({ text: 'Saved.' }, { type: 'status', id: 'm1' });
      document.querySelector('[data-drupal-messages] .messages__wrapper')!.appendChild(el);
    });

    const button = message(page, 'status').getByRole('button', { name: DISMISS, exact: true });
    await expect(button).toBeVisible();
    await button.click();
    await expect(message(page, 'status')).toHaveCount(0);
  });

  test('has no axe violations', async ({ page }) => {
    await setUpFixture(page, [['status', STATUS_TEXT], ['warning', 'Check this.'], ['error', ERROR_TEXT]]);
    const results = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'])
      .analyze();
    expect(results.violations).toEqual([]);
  });
});
