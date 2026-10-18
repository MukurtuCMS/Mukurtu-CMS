#!/usr/bin/env bash
# The Compose examples must be valid, hardened, and (for the single-site one)
# actually bring up a working site. See docs/docker-build.md, "Hosting examples".
set -u
source "$(dirname "${BASH_SOURCE[0]}")/test-lib.sh"

command -v jq >/dev/null || { echo "jq is required"; exit 1; }

SINGLE="$REPO_ROOT/docker-compose.example.yml"
MULTI="$REPO_ROOT/docker-compose.multisite.example.yml"
WORK="$(mktemp -d -t mukurtu-compose.XXXXXX)"
PROJECT="mukurtu-ctest-$$"
cleanup() {
  (cd "$WORK" && MUKURTU_IMAGE=mukurtu-test:full docker compose --env-file .env -p "$PROJECT" -f "$WORK/$(basename "$SINGLE")" down -v >/dev/null 2>&1)
  rm -rf "$WORK" "$BUILD_LOG"
}
trap cleanup EXIT

# Environment and secret files the examples ask for.
mkdir -p "$WORK/secrets"
for name in db_password db_root_password admin_password hash_salt; do
  printf 'test-%s-value' "$name" >"$WORK/secrets/$name.txt"
done
chmod -R a+rX "$WORK"
cat >"$WORK/.env" <<ENV
MUKURTU_HOST=localhost
MUKURTU_ADMIN_EMAIL=admin@example.org
ACME_EMAIL=ops@example.org
SITE_A_HOST=a.example.org
SITE_B_HOST=b.example.org
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

echo "compose: multi-site routes two domains and trusts only the proxy"
if [[ -f "$MULTI" ]]; then
  json="$(compose_json "$MULTI")"
  assert_contains "$(jq -c '.services.site_a.labels' <<<"$json")" "a.example.org" "site_a is routed by its own host name"
  assert_contains "$(jq -c '.services.site_b.labels' <<<"$json")" "b.example.org" "site_b is routed by its own host name"
  assert_eq "true" "$(jq -r '.services.site_a.environment.MUKURTU_REVERSE_PROXY' <<<"$json")" "site_a trusts a reverse proxy"
  assert_contains "$(jq -r '.services.site_a.environment.MUKURTU_REVERSE_PROXY_ADDRESSES' <<<"$json")" "/" "site_a lists the proxy network as a CIDR range"
  [[ "$(jq -r '.services.site_a.environment.MUKURTU_DB_NAME' <<<"$json")" != "$(jq -r '.services.site_b.environment.MUKURTU_DB_NAME' <<<"$json")" ]] \
    && pass "the sites use different databases" || fail "the sites use different databases"
fi

echo "compose: the single-site example brings up a working site"
if [[ -f "$SINGLE" ]]; then
  if ! build_image runtime mukurtu-test:full "${DEFAULT_BUILD_ARGS[@]}"; then
    fail "image builds"; tail -20 "$BUILD_LOG"; finish; exit 1
  fi
  # Run the copy next to the secrets and .env, as a user would.
  dc() { (cd "$WORK" && MUKURTU_IMAGE=mukurtu-test:full docker compose --env-file .env -p "$PROJECT" -f "$WORK/$(basename "$SINGLE")" "$@"); }
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
fi

finish
