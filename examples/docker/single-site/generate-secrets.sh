#!/bin/sh
# Prepares this example: creates .env from .env.example, and the secrets/ folder with
# random passwords. Safe to run again: it never overwrites a file that already exists.
set -eu
cd "$(dirname "$0")"

random() { head -c "$1" /dev/urandom | base64 | tr -d '\n=+/'; }

[ -f .env ] || cp .env.example .env

mkdir -p secrets
umask 022
[ -s secrets/db_password.txt ]      || random 24 > secrets/db_password.txt
[ -s secrets/db_root_password.txt ] || random 24 > secrets/db_root_password.txt
[ -s secrets/hash_salt.txt ]        || random 48 > secrets/hash_salt.txt
[ -s secrets/admin_password.txt ]   || random 16 > secrets/admin_password.txt
# The container runs as uid 1000 and must be able to read these (Docker mounts them as
# files with the permissions they have here).
chmod 644 secrets/*.txt

echo "Created .env and secrets/ (existing files were kept)."
echo "Edit .env if you need to, then run:  docker compose up -d"
echo "Sign in as admin with the password in secrets/admin_password.txt:"
echo "  $(cat secrets/admin_password.txt)"
