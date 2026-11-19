import { test, expect, Page } from '@playwright/test';
import { ADMIN_STATE } from '~helpers/auth-state';

/**
 * Klaro's consent settings button floats over the bottom right of every
 * page. Tabbing scrolls a focused element only just into view, which left a
 * small control at the bottom right completely under the button (WCAG
 * 2.4.11, #2712). The theme and mukurtu_gin_custom stop keyboard scrolling
 * short of it with scroll-padding.
 *
 * The test adds a link at the right edge, on screen but behind the button,
 * and tabs to it from a link at the top. A browser only scrolls a focused
 * element that is not already in view, and scroll-padding takes the strip
 * behind the button out of view.
 */
async function tabToCornerLink(page: Page, path: string) {
  await page.setViewportSize({ width: 320, height: 640 });
  // Smooth scrolling would still be moving when the link is measured.
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await page.goto(path);

  const toggle = page.locator('#klaro_toggle_dialog');
  // Klaro adds the button from JS, and only when the toggle is turned on.
  await toggle.waitFor({ timeout: 10000 }).catch(() => {});
  test.skip(await toggle.count() === 0, 'Klaro toggle button is turned off on this site.');

  await page.evaluate(() => {
    const before = Object.assign(document.createElement('a'), { id: 'pw-before', href: '#', textContent: 'Before' });
    const spacer = document.createElement('div');
    const target = Object.assign(document.createElement('a'), { id: 'pw-target', href: '#', textContent: 'Target' });
    target.style.cssText = 'display: block; inline-size: 60px; margin-inline-start: auto';
    const after = Object.assign(document.createElement('div'), { style: 'block-size: 2000px' });
    document.querySelector('main')!.prepend(before, spacer, target, after);
    before.focus({ preventScroll: true });
    // Starts 50px above the bottom of the screen, behind the button.
    spacer.style.blockSize = `${innerHeight - 50 - target.getBoundingClientRect().top}px`;
  });
  await page.keyboard.press('Tab');
  const target = page.locator('#pw-target');
  await expect(target).toBeFocused();

  const [link, button] = await Promise.all([target.boundingBox(), toggle.boundingBox()]);
  const overlaps = link!.x < button!.x + button!.width && button!.x < link!.x + link!.width
    && link!.y < button!.y + button!.height && button!.y < link!.y + link!.height;
  expect(overlaps, `link ${JSON.stringify(link)} under button ${JSON.stringify(button)}`).toBe(false);
}

test('Klaro toggle button does not cover a focused link on a public page', async ({ page }) => {
  await tabToCornerLink(page, '/');
});

test.describe('Gin', () => {
  test.use({ storageState: ADMIN_STATE });

  test('Klaro toggle button does not cover a focused link on an admin page', async ({ page }) => {
    await tabToCornerLink(page, '/admin/config/system/site-information');
  });
});
