#!/bin/sh
# Container health: nginx is up and php-fpm answers. Uses php-fpm's own ping,
# which does not bootstrap Drupal, so it is cheap and does not need the
# database. (A database outage is visible in the site's own status report.)
curl -fsS --max-time 4 http://127.0.0.1:8080/healthz | grep -q pong
