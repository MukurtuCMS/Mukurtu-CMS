#!/usr/bin/env bash
# Backs up this site while it runs: the database, the public and private files, and this
# folder's settings (.env, secrets/, custom/ and docker-compose.yml).
#
#   ./backup.sh [folder]      the backups go in ./backups unless you name a folder
#
# Each run writes a new folder named after the date and time, then deletes backups older
# than KEEP_DAYS days (14 unless set, for example KEEP_DAYS=30 ./backup.sh). A run that
# fails leaves no partial backup, deletes nothing, and exits with an error.
#
# The backups stay on this server: copy them somewhere else too. To run it every night,
# and to restore, see README.md.
set -euo pipefail
cd "$(dirname "$0")"

DEST="${1:-backups}"
KEEP_DAYS="${KEEP_DAYS:-14}"
STAMP="$(date +%Y-%m-%d-%H%M%S)"
PART="$DEST/.incomplete-$STAMP"

fail() {
  echo "backup failed: $*" >&2
  rm -rf "$PART"
  exit 1
}

[[ "$KEEP_DAYS" =~ ^[0-9]+$ ]] || fail "KEEP_DAYS must be a whole number of days"
# The backup holds the passwords in secrets/, so only this server's administrator may read it.
umask 077
mkdir -p "$DEST"
mkdir "$PART" || fail "can't create $PART"

# The database first, then the files, so every file the database refers to is in the
# backup. --single-transaction makes a consistent copy without stopping the site.
docker compose exec -T db sh -c \
  'mariadb-dump --single-transaction -u root -p"$(cat /run/secrets/db_root_password)" "$MARIADB_DATABASE"' \
  | gzip >"$PART/mukurtu.sql.gz" || fail "the database dump (is the site running?)"
gzip -dc "$PART/mukurtu.sql.gz" | tail -n 1 | grep -q 'Dump completed' || fail "the database dump is incomplete"

for files in public private; do
  docker compose exec -T mukurtu tar czf - -C "/var/www/files/$files" . >"$PART/$files.tgz" \
    || fail "the $files files (is the site running?)"
  gzip -t "$PART/$files.tgz" || fail "the $files files archive is damaged"
done

tar czf "$PART/site.tgz" .env secrets custom docker-compose.yml || fail "the settings in this folder"

mv "$PART" "$DEST/$STAMP"
echo "backup written to $DEST/$STAMP ($(du -sh "$DEST/$STAMP" | awk '{print $1}'))"

# Only after a good backup: delete old ones, and partial ones a killed run left behind.
find "$DEST" -mindepth 1 -maxdepth 1 -type d -name '20*' -mtime +"$KEEP_DAYS" -exec rm -rf {} +
find "$DEST" -mindepth 1 -maxdepth 1 -type d -name '.incomplete-*' -mtime +1 -exec rm -rf {} +
