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
      <ul id="${id}" class="mukurtu-content-warnings__list" role="list">
        ${WARNINGS.map((w) => `<li><div class="mukurtu-content-warning">${w}</div></li>`).join('')}
      </ul>
      <button type="button" class="mukurtu-content-warnings__dismiss" aria-describedby="${id}">Show media</button>
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

test('The dismiss button is described by every warning', async ({ page }) => {
  await load(page, IMAGE);
  const button = page.getByRole('button', { name: 'Show media' }).first();
  await expect(button).toHaveAccessibleDescription(new RegExp(`${WARNINGS[1].slice(0, 30)}.*${WARNINGS[2].slice(0, 30)}`));
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

// Mirrors a browse card: the media sits in a link hidden from AT, and the
// card's title link is the one keyboard users reach.
const CARD = `
  <a href="/node/1" aria-hidden="true" tabindex="-1">
    <div class="media" style="inline-size: 296px; block-size: 197px">${overlay(4)}</div>
  </a>
  <a href="/node/1">Card title</a>`;

test('A warning inside a card link adds no Tab stop', async ({ page }) => {
  await load(page, CARD);
  await page.keyboard.press('Tab');
  await expect(page.getByRole('link', { name: 'Card title' })).toBeFocused();
  await expect(page.locator('.mukurtu-content-warnings__dismiss')).toHaveAttribute('tabindex', '-1');
});

test('Thumbnails keep the scrim but have no Tab stop', async ({ page }) => {
  await load(page, THUMBNAIL);
  await expect(page.locator('.mukurtu-content-warnings')).toBeVisible();
  await expect(page.getByRole('button', { name: 'Show media' })).toBeHidden();
  await expect(page.getByText(WARNINGS[0])).toBeHidden();
});
