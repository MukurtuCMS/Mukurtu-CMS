#!/bin/sh
# Prepares this example: creates .env from .env.example and fills every empty *_PASSWORD
# with a random value. Safe to run again: it keeps a password that is already set.
set -eu
cd "$(dirname "$0")"

random() { head -c "$1" /dev/urandom | base64 | tr -d '\n=+/'; }

[ -f .env ] || cp .env.example .env

for name in $(grep -E '^[A-Z0-9_]*PASSWORD=$' .env | cut -d= -f1); do
  # sed -i.bak works with both GNU and BSD sed.
  sed -i.bak "s|^$name=\$|$name=$(random 24)|" .env
  rm -f .env.bak
done
umask 077
chmod 600 .env

echo "Created .env with random passwords (existing values were kept)."
echo "Edit .env if you need to, then run:  docker compose up -d"
for site in A B; do
  echo "Site $site: admin / $(grep -E "^SITE_${site}_ADMIN_PASSWORD=" .env | cut -d= -f2-)"
done
