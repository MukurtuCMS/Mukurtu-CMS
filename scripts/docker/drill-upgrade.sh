#!/usr/bin/env bash
# Upgrade drill: install a site with the image of an earlier release, then start the
# current image on the same database and volumes, as an operator does when changing
# MUKURTU_IMAGE. Run it before a release; it is not part of the default test suite
# because it builds a second image and takes a long time.
#
#   drill-upgrade.sh <old-ref>      for example: drill-upgrade.sh 4.0.5
#
# The old image is built from the Mukurtu code at <old-ref> with the current Dockerfile
# and docker/ directory laid over it (a release that predates the Dockerfile has none of
# its own). The new image is the checkout, or MUKURTU_TEST_IMAGE if set.
# See docs/releasing.md, "Before a release: the manual checks".
set -u
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

OLD_REF="${1:-}"
if [[ -z "$OLD_REF" ]]; then
  echo "usage: $0 <old-ref>   (a tag or commit of the release to upgrade from)" >&2
  exit 2
fi

RUN_ID="$$"
NET="mukurtu-drill-net-$RUN_ID"
DB="mukurtu-drill-db-$RUN_ID"
SITE="mukurtu-drill-site-$RUN_ID"
PRIV="mukurtu-drill-priv-$RUN_ID"
PUB="mukurtu-drill-pub-$RUN_ID"
OLD_IMAGE="mukurtu-drill:old"
NEW_IMAGE="mukurtu-drill:new"
WORK="$(mktemp -d)"

cleanup() {
  docker rm -f "$SITE" "$DB" >/dev/null 2>&1
  docker volume rm -f "$PRIV" "$PUB" >/dev/null 2>&1
  docker network rm "$NET" >/dev/null 2>&1
  rm -rf "$WORK" "$BUILD_LOG"
}
trap cleanup EXIT

echo "drill: build the old image from $OLD_REF"
if ! git -C "$REPO_ROOT" rev-parse --verify --quiet "$OLD_REF^{commit}" >/dev/null; then
  fail "$OLD_REF is a tag or commit in this repository (fetch the release tags first)"; finish; exit 1
fi
git -C "$REPO_ROOT" archive "$OLD_REF" | tar -x -C "$WORK" || { fail "$OLD_REF can be exported"; finish; exit 1; }
# The current Docker files over the old code. The old tree's own copies, if any, go.
rm -rf "$WORK/docker" "$WORK/Dockerfile" "$WORK/.dockerignore"
cp -r "$REPO_ROOT/docker" "$REPO_ROOT/Dockerfile" "$REPO_ROOT/.dockerignore" "$WORK/"
if docker build --progress=plain --target runtime --tag "$OLD_IMAGE" "$WORK" >"$BUILD_LOG" 2>&1; then
  pass "the old image builds"
else
  fail "the old image builds"; tail -30 "$BUILD_LOG"; finish; exit 1
fi

echo "drill: the new image"
if runtime_image "$NEW_IMAGE"; then pass "the new image is ready"; else fail "the new image is ready"; tail -30 "$BUILD_LOG"; finish; exit 1; fi

docker network create "$NET" >/dev/null
docker run -d --name "$DB" --network "$NET" --network-alias "$DB" \
  -e MARIADB_ROOT_PASSWORD=root -e MARIADB_DATABASE=mukurtu -e MARIADB_USER=mukurtu -e MARIADB_PASSWORD=mukurtu \
  mariadb:11.8 --character-set-server=utf8mb4 --collation-server=utf8mb4_general_ci \
  --transaction-isolation=READ-COMMITTED --binlog-format=ROW >/dev/null
for _ in $(seq 1 60); do
  docker exec "$DB" mariadb -uroot -proot -e 'select 1' >/dev/null 2>&1 && break
  sleep 1
done

# start_site <image>: run the site on the shared database and volumes; wait for the login page.
start_site() {
  docker rm -f "$SITE" >/dev/null 2>&1
  docker run -d --name "$SITE" --network "$NET" \
    -e MUKURTU_DB_HOST="$DB" -e MUKURTU_DB_NAME=mukurtu -e MUKURTU_DB_USER=mukurtu -e MUKURTU_DB_PASSWORD=mukurtu \
    -e MUKURTU_TRUSTED_HOSTS=drill.example.org -e MUKURTU_HASH_SALT=drill-salt \
    -e MUKURTU_ADMIN_PASSWORD='Adm1n-p@ss!' -e MUKURTU_ADMIN_EMAIL=admin@example.org \
    -v "$PRIV:/var/www/files/private" -v "$PUB:/var/www/files/public" "$1" >/dev/null
  for _ in $(seq 1 300); do
    [[ "$(docker inspect -f '{{.State.Running}}' "$SITE")" == true ]] || return 1
    docker exec "$SITE" curl -sf -H 'Host: drill.example.org' -o /dev/null http://127.0.0.1:8080/user/login 2>/dev/null && return 0
    sleep 2
  done
  return 1
}
site_code() { docker exec "$SITE" curl -s -o /dev/null -w '%{http_code}' -H 'Host: drill.example.org' "http://127.0.0.1:8080$1"; }

echo "drill: install with the old image"
if start_site "$OLD_IMAGE"; then pass "the old image installs a site"; else fail "the old image installs a site"; docker logs "$SITE" 2>&1 | tail -30; finish; exit 1; fi
old_version="$(docker exec "$SITE" drush php:eval 'echo \Drupal::VERSION;' 2>/dev/null)"
docker exec "$SITE" drush user:create drilluser --mail=drill@example.org --password=Dr1ll-pass >/dev/null 2>&1
docker exec "$SITE" sh -c 'echo drill-public > /var/www/files/public/drill.txt'
users_before="$(docker exec "$DB" mariadb -uroot -proot -N -B mukurtu -e 'select count(*) from users_field_data' 2>/dev/null)"
docker stop "$SITE" >/dev/null

echo "drill: start the new image on the same data"
if start_site "$NEW_IMAGE"; then pass "the new image serves the old site"; else fail "the new image serves the old site"; docker logs "$SITE" 2>&1 | tail -40; finish; exit 1; fi
log="$(docker logs "$SITE" 2>&1)"
assert_contains "$log" "existing installation found" "the new image found the installation"
assert_not_contains "$log" "Installing Mukurtu" "the new image did not install over it"
assert_contains "$log" "pending database updates" "the log reports the pending updates"
assert_eq "200" "$(site_code /)" "the front page is served"
assert_eq "200" "$(site_code /user/login)" "the login page is served"
assert_eq "$users_before" "$(docker exec "$DB" mariadb -uroot -proot -N -B mukurtu -e 'select count(*) from users_field_data' 2>/dev/null)" "users are unchanged"
assert_eq "drill-public" "$(docker exec "$SITE" curl -s -H 'Host: drill.example.org' http://127.0.0.1:8080/sites/default/files/drill.txt)" "public files are unchanged"
assert_contains "$(docker exec "$SITE" drush updatedb:status 2>&1)" "No database updates required" "no database update is left pending"
requirements="$(docker exec "$SITE" drush core:requirements --severity=2 --format=list 2>&1 | grep -v '^$' || true)"
[[ -z "$requirements" ]] && pass "the Status report shows no errors" || fail "the Status report shows errors: $requirements"
echo "(upgraded from Drupal $old_version to $(docker exec "$SITE" drush php:eval 'echo \Drupal::VERSION;' 2>/dev/null))"

finish
