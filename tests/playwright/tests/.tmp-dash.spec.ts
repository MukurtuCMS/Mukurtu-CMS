import { test } from '@playwright/test';
import { AxeBuilder } from '@axe-core/playwright';
import { ADMIN_STATE } from '~helpers/auth-state';

test.use({ storageState: ADMIN_STATE });

test('duplicate-id-aria on the dashboard', async ({ page }) => {
  await page.goto('/dashboard/mukurtu_dashboard');

  const dom = await page.evaluate(() => {
    const dupes: Record<string, number> = {};
    document.querySelectorAll('[id]').forEach((el) => {
      dupes[el.id] = (dupes[el.id] || 0) + 1;
    });
    const repeated = Object.entries(dupes).filter(([, n]) => n > 1);
    const referenced = repeated.filter(([id]) =>
      document.querySelector(`[aria-labelledby~="${id}"]`) !== null);
    return {
      repeated,
      referenced: referenced.map(([id, n]) => ({ id, n })),
      selectorWorks: document.querySelectorAll('#-menu').length,
      attrSelectorWorks: document.querySelectorAll('[id="-menu"]').length,
    };
  });
  console.log('DOM:', JSON.stringify(dom, null, 2));

  const res = await new AxeBuilder({ page }).withRules(['duplicate-id-aria']).analyze();
  console.log('AXE violations:', res.violations.length, 'passes:', res.passes.length,
    'incomplete:', res.incomplete.length, 'inapplicable:', res.inapplicable.length);
  console.log(JSON.stringify({
    v: res.violations.map((x) => ({ id: x.id, nodes: x.nodes.length })),
    p: res.passes.map((x) => ({ id: x.id, nodes: x.nodes.length })),
    i: res.incomplete.map((x) => ({ id: x.id, nodes: x.nodes.length })),
    na: res.inapplicable.map((x) => x.id),
  }, null, 2));
});
