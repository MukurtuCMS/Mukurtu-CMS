#!/usr/bin/env bash
# The image: how the Dockerfile is shaped, what the `build` stage assembles (the
# mukurtu-template project with this checkout in place of the Packagist profile), and
# what the `runtime` stage that ships contains. See docs/docker-build.md.
set -u
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

IMAGE=mukurtu-test:image

echo "Dockerfile: shape"
dockerfile="$(<"$REPO_ROOT/Dockerfile")"
# The template version is pinned in one place, as the default of the one build argument.
assert_contains "$dockerfile" "ARG MUKURTU_TEMPLATE_VERSION=" "the template version is pinned by a default in the Dockerfile"
pinned="$(sed -n 's/^ARG MUKURTU_TEMPLATE_VERSION=//p' "$REPO_ROOT/Dockerfile" | head -1)"
[[ "$pinned" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[0-9A-Za-z.-]+)?$ ]] && pass "the pinned template version looks like a version ($pinned)" || fail "the pinned template version looks like a version (got '$pinned')"
assert_not_contains "$dockerfile" "AS guard" "there is no guard stage: the default replaces it"
# The media tools are part of every image. An option to leave them out would let a user
# build an image that silently lacks thumbnails, and there is no slim variant to publish.
for pattern in "WITH_FFMPEG" "WITH_POPPLER"; do
  assert_not_contains "$dockerfile" "$pattern" "the Dockerfile has no $pattern option"
done
assert_not_contains "$dockerfile" "healthcheck.sh" "the health check is inline, not a script"
[[ ! -e "$REPO_ROOT/docker/TEMPLATE_VERSION" ]] && pass "there is no separate TEMPLATE_VERSION file" || fail "there is no separate TEMPLATE_VERSION file"

BUILD_TAG=mukurtu-test:build

echo "build stage: assemble /app"
if ! build_image build "$BUILD_TAG" "${DEFAULT_BUILD_ARGS[@]}"; then
  fail "build stage builds"; tail -40 "$BUILD_LOG"; finish; exit 1
fi
pass "build stage builds"

build_in() { docker run --rm --entrypoint sh "$BUILD_TAG" -c "$1"; }

repo_version="$(<"$REPO_ROOT/VERSION.md")"
image_version="$(build_in 'cat /app/web/profiles/mukurtu/VERSION.md')"
assert_eq "$repo_version" "$image_version" "profile in /app is this checkout (VERSION.md matches)"

build_in 'test -x /app/vendor/bin/drush' && pass "drush is installed" || fail "drush is installed"
build_in 'test ! -e /app/vendor/bin/phpunit' && pass "no phpunit (no dev dependencies)" || fail "no phpunit (no dev dependencies)"
build_in 'test -f /app/composer.lock' && pass "composer.lock is kept" || fail "composer.lock is kept"
build_in 'test -f /app/web/profiles/mukurtu/mukurtu.info.yml' && pass "profile info file present" || fail "profile info file present"

leftover_git="$(build_in 'find /app/web/profiles/mukurtu /app/vendor -name .git -prune 2>/dev/null | head -3')"
assert_eq "" "$leftover_git" "no .git directories in profile or vendor"

leftover_tests="$(build_in 'find /app/web/core /app/web/profiles/mukurtu /app/vendor /app/web/modules/contrib /app/web/themes/contrib \( -name tests -o -name Tests \) -type d -prune 2>/dev/null | head -3')"
assert_eq "" "$leftover_tests" "no tests directories in core, profile, vendor or contrib"

build_in 'test -f /app/web/profiles/mukurtu/LICENSE.txt' && pass "licence file kept" || fail "licence file kept"

# The build runs this too; running it again here does not depend on BuildKit
# having shown the output of a cached layer.
if docker run --rm -w /app --entrypoint composer "$BUILD_TAG" audit --no-dev --abandoned=report >/dev/null 2>&1; then
  pass "composer audit finds no advisories"
else
  fail "composer audit finds no advisories"
fi

# Dependency patches (drupal/core, paragraphs, ...) are declared in the profile's
# composer.json and must still be applied when the profile is a path
# repository. Composer fails the build if a patch does not apply, and records
# what it applied in patches.lock.json.
patched="$(build_in "php -r '\$j = json_decode(file_get_contents(\"/app/patches.lock.json\"), true); echo implode(\",\", array_keys(\$j[\"patches\"]));'")"
assert_contains "$patched" "drupal/core" "profile patches are applied: drupal/core"
assert_contains "$patched" "drupal/paragraphs" "profile patches are applied: drupal/paragraphs"


echo "runtime stage: build"
if runtime_image "$IMAGE"; then
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
  for ext in gd intl zip "Zend OPcache" pdo_mysql mbstring apcu maxminddb curl dom fileinfo xml SimpleXML; do
    assert_contains "$modules" "$ext" "$tag has extension: $ext"
  done
  gd="$(run_in "$tag" "php -r 'foreach (gd_info() as \$k => \$v) { echo \$k, \"=\", var_export(\$v, true), \"\\n\"; }'")"
  assert_contains "$gd" "JPEG Support=true" "$tag gd: JPEG"
  assert_contains "$gd" "WebP Support=true" "$tag gd: WebP"
  assert_contains "$gd" "FreeType Support=true" "$tag gd: FreeType"
  assert_contains "$gd" "AVIF Support=true" "$tag gd: AVIF (the Status report flags its absence)"
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

echo "runtime: PDFs whose fonts are not embedded still produce a thumbnail"
# A one-page PDF that names the standard Helvetica font without embedding it, as many
# PDFs do. Without fonts in the image, pdftoppm logs "Couldn't find a font" and draws a
# blank page, while pdftotext still works, so the loss is easy to miss.
pdf_out="$(docker run --rm --entrypoint sh "$IMAGE" -c '
  cd /tmp
  content="BT /F1 48 Tf 20 30 Td (Mukurtu) Tj ET"
  printf "%%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 300 100]/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>endobj\n4 0 obj<</Length %d>>stream\n%s\nendstream endobj\n5 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj\ntrailer<</Root 1 0 R/Size 6>>\n%%%%EOF\n" "${#content}" "$content" > nofonts.pdf
  pdftoppm -png -singlefile -r 72 nofonts.pdf thumb 2>/tmp/pdftoppm.err
  echo "TEXT=$(pdftotext nofonts.pdf - 2>/dev/null | tr -d "\n\f")"
  echo "FONTERR=$(grep -c "find a font" /tmp/pdftoppm.err)"
  php -r "\$i = imagecreatefrompng(\"thumb.png\"); \$n = 0; for (\$y = 0; \$y < imagesy(\$i); \$y++) { for (\$x = 0; \$x < imagesx(\$i); \$x++) { if ((imagecolorat(\$i, \$x, \$y) & 0xFFFFFF) !== 0xFFFFFF) { \$n++; } } } echo \"INKED=\$n\n\";"
' 2>&1)"
assert_contains "$pdf_out" "TEXT=Mukurtu" "text extraction works on the font-less PDF"
assert_contains "$pdf_out" "FONTERR=0" "pdftoppm finds a font for Helvetica"
inked="$(sed -n 's/^INKED=//p' <<<"$pdf_out")"
[[ "${inked:-0}" -gt 200 ]] && pass "the thumbnail is not blank ($inked inked pixels)" || fail "the thumbnail is not blank (inked pixels: '${inked:-none}')"
check "$IMAGE" "has the CMap and encoding data for non-Latin PDFs (poppler-data)" 'test -d /usr/share/poppler'
check "$IMAGE" "has fontconfig" 'command -v fc-match'

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
