import { test, expect } from '@playwright/test';
import { ADMIN_STATE } from '~helpers/auth-state';

/**
 * WCAG 1.4.10 Reflow on admin routes (issue #2336).
 *
 * The inventory-wide check in accessibility-admin.spec.ts is report-only: it
 * cannot fail a build, so a regression in it is silent. This gates the admin
 * pages that actually pass, so they cannot quietly regress while the rest of
 * #2336 is still open.
 *
 * Only two pages are listed, deliberately. The other twelve in #2336 still
 * overflow from two further Gin causes (the sticky form actions on node add
 * forms, and the breadcrumb wrapper on settings forms). Add each page here as
 * its cause is fixed, rather than listing them now and skipping the failures.
 */
test.describe('Reflow (admin)', () => {
  test.use({ storageState: ADMIN_STATE });

  // Clean once the shortcut tooltip, the breadcrumb and the sticky form
  // actions bar are all fixed. See modules/mukurtu_gin_custom/css for each.
  // The one page still missing from this list, the submission form settings,
  // overflows from a wide table, which is a different problem.
  for (const path of [
    '/admin',
    '/admin/structure',
    '/admin/import',
    '/admin/config/mukurtu/content-warnings',
    '/admin/config/mukurtu/submissions',
    '/admin/import-templates/add',
    '/admin/content/media/bulk-upload/image',
    '/admin/content/pending-submissions',
    '/node/add/digital_heritage',
    '/node/add/dictionary_word',
    '/node/add/collection',
    '/admin/export/settings',
  ]) {
    test(`Reflow: no horizontal overflow at 320px on ${path}`, async ({ page }) => {
      await page.setViewportSize({ width: 320, height: 720 });
      await page.goto(path);

      // Guard against passing on a redirect to the login form, which would
      // make this test prove nothing about the admin page.
      await expect(page.locator('#block-gin-page-title, .page-title, h1').first()).toBeVisible();
      expect(page.url(), 'still on the admin page, not redirected to login').not.toContain('/user/login');

      const overflow = await page.evaluate(() => ({
        scrollWidth: document.documentElement.scrollWidth,
        clientWidth: document.documentElement.clientWidth,
      }));
      expect(overflow.scrollWidth).toBeLessThanOrEqual(overflow.clientWidth + 1);
    });
  }

  // The tooltip supplies the link's accessible name, so any future fix that
  // hides it with display:none would trade 1.4.10 for 4.1.2. Pin that.
  test('Reflow: the shortcut tooltip keeps the link its accessible name', async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 720 });
    await page.goto('/admin/structure');
    await expect(page.getByRole('link', { name: /shortcuts/i }).first()).toBeAttached();
  });
});
