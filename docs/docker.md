# Running Mukurtu CMS in a container

This is the reference for running the Mukurtu CMS container image: what it
needs, every setting you can change, and how to operate it. For *how the image
is built and why*, see [docker-build.md](docker-build.md).

- [Quick start](#quick-start)
- [Images and tags](#images-and-tags)
- [What the container does](#what-the-container-does)
- [Settings (environment variables)](#settings-environment-variables)
- [Secrets](#secrets)
- [Volumes and permissions](#volumes-and-permissions)
- [Behind a reverse proxy](#behind-a-reverse-proxy)
- [Sizing memory](#sizing-memory)
- [Mail](#mail)
- [Search (Solr)](#search-solr)
- [Upgrades and backups](#upgrades-and-backups)
- [Running commands](#running-commands)
- [Hosting many sites](#hosting-many-sites)
- [Troubleshooting](#troubleshooting)
- [Building the image yourself](#building-the-image-yourself)

## Quick start

You need Docker with the Compose plugin, a host name for the site, and a
reverse proxy that provides HTTPS (the container speaks plain HTTP on port
8080).

```
mkdir mukurtu && cd mukurtu
curl -O https://raw.githubusercontent.com/OWNER/Mukurtu-CMS/main/docker-compose.example.yml
mv docker-compose.example.yml docker-compose.yml

mkdir secrets
openssl rand -base64 24 > secrets/db_password.txt
openssl rand -base64 24 > secrets/db_root_password.txt
openssl rand -base64 48 > secrets/hash_salt.txt
printf '%s' 'choose-a-strong-admin-password' > secrets/admin_password.txt
chmod 644 secrets/*.txt

cat > .env <<'ENV'
MUKURTU_HOST=mukurtu.example.org
MUKURTU_ADMIN_EMAIL=you@example.org
MUKURTU_IMAGE=ghcr.io/OWNER/mukurtu:4.0.5
ENV

docker compose up -d
docker compose logs -f mukurtu
```

Replace `OWNER` with the GitHub account that publishes the image. The first start
installs Mukurtu into the empty database, which takes a few minutes; the log ends
with `[mukurtu] starting`. Then browse to the site through your reverse proxy and
sign in as `admin` with the password from `secrets/admin_password.txt`.

## Images and tags

Images are published to `ghcr.io/<owner>/mukurtu` for `linux/amd64` and
`linux/arm64`, in two variants:

| Variant | Tags | Contains |
| --- | --- | --- |
| **full** | `4.0.5`, `4.0.5-r1`, `4.0`, `4`, `latest` | Everything, including `ffmpeg` and `poppler-utils` |
| **slim** | `4.0.5-slim`, `4.0.5-r1-slim`, `4.0-slim`, `4-slim`, `latest-slim` | No `ffmpeg`, no `poppler-utils` |

Full and slim are **different images**, chosen by the tag you pull. They are not a
setting of the running container: there is no environment variable that adds or
removes `ffmpeg` or `poppler-utils`. Choose **full** unless you know you do not need the media features. In the slim
image Mukurtu cannot extract text from PDFs (so PDF content is not searchable),
make PDF thumbnails, or make video thumbnails. The container logs a warning at
start-up for each missing tool, and Mukurtu's status report page lists them.

**Revisions.** `4.0.5-r1` is a new image of the same Mukurtu 4.0.5, published when
something under Mukurtu needs a fix (the base image, PHP, nginx, `ffmpeg`): the Mukurtu
code is unchanged, the platform is updated. `-r2`, `-r3` follow if needed. To take
the fix, change your tag from `4.0.5` to `4.0.5-r1`; the release notes say what
changed.

**A published tag never changes.** Nothing rebuilds it, so `4.0.5` is the same image
tomorrow as today. That is why revisions exist: a fix arrives as a new tag you move
to on purpose. `4.0` follows new patch releases and revisions, `4` also follows
new minor releases, and `latest` follows everything newer; each moves only forward,
so a backport never moves it back. Pre-releases (such as `4.0.0-rc`) have only
their exact tag and never become any of those. **Pin an exact tag in production**
and upgrade on purpose. The full tagging and publishing rules are in
[docker-build.md](docker-build.md#publishing-and-supply-chain).

## What the container does

- Serves Mukurtu with nginx and php-fpm on **port 8080** (plain HTTP). Put a
  reverse proxy that terminates HTTPS in front of it.
- Runs as **uid 1000** (user `mukurtu`), not root. The code is read-only to that
  user.
- On start it checks its settings, waits for the database, **installs Mukurtu if
  the database is empty**, applies database updates if the site already exists,
  then serves requests. The exact sequence is in
  [docker-build.md](docker-build.md#startup-supervision-and-health).
- Runs Drupal's cron itself, every hour by default.
- Logs to the container's standard output and error: `docker logs` shows nginx
  access and error logs, PHP errors, and cron output.
- Reports health through Docker's `HEALTHCHECK`.
- Stops cleanly on `SIGTERM` (`docker stop`), normally within a few seconds.

## Settings (environment variables)

Every setting is an environment variable. Nothing is edited inside the container.
In the tables, **Required** means the container refuses to start without it, and a
blank default means "not set".

Values are checked at start-up, and a bad value stops the container with a
message that names the variable, for example `ERROR: MUKURTU_UPLOAD_MAX must be
a size such as 128M or 1G (got '100MB')`.

A *size* is a number followed by `K`, `M`, or `G` (`512M`). A *duration* is a
number followed by `s`, `m`, `h`, or `d` (`10s`).

### Required

| Variable | Default | Description |
| --- | --- | --- |
| `MUKURTU_DB_HOST` | | Host name of the MariaDB or MySQL server (`db` in the Compose examples). |
| `MUKURTU_DB_NAME` | | Database name. Create it as `utf8mb4` (see [Database](#database)). |
| `MUKURTU_DB_USER` | | Database user. It needs all privileges on that database. |
| `MUKURTU_DB_PASSWORD` | | Database password. Also accepts [`MUKURTU_DB_PASSWORD_FILE`](#secrets). |
| `MUKURTU_TRUSTED_HOSTS` | | The host names the site answers to, separated by commas: `example.org,www.example.org`. Drupal refuses any request with a different `Host` header, which blocks host-header attacks such as poisoned password-reset links. Entries match exactly, ignoring case, and `*.example.org` trusts every subdomain. |

When the site is installed for the first time, two more are required:
`MUKURTU_ADMIN_PASSWORD` and `MUKURTU_ADMIN_EMAIL` (below). There is deliberately
no default administrator password.

### Database

| Variable | Default | Description |
| --- | --- | --- |
| `MUKURTU_DB_PORT` | `3306` | Database port. |
| `MUKURTU_DB_PREFIX` | | Prefix for table names. Leave empty unless several sites share one database. |
| `MUKURTU_DB_WAIT_TIMEOUT` | `60` | Seconds to wait for the database to accept connections at start-up before giving up. Raise it on slow storage or if the database initialises on the same `up`. |

Create the database with the `utf8mb4` character set, as Drupal recommends, so
emoji and non-Latin scripts are stored correctly:

```sql
CREATE DATABASE mukurtu CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
```

The container connects over TCP with the character set `utf8mb4`. See
[docker-build.md](docker-build.md#hosting-examples) for the server settings the
Compose files use (`READ-COMMITTED`, row-based logging, `max_allowed_packet`).

### First install and updates

| Variable | Default | Description |
| --- | --- | --- |
| `MUKURTU_AUTO_INSTALL` | `true` | If the database is empty, install Mukurtu at start-up. Set `false` to install by hand (`docker exec <container> drush site:install mukurtu`). A database that has tables but no complete Drupal install is never installed over: the container stops with an error. |
| `MUKURTU_ADMIN_USER` | `admin` | User name of the first administrator. Used only at install. |
| `MUKURTU_ADMIN_PASSWORD` | | Password of the first administrator. Required for an install, no default. Also accepts [`MUKURTU_ADMIN_PASSWORD_FILE`](#secrets). Used only at install; change it later in Mukurtu. |
| `MUKURTU_ADMIN_EMAIL` | | Email address of the first administrator. Required for an install. |
| `MUKURTU_SITE_NAME` | `Mukurtu` | Site name. Used only at install; change it later in Mukurtu. |
| `MUKURTU_SITE_EMAIL` | the administrator's email | The address the site sends mail from. Used only at install. |
| `MUKURTU_LOCALE` | `en` | Language of the installed site (a Drupal language code). Used only at install. |
| `MUKURTU_RUN_UPDATES` | `true` | At every start of an installed site, run `drush updatedb` and `drush cache:rebuild`. A new image version contains new code, and this brings the database and caches in line with it. Set `false` only if you run updates yourself, in a controlled order. |

### Security and addressing

| Variable | Default | Description |
| --- | --- | --- |
| `MUKURTU_HASH_SALT` | generated once and kept in the private volume | Secret that signs one-time login links and image-style tokens. **Set it explicitly in production** (also accepts [`MUKURTU_HASH_SALT_FILE`](#secrets)). A generated salt lives in the private volume; if that volume is lost while the database is kept, the salt changes and previously issued image URLs stop validating. |
| `MUKURTU_REVERSE_PROXY` | `false` | Set `true` when the container is behind a reverse proxy, so Drupal reads the client address, scheme, and host from `X-Forwarded-*` headers. |
| `MUKURTU_REVERSE_PROXY_ADDRESSES` | | Required when `MUKURTU_REVERSE_PROXY=true`. The proxy's IP addresses or CIDR ranges, separated by commas (`172.28.0.0/24`). Those headers are trusted **only** from these addresses. |
| `MUKURTU_BASE_URL` | `https://` plus the first entry of `MUKURTU_TRUSTED_HOSTS` | The site's public address. Only command-line tools use it: cron, and `drush`, need it to write correct links in mail and output. |

### Files

| Variable | Default | Description |
| --- | --- | --- |
| `MUKURTU_FILE_TEMP_PATH` | `/tmp` | Where uploads are written before Drupal moves them into place. An upload is held here twice while it is processed (nginx buffers it, then PHP receives a copy), so size this for **twice** the largest upload. What backs this directory is your choice: a **tmpfs** is fast but counts against the container's memory limit, while a **size-capped volume** uses disk. The directory must be writable by uid 1000. |
| `MUKURTU_CONFIG_SYNC_DIR` | `/var/www/files/private/config-sync` | Drupal's configuration sync directory. Keep it outside the web root; the default is in the private volume. |
| `MUKURTU_UPLOAD_MAX` | `100M` | Largest upload. Sets PHP's `upload_max_filesize` and `post_max_size` and nginx's `client_max_body_size` together. Also reverse proxies have limits of their own (nginx's default is 1M): raise theirs too. |

### Cron

| Variable | Default | Description |
| --- | --- | --- |
| `MUKURTU_CRON` | `true` | Run Drupal's cron inside the container. Set `false` if you run `drush core:cron` from outside. |
| `MUKURTU_CRON_INTERVAL` | `3600` | Seconds between cron runs. A failing run is logged and tried again at the next interval. |

### PHP

| Variable | Default | Description |
| --- | --- | --- |
| `MUKURTU_PHP_MEMORY_LIMIT` | `512M` | Memory limit of one web request. Upstream recommends 512M. Memory used by `ffmpeg` and `pdftoppm` is *not* counted here; see [Sizing memory](#sizing-memory). Use `-1` for no limit. |
| `MUKURTU_PHP_CLI_MEMORY_LIMIT` | `1G` | Memory limit for command-line PHP: install, updates, cron, and `drush`. Larger than the web limit because the installer is memory hungry. |
| `MUKURTU_PHP_MAX_EXECUTION_TIME` | `120` | Seconds a web request may run. php-fpm also stops a request after this long, and nginx waits this long for an answer. |
| `MUKURTU_PHP_MAX_INPUT_VARS` | `3000` | PHP's `max_input_vars`. Mukurtu's larger forms need more than PHP's default of 1000. |
| `MUKURTU_PHP_TIMEZONE` | `UTC` | PHP's default time zone (`Pacific/Auckland`). Each user and the site have their own time zones in Drupal; this is only the fallback. |
| `MUKURTU_OPCACHE_MEMORY` | `128` | Megabytes for the compiled-code cache. Mukurtu's code fits comfortably; raise it only if the cache fills (visible with `opcache_get_status()`). |
| `MUKURTU_OPCACHE_MAX_FILES` | `20000` | Most files opcache will hold. Must exceed the number of PHP files the site loads. |
| `MUKURTU_APCU_MEMORY` | `32M` | Shared memory for APCu, which Drupal uses to cache bootstrap and discovery data. |

### php-fpm (how many requests run at once)

php-fpm runs the PHP for page requests in worker processes. These settings decide
how many exist, and so how much memory a busy site can use and how an idle site
behaves. See [Sizing memory](#sizing-memory).

| Variable | Default | Description |
| --- | --- | --- |
| `MUKURTU_FPM_PM` | `ondemand` | Process manager. `ondemand`: no workers while idle, started on demand (lowest idle memory, the right choice for hosting many sites). `dynamic`: keeps a few spare workers ready (faster first response). `static`: always runs `MUKURTU_FPM_MAX_CHILDREN` workers. |
| `MUKURTU_FPM_MAX_CHILDREN` | `4` | Most page requests PHP serves at once. The cap on a site's PHP memory. Requests beyond it wait. |
| `MUKURTU_FPM_PROCESS_IDLE_TIMEOUT` | `10s` | `ondemand` only: how long an idle worker lives before it exits. |
| `MUKURTU_FPM_START_SERVERS` | `2` | `dynamic` only: workers started at the beginning. |
| `MUKURTU_FPM_MIN_SPARE_SERVERS` | `1` | `dynamic` only: fewest idle workers kept. |
| `MUKURTU_FPM_MAX_SPARE_SERVERS` | `3` | `dynamic` only: most idle workers kept. |
| `MUKURTU_FPM_MAX_REQUESTS` | `500` | A worker exits and is replaced after this many requests, which bounds slow memory growth. `0` disables it. |
| `MUKURTU_FPM_FILES_MAX_CHILDREN` | `2` | Cap for the separate worker pool that serves **private file downloads** (`/system/files/*`). Drupal streams private files through PHP, so each download or video range request occupies a worker for as long as the client takes to receive it. A separate pool means a few large downloads cannot stop pages loading. Raise it for a site that serves a lot of private media, together with the container's memory limit. |

### nginx

| Variable | Default | Description |
| --- | --- | --- |
| `MUKURTU_NGINX_WORKER_PROCESSES` | `1` | nginx worker processes. One is plenty: nginx is not the bottleneck for a Drupal site, and each worker uses memory. |
| `MUKURTU_NGINX_WORKER_CONNECTIONS` | `1024` | Connections per nginx worker. |
| `MUKURTU_NGINX_ACCESS_LOG` | `true` | Write nginx's access log to the container log. Set `false` to log errors only, for example behind a proxy that already logs requests. |

### Mail

Mukurtu sends mail for password resets, notifications, and digests. Set
`MUKURTU_SMTP_HOST` and the container delivers through that server; see
[Mail](#mail).

| Variable | Default | Description |
| --- | --- | --- |
| `MUKURTU_SMTP_HOST` | | SMTP server. Not set: the site cannot send mail, and a warning is logged at start-up. |
| `MUKURTU_SMTP_PORT` | `587` | SMTP port. |
| `MUKURTU_SMTP_TLS` | `starttls` | `starttls` (usual for port 587), `tls` (usual for port 465), or `off` (only for a trusted local relay). |
| `MUKURTU_SMTP_USER` | | SMTP user name. Leave empty if the server needs no authentication. |
| `MUKURTU_SMTP_PASSWORD` | | SMTP password. Also accepts [`MUKURTU_SMTP_PASSWORD_FILE`](#secrets). |
| `MUKURTU_SMTP_FROM` | | Forces the envelope sender address. Many providers only accept mail from addresses they have verified. If empty, the address Drupal supplies is used. |

### Advanced

| Variable | Default | Description |
| --- | --- | --- |
| `MUKURTU_RUN_DIR` | `/run/mukurtu` | Where the rendered configuration is written. Change it only if `/run` is not usable; it should be on a tmpfs or other writable location. |

### Used by the Compose examples only

These are read by the example Compose files, not by the container.

| Variable | Default | Description |
| --- | --- | --- |
| `MUKURTU_IMAGE` | the `latest` tag of the published image | Which image to run. Pin a version. |
| `MUKURTU_HOST` | | The site's host name, passed to `MUKURTU_TRUSTED_HOSTS`. |

## Secrets

Four settings can be given as a file instead of a value, by adding `_FILE` to the
name and giving the path of the file:

| Setting | File form |
| --- | --- |
| `MUKURTU_DB_PASSWORD` | `MUKURTU_DB_PASSWORD_FILE` |
| `MUKURTU_ADMIN_PASSWORD` | `MUKURTU_ADMIN_PASSWORD_FILE` |
| `MUKURTU_HASH_SALT` | `MUKURTU_HASH_SALT_FILE` |
| `MUKURTU_SMTP_PASSWORD` | `MUKURTU_SMTP_PASSWORD_FILE` |

The file form is meant for Docker and Kubernetes secrets (mounted at
`/run/secrets/<name>`). If both forms are set, the file wins. A trailing newline in
the file is ignored. The file must be readable by uid 1000.

Prefer the file form. Anyone who can run `docker inspect` on the container sees
plain environment variables, including the database password.

## Volumes and permissions

| Path in the container | Holds | Notes |
| --- | --- | --- |
| `/var/www/files/public` | Public uploads and generated image styles | Served directly by nginx. |
| `/var/www/files/private` | Private files, the generated hash salt, and the configuration sync directory | **Required.** Mukurtu's access control depends on Drupal's private file system. |

Back up both, together with the database (see [Upgrades and
backups](#upgrades-and-backups)).

Both must be writable by **uid 1000**. A fresh named volume works without any
preparation. For a bind mount, make the host directory writable by that uid:

```
sudo chown -R 1000:1000 /srv/mukurtu/files
```

If a directory is not writable, the container stops at start-up with `ERROR:
/var/www/files/private is not writable by uid 1000`, naming the path.

The code and configuration in the image are read-only, so the container can run
with `read_only: true` provided `/tmp` and `/run` are writable (the Compose
examples mount tmpfs there).

## Behind a reverse proxy

Run a proxy that terminates HTTPS and forwards to port 8080 of the container
(Traefik, Caddy, nginx, a cloud load balancer). Then:

1. Set `MUKURTU_TRUSTED_HOSTS` to the public host name(s).
2. Set `MUKURTU_REVERSE_PROXY=true` and `MUKURTU_REVERSE_PROXY_ADDRESSES` to the
   proxy's address or range, so Drupal knows the real client address and that the
   site is served over HTTPS.
3. Set HSTS and Content-Security-Policy **at the proxy**. The container sets
   `X-Content-Type-Options` and `Referrer-Policy` itself, but only the proxy
   knows the site's real scheme and origins.
4. Make sure the proxy allows uploads as large as `MUKURTU_UPLOAD_MAX`, and does
   not buffer responses for `/system/files/` (large private downloads).

## Sizing memory

A site's memory comes from a few parts. The first three are mostly fixed; the last
two grow with traffic and uploads.

| Part | Roughly | Controlled by |
| --- | --- | --- |
| nginx and the supervising scripts | about 12 MB | `MUKURTU_NGINX_WORKER_PROCESSES` |
| opcache (compiled code), shared by all workers | most of the idle footprint, up to `MUKURTU_OPCACHE_MEMORY` | `MUKURTU_OPCACHE_MEMORY` |
| APCu, shared by all workers | up to `MUKURTU_APCU_MEMORY` | `MUKURTU_APCU_MEMORY` |
| One PHP worker | up to `MUKURTU_PHP_MEMORY_LIMIT` while serving a request | `MUKURTU_FPM_MAX_CHILDREN`, `MUKURTU_FPM_FILES_MAX_CHILDREN` |
| `ffmpeg` / `pdftoppm` | **not counted in `memory_limit`** | the size of the media |

The last row is the one that surprises people. Mukurtu runs `ffmpeg` (video
thumbnails) and `pdftoppm` (PDF thumbnails) *inside the upload request*. Their
memory belongs to the container, not to the PHP process, so a container memory
limit sized only for PHP can be exceeded and the container killed in the middle of
an upload. Leave headroom: the Compose examples give each site **1 GB**.

**Measured** on a freshly installed site (details and conditions in
[docker-build.md](docker-build.md#measured-footprint)): about **90 MB** when idle,
about **145 MB** with four PHP workers serving requests at once, and **160 MB** for
`ffmpeg` extracting a thumbnail from a 100 MB 1080p video. Those are floors: a site
with real content uses more per worker, up to `MUKURTU_PHP_MEMORY_LIMIT`. The 1 GB
limit covers the worst case, roughly: idle footprint, plus one worker at its limit
(512 MB), plus `ffmpeg` (160 MB or more), plus twice the uploaded file if
`MUKURTU_FILE_TEMP_PATH` is a tmpfs (about 960 MB for a 100 MB video).

To fit more sites on a host, lower `MUKURTU_FPM_MAX_CHILDREN` (a low-traffic site
rarely needs more than 2 or 3) and keep `MUKURTU_FPM_PM=ondemand`. To make a busy
site faster, raise `MUKURTU_FPM_MAX_CHILDREN` and the container's memory limit
together.

## Mail

The container includes a small mail relay (`msmtp`). Set `MUKURTU_SMTP_HOST`
(and the port, security mode, user, and password) and everything Mukurtu sends goes
through that server. Use a real mail provider; mail sent straight from a server
without the right DNS records (SPF, DKIM) is usually rejected or marked as spam.

If `MUKURTU_SMTP_HOST` is not set, the site cannot send mail at all, and the
container says so in its log at every start. Password resets and notifications will
not arrive.

## Search (Solr)

Search works out of the box with Drupal's database search backend. If you run an
Apache Solr server, the connection is not an environment variable: it is stored
in the site's configuration as a Search API server. Configure it in the
administration pages under *Configuration, Search and metadata, Search API*, as on
any Mukurtu site. The container needs network access to the Solr host; nothing in
the image needs to change.

## Upgrades and backups

**Upgrade** by changing the image tag and recreating the container:

```
# edit MUKURTU_IMAGE in .env to the new version, then
docker compose pull mukurtu
docker compose up -d mukurtu
```

At start-up the container applies database updates and rebuilds caches
(`MUKURTU_RUN_UPDATES=true`), then serves requests. **Back up before upgrading**,
and read the release notes: some releases must be passed through rather than
skipped. To go back, restore the backup and run the previous tag; databases are
not downgraded.

**Back up** three things together, taken at the same moment if you can:

1. The database: `docker compose exec db mariadb-dump --single-transaction -u root -p mukurtu > mukurtu.sql`
   (or `docker compose exec mukurtu drush sql:dump > mukurtu.sql`).
2. The `public` volume.
3. The `private` volume.

Keep `MUKURTU_HASH_SALT` with the backup if you set it explicitly.

## Running commands

`docker exec` runs a command in the running container with the right environment:

```
docker compose exec mukurtu drush status
docker compose exec mukurtu drush user:login          # one-time login link
docker compose exec mukurtu drush cache:rebuild
docker compose exec mukurtu drush sql:cli
```

You can also run a one-off container. It prepares the same configuration but does
not wait for the database, install, or update:

```
docker compose run --rm mukurtu drush status
```

## Hosting many sites

Run one container per site, from the same image, each with its own environment,
database, and volumes, behind one reverse proxy that routes by host name.
[`docker-compose.multisite.example.yml`](../docker-compose.multisite.example.yml)
shows two sites with Traefik and automatic HTTPS certificates. Each site can be
upgraded, restarted, and limited separately, and one site's failure cannot reach
another's data. See [Sizing memory](#sizing-memory) for fitting more sites on a
host, and [docker-build.md](docker-build.md#hosting-examples) for why the examples
are laid out this way.

## Troubleshooting

| What you see | Meaning and fix |
| --- | --- |
| `ERROR: <VARIABLE> is required` | A required setting is missing. |
| `ERROR: <VARIABLE> must be ...` | The value is not valid. The message says what is expected and what was received. |
| `ERROR: MUKURTU_REVERSE_PROXY_ADDRESSES is required when MUKURTU_REVERSE_PROXY=true` | Say which addresses the proxy connects from. |
| `ERROR: database <host>:<port> not reachable after <n>s (last error: ...)` | The database did not accept connections. Check the host name, the network, and the credentials; the last error is included. Raise `MUKURTU_DB_WAIT_TIMEOUT` if it is merely slow. |
| `ERROR: database <name> contains tables but not a complete Drupal installation` | The container refuses to install over unknown data. Empty the database, or restore a backup. |
| `ERROR: MUKURTU_ADMIN_PASSWORD is required` | The database is empty and `MUKURTU_AUTO_INSTALL` is `true`; give the first administrator a password, or set `MUKURTU_AUTO_INSTALL=false`. |
| `ERROR: <path> is not writable by uid 1000` | A volume or `MUKURTU_FILE_TEMP_PATH` cannot be written. See [Volumes and permissions](#volumes-and-permissions). |
| `WARNING: ffmpeg is not installed in this image` | You are running the slim image. Use the full image if you need that feature. |
| `WARNING: MUKURTU_SMTP_HOST is not set` | The site cannot send mail. See [Mail](#mail). |
| Pages answer `400 Bad Request` | The `Host` header is not in `MUKURTU_TRUSTED_HOSTS`. |
| Uploads fail without a message | Check `MUKURTU_UPLOAD_MAX`, and the limits of the reverse proxy in front. |
| A video upload kills the container | Out of memory: `ffmpeg` is outside PHP's limit. Raise the container's memory limit; see [Sizing memory](#sizing-memory). |
| The container shows `unhealthy` right after the first start | A fresh install takes a few minutes. The health check allows ten minutes before it counts failures. |
| Large downloads make other private downloads wait | The private file pool is full. Raise `MUKURTU_FPM_FILES_MAX_CHILDREN`. |

## Building the image yourself

The published images are built by the workflow in
`.github/workflows/docker-publish.yml`. To build one locally from a checkout:

```
docker build \
  --build-arg MUKURTU_TEMPLATE_VERSION="$(cat docker/TEMPLATE_VERSION)" \
  --target runtime \
  -t mukurtu:local .
```

That builds the **full** image. For the slim image, add
`--build-arg WITH_FFMPEG=false --build-arg WITH_POPPLER=false`.

These are options for *building* the image: they decide what is inside it, and the
running container cannot change them. They are not environment variables. (That is
why "slim" is a different tag and not a setting.)

| Build argument | Default | Values | Meaning |
| --- | --- | --- | --- |
| `WITH_FFMPEG` | `true` | `true` or `false` | Include `ffmpeg` (video thumbnails). |
| `WITH_POPPLER` | `true` | `true` or `false` | Include `poppler-utils` (PDF text and thumbnails). |
| `MUKURTU_TEMPLATE_VERSION` | none: required | a `mukurtu/mukurtu-template` version | The project template the image is built on. The pinned version is in `docker/TEMPLATE_VERSION`. |

The build stops with a message naming the argument if one is invalid or missing.
`scripts/docker/test-*.sh` are the tests; each builds what it needs and takes
from seconds to several minutes (the install test performs a real installation).
