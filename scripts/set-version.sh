#!/usr/bin/env bash
#
# Sets the Mukurtu release version everywhere it is recorded.
#
# The version appears in VERSION.md (which the dashboard reads), in the
# `version:` key of the profile, every custom module and the theme (which is
# what /admin/modules and /admin/appearance display), and in the build tooling's
# package.json files. Before this script existed these were edited by hand,
# which is why every one of them still read "4.x-dev" at 4.0.0-rc.
#
# Usage: scripts/set-version.sh 4.0.1
#
# Test fixture modules under tests/ are deliberately left alone: they are not
# part of the distribution and carry their own unrelated versions.

set -euo pipefail

if [ $# -ne 1 ]; then
  echo "Usage: $0 <version>   e.g. $0 4.0.1" >&2
  exit 1
fi

VERSION="$1"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

# A release version, or a development placeholder like 4.x-dev.
if ! printf '%s' "$VERSION" | grep -qE '^([0-9]+\.[0-9]+\.[0-9]+(-[0-9A-Za-z.]+)?|[0-9]+\.x-dev)$'; then
  echo "Refusing to set an unrecognised version: $VERSION" >&2
  echo "Expected something like 4.0.1, 4.0.1-beta1, or 4.x-dev." >&2
  exit 1
fi

changed=0

printf '%s\n' "$VERSION" > VERSION.md
echo "  VERSION.md -> $VERSION"
changed=$((changed + 1))

# Every .info.yml that already declares a version, excluding test fixtures.
while IFS= read -r file; do
  case "$file" in
    */tests/*) continue ;;
  esac
  if grep -qE '^version: ' "$file"; then
    perl -pi -e "s{^version: .*\$}{version: $VERSION}" "$file"
    echo "  $file -> $VERSION"
    changed=$((changed + 1))
  fi
done < <(find . -name '*.info.yml' -not -path './web/*' -not -path './vendor/*' -not -path './node_modules/*' | sort)

# Build tooling. Not published to npm, but a stale version is worse than none.
for pkg in package.json themes/mukurtu_v4/package.json; do
  if [ -f "$pkg" ]; then
    perl -pi -e "s{^(\\s*\"version\":\\s*\")[^\"]*(\",?)\$}{\${1}$VERSION\${2}}" "$pkg"
    echo "  $pkg -> $VERSION"
    changed=$((changed + 1))
  fi
done

echo
echo "Updated $changed files to $VERSION."
echo "Review with: git diff --stat"
