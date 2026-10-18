#!/bin/sh
# Run php-fpm, nginx, and (optionally) the cron loop as the container's
# processes. If any of them stops, stop the others and exit non-zero, so the
# container restarts instead of serving errors. On SIGTERM or SIGINT, shut
# everything down gracefully and exit 0.
set -u

LIB=/usr/local/lib/mukurtu
RUN=${MUKURTU_RUN_DIR:-/run/mukurtu}

pids=""
quit_pids=""   # nginx and php-fpm finish their requests on SIGQUIT
term_pids=""   # shell scripts: a background job in a script starts with SIGQUIT
               # ignored, and an ignored signal cannot be trapped
stopping=false

start() { # start quit|term <command...>
  _how=$1
  shift
  "$@" &
  pids="$pids $!"
  if [ "$_how" = quit ]; then quit_pids="$quit_pids $!"; else term_pids="$term_pids $!"; fi
}

stop_all() {
  stopping=true
  for pid in $quit_pids; do kill -QUIT "$pid" 2>/dev/null; done
  for pid in $term_pids; do kill -TERM "$pid" 2>/dev/null; done
}
trap stop_all TERM INT

start quit php-fpm -y "$RUN/php-fpm.conf" -c "$RUN/php.ini"
start quit nginx -c "$RUN/nginx.conf" -g 'daemon off;'
if [ "${MUKURTU_CRON:-true}" = true ]; then
  start term "$LIB/cron.sh"
fi

# Wait until any child exits.
while true; do
  for pid in $pids; do
    if ! kill -0 "$pid" 2>/dev/null; then
      if [ "$stopping" = true ]; then
        # Graceful shutdown: wait for the rest, then leave.
        for other in $pids; do wait "$other" 2>/dev/null; done
        exit 0
      fi
      echo "ERROR: a container process (pid $pid) exited unexpectedly; stopping" >&2
      stop_all
      for other in $pids; do wait "$other" 2>/dev/null; done
      exit 1
    fi
  done
  sleep 1 &
  wait $! 2>/dev/null
done
