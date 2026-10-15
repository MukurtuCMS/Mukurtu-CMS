import { Page, TestInfo } from '@playwright/test';
import { AxeBuilder } from '@axe-core/playwright';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Axe rule tags that map to the program's WCAG 2.1 AA conformance target.
 * See docs/accessibility/README.md at the profile root.
 */
const WCAG_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'];

/**
 * WCAG 2.2-only rule tags, tracked informationally per the charter (not part
 * of the 2.1 AA gating target). Kept in a separate results bucket so they
 * never get counted as WCAG violations.
 */
const WCAG22_TAGS = ['wcag22a', 'wcag22aa'];

/**
 * Axe rules that report a detected defect as "incomplete" instead of as a
 * violation.
 *
 * These rules carry axe's `reviewOnFail` flag, which means a failing check is
 * downgraded to "needs review" on the way out. A page can therefore breach one
 * of these rules and still scan with zero violations, which is how the
 * dashboard's 16 duplicate `aria-labelledby` targets (#2395) went unreported
 * while the page scanned clean.
 *
 * They are listed here so the findings land in their own bucket rather than
 * being mixed in with the genuinely human-judgement incompletes such as
 * contrast over a background image. The list is pinned rather than read from
 * axe at runtime because axe exposes `reviewOnFail` only on a private field;
 * reviewOnFailRules.spec.ts regenerates it from axe-core and fails if an axe
 * upgrade changes the set, so the blind spot cannot widen silently.
 */
const REVIEW_ON_FAIL_RULES = [
  'aria-braille-equivalent',
  'bypass',
  'duplicate-id-aria',
  'frame-title-unique',
  'no-autoplay-audio',
];

/**
 * Directory where per-page scan results are written, relative to
 * tests/playwright/. Consumed when consolidating findings into
 * docs/accessibility/findings/.
 */
const RESULTS_DIR = path.join(__dirname, '../../test-results/a11y');

/**
 * Run an axe-core scan on the current page and record the results.
 *
 * Report-only by design: violations are written to
 * test-results/a11y/<slug>.json and attached to the Playwright report, but
 * this never fails the test. See "The ratchet" in the accessibility program
 * charter for the plan to make clean pages gating over time.
 *
 * @param page
 *   The Playwright page, already navigated to the URL under audit.
 * @param testInfo
 *   Playwright TestInfo, used to attach results to the report.
 * @param slug
 *   Machine name for the audited page, used as the results filename.
 */
export async function auditPage(page: Page, testInfo: TestInfo, slug: string): Promise<void> {
  // Scan for WCAG 2.1 A/AA violations, WCAG 2.2-only violations, and axe
  // "best practice" findings in a single pass, then separate them so
  // conformance failures are clearly distinguished from informational and
  // advisory findings.
  const results = await new AxeBuilder({ page })
    .withTags([...WCAG_TAGS, ...WCAG22_TAGS, 'best-practice'])
    .analyze();

  const hasTag = (violation: typeof results.violations[number], tags: string[]) =>
    violation.tags.some((tag) => tags.includes(tag));
  const wcagViolations = results.violations.filter((v) => hasTag(v, WCAG_TAGS));
  const wcag22Violations = results.violations.filter(
    (v) => !hasTag(v, WCAG_TAGS) && hasTag(v, WCAG22_TAGS),
  );
  const bestPracticeViolations = results.violations.filter(
    (v) => !hasTag(v, WCAG_TAGS) && !hasTag(v, WCAG22_TAGS),
  );

  // Rules whose failures axe downgrades to "incomplete" (see
  // REVIEW_ON_FAIL_RULES). Split out of the incomplete list so a detected
  // defect is not buried among the items that genuinely need human judgement.
  const reviewOnFailFindings = results.incomplete.filter((r) =>
    REVIEW_ON_FAIL_RULES.includes(r.id));
  const incomplete = results.incomplete.filter((r) =>
    !REVIEW_ON_FAIL_RULES.includes(r.id));

  const report = {
    slug,
    url: page.url(),
    timestamp: new Date().toISOString(),
    axeVersion: results.testEngine.version,
    summary: {
      wcagViolations: wcagViolations.length,
      wcag22Violations: wcag22Violations.length,
      bestPracticeViolations: bestPracticeViolations.length,
      reviewOnFailFindings: reviewOnFailFindings.length,
      passes: results.passes.length,
      incomplete: incomplete.length,
    },
    wcagViolations,
    // WCAG 2.2-only findings — informational, not part of the 2.1 AA target.
    wcag22Violations,
    bestPracticeViolations,
    // Detected defects that axe reports as "incomplete" because the rule
    // carries reviewOnFail. These are findings, not open questions.
    reviewOnFailFindings,
    // "Incomplete" checks need human review (e.g. contrast on images).
    incomplete,
  };

  fs.mkdirSync(RESULTS_DIR, { recursive: true });
  fs.writeFileSync(path.join(RESULTS_DIR, `${slug}.json`), JSON.stringify(report, null, 2));

  await testInfo.attach(`a11y-${slug}`, {
    body: JSON.stringify(report, null, 2),
    contentType: 'application/json',
  });

  // Surface a human-readable summary in the report without failing the test.
  const counts = wcagViolations
    .map((v) => `${v.id} (${v.impact}, ${v.nodes.length} nodes)`)
    .join(', ');
  testInfo.annotations.push({
    type: 'a11y',
    description: `${wcagViolations.length} WCAG violation rule(s) on ${slug}${counts ? `: ${counts}` : ''}`,
  });

  if (reviewOnFailFindings.length) {
    const reviewCounts = reviewOnFailFindings
      .map((r) => `${r.id} (${r.nodes.length} nodes)`)
      .join(', ');
    testInfo.annotations.push({
      type: 'a11y-review-on-fail',
      description: `${reviewOnFailFindings.length} rule(s) on ${slug} that axe reports as incomplete rather than as a violation: ${reviewCounts}`,
    });
  }
}
