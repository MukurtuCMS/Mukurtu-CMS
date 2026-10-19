#!/bin/sh
# Container entrypoint (run under tini).
#
#   entrypoint.sh supervise      Boot the site: the default.
#   entrypoint.sh <command...>   Run a one-off command (for example `drush
#                                status`) with the same configuration and
#                                environment, without installing or updating.
#
# Boot sequence: validate and render configuration, check the volumes, wait for
# the database, install an empty database, run updates, then hand over to the
# supervisor. Every failure names the setting or path at fault and exits
# non-zero, so the container restarts or stops visibly instead of half-working.
set -eu

LIB=/usr/local/lib/mukurtu
. "$LIB/lib.sh"

log() { printf '[mukurtu] %s\n' "$*"; }

PRIVATE_DIR=/var/www/files/private
PUBLIC_DIR=/var/www/files/public

: "${MUKURTU_RUN_DIR:=/run/mukurtu}"
export MUKURTU_RUN_DIR

mode=${1:-supervise}

# --- 1. configuration -----------------------------------------------------
# Secrets first: from here on the plain variables hold the resolved values.
load_secret MUKURTU_DB_PASSWORD
load_secret MUKURTU_ADMIN_PASSWORD
load_secret MUKURTU_HASH_SALT

"$LIB/render-config.sh"

# Drush and every other command-line PHP use the rendered CLI ini.
PHPRC="$MUKURTU_RUN_DIR/cli"
export PHPRC

if [ "$mode" != supervise ]; then
  exec "$@"
fi

# --- 2. required settings --------------------------------------------------
for var in MUKURTU_DB_HOST MUKURTU_DB_NAME MUKURTU_DB_USER MUKURTU_DB_PASSWORD MUKURTU_TRUSTED_HOSTS; do
  require_env "$var"
done
: "${MUKURTU_DB_PORT:=3306}"
: "${MUKURTU_AUTO_INSTALL:=true}"
: "${MUKURTU_RUN_UPDATES:=true}"
: "${MUKURTU_CRON:=true}"
: "${MUKURTU_CRON_INTERVAL:=3600}"
: "${MUKURTU_DB_WAIT_TIMEOUT:=60}"
: "${MUKURTU_ADMIN_USER:=admin}"
: "${MUKURTU_SITE_NAME:=Mukurtu}"
: "${MUKURTU_LOCALE:=en}"
: "${MUKURTU_FILE_TEMP_PATH:=/tmp}"
export MUKURTU_DB_PORT
validate_bool MUKURTU_AUTO_INSTALL
validate_bool MUKURTU_RUN_UPDATES
validate_bool MUKURTU_CRON
validate_positive_int MUKURTU_CRON_INTERVAL
validate_positive_int MUKURTU_DB_WAIT_TIMEOUT

# --- 3. volumes -----------------------------------------------------------
uid=$(id -u)
for dir in "$PUBLIC_DIR" "$PRIVATE_DIR"; do
  if [ ! -d "$dir" ] || [ ! -w "$dir" ]; then
    die "$dir is not writable by uid $uid. Mount a volume that uid $uid can write to (for a bind mount: chown $uid:$uid on the host directory), or use a named volume."
  fi
done
mkdir -p "$MUKURTU_FILE_TEMP_PATH" 2>/dev/null || true
[ -w "$MUKURTU_FILE_TEMP_PATH" ] || die "MUKURTU_FILE_TEMP_PATH ($MUKURTU_FILE_TEMP_PATH) is not writable by uid $uid. Mount a tmpfs or volume there, or point the variable at a writable directory."

# --- 4. hash salt ---------------------------------------------------------
# An explicit salt is best for production. Without one, make one and keep it in
# the private volume so it survives restarts.
if [ -z "${MUKURTU_HASH_SALT:-}" ]; then
  if [ ! -s "$PRIVATE_DIR/.hash_salt" ]; then
    (umask 077; head -c 48 /dev/urandom | base64 | tr -d '\n=+/' > "$PRIVATE_DIR/.hash_salt")
    log "generated a hash salt in $PRIVATE_DIR/.hash_salt (set MUKURTU_HASH_SALT to manage it yourself)"
  fi
fi
mkdir -p "${MUKURTU_CONFIG_SYNC_DIR:-$PRIVATE_DIR/config-sync}" 2>/dev/null || true

# --- 5. database ----------------------------------------------------------
log "waiting for the database at $MUKURTU_DB_HOST:$MUKURTU_DB_PORT (up to ${MUKURTU_DB_WAIT_TIMEOUT}s)"
waited=0
until _db_error=$(php "$LIB/db.php" ping 2>&1); do
  if [ "$waited" -ge "$MUKURTU_DB_WAIT_TIMEOUT" ]; then
    die "database $MUKURTU_DB_HOST:$MUKURTU_DB_PORT not reachable after ${MUKURTU_DB_WAIT_TIMEOUT}s (last error: $_db_error)"
  fi
  sleep 1
  waited=$((waited + 1))
done
log "database is reachable"

# --- 6. install or update -------------------------------------------------
state=$(php "$LIB/db.php" state)
case "$state" in
  installed)
    log "existing installation found"
    if [ "$MUKURTU_RUN_UPDATES" = true ]; then
      log "running database updates"
      drush updatedb --yes
      drush cache:rebuild
    fi
    ;;
  empty)
    if [ "$MUKURTU_AUTO_INSTALL" = true ]; then
      require_env MUKURTU_ADMIN_PASSWORD
      require_env MUKURTU_ADMIN_EMAIL
      log "Installing Mukurtu into the empty database (this takes a few minutes). Drush prints a notice below that it will DROP all tables; the database is empty, so nothing is dropped."
      drush site:install mukurtu --yes \
        --account-name="$MUKURTU_ADMIN_USER" \
        --account-pass="$MUKURTU_ADMIN_PASSWORD" \
        --account-mail="$MUKURTU_ADMIN_EMAIL" \
        --site-name="$MUKURTU_SITE_NAME" \
        --site-mail="${MUKURTU_SITE_EMAIL:-$MUKURTU_ADMIN_EMAIL}" \
        --locale="$MUKURTU_LOCALE"
      log "installation finished"
    else
      warn "the database is empty and MUKURTU_AUTO_INSTALL=false: the site will not work until Mukurtu is installed (for example with: docker exec <container> drush site:install mukurtu)."
    fi
    ;;
  partial)
    die "database $MUKURTU_DB_NAME contains tables but not a complete Drupal installation. Refusing to install over it: empty the database, or restore it from a backup."
    ;;
  *)
    die "could not read the state of database $MUKURTU_DB_NAME"
    ;;
esac

# --- 7. run ---------------------------------------------------------------
log "starting"
exec "$LIB/supervise.sh"
