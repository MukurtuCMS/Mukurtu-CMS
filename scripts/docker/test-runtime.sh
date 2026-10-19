#!/usr/bin/env bash
# The `runtime` stage is the image that ships: what is in it, what runs as whom, and
# that the media tools Mukurtu shells out to are always there. See
# docs/docker-build.md, "Runtime image, tools and extensions".
set -u
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

IMAGE=mukurtu-test:image

if build_image runtime "$IMAGE" "${DEFAULT_BUILD_ARGS[@]}"; then
  pass "runtime builds"
else
  fail "runtime builds"; tail -30 "$BUILD_LOG"; finish; exit 1
fi

run_in() { docker run --rm --entrypoint sh "$1" -c "$2" 2>&1; }
ok() { run_in "$1" "$2" >/dev/null 2>&1; }

check() { # check <tag> <message> <shell command that must succeed>
  if ok "$1" "$3"; then pass "$1 $2"; else fail "$1 $2"; fi
}

echo "runtime: the media tools are always installed and run"
check "$IMAGE" "has ffmpeg" 'ffmpeg -version'
check "$IMAGE" "has pdftotext" 'pdftotext -v'
check "$IMAGE" "has pdftoppm" 'pdftoppm --help'

echo "runtime: contents and users"
for tag in "$IMAGE"; do
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

echo "runtime: mail without an SMTP server fails with a clear message"
for tag in "$IMAGE"; do
  out="$(docker run --rm --entrypoint sh "$tag" -c '
    export MUKURTU_RUN_DIR=/tmp/r
    /usr/local/lib/mukurtu/render-config.sh 2>/dev/null
    PHPRC=/tmp/r/cli php -r "var_dump(mail(\"someone@example.org\", \"subject\", \"body\"));" 2>&1' 2>&1)"
  assert_contains "$out" "bool(false)" "$tag: PHP mail() reports failure"
  assert_contains "$out" "mail was not sent: MUKURTU_SMTP_HOST is not set" "$tag: the log says why"
  assert_not_contains "$out" "127.0.0.1" "$tag: it does not try to reach a mail server on localhost"
done

echo "image sizes (informational)"
docker image ls --format '  {{.Repository}}:{{.Tag}} {{.Size}}' mukurtu-test

finish
