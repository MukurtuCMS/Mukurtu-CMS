#!/usr/bin/env bash
# The Dockerfile has exactly one build argument, MUKURTU_TEMPLATE_VERSION, and refuses to
# build without it. ffmpeg and poppler-utils are always part of the image: there are no
# options for them. See docs/docker-build.md, "Pinned inputs and the build guard".
set -u
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

TAG=mukurtu-test:guard

echo "guard: MUKURTU_TEMPLATE_VERSION unset"
if build_image guard "$TAG"; then
  fail "build fails when MUKURTU_TEMPLATE_VERSION is unset"
else
  pass "build fails when MUKURTU_TEMPLATE_VERSION is unset"
fi
assert_contains "$(<"$BUILD_LOG")" "build arg MUKURTU_TEMPLATE_VERSION must be set" \
  "message names MUKURTU_TEMPLATE_VERSION"

echo "guard: MUKURTU_TEMPLATE_VERSION empty"
if build_image guard "$TAG" --build-arg MUKURTU_TEMPLATE_VERSION=; then
  fail "build fails when MUKURTU_TEMPLATE_VERSION is empty"
else
  pass "build fails when MUKURTU_TEMPLATE_VERSION is empty"
fi

echo "guard: MUKURTU_TEMPLATE_VERSION set"
if build_image guard "$TAG" "${DEFAULT_BUILD_ARGS[@]}"; then
  pass "build succeeds with the template version"
else
  fail "build succeeds with the template version"; tail -5 "$BUILD_LOG"
fi

echo "guard: no options for the media tools"
# The tools are part of every image. An option to leave them out would let a user build
# an image that silently lacks thumbnails, and there is no slim variant to publish.
for pattern in "WITH_FFMPEG" "WITH_POPPLER"; do
  if grep -q "$pattern" "$REPO_ROOT/Dockerfile"; then
    fail "the Dockerfile has no $pattern option"
  else
    pass "the Dockerfile has no $pattern option"
  fi
done

finish
