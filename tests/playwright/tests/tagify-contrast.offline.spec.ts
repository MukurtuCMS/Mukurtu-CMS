import { test, expect, Page } from '@playwright/test';
import path = require('path');

/**
 * Tagify's default highlighted suggestion (white on #3595f6, about 3.1:1)
 * and placeholder grays (2.8:1 or lower) fail WCAG 1.4.3 in the public
 * theme, which has no Gin skin to replace them (#2668). The Tagify module
 * also fades its "No matching suggestions" row below 4.5:1 (#2727).
 *
 * Runs the real Tagify library with the compiled theme CSS and each shipped
 * palette, so it needs no site and runs in the `offline` project.
 */

const ROOT = path.join(__dirname, '../../..');
const THEME = path.join(ROOT, 'themes/mukurtu_v4');
const TAGIFY_JS = path.join(ROOT, 'libraries/tagify/dist/tagify.js');
const TAGIFY_CSS = path.join(ROOT, 'libraries/tagify/dist/tagify.css');
const NO_MATCH_CSS = path.join(ROOT, 'modules/mukurtu_core/css/tagify-no-match.css');

// The Tagify module's rules for its list items and "No matching
// suggestions" row (css/tagify.css), copied because vendor/ isn't installed
// in the offline job. They fade the row to half opacity and push the typed
// text to the far edge (#2727).
const MODULE_NO_MATCH_RULE = `.tagify__dropdown__item { display: flex; align-items: center; justify-content: space-between; }
.tagify--dropdown-item-no-match {
  display: grid; grid-template-columns: 1fr auto !important; align-items: center;
  padding: var(--tagify-dd-item-pad); opacity: 0.5; font-style: italic;
}
.tagify--dropdown-item-no-match p { grid-column: 1; font-size: 0.8em; }
.tagify--dropdown-item-no-match .tagify--value { grid-column: 2; font-size: 0.9em; }`;

const PALETTES: [string, string | null][] = [
  ['default', null],
  ['Red and bone', 'components/00-base/palettes/red-bone.css'],
  ['Blue and gold', 'components/00-base/palettes/blue-gold.css'],
];

async function setUp(page: Page, palette: string | null): Promise<void> {
  await page.setContent(`<!DOCTYPE html><html><head><title>Tagify fixture</title></head><body>
    <label for="keywords">Keywords</label>
    <input id="keywords" class="tagify-widget" placeholder="Add keywords">
  </body></html>`);
  // The library CSS is attached before the theme's, as on a real page.
  await page.addStyleTag({ path: TAGIFY_CSS });
  await page.addStyleTag({ content: MODULE_NO_MATCH_RULE });
  await page.addStyleTag({ path: NO_MATCH_CSS });
  await page.addStyleTag({ path: path.join(THEME, 'css/style.css') });
  if (palette) {
    await page.addStyleTag({ path: path.join(THEME, palette) });
  }
  await page.addScriptTag({ path: TAGIFY_JS });
  await page.evaluate(() => {
    // @ts-ignore Tagify is the global from tagify.js.
    new Tagify(document.getElementById('keywords'), {
      whitelist: ['Mary Jane Smith', 'Mary Jones'],
      dropdown: { enabled: 1, highlightFirst: true },
      // The module's "no match" row (js/tagify.js).
      templates: {
        dropdownItemNoMatch: (data: any) => `<div class="tagify__dropdown__item tagify--dropdown-item-no-match" value="noMatch" tabindex="0" role="option">
          <p>No matching suggestions found for:</p><strong class="tagify--value">${data.value}</strong></div>`,
      },
    });
  });
}

/**
 * Returns the contrast ratio of an element's text against its background.
 *
 * Text colors with alpha are blended over the background, as Tagify's
 * placeholder grays are.
 */
function contrast(page: Page, selector: string, pseudo: string | null = null): Promise<number> {
  return page.evaluate(([selector, pseudo]) => {
    const parse = (value: string) => {
      const parts = value.match(/[\d.]+/g)!.map(Number);
      return { rgb: parts.slice(0, 3), alpha: parts[3] ?? 1 };
    };
    const element = document.querySelector(selector)!;
    let background = parse('rgb(255, 255, 255)');
    for (let node: Element | null = element; node; node = node.parentElement) {
      const color = parse(getComputedStyle(node).backgroundColor);
      if (color.alpha === 1) {
        background = color;
        break;
      }
    }
    const text = parse(getComputedStyle(element, pseudo).color);
    const blended = text.rgb.map((value, i) => value * text.alpha + background.rgb[i] * (1 - text.alpha));
    const luminance = (rgb: number[]) => {
      const [r, g, b] = rgb.map((value) => {
        const channel = value / 255;
        return channel <= 0.03928 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;
      });
      return 0.2126 * r + 0.7152 * g + 0.0722 * b;
    };
    const [light, dark] = [luminance(blended), luminance(background.rgb)].sort((a, b) => b - a);
    return (light + 0.05) / (dark + 0.05);
  }, [selector, pseudo] as const);
}

async function openSuggestions(page: Page): Promise<void> {
  await page.locator('.tagify__input').click();
  await page.keyboard.type('Mary');
  await expect(page.locator('.tagify__dropdown__item--active')).toBeVisible();
}

for (const [name, palette] of PALETTES) {
  test.describe(`${name} palette`, () => {
    test.beforeEach(async ({ page }) => {
      await setUp(page, palette);
    });

    test('Highlighted suggestion text has 4.5:1 contrast', async ({ page }) => {
      await openSuggestions(page);
      expect(await contrast(page, '.tagify__dropdown__item--active')).toBeGreaterThanOrEqual(4.5);
    });

    test('"No matching suggestions" is not faded and has 4.5:1 contrast', async ({ page }) => {
      await page.locator('.tagify__input').click();
      await page.keyboard.type('zqx');
      const row = page.locator('.tagify--dropdown-item-no-match');
      await expect(row).toBeVisible();
      // contrast() reads colors, not opacity, so check the fade separately.
      await expect(row).toHaveCSS('opacity', '1');
      // As the only row it's highlighted; check it plain too.
      for (const highlighted of [true, false]) {
        await row.evaluate((element, on) => element.classList.toggle('tagify__dropdown__item--active', on), highlighted);
        for (const part of ['p', '.tagify--value']) {
          expect(await contrast(page, `.tagify--dropdown-item-no-match ${part}`), `${part}, highlighted: ${highlighted}`).toBeGreaterThanOrEqual(4.5);
        }
      }
    });

    test('Typed text follows the "No matching suggestions" message', async ({ page }) => {
      // The fixture field is narrow enough to wrap the message; widen it to
      // the size of a field on the edit form.
      await page.addStyleTag({ content: 'tags.tagify { inline-size: 40rem; }' });
      await page.locator('.tagify__input').click();
      await page.keyboard.type('zqx');
      // Measures the text, not the boxes, which can stretch to fill the row.
      // Measured together: the list slides into place as it opens.
      const [message, value] = await page.locator('.tagify--dropdown-item-no-match').evaluate((row) =>
        ['p', '.tagify--value'].map((part) => {
          const range = document.createRange();
          range.selectNodeContents(row.querySelector(part)!);
          return range.getBoundingClientRect().toJSON();
        }));
      // On the message's line, a short gap after it, not at the far edge.
      expect(value.x - message.right).toBeGreaterThanOrEqual(0);
      expect(value.x - message.right).toBeLessThan(16);
      const middle = value.y + value.height / 2;
      expect(middle).toBeGreaterThan(message.y);
      expect(middle).toBeLessThan(message.y + message.height);
    });

    test('Placeholder text has 4.5:1 contrast', async ({ page }) => {
      expect(await contrast(page, '.tagify__input', '::before')).toBeGreaterThanOrEqual(4.5);
      await page.locator('.tagify__input').click();
      await expect(page.locator('tags.tagify')).toHaveClass(/tagify--focus/);
      expect(await contrast(page, '.tagify__input', '::before')).toBeGreaterThanOrEqual(4.5);
    });
  });
}

test('Highlighted suggestion uses the system highlight colors in forced colors mode', async ({ page }) => {
  await page.emulateMedia({ forcedColors: 'active' });
  await setUp(page, null);
  await openSuggestions(page);
  // The operating system picks a readable Highlight/HighlightText pair, so
  // check those are used rather than measuring headless Chromium's stand-ins.
  const colors = await page.evaluate(() => {
    const reference = document.createElement('div');
    reference.style.cssText = 'forced-color-adjust: none; background: Highlight; color: HighlightText';
    document.body.append(reference);
    const style = (element: Element) => {
      const { backgroundColor, color } = getComputedStyle(element);
      return { backgroundColor, color };
    };
    const [active, other] = document.querySelectorAll('.tagify__dropdown__item');
    return { system: style(reference), active: style(active), other: style(other) };
  });
  expect(colors.active).toEqual(colors.system);
  expect(colors.other.backgroundColor).not.toBe(colors.active.backgroundColor);
});
