import { test, expect } from '@playwright/test';
import path = require('path');

/**
 * Regression test for the full-node field divider: a `border-block-start`
 * applied to top-level fields inside `.full-node__content` (see
 * `_full-node.scss`'s `.field:where(:not(...))` rule) on node full displays
 * such as Digital Heritage, Person, Place, etc.
 *
 * The rule excludes `.field` elements nested inside a Layout Builder inline
 * block (wrapped in `block.html.twig`'s generic `.block-wrapper` class) so
 * the divider doesn't double up inside those blocks. But every node's
 * "Main page content" system block *also* renders through that same
 * `block.html.twig`, wrapping the entire `.full-node__content` tree from
 * the outside - an unscoped `.block-wrapper .field` exclusion matched that
 * case too, silently suppressing the divider on every ordinary node page.
 *
 * Tests against the real compiled theme CSS with two static fixtures
 * (see feedback_playwright_isolated_fixture_testing) rather than live
 * content, since only the DOM nesting shape matters here, not any
 * particular bundle's seeded fixture data.
 */

const THEME_DIR = path.join(__dirname, '../../../themes/mukurtu_v4');

// Mirrors the real markup order for a classic (non-Layout-Builder) full node
// page: the "Main page content" block wraps the entire node from the
// outside. Structure confirmed against a live render of
// node--digital-heritage--full.html.twig (block-mukurtu-v4-content wraps
// <article class="full-node">...).
const classicNodePageHtml = `
  <div id="block-mukurtu-v4-content" class="block-wrapper">
    <article class="contextual-region">
      <div class="full-node">
        <div class="full-node__grid">
          <div class="full-node__main">
            <div class="full-node__content">
              <div class="item-content">
                <div class="field field--name-field-description field--type-text-long field--label-above">
                  <div class="field__label">Description</div>
                  <div class="field__item"><p>Test description.</p></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </article>
  </div>
`;

// Mirrors a Layout Builder inline block (e.g. the "Text" block type) placed
// inside a basic page's Layout Builder section, itself inside
// .full-node__content. Only the .full-node__content -> ... -> .block-wrapper
// -> .field nesting direction is load-bearing for this rule; the layout
// section/region wrapper classes in between are not.
const layoutBuilderInlineBlockHtml = `
  <div class="full-node__content">
    <div class="layout layout--onecol">
      <div class="layout__region layout__region--content">
        <div class="block block-inline-blockcontent block-wrapper">
          <div class="field field--name-body field--type-text-long field--label-hidden">
            <div class="field__item"><p>Test inline block body.</p></div>
          </div>
        </div>
      </div>
    </div>
  </div>
`;

async function borderBlockStartStyle(page, html: string, selector: string) {
  await page.setContent(`<!DOCTYPE html><html><head><title>Full-node divider fixture</title></head><body>${html}</body></html>`);
  await page.addStyleTag({ path: path.join(THEME_DIR, 'css/style.css') });
  return page.locator(selector).evaluate((el) => getComputedStyle(el).borderBlockStartStyle);
}

test.describe('Full-node field divider', () => {
  test('divider renders on a top-level field of a classic full node page', async ({ page }) => {
    const style = await borderBlockStartStyle(page, classicNodePageHtml, '.field--name-field-description');
    expect(style).toBe('solid');
  });

  test('divider is still suppressed inside a Layout Builder inline block', async ({ page }) => {
    const style = await borderBlockStartStyle(page, layoutBuilderInlineBlockHtml, '.field--name-body');
    expect(style).toBe('none');
  });
});
