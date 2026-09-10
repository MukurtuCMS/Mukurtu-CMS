#!/usr/bin/env bash
#
# Checks hook_update_N() numbering across the profile and its modules.
#
# Two rules, both of which have already caused real problems:
#
#   1. No number may repeat within a file. Drupal silently runs only one of a
#      duplicated pair, so the other never executes on any site.
#
#   2. Every number must be greater than that module's
#      hook_update_last_removed(). Numbers at or below it are never run:
#      update_get_update_list() only lists updates above a site's installed
#      schema version, and a fresh install is seeded at last_removed. A hook
#      numbered too low is dead code that looks live.
#
# See docs/update-hooks.md for the numbering scheme.
#
# Controlled by MUKURTU_LINT_STRICT: unset/0 = report only (exit 0),
# 1 = fail the build on any violation.
set -euo pipefail

cd "$(git rev-parse --show-toplevel)"

STRICT="${MUKURTU_LINT_STRICT:-0}"
EXIT_CODE=0

HOOK_PATTERN="^function [a-z_]+_update_[0-9]+\("
LAST_REMOVED_PATTERN="^function [a-z_]+_update_last_removed\("

# --- Self-test: prove the patterns match their own fixtures before trusting
# them against the real tree, mirroring multilingual-guardrails.sh. A pattern
# that silently matches nothing would make this lint report success forever.
self_test() {
  local pattern="$1" fixture="$2" label="$3"
  if ! printf '%s\n' "$fixture" | grep -Eq -e "$pattern"; then
    echo "FATAL: lint pattern for '$label' does not match its own fixture." >&2
    echo "  pattern: $pattern" >&2
    echo "  fixture: $fixture" >&2
    exit 1
  fi
}

self_test "$HOOK_PATTERN" "function mukurtu_core_update_40123(): void {" "hook_update_N declaration"
self_test "$LAST_REMOVED_PATTERN" "function mukurtu_core_update_last_removed(): int {" "hook_update_last_removed declaration"

# Every .install file in the profile, including the profile's own.
install_files() {
  find . -name '*.install' \
    -not -path './web/*' \
    -not -path './vendor/*' \
    -not -path './node_modules/*' \
    -not -path './*/tests/*' \
    | sort
}

report() {
  local message="$1"
  echo "$message"
  if [ "$STRICT" = "1" ]; then
    EXIT_CODE=1
  fi
}

echo "== Mukurtu update hook numbering =="
echo "(mode: $([ "$STRICT" = "1" ] && echo enforcing || echo warn-only))"
echo

DUPLICATE_VIOLATIONS=0
BASELINE_VIOLATIONS=0
CHECKED=0

while IFS= read -r file; do
  numbers=$(grep -oE "$HOOK_PATTERN" "$file" 2>/dev/null | grep -oE '_update_[0-9]+' | grep -oE '[0-9]+' || true)
  [ -z "$numbers" ] && continue
  CHECKED=$((CHECKED + 1))

  # Rule 1: no repeats within the file.
  dupes=$(printf '%s\n' "$numbers" | sort | uniq -d)
  if [ -n "$dupes" ]; then
    report "DUPLICATE numbers in $file:"
    printf '%s\n' "$dupes" | sed 's/^/    /'
    DUPLICATE_VIOLATIONS=$((DUPLICATE_VIOLATIONS + 1))
  fi

  # Rule 2: every number must exceed hook_update_last_removed(), when present.
  # The return value is read from the function body rather than assumed.
  last_removed=$(awk '
    /^function [a-z_]+_update_last_removed\(/ { inside = 1; next }
    inside && match($0, /return[[:space:]]+[0-9]+/) {
      s = substr($0, RSTART, RLENGTH); gsub(/[^0-9]/, "", s); print s; exit
    }
    inside && /^}/ { exit }
  ' "$file")

  if [ -n "$last_removed" ]; then
    while IFS= read -r n; do
      [ -z "$n" ] && continue
      if [ "$n" -le "$last_removed" ]; then
        report "DEAD HOOK in $file: _update_$n is not greater than last_removed ($last_removed), so it will never run"
        BASELINE_VIOLATIONS=$((BASELINE_VIOLATIONS + 1))
      fi
    done <<< "$numbers"
  fi
done < <(install_files)

# Rule 3: every hook_update_last_removed() is well formed.
#
# After the 4.0.1 strip there are no update hooks left, so rules 1 and 2 have
# nothing to check and stay silent until someone adds a hook. That is the
# intent, but it would leave this script reporting success while inspecting
# nothing at all, which is how a lint quietly stops being a lint. The baselines
# themselves are checkable, so they are checked: they are what every future
# hook number is measured against, and a malformed one would take rule 2 down
# with it.
BASELINE_COUNT=0

while IFS= read -r file; do
  declarations=$(grep -cE "$LAST_REMOVED_PATTERN" "$file" 2>/dev/null || true)
  [ "$declarations" = "0" ] && continue

  if [ "$declarations" -gt 1 ]; then
    report "DUPLICATE last_removed in $file: declared $declarations times, only one takes effect"
  fi

  value=$(awk '
    /^function [a-z_]+_update_last_removed\(/ { inside = 1; next }
    inside && match($0, /return[[:space:]]+[0-9]+/) {
      s = substr($0, RSTART, RLENGTH); gsub(/[^0-9]/, "", s); print s; exit
    }
    inside && /^}/ { exit }
  ' "$file")

  if [ -z "$value" ]; then
    report "UNREADABLE last_removed in $file: no integer return value found"
    continue
  fi
  if [ "$value" -le 0 ]; then
    report "INVALID last_removed in $file: $value is not a positive update number"
    continue
  fi

  BASELINE_COUNT=$((BASELINE_COUNT + 1))
done < <(install_files)

echo "Checked $CHECKED file(s) containing update hooks."
echo "Checked $BASELINE_COUNT last_removed baseline(s)."
if [ "$CHECKED" = "0" ] && [ "$BASELINE_COUNT" = "0" ]; then
  report "NOTHING CHECKED: no update hooks and no last_removed baselines were found at all, which almost certainly means this script stopped matching the tree rather than that the tree is clean"
fi
if [ "$DUPLICATE_VIOLATIONS" = "0" ]; then
  echo "OK: no duplicate hook numbers within a file."
fi
if [ "$BASELINE_VIOLATIONS" = "0" ]; then
  echo "OK: no hook numbered at or below its module's last_removed."
fi

if [ "$EXIT_CODE" != "0" ]; then
  echo >&2
  echo "A new update hook must be numbered higher than every existing number in" >&2
  echo "its file and higher than that module's hook_update_last_removed()." >&2
  echo "Each .install file declares last_removed at most once, returning the" >&2
  echo "highest update number the previous release shipped." >&2
  echo "See docs/update-hooks.md." >&2
fi

exit "$EXIT_CODE"
