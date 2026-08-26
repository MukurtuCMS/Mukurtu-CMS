#!/usr/bin/env bash
#
# Greps custom Mukurtu modules for untranslated UI strings that should be
# wrapped in t()/$this->t(). See docs/content-language-policy.md.
#
# Controlled by MUKURTU_LINT_STRICT: unset/0 = report only (exit 0),
# 1 = fail the build on any violation. Flip that in CI once the known
# backlog of violations has been fixed, without editing this script.
set -euo pipefail

cd "$(git rev-parse --show-toplevel)"

STRICT="${MUKURTU_LINT_STRICT:-0}"
EXIT_CODE=0

# --- Self-test: prove each pattern can actually match, before trusting it
# against the real tree. Catches environments (e.g. ugrep) that silently
# treat a leading "-" in the pattern as an option instead of a literal.
self_test() {
  local pattern="$1" fixture="$2" label="$3"
  if ! printf '%s\n' "$fixture" | grep -Eq -e "$pattern"; then
    echo "FATAL: lint pattern for '$label' does not match its own fixture." >&2
    echo "  pattern: $pattern" >&2
    echo "  fixture: $fixture" >&2
    echo "The grep on this system may be misinterpreting the pattern (e.g. ugrep" >&2
    echo "treating a leading '-' as an option). Fix the pattern, don't skip this check." >&2
    exit 1
  fi
}

LABEL_DESC_PATTERN="->set(Label|Description)\\([[:space:]]*['\"]"
TITLE_DESC_PATTERN="['\"]#(title|description)['\"][[:space:]]*=>[[:space:]]*['\"]"

self_test "$LABEL_DESC_PATTERN" "      ->setLabel('Cultural Protocols')" "setLabel/setDescription literal"
self_test "$TITLE_DESC_PATTERN" "      '#title' => 'Related Content Display'," "#title/#description literal"

run_check() {
  local pattern="$1" description="$2"
  local matches
  matches=$(grep -rEn -e "$pattern" modules --include="*.php" --include="*.module" 2>/dev/null \
    | grep -Ev "/tests/" \
    | grep -Ev "\((''|\"\")\)" \
    | grep -Ev "=> (''|\"\"),?\$" \
    || true)

  if [ -z "$matches" ]; then
    echo "OK: no violations for: $description"
    return
  fi

  local count
  count=$(echo "$matches" | wc -l | tr -d ' ')
  echo "FOUND $count violation(s) for: $description"
  echo "$matches" | sed 's/^/  /'
  echo

  if [ "$STRICT" = "1" ]; then
    EXIT_CODE=1
  fi
}

echo "== Mukurtu multilingual lint: untranslated UI strings =="
echo "(mode: $([ "$STRICT" = "1" ] && echo enforcing || echo warn-only))"
echo

run_check "$LABEL_DESC_PATTERN" \
  "->setLabel()/->setDescription() called with a plain string literal instead of t('...')"

run_check "$TITLE_DESC_PATTERN" \
  "'#title'/'#description' render array key set to a plain string literal instead of \$this->t('...')"

if [ "$EXIT_CODE" != "0" ]; then
  echo "Wrap the strings above in t()/\$this->t() so they can be interface-translated." >&2
  echo "See docs/content-language-policy.md for context." >&2
fi

exit "$EXIT_CODE"
