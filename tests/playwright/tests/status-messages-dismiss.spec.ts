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

// The severity icons the template draws, decorative beside the heading
// that names the type in words (WCAG 1.4.1, issue #2421).
const ICONS: Record<string, string> = {
  status: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 13" aria-hidden="true" focusable="false"><path d="M2 6.571L5.6 10 14 2" fill="none" stroke="currentColor" stroke-width="3"/></svg>',
  warning: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 4 14" fill="currentColor" aria-hidden="true" focusable="false"><path d="M.5 0h3v9h-3z"/><circle cx="2" cy="12.5" r="1.5"/></svg>',
  error: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" aria-hidden="true" focusable="false"><circle cx="7" cy="7" r="6" fill="none" stroke="currentColor" stroke-width="2"/><path d="M3 11.193L11.45 3" stroke="currentColor" stroke-width="2"/></svg>',
};

// Mirrors templates/misc/status-messages.html.twig.
function messageHtml(type: string, text: string): string {
  return `
    <div class="messages-list__item messages messages--${type}" data-drupal-selector="messages" role="contentinfo" aria-label="${LABELS[type]}">
      <div class="messages__container" data-drupal-selector="messages-container"${type === 'error' ? ' role="alert"' : ''}>
        <div class="messages__header">
          <div class="messages__icon">${ICONS[type]}</div>
          <h2 class="messages__title">${LABELS[type]}</h2>
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

const ALL_TYPES = ['status', 'warning', 'error'];
const ALL_THREE: [string, string][] = [
  ['status', STATUS_TEXT],
  ['warning', 'Check this.'],
  ['error', ERROR_TEXT],
];

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


  /**
   * WCAG 1.4.1 Use of Color (issue #2421). The three message types used to
   * differ only by their box colour: no icon, identical padding, and a
   * heading that was .visually-hidden. Someone who cannot tell the hues
   * apart, or is on a monochrome or forced-colors display, could not tell a
   * failure from a confirmation.
   *
   * These pin the two non-colour cues, and that they actually differ per
   * type. An icon drawn identically three times would satisfy a shallower
   * assertion while conveying nothing.
   */
  test('each message names its severity in visible text', async ({ page }) => {
    await setUpFixture(page, ALL_THREE);

    for (const type of ALL_TYPES) {
      const title = message(page, type).locator('.messages__title');
      await expect(title, `${type} has a visible heading`).toBeVisible();
      await expect(title).toHaveText(LABELS[type]);
    }
  });

  test('each message type draws a different icon', async ({ page }) => {
    await setUpFixture(page, ALL_THREE);

    const drawn: Record<string, string> = {};
    for (const type of ALL_TYPES) {
      const icon = message(page, type).locator('.messages__icon svg');
      await expect(icon, `${type} has an icon`).toBeAttached();
      // Decorative: the heading beside it already says the type in words.
      await expect(icon).toHaveAttribute('aria-hidden', 'true');
      drawn[type] = (await icon.innerHTML()).replace(/\s+/g, ' ').trim();
    }
    const shapes = Object.values(drawn);
    expect(new Set(shapes).size, `the three icons are distinct, got ${JSON.stringify(drawn)}`).toBe(ALL_TYPES.length);
  });

  test('the icon follows the message colour rather than a fixed hue', async ({ page }) => {
    // So it keeps each type's contrast and survives forced-colors mode,
    // the same reason the dismiss button uses currentColor.
    await setUpFixture(page, ALL_THREE);

    for (const type of ALL_TYPES) {
      const svg = message(page, type).locator('.messages__icon svg');
      const uses = await svg.evaluate((el) => el.outerHTML.includes('currentColor'));
      expect(uses, `${type} icon is drawn in currentColor`).toBe(true);
    }
  });

  test('has no axe violations', async ({ page }) => {
    await setUpFixture(page, [['status', STATUS_TEXT], ['warning', 'Check this.'], ['error', ERROR_TEXT]]);
    const results = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'])
      .analyze();
    expect(results.violations).toEqual([]);
  });
});
