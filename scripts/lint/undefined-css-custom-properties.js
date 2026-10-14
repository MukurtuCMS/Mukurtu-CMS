#!/usr/bin/env node
/**
 * Flags CSS custom properties the mukurtu_v4 theme reads but nothing defines.
 *
 * A var() with no fallback whose property is never defined is invalid at
 * computed-value time, so the declaration silently behaves as `unset`: colour
 * and font-size inherit from the parent instead. The rule looks right in the
 * source and does nothing, which is how a header search input ended up with
 * white text on a white background under the light header treatment (#2186).
 *
 * Reads are taken from the theme's SCSS sources. A property counts as defined
 * if anything under modules/ or themes/ declares it (`--x:` in SCSS, CSS or a
 * Twig inline style) or names it as a string literal in JS or PHP, which is
 * how style.setProperty() sets one. A var() with a fallback is never flagged.
 *
 * Controlled by MUKURTU_LINT_STRICT, matching scripts/lint/multilingual-guardrails.sh:
 * unset/0 = report only (exit 0), 1 = fail the build on any violation.
 */
'use strict';
const fs = require('fs');
const path = require('path');

const STRICT = (process.env.MUKURTU_LINT_STRICT || '0') === '1';
const ROOT = path.resolve(__dirname, '..', '..');
const READ_ROOT = path.join(ROOT, 'themes', 'mukurtu_v4', 'components');
const DEFINE_ROOTS = [path.join(ROOT, 'modules'), path.join(ROOT, 'themes')];
// Compiled output mirrors the sources, so a definition deleted from SCSS but
// not yet recompiled must not count.
const SKIP_DIRS = new Set(['node_modules', '.git', path.join(ROOT, 'themes', 'mukurtu_v4', 'css')]);

// Defined at runtime by Drupal core and the Gin admin theme, outside this repo.
const EXTERNAL_PREFIXES = ['--gin-', '--drupal-displace-', '--admin-toolbar-'];

// var(--name) closed with no fallback.
const READ = /var\(\s*(--[A-Za-z0-9_-]+)\s*\)/g;
const DECLARE = /(--[A-Za-z0-9_-]+)\s*:/g;
const LITERAL = /['"](--[A-Za-z0-9_-]+)['"]/g;

function walk(dir, exts, out = []) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      if (!SKIP_DIRS.has(entry.name) && !SKIP_DIRS.has(full)) walk(full, exts, out);
    }
    else if (exts.includes(path.extname(entry.name))) {
      out.push(full);
    }
  }
  return out;
}

function stripComments(source) {
  return source.replace(/\/\*[\s\S]*?\*\//g, (m) => m.replace(/[^\n]/g, ' '))
    .replace(/(^|[^:])\/\/[^\n]*/g, (m, p) => p + ' '.repeat(m.length - p.length));
}

function collectDefinitions(files) {
  const defined = new Set();
  for (const file of files) {
    const source = fs.readFileSync(file, 'utf8');
    const ext = path.extname(file);
    const pattern = ['.js', '.php', '.module', '.theme', '.inc'].includes(ext) ? LITERAL : DECLARE;
    for (const m of source.matchAll(pattern)) defined.add(m[1]);
  }
  return defined;
}

function findViolations(files, defined) {
  const violations = [];
  for (const file of files) {
    const source = stripComments(fs.readFileSync(file, 'utf8'));
    for (const m of source.matchAll(READ)) {
      const name = m[1];
      if (defined.has(name) || EXTERNAL_PREFIXES.some((p) => name.startsWith(p))) continue;
      const line = source.slice(0, m.index).split('\n').length;
      violations.push({ file: path.relative(ROOT, file), line, name });
    }
  }
  return violations;
}

// --- Self-test: prove the patterns flag what they should and only that,
// before trusting a clean result against the real tree.
function selfTest() {
  const defined = new Set(['--defined']);
  const cases = [
    ['a { color: var(--missing); }', 1],
    ['a { color: var( --missing ); }', 1],
    ['a { color: var(--missing, #000); }', 0],
    ['a { color: var(--defined); }', 0],
    ['a { color: var(--gin-color-text); }', 0],
    ['/* var(--missing) */ a { color: red; }', 0],
    ['// var(--missing)\na { color: red; }', 0],
  ];
  const tmp = fs.mkdtempSync(path.join(require('os').tmpdir(), 'css-props-'));
  try {
    cases.forEach(([css, expected], i) => {
      const file = path.join(tmp, `case${i}.scss`);
      fs.writeFileSync(file, css);
      const got = findViolations([file], defined).length;
      if (got !== expected) {
        console.error(`FATAL: self-test case ${i} expected ${expected} violation(s), got ${got}: ${css}`);
        process.exit(1);
      }
    });
    const defs = path.join(tmp, 'defs');
    fs.mkdirSync(defs);
    fs.writeFileSync(path.join(defs, 'a.scss'), ':root { --from-scss: 1px; }');
    fs.writeFileSync(path.join(defs, 'b.js'), "el.style.setProperty('--from-js', '1px');");
    fs.writeFileSync(path.join(defs, 'c.twig'), '<div style="--from-twig: url(x);">');
    const found = collectDefinitions(walk(defs, ['.scss', '.js', '.twig']));
    for (const name of ['--from-scss', '--from-js', '--from-twig']) {
      if (!found.has(name)) {
        console.error(`FATAL: self-test did not pick up the definition of ${name}.`);
        process.exit(1);
      }
    }
  }
  finally {
    fs.rmSync(tmp, { recursive: true, force: true });
  }
}

selfTest();

const defineFiles = DEFINE_ROOTS.flatMap((dir) => walk(dir, ['.scss', '.css', '.twig', '.js', '.php', '.module', '.theme', '.inc']));
const defined = collectDefinitions(defineFiles);
const violations = findViolations(walk(READ_ROOT, ['.scss']), defined);

if (!violations.length) {
  console.log('No undefined CSS custom properties read without a fallback.');
  process.exit(0);
}

console.log(`Found ${violations.length} read(s) of an undefined CSS custom property with no fallback:\n`);
for (const v of violations) console.log(`  ${v.file}:${v.line}  var(${v.name})`);
console.log('\nDefine the property, point the var() at one that exists, or add a fallback.');
process.exit(STRICT ? 1 : 0);
