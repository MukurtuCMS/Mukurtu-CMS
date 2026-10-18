#!/bin/sh
# Run Drupal cron every MUKURTU_CRON_INTERVAL seconds (default 3600). Started by
# supervise.sh when MUKURTU_CRON=true. A failing run is logged and retried at
# the next interval; it never stops the container.
set -u

INTERVAL=${MUKURTU_CRON_INTERVAL:-3600}

# Sleep in the background so a TERM from the supervisor interrupts it at once.
# (Only TERM: a script started as a background job has SIGINT and SIGQUIT
# ignored, and an ignored signal cannot be trapped.)
sleeper=""
trap '[ -z "$sleeper" ] || kill "$sleeper" 2>/dev/null; exit 0' TERM

while true; do
  sleep "$INTERVAL" &
  sleeper=$!
  wait $sleeper
  echo "[mukurtu] cron: running"
  if drush core:cron; then
    echo "[mukurtu] cron: run finished"
  else
    echo "[mukurtu] cron: run failed (will retry in ${INTERVAL}s)" >&2
  fi
done
