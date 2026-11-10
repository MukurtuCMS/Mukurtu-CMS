import { test, expect, Page } from '@playwright/test';
import fs = require('fs');
import path = require('path');

/**
 * A nested term's tag shows its path and may wrap onto several lines
 * (patches/tagify-nested-groups.patch). Removing it made the field grow
 * taller: Tagify shrinks a removed tag to zero width, and the patch's
 * wrapping rule let its text wrap a letter per line on the way (#2523).
 *
 * The offline job has no vendor/, so this takes the Tagify module's CSS as
 * the patch leaves it from the patch itself, with the real Tagify library.
 */

const ROOT = path.join(__dirname, '../../..');
const TAGIFY_JS = path.join(ROOT, 'libraries/tagify/dist/tagify.js');
const TAGIFY_CSS = path.join(ROOT, 'libraries/tagify/dist/tagify.css');
const PATCH = fs.readFileSync(path.join(ROOT, 'patches/tagify-nested-groups.patch'), 'utf8');

/**
 * Returns the new side of the patch's css/tagify.css hunks.
 *
 * Hunks start and end mid-rule, which CSS parsing skips over.
 */
function patchedModuleCss(): string {
  const file = PATCH.split(/^diff --git /m).find((part) => part.startsWith('a/css/tagify.css'))!;
  return file.split('\n')
    .filter((line) => /^[ +]/.test(line) && !line.startsWith('+++'))
    .map((line) => line.slice(1))
    .join('\n');
}

async function setUp(page: Page): Promise<void> {
  await page.setContent(`<!DOCTYPE html><html><head><title>Tagify fixture</title></head><body>
    <div style="inline-size: 320px">
      <label for="subject">Subject</label>
      <input id="subject" class="tagify-widget">
    </div>
  </body></html>`);
  await page.addStyleTag({ path: TAGIFY_CSS });
  await page.addStyleTag({ content: patchedModuleCss() });
  await page.addScriptTag({ path: TAGIFY_JS });
  await page.evaluate(() => {
    // Mirrors the patched module's tag template for a nested term.
    const tag = function (this: any, data: any) {
      return `<tag title="${data.parent_name} › ${data.label}" contenteditable="false" spellcheck="false"
          tabIndex="-1" class="tagify__tag" ${this.getAttributes(data)}>
        <x class="tagify__tag__removeBtn" role="button" aria-label="remove ${data.label} tag" tabindex="0"></x>
        <div id="tagify__tag-items">
          <span class="tagify__tag-text"><span class="tagify__tag-text-parent">${data.parent_name} › </span>${data.label}</span>
        </div>
      </tag>`;
    };
    // @ts-ignore Tagify is the global from tagify.js.
    const tagify = new Tagify(document.getElementById('subject'), {
      tagTextProp: 'label',
      templates: { tag },
    });
    tagify.addTags([
      { value: '1', label: 'Springfield', parent_name: 'United States › Oregon › Lane County' },
    ]);
  });
}

const fieldHeight = (page: Page) => page.locator('tags.tagify').evaluate((tags) => tags.getBoundingClientRect().height);

test('A nested term\'s long path wraps', async ({ page }) => {
  await setUp(page);
  const tag = page.locator('.tagify__tag');
  const lineHeight = await tag.evaluate((element) => parseFloat(getComputedStyle(element).lineHeight) || 20);
  const textHeight = await page.locator('.tagify__tag-text').evaluate((element) => element.getBoundingClientRect().height);
  expect(textHeight, 'the path wraps onto more than one line').toBeGreaterThan(lineHeight * 1.5);
});

test('Removing a nested term doesn\'t make the field taller', async ({ page }) => {
  await setUp(page);
  const before = await fieldHeight(page);
  // Record the field's height on every frame until the tag has gone.
  const tallest = page.evaluate(() => new Promise<number>((resolve) => {
    const tags = document.querySelector('tags.tagify')!;
    let max = 0;
    const sample = () => {
      max = Math.max(max, tags.getBoundingClientRect().height);
      if (tags.querySelector('.tagify__tag')) {
        requestAnimationFrame(sample);
      }
      else {
        resolve(max);
      }
    };
    requestAnimationFrame(sample);
  }));
  await page.locator('.tagify__tag__removeBtn').click();
  expect(await tallest).toBeLessThanOrEqual(before + 1);
  await expect(page.locator('.tagify__tag')).toHaveCount(0);
});
