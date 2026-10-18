#!/usr/bin/env bash
# docs/docker-build.md grows with the implementation: every finished piece of
# the image must have its section. Add a heading to REQUIRED_SECTIONS in the
# same commit that adds the section.
#
# Usage: test-docs.sh [heading...]   (arguments replace the default list)
set -u
source "$(dirname "${BASH_SOURCE[0]}")/test-lib.sh"

DOC="$REPO_ROOT/docs/docker-build.md"

REQUIRED_SECTIONS=(
  "Pinned inputs and the build guard"
  "Where the code comes from"
  "Runtime image, tools and extensions"
)
if [[ $# -gt 0 ]]; then
  REQUIRED_SECTIONS=("$@")
fi

if [[ ! -f "$DOC" ]]; then
  fail "docs/docker-build.md exists"
  finish
  exit
fi

for heading in "${REQUIRED_SECTIONS[@]}"; do
  if grep -qxF "## $heading" "$DOC"; then
    pass "section: $heading"
  else
    fail "section missing: $heading"
  fi
done

finish
