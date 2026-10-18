#!/usr/bin/env bash
# Shared helpers for the scripts/docker/test-*.sh tests. Source this file.
#
#   build_image <target> <tag> [docker build args...]
#     Builds a Dockerfile target from the repository root. Output goes to
#     $BUILD_LOG; the return code is docker's.
#   assert_eq <expected> <actual> <message>
#   assert_contains <haystack> <needle> <message>
#   assert_not_contains <haystack> <needle> <message>
#   assert_fails <message> <command...>
#   pass / fail / finish

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
TESTS_RUN=0
TESTS_FAILED=0
BUILD_LOG="$(mktemp -t mukurtu-docker-build.XXXXXX)"
trap 'rm -f "$BUILD_LOG"' EXIT

# The mukurtu-template version the image is built against. One file, read by the
# publish workflow and by every test (override with MUKURTU_TEMPLATE_VERSION).
TEMPLATE_VERSION="${MUKURTU_TEMPLATE_VERSION:-$(tr -d '[:space:]' <"$REPO_ROOT/docker/TEMPLATE_VERSION")}"

# Build args every full build needs. Tests that build past the guard use these.
DEFAULT_BUILD_ARGS=(
  --build-arg WITH_FFMPEG=true
  --build-arg WITH_POPPLER=true
  --build-arg MUKURTU_TEMPLATE_VERSION="$TEMPLATE_VERSION"
)

pass() { TESTS_RUN=$((TESTS_RUN + 1)); echo "  ok   $1"; }
fail() { TESTS_RUN=$((TESTS_RUN + 1)); TESTS_FAILED=$((TESTS_FAILED + 1)); echo "  FAIL $1"; }

build_image() {
  local target="$1" tag="$2"
  shift 2
  docker build --progress=plain --target "$target" --tag "$tag" \
    --file "$REPO_ROOT/Dockerfile" "$@" "$REPO_ROOT" >"$BUILD_LOG" 2>&1
}

assert_eq() {
  if [[ "$1" == "$2" ]]; then pass "$3"; else fail "$3 (expected '$1', got '$2')"; fi
}

assert_contains() {
  if [[ "$1" == *"$2"* ]]; then pass "$3"; else fail "$3 (missing '$2')"; fi
}

assert_not_contains() {
  if [[ "$1" != *"$2"* ]]; then pass "$3"; else fail "$3 (unexpected '$2')"; fi
}

assert_fails() {
  local message="$1"
  shift
  if "$@" >/dev/null 2>&1; then fail "$message (command succeeded)"; else pass "$message"; fi
}

finish() {
  echo "$TESTS_RUN checks, $TESTS_FAILED failed"
  [[ "$TESTS_FAILED" -eq 0 ]]
}
