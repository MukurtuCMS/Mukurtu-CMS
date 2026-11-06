import { test, expect, Page } from '@playwright/test';
import fs = require('fs');
import path = require('path');

/**
 * Issue #1301: several content warnings can stack on one media item. The
 * overlay used to be a role="button" div whose label hid the warning text
 * from screen readers. It now holds the warnings and a real "Show media"
 * button described by them. These checks cover what a Kernel test can't:
 * focus after dismissing, scrolling on small screens, and thumbnails.
 *
 * Runs the real compiled theme CSS and the module's behavior against a
 * static fixture, so it needs no site and runs in the `offline` project.
 */

const ROOT = path.join(__dirname, '../../..');
const THEME_CSS = fs.readFileSync(path.join(ROOT, 'themes/mukurtu_v4/css/style.css'), 'utf8');
const BEHAVIOR = fs.readFileSync(path.join(ROOT, 'modules/mukurtu_content_warnings/js/content-warnings.js'), 'utf8');

const SHIMS = `
  window.Drupal = { behaviors: {} };
  window.once = (id, selector, context) => Array.from(context.querySelectorAll(selector));
`;

const WARNINGS = [
  'Warning: The following people are deceased. Click through to access content. Ada Example, Ben Example',
  'Warning: This media shows a ceremony that some viewers may find sensitive. Click through to access content.',
  'Warning: This media shows funeral or burial practices. Click through to access content.',
];

// Mirrors mukurtu-content-warnings.html.twig.
function overlay(mid: number): string {
  const id = `mukurtu-content-warnings-${mid}`;
  return `
    <div class="mukurtu-content-warnings" data-mid="${mid}" tabindex="-1">
      <p id="${id}-title" class="mukurtu-content-warnings__title">Media content warnings</p>
      <p class="mukurtu-content-warnings__card-hint">Access item to view</p>
      <ul id="${id}" class="mukurtu-content-warnings__list" role="list">
        ${WARNINGS.map((w) => `<li><div class="mukurtu-content-warning">${w}</div></li>`).join('')}
      </ul>
      <div class="mukurtu-content-warnings__actions">
        <button type="button" class="mukurtu-content-warnings__dismiss" aria-describedby="${id}-title ${id}">Show media</button>
      </div>
    </div>`;
}

// Mirrors media--image--media-assets.html.twig: the slide's .media also holds
// the hidden copy the lightbox shows, with its own overlay.
const IMAGE = `
  <div class="media media--image" style="inline-size: 100%; block-size: 181px">
    ${overlay(1)}
    <img alt="Sample landscape" width="272" height="181" src="data:image/gif;base64,R0lGODlhAQABAAAAACw=">
    <div id="image-1" class="media-asset--glightbox-inline" style="display: none">
      <div class="media media--image">${overlay(1)}</div>
    </div>
  </div>
  <a class="media-asset--link button" href="#image-1">Expand</a>`;

const VIDEO = `
  <div class="media media--video" style="inline-size: 100%; block-size: 181px">
    ${overlay(2)}
    <video controls width="272" height="181"></video>
  </div>`;

const THUMBNAIL = `
  <div class="splide__track--nav">
    <div class="media" style="inline-size: 150px; block-size: 100px">${overlay(3)}</div>
  </div>`;

async function load(page: Page, body: string) {
  await page.setContent(`<!DOCTYPE html><html><head><title>Content warnings fixture</title><style>${THEME_CSS}</style></head><body>
    <main style="padding: 16px">${body}</main>
    <script>${SHIMS}</script>
    <script>${BEHAVIOR}</script>
    <script>Drupal.behaviors.contentWarnings.attach(document);</script>
  </body></html>`);
}

test('The dismiss button is described by the title and every warning', async ({ page }) => {
  await load(page, IMAGE);
  const button = page.getByRole('button', { name: 'Show media' }).first();
  await expect(button).toHaveAccessibleDescription(new RegExp(`^Media content warnings .*${WARNINGS[1].slice(0, 30)}.*${WARNINGS[2].slice(0, 30)}`));
});

test('Dismissing an image moves focus to the media, not the page body', async ({ page }) => {
  await load(page, IMAGE);
  await page.getByRole('button', { name: 'Show media' }).first().focus();
  await page.keyboard.press('Enter');
  for (const warning of await page.locator('.mukurtu-content-warnings').all()) {
    await expect(warning).toHaveClass(/dismissed/);
  }
  await expect(page.locator('main > .media')).toBeFocused();
});

test('Dismissing a video moves focus to the player', async ({ page }) => {
  await load(page, VIDEO);
  await page.getByRole('button', { name: 'Show media' }).focus();
  await page.keyboard.press('Space');
  await expect(page.locator('video')).toBeFocused();
});

test('Clicking the warning text dismisses it', async ({ page }) => {
  await load(page, VIDEO);
  await page.getByText(WARNINGS[1]).click();
  await expect(page.locator('.mukurtu-content-warnings')).toHaveClass(/dismissed/);
});

test('Long warnings scroll from the top on a phone instead of clipping', async ({ page }) => {
  await page.setViewportSize({ width: 320, height: 640 });
  await load(page, VIDEO);
  const metrics = await page.locator('.mukurtu-content-warnings').evaluate((el) => ({
    scrolls: el.scrollHeight > el.clientHeight,
    overflowY: getComputedStyle(el).overflowY,
    firstLineInside: el.querySelector('li')!.getBoundingClientRect().top >= el.getBoundingClientRect().top,
  }));
  expect(metrics.scrolls, 'the fixture is taller than the media').toBe(true);
  expect(metrics.overflowY).toBe('auto');
  expect(metrics.firstLineInside, 'the first warning starts inside the overlay').toBe(true);
});

// Mirrors _card-media.html.twig for warned media: no link around the media,
// and the item URL handed to the overlay. The card's title link follows.
const CARD = `
  <article>
    <div class="card-media--content-warning" data-mukurtu-card-url="#item">
      <div class="media" style="inline-size: 203px; block-size: 135px">${overlay(4)}</div>
    </div>
    <h2><a href="#item">Card title</a></h2>
  </article>`;

// Media some other template still wraps in a link hidden from AT.
const LINKED_CARD = `
  <a href="#item" aria-hidden="true" tabindex="-1">
    <div class="media" style="inline-size: 203px; block-size: 135px">${overlay(6)}</div>
  </a>
  <a href="#item">Card title</a>`;

test('A card shows the title, a link to the item, and Show media last', async ({ page }) => {
  await load(page, CARD);
  const warning = page.locator('.mukurtu-content-warnings');
  const link = page.getByRole('link', { name: 'Access item to view' });
  const button = page.getByRole('button', { name: 'Show media' });
  await expect(page.getByText('Media content warnings')).toBeVisible();
  await expect(link).toHaveAttribute('href', '#item');
  // Every card's link has the same text, so the card's title describes it.
  await expect(link).toHaveAccessibleDescription('Card title');
  await expect(button).toBeVisible();
  await expect(page.getByText(WARNINGS[0])).toBeHidden();
  const fits = await warning.evaluate((el) => el.scrollHeight <= el.clientHeight + 1);
  expect(fits, 'the card fits the smallest card').toBe(true);
  const linkBox = (await link.boundingBox())!;
  const buttonBox = (await button.boundingBox())!;
  expect(buttonBox.y, 'Show media comes after the link').toBeGreaterThan(linkBox.y);
  // Screen readers still hear every warning with the button.
  await expect(button).toHaveAccessibleDescription(new RegExp(WARNINGS[1].slice(0, 30)));
});

test('A card link opens the item without revealing the media', async ({ page }) => {
  await load(page, CARD);
  await page.getByRole('link', { name: 'Access item to view' }).click();
  await expect(page).toHaveURL(/#item$/);
  await expect(page.locator('.mukurtu-content-warnings')).not.toHaveClass(/dismissed/);
});

test('Show media on a card reveals the media in place', async ({ page }) => {
  await load(page, CARD);
  await page.getByRole('button', { name: 'Show media' }).focus();
  await page.keyboard.press('Enter');
  await expect(page.locator('.mukurtu-content-warnings')).toHaveClass(/dismissed/);
  await expect(page).not.toHaveURL(/#item$/);
});

test('Media still inside a link shows the title and hint, and adds no Tab stop', async ({ page }) => {
  await load(page, LINKED_CARD);
  await expect(page.getByText('Access item to view')).toBeVisible();
  await expect(page.locator('.mukurtu-content-warnings__dismiss')).toBeHidden();
  await page.keyboard.press('Tab');
  await expect(page.getByRole('link', { name: 'Card title' })).toBeFocused();
  await page.getByText('Access item to view').click();
  await expect(page).toHaveURL(/#item$/);
});

test('Outside cards, the card hint stays hidden', async ({ page }) => {
  await load(page, VIDEO);
  await expect(page.getByText('Access item to view')).toBeHidden();
});

test('The button stays in view while long warnings scroll', async ({ page }) => {
  await page.setViewportSize({ width: 320, height: 640 });
  await load(page, VIDEO);
  const warning = page.locator('.mukurtu-content-warnings');
  await expect(warning).toHaveClass(/mukurtu-content-warnings--scrolls/);
  const inView = async () => warning.evaluate((el) => {
    const o = el.getBoundingClientRect();
    const b = el.querySelector('button')!.getBoundingClientRect();
    return b.top >= o.top && b.bottom <= o.bottom + 1;
  });
  expect(await inView(), 'button in view at the top of the text').toBe(true);
  await warning.evaluate((el) => { el.scrollTop = el.scrollHeight; });
  expect(await inView(), 'button in view at the end of the text').toBe(true);
});

test('The band follows text that grows inside an overlay of the same size', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await load(page, `<div class="media" style="inline-size: 843px; block-size: 562px">${overlay(7)}</div>`);
  const warning = page.locator('.mukurtu-content-warnings');
  await expect(warning).not.toHaveClass(/mukurtu-content-warnings--scrolls/);
  // As when a web font swaps in, or text-only zoom: the overlay keeps its size.
  await page.locator('.mukurtu-content-warnings__list').evaluate((el) => { el.style.fontSize = '5rem'; });
  await expect(warning).toHaveClass(/mukurtu-content-warnings--scrolls/);
});

test('Warnings that fit have no scrolling band', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await load(page, `<div class="media" style="inline-size: 843px; block-size: 562px">${overlay(5)}</div>`);
  await expect(page.locator('.mukurtu-content-warnings')).not.toHaveClass(/mukurtu-content-warnings--scrolls/);
});

test('Thumbnails keep the scrim but have no Tab stop', async ({ page }) => {
  await load(page, THUMBNAIL);
  await expect(page.locator('.mukurtu-content-warnings')).toBeVisible();
  await expect(page.getByRole('button', { name: 'Show media' })).toBeHidden();
  await expect(page.getByText(WARNINGS[0])).toBeHidden();
});
