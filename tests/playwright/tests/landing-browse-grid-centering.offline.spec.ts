import { test, expect, Page } from '@playwright/test';
import fs = require('fs');
import path = require('path');

/**
 * The Browse by Category and Browse by Community landing page blocks center a
 * row that isn't full, as the Featured Content block does, while keeping the
 * card widths of the 1/2/4-column grid they had before. The /categories and
 * /communities pages share the same classes but keep their grid, so a short
 * row still starts at the inline start there.
 *
 * Tests against the real compiled theme CSS with static fixtures (see
 * landing-page-reflow.offline.spec.ts), so it needs no site and runs in the
 * `offline` project.
 */

const THEME_DIR = path.join(__dirname, '../../../themes/mukurtu_v4');
const THEME_CSS = fs.readFileSync(path.join(THEME_DIR, 'css/style.css'), 'utf8');

function categoryCards(count: number): string {
  return Array.from({ length: count }, (_, i) => `
    <div class="category-card views-row">
      <div class="category-teaser">
        <a href="#"><h2><div class="field field--name-name field--type-string field--label-hidden field__item">Category ${i + 1}</div></h2></a>
      </div>
    </div>`).join('');
}

function communityCards(count: number): string {
  return Array.from({ length: count }, (_, i) => `
    <div class="community-browse-block__row communities__item">
      <div class="field field--name-name"><a href="#">Community ${i + 1}</a></div>
    </div>`).join('');
}

interface Fixture {
  name: string;
  container: string;
  card: string;
  // Markup as the landing page block renders it.
  block: (count: number) => string;
  // Markup as the full listing page renders it.
  page: (count: number) => string;
}

const FIXTURES: Fixture[] = [
  {
    name: 'Browse by Category',
    container: '.category-grid',
    card: '.category-card',
    // Mirrors the Layout Builder views block: the block wrapper carries
    // data-layout-block-uuid.
    block: (count) => `
      <div class="views-element-container block-wrapper" data-layout-block-uuid="fixture">
        <h2>Browse by Category</h2>
        <div><div class="category-grid">${categoryCards(count)}</div></div>
      </div>`,
    // Mirrors /categories: the view sits in the main content block.
    page: (count) => `
      <div id="block-mukurtu-v4-content" class="block-wrapper">
        <div class="views-element-container"><div class="category-grid">${categoryCards(count)}</div></div>
      </div>`,
  },
  {
    name: 'Browse by Community',
    container: '.communities',
    card: '.communities__item',
    // Mirrors browse-by-community-block.html.twig.
    block: (count) => `
      <div class="block-wrapper" data-layout-block-uuid="fixture">
        <h2>Browse by Community</h2>
        <div class="view-browse-by-community">
          <div class="community-page-browse">
            <div class="communities">${communityCards(count)}</div>
          </div>
        </div>
      </div>`,
    // Mirrors /communities.
    page: (count) => `
      <div id="block-mukurtu-v4-content" class="block-wrapper">
        <div class="community-page-browse community-page-listing">
          <div class="communities">${communityCards(count)}</div>
        </div>
      </div>`,
  },
];

async function render(page: Page, width: number, body: string): Promise<void> {
  await page.setViewportSize({ width, height: 900 });
  await page.setContent(`<!DOCTYPE html><html><head><title>Browse grid fixture</title><style>${THEME_CSS}</style></head><body><main role="main"><div class="layout layout--onecol"><div class="layout__region layout__region--content">${body}</div></div></main></body></html>`);
}

interface Measurement {
  // The container's content box.
  left: number;
  right: number;
  // Each card's box.
  cards: { left: number; right: number; width: number; top: number }[];
}

async function measure(page: Page, fixture: Fixture): Promise<Measurement> {
  return page.locator(fixture.container).first().evaluate((el, cardSelector) => {
    const box = el.getBoundingClientRect();
    const style = getComputedStyle(el);
    return {
      left: box.left + parseFloat(style.paddingLeft) + parseFloat(style.borderLeftWidth),
      right: box.right - parseFloat(style.paddingRight) - parseFloat(style.borderRightWidth),
      cards: Array.from(el.querySelectorAll(`:scope > ${cardSelector}`)).map((card) => {
        const r = card.getBoundingClientRect();
        return { left: r.left, right: r.right, width: r.width, top: r.top };
      }),
    };
  }, fixture.card);
}

// Viewport widths for each column count: 4 from lg (60rem), 2 from sm (30rem),
// and 1 below that.
const LAYOUTS = [
  { width: 1280, columns: 4 },
  { width: 800, columns: 2 },
  { width: 320, columns: 1 },
];

for (const fixture of FIXTURES) {
  for (const { width, columns } of LAYOUTS) {
    test(`${fixture.name} block: cards keep their grid width at ${width}px`, async ({ page }) => {
      // A full row on the listing page gives the grid's column width.
      await render(page, width, fixture.page(columns));
      const grid = await measure(page, fixture);
      await render(page, width, fixture.block(columns));
      const block = await measure(page, fixture);

      expect(block.cards).toHaveLength(columns);
      for (let i = 0; i < columns; i++) {
        expect(Math.abs(block.cards[i].width - grid.cards[i].width), `card ${i + 1} width`).toBeLessThanOrEqual(1);
        expect(Math.abs(block.cards[i].left - grid.cards[i].left), `card ${i + 1} position`).toBeLessThanOrEqual(1);
      }
    });

    // Every count that leaves the last row part full at this width.
    const partialCounts = columns === 1 ? [] : Array.from({ length: columns - 1 }, (_, i) => i + 1).concat(columns + 1);
    for (const count of partialCounts) {
      test(`${fixture.name} block: ${count} card(s) centered at ${width}px`, async ({ page }) => {
        await render(page, width, fixture.page(columns));
        const cardWidth = (await measure(page, fixture)).cards[0].width;

        await render(page, width, fixture.block(count));
        const block = await measure(page, fixture);
        const lastTop = Math.max(...block.cards.map((c) => c.top));
        const lastRow = block.cards.filter((c) => Math.abs(c.top - lastTop) <= 1);

        expect(lastRow.length, 'last row is part full').toBeLessThan(columns);
        for (const card of block.cards) {
          expect(Math.abs(card.width - cardWidth), 'card width unchanged').toBeLessThanOrEqual(1);
        }
        const before = Math.min(...lastRow.map((c) => c.left)) - block.left;
        const after = block.right - Math.max(...lastRow.map((c) => c.right));
        expect(Math.abs(before - after), 'equal space on each side of the last row').toBeLessThanOrEqual(1);
        expect(before, 'last row is not at the inline start').toBeGreaterThan(1);
      });
    }
  }

  test(`${fixture.name} page: a short row still starts at the inline start`, async ({ page }) => {
    await render(page, 1280, fixture.page(1));
    const listing = await measure(page, fixture);
    expect(Math.abs(listing.cards[0].left - listing.left)).toBeLessThanOrEqual(1);
    const display = await page.locator(fixture.container).first().evaluate((el) => getComputedStyle(el).display);
    expect(display).toBe('grid');
  });

  test(`${fixture.name} block: no horizontal scroll at 320px`, async ({ page }) => {
    await render(page, 320, fixture.block(3));
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow).toBeLessThanOrEqual(1);
  });
}
