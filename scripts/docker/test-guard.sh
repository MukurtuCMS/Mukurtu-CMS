#!/usr/bin/env bash
# The Dockerfile refuses to build unless every required build arg is set
# explicitly. See docs/docker-build.md, "Pinned inputs and the build guard".
set -u
source "$(dirname "${BASH_SOURCE[0]}")/test-lib.sh"

TAG=mukurtu-test:guard
TEMPLATE=(--build-arg MUKURTU_TEMPLATE_VERSION="$TEMPLATE_VERSION")

echo "guard: WITH_FFMPEG unset"
if build_image guard "$TAG" --build-arg WITH_POPPLER=true "${TEMPLATE[@]}"; then
  fail "build fails when WITH_FFMPEG is unset"
else
  pass "build fails when WITH_FFMPEG is unset"
fi
assert_contains "$(<"$BUILD_LOG")" "build arg WITH_FFMPEG must be 'true' or 'false' (got '')" \
  "message names WITH_FFMPEG"

echo "guard: WITH_POPPLER invalid"
if build_image guard "$TAG" --build-arg WITH_FFMPEG=true --build-arg WITH_POPPLER=yes "${TEMPLATE[@]}"; then
  fail "build fails when WITH_POPPLER=yes"
else
  pass "build fails when WITH_POPPLER=yes"
fi
assert_contains "$(<"$BUILD_LOG")" "build arg WITH_POPPLER must be 'true' or 'false' (got 'yes')" \
  "message names WITH_POPPLER and the bad value"

echo "guard: MUKURTU_TEMPLATE_VERSION unset"
if build_image guard "$TAG" --build-arg WITH_FFMPEG=true --build-arg WITH_POPPLER=true; then
  fail "build fails when MUKURTU_TEMPLATE_VERSION is unset"
else
  pass "build fails when MUKURTU_TEMPLATE_VERSION is unset"
fi
assert_contains "$(<"$BUILD_LOG")" "build arg MUKURTU_TEMPLATE_VERSION must be set" \
  "message names MUKURTU_TEMPLATE_VERSION"

echo "guard: everything set"
if build_image guard "$TAG" --build-arg WITH_FFMPEG=false --build-arg WITH_POPPLER=false "${TEMPLATE[@]}"; then
  pass "build succeeds with slim args"
else
  fail "build succeeds with slim args"
fi
if build_image guard "$TAG" "${DEFAULT_BUILD_ARGS[@]}"; then
  pass "build succeeds with full args"
else
  fail "build succeeds with full args"
fi

finish
