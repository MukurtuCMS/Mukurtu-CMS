#!/usr/bin/env bash
# The Compose examples under examples/docker/ must be complete (a .env.example that covers
# every required variable, a generator that makes real secrets), hardened, and actually
# bring up working sites: the minimal and single-site examples, and the multi-tenant
# one (several sites behind a Caddy proxy). See docs/docker-build.md, "Hosting examples".
set -u
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

command -v jq >/dev/null || { echo "jq is required"; exit 1; }

EX="$REPO_ROOT/examples/docker"
WORK="$(mktemp -d -t mukurtu-compose.XXXXXX)"
PROJECT="mukurtu-ctest-$$"
IMAGE_TAG=mukurtu-test:image
cleanup() {
  for name in minimal single-site multi-tenant; do
    [[ -f "$WORK/$name/docker-compose.yml" ]] && (cd "$WORK/$name" && docker compose -p "$PROJECT-$name" down -v >/dev/null 2>&1)
  done
  rm -rf "$WORK" "$BUILD_LOG"
}
trap cleanup EXIT

# prep <name>: copy an example to a scratch directory and prepare it the way its README
# says a user does: run the generator, then point the image at the one under test.
prep() {
  local name="$1" dir="$WORK/$1"
  cp -r "$EX/$name" "$dir"
  if [[ -x "$dir/generate-secrets.sh" ]]; then
    (cd "$dir" && ./generate-secrets.sh >/dev/null 2>&1)
  fi
  if [[ -f "$dir/.env" ]]; then
    sed -i "s#^MUKURTU_IMAGE=.*#MUKURTU_IMAGE=$IMAGE_TAG#" "$dir/.env"
  else
    # The minimal example has no .env: its compose file names the image directly.
    sed -i "s#^\( *image: \)ghcr.io/[^ ]*mukurtu:[^ ]*#\1$IMAGE_TAG#" "$dir/docker-compose.yml"
  fi
}
# dc <name> <docker compose args...>
dc() { local name="$1"; shift; (cd "$WORK/$name" && docker compose -p "$PROJECT-$name" "$@"); }
compose_json() { (cd "$WORK/$1" && docker compose config --format json 2>&1); }

echo "examples: the layout"
for name in minimal single-site multi-tenant; do
  [[ -f "$EX/$name/docker-compose.yml" ]] && pass "$name/docker-compose.yml exists" || fail "$name/docker-compose.yml exists"
  [[ -f "$EX/$name/README.md" ]] && pass "$name/README.md exists" || fail "$name/README.md exists"
done
[[ -f "$EX/README.md" ]] && pass "examples/docker/README.md exists" || fail "examples/docker/README.md exists"
ignore="$(cat "$EX/.gitignore" 2>/dev/null)"
assert_contains "$ignore" ".env" "examples/docker/.gitignore keeps the generated .env files out of git"
assert_contains "$ignore" "secrets" "examples/docker/.gitignore keeps the generated secrets out of git"
for name in single-site multi-tenant; do
  for f in .env.example generate-secrets.sh; do
    [[ -e "$EX/$name/$f" ]] && pass "$name/$f exists" || fail "$name/$f exists"
  done
  [[ -x "$EX/$name/generate-secrets.sh" ]] && pass "$name/generate-secrets.sh is executable" || fail "$name/generate-secrets.sh is executable"
  # Real or sample secret values must never be committed.
  committed="$(git -C "$REPO_ROOT" ls-files "examples/docker/$name" | grep -E '(^|/)(\.env|secrets/)' | grep -v '\.env\.example$' || true)"
  assert_eq "" "$committed" "$name: no .env or secret file is committed"
done

for name in minimal single-site multi-tenant; do
  [[ -d "$EX/$name" ]] && prep "$name"
done

echo "examples: every example is valid Compose"
for name in minimal single-site multi-tenant; do
  [[ -f "$WORK/$name/docker-compose.yml" ]] || continue
  if json="$(compose_json "$name")" && jq -e . >/dev/null <<<"$json"; then
    pass "$name is valid Compose"
  else
    fail "$name is valid Compose"; echo "$json" | head -5
  fi
done

echo "examples: .env.example covers every required variable"
for name in single-site multi-tenant; do
  [[ -f "$EX/$name/docker-compose.yml" ]] || continue
  required="$(grep -oE '\$\{[A-Z0-9_]+:\?' "$EX/$name/docker-compose.yml" | sed -E 's/\$\{([A-Z0-9_]+):\?/\1/' | sort -u)"
  missing=""
  for var in $required; do
    grep -qE "^$var=" "$EX/$name/.env.example" 2>/dev/null || missing="$missing $var"
  done
  [[ -z "$missing" ]] && pass "$name: .env.example has every required variable ($(wc -w <<<"$required") of them)" || fail "$name: .env.example is missing:$missing"
done

echo "examples: the generators make real, private, distinct secrets and never overwrite"
if [[ -d "$WORK/single-site" ]]; then
  d="$WORK/single-site"
  for f in db_password db_root_password hash_salt admin_password; do
    [[ -s "$d/secrets/$f.txt" ]] && pass "single-site: secrets/$f.txt is created and not empty" || fail "single-site: secrets/$f.txt is created and not empty"
  done
  assert_eq "644" "$(stat -c %a "$d/secrets/db_password.txt")" "single-site: secrets are readable by the container's user (mode 644)"
  [[ "$(<"$d/secrets/db_password.txt")" != "$(<"$d/secrets/db_root_password.txt")" ]] && pass "single-site: the database and root passwords differ" || fail "single-site: the database and root passwords differ"
  before="$(cat "$d"/secrets/*.txt | sha256sum)"
  (cd "$d" && ./generate-secrets.sh >/dev/null 2>&1)
  assert_eq "$before" "$(cat "$d"/secrets/*.txt | sha256sum)" "single-site: running the generator again keeps the existing secrets"
fi
if [[ -d "$WORK/multi-tenant" ]]; then
  d="$WORK/multi-tenant"
  for var in DB_ROOT_PASSWORD SITE_A_DB_PASSWORD SITE_B_DB_PASSWORD SITE_A_ADMIN_PASSWORD SITE_B_ADMIN_PASSWORD; do
    value="$(grep -E "^$var=" "$d/.env" | cut -d= -f2-)"
    [[ ${#value} -ge 16 ]] && pass "multi-tenant: $var is generated (${#value} characters)" || fail "multi-tenant: $var is generated"
  done
  before="$(grep -E 'PASSWORD=' "$d/.env" | sha256sum)"
  (cd "$d" && ./generate-secrets.sh >/dev/null 2>&1)
  assert_eq "$before" "$(grep -E 'PASSWORD=' "$d/.env" | sha256sum)" "multi-tenant: running the generator again keeps the existing passwords"
fi

echo "examples: every Mukurtu service is hardened and bounded"
check_services() { # check_services <name> <service names...>
  local name="$1" json svc
  shift
  json="$(compose_json "$name")" || return
  for svc in "$@"; do
    assert_eq "true" "$(jq -r ".services[\"$svc\"].read_only" <<<"$json")" "$name: $svc is read_only"
    assert_eq '["ALL"]' "$(jq -c ".services[\"$svc\"].cap_drop" <<<"$json")" "$name: $svc drops all capabilities"
    assert_contains "$(jq -c ".services[\"$svc\"].security_opt" <<<"$json")" "no-new-privileges:true" "$name: $svc sets no-new-privileges"
    local mem
    mem="$(jq -r ".services[\"$svc\"].mem_limit // \"\"" <<<"$json")"
    [[ -n "$mem" && "$mem" != null ]] && pass "$name: $svc has mem_limit ($mem)" || fail "$name: $svc has mem_limit"
    assert_contains "$(jq -c ".services[\"$svc\"].tmpfs" <<<"$json")" "/tmp" "$name: $svc has a /tmp tmpfs with a cap"
  done
}
[[ -d "$WORK/single-site" ]] && check_services single-site mukurtu
[[ -d "$WORK/multi-tenant" ]] && check_services multi-tenant site_a site_b

echo "examples: MariaDB is configured the way Drupal asks"
for name in minimal single-site multi-tenant; do
  [[ -d "$WORK/$name" ]] || continue
  cmd="$(compose_json "$name" | jq -c '.services.db.command')"
  assert_contains "$cmd" "--transaction-isolation=READ-COMMITTED" "$name: READ-COMMITTED"
  assert_contains "$cmd" "--binlog-format=ROW" "$name: binlog_format ROW"
  assert_contains "$cmd" "--character-set-server=utf8mb4" "$name: utf8mb4"
done
for name in single-site multi-tenant; do
  [[ -d "$WORK/$name" ]] || continue
  assert_contains "$(compose_json "$name" | jq -c '.services.db.command')" "--max-allowed-packet=64M" "$name: max_allowed_packet"
done

echo "examples: the multi-tenant example routes two domains through Caddy and trusts only the proxy"
if [[ -d "$WORK/multi-tenant" ]]; then
  json="$(compose_json multi-tenant)"
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

if ! runtime_image "$IMAGE_TAG"; then
  fail "image builds"; tail -20 "$BUILD_LOG"; finish; exit 1
fi

# live_single <name>: bring up an example on port 8080 and wait for the login page.
live_single() {
  local name="$1" ok=false
  dc "$name" up -d >/dev/null 2>&1
  for _ in $(seq 1 300); do
    if curl -sf -o /dev/null -H 'Host: localhost' "http://127.0.0.1:8080/user/login"; then ok=true; break; fi
    sleep 2
  done
  if $ok; then pass "$name installs and serves pages"; else
    fail "$name installs and serves pages"
    dc "$name" logs --tail 40 mukurtu 2>&1
  fi
  dc "$name" down -v >/dev/null 2>&1
}
echo "examples: the minimal example brings up a working site"
[[ -d "$WORK/minimal" ]] && live_single minimal
echo "examples: the single-site example brings up a working site (read-only root, tmpfs, secrets)"
[[ -d "$WORK/single-site" ]] && live_single single-site

echo "examples: the multi-tenant example brings up two sites behind Caddy"
if [[ -d "$WORK/multi-tenant" ]]; then
  # (the .env.example defaults are the local-try values: *.localhost names, ports 8088 and 8443)
  via_caddy() { curl -sk --max-time 20 --resolve "$1:8443:127.0.0.1" "https://$1:8443$2"; }
  code_via_caddy() { curl -sk --max-time 20 -o /dev/null -w '%{http_code}' --resolve "$1:8443:127.0.0.1" "https://$1:8443$2"; }
  dc multi-tenant up -d >/dev/null 2>&1
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
    # Ask Docker for the container's port bindings. (Not `docker compose port`, whose
    # output for an unpublished port differs between Compose versions.)
    bindings="$(docker inspect -f '{{json .HostConfig.PortBindings}}' "$(dc multi-tenant ps -q site_a)" 2>/dev/null)"
    [[ "$bindings" == "{}" || "$bindings" == "null" ]] && pass "site A publishes no port on the host" || fail "site A publishes no port on the host (got '$bindings')"
    bindings="$(docker inspect -f '{{json .HostConfig.PortBindings}}' "$(dc multi-tenant ps -q caddy)" 2>/dev/null)"
    assert_contains "$bindings" "8443" "the proxy does publish its HTTPS port"
    password_a="$(grep -E '^SITE_A_DB_PASSWORD=' "$WORK/multi-tenant/.env" | cut -d= -f2-)"
    assert_eq "denied" "$(dc multi-tenant exec -T db sh -c "mariadb -usite_a -p'$password_a' -e 'use site_b' 2>&1 | grep -q 'Access denied' && echo denied")" "site A's database user cannot open site B's database"
  else
    fail "both sites install and answer through Caddy"
    dc multi-tenant logs --tail 30 caddy site_a site_b 2>&1
  fi
fi

finish
