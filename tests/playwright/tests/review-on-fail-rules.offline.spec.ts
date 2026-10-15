import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';
import axe from 'axe-core';
import { auditPage } from '~helpers/axe';

/**
 * Tests the review-on-fail bucket in auditPage().
 *
 * Some axe rules carry a `reviewOnFail` flag, which downgrades a failing
 * check to "incomplete" on the way out. A page can breach one of those rules
 * and still scan with zero violations. That is how the Mukurtu dashboard's 16
 * duplicate `aria-labelledby` targets (#2395) sat unreported while the page
 * scanned clean: axe found them every time and filed them next to the
 * contrast-over-an-image items nobody triages.
 *
 * The scan is report-only, so a bucket that silently stopped collecting would
 * look exactly like a clean site. These tests pin it in both directions, and
 * pin the rule list against an axe upgrade that quietly adds a rule to it.
 */

const RESULTS_DIR = path.join(__dirname, '../test-results/a11y');

/**
 * Reads the scan report auditPage() writes for a slug.
 */
function readReport(slug: string) {
  return JSON.parse(fs.readFileSync(path.join(RESULTS_DIR, `${slug}.json`), 'utf8'));
}

/**
 * Removes a fixture's report.
 *
 * auditPage() writes into the directory CI uploads as the a11y artifact, so
 * a fixture left behind would sit there looking like a scanned page.
 */
function discardReport(slug: string) {
  fs.rmSync(path.join(RESULTS_DIR, `${slug}.json`), { force: true });
}

test.describe('review-on-fail rules', () => {
  test('the pinned rule list still matches axe-core', async () => {
    // axe exposes reviewOnFail only on a private field, so the helper pins the
    // list instead of reading it at runtime. This regenerates it, so an axe
    // upgrade that adds a review-on-fail rule in our tag set fails here rather
    // than widening the blind spot in silence.
    const audit = (axe as unknown as {
      _audit: { rules: { id: string; enabled: boolean; reviewOnFail?: boolean; tags: string[] }[] };
    })._audit;

    expect(audit?.rules, 'axe-core no longer exposes _audit.rules; the pinned '
      + 'list in src/helpers/axe.ts can no longer be verified').toBeTruthy();

    // Deliberately not filtered by tag. Narrowing to the WCAG tags we request
    // would let an axe release add a best-practice-only review-on-fail rule
    // and keep this test green while the scan buried its findings, and
    // listing a rule we never request costs nothing: the bucket filters the
    // results we actually get.
    const fromAxe = audit.rules
      .filter((r) => r.enabled && r.reviewOnFail)
      .map((r) => r.id)
      .sort();

    // Guards against the filter above matching nothing and passing vacuously.
    expect(fromAxe.length).toBeGreaterThan(0);

    const pinned = fs.readFileSync(
      path.join(__dirname, '../src/helpers/axe.ts'), 'utf8',
    );
    const block = pinned.match(/const REVIEW_ON_FAIL_RULES = \[([^\]]*)\]/);
    expect(block, 'REVIEW_ON_FAIL_RULES not found in src/helpers/axe.ts').toBeTruthy();
    const listed = [...block![1].matchAll(/'([^']+)'/g)].map((m) => m[1]).sort();

    expect(listed).toEqual(fromAxe);
  });

  test('a duplicate ARIA target is reported, not buried', async ({ page }, testInfo) => {
    // The dashboard's shape: several menu blocks rendered by one template that
    // emits an empty id, so every copy collides and every copy points at it.
    await page.setContent(`
      <main>
        <nav aria-labelledby="-menu"><h2 id="-menu">Create</h2><a href="#a">A</a></nav>
        <nav aria-labelledby="-menu"><h2 id="-menu">Content</h2><a href="#b">B</a></nav>
      </main>
    `);
    await auditPage(page, testInfo, 'tmp-review-on-fail-dirty');
    const report = readReport('tmp-review-on-fail-dirty');
    discardReport('tmp-review-on-fail-dirty');

    const ids = report.reviewOnFailFindings.map((r: { id: string }) => r.id);
    expect(ids).toContain('duplicate-id-aria');

    // The point of the bucket: axe never files this as a violation, whatever
    // else is wrong with the page, so the WCAG count the programme reads would
    // never have mentioned it.
    const violationIds = report.wcagViolations.map((v: { id: string }) => v.id);
    expect(violationIds).not.toContain('duplicate-id-aria');
    // And it must no longer sit in the human-judgement list.
    const stillIncomplete = report.incomplete.map((r: { id: string }) => r.id);
    expect(stillIncomplete).not.toContain('duplicate-id-aria');
  });

  test('a page without duplicates reports nothing', async ({ page }, testInfo) => {
    await page.setContent(`
      <main>
        <nav aria-labelledby="create-menu"><h2 id="create-menu">Create</h2><a href="#a">A</a></nav>
        <nav aria-labelledby="content-menu"><h2 id="content-menu">Content</h2><a href="#b">B</a></nav>
      </main>
    `);
    await auditPage(page, testInfo, 'tmp-review-on-fail-clean');
    const report = readReport('tmp-review-on-fail-clean');
    discardReport('tmp-review-on-fail-clean');

    expect(report.reviewOnFailFindings).toEqual([]);
    expect(report.summary.reviewOnFailFindings).toBe(0);
  });
});
