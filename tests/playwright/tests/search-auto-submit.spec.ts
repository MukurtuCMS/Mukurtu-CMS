import { test, expect } from '@playwright/test';
import { gotoReady } from '~helpers/preview';

/**
 * The search box on Browse, Digital Heritage and Dictionary searches on its
 * own only once three letters are typed, as the views are configured.
 *
 * These pages place their filters with Twig Tweak's drupal_block(), which
 * moves the form's attributes to a wrapper, so Better Exposed Filters lost the
 * minimum length and reloaded the page after one letter, taking focus out of
 * the box. "zzq" matches nothing, so no search suggestion holds the search.
 */

// Well past Better Exposed Filters' 500ms auto-submit delay.
const PAUSE = 1500;

for (const path of ['/browse', '/digital-heritage', '/dictionary']) {
  test(`${path} waits for three letters before searching`, async ({ page }) => {
    await gotoReady(page, path);
    const input = page.locator('form[id^="views-exposed-form-"]')
      .locator('input[name="search_api_fulltext"], input[name="keywords"]');
    let loads = 0;
    page.on('request', (request) => {
      if (request.isNavigationRequest() && request.frame() === page.mainFrame()) {
        loads += 1;
      }
    });

    await input.pressSequentially('zz');
    await page.waitForTimeout(PAUSE);
    expect(loads, 'The page reloaded before three letters were typed.').toBe(0);
    await expect(input).toBeFocused();
    await expect(input).toHaveValue('zz');

    await input.press('q');
    await expect.poll(() => loads, { timeout: 5000 }).toBe(1);
  });
}
