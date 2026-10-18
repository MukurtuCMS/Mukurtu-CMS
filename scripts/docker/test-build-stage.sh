#!/usr/bin/env bash
# The `build` stage assembles the Composer project: mukurtu-template as the
# skeleton, with this checkout standing in for the Packagist copy of the
# profile. See docs/docker-build.md, "Where the code comes from".
set -u
source "$(dirname "${BASH_SOURCE[0]}")/test-lib.sh"

TAG=mukurtu-test:build

echo "build: assemble /app"
if ! build_image build "$TAG" "${DEFAULT_BUILD_ARGS[@]}"; then
  fail "build stage builds"
  tail -40 "$BUILD_LOG"
  finish
  exit 1
fi
pass "build stage builds"

in_image() { docker run --rm --entrypoint sh "$TAG" -c "$1"; }

repo_version="$(<"$REPO_ROOT/VERSION.md")"
image_version="$(in_image 'cat /app/web/profiles/mukurtu/VERSION.md')"
assert_eq "$repo_version" "$image_version" "profile in /app is this checkout (VERSION.md matches)"

in_image 'test -x /app/vendor/bin/drush' && pass "drush is installed" || fail "drush is installed"
in_image 'test ! -e /app/vendor/bin/phpunit' && pass "no phpunit (no dev dependencies)" || fail "no phpunit (no dev dependencies)"
in_image 'test -f /app/composer.lock' && pass "composer.lock is kept" || fail "composer.lock is kept"
in_image 'test -f /app/web/profiles/mukurtu/mukurtu.info.yml' && pass "profile info file present" || fail "profile info file present"

leftover_git="$(in_image 'find /app/web/profiles/mukurtu /app/vendor -name .git -prune 2>/dev/null | head -3')"
assert_eq "" "$leftover_git" "no .git directories in profile or vendor"

leftover_tests="$(in_image 'find /app/web/profiles/mukurtu /app/vendor /app/web/modules/contrib /app/web/themes/contrib \( -name tests -o -name Tests \) -type d -prune 2>/dev/null | head -3')"
assert_eq "" "$leftover_tests" "no tests directories in profile, vendor or contrib"

in_image 'test -f /app/web/profiles/mukurtu/LICENSE.txt' && pass "licence file kept" || fail "licence file kept"

assert_contains "$(<"$BUILD_LOG")" "No security vulnerability advisories found" "composer audit ran and found nothing"

# Dependency patches (drupal/core, paragraphs, ...) are declared in the profile's
# composer.json and must still be applied when the profile is a path
# repository. Composer fails the build if a patch does not apply, and records
# what it applied in patches.lock.json.
patched="$(in_image "php -r '\$j = json_decode(file_get_contents(\"/app/patches.lock.json\"), true); echo implode(\",\", array_keys(\$j[\"patches\"]));'")"
assert_contains "$patched" "drupal/core" "profile patches are applied: drupal/core"
assert_contains "$patched" "drupal/paragraphs" "profile patches are applied: drupal/paragraphs"

finish
