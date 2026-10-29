#!/bin/sh
# Run Drupal cron every MUKURTU_CRON_INTERVAL seconds (default 3600). Started by
# supervise.sh when MUKURTU_CRON=true. A failing run is logged and retried at
# the next interval; it never stops the container.
set -u

INTERVAL=${MUKURTU_CRON_INTERVAL:-3600}

# The first run comes soon after start (a minute, plus up to 30 seconds so that a host
# starting many sites does not run them all at the same moment), not a whole interval
# later. After that, every interval. An interval under a minute is used from the start.
if [ "$INTERVAL" -lt 60 ]; then
  delay=$INTERVAL
else
  delay=$((60 + $(od -An -N1 -tu1 /dev/urandom) % 30))
fi

# Sleep in the background so a TERM from the supervisor interrupts it at once.
# (Only TERM: a script started as a background job has SIGINT and SIGQUIT
# ignored, and an ignored signal cannot be trapped.)
sleeper=""
trap '[ -z "$sleeper" ] || kill "$sleeper" 2>/dev/null; exit 0' TERM

while true; do
  sleep "$delay" &
  sleeper=$!
  wait $sleeper
  delay=$INTERVAL
  echo "[mukurtu] cron: running"
  if drush core:cron; then
    echo "[mukurtu] cron: run finished"
  else
    echo "[mukurtu] cron: run failed (will retry in ${INTERVAL}s)" >&2
  fi
done
