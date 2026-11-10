import { test, expect } from '@playwright/test';
import { ADMIN_STATE } from '~helpers/auth-state';

/**
 * The drawing toolbar on the Map Points widget (#864).
 *
 * Leaflet-Geoman titles its edit buttons "Edit Layers", "Drag Layers" and so
 * on, which means nothing to someone editing map points. modules/mukurtu_core's
 * mukurtu-leaflet-widget.js replaces the button titles through Geoman's
 * setLang(), and copies each title onto the focusable button as its
 * aria-label, so screen readers get the same name.
 */
test.describe('Map toolbar labels', () => {
  test.use({ storageState: ADMIN_STATE });

  test('Map toolbar labels: buttons say map points, not layers', async ({ page }) => {
    await page.goto('/node/add/digital_heritage');
    // The map is in the Locations group: a tab on wide screens, an
    // accordion on narrow ones.
    const tab = page.locator("a[href='#edit-group-additional-fields']");
    if (await tab.isVisible()) {
      await tab.click();
    }
    else {
      const closed = page.locator('details:not([open]):has(.leaflet-pm-toolbar) > summary');
      while (await closed.count()) {
        await closed.first().click();
      }
    }

    const toolbar = page.locator('.leaflet-pm-toolbar').first();
    await expect(toolbar).toBeVisible();

    for (const title of ['Draw marker', 'Draw polygon', 'Draw line', 'Edit map points', 'Move map points', 'Remove map points']) {
      const container = page.locator(`.button-container[title="${title}"]`).first();
      await expect(container, `toolbar button titled "${title}"`).toHaveCount(1);
      await expect(container.locator('a.leaflet-buttons-control-button')).toHaveAttribute('aria-label', title);
    }

    const titles = await page.locator('.leaflet-pm-toolbar .button-container[title]').evaluateAll(
      (els) => els.map((el) => el.getAttribute('title') ?? ''),
    );
    expect(titles.filter((t) => /layers/i.test(t)), 'no toolbar title still says "Layers"').toEqual([]);
  });
});
