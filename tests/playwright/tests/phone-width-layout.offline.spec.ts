import { test, expect, Page } from '@playwright/test';
import fs = require('fs');
import path = require('path');

/**
 * Phone-width layout bugs in the mukurtu_v4 theme.
 *
 * - The "more actions" menu on a browse card opened off the left edge of the
 *   screen below md, because it was anchored to the end edge of a toggle
 *   that sits at the start of the card there.
 * - A local-task tab whose label wrapped split its border and padding across
 *   lines, and the export buttons squeezed the tabs instead of wrapping.
 * - The thumbnail strip cut thumbnails off at <=600px (the JS shrinks the
 *   strip to 44px but the images kept a 100px cap), and the prev/next arrows
 *   of the thumbnail and multipage carousels covered the first and last
 *   slides.
 * - The page title ran to the screen edges, and the collection hero heading
 *   was too big for a phone, so ordinary words broke mid-word.
 *
 * Runs the real compiled theme CSS, Splide, and carousel scripts against
 * static fixtures that mirror the rendered markup, so it needs no site
 * content and runs in the `offline` project. `once()` is reimplemented
 * minimally, as in multipage-announce.offline.spec.ts.
 */

const REPO = path.join(__dirname, '../../..');
const read = (p: string) => fs.readFileSync(path.join(REPO, p), 'utf8');
const THEME_CSS = read('themes/mukurtu_v4/css/style.css');
const SPLIDE_CSS = read('modules/mukurtu_core/libraries/splide/splide.min.css');
const SPLIDE_JS = read('modules/mukurtu_core/libraries/splide/splide.min.js');
const MEDIA_CAROUSEL_JS = read('themes/mukurtu_v4/js/media-asset-carousel.js');
const MULTIPAGE_JS = read('modules/mukurtu_multipage_items/js/multipage-nav.js');

const DRUPAL_STUB = `
  window.Drupal = { behaviors: {}, mukurtuStopMedia: function () {}, t: function (s) { return s; }, announce: function () {} };
  window.Drupal.ajax = function () { return { execute: function () { return new Promise(function () {}); } }; };
  window.once = function (id, selector, context) {
    var attr = 'data-once-' + id;
    var els = Array.prototype.filter.call((context || document).querySelectorAll(selector), function (el) {
      return !el.hasAttribute(attr);
    });
    els.forEach(function (el) { el.setAttribute(attr, ''); });
    return els;
  };
`;

// A stand-in image with real intrinsic dimensions, like the carousel
// thumbnail image style's output.
const img = (w: number, h: number, alt = '') =>
  `<img src="data:image/svg+xml,${encodeURIComponent(`<svg xmlns='http://www.w3.org/2000/svg' width='${w}' height='${h}'><rect width='100%' height='100%' fill='%23865'/></svg>`)}" width="${w}" height="${h}" alt="${alt}">`;

// The theme's page wrappers, which the page-level rules are nested under.
const page = (main: string, bodyClass = '') => `<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>Phone width fixture</title>
<style>${SPLIDE_CSS}</style><style>${THEME_CSS}</style><script>${DRUPAL_STUB}</script></head>
<body class="${bodyClass}"><div class="dialog-off-canvas-main-canvas"><div class="layout-container"><main>${main}</main></div></div></body></html>`;

async function open(p: Page, html: string, width: number, dir: 'ltr' | 'rtl' = 'ltr'): Promise<void> {
  await p.setViewportSize({ width, height: 844 });
  // A real origin rather than setContent(): multipage-nav.js calls
  // history.replaceState(), which about:blank refuses.
  await p.route(/^https:\/\/layout\.test\//, (route) => route.fulfill({ status: 200, contentType: 'text/html', body: html }));
  await p.goto('https://layout.test/');
  if (dir === 'rtl') {
    await p.evaluate(() => document.documentElement.setAttribute('dir', 'rtl'));
  }
}

// Mirrors node--*--browse.html.twig with quick-actions.html.twig.
const BROWSE_CARD = page(`
  <div class="browse"><div class="browse__main-content"><div class="region"><div class="block-wrapper">
    <div class="browse-container"><div class="browse-results"><div class="views-element-container"><div class="browse-content list">
      <div class="views-row"><article class="horizontal-card">
        <div class="horizontal-card__media"></div>
        <div class="horizontal-card__content">
          <div class="horizontal-card__content-header">
            <h2><a href="#">Sample word list with a long descriptive title</a></h2>
            <div class="horizontal-card__content-type">Word List</div>
            <div class="quick-actions">
              <details class="quick-actions__overflow">
                <summary class="quick-actions__overflow-toggle" aria-label="More actions for Sample word list">...</summary>
                <ul class="quick-actions__overflow-menu">
                  <li><a href="#">Edit</a></li>
                  <li><a href="#">Add to Collection</a></li>
                  <li><a href="#">Add to Personal Collection</a></li>
                  <li><a href="#">Add to export list</a></li>
                  <li><a href="#">Export</a></li>
                </ul>
              </details>
            </div>
          </div>
        </div>
      </article></div>
    </div></div></div></div>
  </div></div></div></div>
`);

for (const [width, dir] of [[320, 'ltr'], [390, 'ltr'], [390, 'rtl']] as const) {
  test(`More actions menu opens inside the screen at ${width}px ${dir}`, async ({ page: p }) => {
    await open(p, BROWSE_CARD, width, dir);
    await p.locator('.quick-actions__overflow-toggle').click();
    const menu = (await p.locator('.quick-actions__overflow-menu').boundingBox())!;
    const toggle = (await p.locator('.quick-actions__overflow-toggle').boundingBox())!;
    expect(menu.x, 'menu starts inside the screen').toBeGreaterThanOrEqual(0);
    expect(menu.x + menu.width, 'menu ends inside the screen').toBeLessThanOrEqual(width);

    // Below md the toggle sits at the start of the card, so the menu lines
    // up with the toggle's start edge and opens toward the end.
    if (dir === 'ltr') {
      expect(Math.abs(menu.x - toggle.x), 'menu lines up with the toggle start').toBeLessThanOrEqual(1);
    }
    else {
      expect(Math.abs(menu.x + menu.width - (toggle.x + toggle.width)), 'menu lines up with the toggle start').toBeLessThanOrEqual(1);
    }

    // The menu sizes to its labels, so a short one stays on one line.
    const lines = await p.getByRole('link', { name: 'Add to Collection' }).evaluate((a) => {
      const range = document.createRange();
      range.selectNodeContents(a);
      return new Set(Array.from(range.getClientRects(), (r) => Math.round(r.top))).size;
    });
    expect(lines, '"Add to Collection" stays on one line').toBe(1);
  });
}

test('More actions menu still opens back from the end corner at md and up', async ({ page: p }) => {
  // At >=md the toggle floats in the card's end corner, so the menu keeps
  // lining up with its end edge, as it did before.
  await open(p, BROWSE_CARD, 1280);
  await p.locator('.quick-actions__overflow-toggle').click();
  const menu = (await p.locator('.quick-actions__overflow-menu').boundingBox())!;
  const toggle = (await p.locator('.quick-actions__overflow-toggle').boundingBox())!;
  expect(Math.abs(menu.x + menu.width - (toggle.x + toggle.width)), 'menu lines up with the toggle end').toBeLessThanOrEqual(1);
});

// Mirrors block--mukurtu-v4-local-tasks.html.twig with core's
// menu-local-tasks.html.twig, as an editor sees it on an item.
const LOCAL_TASKS = page(`
  <div class="node"><div class="node__main-content"><div class="region">
    <div class="local-tasks"><div class="local-tasks__inner">
      <div class="local-tasks__tabs">
        <h2 class="visually-hidden">Primary tabs</h2>
        <ul>
          <li><a href="#" class="is-active" aria-current="page">View</a></li>
          <li><a href="#">Edit</a></li>
          <li><a href="#">Delete</a></li>
          <li><a href="#">Add Community Record</a></li>
          <li><a href="#">Create Multipage Item</a></li>
          <li><a href="#">Add to Collection</a></li>
          <li><a href="#">Add to Personal Collection</a></li>
        </ul>
      </div>
      <div class="local-tasks__export">
        <a href="#" class="button button--small">Export</a>
        <a href="#">Add to export list</a>
      </div>
    </div></div>
  </div></div></div>
`, 'path-node');

for (const width of [320, 390]) {
  test(`Local task tabs stay whole at ${width}px`, async ({ page: p }) => {
    await open(p, LOCAL_TASKS, width);
    const split = await p.locator('.local-tasks__tabs a').evaluateAll((links) =>
      links.filter((a) => a.getClientRects().length > 1).map((a) => a.textContent));
    expect(split, 'no tab breaks into separate boxes').toEqual([]);

    const tabs = (await p.locator('.local-tasks__tabs').boundingBox())!;
    const exportRow = (await p.locator('.local-tasks__export').boundingBox())!;
    expect(exportRow.y, 'export buttons wrap below the tabs').toBeGreaterThanOrEqual(tabs.y + tabs.height - 1);

    const overflow = await p.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow, 'no horizontal scroll').toBeLessThanOrEqual(0);
  });
}

// The arrows sit outside the track, so they can't cover a slide.
async function expectArrowsOutsideTrack(p: Page, root: string): Promise<void> {
  const track = (await p.locator(`${root} .splide__track`).boundingBox())!;
  const prev = (await p.locator(`${root} .splide__arrow--prev`).boundingBox())!;
  const next = (await p.locator(`${root} .splide__arrow--next`).boundingBox())!;
  expect(prev.x + prev.width, 'prev arrow is left of the track').toBeLessThanOrEqual(track.x + 1);
  expect(next.x, 'next arrow is right of the track').toBeGreaterThanOrEqual(track.x + track.width - 1);
}

// Mirrors field--node--field-media-assets.html.twig's two carousels. The main
// slides are reduced to placeholders: only the thumbnail strip is under test.
const THUMBS = [img(295, 184), img(180, 180, 'Document'), img(200, 200, 'Audio'), img(267, 200, 'Photo'), img(267, 200, 'Photo'), img(267, 200, 'Photo')];
const MEDIA_CAROUSELS = page(`
  <div class="full-node"><div class="media-carousels">
    <section class="splide media-carousel" aria-label="Media Assets Carousel">
      <div class="splide__track"><ul class="splide__list">
        ${THUMBS.map((_, i) => `<li class="splide__slide"><div class="media-asset--slide"><div class="media">Slide ${i + 1}</div></div></li>`).join('')}
      </ul></div>
    </section>
    <section class="splide thumbnail-carousel" aria-label="Thumbnail Carousel">
      <div class="splide__track"><ul class="splide__list">
        ${THUMBS.map((t) => `<li class="splide__slide"><div class="media media--image"><div class="field field--name-field-media-image field__item">${t}</div></div></li>`).join('')}
      </ul></div>
    </section>
  </div></div>
  <script>${SPLIDE_JS}</script>
  <script>${MEDIA_CAROUSEL_JS}</script>
  <script>Drupal.behaviors.mediaAssetCarousel.attach(document);</script>
`);

for (const width of [320, 390]) {
  test(`Thumbnail strip fits its thumbnails and arrows at ${width}px`, async ({ page: p }) => {
    await open(p, MEDIA_CAROUSELS, width);
    await expect(p.locator('.thumbnail-carousel.is-initialized')).toBeVisible();

    const clipped = await p.locator('.thumbnail-carousel .splide__slide').evaluateAll((slides) =>
      slides.filter((slide) => {
        const s = slide.getBoundingClientRect();
        const i = slide.querySelector('img')!.getBoundingClientRect();
        return i.top < s.top - 1 || i.bottom > s.bottom + 1;
      }).length);
    expect(clipped, 'every thumbnail image fits its slide').toBe(0);

    await expectArrowsOutsideTrack(p, '.thumbnail-carousel');
  });
}

// Mirrors multipage-item-book-view.html.twig.
const MULTIPAGE = page(`
  <div class="multipage-items">
    <div class="splide__wrapper">
      <section class="splide multipage-carousel" aria-label="Multipage Item Carousel">
        <div class="splide__track" id="mukurtu-multipage-item-page-nav"><ul class="splide__list">
          ${['5', '6', '7', '8'].map((id) => `<li class="splide__slide" data-id="${id}">${img(150, 100, `Page ${id}`)}</li>`).join('')}
        </ul></div>
      </section>
    </div>
    <div class="multipage-carousel__navigation">
      <label for="multipage-item-table-of-contents">Jump to page</label>
      <select id="multipage-item-table-of-contents">
        ${['5', '6', '7', '8'].map((id) => `<option value="${id}">Page ${id}</option>`).join('')}
      </select>
    </div>
    <div id="mukurtu-multipage-item-selected-page"></div>
  </div>
  <script>${SPLIDE_JS}</script>
  <script>${MULTIPAGE_JS}</script>
  <script>Drupal.behaviors.mukurtuMultipageNav.attach(document);</script>
`);

for (const width of [320, 390]) {
  test(`Multipage carousel arrows clear the pages at ${width}px`, async ({ page: p }) => {
    await open(p, MULTIPAGE, width);
    await expect(p.locator('.multipage-carousel.is-initialized')).toBeVisible();
    await expectArrowsOutsideTrack(p, '.multipage-carousel');

    // Opaque, like the media and thumbnail carousel arrows, so they keep
    // their contrast over any page thumbnail.
    const bg = await p.locator('.multipage-carousel .splide__arrow--next').evaluate((el) => getComputedStyle(el).backgroundColor);
    expect(bg).toBe('rgba(0, 0, 0, 0.6)');
  });
}

// Mirrors page-title.html.twig and node--collection--full.html.twig's header.
const COLLECTION = page(`
  <div class="region"><div class="page__title"><h1><div class="field field--name-title field__item">Sample collection of items for layout testing</div></h1></div></div>
  <div class="node"><div class="node__main-content"><div class="region"><div class="block-wrapper"><article>
    <div class="full-node collection"><div class="collection__header">
      <div class="collection__header__image"></div>
      <div class="collection__header__text">
        <h2><div class="field field--name-title field__item">Sample collection of items for layout testing</div></h2>
        <div class="field field--name-field-summary field__item">A collection.</div>
      </div>
    </div></div>
  </article></div></div></div></div>
`, 'path-node page-node-type-collection');

// Words whose letters land on more than one line.
const brokenWords = (p: Page, selector: string) => p.locator(selector).evaluate((el) => {
  const broken: string[] = [];
  const walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT);
  const range = document.createRange();
  for (let node = walker.nextNode(); node; node = walker.nextNode()) {
    const re = /\S+/g;
    let m;
    while ((m = re.exec(node.textContent!))) {
      range.setStart(node, m.index);
      range.setEnd(node, m.index + m[0].length);
      if (new Set(Array.from(range.getClientRects(), (r) => Math.round(r.top))).size > 1) broken.push(m[0]);
    }
  }
  return broken;
});

for (const width of [320, 390]) {
  test(`Page title and collection hero fit a ${width}px screen`, async ({ page: p }) => {
    await open(p, COLLECTION, width);

    // The title text keeps a side gutter instead of touching the edges.
    const text = await p.locator('.page__title h1').evaluate((h1) => {
      const range = document.createRange();
      range.selectNodeContents(h1);
      const rects = Array.from(range.getClientRects());
      return { left: Math.min(...rects.map((r) => r.left)), right: Math.max(...rects.map((r) => r.right)) };
    });
    expect(text.left, 'title has a start gutter').toBeGreaterThanOrEqual(16);
    expect(text.right, 'title has an end gutter').toBeLessThanOrEqual(width - 16);

    expect(await brokenWords(p, '.page__title h1'), 'no title word breaks mid-word').toEqual([]);
    expect(await brokenWords(p, '.collection__header__text h2'), 'no hero word breaks mid-word').toEqual([]);
  });
}
