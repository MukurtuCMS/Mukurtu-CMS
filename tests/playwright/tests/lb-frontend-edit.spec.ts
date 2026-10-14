import { test, expect } from '@playwright/test';
import path = require('path');

/**
 * Tests lb-frontend-edit.js's dedup of Drupal core's own "Configure block"
 * contextual link against Mukurtu's own front-end block-edit button, in
 * isolation from the rest of the site: a static fixture reproducing the
 * block-template DOM shape (title_suffix's contextual-links markup nested
 * inside the block wrapper that carries data-layout-block-uuid), with the
 * real compiled lb-frontend-edit.css/.js loaded against it.
 *
 * Regression covered: https://github.com/MukurtuCMS/Mukurtu-CMS/pull/2239 -
 * both a Mukurtu-styled and a Gin-styled edit pencil showing at once on a
 * landing-page block. lb-frontend-edit.css now hides .contextual wherever
 * .lb-editable is present - but only block A below gets .lb-editable (it has
 * a matching mukurtuLbEditUrls entry), so block B's .contextual staying
 * visible guards the access-check fallback: a block the JS can't add its own
 * button to (no edit URL - e.g. the current user lacks update access on that
 * one block_content entity) must keep core's link as its only edit path.
 */

const MODULE_DIR = path.join(__dirname, '../../../modules/mukurtu_gin_custom');

function fixtureHtml(): string {
  function block(uuid: string, label: string): string {
    return `
      <div class="block-wrapper block--image-with-description block--image-with-description--full"
           data-layout-block-uuid="${uuid}" data-lb-block-label="${label}">
        <div class="block-wrapper__first"></div>
        <div class="block-wrapper__second">
          <div class="block-wrapper__second-card">
            <div class="contextual-region">
              <div class="contextual">
                <button class="trigger" type="button">Configure block</button>
              </div>
            </div>
            <h2>${label}</h2>
          </div>
        </div>
      </div>
    `;
  }

  return `
    <div id="block-a">${block('uuid-a', 'Hero Image')}</div>
    <div id="block-b">${block('uuid-b', 'Other Block')}</div>
  `;
}

/**
 * Loads the fixture markup plus the real lb-frontend-edit.css/.js, stubs
 * just enough of Drupal's JS runtime for the behavior to run, then attaches
 * it with only block A's UUID resolvable to an edit URL.
 */
async function setUpFixture(page) {
  await page.setContent(`<!DOCTYPE html><html><head><title>LB frontend edit fixture</title></head><body>${fixtureHtml()}</body></html>`);

  await page.addStyleTag({ path: path.join(MODULE_DIR, 'css/lb-frontend-edit.css') });

  await page.addScriptTag({
    content: `
      window.jQuery = window.jQuery || function () {};
      window.Drupal = window.Drupal || { behaviors: {} };
      window.Drupal.t = function (str, args) {
        if (args) {
          Object.keys(args).forEach(function (key) {
            str = str.replace(key, args[key]);
          });
        }
        return str;
      };
      window.Drupal.attachBehaviors = function () {};
      window.once = function (id, selector, context) {
        var root = context || document;
        var attr = 'data-once-' + id;
        var els = Array.prototype.filter.call(root.querySelectorAll(selector), function (el) {
          return !el.hasAttribute(attr);
        });
        els.forEach(function (el) { el.setAttribute(attr, ''); });
        return els;
      };
    `,
  });
  await page.addScriptTag({ path: path.join(MODULE_DIR, 'js/lb-frontend-edit.js') });

  await page.evaluate(() => {
    (window as any).Drupal.behaviors.mukurtuLbFrontendEdit.attach(document, {
      mukurtuLbEditUrls: { 'uuid-a': '/block-content/1/edit' },
    });
  });
}

test.describe('Front-end Layout Builder block edit button', () => {
  test('replaces the core contextual link where its own edit URL exists', async ({ page }) => {
    await setUpFixture(page);

    const blockA = page.locator('#block-a');
    await expect(blockA.locator('.lb-edit-btn')).toHaveCount(1);
    await expect(blockA.locator('.contextual')).toBeHidden();
  });

  test('leaves the core contextual link as a fallback where no edit URL exists', async ({ page }) => {
    await setUpFixture(page);

    const blockB = page.locator('#block-b');
    await expect(blockB.locator('.lb-edit-btn')).toHaveCount(0);
    await expect(blockB.locator('.contextual')).toBeVisible();
  });
});
