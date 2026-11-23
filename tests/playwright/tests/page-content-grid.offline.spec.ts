import { test, expect, Page } from '@playwright/test';
import fs = require('fs');
import path = require('path');

/**
 * Issue #2749: content on pages that use the default page template
 * (page.html.twig) sat flush against the page edge instead of lining up with
 * the breadcrumb, title and tabs.
 *
 * The content region is now the grid item, not each block, because a block
 * can print bare text (the page not found message, for example), which a
 * rule on child elements can't place. Components that build their own
 * full-width grid (tabs, taxonomy term pages, the Articles page) must not
 * add the gutter a second time, at any width.
 *
 * Tests against the real compiled theme CSS with a static fixture (see
 * landing-page-reflow.offline.spec.ts), so it needs no site and runs in the
 * `offline` project.
 */

const THEME_DIR = path.join(__dirname, '../../../themes/mukurtu_v4');
const THEME_CSS = fs.readFileSync(path.join(THEME_DIR, 'css/style.css'), 'utf8');

// Mirrors page.html.twig on /user/login, with the tabs block, a form block,
// a block that prints bare text, and a taxonomy term page's own grid.
const PAGE = `
  <div class="layout-container">
    <div class="page__breadcrumbs">
      <div class="region">
        <div class="block-wrapper">
          <nav aria-labelledby="system-breadcrumb">
            <h2 id="system-breadcrumb" class="visually-hidden">Breadcrumb</h2>
            <ol class="breadcrumb__list-wrapper">
              <li class="breadcrumb__list-item"><a href="#">Home</a></li>
              <li class="breadcrumb__list-item">Log in</li>
            </ol>
          </nav>
        </div>
      </div>
    </div>
    <main id="main-content" role="main">
      <div class="region">
        <div class="page__title"><h1>Log in</h1></div>
      </div>
      <div class="layout-content">
        <div class="region">
          <div class="local-tasks">
            <h2 class="visually-hidden">Primary tabs</h2>
            <ul><li><a href="#" class="is-active">Log in</a></li><li><a href="#">Reset your password</a></li></ul>
          </div>
          <div class="block-wrapper" id="form-block">
            <form class="user-login-form">
              <div class="form-item"><label for="name">Username</label><input type="text" id="name" class="form-text"></div>
            </form>
          </div>
          <div class="block-wrapper" id="text-block">${'The page you are looking for might have been moved, renamed, or no longer exists. '.repeat(4)}</div>
          <div class="block-wrapper">
            <div class="taxonomy-term-page"><div id="term-content">Term content</div></div>
          </div>
        </div>
      </div>
    </main>
  </div>
`;

function html(): string {
  return `<!DOCTYPE html><html lang="en"><head><title>Page content grid fixture</title><style>${THEME_CSS}</style></head><body>${PAGE}</body></html>`;
}

async function edges(page: Page) {
  return page.evaluate(() => {
    const box = (selector: string) => {
      const rect = document.querySelector(selector)!.getBoundingClientRect();
      return { left: rect.left, right: rect.right };
    };
    // Bare text has no element of its own, so measure the text itself.
    const range = document.createRange();
    range.selectNodeContents(document.querySelector('#text-block')!);
    const text = range.getBoundingClientRect();
    return {
      // The breadcrumb's block spans the page grid's content columns.
      grid: box('.page__breadcrumbs .block-wrapper'),
      tabs: box('.local-tasks ul'),
      form: box('.user-login-form'),
      text: { left: text.left, right: text.right },
      term: box('#term-content'),
      overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
    };
  });
}

// One width from each band of the layout: below md, md, xl and 2xl. 320px
// is also 400% zoom on a 1280px window (WCAG 1.4.10).
for (const width of [320, 768, 1280, 1920]) {
  test(`Default-template content lines up with the breadcrumb at ${width}px`, async ({ page }) => {
    await page.setViewportSize({ width, height: 900 });
    await page.setContent(html());
    const e = await edges(page);

    // Without the theme's grid, everything would line up at 0.
    expect(e.grid.left, 'the page grid has a side gutter').toBeGreaterThan(0);
    expect(e.form.left, 'form starts at the grid edge').toBeCloseTo(e.grid.left, 0);
    expect(e.form.right, 'form ends at the grid edge').toBeCloseTo(e.grid.right, 0);
    expect(e.tabs.left, 'tabs start at the grid edge').toBeCloseTo(e.grid.left, 0);
    expect(e.term.left, 'a nested full-width grid adds no second gutter').toBeCloseTo(e.grid.left, 0);
    expect(e.term.right, 'a nested full-width grid adds no second gutter').toBeCloseTo(e.grid.right, 0);

    // Bare text wraps across the content column, not inside one grid column.
    expect(e.text.left, 'bare text starts at the grid edge').toBeCloseTo(e.grid.left, 0);
    expect(e.text.right, 'bare text uses the full column').toBeGreaterThan(e.grid.right - (e.grid.right - e.grid.left) * 0.15);

    expect(e.overflow, 'no horizontal scroll').toBeLessThanOrEqual(0);
  });
}
