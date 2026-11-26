import { test, expect, Page, Route } from '@playwright/test';
import fs = require('fs');
import path = require('path');

/**
 * Issue #336: the 3D Model media type's viewer.
 *
 * Markup mirroring media--3d-model--media-assets.html.twig, with the real
 * vendored model-viewer, GLightbox and the real compiled theme CSS and JS.
 * The page is served from a made-up origin, so the viewer can fetch the
 * model; every request to anywhere else is recorded and refused. The model
 * is fixtures/test-model.glb (see fixtures/make-test-model.py).
 */

const THEME_DIR = path.join(__dirname, '../../../themes/mukurtu_v4');
const MEDIA_DIR = path.join(__dirname, '../../../modules/mukurtu_media');
const MODEL = path.join(__dirname, '../fixtures/test-model.glb');
const ORIGIN = 'https://fixture.test';

// Headless Chromium only offers WebGL through SwiftShader, and only when
// asked for it.
// It renders on the CPU. This file's tests run one after another in one
// worker, rather than all at once, and with other files' workers running a
// click can still take longer than the suite's 5 second action timeout.
test.describe.configure({ mode: 'default' });
test.use({
  launchOptions: { args: ['--use-gl=angle', '--use-angle=swiftshader', '--enable-unsafe-swiftshader'] },
  actionTimeout: 20000,
});

const A11Y = JSON.stringify({
  front: 'View from the front',
  right: 'View from the right',
  'upper-front': 'View from above the front',
  'interaction-prompt': 'Use a mouse, touch, the arrow keys, or the buttons below to move the model.',
});

const VIEWER = `
  <div class="media-3d-model">
    <model-viewer class="media-3d-model__viewer nodrag" src="/model.glb" alt="Test box"
      a11y='${A11Y}' camera-controls touch-action="pan-y" interaction-prompt="none"
      loading="lazy" ar ar-modes="webxr quick-look">
      <button type="button" slot="ar-button" class="button media-3d-model__ar">View in your space</button>
    </model-viewer>
    <p class="media-3d-model__unsupported" hidden>This 3D model can't be displayed in your browser.</p>
    <div class="media-3d-model__controls" role="group" aria-label="3D model controls" hidden>
      <div class="media-3d-model__controls-row">
        <button type="button" class="button" data-model-viewer-action="rotate-left">Rotate left</button>
        <button type="button" class="button" data-model-viewer-action="rotate-right">Rotate right</button>
        <button type="button" class="button" data-model-viewer-action="rotate-up">Rotate up</button>
        <button type="button" class="button" data-model-viewer-action="rotate-down">Rotate down</button>
      </div>
      <div class="media-3d-model__controls-row">
        <button type="button" class="button" data-model-viewer-action="zoom-in">Zoom in</button>
        <button type="button" class="button" data-model-viewer-action="zoom-out">Zoom out</button>
        <button type="button" class="button" data-model-viewer-action="reset">Reset view</button>
      </div>
      <div class="media-3d-model__status visually-hidden" aria-live="polite"></div>
    </div>
  </div>`;

const PAGE = `<!DOCTYPE html>
<html lang="en"><head><title>3D model fixture</title>
<link rel="stylesheet" href="/theme/libraries/glightbox/glightbox.min.css">
<link rel="stylesheet" href="/theme/css/style.css">
</head><body><main style="inline-size: 843px">
  <div class="media-asset--slide">
    <div class="media media--3d_model">
      <div class="media-asset--content">${VIEWER}</div>
      <div id="model-3d-1" class="media-asset--glightbox-inline">
        <div class="media media--3d-model">${VIEWER}</div>
      </div>
    </div>
    <div class="media-asset--actions">
      <a href="#model-3d-1" data-type="inline" class="media-asset--link button">Expand +<span class="visually-hidden"> 3D model: Test box</span></a>
    </div>
  </div>
</main>
<script>
  window.Drupal = { behaviors: {} };
  window.drupalSettings = { mukurtuModelViewer: {
    dracoDecoderLocation: '/theme/libraries/model-viewer/draco/',
    ktx2TranscoderLocation: '/theme/libraries/model-viewer/basis/'
  } };
  window.once = function (id, selector, context) {
    var attr = 'data-once-' + id;
    var root = context || document;
    var els = typeof selector === 'string'
      ? Array.prototype.slice.call((root.querySelectorAll ? root : document).querySelectorAll(selector))
      : [selector];
    if (typeof selector === 'string' && root.matches && root.matches(selector)) { els.unshift(root); }
    els = els.filter(function (el) { return !el.hasAttribute(attr); });
    els.forEach(function (el) { el.setAttribute(attr, ''); });
    return els;
  };
</script>
<script src="/theme/js/media-3d-model.js"></script>
<script type="module" src="/theme/libraries/model-viewer/model-viewer.min.js"></script>
<script src="/theme/libraries/glightbox/glightbox.min.js"></script>
<script src="/theme/js/media-stop.js"></script>
<script src="/theme/js/media-asset-glightbox.js"></script>
<script>
  Drupal.behaviors.media3dModel.attach(document);
  Drupal.behaviors.mediaAssetGLightbox.attach(document);
</script>
</body></html>`;

// A second media asset, so the lightbox has a next slide (/two).
// Mirrors media--3d-model.html.twig: a model embedded in body text, in
// each embed size, with no lightbox (/embed).
const EMBEDS = ['small', 'medium', 'large'].map((size) => `
  <div class="media media--3d_model media--3d-model-embed media--3d-model-embed--${size}" data-size="${size}">${VIEWER}</div>`).join('');

// The same, with a caption: the figure the caption filter wraps it in
// (/embed-captioned).
const CAPTIONED = ['small', 'medium', 'large'].map((size) => `
  <figure role="group" class="align-${size === 'medium' ? 'right' : 'left'}" data-size="${size}">
    <div class="media media--3d_model media--3d-model-embed media--3d-model-embed--${size}">${VIEWER}</div>
    <figcaption>A caption for the ${size} model</figcaption>
  </figure>`).join('');

const SECOND = `
  <div class="media-asset--slide">
    <div id="second-1" class="media-asset--glightbox-inline"><div class="media"><p>Second slide</p></div></div>
    <div class="media-asset--actions">
      <a href="#second-1" data-type="inline" class="media-asset--link button">Expand second</a>
    </div>
  </div>`;

// Mirrors mukurtu-3d-model-capture.html.twig: the viewer on a 3D Model add
// or edit form, for capturing the thumbnail (#2809), with the form's hidden
// field (/capture). Drupal.t and once.remove stand in for core's.
const CAPTURE = `
  <form>
    <input type="hidden" name="thumbnail_capture" value="" data-mukurtu-3d-capture-value>
    <label>Thumbnail <input type="file" name="files[field_thumbnail_0]"></label>
    <div class="mukurtu-3d-capture" data-mukurtu-3d-capture>${VIEWER}
      <div class="mukurtu-3d-capture__actions" hidden>
        <button type="button" class="button" data-mukurtu-3d-capture-button>Use this view as the thumbnail</button>
        <p class="mukurtu-3d-capture__status" role="status"></p>
        <img class="mukurtu-3d-capture__preview" alt="Captured thumbnail" hidden>
      </div>
    </div>
  </form>`;

const CAPTURE_SCRIPTS = `
<link rel="stylesheet" href="/media/css/media-admin.css">
<script>
  Drupal.t = function (s) { return s; };
  once.remove = function (id, selector, context) {
    var attr = 'data-once-' + id;
    return Array.prototype.slice.call(context.querySelectorAll(selector)).filter(function (el) {
      var had = el.hasAttribute(attr);
      el.removeAttribute(attr);
      return had;
    });
  };
</script>
<script src="/media/js/3d-model-thumbnail-capture.js"></script>
<script>Drupal.behaviors.mukurtu3dModelThumbnailCapture.attach(document);</script>
</body>`;

function capturePage(value = ''): string {
  const form = CAPTURE.replace('value=""', `value="${value}"`);
  return PAGE.slice(0, PAGE.indexOf('<main')) + `<main style="inline-size: 640px">${form}</main>` + PAGE.slice(PAGE.indexOf('</main>') + '</main>'.length).replace('</body>', CAPTURE_SCRIPTS);
}

// A 2x1 PNG, as a capture kept when the form is shown again.
const KEPT = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAIAAAABCAYAAAD0In+KAAAAEUlEQVR42mP8z8DwnwEIGAEAD/4C/vZkG/MAAAAASUVORK5CYII=';

const TYPES: Record<string, string> = {
  '.js': 'text/javascript',
  '.css': 'text/css',
  '.wasm': 'application/wasm',
  '.map': 'application/json',
};

async function serve(page: Page): Promise<string[]> {
  const outside: string[] = [];
  await page.route('**/*', async (route: Route) => {
    const url = new URL(route.request().url());
    if (url.origin !== ORIGIN) {
      outside.push(url.href);
      return route.abort();
    }
    if (url.pathname === '/') {
      return route.fulfill({ contentType: 'text/html', body: PAGE });
    }
    if (url.pathname === '/embed' || url.pathname === '/embed-captioned') {
      const embeds = url.pathname === '/embed' ? EMBEDS : CAPTIONED;
      const body = PAGE.slice(0, PAGE.indexOf('<main')) + `<main style="inline-size: 1200px">${embeds}</main>` + PAGE.slice(PAGE.indexOf('</main>') + '</main>'.length);
      return route.fulfill({ contentType: 'text/html', body });
    }
    if (url.pathname === '/capture' || url.pathname === '/capture-kept') {
      return route.fulfill({ contentType: 'text/html', body: capturePage(url.pathname === '/capture-kept' ? KEPT : '') });
    }
    if (url.pathname.startsWith('/media/')) {
      const file = path.join(MEDIA_DIR, url.pathname.slice('/media/'.length));
      if (fs.existsSync(file)) {
        return route.fulfill({ contentType: TYPES[path.extname(file)] ?? 'application/octet-stream', body: fs.readFileSync(file) });
      }
    }
    if (url.pathname === '/two') {
      return route.fulfill({ contentType: 'text/html', body: PAGE.replace('</main>', SECOND + '</main>') });
    }
    if (url.pathname === '/model.glb') {
      return route.fulfill({ contentType: 'model/gltf-binary', body: fs.readFileSync(MODEL) });
    }
    if (url.pathname.startsWith('/theme/')) {
      const file = path.join(THEME_DIR, url.pathname.slice('/theme/'.length));
      if (fs.existsSync(file)) {
        return route.fulfill({ contentType: TYPES[path.extname(file)] ?? 'application/octet-stream', body: fs.readFileSync(file) });
      }
    }
    return route.fulfill({ status: 404, body: '' });
  });
  return outside;
}

async function load(page: Page, selector = '.media-asset--content model-viewer') {
  if (!selector.startsWith('.glightbox-container')) {
    await page.goto(ORIGIN + '/');
  }
  await page.locator(selector).scrollIntoViewIfNeeded();
  await page.waitForFunction((s) => (document.querySelector(s) as any)?.loaded === true, selector, { timeout: 20000 });
}

function orbit(page: Page, selector = '.media-asset--content model-viewer') {
  return page.evaluate((s) => {
    const viewer = document.querySelector(s) as any;
    const o = viewer.getCameraOrbit();
    return { theta: o.theta, phi: o.phi, radius: o.radius };
  }, selector);
}

async function active(page: Page): Promise<string> {
  return page.evaluate(() => {
    const el = document.activeElement as HTMLElement;
    if (el.classList.contains('gclose')) return 'Close';
    if (el.matches('model-viewer')) return 'VIEWER';
    return `${el.tagName} ${el.textContent!.trim()}`;
  });
}

test('The model loads from the site, with no request anywhere else', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  const outside = await serve(page);
  await load(page);

  await expect(page.locator('.media-asset--content .media-3d-model__controls')).toBeVisible();
  const box = (await page.locator('.media-asset--content model-viewer').boundingBox())!;
  expect(box.width / box.height).toBeCloseTo(16 / 9, 1);

  // The translated instructions reach the viewer's accessible name.
  const label = await page.evaluate(() => (document.querySelector('.media-asset--content model-viewer') as any)
    .shadowRoot.querySelector('.userInput').getAttribute('aria-label'));
  expect(label).toBe('Test box. Use a mouse, touch, the arrow keys, or the buttons below to move the model.');

  expect(outside).toEqual([]);
});

test('Each control button moves the camera, and Reset view puts it back (WCAG 2.5.7)', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await serve(page);
  await load(page);
  const controls = page.locator('.media-asset--content .media-3d-model__controls');
  const start = await orbit(page);

  await controls.getByRole('button', { name: 'Rotate left' }).click();
  await expect.poll(async () => (await orbit(page)).theta).toBeGreaterThan(start.theta + 0.1);

  await controls.getByRole('button', { name: 'Rotate right' }).click();
  await controls.getByRole('button', { name: 'Rotate right' }).click();
  await expect.poll(async () => (await orbit(page)).theta).toBeLessThan(start.theta - 0.1);

  // Up and down match the Up and Down Arrow keys: Up tips the model's front
  // upward, which moves the camera down.
  await controls.getByRole('button', { name: 'Rotate up' }).click();
  await expect.poll(async () => (await orbit(page)).phi).toBeGreaterThan(start.phi + 0.1);

  await controls.getByRole('button', { name: 'Rotate down' }).click();
  await controls.getByRole('button', { name: 'Rotate down' }).click();
  await expect.poll(async () => (await orbit(page)).phi).toBeLessThan(start.phi - 0.1);

  // How much of the scene is in view: zooming moves the camera in, then
  // narrows its field of view, and either shrinks this.
  const view = () => page.evaluate(() => {
    const viewer = document.querySelector('.media-asset--content model-viewer') as any;
    return viewer.getCameraOrbit().radius * Math.tan((viewer.getFieldOfView() * Math.PI) / 360);
  });
  const startView = await view();
  await controls.getByRole('button', { name: 'Zoom in' }).click();
  await expect.poll(view).toBeLessThan(startView * 0.98);
  const zoomedView = await view();
  await controls.getByRole('button', { name: 'Zoom out' }).click();
  await expect.poll(view).toBeGreaterThan(zoomedView * 1.02);

  await controls.getByRole('button', { name: 'Reset view' }).click();
  await expect.poll(async () => (await orbit(page)).theta).toBeCloseTo(start.theta, 2);
  await expect.poll(async () => (await orbit(page)).phi).toBeCloseTo(start.phi, 2);

  // Quick presses add up: three steps, not one step started three times.
  const step = Math.PI / 8;
  const rotateLeft = controls.getByRole('button', { name: 'Rotate left' });
  await rotateLeft.click();
  await rotateLeft.click();
  await rotateLeft.click();
  await expect.poll(async () => (await orbit(page)).theta).toBeCloseTo(start.theta + 3 * step, 1);
});

test('In the lightbox, Tab goes from the viewer through the controls to Close (WCAG 2.1.1)', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await serve(page);
  await page.goto(ORIGIN + '/');
  await page.waitForTimeout(250);
  await page.locator('a.media-asset--link').click();
  await load(page, '.glightbox-container model-viewer');

  await page.locator('.gclose').focus();
  const order: string[] = [];
  for (let i = 0; i < 9; i++) {
    await page.keyboard.press('Tab');
    order.push(await active(page));
  }
  expect(order).toEqual([
    'VIEWER',
    'BUTTON Rotate left',
    'BUTTON Rotate right',
    'BUTTON Rotate up',
    'BUTTON Rotate down',
    'BUTTON Zoom in',
    'BUTTON Zoom out',
    'BUTTON Reset view',
    'Close',
  ]);

  // The buttons' ring is white against the dark backdrop (WCAG 1.4.11).
  // Focus is on Close, so two presses: the viewer, then Rotate left.
  await page.keyboard.press('Tab');
  await page.keyboard.press('Tab');
  expect(await active(page)).toBe('BUTTON Rotate left');
  await expect(page.locator('.glightbox-container [data-model-viewer-action="rotate-left"]')).toHaveCSS('outline-color', 'rgb(255, 255, 255)');

  // With the viewer focused, the arrow keys turn the model. The lightbox
  // moves focus with a script, which still shows the keyboard ring.
  await page.locator('.gclose').focus();
  await page.keyboard.press('Tab');
  await expect(page.locator('.glightbox-container model-viewer')).toHaveClass(/media-3d-model__viewer--focus-visible/);
  const selector = '.glightbox-container model-viewer';
  const before = (await orbit(page, selector)).theta;
  await page.keyboard.press('ArrowLeft');
  await expect.poll(async () => (await orbit(page, selector)).theta).toBeGreaterThan(before + 0.1);
});

test('The rotate and reset buttons announce the view they leave (WCAG 4.1.3)', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await serve(page);
  await load(page);
  const controls = page.locator('.media-asset--content .media-3d-model__controls');
  const status = page.locator('.media-asset--content .media-3d-model__status');
  await expect(status).toHaveAttribute('aria-live', 'polite');

  const rotateLeft = controls.getByRole('button', { name: 'Rotate left' });
  await rotateLeft.click();
  await rotateLeft.click();
  await rotateLeft.click();
  await expect(status).toHaveText('View from the right');

  await controls.getByRole('button', { name: 'Reset view' }).click();
  await expect(status).toHaveText('View from the front');
});

test('Keyboard focus on the viewer shows an inset ring, and a click shows none (WCAG 2.4.7)', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await serve(page);
  await load(page);
  const viewer = page.locator('.media-asset--content model-viewer');

  await viewer.click();
  await expect(viewer).not.toHaveClass(/media-3d-model__viewer--focus-visible/);
  await expect(viewer).toHaveCSS('outline-style', 'none');

  await page.locator('.media-asset--content .media-3d-model__controls button').first().focus();
  await page.keyboard.press('Shift+Tab');
  expect(await active(page)).toBe('VIEWER');
  await expect(viewer).toHaveClass(/media-3d-model__viewer--focus-visible/);
  await expect(viewer).toHaveCSS('outline-width', '3px');
  await expect(viewer).toHaveCSS('outline-offset', '-3px');

  await page.keyboard.press('Tab');
  await expect(viewer).not.toHaveClass(/media-3d-model__viewer--focus-visible/);
});

test('In a lightbox with more slides, Left and Right turn the model and stay on its slide (WCAG 2.1.1)', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await serve(page);
  await page.goto(ORIGIN + '/two');
  await page.waitForTimeout(250);
  await page.locator('a.media-asset--link').first().click();
  const selector = '.glightbox-container model-viewer';
  await load(page, selector);

  await page.locator('.gclose').focus();
  await page.keyboard.press('Tab');
  expect(await active(page)).toBe('VIEWER');
  const before = (await orbit(page, selector)).theta;
  // One press: with two slides and looping, a second would come back.
  await page.keyboard.press('ArrowRight');
  await expect.poll(async () => (await orbit(page, selector)).theta).toBeLessThan(before - 0.2);
  // GLightbox changes slides after an animation, so give it the time.
  await page.waitForTimeout(1000);
  const current = await page.evaluate(() => [...document.querySelectorAll('.glightbox-container .gslide')]
    .findIndex((slide) => slide.classList.contains('current')));
  expect(current).toBe(0);
  expect(await active(page)).toBe('VIEWER');
});

test('Dragging the model in the lightbox turns it, and leaves the slide where it is', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await serve(page);
  await page.goto(ORIGIN + '/');
  await page.waitForTimeout(250);
  await page.locator('a.media-asset--link').click();
  const selector = '.glightbox-container model-viewer';
  await load(page, selector);

  // GLightbox moves the slide's inner container, so measure something in it.
  const slide = page.locator('.glightbox-container .media-3d-model__controls');
  const slideBefore = (await slide.boundingBox())!;
  const box = (await page.locator(selector).boundingBox())!;
  const before = (await orbit(page, selector)).theta;
  await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
  await page.mouse.down();
  await page.mouse.move(box.x + box.width / 2 + 250, box.y + box.height / 2, { steps: 10 });
  // Still held: GLightbox moves the slide with the pointer while it drags.
  const slideDuring = (await slide.boundingBox())!;
  await page.mouse.up();

  expect(Math.abs(slideDuring.x - slideBefore.x)).toBeLessThan(1);
  await expect(page.locator('.glightbox-container')).toBeVisible();
  await expect.poll(async () => Math.abs((await orbit(page, selector)).theta - before)).toBeGreaterThan(0.2);
});

for (const [width, height, ratio] of [[1440, 900, 16 / 9], [768, 1024, 1], [390, 844, 1]]) {
  test(`In the lightbox the viewer and its controls fit at ${width}x${height}`, async ({ page }) => {
    await page.setViewportSize({ width, height });
    await serve(page);
    await page.goto(ORIGIN + '/');
    await page.waitForTimeout(250);
    await page.locator('a.media-asset--link').click();
    await load(page, '.glightbox-container model-viewer');

    await expect(async () => {
      const fit = await page.evaluate(() => {
        const viewer = document.querySelector('.glightbox-container model-viewer')!.getBoundingClientRect();
        const slide = document.querySelector('.glightbox-container .ginlined-content')!.getBoundingClientRect();
        const controls = document.querySelector('.glightbox-container .media-3d-model__controls')!.getBoundingClientRect();
        return {
          ratio: viewer.width / viewer.height,
          inside: viewer.left >= slide.left - 0.5 && viewer.right <= slide.right + 0.5,
          controlsOnScreen: controls.bottom <= window.innerHeight,
        };
      });
      expect(fit.ratio).toBeCloseTo(ratio, 1);
      expect(fit.inside).toBe(true);
      expect(fit.controlsOnScreen).toBe(true);
    }).toPass({ timeout: 3000 });
  });
}

test('Embedded in body text, the viewer works at each size, with no lightbox (#2810)', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await serve(page);
  await page.goto(ORIGIN + '/embed');
  await expect(page.locator('a.media-asset--link')).toHaveCount(0);

  for (const [size, max] of [['small', 320], ['medium', 480], ['large', 900]] as const) {
    const embed = page.locator(`[data-size="${size}"]`);
    const viewer = embed.locator('model-viewer');
    await viewer.scrollIntoViewIfNeeded();
    await page.waitForFunction((s) => (document.querySelector(`[data-size="${s}"] model-viewer`) as any)?.loaded === true, size, { timeout: 20000 });
    await expect(embed.locator('.media-3d-model__controls')).toBeVisible();

    const box = (await viewer.boundingBox())!;
    expect(box.width).toBeCloseTo(max, 0);
    expect(box.width / box.height).toBeCloseTo(16 / 9, 1);
  }

  // Narrow embeds lay their buttons out two to a line, by the viewer's own
  // width rather than the screen's: Rotate up sits below Rotate left.
  const small = page.locator('[data-size="small"] .media-3d-model__controls');
  const left = (await small.getByRole('button', { name: 'Rotate left' }).boundingBox())!;
  const up = (await small.getByRole('button', { name: 'Rotate up' }).boundingBox())!;
  expect(up.y).toBeGreaterThan(left.y + left.height - 1);
  const large = page.locator('[data-size="large"] .media-3d-model__controls');
  const largeLeft = (await large.getByRole('button', { name: 'Rotate left' }).boundingBox())!;
  const largeDown = (await large.getByRole('button', { name: 'Rotate down' }).boundingBox())!;
  expect(largeDown.y).toBeCloseTo(largeLeft.y, 0);
});

test('With a caption, an embedded viewer keeps its size and its buttons (#2810)', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await serve(page);
  await page.goto(ORIGIN + '/embed-captioned');

  for (const [size, max] of [['small', 320], ['medium', 480], ['large', 900]] as const) {
    const figure = page.locator(`figure[data-size="${size}"]`);
    const viewer = figure.locator('model-viewer');
    await viewer.scrollIntoViewIfNeeded();
    await page.waitForFunction((s) => (document.querySelector(`figure[data-size="${s}"] model-viewer`) as any)?.loaded === true, size, { timeout: 20000 });

    // The figure takes the embed's width, rather than shrinking around a
    // viewer that gives it none, so the buttons and caption stay inside.
    const box = (await figure.boundingBox())!;
    expect(box.width).toBeCloseTo(max, 0);
    expect((await viewer.boundingBox())!.width).toBeCloseTo(max, 0);
    for (const part of [figure.locator('.media-3d-model__controls'), figure.locator('figcaption')]) {
      const inner = (await part.boundingBox())!;
      expect(inner.x).toBeGreaterThanOrEqual(box.x - 1);
      expect(inner.x + inner.width).toBeLessThanOrEqual(box.x + box.width + 1);
    }
  }
});

test('Without WebGL, a message replaces the viewer and its controls', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.addInitScript(() => {
    const original = HTMLCanvasElement.prototype.getContext;
    HTMLCanvasElement.prototype.getContext = function (type: string, ...rest: any[]) {
      return /webgl/.test(type) ? null : (original as any).call(this, type, ...rest);
    } as any;
  });
  await serve(page);
  await page.goto(ORIGIN + '/');

  const inline = page.locator('.media-asset--content');
  await expect(inline.locator('.media-3d-model__unsupported')).toBeVisible();
  await expect(inline.locator('.media-3d-model__controls')).toBeHidden();
  await expect(inline.locator('model-viewer')).toBeHidden();
});

test('On the add and edit forms, the current view can be captured as the thumbnail, by keyboard (#2809)', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  const outside = await serve(page);
  await page.goto(ORIGIN + '/capture');
  const button = page.getByRole('button', { name: 'Use this view as the thumbnail' });
  await expect(button).toBeVisible({ timeout: 20000 });

  // model-viewer lowers its resolution on a slow device. A slower CPU and
  // a few seconds of turning get it there.
  const cdp = await page.context().newCDPSession(page);
  await cdp.send('Emulation.setCPUThrottlingRate', { rate: 20 });
  for (let i = 0; i < 6; i++) {
    await page.getByRole('button', { name: 'Rotate right' }).click();
    await page.waitForTimeout(500);
  }
  await button.focus();
  await page.keyboard.press('Enter');

  const preview = page.getByRole('img', { name: 'Captured thumbnail' });
  await expect(preview).toBeVisible();
  // model-viewer has a status region of its own, in its shadow root.
  const status = page.locator('.mukurtu-3d-capture__status');
  await expect(status).toHaveAttribute('role', 'status');
  await expect(status).toHaveText('The thumbnail will be this view when you save.');
  const value = await page.locator('[data-mukurtu-3d-capture-value]').inputValue();
  expect(value).toMatch(/^data:image\/png;base64,/);

  // The whole view, at full resolution, not the part a lower resolution
  // leaves.
  const sizes = await page.evaluate(() => {
    const viewer = document.querySelector('model-viewer')!;
    const img = document.querySelector('.mukurtu-3d-capture__preview') as HTMLImageElement;
    return { width: img.naturalWidth, height: img.naturalHeight, viewerWidth: viewer.clientWidth, viewerHeight: viewer.clientHeight };
  });
  expect(sizes.width).toBeGreaterThanOrEqual(sizes.viewerWidth - 1);
  expect(sizes.height).toBeGreaterThanOrEqual(sizes.viewerHeight - 1);

  expect(outside).toEqual([]);
});

test('A capture kept when the form is shown again is shown, and removing the model drops it (#2809)', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await serve(page);
  await page.goto(ORIGIN + '/capture-kept');
  await expect(page.getByRole('img', { name: 'Captured thumbnail' })).toBeVisible();
  await expect(page.locator('[data-mukurtu-3d-capture-value]')).toHaveValue(KEPT);

  // What Drupal does when the Remove button's response replaces the widget.
  await page.evaluate(() => (window as any).Drupal.behaviors.mukurtu3dModelThumbnailCapture.detach(document.querySelector('form'), {}, 'unload'));
  await expect(page.locator('[data-mukurtu-3d-capture-value]')).toHaveValue('');
});

test('A thumbnail uploaded after a capture replaces the capture (#2809)', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await serve(page);
  await page.goto(ORIGIN + '/capture-kept');
  await expect(page.getByRole('img', { name: 'Captured thumbnail' })).toBeVisible();

  await page.getByLabel('Thumbnail').setInputFiles({ name: 'basket.png', mimeType: 'image/png', buffer: Buffer.from(KEPT.split(',')[1], 'base64') });
  await expect(page.locator('[data-mukurtu-3d-capture-value]')).toHaveValue('');
  await expect(page.getByRole('img', { name: 'Captured thumbnail' })).toBeHidden();
  await expect(page.locator('.mukurtu-3d-capture__status')).toHaveText('');
});
