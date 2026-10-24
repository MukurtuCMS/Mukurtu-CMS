#!/usr/bin/env bash
# Shared helpers for the scripts/docker/test-*.sh tests. Source this file.
#
#   build_image <target> <tag> [docker build args...]
#     Builds a Dockerfile target from the repository root. Output goes to
#     $BUILD_LOG; the return code is docker's.
#   runtime_image <tag>
#     Makes the runtime image available as <tag>: the image named by
#     MUKURTU_TEST_IMAGE if that is set (the pipeline tests the image it will publish),
#     otherwise a fresh build of the runtime stage.
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

# Build arguments every build needs: none. The template version is pinned by the
# Dockerfile's own default. (Override with --build-arg MUKURTU_TEMPLATE_VERSION=...)
DEFAULT_BUILD_ARGS=()
# Set COMPOSER_AUDIT_FLAGS=--ignore-unreachable to build while Drupal's advisory service is down.
[[ -z "${COMPOSER_AUDIT_FLAGS:-}" ]] || DEFAULT_BUILD_ARGS+=(--build-arg "COMPOSER_AUDIT_FLAGS=$COMPOSER_AUDIT_FLAGS")

pass() { TESTS_RUN=$((TESTS_RUN + 1)); echo "  ok   $1"; }
fail() { TESTS_RUN=$((TESTS_RUN + 1)); TESTS_FAILED=$((TESTS_FAILED + 1)); echo "  FAIL $1"; }

build_image() {
  local target="$1" tag="$2"
  shift 2
  docker build --progress=plain --target "$target" --tag "$tag" \
    --file "$REPO_ROOT/Dockerfile" "$@" "$REPO_ROOT" >"$BUILD_LOG" 2>&1
}

runtime_image() {
  if [[ -n "${MUKURTU_TEST_IMAGE:-}" ]]; then
    docker tag "$MUKURTU_TEST_IMAGE" "$1" >"$BUILD_LOG" 2>&1
  else
    build_image runtime "$1" "${DEFAULT_BUILD_ARGS[@]}"
  fi
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
