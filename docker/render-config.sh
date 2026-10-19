#!/bin/sh
# Turn MUKURTU_* environment variables into the configuration files php-fpm,
# nginx, PHP and msmtp read. Runs at every container start, before anything else.
#
# Output goes to $MUKURTU_RUN_DIR (default /run/mukurtu), which may be a tmpfs:
# nothing outside it needs to be writable. Every variable is validated here, and
# the first invalid one stops the start with an error naming it.
set -eu

LIB_DIR=$(dirname "$0")
. "$LIB_DIR/lib.sh"

# --- defaults -------------------------------------------------------------
: "${MUKURTU_RUN_DIR:=/run/mukurtu}"
: "${MUKURTU_PHP_MEMORY_LIMIT:=512M}"
: "${MUKURTU_PHP_CLI_MEMORY_LIMIT:=1G}"
: "${MUKURTU_PHP_MAX_EXECUTION_TIME:=120}"
: "${MUKURTU_PHP_MAX_INPUT_VARS:=3000}"
: "${MUKURTU_PHP_TIMEZONE:=UTC}"
: "${MUKURTU_UPLOAD_MAX:=100M}"
: "${MUKURTU_FILE_TEMP_PATH:=/tmp}"
: "${MUKURTU_OPCACHE_MEMORY:=128}"
: "${MUKURTU_OPCACHE_MAX_FILES:=20000}"
: "${MUKURTU_APCU_MEMORY:=32M}"
: "${MUKURTU_FPM_PM:=ondemand}"
: "${MUKURTU_FPM_MAX_CHILDREN:=4}"
: "${MUKURTU_FPM_PROCESS_IDLE_TIMEOUT:=10s}"
: "${MUKURTU_FPM_START_SERVERS:=2}"
: "${MUKURTU_FPM_MIN_SPARE_SERVERS:=1}"
: "${MUKURTU_FPM_MAX_SPARE_SERVERS:=3}"
: "${MUKURTU_FPM_MAX_REQUESTS:=500}"
: "${MUKURTU_FPM_FILES_MAX_CHILDREN:=2}"
: "${MUKURTU_NGINX_WORKER_PROCESSES:=1}"
: "${MUKURTU_NGINX_WORKER_CONNECTIONS:=1024}"
: "${MUKURTU_NGINX_ACCESS_LOG:=true}"
: "${MUKURTU_REVERSE_PROXY:=false}"
: "${MUKURTU_REVERSE_PROXY_ADDRESSES:=}"
: "${MUKURTU_SMTP_HOST:=}"
: "${MUKURTU_SMTP_PORT:=587}"
: "${MUKURTU_SMTP_USER:=}"
: "${MUKURTU_SMTP_TLS:=starttls}"
: "${MUKURTU_SMTP_FROM:=}"
: "${MUKURTU_BASE_URL:=}"

# --- validation -----------------------------------------------------------
validate_abs_path MUKURTU_RUN_DIR
validate_memory_limit MUKURTU_PHP_MEMORY_LIMIT
validate_memory_limit MUKURTU_PHP_CLI_MEMORY_LIMIT
validate_positive_int MUKURTU_PHP_MAX_EXECUTION_TIME
validate_positive_int MUKURTU_PHP_MAX_INPUT_VARS
validate_timezone MUKURTU_PHP_TIMEZONE
validate_size MUKURTU_UPLOAD_MAX
validate_abs_path MUKURTU_FILE_TEMP_PATH
validate_positive_int MUKURTU_OPCACHE_MEMORY
validate_positive_int MUKURTU_OPCACHE_MAX_FILES
validate_size MUKURTU_APCU_MEMORY
validate_choice MUKURTU_FPM_PM ondemand dynamic static
validate_positive_int MUKURTU_FPM_MAX_CHILDREN
validate_duration MUKURTU_FPM_PROCESS_IDLE_TIMEOUT
validate_positive_int MUKURTU_FPM_START_SERVERS
validate_positive_int MUKURTU_FPM_MIN_SPARE_SERVERS
validate_positive_int MUKURTU_FPM_MAX_SPARE_SERVERS
validate_int MUKURTU_FPM_MAX_REQUESTS
validate_positive_int MUKURTU_FPM_FILES_MAX_CHILDREN
validate_positive_int MUKURTU_NGINX_WORKER_PROCESSES
validate_positive_int MUKURTU_NGINX_WORKER_CONNECTIONS
validate_bool MUKURTU_NGINX_ACCESS_LOG
validate_bool MUKURTU_REVERSE_PROXY
validate_positive_int MUKURTU_SMTP_PORT
validate_choice MUKURTU_SMTP_TLS starttls tls off
[ -z "$MUKURTU_BASE_URL" ] || validate_url MUKURTU_BASE_URL

if [ "$MUKURTU_REVERSE_PROXY" = true ] && [ -z "$MUKURTU_REVERSE_PROXY_ADDRESSES" ]; then
  die "MUKURTU_REVERSE_PROXY_ADDRESSES is required when MUKURTU_REVERSE_PROXY=true (a comma-separated list of the proxy's IP addresses or CIDR ranges)"
fi

# --- php-fpm pool settings that depend on the process manager -------------
case "$MUKURTU_FPM_PM" in
  ondemand)
    MUKURTU_FPM_PM_SETTINGS="pm.process_idle_timeout = $MUKURTU_FPM_PROCESS_IDLE_TIMEOUT"
    ;;
  dynamic)
    MUKURTU_FPM_PM_SETTINGS="pm.start_servers = $MUKURTU_FPM_START_SERVERS
pm.min_spare_servers = $MUKURTU_FPM_MIN_SPARE_SERVERS
pm.max_spare_servers = $MUKURTU_FPM_MAX_SPARE_SERVERS"
    ;;
  static)
    MUKURTU_FPM_PM_SETTINGS="; pm = static: pm.max_children workers are always running"
    ;;
esac

# --- nginx access log -----------------------------------------------------
if [ "$MUKURTU_NGINX_ACCESS_LOG" = true ]; then
  MUKURTU_NGINX_ACCESS_LOG_DIRECTIVE="access_log /dev/stdout;"
else
  MUKURTU_NGINX_ACCESS_LOG_DIRECTIVE="access_log off;"
fi

# --- outbound mail (msmtp) ------------------------------------------------
umask 077
mkdir -p "$MUKURTU_RUN_DIR/cli"

if [ -z "$MUKURTU_SMTP_HOST" ]; then
  warn "MUKURTU_SMTP_HOST is not set: the site cannot send mail (password resets, notifications)."
  MUKURTU_MSMTP_SETTINGS="# MUKURTU_SMTP_HOST is not set: outbound mail is not configured."
  # Mail still fails, but with a message that names the cause.
  MUKURTU_PHP_MAIL_SETTINGS="sendmail_path = /usr/local/lib/mukurtu/no-mail"
else
  _validate MUKURTU_SMTP_HOST '^[A-Za-z0-9.-]+$' "a host name or IP address"
  [ -z "$MUKURTU_SMTP_USER" ] || validate_no_spaces MUKURTU_SMTP_USER
  [ -z "$MUKURTU_SMTP_FROM" ] || validate_no_spaces MUKURTU_SMTP_FROM

  load_secret MUKURTU_SMTP_PASSWORD
  _tls=on _starttls=on
  case "$MUKURTU_SMTP_TLS" in
    tls) _starttls=off ;;
    off) _tls=off _starttls=off ;;
  esac

  # The password sits in its own 0600 file and msmtp is told to read it, so no
  # quoting rule of the msmtprc format can alter it.
  printf '%s' "$MUKURTU_SMTP_PASSWORD" > "$MUKURTU_RUN_DIR/smtp-password"

  MUKURTU_MSMTP_SETTINGS="defaults
tls $_tls
tls_starttls $_starttls
tls_trust_file /etc/ssl/certs/ca-certificates.crt

account default
host $MUKURTU_SMTP_HOST
port $MUKURTU_SMTP_PORT"
  if [ -n "$MUKURTU_SMTP_USER" ]; then
    MUKURTU_MSMTP_SETTINGS="$MUKURTU_MSMTP_SETTINGS
auth on
user $MUKURTU_SMTP_USER
passwordeval cat $MUKURTU_RUN_DIR/smtp-password"
  fi
  if [ -n "$MUKURTU_SMTP_FROM" ]; then
    MUKURTU_MSMTP_SETTINGS="$MUKURTU_MSMTP_SETTINGS
from $MUKURTU_SMTP_FROM"
  fi
  MUKURTU_PHP_MAIL_SETTINGS="sendmail_path = /usr/bin/msmtp --read-envelope-from -t -C $MUKURTU_RUN_DIR/msmtprc"
fi

export MUKURTU_RUN_DIR MUKURTU_PHP_MEMORY_LIMIT MUKURTU_PHP_MAX_EXECUTION_TIME \
  MUKURTU_PHP_MAX_INPUT_VARS MUKURTU_PHP_TIMEZONE MUKURTU_UPLOAD_MAX MUKURTU_FILE_TEMP_PATH \
  MUKURTU_OPCACHE_MEMORY MUKURTU_OPCACHE_MAX_FILES MUKURTU_APCU_MEMORY MUKURTU_FPM_PM \
  MUKURTU_FPM_MAX_CHILDREN MUKURTU_FPM_PROCESS_IDLE_TIMEOUT MUKURTU_FPM_MAX_REQUESTS \
  MUKURTU_FPM_FILES_MAX_CHILDREN MUKURTU_FPM_PM_SETTINGS MUKURTU_NGINX_WORKER_PROCESSES \
  MUKURTU_NGINX_WORKER_CONNECTIONS MUKURTU_NGINX_ACCESS_LOG_DIRECTIVE \
  MUKURTU_MSMTP_SETTINGS MUKURTU_PHP_MAIL_SETTINGS

# --- the address command-line tools write into links -----------------------
# Cron mail and `drush user:login` need the site's public address. It is MUKURTU_BASE_URL,
# or https:// plus the first trusted host. It is written to a file that the `drush`
# wrapper reads, so it applies however Drush is started (including `docker exec`,
# which does not run the entrypoint).
mkdir -p "$MUKURTU_RUN_DIR"
rm -f "$MUKURTU_RUN_DIR/drush-uri"
_drush_uri=$MUKURTU_BASE_URL
if [ -z "$_drush_uri" ]; then
  _first_host=$(printf '%s' "${MUKURTU_TRUSTED_HOSTS:-}" | cut -d, -f1 | tr -d ' ')
  if printf '%s' "$_first_host" | grep -Eq '^[A-Za-z0-9][A-Za-z0-9.-]*$'; then
    _drush_uri="https://$_first_host"
  fi
fi
[ -z "$_drush_uri" ] || printf '%s\n' "$_drush_uri" > "$MUKURTU_RUN_DIR/drush-uri"

# --- render ---------------------------------------------------------------
TEMPLATES="$LIB_DIR/templates"
mkdir -p "$MUKURTU_RUN_DIR" \
  "$MUKURTU_RUN_DIR/nginx-body" "$MUKURTU_RUN_DIR/nginx-proxy" "$MUKURTU_RUN_DIR/nginx-fastcgi" \
  "$MUKURTU_RUN_DIR/nginx-uwsgi" "$MUKURTU_RUN_DIR/nginx-scgi"

render_template "$TEMPLATES/php.ini.tmpl" "$MUKURTU_RUN_DIR/php.ini"
render_template "$TEMPLATES/php-fpm.conf.tmpl" "$MUKURTU_RUN_DIR/php-fpm.conf"
render_template "$TEMPLATES/nginx.conf.tmpl" "$MUKURTU_RUN_DIR/nginx.conf"
render_template "$TEMPLATES/msmtprc.tmpl" "$MUKURTU_RUN_DIR/msmtprc"

# The command line (Drush: install, updates, cron) has no php.ini of its own in
# this image, so it would run with PHP's 128M default. It gets the same file
# with its own, larger memory limit and no execution time limit.
{
  cat "$MUKURTU_RUN_DIR/php.ini"
  printf '\n; --- command line overrides ---\nmemory_limit = %s\nmax_execution_time = 0\n' "$MUKURTU_PHP_CLI_MEMORY_LIMIT"
} > "$MUKURTU_RUN_DIR/cli/php.ini"
