import { test, expect } from '@playwright/test';

/**
 * Regression test for navigation landmarks whose accessible name does not
 * resolve.
 *
 * block--mukurtu-v4-main-menu.html.twig used heading_id on the line that
 * built the nav's attributes, but did not set it until 30 lines further
 * down. Twig resolves an undefined variable to null, so the nav was built
 * with an empty aria-labelledby and Twig then dropped the attribute, leaving
 * the site's main navigation landmark unnamed (WCAG 1.3.1, 4.1.2). The h2 it
 * was meant to point at rendered correctly the whole time, which is why
 * nothing looked wrong on the page.
 *
 * A dangling reference fails the same way: aria-labelledby pointing at an ID
 * that is not in the document leaves the landmark unnamed, and axe reports
 * neither case as a violation, so it needs asserting directly.
 */

test.describe('navigation landmark names', () => {
  test('the main menu landmark is named', async ({ page }) => {
    await page.goto('/');

    const nav = page.locator('nav.main-menu');
    await expect(nav).toHaveCount(1);

    const ref = await nav.getAttribute('aria-labelledby');
    expect(ref, 'the main menu nav has an aria-labelledby').toBeTruthy();

    // An attribute selector rather than `#id`, so an ID that is not a valid
    // CSS identifier is still looked up rather than throwing.
    const target = page.locator(`[id="${ref}"]`);
    await expect(target, 'its aria-labelledby resolves to one element').toHaveCount(1);
    expect((await target.innerText()).trim()).not.toBe('');
  });

  test('no navigation landmark points at a missing element', async ({ page }) => {
    await page.goto('/');

    const dangling = await page.evaluate(() => {
      return [...document.querySelectorAll('nav[aria-labelledby]')]
        .map((nav) => ({
          ref: nav.getAttribute('aria-labelledby') ?? '',
          cls: nav.className,
        }))
        .filter((n) => n.ref.split(/\s+/).filter(Boolean)
          .some((id) => document.getElementById(id) === null));
    });

    expect(dangling, 'every nav aria-labelledby resolves').toEqual([]);
  });
});
