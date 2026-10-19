#!/usr/bin/env bash
# The supervisor starts php-fpm and nginx cleanly, with nothing alarming in the log,
# and stops them on SIGTERM. Needs no database: it runs supervise.sh directly.
# See docs/docker-build.md, "Startup, supervision and health".
set -u
source "$(dirname "${BASH_SOURCE[0]}")/test-lib.sh"

IMAGE=mukurtu-test:full
NAME="mukurtu-supervise-test-$$"
trap 'docker rm -f "$NAME" >/dev/null 2>&1; rm -f "$BUILD_LOG"' EXIT

if ! build_image runtime "$IMAGE" "${DEFAULT_BUILD_ARGS[@]}"; then
  fail "runtime image builds"; tail -30 "$BUILD_LOG"; finish; exit 1
fi

docker run -d --name "$NAME" --entrypoint sh -e MUKURTU_CRON=false "$IMAGE" -c '
  export MUKURTU_RUN_DIR=/tmp/run
  /usr/local/lib/mukurtu/render-config.sh 2>/dev/null || exit 1
  exec /usr/local/lib/mukurtu/supervise.sh' >/dev/null

for _ in $(seq 1 40); do
  docker exec "$NAME" curl -sf http://127.0.0.1:8080/healthz >/dev/null 2>&1 && break
  sleep 0.25
done
assert_eq "pong" "$(docker exec "$NAME" curl -s http://127.0.0.1:8080/healthz)" "php-fpm and nginx answer"

log="$(docker logs "$NAME" 2>&1)"
assert_contains "$log" "ready to handle connections" "php-fpm reports it is ready"
assert_not_contains "$log" "[alert]" "nginx logs no alert at start-up"
assert_not_contains "$log" "[emerg]" "nginx logs no emergency at start-up"
assert_not_contains "$log" "Permission denied" "nothing is refused permission at start-up"
assert_not_contains "$log" "ERROR" "no error at start-up"

start=$SECONDS
docker stop -t 10 "$NAME" >/dev/null
[[ $((SECONDS - start)) -lt 10 ]] && pass "stops within 10 seconds" || fail "stops within 10 seconds"
assert_eq "0" "$(docker inspect -f '{{.State.ExitCode}}' "$NAME")" "exits 0 on SIGTERM"

finish
