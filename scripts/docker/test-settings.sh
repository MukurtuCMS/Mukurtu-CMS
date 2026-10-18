#!/usr/bin/env bash
# docker/settings.php reads all site configuration from MUKURTU_* environment
# variables. See docs/docker-build.md, "Drupal settings".
set -u
source "$(dirname "${BASH_SOURCE[0]}")/test-lib.sh"

IMAGE=mukurtu-test:full
HARNESS="$(mktemp -t mukurtu-harness.XXXXXX)"
trap 'rm -f "$HARNESS" "$BUILD_LOG"' EXIT

if ! build_image runtime "$IMAGE" "${DEFAULT_BUILD_ARGS[@]}"; then
  fail "runtime image builds"; tail -30 "$BUILD_LOG"; finish; exit 1
fi

# Include settings.php the way Drupal does and print what it defined.
cat >"$HARNESS" <<'PHP'
<?php
$app_root = '/var/www/html/web';
$site_path = 'sites/default';
$databases = [];
$settings = [];
$config = [];
try {
  include '/var/www/html/web/sites/default/settings.php';
  echo json_encode(['databases' => $databases, 'settings' => $settings, 'config' => $config]);
}
catch (Throwable $e) {
  echo json_encode(['exception' => get_class($e) . ': ' . $e->getMessage()]);
}
PHP
chmod a+r "$HARNESS"

BASE_ENV=(
  MUKURTU_DB_HOST=db MUKURTU_DB_NAME=mukurtu MUKURTU_DB_USER=mukurtu MUKURTU_DB_PASSWORD=secret
  MUKURTU_TRUSTED_HOSTS=example.com MUKURTU_HASH_SALT=salt
)

# settings [-s "shell run before php"] -- <VAR=value>...  (BASE_ENV is added
# first; later values override). Prints the harness JSON.
settings() {
  local pre=""
  if [[ "${1:-}" == "-s" ]]; then pre="$2"; shift 2; fi
  local env_args=() kv
  for kv in "${BASE_ENV[@]}" "$@"; do env_args+=(-e "$kv"); done
  docker run --rm --entrypoint sh -v "$HARNESS:/harness.php:ro" "${env_args[@]}" "$IMAGE" -c "$pre php /harness.php"
}

# without <VAR>: BASE_ENV minus one variable
settings_without() {
  local drop="$1" saved=("${BASE_ENV[@]}") kept=() kv
  for kv in "${BASE_ENV[@]}"; do [[ "$kv" == "$drop="* ]] || kept+=("$kv"); done
  BASE_ENV=("${kept[@]}")
  settings "${@:2}"
  BASE_ENV=("${saved[@]}")
}

# jget <json> <python expression over d>
jget() { python3 -c 'import json,sys; d=json.loads(sys.argv[1]); print(eval(sys.argv[2]), end="")' "$1" "$2"; }

echo "settings: database"
out="$(settings MUKURTU_DB_PORT=3307 MUKURTU_DB_PREFIX=mk_)"
db='d["databases"]["default"]["default"]'
assert_eq "mukurtu" "$(jget "$out" "$db['database']")" "database name"
assert_eq "mukurtu" "$(jget "$out" "$db['username']")" "database user"
assert_eq "secret" "$(jget "$out" "$db['password']")" "database password"
assert_eq "db" "$(jget "$out" "$db['host']")" "database host"
assert_eq "3307" "$(jget "$out" "$db['port']")" "database port"
assert_eq "mk_" "$(jget "$out" "$db['prefix']")" "database prefix"
assert_eq "mysql" "$(jget "$out" "$db['driver']")" "database driver"
assert_eq "utf8mb4_general_ci" "$(jget "$out" "$db['collation']")" "database collation"
out="$(settings)"
assert_eq "3306" "$(jget "$out" "$db['port']")" "database port defaults to 3306"
assert_eq "" "$(jget "$out" "$db['prefix']")" "database prefix defaults to empty"

echo "settings: special characters survive"
pw='p$a"s'"'"'\w@#%&'
out="$(settings "MUKURTU_DB_PASSWORD=$pw")"
assert_eq "$pw" "$(jget "$out" "$db['password']")" "database password is byte-identical"

echo "settings: _FILE beats the plain variable"
out="$(settings -s "printf 'from-file\n' >/tmp/pw;" MUKURTU_DB_PASSWORD=from-env MUKURTU_DB_PASSWORD_FILE=/tmp/pw)"
assert_eq "from-file" "$(jget "$out" "$db['password']")" "DB password file wins, trailing newline dropped"

echo "settings: required variables"
for var in MUKURTU_DB_HOST MUKURTU_DB_NAME MUKURTU_DB_USER MUKURTU_DB_PASSWORD MUKURTU_TRUSTED_HOSTS; do
  out="$(settings_without "$var")"
  assert_contains "$(jget "$out" 'd.get("exception","")')" "$var" "missing $var is reported by name"
done

echo "settings: hash salt"
out="$(settings MUKURTU_HASH_SALT=from-env)"
assert_eq "from-env" "$(jget "$out" 'd["settings"]["hash_salt"]')" "salt from MUKURTU_HASH_SALT"
out="$(settings -s "printf 'from-file\n' >/tmp/salt;" MUKURTU_HASH_SALT_FILE=/tmp/salt)"
assert_eq "from-file" "$(jget "$out" 'd["settings"]["hash_salt"]')" "salt from MUKURTU_HASH_SALT_FILE"
out="$(settings_without MUKURTU_HASH_SALT -s "printf 'persisted\n' >/var/www/files/private/.hash_salt;")"
assert_eq "persisted" "$(jget "$out" 'd["settings"]["hash_salt"]')" "salt from the persisted file in the private volume"
out="$(settings_without MUKURTU_HASH_SALT)"
assert_contains "$(jget "$out" 'd.get("exception","")')" "MUKURTU_HASH_SALT" "no salt anywhere is reported by name"

echo "settings: trusted hosts"
out="$(settings MUKURTU_TRUSTED_HOSTS=example.com,www.example.com)"
patterns="$(jget "$out" 'json.dumps(d["settings"]["trusted_host_patterns"])')"
matches() { # matches <patterns json> <host> -> yes|no   (Symfony wraps each pattern as {pattern}i)
  php_out="$(docker run --rm --entrypoint php "$IMAGE" -r '
    $p = json_decode($argv[1], true); $h = $argv[2]; $m = false;
    foreach ($p as $x) { if (preg_match("{" . $x . "}i", $h)) { $m = true; } }
    echo $m ? "yes" : "no";' "$1" "$2")"
  echo "$php_out"
}
assert_eq "yes" "$(matches "$patterns" example.com)" "example.com is trusted"
assert_eq "yes" "$(matches "$patterns" www.example.com)" "www.example.com is trusted"
assert_eq "yes" "$(matches "$patterns" Example.COM)" "host matching ignores case"
assert_eq "no" "$(matches "$patterns" exampleXcom)" "exampleXcom is not trusted (dots are literal)"
assert_eq "no" "$(matches "$patterns" example.com.evil.org)" "example.com.evil.org is not trusted"
assert_eq "no" "$(matches "$patterns" evil-example.com)" "evil-example.com is not trusted"
assert_eq "no" "$(matches "$patterns" other.example.com)" "other.example.com is not trusted without a wildcard"
out="$(settings 'MUKURTU_TRUSTED_HOSTS=*.example.com, example.com')"
patterns="$(jget "$out" 'json.dumps(d["settings"]["trusted_host_patterns"])')"
assert_eq "yes" "$(matches "$patterns" a.b.example.com)" "*.example.com trusts any subdomain"
assert_eq "yes" "$(matches "$patterns" example.com)" "whitespace around entries is ignored"
assert_eq "no" "$(matches "$patterns" example.com.evil.org)" "wildcard does not trust a suffix attack"

echo "settings: reverse proxy"
out="$(settings)"
assert_eq "None" "$(jget "$out" 'd["settings"].get("reverse_proxy")')" "reverse proxy is off by default"
out="$(settings MUKURTU_REVERSE_PROXY=true 'MUKURTU_REVERSE_PROXY_ADDRESSES=10.0.0.0/8, 172.16.0.1')"
assert_eq "True" "$(jget "$out" 'd["settings"]["reverse_proxy"]')" "reverse proxy on"
assert_eq '["10.0.0.0/8", "172.16.0.1"]' "$(jget "$out" 'json.dumps(d["settings"]["reverse_proxy_addresses"])')" "proxy addresses split and trimmed"

echo "settings: paths and Drupal flags"
out="$(settings)"
assert_eq "/var/www/files/private" "$(jget "$out" 'd["settings"]["file_private_path"]')" "private path"
assert_eq "/tmp" "$(jget "$out" 'd["settings"]["file_temp_path"]')" "temp path defaults to /tmp"
assert_eq "/var/www/files/private/config-sync" "$(jget "$out" 'd["settings"]["config_sync_directory"]')" "config sync directory"
assert_eq "True" "$(jget "$out" 'd["settings"]["skip_permissions_hardening"]')" "permissions hardening skipped (read-only code)"
assert_eq "False" "$(jget "$out" 'd["settings"]["class_loader_auto_detect"]')" "class loader auto-detect off"
assert_eq "hide" "$(jget "$out" 'd["config"]["system.logging"]["error_level"]')" "errors are not shown on screen"
out="$(settings MUKURTU_FILE_TEMP_PATH=/var/www/files/tmp MUKURTU_CONFIG_SYNC_DIR=/data/sync)"
assert_eq "/var/www/files/tmp" "$(jget "$out" 'd["settings"]["file_temp_path"]')" "temp path override"
assert_eq "/data/sync" "$(jget "$out" 'd["settings"]["config_sync_directory"]')" "config sync directory override"

finish
