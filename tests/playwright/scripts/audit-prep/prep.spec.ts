import { test } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';
import { EXTRACT } from './extract';

/**
 * Generates one Markdown audit card per page for the manual keyboard and
 * screen reader passes (buckets C and D of the #2242 triage).
 *
 * This is an operator tool, not a test. It makes no assertions and decides
 * no criterion: it gathers the things a person would otherwise hunt for, so
 * their time goes on the judgement only a person can make. See
 * docs/accessibility/manual-audit-runbook.md.
 *
 *   AUDIT_BASE_URL=https://your-site.ddev.site \
 *   AUDIT_SESSION=/user/reset/... \
 *   npx playwright test -c scripts/audit-prep/playwright.config.ts
 */

const OUT = path.join(__dirname, '../../test-results/manual-audit');

// Anonymous pages, plus authenticated ones when AUDIT_SESSION is given.
// Kept as a literal list rather than imported from the inventory helpers:
// a tester needs to see and edit what will be visited, and the inventory
// carries discovery logic that needs seeded content this tool should not
// depend on.
const ANONYMOUS = [
  ['home', '/'],
  ['browse', '/browse'],
  ['digital-heritage-browse', '/digital-heritage'],
  ['communities', '/communities'],
  ['dictionary-browse', '/dictionary'],
  ['login', '/user/login'],
];

const AUTHENTICATED = [
  ['admin-overview', '/admin'],
  ['content-listing', '/admin/content'],
  ['people-listing', '/admin/people'],
  ['node-add-digital-heritage', '/node/add/digital_heritage'],
  ['submission-settings', '/admin/config/mukurtu/submissions/digital_heritage'],
  ['account-edit', '/user'],
];

type Card = Awaited<ReturnType<typeof EXTRACT>>;

function render(slug: string, url: string, d: Card): string {
  const L: string[] = [];
  L.push(`# ${slug}`, '', `\`${url}\` — ${d.title}`, '');
  L.push(`Page language: \`${d.lang}\``, '');

  L.push('## Landmarks', '');
  L.push(d.landmarks.length
    ? d.landmarks.map((l) => `- \`${l.role}\` — ${l.label}`).join('\n')
    : '_none found_');
  L.push('', '> **2.4.6 / 1.3.1:** more than one landmark of a kind needs distinguishing labels.', '');

  L.push('## Heading outline', '');
  L.push(d.headings.length
    ? d.headings.map((h) => `${'  '.repeat(Math.max(0, h.level - 1))}- h${h.level}: ${h.text}`).join('\n')
    : '_no headings_');
  if (d.skipped.length) L.push('', `**Skipped levels:** ${d.skipped.join('; ')}`);
  L.push('', '> **2.4.6:** is this a meaningful outline, not just a non-skipping one? Does each heading describe what follows?', '');

  L.push(`## Tab order (${d.focusables.length} stops)`, '');
  L.push('| # | role | accessible name | x,y | tabindex |');
  L.push('|---|---|---|---|---|');
  for (const f of d.focusables) {
    L.push(`| ${f.i} | ${f.role} | ${f.name} | ${f.x},${f.y} | ${f.tabindex ?? ''} |`);
  }
  if (d.backwards.length) {
    L.push('', `**Stops that sit above the previous one** (where DOM and reading order may part company):`);
    L.push(d.backwards.map((b) => `- #${b.i} ${b.role} "${b.name}" at y=${b.y}`).join('\n'));
  }
  L.push('', '> **2.4.3 / 1.3.2:** does this sequence match the visual reading order?',
    '> **2.1.1:** is every one of these operable, and is anything interactive *missing* from the list?',
    '> **3.2.1:** does focusing any of them navigate or submit on its own?', '');

  L.push('## Links with the same text and different targets', '');
  L.push(d.ambiguous.length
    ? d.ambiguous.map((a) => `- **"${a.text}"** → ${a.targets.map((t) => `\`${t}\``).join(', ')}`).join('\n')
    : '_none — every repeated link text points at one place_');
  L.push('', '> **2.4.4:** read alone, out of context, does each of these say where it goes?', '');

  L.push('## Live regions', '');
  L.push(d.liveRegions.length
    ? d.liveRegions.map((r) => `- \`${r.where}\` role=${r.role} aria-live=${r.live} atomic=${r.atomic}${r.currentlyEmpty ? ' _(empty now)_' : ''}`).join('\n')
    : '_none on this page_');
  L.push('', '> **4.1.3:** trigger each one and confirm it is announced **without** moving focus. An empty region now is normal; it fills on the event.', '');

  if (d.hoverTargets.length) {
    L.push('## Content revealed on hover or focus', '');
    L.push(d.hoverTargets.map((h) => `- ${h.where} "${h.name}" via ${h.via}`).join('\n'));
    L.push('', '> **1.4.13:** dismissible without moving the pointer, hoverable without vanishing, and persistent until dismissed?', '');
  }

  if (d.controls.length) {
    L.push(`## Form controls (${d.controls.length})`, '');
    L.push('| field | accessible name | required | describedby |');
    L.push('|---|---|---|---|');
    for (const c of d.controls) L.push(`| ${c.field} | ${c.name} | ${c.required ? 'yes' : ''} | ${c.describedby} |`);
    L.push('', '> **3.3.1:** submit it wrong, then confirm the error is announced and names the field.',
      '> **3.2.2:** does changing any value navigate or submit without warning?', '');
  }

  L.push('---', '', '## Findings on this page', '',
    'Record one line per failure: criterion, what you did, what happened, what you expected.', '',
    '- ', '');
  return L.join('\n');
}

test('generate manual-audit cards', async ({ page, context }) => {
  const session = process.env.AUDIT_SESSION;
  if (session) {
    await page.goto(session);
    await page.waitForLoadState('networkidle');
  }
  const pages = session ? [...ANONYMOUS, ...AUTHENTICATED] : ANONYMOUS;
  if (!session) {
    console.log('  No AUDIT_SESSION set: generating anonymous pages only.');
  }

  fs.mkdirSync(OUT, { recursive: true });
  const index: string[] = ['# Manual audit cards', '',
    `Generated ${new Date().toISOString().slice(0, 10)} against \`${process.env.AUDIT_BASE_URL}\`.`, '',
    'Work through these with the runbook: docs/accessibility/manual-audit-runbook.md', ''];

  for (const [slug, url] of pages) {
    // One awkward page must not cost the rest of the run, so each is wrapped
    // and its failure recorded in the index rather than thrown.
    try {
      // domcontentloaded, not networkidle: some admin pages keep a connection
      // open and never go idle, which would stall the whole walk.
      const res = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 60_000 });
      const status = res?.status() ?? 0;
      if (status >= 400) {
        console.log(`  ${slug.padEnd(28)} HTTP ${status} — skipped`);
        index.push(`- ~~${slug}~~ (\`${url}\` returned HTTP ${status})`);
        continue;
      }
      await page.waitForTimeout(1200);
      const data = await page.evaluate(EXTRACT);
      fs.writeFileSync(path.join(OUT, `${slug}.md`), render(slug, url, data));
      console.log(`  ${slug.padEnd(28)} ${data.focusables.length} tab stops, ${data.headings.length} headings, ${data.liveRegions.length} live regions`);
      index.push(`- [${slug}](${slug}.md) — \`${url}\``);
    } catch (e) {
      const why = e instanceof Error ? e.message.split('\n')[0] : String(e);
      console.log(`  ${slug.padEnd(28)} FAILED: ${why.slice(0, 80)}`);
      index.push(`- ~~${slug}~~ (\`${url}\` could not be captured: ${why.slice(0, 80)})`);
    } finally {
      // Written every iteration, so an interrupted run still leaves a usable
      // index of whatever was captured.
      fs.writeFileSync(path.join(OUT, 'index.md'), index.join('\n') + '\n');
    }
  }
  console.log(`\n  Cards written to tests/playwright/${path.relative(path.join(__dirname, '../..'), OUT)}/`);
});
