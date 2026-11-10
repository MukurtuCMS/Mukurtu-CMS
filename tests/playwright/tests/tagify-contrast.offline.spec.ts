import { test, expect, Page } from '@playwright/test';
import path = require('path');

/**
 * Tagify's default highlighted suggestion (white on #3595f6, about 3.1:1)
 * and placeholder grays (2.8:1 or lower) fail WCAG 1.4.3 in the public
 * theme, which has no Gin skin to replace them (#2668).
 *
 * Runs the real Tagify library with the compiled theme CSS and each shipped
 * palette, so it needs no site and runs in the `offline` project.
 */

const ROOT = path.join(__dirname, '../../..');
const THEME = path.join(ROOT, 'themes/mukurtu_v4');
const TAGIFY_JS = path.join(ROOT, 'libraries/tagify/dist/tagify.js');
const TAGIFY_CSS = path.join(ROOT, 'libraries/tagify/dist/tagify.css');

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
