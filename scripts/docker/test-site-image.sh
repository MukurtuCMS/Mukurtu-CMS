#!/usr/bin/env bash
# The site image example (examples/docker/site-image): a site's own image, built on
# the image under test, with a contributed module that needs a Composer library. See
# "Adding modules and themes" in docs/docker.md. Needs Docker and internet access
# (Packagist and Drupal.org).
set -u
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

BASE=mukurtu-test:image
SITE=mukurtu-test:site-image
WORK="$(mktemp -d -t mukurtu-site-image.XXXXXX)"
cleanup() {
  docker rmi -f "$SITE" >/dev/null 2>&1
  rm -rf "$WORK" "$BUILD_LOG"
}
trap cleanup EXIT

if ! runtime_image "$BASE"; then
  fail "runtime image builds"; tail -30 "$BUILD_LOG"; finish; exit 1
fi

echo "site image: build with drupal/address"
cp -r "$REPO_ROOT/examples/docker/site-image/." "$WORK/"
echo "drupal/address  # a comment after a package is ignored" >> "$WORK/composer-packages.txt"
# Packagist and Drupal.org sometimes fail a download; one such failure must not fail a release.
built=false
for attempt in 1 2 3; do
  if docker build --progress=plain --build-arg "MUKURTU_IMAGE=$BASE" --tag "$SITE" "$WORK" >"$BUILD_LOG" 2>&1; then
    built=true; break
  fi
  sleep 15
done
if ! $built; then
  fail "the site image builds"; tail -40 "$BUILD_LOG"; finish; exit 1
fi
pass "the site image builds"

in_site() { docker run --rm --entrypoint sh "$SITE" -c "$1"; }
in_base() { docker run --rm --entrypoint sh "$BASE" -c "$1"; }

in_site 'test -f /var/www/html/web/modules/contrib/address/address.info.yml' && pass "the module is in web/modules/contrib" || fail "the module is in web/modules/contrib"
assert_eq "yes" "$(in_site "cd /var/www/html && php -r 'require \"vendor/autoload.php\"; echo class_exists(\"CommerceGuys\\\\Addressing\\\\Address\") ? \"yes\" : \"no\";'")" "the module's Composer library autoloads"

# Every package the official image ships must be there at the same version.
versions='php -r '\''$l = json_decode(file_get_contents("/var/www/html/composer.lock"), true); foreach ($l["packages"] as $p) echo $p["name"], " ", $p["version"], "\n";'\'''
base_versions="$(in_base "$versions" | sort)"
site_versions="$(in_site "$versions" | sort)"
changed="$(comm -23 <(echo "$base_versions") <(echo "$site_versions"))"
assert_eq "" "$changed" "no package from the official image was changed or removed"
added="$(comm -13 <(echo "$base_versions") <(echo "$site_versions") | cut -d' ' -f1 | tr '\n' ' ')"
assert_contains "$added" "drupal/address" "drupal/address was added"
assert_contains "$added" "commerceguys/addressing" "its library was added"

in_site '! command -v composer' && pass "the site image has no composer binary" || fail "the site image has no composer binary"
in_site '! command -v git' && pass "the site image has no build tools (git)" || fail "the site image has no build tools (git)"
assert_eq "mukurtu" "$(in_site 'id -un')" "the site image runs as user mukurtu"
in_site '! test -w /var/www/html && ! test -w /var/www/html/web/modules/contrib && ! test -w /var/www/html/vendor' && pass "the code is not writable by the runtime user" || fail "the code is not writable by the runtime user"
assert_eq "$(docker image inspect -f '{{json .Config.Entrypoint}}' "$BASE")" "$(docker image inspect -f '{{json .Config.Entrypoint}}' "$SITE")" "the site image keeps the official entrypoint"
assert_eq "$(docker image inspect -f '{{json .Config.Healthcheck}}' "$BASE")" "$(docker image inspect -f '{{json .Config.Healthcheck}}' "$SITE")" "the site image keeps the official health check"

echo "site image: MUKURTU_IMAGE is required"
out="$(docker build "$WORK" 2>&1)"
assert_contains "$out" "MUKURTU_IMAGE_is_required" "building without MUKURTU_IMAGE names the missing argument"

finish
