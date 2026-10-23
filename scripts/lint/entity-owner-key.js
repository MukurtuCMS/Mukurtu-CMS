#!/usr/bin/env node
/**
 * Flags `'uid' =>` in Entity::create() calls for entities whose owner field is
 * not named uid.
 *
 * Community, Protocol and PersonalCollection declare `'uid' => 'user_id'` in
 * their entity_keys: uid is the key's name, user_id is the field. create()
 * takes field names, so `Community::create(['uid' => $owner->id()])` sets
 * nothing, and preCreate() then defaults the owner to the current user, which
 * in a kernel test is often anonymous. The group is saved with no owner, OG
 * never adds the owner as a member, and a test passes against a state the
 * application never produces. Twenty fixtures had this before #2390.
 *
 * The entities are found by reading entity_keys from every class under
 * modules/X/src/Entity/, so a new entity with the same mapping is covered
 * without editing this file. Only a `'uid'` key at the top level of the
 * create() array is flagged. Creating one of these entities through
 * $storage->create() is not visible to this scan.
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

const UID_KEY = /entity_keys\s*:\s*\[[^\]]*?['"]uid['"]\s*=>\s*['"](\w+)['"]/s;
const CLASS_NAME = /^(?:final\s+|abstract\s+)?class\s+(\w+)/m;

/** Entity classes whose uid entity key points at a field with another name. */
function entitiesFrom(source) {
  const key = source.match(UID_KEY);
  const cls = source.match(CLASS_NAME);
  if (!key || !cls || key[1] === 'uid') return [];
  return [{ cls: cls[1], field: key[1] }];
}

/**
 * Returns the index just past the string literal starting at i, so that
 * brackets and keys inside strings are never mistaken for code.
 */
function skipString(source, i) {
  const quote = source[i];
  for (let j = i + 1; j < source.length; j++) {
    if (source[j] === '\\') j++;
    else if (source[j] === quote) return j + 1;
  }
  return source.length;
}

/** Top-level 'uid' keys in the create() call that opens at index `open`. */
function uidKeysInCall(source, open) {
  const found = [];
  let depth = 0;
  for (let i = open; i < source.length; i++) {
    const c = source[i];
    if (c === "'" || c === '"') {
      const end = skipString(source, i);
      // depth 2: inside create( and directly inside its [ array.
      if (depth === 2 && source.slice(i + 1, end - 1) === 'uid' && /^\s*=>/.test(source.slice(end))) {
        found.push(i);
      }
      i = end - 1;
    } else if (c === '(' || c === '[') {
      depth++;
    } else if (c === ')' || c === ']') {
      depth--;
      if (depth === 0) break;
    }
  }
  return found;
}

function scan(source, file, entities) {
  const out = [];
  const lineOf = (i) => source.slice(0, i).split('\n').length;
  for (const { cls, field } of entities) {
    const call = new RegExp(`\\b${cls}::create\\s*\\(`, 'g');
    let m;
    while ((m = call.exec(source)) !== null) {
      const open = m.index + m[0].length - 1;
      for (const at of uidKeysInCall(source, open)) {
        out.push({ file, line: lineOf(at), cls, field });
      }
    }
  }
  return out;
}

// --- Self-test: prove the matcher can both catch and acquit, before trusting
// it against the real tree. A lint that silently matches nothing is worse than
// no lint, because it reports success.
const FIXTURE_ENTITY = `#[ContentEntityType(
  id: 'community',
  entity_keys: [
    'id' => 'id',
    'uid' => 'user_id',
  ],
)]
class Community extends EditorialContentEntityBase {}`;
const SELF_ENTITIES = entitiesFrom(FIXTURE_ENTITY);
const FIXTURES = [
  ['multi-line uid key is flagged',
    `$c = Community::create([\n  'name' => 'A',\n  'uid' => $owner->id(),\n]);`, 1],
  ['single-line uid key is flagged',
    `$c = Community::create(['name' => 'A', "uid" => 1]);`, 1],
  ['user_id key is accepted',
    `$c = Community::create(['name' => 'A', 'user_id' => $owner->id()]);`, 0],
  ['a uid key in a nested array is out of scope',
    `$c = Community::create(['field_x' => ['uid' => 1], 'user_id' => 1]);`, 0],
  ['other entities are out of scope',
    `$n = Node::create(['type' => 'page', 'uid' => 1]);`, 0],
  ['uid inside a string value is not a key',
    `$c = Community::create(['name' => "'uid' => 1", 'user_id' => 1]);`, 0],
];
let selfTestFailed = SELF_ENTITIES.length !== 1;
if (selfTestFailed) {
  console.error('FATAL: self-test could not read the uid => user_id mapping from its fixture entity.');
}
for (const [label, fixture, expected] of FIXTURES) {
  const got = scan(fixture, 'fixture.php', SELF_ENTITIES).length;
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
const SKIP_DIR = new Set(['node_modules', 'vendor', '.git']);

function collect(dir, acc) {
  for (const entry of fs.readdirSync(path.join(ROOT, dir), { withFileTypes: true })) {
    const rel = path.join(dir, entry.name);
    if (entry.isSymbolicLink()) continue;
    if (entry.isDirectory()) {
      if (!SKIP_DIR.has(entry.name)) collect(rel, acc);
    } else if (/\.(php|module|install|inc)$/.test(entry.name)) {
      acc.push(rel);
    }
  }
  return acc;
}

const files = ['modules', 'src', 'tests']
  .filter((d) => fs.existsSync(path.join(ROOT, d)))
  .flatMap((d) => collect(d, []));

const read = (f) => fs.readFileSync(path.join(ROOT, f), 'utf8');
const entities = files
  .filter((f) => f.split(path.sep).includes('Entity') && f.includes(`${path.sep}src${path.sep}`))
  .flatMap((f) => entitiesFrom(read(f)));

if (!entities.length) {
  console.error('FATAL: found no entity whose uid key maps to another field, but Community and Protocol both do.');
  console.error('The entity_keys pattern no longer matches how entities are declared; fix it before trusting this lint.');
  process.exit(2);
}

const findings = files.flatMap((f) => scan(read(f), f, entities));

console.log(`Checked ${files.length} PHP file(s) for create() calls on: ${entities.map((e) => `${e.cls} (owner field ${e.field})`).join(', ')}.`);
if (!findings.length) {
  console.log("No 'uid' keys found in their create() calls.");
  process.exit(0);
}
for (const f of findings) {
  console.log(`${f.file}:${f.line}: ${f.cls}::create() sets 'uid', which is not a field; use '${f.field}'`);
}
console.log(`\n${findings.length} create() call(s) set an owner that is silently ignored.`);
process.exit(STRICT ? 1 : 0);
