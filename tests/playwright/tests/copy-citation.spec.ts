import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import path = require('path');

/**
 * Tests the "copy citation" icon buttons on the Digital Heritage sidebar in
 * isolation: a static fixture reproducing the markup that
 * field--field-citation.html.twig and
 * field--node--field-knowledge-keepers--digital-heritage.html.twig render
 * (both via templates/misc/copy-citation-button.html.twig), with the real
 * compiled theme CSS and the real js/copy-citation.js loaded against it.
 *
 * navigator.clipboard is replaced with a recorder: headless clipboard
 * permissions are not reliable on a setContent() (about:blank) page, and
 * what this test cares about is which text copy-citation.js hands to
 * writeText() and which live region it announces in, not the browser's own
 * clipboard.
 *
 * `once()` is reimplemented minimally, as in remote-video-lightbox.spec.ts,
 * because @drupal/core's copy lives under the gitignored vendor/ directory.
 */

const THEME_DIR = path.join(__dirname, '../../../themes/mukurtu_v4');

const COPY_ICON = '<svg xmlns="http://www.w3.org/2000/svg" xml:space="preserve" width="16" height="18" aria-hidden="true"><path d="M11 0H3a1 1 0 0 0-1 1v1H1a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1v-1h1a1 1 0 0 0 1-1V1a1 1 0 0 0-1-1zM10 17H1V3h1v12a1 1 0 0 0 1 1h7v1zm2-2H3V2h8v13z"/></svg>';

// Mirrors templates/misc/copy-citation-button.html.twig.
function copyButtonHtml(buttonLabel: string, successMessage: string): string {
  return `
    <span class="citation-copy-controls">
      <span class="citation-copy-feedback" aria-hidden="true">Copied!</span>
      <button type="button" class="button button--copy-icon" data-copy-citation data-copy-success="${successMessage}" aria-label="${buttonLabel}">
        ${COPY_ICON}
      </button>
    </span>
    <div class="visually-hidden" role="status" aria-live="polite" data-copy-citation-status></div>
  `;
}

const CITATION_TEXT = 'Basket Weaving Demonstration. Jane Doe. 2020. https://example.org/digital-heritage/basket-weaving';

// The knowledge keepers paragraph template emits one line per sub-field
// with Twig indentation between them, and &nbsp; inside the date.
const KNOWLEDGE_KEEPERS_HTML = `
  <div class="paragraph paragraph--type--indigenous-knowledge-keepers paragraph--view-mode--default">
          Mary Smith,
         Example Nation.
          Treaty 6 Territory.
          Edmonton.
          Basket weaving.
          5&nbsp;June&nbsp;2024.
  </div>
`;
const KNOWLEDGE_KEEPERS_TEXT = 'Mary Smith, Example Nation. Treaty 6 Territory. Edmonton. Basket weaving. 5 June 2024.';

const CITATION_BUTTON = 'Copy citation';
const CITATION_SUCCESS = 'Citation copied to clipboard';
const KK_BUTTON = 'Copy Elders and Knowledge Keepers citation';
const KK_SUCCESS = 'Elders and Knowledge Keepers citation copied to clipboard';

function fixtureHtml(): string {
  return `
    <main>
      <div class="full-node">
        <div class="full-node__sidebar" style="inline-size: 360px;">
          <div class="sidebar-section">
            <div class="field field--name-field-citation field--type-text-long field--label-above" data-copy-citation-field>
              <div class="field__label-row">
                <div class="field__label">Citation</div>
                ${copyButtonHtml(CITATION_BUTTON, CITATION_SUCCESS)}
              </div>
              <div class="field__item">
                <div class="citation-text" data-citation-text><p>${CITATION_TEXT}</p></div>
              </div>
            </div>
            <div class="field field--name-field-knowledge-keepers field--type-entity-reference-revisions field--label-above" data-copy-citation-field>
              <div class="field__label-row">
                <div class="field__label">Citing Indigenous Elders and Knowledge Keepers</div>
                ${copyButtonHtml(KK_BUTTON, KK_SUCCESS)}
              </div>
              <div data-citation-text>
                <div class="field__item">${KNOWLEDGE_KEEPERS_HTML}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  `;
}

type ClipboardMode = 'record' | 'reject' | 'unsupported';

async function setUpFixture(page, clipboard: ClipboardMode = 'record') {
  await page.setViewportSize({ width: 1280, height: 900 });
  await page.setContent(`<!DOCTYPE html><html lang="en"><head><title>Copy citation fixture</title></head><body>${fixtureHtml()}</body></html>`);
  await page.addStyleTag({ path: path.join(THEME_DIR, 'css/style.css') });

  await page.addScriptTag({
    content: `
      window.Drupal = window.Drupal || { behaviors: {} };
      window.Drupal.t = function (str) { return str; };
      window.once = function (id, selector, context) {
        var root = context || document;
        var attr = 'data-once-' + id;
        var els = Array.prototype.filter.call(root.querySelectorAll(selector), function (el) {
          return !el.hasAttribute(attr);
        });
        els.forEach(function (el) { el.setAttribute(attr, ''); });
        return els;
      };
      window.__copied = [];
      var mode = ${JSON.stringify(clipboard)};
      Object.defineProperty(navigator, 'clipboard', {
        configurable: true,
        value: mode === 'unsupported' ? undefined : {
          writeText: function (text) {
            if (mode === 'reject') return Promise.reject(new Error('denied'));
            window.__copied.push(text);
            return Promise.resolve();
          },
        },
      });
    `,
  });
  await page.addScriptTag({ path: path.join(THEME_DIR, 'js/copy-citation.js') });
  await page.evaluate(() => (window as any).Drupal.behaviors.copyCitation.attach(document));

  // The stylesheet is added after the markup, so the "Copied!" text starts
  // visible and then runs its 0.2s opacity transition down to 0. A real
  // page loads CSS first and never shows that fade. Wait for it to finish
  // so nothing (notably axe's contrast check) measures it mid-transition.
  for (const feedback of await page.locator('.citation-copy-feedback').all()) {
    await expect(feedback).toHaveCSS('opacity', '0');
  }
}

function field(page, name: 'citation' | 'knowledge-keepers') {
  return page.locator(`.field--name-field-${name}`);
}

test.describe('Copy citation buttons', () => {
  test('both buttons sit in the label row, at the right edge, above the text', async ({ page }) => {
    await setUpFixture(page);

    for (const [name, buttonLabel] of [['citation', CITATION_BUTTON], ['knowledge-keepers', KK_BUTTON]] as const) {
      const f = field(page, name);
      const button = f.getByRole('button', { name: buttonLabel, exact: true });
      const buttonBox = (await button.boundingBox())!;
      const labelBox = (await f.locator('.field__label').boundingBox())!;
      const textBox = (await f.locator('[data-citation-text]').boundingBox())!;
      const fieldBox = (await f.boundingBox())!;

      // Right of the label, not below it.
      expect(buttonBox.x).toBeGreaterThanOrEqual(labelBox.x + labelBox.width);
      // Pushed to the field's right edge.
      expect(fieldBox.x + fieldBox.width - (buttonBox.x + buttonBox.width)).toBeLessThanOrEqual(1);
      // Vertically centered on the label's first line, and above the text.
      const labelFirstLineCenter = labelBox.y + 12;
      expect(Math.abs(buttonBox.y + buttonBox.height / 2 - labelFirstLineCenter)).toBeLessThanOrEqual(2);
      expect(labelBox.y + labelBox.height).toBeLessThanOrEqual(textBox.y);

      // WCAG 2.5.5 / 2.5.8 target size.
      expect(buttonBox.width).toBeGreaterThanOrEqual(44);
      expect(buttonBox.height).toBeGreaterThanOrEqual(44);
    }
  });

  test('the long knowledge keepers label wraps beside the button instead of pushing it down', async ({ page }) => {
    await setUpFixture(page);
    const f = field(page, 'knowledge-keepers');
    const labelBox = (await f.locator('.field__label').boundingBox())!;
    // 360px sidebar: the label needs more than one 24px line.
    expect(labelBox.height).toBeGreaterThan(24);
    const buttonBox = (await f.getByRole('button', { name: KK_BUTTON }).boundingBox())!;
    expect(buttonBox.y).toBeLessThan(labelBox.y + 24);
  });

  test('each button copies its own field and announces in its own live region', async ({ page }) => {
    await setUpFixture(page);
    const citationStatus = field(page, 'citation').locator('[data-copy-citation-status]');
    const kkStatus = field(page, 'knowledge-keepers').locator('[data-copy-citation-status]');

    await page.getByRole('button', { name: KK_BUTTON, exact: true }).click();
    await expect(kkStatus).toHaveText(KK_SUCCESS);
    // Negative control: the other field's region stays silent.
    await expect(citationStatus).toHaveText('');
    expect(await page.evaluate(() => (window as any).__copied)).toEqual([KNOWLEDGE_KEEPERS_TEXT]);

    await page.getByRole('button', { name: CITATION_BUTTON, exact: true }).click();
    await expect(citationStatus).toHaveText(CITATION_SUCCESS);
    expect(await page.evaluate(() => (window as any).__copied)).toEqual([KNOWLEDGE_KEEPERS_TEXT, CITATION_TEXT]);
  });

  test('shows the visual "Copied!" feedback only next to the clicked button', async ({ page }) => {
    await setUpFixture(page);
    await page.getByRole('button', { name: CITATION_BUTTON, exact: true }).click();
    await expect(field(page, 'citation').locator('.citation-copy-feedback')).toHaveCSS('opacity', '1');
    await expect(field(page, 'knowledge-keepers').locator('.citation-copy-feedback')).toHaveCSS('opacity', '0');
  });

  test('keeps line breaks from the citation template', async ({ page }) => {
    await setUpFixture(page);
    await page.locator('.field--name-field-citation [data-citation-text]').evaluate((el) => {
      el.innerHTML = '<p>Line one.<br>\n      Line two.</p>';
    });
    await page.getByRole('button', { name: CITATION_BUTTON, exact: true }).click();
    expect(await page.evaluate(() => (window as any).__copied)).toEqual(['Line one.\nLine two.']);
  });

  test('announces the fallback when the clipboard API is missing', async ({ page }) => {
    await setUpFixture(page, 'unsupported');
    await page.getByRole('button', { name: KK_BUTTON, exact: true }).click();
    await expect(field(page, 'knowledge-keepers').locator('[data-copy-citation-status]'))
      .toHaveText('Copy to clipboard is not supported in this browser.');
  });

  test('announces the failure message when the copy is rejected', async ({ page }) => {
    await setUpFixture(page, 'reject');
    await page.getByRole('button', { name: CITATION_BUTTON, exact: true }).click();
    await expect(field(page, 'citation').locator('[data-copy-citation-status]'))
      .toHaveText('Unable to copy citation. Please copy the text manually.');
  });

  test('has no axe violations', async ({ page }) => {
    await setUpFixture(page);
    const results = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'])
      .analyze();
    expect(results.violations).toEqual([]);
  });
});
