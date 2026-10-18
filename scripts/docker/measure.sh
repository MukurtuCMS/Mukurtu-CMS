#!/usr/bin/env bash
# Measure what the image costs: size on disk, memory when idle, memory under
# load, and the memory of the thumbnail tools. Prints a report whose figures go
# into docs/docker-build.md ("Measured footprint"). Not a pass/fail test.
#
#   scripts/docker/measure.sh        (takes ~6 minutes: it installs a real site)
set -u
source "$(dirname "${BASH_SOURCE[0]}")/test-lib.sh"

ID="$$"
NET="mukurtu-measure-net-$ID"
DB="mukurtu-measure-db-$ID"
SITE="mukurtu-measure-site-$ID"
cleanup() {
  docker rm -f "$SITE" "$DB" >/dev/null 2>&1
  docker volume rm -f "mukurtu-measure-pub-$ID" "mukurtu-measure-priv-$ID" >/dev/null 2>&1
  docker network rm "$NET" >/dev/null 2>&1
  rm -f "$BUILD_LOG"
}
trap cleanup EXIT

for variant in "full true true" "slim false false"; do
  set -- $variant
  build_image runtime "mukurtu-measure:$1" --build-arg WITH_FFMPEG="$2" --build-arg WITH_POPPLER="$3" \
    --build-arg MUKURTU_TEMPLATE_VERSION="$TEMPLATE_VERSION" || { echo "build failed: $1"; tail -20 "$BUILD_LOG"; exit 1; }
done

mb() { sed -E 's/MiB//; s/GiB/*1024/; s/KiB/\/1024/' | awk '{ printf "%.0f", $0 }' 2>/dev/null; }
mem_now() { # container memory as docker reports it (usage minus reclaimable cache), in MB
  docker stats --no-stream --format '{{.MemUsage}}' "$SITE" | awk '{print $1}' | python3 -c '
import re, sys
v = sys.stdin.read().strip()
m = re.match(r"([\d.]+)(KiB|MiB|GiB)", v)
f = {"KiB": 1 / 1024, "MiB": 1, "GiB": 1024}[m.group(2)]
print(round(float(m.group(1)) * f))'
}

echo "# Measured footprint"
echo
echo "Date: $(date -u +%Y-%m-%d)  Host: $(nproc) CPUs, $(free -m | awk '/Mem:/{print $2}') MB RAM, $(uname -sr), Docker $(docker version --format '{{.Server.Version}}')"
echo
echo "## Image size (uncompressed, linux/$(docker version --format '{{.Server.Arch}}'))"
for v in full slim; do
  size=$(docker image inspect "mukurtu-measure:$v" --format '{{.Size}}')
  echo "- $v: $((size / 1000000)) MB"
done

docker network create "$NET" >/dev/null
docker run -d --name "$DB" --network "$NET" -e MARIADB_ROOT_PASSWORD=r -e MARIADB_DATABASE=m -e MARIADB_USER=m -e MARIADB_PASSWORD=m \
  mariadb:11 --character-set-server=utf8mb4 --collation-server=utf8mb4_general_ci \
  --transaction-isolation=READ-COMMITTED --binlog-format=ROW >/dev/null
for _ in $(seq 1 60); do docker exec "$DB" mariadb -ur -pr -e 'select 1' >/dev/null 2>&1 && break; docker exec "$DB" mariadb -uroot -pr -e 'select 1' >/dev/null 2>&1 && break; sleep 1; done

docker run -d --name "$SITE" --network "$NET" \
  -e MUKURTU_DB_HOST="$DB" -e MUKURTU_DB_NAME=m -e MUKURTU_DB_USER=m -e MUKURTU_DB_PASSWORD=m \
  -e MUKURTU_TRUSTED_HOSTS=measure.example.org -e MUKURTU_HASH_SALT=measure \
  -e MUKURTU_ADMIN_PASSWORD=Measure-1-pass -e MUKURTU_ADMIN_EMAIL=a@example.org \
  -v "mukurtu-measure-pub-$ID:/var/www/files/public" -v "mukurtu-measure-priv-$ID:/var/www/files/private" \
  "mukurtu-measure:full" >/dev/null

echo
echo "Installing a site to measure (a few minutes)..." >&2
for _ in $(seq 1 300); do
  docker exec "$SITE" curl -sf -H 'Host: measure.example.org' -o /dev/null http://127.0.0.1:8080/user/login 2>/dev/null && break
  sleep 2
done

get() { docker exec "$SITE" curl -s -o /dev/null -H 'Host: measure.example.org' --max-time 30 "http://127.0.0.1:8080$1"; }

# Right after install the container is full of install-time cache; restart for
# a realistic idle figure.
docker restart "$SITE" >/dev/null
for _ in $(seq 1 120); do
  docker exec "$SITE" curl -sf -H 'Host: measure.example.org' -o /dev/null http://127.0.0.1:8080/user/login 2>/dev/null && break
  sleep 2
done

echo
echo "## Memory of one running site (full image, defaults: ondemand, 4 workers, 128M opcache)"
sleep 3
echo "- just started, before any request: $(mem_now) MB"
for _ in 1 2 3; do get /; get /user/login; done
sleep 15   # let ondemand workers exit (idle timeout 10s)
echo "- idle after warm-up (opcache and APCu populated, no PHP workers): $(mem_now) MB"
echo "- processes at idle (RSS KB):"
docker exec "$SITE" ps -o rss,args | sort -rn | head -6 | sed 's/^/    /'

# 4 concurrent request streams for 20 seconds; sample memory while they run.
for i in 1 2 3 4; do
  docker exec -d "$SITE" sh -c 'end=$(( $(date +%s) + 20 )); while [ $(date +%s) -lt $end ]; do curl -s -o /dev/null -H "Host: measure.example.org" "http://127.0.0.1:8080/user/login?r=$RANDOM"; done'
done
peak=0
for _ in $(seq 1 8); do
  sleep 1.5
  now=$(mem_now)
  [[ "$now" -gt "$peak" ]] && peak=$now
done
workers=$(docker exec "$SITE" sh -c 'ps -o args | grep -c "[p]hp-fpm: pool www"')
echo "- under load (4 concurrent request streams), peak: $peak MB with $workers PHP workers"
sleep 25
echo "- back to idle after load: $(mem_now) MB"

echo
echo "## Thumbnail tools (a 100 MB video, the command Mukurtu runs)"
docker exec "$SITE" sh -c '
  cd /tmp
  ffmpeg -loglevel error -f lavfi -i testsrc2=size=1920x1080:rate=30 -t 40 -c:v libx264 -b:v 20M -pix_fmt yuv420p video.mp4
  ls -l video.mp4 | awk "{printf \"- test video: %.0f MB\\n\", \$5/1000000}"
  # Same command as modules/mukurtu_media/src/Entity/Video.php
  ffmpeg -loglevel error -ss 00:00:00 -i /tmp/video.mp4 -frames:v 1 /tmp/thumb.png &
  pid=$!
  hwm=0
  while kill -0 $pid 2>/dev/null; do
    v=$(awk "/VmHWM/{print \$2}" /proc/$pid/status 2>/dev/null)
    [ -n "$v" ] && [ "$v" -gt "$hwm" ] && hwm=$v
    sleep 0.02
  done
  echo "- ffmpeg thumbnail extraction, peak resident memory: $((hwm / 1024)) MB"
  rm -f /tmp/video.mp4 /tmp/thumb.png
'
