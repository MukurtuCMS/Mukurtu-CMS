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

  // Every page from #2336. Four causes, all fixed: the shortcut tooltip, the
  // breadcrumb and the sticky form actions bar (modules/mukurtu_gin_custom),
  // and on the submission form settings both a fixed-width select
  // (modules/mukurtu_submissions) and visually-hidden labels escaping Gin's
  // table scroll wrapper.
  for (const path of [
    '/admin',
    '/admin/structure',
    '/admin/import',
    '/admin/config/mukurtu/character-palette',
    '/admin/config/mukurtu/content-warnings',
    '/admin/config/mukurtu/submissions',
    '/admin/import-templates/add',
    '/admin/content/media/bulk-upload/image',
    '/admin/content/pending-submissions',
    '/node/add/digital_heritage',
    '/node/add/dictionary_word',
    '/node/add/collection',
    '/admin/export/settings',
    '/admin/config/mukurtu/submissions/digital_heritage',
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

  // The 320px fix for the sticky form actions bar must not narrow it on wide
  // screens, where a first version made the "Save as" label wrap above its
  // select.
  for (const path of ['/node/add/digital_heritage', '/node/add/dictionary_word']) {
    test(`Reflow: the moderation state label stays beside its select at 1920px on ${path}`, async ({ page }) => {
      await page.setViewportSize({ width: 1920, height: 1080 });
      await page.goto(path);

      const select = page.locator('.gin-sticky-form-actions .field--name-moderation-state select');
      await expect(select).toBeVisible();
      const id = await select.getAttribute('id');
      const label = page.locator(`label[for="${id}"]`);
      await expect(label).toBeVisible();

      const labelBox = await label.boundingBox();
      const selectBox = await select.boundingBox();
      expect(labelBox!.y + labelBox!.height, 'label ends below the top of the select, so it is on the same row').toBeGreaterThan(selectBox!.y);
      expect(labelBox!.x + labelBox!.width, 'label is to the left of the select').toBeLessThanOrEqual(selectBox!.x + 1);
    });
  }

  // The tooltip supplies the link's accessible name, so any future fix that
  // hides it with display:none would trade 1.4.10 for 4.1.2. Pin that.
  test('Reflow: the shortcut tooltip keeps the link its accessible name', async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 720 });
    await page.goto('/admin/structure');
    await expect(page.getByRole('link', { name: /shortcuts/i }).first()).toBeAttached();
  });

  // A collapsed paragraph's buttons covered its type label at 320px (#2757).
  // Uses the Person form's Biography sections, which every site has.
  test('Reflow: collapsed paragraph buttons stay clear of the type label at 320px', async ({ page }) => {
    // Open the Biography tab at desktop width, where it is a link, then narrow.
    await page.goto('/node/add/person');
    const tab = page.locator('a[href="#edit-group-biography"]:visible').first();
    if (await tab.count()) {
      await tab.click();
    }
    await page.setViewportSize({ width: 320, height: 720 });
    const field = page.locator('.field--name-field-sections').first();
    await field.locator('input[name$="[subform][field_title][0][value]"]').first().fill('Childhood');
    const collapse = field.locator('input[name="field_sections_0_collapse"]');
    await collapse.focus();
    await page.keyboard.press('Enter');
    const expand = field.locator('input[name="field_sections_0_edit"]');
    await expect(expand).toBeVisible();

    const top = field.locator('.paragraph-top').first();
    const label = await top.locator('.paragraph-type-label').boundingBox();
    const buttons = await top.locator('.paragraphs-actions :is(input, button):visible').evaluateAll(
      (elements) => elements.map((element) => {
        const box = element.getBoundingClientRect();
        return { x: box.x, y: box.y, width: box.width, height: box.height };
      }),
    );
    expect(buttons.length).toBeGreaterThan(0);
    for (const button of buttons) {
      const overlaps = button.x < label!.x + label!.width && label!.x < button.x + button.width
        && button.y < label!.y + label!.height && label!.y < button.y + button.height;
      expect(overlaps, 'a button covers the paragraph type label').toBe(false);
    }
  });
});
