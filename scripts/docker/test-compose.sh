#!/usr/bin/env bash
# The Compose examples must be valid, hardened, and actually bring up working sites:
# the single-site file, and the hosting file (several sites behind a Caddy proxy).
# See docs/docker-build.md, "Hosting examples".
set -u
source "$(dirname "${BASH_SOURCE[0]}")/test-lib.sh"

command -v jq >/dev/null || { echo "jq is required"; exit 1; }

SINGLE="$REPO_ROOT/docker-compose.example.yml"
MULTI="$REPO_ROOT/docker-compose.multi-tenant.example.yml"
WORK="$(mktemp -d -t mukurtu-compose.XXXXXX)"
PROJECT="mukurtu-ctest-$$"
IMAGE_TAG=mukurtu-test:full
cleanup() {
  (cd "$WORK" && MUKURTU_IMAGE=$IMAGE_TAG docker compose --env-file .env -p "$PROJECT-one" -f "$WORK/$(basename "$SINGLE")" down -v >/dev/null 2>&1)
  (cd "$WORK" && MUKURTU_IMAGE=$IMAGE_TAG docker compose --env-file .env -p "$PROJECT-many" -f "$WORK/$(basename "$MULTI")" down -v >/dev/null 2>&1)
  rm -rf "$WORK" "$BUILD_LOG"
}
trap cleanup EXIT

# Environment and secret files the examples ask for. The hosting example is tried on
# *.localhost names, which Caddy serves with its own local certificate authority, and
# on high ports so no privileges are needed.
mkdir -p "$WORK/secrets"
for name in db_password db_root_password admin_password hash_salt; do
  printf 'test-%s-value' "$name" >"$WORK/secrets/$name.txt"
done
chmod -R a+rX "$WORK"
cat >"$WORK/.env" <<ENV
MUKURTU_HOST=localhost
MUKURTU_ADMIN_EMAIL=admin@example.org
ACME_EMAIL=ops@example.org
HTTP_PORT=8088
HTTPS_PORT=8443
SITE_A_HOST=a.localhost
SITE_B_HOST=b.localhost
SITE_A_DB_PASSWORD=aaa
SITE_B_DB_PASSWORD=bbb
DB_ROOT_PASSWORD=root
SITE_A_ADMIN_PASSWORD=Adm1n-a
SITE_B_ADMIN_PASSWORD=Adm1n-b
SITE_A_ADMIN_EMAIL=a@example.org
SITE_B_ADMIN_EMAIL=b@example.org
ENV
cp "$SINGLE" "$MULTI" "$WORK/" 2>/dev/null

compose_json() { (cd "$WORK" && docker compose --env-file .env -f "$1" config --format json 2>&1); }

echo "compose: both files are valid"
for file in "$SINGLE" "$MULTI"; do
  if [[ ! -f "$file" ]]; then fail "$(basename "$file") exists"; continue; fi
  if json="$(compose_json "$file")" && jq -e . >/dev/null <<<"$json"; then
    pass "$(basename "$file") is valid Compose"
  else
    fail "$(basename "$file") is valid Compose"; echo "$json" | head -5
  fi
done

echo "compose: every Mukurtu service is hardened and bounded"
check_services() { # check_services <file> <service names...>
  local file="$1" json svc
  shift
  json="$(compose_json "$file")" || return
  for svc in "$@"; do
    assert_eq "true" "$(jq -r ".services[\"$svc\"].read_only" <<<"$json")" "$(basename "$file"): $svc is read_only"
    assert_eq '["ALL"]' "$(jq -c ".services[\"$svc\"].cap_drop" <<<"$json")" "$(basename "$file"): $svc drops all capabilities"
    assert_contains "$(jq -c ".services[\"$svc\"].security_opt" <<<"$json")" "no-new-privileges:true" "$(basename "$file"): $svc sets no-new-privileges"
    local mem
    mem="$(jq -r ".services[\"$svc\"].mem_limit // \"\"" <<<"$json")"
    [[ -n "$mem" && "$mem" != null ]] && pass "$(basename "$file"): $svc has mem_limit ($mem)" || fail "$(basename "$file"): $svc has mem_limit"
    assert_contains "$(jq -c ".services[\"$svc\"].tmpfs" <<<"$json")" "/tmp" "$(basename "$file"): $svc has a /tmp tmpfs with a cap"
  done
}
[[ -f "$SINGLE" ]] && check_services "$SINGLE" mukurtu
[[ -f "$MULTI" ]] && check_services "$MULTI" site_a site_b

echo "compose: MariaDB is configured the way Drupal asks"
for file in "$SINGLE" "$MULTI"; do
  [[ -f "$file" ]] || continue
  cmd="$(compose_json "$file" | jq -c '.services.db.command')"
  assert_contains "$cmd" "--transaction-isolation=READ-COMMITTED" "$(basename "$file"): READ-COMMITTED"
  assert_contains "$cmd" "--binlog-format=ROW" "$(basename "$file"): binlog_format ROW"
  assert_contains "$cmd" "--max-allowed-packet=64M" "$(basename "$file"): max_allowed_packet"
  assert_contains "$cmd" "--character-set-server=utf8mb4" "$(basename "$file"): utf8mb4"
done

echo "compose: the hosting file routes two domains through Caddy and trusts only the proxy"
if [[ -f "$MULTI" ]]; then
  json="$(compose_json "$MULTI")"
  caddyfile="$(jq -r '.configs.caddyfile.content // ""' <<<"$json")"
  assert_contains "$caddyfile" "a.localhost" "the Caddyfile names site A's host"
  assert_contains "$caddyfile" "reverse_proxy site_a:8080" "site A's host goes to site_a"
  assert_contains "$caddyfile" "b.localhost" "the Caddyfile names site B's host"
  assert_contains "$caddyfile" "reverse_proxy site_b:8080" "site B's host goes to site_b"
  assert_eq "null" "$(jq -c '.services.site_a.ports' <<<"$json")" "site_a publishes no port of its own (only the proxy does)"
  assert_eq "null" "$(jq -c '.services.db.ports' <<<"$json")" "the database publishes no port"
  assert_eq "true" "$(jq -r '.services.site_a.environment.MUKURTU_REVERSE_PROXY' <<<"$json")" "site_a trusts a reverse proxy"
  assert_contains "$(jq -r '.services.site_a.environment.MUKURTU_REVERSE_PROXY_ADDRESSES' <<<"$json")" "/" "site_a lists the proxy network as a CIDR range"
  assert_eq "false" "$(jq -r 'any(.services | to_entries[]; .value.volumes[]?.source == "/var/run/docker.sock")' <<<"$json")" "no service mounts the Docker socket"
  [[ "$(jq -r '.services.site_a.environment.MUKURTU_DB_NAME' <<<"$json")" != "$(jq -r '.services.site_b.environment.MUKURTU_DB_NAME' <<<"$json")" ]] \
    && pass "the sites use different databases" || fail "the sites use different databases"
  # (Caddy is not read_only: Compose cannot place an inline Caddyfile in a read-only
  # service, and the single self-contained file is worth more than that one flag.)
  assert_eq '["ALL"]' "$(jq -c '.services.caddy.cap_drop' <<<"$json")" "caddy drops all capabilities"
  assert_eq '["NET_BIND_SERVICE"]' "$(jq -c '.services.caddy.cap_add' <<<"$json")" "caddy adds back only the right to bind ports 80 and 443"
  assert_contains "$(jq -c '.services.caddy.security_opt' <<<"$json")" "no-new-privileges:true" "caddy sets no-new-privileges"
fi

if ! build_image runtime "$IMAGE_TAG" "${DEFAULT_BUILD_ARGS[@]}"; then
  fail "image builds"; tail -20 "$BUILD_LOG"; finish; exit 1
fi

echo "compose: the single-site example brings up a working site"
if [[ -f "$SINGLE" ]]; then
  # Run the copy next to the secrets and .env, as a user would.
  dc() { (cd "$WORK" && MUKURTU_IMAGE=$IMAGE_TAG docker compose --env-file .env -p "$PROJECT-one" -f "$WORK/$(basename "$SINGLE")" "$@"); }
  dc up -d >/dev/null 2>&1
  port="$(dc port mukurtu 8080 2>/dev/null | sed 's/.*://')"
  ok=false
  for _ in $(seq 1 300); do
    if curl -sf -o /dev/null -H 'Host: localhost' "http://127.0.0.1:${port:-0}/user/login"; then ok=true; break; fi
    sleep 2
  done
  if $ok; then pass "the site installs and serves pages from the example (read-only root, tmpfs, secrets)"; else
    fail "the site installs and serves pages from the example"
    dc logs --tail 40 mukurtu 2>&1
  fi
  dc down -v >/dev/null 2>&1
fi

echo "compose: the hosting example brings up two sites behind Caddy"
if [[ -f "$MULTI" ]]; then
  mc() { (cd "$WORK" && MUKURTU_IMAGE=$IMAGE_TAG docker compose --env-file .env -p "$PROJECT-many" -f "$WORK/$(basename "$MULTI")" "$@"); }
  # get <host> <path>: through Caddy's TLS port, as a browser would reach that name.
  via_caddy() { curl -sk --max-time 20 --resolve "$1:8443:127.0.0.1" "https://$1:8443$2"; }
  code_via_caddy() { curl -sk --max-time 20 -o /dev/null -w '%{http_code}' --resolve "$1:8443:127.0.0.1" "https://$1:8443$2"; }
  mc up -d >/dev/null 2>&1
  ok=false
  for _ in $(seq 1 300); do
    if [[ "$(code_via_caddy a.localhost /user/login)" == 200 && "$(code_via_caddy b.localhost /user/login)" == 200 ]]; then ok=true; break; fi
    sleep 2
  done
  if $ok; then
    pass "both sites install and answer through Caddy"
    assert_contains "$(via_caddy a.localhost /)" "| Site A" "a.localhost is site A"
    assert_contains "$(via_caddy b.localhost /)" "| Site B" "b.localhost is site B"
    assert_not_contains "$(via_caddy a.localhost /)" "| Site B" "site A does not show site B"
    assert_contains "$(via_caddy a.localhost /)" "https://a.localhost" "Drupal knows it is served over HTTPS (X-Forwarded-Proto is trusted from the proxy)"
    if curl -sk --max-time 10 -o /dev/null --resolve "c.localhost:8443:127.0.0.1" "https://c.localhost:8443/"; then
      fail "an unknown host name is not served"
    else
      pass "an unknown host name is not served"
    fi
    # `docker compose port` prints nothing, or ":0", for a port that is not published.
    published="$(mc port site_a 8080 2>/dev/null | sed 's/^://')"
    [[ -z "$published" || "$published" == 0 ]] && pass "site A's port is not published on the host" || fail "site A's port is not published on the host (got '$published')"
    # The sites cannot reach each other's databases.
    assert_eq "denied" "$(mc exec -T db sh -c "mariadb -usite_a -paaa -e 'use site_b' 2>&1 | grep -q 'Access denied' && echo denied")" "site A's database user cannot open site B's database"
  else
    fail "both sites install and answer through Caddy"
    mc logs --tail 30 caddy site_a site_b 2>&1
  fi
fi

finish
