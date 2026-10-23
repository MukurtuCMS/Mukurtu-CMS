import { test, expect, Locator, Page } from '@playwright/test';
import { Login } from '~components/login';
import { adminAccount } from '~helpers/a11y-credentials';

/**
 * Dragging to reorder items in an Entity Browser list (#808).
 *
 * Entity Browser fades the hovered item to 60% and leaves Sortable on native
 * HTML5 drag and drop, where the browser draws its own translucent preview
 * of that already-faded item. Editors reordering a collection could barely
 * see what they were moving. mukurtu_core keeps the item opaque on hover,
 * switches Sortable to its fallback mode so the dragged copy is a real,
 * fully opaque element, and outlines the spot where it will land.
 *
 * The collection form has three sortable lists and needs no existing content.
 * Its lists start empty, so stand-in rows are added to the first one. Sortable
 * works on whatever children the list has when a drag starts, so they behave
 * like rows Entity Browser rendered itself.
 */
const FORM_PATH = '/node/add/collection';
const LIST = '.field--widget-entity-browser-entity-reference .entities-list.sortable';

async function addRows(page: Page, labels: string[]): Promise<Locator> {
  const list = page.locator(LIST).first();
  await list.evaluate((el, items) => {
    el.innerHTML = items.map((label, i) =>
      `<div class="item-container" id="item-${i + 1}" data-entity-id="node:${i + 1}" style="padding:1rem;border:1px solid">`
      + `<span id="item-${i + 1}-title">${label}</span></div>`,
    ).join('');
  }, labels);
  // The collection form's lists sit far below the fold, and mouse events
  // outside the viewport hit nothing.
  await list.locator('.item-container').first().scrollIntoViewIfNeeded();
  return list;
}

test.describe('Entity Browser list reordering', () => {
  test.beforeEach(async ({ page }) => {
    const account = adminAccount();
    await new Login(page).login(account.username, account.password);
    await page.goto(FORM_PATH);
    await expect(page.locator(LIST).first()).toBeAttached({ timeout: 30000 });
  });

  test('a hovered item stays fully opaque', async ({ page }) => {
    const list = await addRows(page, ['First item', 'Second item']);
    const item = list.locator('.item-container').first();
    await item.hover();
    expect(await item.evaluate((el) => getComputedStyle(el).opacity)).toBe('1');
  });

  test('the dragged copy is fully opaque and the drop spot is outlined', async ({ page }) => {
    const list = await addRows(page, ['First item', 'Second item', 'Third item']);

    // Fallback mode is what makes the dragged copy a real element that CSS
    // can style, rather than a browser-drawn translucent image.
    const forceFallback = await list.evaluate(
      (el) => (window as any).Sortable.get(el)?.option('forceFallback'),
    );
    expect(forceFallback, 'Sortable uses its fallback drag mode').toBe(true);

    const first = list.locator('.item-container').first();
    const last = list.locator('.item-container').last();
    const from = (await first.boundingBox())!;
    const to = (await last.boundingBox())!;

    // Entity Browser lays its items out side by side, so drag across to the
    // far edge of the last one.
    await page.mouse.move(from.x + from.width / 2, from.y + from.height / 2);
    await page.mouse.down();
    await page.mouse.move(to.x + to.width - 2, to.y + to.height / 2, { steps: 20 });

    const clone = page.locator('.sortable-fallback');
    await expect(clone, 'a dragged copy is shown').toBeVisible();
    expect(await clone.evaluate((el) => getComputedStyle(el).opacity)).toBe('1');
    // Gin's cards have no background, so without one of its own the clone
    // shows whatever it passes over, which is as hard to read as a faded one.
    expect(
      await clone.evaluate((el) => getComputedStyle(el).backgroundColor),
      'the dragged copy has a solid background',
    ).not.toBe('rgba(0, 0, 0, 0)');

    // The copy is a visual stand-in only: no repeated IDs, nothing focusable.
    expect(await clone.evaluate((el) => ({
      inert: (el as HTMLElement).inert,
      hidden: el.getAttribute('aria-hidden'),
      ids: el.id ? 1 : el.querySelectorAll('[id]').length,
    }))).toEqual({ inert: true, hidden: 'true', ids: 0 });

    const ghost = list.locator('.sortable-ghost');
    await expect(ghost, 'the drop spot is marked').toHaveCount(1);
    expect(await ghost.evaluate((el) => getComputedStyle(el).outlineStyle)).toBe('dashed');

    await page.mouse.up();
    await expect(list.locator('.item-container').first()).toHaveText('Second item');

    // Entity Browser's onEnd handler saves the new order to the field's hidden
    // input. The instance is recreated to switch drag modes, so this proves
    // the handler came across with it.
    const targetIds = list.locator('xpath=..').locator('input[type="hidden"][name*="[target_id]"]');
    await expect(targetIds).toHaveValue('node:2 node:3 node:1');
  });
});
