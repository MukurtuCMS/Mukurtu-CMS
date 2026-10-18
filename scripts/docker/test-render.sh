#!/usr/bin/env bash
# render-config.sh turns MUKURTU_* environment variables into php.ini,
# php-fpm.conf, nginx.conf and msmtprc. See docs/docker-build.md,
# "Configuration rendering".
set -u
source "$(dirname "${BASH_SOURCE[0]}")/test-lib.sh"

IMAGE=mukurtu-test:full
LIB=/usr/local/lib/mukurtu
OUT=/tmp/mukurtu-run

if ! build_image runtime "$IMAGE" "${DEFAULT_BUILD_ARGS[@]}"; then
  fail "runtime image builds"
  tail -30 "$BUILD_LOG"
  finish
  exit 1
fi

# render <VAR=value>...: run render-config.sh in the image with the given
# environment. Prints RC=<exit code>, then stderr, then each rendered file
# between "=== name" markers.
render() {
  local env_args=() kv
  for kv in "$@"; do env_args+=(-e "$kv"); done
  docker run --rm --entrypoint sh -e MUKURTU_RUN_DIR="$OUT" "${env_args[@]}" "$IMAGE" -c "
    $LIB/render-config.sh 2>/tmp/stderr; echo RC=\$?
    echo '=== stderr'; cat /tmp/stderr
    for f in php.ini cli/php.ini php-fpm.conf nginx.conf msmtprc smtp-password; do
      echo \"=== \$f\"; cat $OUT/\$f 2>/dev/null; echo
    done
    echo '=== mode msmtprc'; stat -c %a $OUT/msmtprc 2>/dev/null
    echo '=== mode smtp-password'; stat -c %a $OUT/smtp-password 2>/dev/null
  " 2>&1
}

# section <output> <name>: the text between "=== name" and the next marker.
section() {
  awk -v want="=== $2" '/^=== /{on = ($0 == want); next} on' <<<"$1"
}
rc() { sed -n 's/^RC=//p' <<<"$1" | head -1; }

echo "render: defaults"
out="$(render)"
assert_eq "0" "$(rc "$out")" "defaults: exits 0"
ini="$(section "$out" php.ini)"
assert_contains "$ini" "memory_limit = 512M" "defaults: memory_limit"
assert_contains "$ini" "upload_max_filesize = 100M" "defaults: upload_max_filesize"
assert_contains "$ini" "post_max_size = 100M" "defaults: post_max_size"
assert_contains "$ini" "opcache.memory_consumption = 128" "defaults: opcache memory"
assert_contains "$ini" "opcache.validate_timestamps = 0" "defaults: opcache validate_timestamps"
assert_contains "$ini" "opcache.jit = 0" "defaults: jit off"
fpm="$(section "$out" php-fpm.conf)"
assert_contains "$fpm" "pm = ondemand" "defaults: pm ondemand"
assert_contains "$fpm" "pm.max_children = 4" "defaults: max_children"
assert_contains "$fpm" "pm.process_idle_timeout = 10s" "defaults: idle timeout"
assert_contains "$fpm" "pm.max_requests = 500" "defaults: max_requests"
nginx="$(section "$out" nginx.conf)"
assert_contains "$nginx" "client_max_body_size 100M;" "defaults: nginx body size"
assert_contains "$nginx" "worker_processes 1;" "defaults: nginx workers"
assert_contains "$nginx" "listen 8080" "defaults: nginx port"
assert_contains "$nginx" "client_body_temp_path /tmp/nginx-body;" "defaults: request bodies are buffered under the upload temp path"
assert_contains "$nginx" "fastcgi_temp_path /tmp/nginx-fastcgi;" "defaults: large responses are buffered under the upload temp path"
out="$(render MUKURTU_FILE_TEMP_PATH=/var/www/files/tmp)"
assert_contains "$(section "$out" nginx.conf)" "client_body_temp_path /var/www/files/tmp/nginx-body;" "nginx buffering follows MUKURTU_FILE_TEMP_PATH"
out="$(render)"

echo "render: upload-one-var"
out="$(render MUKURTU_UPLOAD_MAX=250M)"
assert_contains "$(section "$out" php.ini)" "upload_max_filesize = 250M" "upload: php upload_max_filesize"
assert_contains "$(section "$out" php.ini)" "post_max_size = 250M" "upload: php post_max_size"
assert_contains "$(section "$out" nginx.conf)" "client_max_body_size 250M;" "upload: nginx client_max_body_size"

echo "render: dynamic-pm"
out="$(render MUKURTU_FPM_PM=dynamic)"
fpm="$(section "$out" php-fpm.conf)"
assert_contains "$fpm" "pm = dynamic" "dynamic: pm"
assert_contains "$fpm" "pm.start_servers = 2" "dynamic: start_servers"
assert_contains "$fpm" "pm.min_spare_servers = 1" "dynamic: min_spare_servers"
assert_contains "$fpm" "pm.max_spare_servers = 3" "dynamic: max_spare_servers"
assert_not_contains "$(awk '/^\[files\]/{exit} {print}' <<<"$fpm")" "pm.process_idle_timeout" "dynamic: www pool has no idle timeout"
out="$(render)"
assert_not_contains "$(section "$out" php-fpm.conf)" "pm.start_servers" "ondemand: no start_servers"

echo "render: validation"
out="$(render MUKURTU_UPLOAD_MAX=100MB)"
assert_eq "1" "$(rc "$out")" "invalid size: exits 1"
assert_contains "$(section "$out" stderr)" "ERROR: MUKURTU_UPLOAD_MAX" "invalid size: names the variable"
out="$(render MUKURTU_FPM_PM=sometimes)"
assert_eq "1" "$(rc "$out")" "invalid pm: exits 1"
assert_contains "$(section "$out" stderr)" "ERROR: MUKURTU_FPM_PM" "invalid pm: names the variable"
out="$(render MUKURTU_REVERSE_PROXY=true)"
assert_eq "1" "$(rc "$out")" "proxy without addresses: exits 1"
assert_contains "$(section "$out" stderr)" "MUKURTU_REVERSE_PROXY_ADDRESSES" "proxy without addresses: names the variable"
out="$(render MUKURTU_REVERSE_PROXY=maybe)"
assert_contains "$(section "$out" stderr)" "ERROR: MUKURTU_REVERSE_PROXY" "invalid bool: names the variable"
out="$(render MUKURTU_FPM_MAX_CHILDREN=0)"
assert_eq "1" "$(rc "$out")" "max_children 0: exits 1"
out="$(render MUKURTU_FILE_TEMP_PATH=relative/path)"
assert_eq "1" "$(rc "$out")" "relative temp path: exits 1"

echo "render: line breaks cannot inject configuration"
nl=$'\n'
out="$(render "MUKURTU_UPLOAD_MAX=100M${nl}evil = 1")"
assert_eq "1" "$(rc "$out")" "newline in a size: exits 1"
assert_contains "$(section "$out" stderr)" "MUKURTU_UPLOAD_MAX" "newline in a size: names the variable"
out="$(render MUKURTU_SMTP_HOST=smtp.example.org "MUKURTU_SMTP_USER=mailer${nl}host evil.example")"
assert_eq "1" "$(rc "$out")" "newline in the SMTP user: exits 1"
assert_contains "$(section "$out" stderr)" "MUKURTU_SMTP_USER" "newline in the SMTP user: names the variable"
out="$(render MUKURTU_SMTP_HOST=smtp.example.org "MUKURTU_SMTP_FROM=a@example.org${nl}host evil.example")"
assert_eq "1" "$(rc "$out")" "newline in the SMTP sender: exits 1"
out="$(render "MUKURTU_PHP_TIMEZONE=UTC${nl}evil = 1")"
assert_eq "1" "$(rc "$out")" "newline in the time zone: exits 1"

echo "render: file-secret"
out="$(docker run --rm --entrypoint sh -e MUKURTU_RUN_DIR="$OUT" \
  -e MUKURTU_SMTP_HOST=smtp.example.org -e MUKURTU_SMTP_USER=mailer \
  -e MUKURTU_SMTP_PASSWORD=from-env -e MUKURTU_SMTP_PASSWORD_FILE=/tmp/pw "$IMAGE" -c "
    printf 'from-file\n' >/tmp/pw; $LIB/render-config.sh; cat $OUT/smtp-password" 2>&1)"
assert_eq "from-file" "$out" "_FILE beats the plain variable, trailing newline stripped"

echo "render: special-chars"
secret='p$a"s'"'"'\w@#%'
out="$(render MUKURTU_SMTP_HOST=smtp.example.org MUKURTU_SMTP_USER=mailer "MUKURTU_SMTP_PASSWORD=$secret")"
assert_eq "0" "$(rc "$out")" "special chars: exits 0"
assert_eq "$secret" "$(section "$out" smtp-password)" "special chars: password stored byte-identical"
assert_not_contains "$(section "$out" msmtprc)" "$secret" "special chars: password is not written into msmtprc"
assert_contains "$(section "$out" msmtprc)" "passwordeval cat $OUT/smtp-password" "special chars: msmtprc reads the password from the file"
assert_eq "600" "$(section "$out" 'mode smtp-password')" "special chars: password file mode 0600"
assert_eq "600" "$(section "$out" 'mode msmtprc')" "special chars: msmtprc mode 0600"
assert_contains "$(section "$out" php.ini)" "sendmail_path = /usr/bin/msmtp --read-envelope-from -t -C $OUT/msmtprc" "special chars: php sendmail_path"

echo "render: no-smtp"
out="$(render)"
assert_eq "0" "$(rc "$out")" "no smtp: exits 0"
assert_contains "$(section "$out" stderr)" "MUKURTU_SMTP_HOST" "no smtp: warns on stderr"
assert_not_contains "$(section "$out" msmtprc)" "host " "no smtp: msmtprc has no host line"
assert_not_contains "$(section "$out" php.ini)" "sendmail_path" "no smtp: no sendmail_path"

echo "render: exec-allowed"
out="$(render)"
assert_not_contains "$(section "$out" php.ini)" "disable_functions" "php.ini never sets disable_functions"

echo "render: hardening-ini"
out="$(render)"
ini="$(section "$out" php.ini)"
for line in "expose_php = Off" "display_errors = Off" "cgi.fix_pathinfo = 0" "zend.assertions = -1" \
    "zend.exception_ignore_args = On" "allow_url_include = Off" "session.use_strict_mode = 1" \
    "session.cookie_httponly = 1" "opcache.save_comments = 1" "realpath_cache_size = 4096K" \
    "realpath_cache_ttl = 600" "opcache.interned_strings_buffer = 16" "apc.shm_size = 32M" \
    "date.timezone = UTC" "sys_temp_dir = /tmp" "upload_tmp_dir = /tmp"; do
  assert_contains "$ini" "$line" "php.ini: $line"
done
out="$(render MUKURTU_FILE_TEMP_PATH=/var/www/files/tmp)"
assert_contains "$(section "$out" php.ini)" "upload_tmp_dir = /var/www/files/tmp" "temp path follows MUKURTU_FILE_TEMP_PATH"

echo "render: fpm-env"
out="$(render MUKURTU_FPM_FILES_MAX_CHILDREN=3)"
fpm="$(section "$out" php-fpm.conf)"
assert_eq "2" "$(grep -c '^clear_env = no' <<<"$fpm")" "both pools keep the environment (clear_env = no)"
assert_contains "$fpm" "[www]" "pool www"
assert_contains "$fpm" "[files]" "pool files"
assert_contains "$fpm" "listen = $OUT/www.sock" "www socket"
assert_contains "$fpm" "listen = $OUT/files.sock" "files socket"
assert_contains "$fpm" "request_terminate_timeout = 120s" "www pool terminate timeout follows max execution time"
assert_contains "$fpm" "ping.path = /ping" "www pool ping"
assert_contains "$(awk '/^\[files\]/{on=1} on' <<<"$fpm")" "pm.max_children = 3" "files pool max_children from MUKURTU_FPM_FILES_MAX_CHILDREN"
assert_contains "$fpm" "env[MUKURTU_POOL] = files" "files pool is identifiable"

echo "render: cli-ini"
out="$(render MUKURTU_PHP_CLI_MEMORY_LIMIT=2G)"
cli="$(section "$out" cli/php.ini)"
assert_contains "$cli" "memory_limit = 2G" "cli ini memory_limit"
assert_contains "$cli" "max_execution_time = 0" "cli ini has no execution limit"
out="$(render)"
assert_contains "$(section "$out" cli/php.ini)" "memory_limit = 1G" "cli ini default memory_limit is 1G"
assert_contains "$(section "$out" php.ini)" "memory_limit = 512M" "web ini keeps its own memory_limit"

finish
