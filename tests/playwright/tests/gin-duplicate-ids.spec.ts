import { test, expect, Page } from '@playwright/test';
import { Login } from '~components/login';
import { adminAccount } from '~helpers/a11y-credentials';

/**
 * Gin's toolbars and sticky table headers must not repeat ids (#2397).
 *
 * Gin rendered the toolbar's tabs and trays up to three times (main toolbar,
 * secondary toolbar, and a hidden stub), gave its sidebar menu items an empty
 * id, pointed the logo menu's aria-labelledby at an id that didn't exist, and
 * copied each table's <th> ids into the sticky header. Fixed by
 * patches/gin-duplicate-ids-2397.patch and mukurtu_gin_custom's
 * hook_preprocess_toolbar().
 *
 * Only the toolbar and sticky header markup is checked, so unrelated
 * duplicates elsewhere on a page don't fail this test.
 */
const SCOPES = [
  '#toolbar-administration',
  '#toolbar-administration-secondary',
  '.admin-toolbar',
  '.gin--sticky-table-header',
].join(', ');

async function findProblems(page: Page) {
  return page.evaluate((scopes) => {
    const counts: Record<string, number> = {};
    document.querySelectorAll('[id]').forEach((el) => {
      counts[el.id] = (counts[el.id] || 0) + 1;
    });
    const scoped = [...document.querySelectorAll(scopes)];
    const inScope = (el: Element) => scoped.some((root) => root === el || root.contains(el));

    const duplicates = new Set<string>();
    const broken = new Set<string>();
    document.querySelectorAll('[id]').forEach((el) => {
      if (inScope(el) && counts[el.id] !== 1) {
        duplicates.add(el.id === '' ? '(empty)' : el.id);
      }
    });
    document.querySelectorAll('[aria-labelledby]').forEach((el) => {
      if (!inScope(el)) {
        return;
      }
      for (const id of (el.getAttribute('aria-labelledby') || '').split(/\s+/)) {
        if (id && counts[id] !== 1) {
          broken.add(id);
        }
      }
    });
    return { duplicates: [...duplicates], broken: [...broken] };
  }, SCOPES);
}

test.describe('Gin duplicate ids', () => {
  test.beforeEach(async ({ page }) => {
    const account = adminAccount();
    const login = new Login(page);
    await login.login(account.username, account.password);
  });

  for (const path of ['/admin/content', '/']) {
    test(`toolbar and sticky header ids are unique on ${path}`, async ({ page }) => {
      const errors: string[] = [];
      page.on('pageerror', (error) => errors.push(error.message));

      await page.goto(path);
      await page.waitForLoadState('networkidle');

      const problems = await findProblems(page);
      expect(problems.duplicates, 'Duplicate ids in the toolbar or sticky header.').toEqual([]);
      expect(problems.broken, 'aria-labelledby points to a missing or duplicated id.').toEqual([]);
      expect(errors, 'JavaScript errors on the page.').toEqual([]);
    });
  }

  test('secondary toolbar trays still open from the keyboard', async ({ page }) => {
    await page.goto('/admin/content');
    const link = page.locator('#toolbar-administration-secondary #toolbar-item-shortcuts-secondary');
    await expect(link).toHaveAttribute('data-toolbar-tray', 'toolbar-item-shortcuts-tray');

    await link.focus();
    await expect(page.locator('#toolbar-item-shortcuts-tray-secondary')).toBeVisible();
  });

  test('secondary toolbar tabs without a tray have no empty navigation landmark', async ({ page }) => {
    await page.goto('/admin/content');
    const emptyLandmarks = await page.$$eval('#toolbar-administration-secondary .toolbar-tab', (tabs) => tabs
      .filter((tab) => !tab.querySelector('.toolbar-tray') && tab.querySelector('[role="navigation"], nav'))
      .map((tab) => tab.className));
    expect(emptyLandmarks).toEqual([]);
  });

  test('Escape closes a secondary toolbar tray opened from the keyboard', async ({ page }) => {
    await page.goto('/admin/content');
    await page.mouse.move(0, 0);
    const link = page.locator('#toolbar-item-shortcuts-secondary');
    const tray = page.locator('#toolbar-item-shortcuts-tray-secondary');

    await link.focus();
    await expect(tray).toBeVisible();
    await page.keyboard.press('Tab');
    await page.keyboard.press('Escape');
    await expect(tray).toBeHidden();
    await expect(link).toBeFocused();

    // Leaving and coming back opens it again.
    await page.keyboard.press('Shift+Tab');
    await page.keyboard.press('Tab');
    await expect(tray).toBeVisible();
  });

  test('Escape closes a secondary toolbar tray opened by hover', async ({ page }) => {
    await page.goto('/admin/content');
    const tab = page.locator('#toolbar-administration-secondary .toolbar-tab--toolbar-item-shortcuts');
    const tray = page.locator('#toolbar-item-shortcuts-tray-secondary');

    await tab.hover();
    await expect(tray).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(tray).toBeHidden();

    await page.mouse.move(0, 0);
    await tab.hover();
    await expect(tray).toBeVisible();
  });

  test('select-all checkboxes are named with aria-label', async ({ page }) => {
    await page.goto('/admin/content');
    const checkboxes = page.locator('th.select-all input[type="checkbox"]');
    await expect(checkboxes.first()).toBeAttached();
    for (const checkbox of await checkboxes.all()) {
      await expect(checkbox).toHaveAttribute('aria-label', /rows in this table/);
    }
  });
});
