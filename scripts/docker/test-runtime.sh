#!/usr/bin/env bash
# The `runtime` stage is the image that ships. It is built in two variants:
# full (ffmpeg + poppler-utils) and slim (neither). See docs/docker-build.md,
# "Runtime image, tools and extensions".
set -u
source "$(dirname "${BASH_SOURCE[0]}")/test-lib.sh"

FULL=mukurtu-test:full
SLIM=mukurtu-test:slim
TEMPLATE="${MUKURTU_TEMPLATE_VERSION:-4.0.2}"

build_variant() {
  local tag="$1" ffmpeg="$2" poppler="$3"
  if build_image runtime "$tag" \
      --build-arg WITH_FFMPEG="$ffmpeg" --build-arg WITH_POPPLER="$poppler" \
      --build-arg MUKURTU_TEMPLATE_VERSION="$TEMPLATE"; then
    pass "runtime builds: $tag"
  else
    fail "runtime builds: $tag"
    tail -30 "$BUILD_LOG"
    finish
    exit 1
  fi
}

# in <tag> <shell command>: run a command in the image, print its output.
run_in() { docker run --rm --entrypoint sh "$1" -c "$2" 2>&1; }
ok() { run_in "$1" "$2" >/dev/null 2>&1; }

check() { # check <tag> <message> <shell command that must succeed>
  if ok "$1" "$3"; then pass "$1 $2"; else fail "$1 $2"; fi
}

echo "runtime: full variant"
build_variant "$FULL" true true
check "$FULL" "ffmpeg -version" 'ffmpeg -version'
check "$FULL" "pdftotext -v" 'pdftotext -v'
check "$FULL" "pdftoppm --help" 'pdftoppm --help'

echo "runtime: slim variant"
build_variant "$SLIM" false false
for tool in ffmpeg pdftotext pdftoppm; do
  check "$SLIM" "has no $tool" "! command -v $tool"
done

echo "runtime: both variants"
for tag in "$FULL" "$SLIM"; do
  modules="$(run_in "$tag" 'php -m')"
  for ext in gd intl zip "Zend OPcache" pdo_mysql mbstring apcu curl dom fileinfo xml SimpleXML; do
    assert_contains "$modules" "$ext" "$tag has extension: $ext"
  done
  gd="$(run_in "$tag" "php -r 'foreach (gd_info() as \$k => \$v) { echo \$k, \"=\", var_export(\$v, true), \"\\n\"; }'")"
  assert_contains "$gd" "JPEG Support=true" "$tag gd: JPEG"
  assert_contains "$gd" "WebP Support=true" "$tag gd: WebP"
  assert_contains "$gd" "FreeType Support=true" "$tag gd: FreeType"
  check "$tag" "does not disable exec()" 'php -r "exit(in_array(\"exec\", array_map(\"trim\", explode(\",\", ini_get(\"disable_functions\")))) ? 1 : 0);"'
  check "$tag" "has no composer binary" '! command -v composer'
  check "$tag" "has tini" 'command -v tini'
  check "$tag" "has drush on PATH" 'drush --version'
  check "$tag" "has the profile" 'test -f /var/www/html/web/profiles/mukurtu/mukurtu.info.yml'
  check "$tag" "keeps composer.lock" 'test -f /var/www/html/composer.lock'
  check "$tag" "has nginx" 'nginx -v'
  check "$tag" "has msmtp" 'msmtp --version'
  check "$tag" "public files dir is writable by the runtime user" 'test -w /var/www/files/public'
  check "$tag" "private files dir is writable by the runtime user" 'test -w /var/www/files/private'
  check "$tag" "web/sites/default/files points at the public volume" 'test "$(readlink /var/www/html/web/sites/default/files)" = /var/www/files/public'
  assert_eq "mukurtu" "$(run_in "$tag" 'id -un')" "$tag runs as user mukurtu"
  assert_eq "1000" "$(run_in "$tag" 'id -u')" "$tag runs as uid 1000"
  volumes="$(docker image inspect "$tag" --format '{{json .Config.Volumes}}')"
  assert_contains "$volumes" "/var/www/files/public" "$tag declares the public volume"
  assert_contains "$volumes" "/var/www/files/private" "$tag declares the private volume"
done

echo "image sizes (informational)"
docker image ls --format '  {{.Repository}}:{{.Tag}} {{.Size}}' mukurtu-test

finish
