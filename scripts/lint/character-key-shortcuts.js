#!/usr/bin/env node
/**
 * WCAG 2.1.4 Character Key Shortcuts: a shortcut bound to a single letter,
 * number, punctuation or symbol character must be switchable off, remappable,
 * or active only while a component has focus. A global one hijacks typing for
 * speech-input users, who emit stray characters constantly.
 *
 * This flags the failing shape only: a single-character key comparison inside
 * a handler bound globally (document/window/body) with no delegation selector.
 * A handler bound to a specific element, or delegated through a selector, is
 * active only on focus and is exempt - that is how Enter/Space activation is
 * normally written, and it is not a shortcut.
 *
 * Controlled by MUKURTU_LINT_STRICT, matching scripts/lint/multilingual-guardrails.sh:
 * unset/0 = report only (exit 0), 1 = fail the build on any violation.
 */
'use strict';
const fs = require('fs');
const path = require('path');

const STRICT = (process.env.MUKURTU_LINT_STRICT || '0') === '1';
// Resolved from this file's own location rather than by shelling out to git,
// so the lint runs the same way in CI, in a worktree, and from any directory.
const ROOT = path.resolve(__dirname, '..', '..');

// Named keys are not characters, so they are out of scope for 2.1.4.
const NAMED_KEY = /^(Enter|Escape|Esc|Tab|Backspace|Delete|Shift|Control|Alt|Meta|CapsLock|Arrow\w+|Home|End|Page\w+|Insert|F\d{1,2}|Spacebar|Up|Down|Left|Right)$/;

/** A single printable character: the only thing 2.1.4 is about. */
function isCharacterKey(literal) {
  if (NAMED_KEY.test(literal)) return false;
  return [...literal].length === 1;
}

/** Keycodes for printable characters: space, 0-9, A-Z, and the OEM punctuation block. */
function isCharacterKeyCode(code) {
  const n = Number(code);
  return n === 32 || (n >= 48 && n <= 57) || (n >= 65 && n <= 90) || (n >= 186 && n <= 222);
}

const KEY_LITERAL = /\.key\s*===?\s*(['"])(.*?)\1/g;
const KEY_CODE = /\.(?:keyCode|which)\s*===?\s*(\d+)/g;
// addEventListener('keydown', ...) or jQuery .on('keydown', '<selector>', ...)
const BINDING = /([A-Za-z_$][\w$.()'"\[\]\- ]*?)\s*\.\s*(?:addEventListener|on)\s*\(\s*['"]key(?:down|press|up)['"]\s*(,\s*(['"])(?:(?!\3).)*\3)?/g;

const GLOBAL_TARGET = /(^|[^.\w])(document|window|document\.body|window\.document)\s*$|^\$\(\s*(document|window|'body'|"body")\s*\)$/;

/** The binding that encloses a match is the last one opened before it. */
function bindingFor(source, index) {
  BINDING.lastIndex = 0;
  let found = null, m;
  while ((m = BINDING.exec(source)) !== null) {
    if (m.index > index) break;
    found = { target: m[1].trim(), delegated: Boolean(m[2]), at: m.index };
  }
  return found;
}

function isGlobal(binding) {
  if (!binding) return false;
  // A delegation selector scopes the handler to matching elements, so the
  // handler only fires when one of them has focus.
  if (binding.delegated) return false;
  return GLOBAL_TARGET.test(binding.target);
}

function scan(source, file) {
  const out = [];
  const lineOf = (i) => source.slice(0, i).split('\n').length;
  for (const [re, kind] of [[KEY_LITERAL, 'literal'], [KEY_CODE, 'code']]) {
    re.lastIndex = 0;
    let m;
    while ((m = re.exec(source)) !== null) {
      const value = kind === 'literal' ? m[2] : m[1];
      const isChar = kind === 'literal' ? isCharacterKey(value) : isCharacterKeyCode(value);
      if (!isChar) continue;
      const binding = bindingFor(source, m.index);
      if (!isGlobal(binding)) continue;
      out.push({
        file, line: lineOf(m.index),
        key: kind === 'literal' ? `'${value}'` : `keyCode ${value}`,
        target: binding.target,
      });
    }
  }
  return out;
}

// --- Self-test: prove the matcher can both catch and acquit, before trusting
// it against the real tree. A lint that silently matches nothing is worse than
// no lint, because it reports success.
const FIXTURES = [
  ['global single char is flagged',
    `document.addEventListener('keydown', (e) => { if (e.key === 's') save(); });`, 1],
  ['global keyCode is flagged',
    `window.addEventListener('keydown', function (e) { if (e.keyCode === 83) save(); });`, 1],
  ['element-bound is exempt',
    `el.addEventListener('keydown', (e) => { if (e.key === ' ') toggle(); });`, 0],
  ['jQuery delegated is exempt',
    `$(document).on('keydown', '.views-table tbody tr', function (e) { if (e.key === ' ') pick(); });`, 0],
  ['named keys are out of scope',
    `document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });`, 0],
];
let selfTestFailed = false;
for (const [label, fixture, expected] of FIXTURES) {
  const got = scan(fixture, 'fixture.js').length;
  if (got !== expected) {
    console.error(`FATAL: self-test "${label}" expected ${expected} finding(s), got ${got}.`);
    console.error(`  fixture: ${fixture}`);
    selfTestFailed = true;
  }
}
if (selfTestFailed) {
  console.error('The matcher is not behaving as written; refusing to report a result.');
  process.exit(2);
}

// --- Real scan.
const SKIP_DIR = new Set(['node_modules', 'libraries', 'external', 'vendor', '.git']);

function collect(dir, acc) {
  for (const entry of fs.readdirSync(path.join(ROOT, dir), { withFileTypes: true })) {
    const rel = path.join(dir, entry.name);
    if (entry.isSymbolicLink()) continue;
    if (entry.isDirectory()) {
      if (!SKIP_DIR.has(entry.name)) collect(rel, acc);
    } else if (entry.name.endsWith('.js') && !entry.name.endsWith('.min.js')) {
      acc.push(rel);
    }
  }
  return acc;
}

const files = ['modules', 'themes']
  .filter((d) => fs.existsSync(path.join(ROOT, d)))
  .flatMap((d) => collect(d, []));

const findings = [];
for (const f of files) {
  findings.push(...scan(fs.readFileSync(path.join(ROOT, f), 'utf8'), f));
}

console.log(`Checked ${files.length} JavaScript file(s) for single-character keyboard shortcuts.`);
if (!findings.length) {
  console.log('No global single-character shortcuts found.');
  process.exit(0);
}
for (const f of findings) {
  console.log(`${f.file}:${f.line}: shortcut on ${f.key} bound to ${f.target}`);
}
console.log(`\n${findings.length} possible WCAG 2.1.4 violation(s).`);
console.log('Fix by binding the handler to the component instead of the document,');
console.log('or by requiring a modifier key, or by making the shortcut remappable.');
process.exit(STRICT ? 1 : 0);
