#!/usr/bin/env node
/**
 * Lists every skipped test in a Playwright JSON report, with its reason.
 *
 * Run by CI after the suite so skipped accessibility scans are named in
 * the job log rather than counted. The suite's discovery-based scans call
 * test.skip() when a listing has no matching item, and the line reporter
 * prints only "N skipped" for those. That hides the difference between a
 * site that genuinely lacks a dictionary and a discovery selector that has
 * quietly gone stale, so coverage could shrink with the run still green.
 * See issue #2250.
 *
 * Usage: node scripts/list-skips.js [path/to/report.json]
 *
 * Prints a Markdown summary to stdout, suitable for $GITHUB_STEP_SUMMARY,
 * and one ::warning:: annotation per skip to stderr so they show on the
 * run page. Exits 0 regardless: this reports, it does not gate.
 */

const fs = require('fs');
const path = require('path');

const reportPath = process.argv[2] || path.join(__dirname, '..', 'test-results', 'report.json');

if (!fs.existsSync(reportPath)) {
  console.log(`No Playwright JSON report at ${reportPath}; nothing to summarise.`);
  process.exit(0);
}

const report = JSON.parse(fs.readFileSync(reportPath, 'utf8'));

/**
 * Walks the nested suite tree yielding [title path, test].
 */
function* walk(suite, ancestors = []) {
  const here = suite.title ? [...ancestors, suite.title] : ancestors;
  for (const spec of suite.specs || []) {
    for (const test of spec.tests || []) {
      yield [[...here, spec.title], test];
    }
  }
  for (const child of suite.suites || []) {
    yield* walk(child, here);
  }
}

const skipped = [];
let total = 0;

for (const suite of report.suites || []) {
  for (const [titles, test] of walk(suite)) {
    total++;
    // CI retries failures, so a test can carry several results. Only the
    // last one is its outcome; counting every result would inflate the
    // denominator by however many retries the failures burned.
    const results = test.results || [];
    const outcome = results.length ? results[results.length - 1].status : 'skipped';
    if (outcome !== 'skipped') {
      continue;
    }
    // test.skip(condition, reason) records the reason as a 'skip' annotation.
    const reason = (test.annotations || [])
      .filter((a) => a.type === 'skip')
      .map((a) => a.description)
      .filter(Boolean)
      .join('; ') || '(no reason given)';
    skipped.push({ title: titles.filter(Boolean).join(' › '), reason });
  }
}

if (skipped.length === 0) {
  console.log(`## Skipped scans\n\nNone. All ${total} test results ran.`);
  process.exit(0);
}

console.log(`## Skipped scans: ${skipped.length} of ${total}\n`);
console.log('These did not run. Each is a page that was not scanned, so the run is\nnot evidence about it. A skip is fine when the site genuinely lacks the\ncontent; it is a coverage gap when the content should be there.\n');
console.log('| Test | Reason |');
console.log('|---|---|');
for (const { title, reason } of skipped) {
  console.log(`| ${title.replace(/\|/g, '\\|')} | ${reason.replace(/\|/g, '\\|')} |`);
  process.stderr.write(`::warning title=Skipped scan::${title} — ${reason}\n`);
}
