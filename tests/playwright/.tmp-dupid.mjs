import { chromium } from 'playwright';

const BASE = 'https://mukurtu-a11y.ddev.site';
const ULI = process.argv[2];

const browser = await chromium.launch();
const ctx = await browser.newContext({ ignoreHTTPSErrors: true });
const page = await ctx.newPage();
await page.goto(ULI);
await page.goto(BASE + '/dashboard/mukurtu_dashboard', { waitUntil: 'load' });
console.log('URL:', page.url(), 'TITLE:', await page.title());

const dom = await page.evaluate(() => {
  const counts = {};
  document.querySelectorAll('[id]').forEach((el) => { counts[el.id] = (counts[el.id] || 0) + 1; });
  const repeated = Object.entries(counts).filter(([, n]) => n > 1);
  return {
    repeated,
    aria: repeated.map(([id, n]) => ({
      id, n, refs: document.querySelectorAll(`[aria-labelledby~="${id}"]`).length,
    })),
  };
});
console.log('DOM:', JSON.stringify(dom, null, 2));

await page.addScriptTag({ path: "/Users/michael.wynne/GitHub/Mukurtu-CMS/tests/playwright/node_modules/axe-core/axe.min.js" });
const res = await page.evaluate(async () => {
  const r = await window.axe.run(document, { runOnly: { type: 'rule', values: ['duplicate-id-aria'] } });
  return {
    version: window.axe.version,
    violations: r.violations.flatMap((x) => x.nodes.map(n => ({target: n.target, data: n.any.map(c=>c.data)}))),
    passes: r.passes.flatMap((x) => x.nodes.map(n => ({target: n.target, data: n.any.map(c=>c.data)}))),
    incomplete: r.incomplete.flatMap((x) => x.nodes.map(n => ({target: n.target, data: n.any.map(c=>c.data), msg: n.any.map(c=>c.message)}))),
    inapplicable: r.inapplicable.map((x) => x.id),
  };
});
console.log('AXE:', JSON.stringify(res, null, 2));

await browser.close();
