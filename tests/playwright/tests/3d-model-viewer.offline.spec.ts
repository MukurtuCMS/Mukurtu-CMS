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
const MODEL = path.join(__dirname, '../fixtures/test-model.glb');
const ORIGIN = 'https://fixture.test';

// Headless Chromium only offers WebGL through SwiftShader, and only when
// asked for it.
// It renders on the CPU, so with other workers running a click can take
// longer than the suite's 5 second action timeout.
test.use({
  launchOptions: { args: ['--use-gl=angle', '--use-angle=swiftshader', '--enable-unsafe-swiftshader'] },
  actionTimeout: 20000,
});

const A11Y = JSON.stringify({
  front: 'View from the front',
  'upper-front': 'View from above the front',
  'interaction-prompt': 'Use a mouse, touch, the arrow keys, or the buttons below to move the model.',
});

const VIEWER = `
  <div class="media-3d-model">
    <model-viewer class="media-3d-model__viewer" src="/model.glb" alt="Test box"
      a11y='${A11Y}' camera-controls touch-action="pan-y" interaction-prompt="none"
      loading="lazy" ar ar-modes="webxr quick-look">
      <button type="button" slot="ar-button" class="button media-3d-model__ar">View in your space</button>
    </model-viewer>
    <p class="media-3d-model__unsupported" hidden>This 3D model can't be displayed in your browser.</p>
    <div class="media-3d-model__controls" role="group" aria-label="3D model controls" hidden>
      <button type="button" class="button" data-model-viewer-action="rotate-left">Rotate left</button>
      <button type="button" class="button" data-model-viewer-action="rotate-right">Rotate right</button>
      <button type="button" class="button" data-model-viewer-action="rotate-up">Rotate up</button>
      <button type="button" class="button" data-model-viewer-action="rotate-down">Rotate down</button>
      <button type="button" class="button" data-model-viewer-action="zoom-in">Zoom in</button>
      <button type="button" class="button" data-model-viewer-action="zoom-out">Zoom out</button>
      <button type="button" class="button" data-model-viewer-action="reset">Reset view</button>
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

  // With the viewer focused, the arrow keys turn the model.
  await page.locator('.gclose').focus();
  await page.keyboard.press('Tab');
  const selector = '.glightbox-container model-viewer';
  const before = (await orbit(page, selector)).theta;
  await page.keyboard.press('ArrowLeft');
  await expect.poll(async () => (await orbit(page, selector)).theta).toBeGreaterThan(before + 0.1);
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
