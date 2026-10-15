import { test, expect } from '@playwright/test';
import { ADMIN_STATE } from '~helpers/auth-state';

/**
 * WCAG 2.4.7 Focus Visible on admin forms (issue #2389).
 *
 * Gin copies a form's action buttons into the sticky header and hides the
 * originals with the clip-rect kind of visually-hidden, which keeps them
 * focusable. Tab then stops on controls with none of their box on screen,
 * and on a confirm form the only Cancel it reaches is the invisible one.
 * modules/mukurtu_gin_custom adds visibility: hidden under Gin's own
 * selector to take them out of the tab order.
 *
 * This gates it. The inventory-wide checkFocusVisible is report-only and
 * asks a different question (whether a focused element draws an indicator);
 * it cannot catch a focusable element that is not drawn at all.
 */
test.describe('Focus visible (admin)', () => {
  test.use({ storageState: ADMIN_STATE });

  // Measures each focusable element while it holds focus, which is the only
  // fair way to ask this: a skip link is 0% visible until focused and fully
  // visible once it is, and must not be reported.
  const findInvisibleFocusables = () =>
    [
      ...document.querySelectorAll<HTMLElement>(
        'a[href], button, input:not([type="hidden"]), select, textarea, [tabindex]:not([tabindex="-1"])',
      ),
    ]
      .filter((el) => el.tabIndex >= 0 && !el.hasAttribute('disabled'))
      .map((el) => {
        el.focus();
        if (document.activeElement !== el) return null;
        const r = el.getBoundingClientRect();
        let visible = 0;
        let total = 0;
        for (let y = Math.ceil(r.top) + 1; y < r.bottom - 1; y += 2) {
          for (let x = Math.ceil(r.left) + 1; x < r.right - 1; x += 6) {
            total += 1;
            if (y < 0 || y > innerHeight || x < 0 || x > innerWidth) continue;
            const hit = document.elementFromPoint(x, y);
            if (hit && (hit === el || el.contains(hit))) visible += 1;
          }
        }
        // No sampled points means a zero-area box, which is its own kind of
        // invisible and is what the Gin originals collapse to.
        const pct = total ? Math.round((visible / total) * 100) : 0;
        return pct === 0
          ? `${el.tagName.toLowerCase()}#${el.id || '(no id)'} "${(el.getAttribute('aria-label') || (el as HTMLInputElement).value || el.innerText || '').trim().slice(0, 40)}"`
          : null;
      })
      .filter((x): x is string => x !== null);

  for (const path of [
    '/admin/config/system/site-information',
    '/node/add/digital_heritage',
    '/admin/config/mukurtu/submissions/digital_heritage',
  ]) {
    test(`Focus visible: no focusable element is invisible on ${path}`, async ({ page }) => {
      await page.goto(path);
      // Gin's sidebar collapses with a transition on visibility, and an
      // element is still focusable while that is in flight. Measuring before
      // it settles reports the sidebar's fields, which are not a defect:
      // once the transition ends, visibility: hidden takes them out of the
      // tab order by itself.
      await page.waitForTimeout(600);

      // Guard against passing on a redirect to the login form, which would
      // make this test prove nothing about the admin page.
      await expect(page.locator('#block-gin-page-title, .page-title, h1').first()).toBeVisible();
      expect(page.url(), 'still on the admin page, not redirected to login').not.toContain('/user/login');

      const invisible = await page.evaluate(findInvisibleFocusables);
      expect(invisible, 'focusable elements with none of their box on screen').toEqual([]);
    });
  }

  // The fix hides the in-form originals, so the sticky copies become the
  // only way to submit. Pin that they are there and usable, or a future
  // change to Gin's markup could take the buttons away entirely and still
  // pass the assertions above.
  test('Focus visible: the sticky header still offers a usable submit', async ({ page }) => {
    await page.goto('/admin/config/system/site-information');
    const submit = page.locator('.region-sticky #gin-sticky-edit-submit');
    await expect(submit).toBeVisible();
    // The copy lives outside the form, so it submits through this attribute.
    await expect(submit).toHaveAttribute('form', /.+/);
  });
});
