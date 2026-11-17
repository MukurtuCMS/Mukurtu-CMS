#!/usr/bin/env bash
# Restores a backup made by backup.sh into this folder's site, which must be new: its
# database must be empty. It never overwrites a site that has content.
#
#   ./restore.sh backups/2026-11-17-033000
#
# Before running it on a new server, unpack the backup's site.tgz into this folder, so the
# site gets its old passwords, settings and custom modules:  tar xzf <backup>/site.tgz
# See README.md.
set -euo pipefail
cd "$(dirname "$0")"

fail() {
  echo "restore failed: $*" >&2
  exit 1
}
# quiet <command...>: hide Docker's progress lines, but show them if the command fails.
quiet() {
  local out
  out="$("$@" 2>&1)" || { echo "$out" >&2; return 1; }
}

BACKUP="${1:-}"
[[ -n "$BACKUP" ]] || fail "name the backup folder, for example: ./restore.sh backups/2026-11-17-033000"
BACKUP="$(cd "$BACKUP" 2>/dev/null && pwd)" || fail "there is no folder $1"
for f in mukurtu.sql.gz public.tgz private.tgz; do
  [[ -s "$BACKUP/$f" ]] || fail "$BACKUP has no $f; is it a folder made by backup.sh?"
done

if docker compose ps --status running --services 2>/dev/null | grep -qx mukurtu; then
  fail "the site is running. Restore into a new, empty site instead (see README.md)"
fi

echo "restore: starting the database"
quiet docker compose up -d --wait db || fail "the database didn't start (docker compose logs db)"

# shellcheck disable=SC2016 # expanded inside the db container
db() { docker compose exec -T db sh -c 'mariadb -u root -p"$(cat /run/secrets/db_root_password)" -N -B "$MARIADB_DATABASE"'; }
tables="$(echo 'SHOW TABLES' | db | wc -l)" || fail "can't connect to the database"
[[ "$tables" -eq 0 ]] || fail "the database already has a site in it. Restore into a new, empty site instead (see README.md)"

echo "restore: loading the database"
gzip -dc "$BACKUP/mukurtu.sql.gz" | db \
  || fail "loading the database. To start again, run docker compose down -v in this folder (it deletes only this new site), then ./restore.sh again."

for files in public private; do
  echo "restore: unpacking the $files files"
  # As the site's own user, so the files belong to it.
  quiet docker compose run --rm --no-deps -T --entrypoint tar mukurtu xzf - -C "/var/www/files/$files" \
    <"$BACKUP/$files.tgz" || fail "unpacking the $files files"
done

echo "restore: starting the site"
quiet docker compose up -d mukurtu || fail "the site didn't start (docker compose logs mukurtu)"
echo "restored from $BACKUP. Check the log: docker compose logs -f mukurtu"
echo "It should say 'existing installation found', and end with '[mukurtu] starting'."
