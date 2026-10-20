#!/usr/bin/env bash
# End-to-end behaviour of the container: boot sequence, install, restart,
# failure messages, supervision, cron and health. Needs Docker and takes
# several minutes (a real Mukurtu install runs). See docs/docker-build.md,
# "Startup, supervision and health".
set -u
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

FULL=mukurtu-test:image
RUN_ID="$$"
NET="mukurtu-test-net-$RUN_ID"
DB="mukurtu-test-db-$RUN_ID"
SITE="mukurtu-test-site-$RUN_ID"
SITE_PRIV="mukurtu-test-priv-$RUN_ID"
SITE_PUB="mukurtu-test-pub-$RUN_ID"
CLEANUP_CONTAINERS=("$SITE" "$DB")
CLEANUP_VOLUMES=("$SITE_PRIV" "$SITE_PUB")
CLEANUP_DIRS=()

cleanup() {
  local c v
  for c in "${CLEANUP_CONTAINERS[@]}"; do docker rm -f "$c" >/dev/null 2>&1; done
  for v in "${CLEANUP_VOLUMES[@]}"; do docker volume rm -f "$v" >/dev/null 2>&1; done
  docker network rm "$NET" >/dev/null 2>&1
  rm -f "$BUILD_LOG"
  for d in "${CLEANUP_DIRS[@]}"; do rm -rf "$d"; done
}
trap cleanup EXIT

if ! runtime_image "$FULL"; then
  fail "runtime image builds"; tail -30 "$BUILD_LOG"; finish; exit 1
fi

docker network create "$NET" >/dev/null

db_up() { # db_up <container> : start MariaDB on the test network, wait until it answers
  docker run -d --name "$1" --network "$NET" --network-alias "$1" \
    -e MARIADB_ROOT_PASSWORD=root -e MARIADB_DATABASE=mukurtu -e MARIADB_USER=mukurtu -e MARIADB_PASSWORD=mukurtu \
    mariadb:11.8 --character-set-server=utf8mb4 --collation-server=utf8mb4_general_ci \
    --transaction-isolation=READ-COMMITTED --binlog-format=ROW >/dev/null
  CLEANUP_CONTAINERS+=("$1")
  for _ in $(seq 1 60); do
    docker exec "$1" mariadb -uroot -proot -e 'select 1' >/dev/null 2>&1 && return 0
    sleep 1
  done
  return 1
}
sql() { docker exec "$DB" mariadb -uroot -proot -N -B mukurtu -e "$1" 2>/dev/null; }

COMMON_ENV=(
  -e MUKURTU_DB_HOST="$DB" -e MUKURTU_DB_NAME=mukurtu -e MUKURTU_DB_USER=mukurtu -e MUKURTU_DB_PASSWORD=mukurtu
  -e MUKURTU_TRUSTED_HOSTS=test.example.org -e MUKURTU_HASH_SALT=test-salt
)

# run_to_exit <image> <docker run args...>: run until it exits;
# print "EXIT=<code>" then the log.
run_to_exit() {
  local image="$1" name="mukurtu-test-x-$RANDOM"
  shift
  CLEANUP_CONTAINERS+=("$name")
  docker run -d --name "$name" --network "$NET" "$@" "$image" >/dev/null
  local code
  code="$(timeout 120 docker wait "$name")"
  echo "EXIT=$code"
  docker logs "$name" 2>&1
  docker rm -f "$name" >/dev/null 2>&1
}

db_up "$DB" || { fail "database starts"; finish; exit 1; }
pass "database starts"

echo "entrypoint: no-admin-password"
out="$(run_to_exit "$FULL" "${COMMON_ENV[@]}" -e MUKURTU_ADMIN_EMAIL=a@example.org)"
assert_contains "$out" "ERROR: MUKURTU_ADMIN_PASSWORD" "install without an admin password is refused, naming the variable"
assert_not_contains "$out" "EXIT=0" "install without an admin password exits non-zero"
assert_eq "" "$(sql 'show tables')" "nothing was installed"

echo "entrypoint: required variables"
out="$(run_to_exit "$FULL" -e MUKURTU_DB_HOST="$DB" -e MUKURTU_DB_NAME=mukurtu -e MUKURTU_DB_USER=mukurtu)"
assert_contains "$out" "ERROR: MUKURTU_DB_PASSWORD is required" "missing database password is reported by name"
out="$(run_to_exit "$FULL" -e MUKURTU_DB_HOST="$DB" -e MUKURTU_DB_NAME=mukurtu -e MUKURTU_DB_USER=mukurtu -e MUKURTU_DB_PASSWORD=mukurtu)"
assert_contains "$out" "ERROR: MUKURTU_TRUSTED_HOSTS is required" "missing trusted hosts is reported by name"

echo "entrypoint: db-never"
start=$SECONDS
out="$(run_to_exit "$FULL" -e MUKURTU_DB_HOST=no-such-host -e MUKURTU_DB_NAME=m -e MUKURTU_DB_USER=m -e MUKURTU_DB_PASSWORD=m \
  -e MUKURTU_TRUSTED_HOSTS=x.example.org -e MUKURTU_HASH_SALT=s -e MUKURTU_DB_WAIT_TIMEOUT=3 -e MUKURTU_AUTO_INSTALL=false)"
assert_contains "$out" "ERROR: database no-such-host:3306 not reachable after 3s" "unreachable database: clear timeout message"
assert_not_contains "$out" "EXIT=0" "unreachable database: exits non-zero"
[[ $((SECONDS - start)) -lt 40 ]] && pass "unreachable database: gives up in time" || fail "unreachable database: gives up in time"

echo "entrypoint: unwritable-private"
docker volume create "mukurtu-test-root-$RUN_ID" >/dev/null
CLEANUP_VOLUMES+=("mukurtu-test-root-$RUN_ID")
# A volume that is not empty keeps its ownership (Docker only copies the image
# directory's ownership into an empty volume on first use).
docker run --rm --user 0 --entrypoint sh -v "mukurtu-test-root-$RUN_ID:/d" "$FULL" -c 'touch /d/keep; chown 0:0 /d /d/keep; chmod 755 /d'
out="$(run_to_exit "$FULL" "${COMMON_ENV[@]}" -e MUKURTU_AUTO_INSTALL=false -v "mukurtu-test-root-$RUN_ID:/var/www/files/private")"
assert_contains "$out" "ERROR: /var/www/files/private is not writable by uid 1000" "unwritable private volume: message names the path and uid"
assert_not_contains "$out" "EXIT=0" "unwritable private volume: exits non-zero"

echo "entrypoint: mail warning"
mail_name="mukurtu-test-mail-$RUN_ID"
CLEANUP_CONTAINERS+=("$mail_name")
docker run -d --name "$mail_name" --network "$NET" "${COMMON_ENV[@]}" -e MUKURTU_AUTO_INSTALL=false "$FULL" >/dev/null
sleep 8
mail_log="$(docker logs "$mail_name" 2>&1)"
assert_contains "$mail_log" "WARNING: MUKURTU_SMTP_HOST is not set" "missing SMTP configuration is warned about"
assert_not_contains "$mail_log" "is not installed in this image" "no warning about missing media tools: they are always installed"
docker rm -f "$mail_name" >/dev/null

echo "entrypoint: generated-salt"
# No MUKURTU_HASH_SALT: the entrypoint makes one, keeps it in the private volume,
# and settings.php must use it, across restarts.
salt_app="mukurtu-test-salt-$RUN_ID"
CLEANUP_CONTAINERS+=("$salt_app")
docker run -d --name "$salt_app" --network "$NET" \
  -e MUKURTU_DB_HOST="$DB" -e MUKURTU_DB_NAME=mukurtu -e MUKURTU_DB_USER=mukurtu -e MUKURTU_DB_PASSWORD=mukurtu \
  -e MUKURTU_TRUSTED_HOSTS=x.example.org -e MUKURTU_AUTO_INSTALL=false "$FULL" >/dev/null
for _ in $(seq 1 40); do
  docker exec "$salt_app" test -s /var/www/files/private/.hash_salt 2>/dev/null && break
  sleep 1
done
salt_one="$(docker exec "$salt_app" cat /var/www/files/private/.hash_salt 2>/dev/null)"
[[ -n "$salt_one" && ${#salt_one} -ge 32 ]] && pass "a salt of at least 32 characters is generated" || fail "a salt of at least 32 characters is generated (got '${salt_one}')"
assert_eq "600" "$(docker exec "$salt_app" stat -c %a /var/www/files/private/.hash_salt)" "the salt file is private (mode 0600)"
settings_salt() { docker exec "$salt_app" php -r '$settings = []; $databases = []; $config = []; $app_root = "/var/www/html/web"; $site_path = "sites/default"; include "/var/www/html/web/sites/default/settings.php"; echo $settings["hash_salt"];'; }
assert_eq "$salt_one" "$(settings_salt)" "settings.php uses the generated salt"
docker restart "$salt_app" >/dev/null
for _ in $(seq 1 40); do
  docker exec "$salt_app" curl -sf http://127.0.0.1:8080/healthz >/dev/null 2>&1 && break
  sleep 1
done
assert_eq "$salt_one" "$(docker exec "$salt_app" cat /var/www/files/private/.hash_salt)" "a restart keeps the same salt"
assert_eq "$salt_one" "$(settings_salt)" "settings.php still uses it after the restart"
docker rm -f "$salt_app" >/dev/null

echo "entrypoint: db-late"
late_db="mukurtu-test-latedb-$RUN_ID"
late_app="mukurtu-test-lateapp-$RUN_ID"
CLEANUP_CONTAINERS+=("$late_app" "$late_db")
docker run -d --name "$late_app" --network "$NET" \
  -e MUKURTU_DB_HOST="$late_db" -e MUKURTU_DB_NAME=mukurtu -e MUKURTU_DB_USER=mukurtu -e MUKURTU_DB_PASSWORD=mukurtu \
  -e MUKURTU_TRUSTED_HOSTS=x.example.org -e MUKURTU_HASH_SALT=s -e MUKURTU_AUTO_INSTALL=false "$FULL" >/dev/null
sleep 4
db_up "$late_db" || fail "late database starts"
for _ in $(seq 1 40); do
  docker exec "$late_app" curl -sf http://127.0.0.1:8080/healthz >/dev/null 2>&1 && break
  sleep 1
done
assert_eq "pong" "$(docker exec "$late_app" curl -s http://127.0.0.1:8080/healthz)" "app waited for the database, then came up"
docker rm -f "$late_app" "$late_db" >/dev/null

echo "entrypoint: fresh-install (named volumes, empty database)"
docker run -d --name "$SITE" --network "$NET" "${COMMON_ENV[@]}" \
  -e MUKURTU_ADMIN_PASSWORD='Adm1n-p@ss!' -e MUKURTU_ADMIN_EMAIL=admin@example.org \
  -e MUKURTU_SITE_NAME="Test Site" -e MUKURTU_CRON_INTERVAL=5 \
  -v "$SITE_PRIV:/var/www/files/private" -v "$SITE_PUB:/var/www/files/public" "$FULL" >/dev/null
ready=false
for _ in $(seq 1 300); do
  if [[ "$(docker inspect -f '{{.State.Running}}' "$SITE")" != true ]]; then break; fi
  if docker exec "$SITE" curl -sf -H 'Host: test.example.org' -o /dev/null http://127.0.0.1:8080/user/login 2>/dev/null; then ready=true; break; fi
  sleep 2
done
if ! $ready; then
  fail "site installs and serves pages"; docker logs "$SITE" 2>&1 | tail -40; finish; exit 1
fi
pass "site installs and serves pages"

get() { docker exec "$SITE" curl -s -i --max-time 20 -H 'Host: test.example.org' "http://127.0.0.1:8080$1"; }
code() { docker exec "$SITE" curl -s -o /dev/null -w '%{http_code}' --max-time 20 -H "Host: ${2:-test.example.org}" "http://127.0.0.1:8080$1"; }

assert_contains "$(docker logs "$SITE" 2>&1)" "the database is empty, so nothing is dropped" "the install log explains Drush's 'DROP all tables' notice"
assert_eq "200" "$(code /)" "front page returns 200 (so php-fpm passes the environment to settings.php)"
assert_eq "200" "$(code /user/login)" "login page returns 200"
assert_contains "$(get /)" "Test Site" "front page shows the configured site name"
assert_eq "400" "$(code / evil.test)" "an untrusted Host header is refused"
assert_eq "" "$(sql "select count(*) from users_field_data where name='admin'" | grep -v '^1$')" "the admin account exists"
for _ in $(seq 1 60); do
  [[ "$(docker inspect -f '{{.State.Health.Status}}' "$SITE")" == healthy ]] && break
  sleep 2
done
assert_eq "healthy" "$(docker inspect -f '{{.State.Health.Status}}' "$SITE")" "container reports healthy"
# `docker exec` does not run the entrypoint; the drush wrapper must still know the address.
assert_contains "$(docker exec "$SITE" drush user:login 2>/dev/null)" "https://test.example.org/user/reset" "drush links use the site address, also through docker exec"
assert_eq "1G" "$(docker exec "$SITE" drush php:eval 'echo ini_get("memory_limit");' 2>/dev/null)" "Drush runs with MUKURTU_PHP_CLI_MEMORY_LIMIT"
assert_eq "512M" "$(docker exec "$SITE" php -d opcache.enable_cli=0 -r 'echo "512M";')" "php binary runs"

echo "entrypoint: security checks on the live site"
docker exec "$SITE" sh -c 'echo "<?php echo \"EXECUTED\";" > /var/www/files/public/x.php'
body="$(get /sites/default/files/x.php)"
assert_not_contains "$body" "EXECUTED" "a PHP file in the public files is not executed"
assert_contains "$(code /sites/default/files/x.php)" "404" "a PHP file in the public files returns 404"

echo "entrypoint: ping-health"
# The Dockerfile's HEALTHCHECK command, run by hand.
docker exec "$SITE" sh -c 'curl -fsS --max-time 4 http://127.0.0.1:8080/healthz | grep -q pong' && pass "the health check command succeeds" || fail "the health check command succeeds"
assert_not_contains "$(docker logs "$SITE" 2>&1)" "healthz" "the healthcheck does not appear in the access log"

echo "entrypoint: cron"
sleep 12
runs="$(docker logs "$SITE" 2>&1 | grep -c 'cron: run finished')"
[[ "$runs" -ge 2 ]] && pass "cron runs on the configured interval ($runs runs)" || fail "cron runs on the configured interval ($runs runs)"
assert_contains "$(docker exec "$SITE" drush state:get system.cron_last 2>/dev/null)" "1" "Drupal recorded a cron run"

echo "entrypoint: zombies"
docker exec "$SITE" sh -c 'sh -c "sleep 0.2 &" ; sleep 1'
assert_eq "0" "$(docker exec "$SITE" sh -c 'ps -o stat | grep -c Z')" "orphaned processes are reaped"
assert_contains "$(docker exec "$SITE" sh -c 'ps -o pid,args | sed -n 2p')" "tini" "tini is PID 1"

echo "entrypoint: restart-idempotent"
salt_before="$(docker exec "$SITE" cat /var/www/files/private/.hash_salt 2>/dev/null || echo none)"
users_before="$(sql 'select count(*) from users_field_data')"
docker restart "$SITE" >/dev/null
for _ in $(seq 1 120); do
  docker exec "$SITE" curl -sf -H 'Host: test.example.org' -o /dev/null http://127.0.0.1:8080/user/login 2>/dev/null && break
  sleep 2
done
assert_eq "200" "$(code /user/login)" "site is back after a restart"
assert_eq "$users_before" "$(sql 'select count(*) from users_field_data')" "restart did not reinstall (user count unchanged)"
assert_eq "$salt_before" "$(docker exec "$SITE" cat /var/www/files/private/.hash_salt 2>/dev/null || echo none)" "restart did not change the salt file"
restart_log="$(docker logs --since 60s "$SITE" 2>&1)"
assert_not_contains "$restart_log" "Installing Mukurtu" "restart log shows no installation"
assert_contains "$restart_log" "existing installation" "restart log says an installation exists"
assert_contains "$restart_log" "pending database updates:" "restart log lists the pending updates before they run"
[[ "$restart_log" == *"pending database updates:"*"running database updates"* ]] && pass "the list comes before the updates run" || fail "the list comes before the updates run"

echo "entrypoint: sigterm"
start=$SECONDS
docker stop -t 10 "$SITE" >/dev/null
code_exit="$(docker inspect -f '{{.State.ExitCode}}' "$SITE")"
[[ $((SECONDS - start)) -lt 10 ]] && pass "container stops within 10 seconds" || fail "container stops within 10 seconds"
[[ "$code_exit" == 0 || "$code_exit" == 143 ]] && pass "stop exit code is 0 or 143 ($code_exit)" || fail "stop exit code is 0 or 143 ($code_exit)"

echo "entrypoint: restore a backup onto a new host"
# The commands are the ones docs/docker.md gives under "Upgrades and backups" and
# "Moving a site to another host". The site is stopped, so the three parts are consistent.
BK="$(mktemp -d)"
CLEANUP_DIRS+=("$BK")
docker run --rm --user 1000 --entrypoint sh -v "$SITE_PUB:/d" "$FULL" -c 'echo public-probe > /d/restore-probe.txt'
docker run --rm --user 1000 --entrypoint sh -v "$SITE_PRIV:/d" "$FULL" -c 'echo private-probe > /d/restore-probe.txt'
docker exec "$DB" mariadb-dump --single-transaction -uroot -proot mukurtu > "$BK/mukurtu.sql"
docker run --rm -v "$SITE_PUB:/data:ro" -v "$BK:/backup" alpine tar czf /backup/public.tgz -C /data .
docker run --rm -v "$SITE_PRIV:/data:ro" -v "$BK:/backup" alpine tar czf /backup/private.tgz -C /data .
[[ -s "$BK/mukurtu.sql" && -s "$BK/public.tgz" && -s "$BK/private.tgz" ]] && pass "the backup commands produce a dump and two archives" || fail "the backup commands produce a dump and two archives"

DB2="mukurtu-test-db2-$RUN_ID"
NEW_PRIV="mukurtu-test-newpriv-$RUN_ID"
NEW_PUB="mukurtu-test-newpub-$RUN_ID"
NEW_SITE="mukurtu-test-newsite-$RUN_ID"
CLEANUP_VOLUMES+=("$NEW_PRIV" "$NEW_PUB")
CLEANUP_CONTAINERS+=("$NEW_SITE")
db_up "$DB2" || fail "the new database starts"
MAIL="mukurtu-test-mailpit-$RUN_ID"
CLEANUP_CONTAINERS+=("$MAIL")
docker run -d --name "$MAIL" --network "$NET" --network-alias "$MAIL" axllent/mailpit:v1.31.6 >/dev/null
docker exec -i "$DB2" mariadb -uroot -proot mukurtu < "$BK/mukurtu.sql"
docker run --rm -v "$NEW_PUB:/data" -v "$BK:/backup:ro" alpine sh -c 'tar xzf /backup/public.tgz -C /data && chown -R 1000:1000 /data'
docker run --rm -v "$NEW_PRIV:/data" -v "$BK:/backup:ro" alpine sh -c 'tar xzf /backup/private.tgz -C /data && chown -R 1000:1000 /data'
docker run -d --name "$NEW_SITE" --network "$NET" \
  -e MUKURTU_DB_HOST="$DB2" -e MUKURTU_DB_NAME=mukurtu -e MUKURTU_DB_USER=mukurtu -e MUKURTU_DB_PASSWORD=mukurtu \
  -e MUKURTU_TRUSTED_HOSTS=test.example.org -e MUKURTU_HASH_SALT=test-salt \
  -e MUKURTU_ADMIN_PASSWORD='Adm1n-p@ss!' -e MUKURTU_ADMIN_EMAIL=admin@example.org \
  -e MUKURTU_SMTP_HOST="$MAIL" -e MUKURTU_SMTP_PORT=1025 -e MUKURTU_SMTP_TLS=off -e MUKURTU_SMTP_FROM=sender@example.org \
  -v "$NEW_PRIV:/var/www/files/private" -v "$NEW_PUB:/var/www/files/public" "$FULL" >/dev/null
ready=false
for _ in $(seq 1 120); do
  [[ "$(docker inspect -f '{{.State.Running}}' "$NEW_SITE")" == true ]] || break
  docker exec "$NEW_SITE" curl -sf -H 'Host: test.example.org' -o /dev/null http://127.0.0.1:8080/user/login 2>/dev/null && { ready=true; break; }
  sleep 2
done
$ready && pass "the restored site serves pages" || { fail "the restored site serves pages"; docker logs "$NEW_SITE" 2>&1 | tail -20; }
restored_log="$(docker logs "$NEW_SITE" 2>&1)"
assert_contains "$restored_log" "existing installation" "the container recognises the restored database and does not install over it"
assert_not_contains "$restored_log" "Installing Mukurtu" "no installation ran on the restored site"
assert_eq "$users_before" "$(docker exec "$DB2" mariadb -uroot -proot -N -B mukurtu -e 'select count(*) from users_field_data' 2>/dev/null)" "the restored database has the same users"
assert_eq "public-probe" "$(docker exec "$NEW_SITE" curl -s -H 'Host: test.example.org' http://127.0.0.1:8080/sites/default/files/restore-probe.txt)" "a restored public file is served"
assert_eq "private-probe" "$(docker exec "$NEW_SITE" cat /var/www/files/private/restore-probe.txt)" "a restored private file is in place"
assert_contains "$(docker exec "$NEW_SITE" drush user:login 2>/dev/null)" "/user/reset" "the restored site can issue a login link (the salt works)"

echo "entrypoint: mail leaves through the configured SMTP server"
# Drupal's password-reset mail to the administrator, captured by Mailpit.
mail_send="$(docker exec "$NEW_SITE" drush php:eval '$u = \Drupal\user\Entity\User::load(1); var_dump(_user_mail_notify("password_reset", $u));' 2>&1)"
mail_json=""
for _ in $(seq 1 20); do
  mail_json="$(docker exec "$NEW_SITE" curl -s "http://$MAIL:8025/api/v1/messages" 2>/dev/null)"
  [[ "$mail_json" == *'"messages_count":0'* || -z "$mail_json" ]] || break
  sleep 1
done
assert_contains "$mail_json" "admin@example.org" "the password-reset mail reached the SMTP server, addressed to the administrator"
# Mailpit records the envelope sender (MAIL FROM) as a Return-Path header on the raw
# message, unless the message already has one (Drupal adds its own). So the sender is
# checked with a plain probe message through PHP's mail(), which uses the same sendmail_path.
docker exec "$NEW_SITE" php -r 'mail("b@example.org", "sender probe", "body", "From: a@example.org");' >/dev/null 2>&1
sleep 2
mail_latest="$(docker exec "$NEW_SITE" curl -s "http://$MAIL:8025/api/v1/message/latest/raw" 2>/dev/null)"
assert_contains "$mail_latest" "Return-Path: <sender@example.org>" "MUKURTU_SMTP_FROM sets the envelope sender"
if [[ "$mail_json" != *"admin@example.org"* || "$mail_latest" != *"Return-Path: <sender@example.org>"* ]]; then
  echo "--- drush: $mail_send"; echo "--- mailpit: $mail_latest"; docker logs "$NEW_SITE" 2>&1 | tail -15
fi
docker rm -f "$NEW_SITE" "$DB2" "$MAIL" >/dev/null 2>&1

echo "entrypoint: install in another language"
LOCALE_DB="mukurtu-test-localedb-$RUN_ID"
LOCALE_SITE="mukurtu-test-localesite-$RUN_ID"
CLEANUP_CONTAINERS+=("$LOCALE_SITE")
db_up "$LOCALE_DB" || fail "the locale database starts"
docker run -d --name "$LOCALE_SITE" --network "$NET" \
  -e MUKURTU_DB_HOST="$LOCALE_DB" -e MUKURTU_DB_NAME=mukurtu -e MUKURTU_DB_USER=mukurtu -e MUKURTU_DB_PASSWORD=mukurtu \
  -e MUKURTU_TRUSTED_HOSTS=test.example.org -e MUKURTU_HASH_SALT=test-salt \
  -e MUKURTU_ADMIN_PASSWORD='Adm1n-p@ss!' -e MUKURTU_ADMIN_EMAIL=admin@example.org -e MUKURTU_LOCALE=es "$FULL" >/dev/null
ready=false
for _ in $(seq 1 300); do
  [[ "$(docker inspect -f '{{.State.Running}}' "$LOCALE_SITE")" == true ]] || break
  docker exec "$LOCALE_SITE" curl -sf -H 'Host: test.example.org' -o /dev/null http://127.0.0.1:8080/user/login 2>/dev/null && { ready=true; break; }
  sleep 2
done
$ready && pass "a site installs with MUKURTU_LOCALE=es" || { fail "a site installs with MUKURTU_LOCALE=es"; docker logs "$LOCALE_SITE" 2>&1 | tail -20; }
assert_contains "$(docker exec "$LOCALE_SITE" curl -s -H 'Host: test.example.org' http://127.0.0.1:8080/user/login)" 'lang="es"' "the installed site's language is Spanish"
docker rm -f "$LOCALE_SITE" "$LOCALE_DB" >/dev/null 2>&1

finish
