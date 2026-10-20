import { test, expect } from '@playwright/test';
import { Login } from '~components/login';
import { adminAccount } from '~helpers/a11y-credentials';

/**
 * Gin's meta sidebar trigger must stay operable on node forms.
 *
 * The trigger is the only control that opens or closes the sidebar panel
 * holding Revision information, Menu settings, URL alias and the rest. Gin
 * gives it width: 32px, but it sits in .gin-sticky-form-actions, which is
 * display: flex, and it inherits flex-shrink: 1. On Mukurtu's node forms that
 * bar also carries the "Save as" state select beside Save and Preview, so
 * there is not enough room and the trigger is the item that gives way: it
 * measured 13px wide with the panel closed and 0px with it open.
 *
 * At those sizes it cannot be operated, and it is under the 24 by 24 minimum
 * in WCAG 2.2 2.5.8 Target Size. Fixed with flex-shrink: 0 in
 * mukurtu_gin_custom's stylesheet.
 *
 * Both states are checked because the defect was worse in the open one, and a
 * fix that only held while the panel was closed would leave no way to close
 * it again.
 */

/** WCAG 2.2 2.5.8 Target Size (Minimum). */
const MIN_TARGET = 24;

test.describe('Gin meta sidebar trigger', () => {
  test.beforeEach(async ({ page }) => {
    const account = adminAccount();
    const login = new Login(page);
    await login.login(account.username, account.password);
  });

  test('stays operable with the panel closed and open', async ({ page }) => {
    await page.goto('/node/add/dictionary_word');

    const trigger = page.locator('.meta-sidebar__trigger');
    await expect(trigger).toHaveCount(1);

    const box = async () => {
      const b = await trigger.boundingBox();
      expect(b, 'the trigger has a box to measure').not.toBeNull();
      return b!;
    };

    const initialState = await page.evaluate(() =>
      document.body.getAttribute('data-meta-sidebar'));

    const closed = await box();
    expect(closed.width,
      `trigger is ${Math.round(closed.width)}px wide with the panel `
      + `"${initialState}"; it is squeezed by the sticky action buttons`)
      .toBeGreaterThanOrEqual(MIN_TARGET);
    expect(closed.height).toBeGreaterThanOrEqual(MIN_TARGET);

    // No force: the point is that it can be clicked as it stands. A real
    // click also exercises the state the squeeze was worst in.
    await trigger.click();
    await expect
      .poll(() => page.evaluate(() => document.body.getAttribute('data-meta-sidebar')))
      .not.toBe(initialState);

    const toggled = await box();
    expect(toggled.width,
      `trigger collapsed to ${Math.round(toggled.width)}px after toggling, `
      + 'leaving no way to put the panel back')
      .toBeGreaterThanOrEqual(MIN_TARGET);
    expect(toggled.height).toBeGreaterThanOrEqual(MIN_TARGET);
  });
});
