import { test, expect, Page } from '@playwright/test';
import fs = require('fs');
import path = require('path');

/**
 * Community and taxonomy record tabs on narrow screens (#2557).
 *
 * Below field_group's tabs breakpoint (640px) the records stay an accordion
 * of details elements, and field_group never adds .horizontal-tabs-panes.
 * _horizontal-tabs.scss reverts every style inside .horizontal-tabs except
 * the panes, so the whole record used to render with browser defaults. The
 * panes are now also found by their data-horizontal-tabs-panes attribute,
 * and the accordion rows are styled like the tabs.
 *
 * Tests against the real compiled theme CSS with static fixtures (see
 * page-background.offline.spec.ts), so it needs no site.
 */

const THEME_CSS = fs.readFileSync(path.join(__dirname, '../../../themes/mukurtu_v4/css/style.css'), 'utf8');

// Mirrors community-records.html.twig before field_group runs, with a
// trimmed node--digital-heritage--full.html.twig in each pane.
function record(id: string, community: string, open: boolean): string {
  return `
    <details id="record-${id}" data-nid="${id}"${open ? ' open' : ''}>
      <summary><div class="div-title"><span>${community}</span></div></summary>
      <div class="tab-content-wrapper">
        <div class="tab-content-wrapper__content">
          <article class="node">
            <div class="full-node">
              <div class="full-node__grid">
                <div class="full-node__main"><h2 class="field__label">Summary</h2></div>
              </div>
            </div>
          </article>
        </div>
      </div>
    </details>
  `;
}

const ACCORDION = `
  <article class="digital-heritage__community-record">
    <div data-horizontal-tabs class="community-records horizontal-tabs clearfix">
      <ul data-horizontal-tabs-list class="horizontal-tabs-list visually-hidden"></ul>
      <div data-horizontal-tabs-panes>
        ${record('3', 'North Community', true)}
        ${record('4', 'South Community', false)}
      </div>
    </div>
  </article>
`;

async function load(page: Page): Promise<void> {
  await page.setViewportSize({ width: 390, height: 900 });
  await page.setContent(`<!DOCTYPE html><html><head><title>Record accordion fixture</title><style>${THEME_CSS}</style></head><body>${ACCORDION}</body></html>`);
}

// A theme custom property resolved to a computed colour.
async function themeColor(page: Page, property: string): Promise<string> {
  return page.evaluate((prop) => {
    const probe = document.createElement('span');
    probe.style.color = `var(${prop})`;
    document.body.appendChild(probe);
    const color = getComputedStyle(probe).color;
    probe.remove();
    return color;
  }, property);
}

test('record content keeps the theme layout', async ({ page }) => {
  await load(page);
  const grid = page.locator('#record-3 .full-node__grid');
  // The revert used to turn the grid back into a plain block.
  await expect(grid).toHaveCSS('display', 'grid');
  await expect(grid).toHaveCSS('padding-inline-start', '24px');
});

test('rows are styled like the tabs', async ({ page }) => {
  await load(page);
  const open = page.locator('#record-3 > summary');
  const closed = page.locator('#record-4 > summary');

  await expect(open).toHaveCSS('background-color', await themeColor(page, '--brand-primary-dark'));
  await expect(open).toHaveCSS('color', await themeColor(page, '--light-text-color'));
  await expect(closed).toHaveCSS('color', await themeColor(page, '--horizontal-tab-link-text'));
});

test('tabs built by field_group are unchanged', async ({ page }) => {
  await load(page);
  // What field_group does above its breakpoint.
  await page.evaluate(() => {
    const panes = document.querySelector('[data-horizontal-tabs-panes]')!;
    panes.classList.add('horizontal-tabs-panes');
    panes.querySelectorAll('details').forEach((d) => d.classList.add('horizontal-tabs-pane'));
  });
  await expect(page.locator('#record-3 > summary')).not.toHaveCSS('font-weight', '700');
});
