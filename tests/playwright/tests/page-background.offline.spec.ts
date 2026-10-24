import { test, expect, Page } from '@playwright/test';
import fs = require('fs');
import path = require('path');

/**
 * The home page background image and the full-width hero that sits on it.
 *
 * Two behaviours, both pure CSS against classes the templates add:
 *
 * - A full-width hero with no image of its own and Background set to None
 *   paints no ground, so over a page background its text has to take the
 *   page's Light/Dark treatment. Its plain-page colours used to win instead,
 *   leaving a brand heading and dark body copy on the darkened image.
 * - The page and header scrims can be turned off (mukurtu_design.settings
 *   background.tint / header.tint), which page.html.twig expresses as
 *   page--no-tint / site-header--no-tint.
 *
 * Tests against the real compiled theme CSS with static fixtures (see
 * full-node-divider.spec.ts) rather than live content, so it needs no site and
 * runs in the `offline` project.
 */

const THEME_DIR = path.join(__dirname, '../../../themes/mukurtu_v4');

// Inlined in <head> rather than added with addStyleTag() after the content:
// links have a 0.12s colour transition, and a stylesheet arriving after first
// paint starts one from the default link blue, so a read could land mid-fade.
const THEME_CSS = fs.readFileSync(path.join(THEME_DIR, 'css/style.css'), 'utf8');

const WHITE = 'rgb(255, 255, 255)';
const BLACK = 'rgb(0, 0, 0)';

// The image itself is irrelevant, but the scrim is layered over
// var(--mukurtu-page-background-image), and an unset custom property would
// make the whole background-image declaration invalid.
const IMAGE = "url('data:image/gif;base64,R0lGODlhAQABAAAAACw=')";

// Mirrors block--block-content--type--full-image-with-description.html.twig
// for a block with no image, including the contextual links title_suffix
// renders inside the card for anyone with edit access.
function hero(ground: string): string {
  return `
    <div class="block-wrapper block--image-with-description block--image-with-description--full block--image-with-description--no-image hero-ground-${ground}">
      <div class="block-wrapper__first"></div>
      <div class="block-wrapper__second">
        <div class="block-wrapper__second-card">
          <div class="contextual"><ul class="contextual-links"><li><a href="#">Configure block</a></li></ul></div>
          <h2>Site name</h2>
          <p>Welcome text with <a href="#">a link</a>.</p>
        </div>
      </div>
    </div>
  `;
}

// Mirrors page.html.twig's .layout-container classes.
function page(inner: string, { background = true, treatment = 'light', tint = true } = {}): string {
  const classes = background
    ? ` page--has-background page--text-${treatment}${tint ? '' : ' page--no-tint'}`
    : '';
  const style = background ? ` style="--mukurtu-page-background-image: ${IMAGE};"` : '';
  return `<div class="layout-container${classes}"${style}>${inner}</div>`;
}

async function load(p: Page, html: string): Promise<void> {
  await p.setContent(`<!DOCTYPE html><html><head><title>Page background fixture</title><style>${THEME_CSS}</style></head><body>${html}</body></html>`);
}

async function color(p: Page, selector: string): Promise<string> {
  return p.locator(selector).evaluate((el) => getComputedStyle(el).color);
}

async function backgroundImage(p: Page, selector: string): Promise<string> {
  return p.locator(selector).evaluate((el) => getComputedStyle(el).backgroundImage);
}

test.describe('No-image hero over a page background', () => {
  test('takes the light treatment', async ({ page: p }) => {
    await load(p, page(hero('none'), { treatment: 'light' }));

    expect(await color(p, 'h2')).toBe(WHITE);
    expect(await color(p, 'p')).toBe(WHITE);
    expect(await color(p, 'p a')).toBe(WHITE);
  });

  test('takes the dark treatment', async ({ page: p }) => {
    await load(p, page(hero('none'), { treatment: 'dark' }));

    expect(await color(p, 'h2')).toBe(BLACK);
    expect(await color(p, 'p')).toBe(BLACK);
    expect(await color(p, 'p a')).toBe(BLACK);
  });

  // Negative control: the inherit rule must be scoped to a page background,
  // or a plain page would lose the hero's own colours.
  test('keeps its own colours on a plain page', async ({ page: p }) => {
    await load(p, page(hero('none'), { background: false }));

    const brandPrimaryDark = await p.evaluate(() => {
      const probe = document.createElement('span');
      probe.style.color = 'var(--brand-primary-dark)';
      document.body.append(probe);
      return getComputedStyle(probe).color;
    });

    expect(await color(p, 'h2')).toBe(brandPrimaryDark);
    expect(await color(p, 'p')).not.toBe(WHITE);
  });

  // A ground that paints its own colour keeps its own text colour, even when
  // the page treatment would pick a different one.
  test('a brand ground keeps white text over a dark treatment', async ({ page: p }) => {
    await load(p, page(hero('brand'), { treatment: 'dark' }));

    expect(await color(p, 'h2')).toBe(WHITE);
    expect(await color(p, 'p')).toBe(WHITE);
  });

  test('contextual links do not take the treatment colour', async ({ page: p }) => {
    await load(p, page(hero('none'), { treatment: 'light' }));

    expect(await color(p, '.contextual a')).not.toBe(WHITE);
  });
});

test.describe('Background scrim', () => {
  test('the page scrim is on by default and can be turned off', async ({ page: p }) => {
    await load(p, page('', { treatment: 'light' }));
    expect(await backgroundImage(p, '.layout-container')).toContain('linear-gradient(rgba(0, 0, 0, 0.6)');

    await load(p, page('', { treatment: 'light', tint: false }));
    const untinted = await backgroundImage(p, '.layout-container');
    expect(untinted).toContain('linear-gradient(rgba(0, 0, 0, 0)');
    expect(untinted).toContain('url(');
  });

  test('turning the page scrim off keeps the treatment text colour', async ({ page: p }) => {
    await load(p, page('<p>Text</p>', { treatment: 'dark', tint: false }));

    expect(await color(p, 'p')).toBe(BLACK);
  });

  test('the header scrim is on by default and can be turned off', async ({ page: p }) => {
    const header = (tint: boolean) => `
      <header class="site-header header site-header--has-background site-header--text-dark${tint ? '' : ' site-header--no-tint'}"
        style="--mukurtu-header-background-image: ${IMAGE};"></header>`;

    await load(p, header(true));
    expect(await backgroundImage(p, '.site-header')).toContain('linear-gradient(rgba(255, 255, 255, 0.6)');

    await load(p, header(false));
    expect(await backgroundImage(p, '.site-header')).toContain('linear-gradient(rgba(0, 0, 0, 0)');
  });
});

// Issue #2483: over a background image the header search sits on the photo,
// where its grey border measured as low as 2.3:1 against light areas. A white
// ring gives the border a known ground whatever the photo or tint.
test.describe('Header search over a background image', () => {
  // Mirrors block--mukurtu-v4-main-menu.html.twig, without the icon.
  const search = (instance: 'mobile' | 'desktop') => `
    <div class="header-search header-search--${instance}">
      <form class="header-search__form" role="search" aria-label="Search">
        <input class="header-search__input" type="search" aria-label="Search">
        <button class="header-search__button" type="submit" aria-label="Search"></button>
      </form>
    </div>`;
  const header = (classes: string) => `
    <header class="site-header header${classes}" style="--mukurtu-header-background-image: ${IMAGE};">
      ${search('mobile')}
      <div id="header-nav" class="header-nav"><div class="header__main-nav">${search('desktop')}</div></div>
    </header>`;

  const shadows = (p: Page) => p.locator('.header-search__form').evaluateAll(
    (forms) => forms.map((form) => getComputedStyle(form).boxShadow),
  );
  const RING = `${WHITE} 0px 0px 0px 2px`;

  for (const tint of [true, false]) {
    test(`rings the search over a header image${tint ? '' : ' with no tint'}`, async ({ page: p }) => {
      await load(p, header(` site-header--has-background site-header--text-dark${tint ? '' : ' site-header--no-tint'}`));
      expect(await shadows(p)).toEqual([RING, RING]);
    });

    test(`rings the search over a page image${tint ? '' : ' with no tint'}`, async ({ page: p }) => {
      await load(p, page(header(''), { treatment: 'light', tint }));
      expect(await shadows(p)).toEqual([RING, RING]);
    });
  }

  test('leaves the search alone on a plain header', async ({ page: p }) => {
    await load(p, header(''));
    expect(await shadows(p)).toEqual(['none', 'none']);
  });

  test('the border meets 3:1 against the ring', async ({ page: p }) => {
    await load(p, header(' site-header--has-background site-header--text-light'));
    const ratio = await p.locator('.header-search--mobile .header-search__input').evaluate((el) => {
      const [r, g, b] = getComputedStyle(el).borderTopColor.match(/\d+/g)!.map(Number);
      const channel = (c: number) => {
        const s = c / 255;
        return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
      };
      const luminance = 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b);
      return 1.05 / (luminance + 0.05);
    });
    expect(ratio).toBeGreaterThanOrEqual(3);
  });
});
