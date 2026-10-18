# Docker image for Mukurtu CMS: design

Status: approved by the owner (2026-10-17), revised after the security and performance review. Implementation in progress on branch `docker-image`.

## Intent

Publish a small, production-grade container image of Mukurtu CMS to GitHub
Container Registry (GHCR) automatically whenever a GitHub release is published.

The motivating use is hosting: one VPS running many independent Mukurtu sites
(tenants), each at its own domain. The image must therefore idle cheaply and be
fully configurable by environment variables, with no file editing at runtime.

The work is intended to be proposed upstream to `MukurtuCMS/Mukurtu-CMS`, so it
must work unchanged in the upstream repository and in forks.

## Goals

- One image, published to `ghcr.io/<repository owner>/mukurtu` on `release: published`.
- As small and light as practical: multi-stage build, Alpine, no Composer or
  dev tooling in the final image, non-root runtime.
- Every operationally important setting is an environment variable, required
  and optional, each documented in one place.
- First boot against an empty database installs the site automatically.
- Image contents are exactly the code of the release tag.
- Every external tool Mukurtu needs is installed explicitly and verified at
  build time.
- Follow standard Drupal, PHP, nginx, and container-hardening practice, and
  explain every choice in a public document (`docs/docker-build.md`) written
  for people who need to judge how the image is built. The document grows with
  each piece of the implementation; it is not written afterwards.

## Non-goals

- Bundling a database. The DB is always external.
- Drupal multisite. One container = one site. A future opt-in
  `MUKURTU_MULTISITE` mode is possible; this design does not block it, but it is
  not built now.
- Redis, Memcached, Solr, or a mail server inside the image.
- A development image (no Xdebug, no dev dependencies).
- Replacing the documented Composer or DDEV install paths.

## Anti-goals (a failure even if it technically works)

- An image that silently lacks `ffmpeg` or `poppler-utils` because a default was
  relied on.
- A release image that differs from the tagged code (for example, built from
  whatever Packagist has at the time).
- A site that boots with a public, unsalted, or default-password configuration.
- Secrets that can only be passed as plain env vars with no `_FILE` alternative.

## Decisions (made by the project owner)

| Topic | Decision |
| --- | --- |
| Runtime | nginx + php-fpm on Alpine, PHP 8.4, lowest idle RAM |
| Source | Build `mukurtu/mukurtu-template` as the skeleton, then replace the profile with the checkout of the release tag |
| Template version | Pinned by build arg `MUKURTU_TEMPLATE_VERSION`, never floating |
| Tools | `ffmpeg` and `poppler-utils` via build args with no default; `-slim` variant omits both |
| Database | External. Auto-install on first boot driven by env vars |
| Multi-tenant | Standalone container per tenant, same image, reverse proxy routes by domain |
| Platforms | `linux/amd64`, `linux/arm64` |

## Source of the build, and why not Packagist

`mukurtu-template` is the root Composer project (core, scaffold, plugin
allowlist, installer paths). This repository is only the profile. The pipeline
runs `composer create-project mukurtu/mukurtu-template:$MUKURTU_TEMPLATE_VERSION`,
then points a Composer `path` repository at the checked-out tag with a
`versions` override, as `.tugboat/config.yml` already does.

Packagist is not used for the profile because the release event fires before the
Packagist webhook completes, so a Packagist-based build races the tag.
`docs/releasing.md` also requires the profile to be tagged before the template.

## Image

Stage 1 (`build`): Composer, `--no-dev --optimize-autoloader`
(not `--classmap-authoritative`: Drupal registers namespaces at run time and
an authoritative class map breaks it). Remove tests, docs, `.git`, node tooling, and other
non-runtime files from vendor and the profile.

Stage 2 (`runtime`): Alpine with php-fpm, nginx, a small process supervisor
(s6-overlay or equivalent), `msmtp` for outbound mail, `mariadb-client` (for the
DB wait and install check), and Drush. No Composer.

Build args, all required, no defaults, checked at the top of the Dockerfile with
a clear failure message:

| Arg | Values | Meaning |
| --- | --- | --- |
| `WITH_FFMPEG` | `true` / `false` | Install `ffmpeg` |
| `WITH_POPPLER` | `true` / `false` | Install `poppler-utils` |

Other pinned args: `PHP_VERSION`, `ALPINE_VERSION`, `COMPOSER_VERSION`,
`MUKURTU_TEMPLATE_VERSION`. The workflow supplies every one explicitly.

Build-time assertions (the build fails if any is wrong):

- With `WITH_FFMPEG=true`: `ffmpeg -version` succeeds. With `false`: `ffmpeg` is absent.
- With `WITH_POPPLER=true`: `pdftotext -v` and `pdftoppm --help` succeed. With
  `false`: both are absent. These are the exact probes in
  `modules/mukurtu_core/src/Hook/MediaToolRequirements.php`.
- `php -m` lists: `gd`, `intl`, `zip`, `Zend OPcache`, `pdo_mysql`, `mbstring`,
  and the other extensions Drupal core requires.
- `gd` reports JPEG, WebP, and FreeType support.
- `exec` is not in `disable_functions` (Mukurtu needs `exec()` for thumbnails and
  PDF text).

The entrypoint also logs a warning at boot for each missing tool, so a slim
container says what it cannot do.

Runtime properties: non-root user, `HEALTHCHECK` against a lightweight endpoint,
PHP errors and nginx logs to stdout/stderr, `validate_timestamps=0` (the image
is immutable), JIT off.

Volumes: `/var/www/files/public` and `/var/www/files/private`. The private
volume is required for Mukurtu's access control; the entrypoint refuses to
start without it being writable.

## Tags and publishing

Workflow: `.github/workflows/docker-publish.yml`.

- Trigger: `release: published`, plus `workflow_dispatch` with a tag input. A
  release created by another workflow using `GITHUB_TOKEN` does not trigger
  `release: published`, so the manual path is the fallback.
- Image name: `ghcr.io/${{ github.repository_owner }}/mukurtu`, lowercased. No
  hard-coded owner, so it works in forks and upstream.
- Auth: `GITHUB_TOKEN` with `packages: write`. Upstream must set the GHCR
  package visibility to public once.
- Matrix: two variants, set explicitly per row.
  - full: `WITH_FFMPEG=true`, `WITH_POPPLER=true`
  - slim: `WITH_FFMPEG=false`, `WITH_POPPLER=false`
- Tags (via `docker/metadata-action`): `4.0.5`, `4.0`, `4`, `latest` for full;
  the same with a `-slim` suffix for slim. Pre-releases get only the exact tag
  and never move `latest`, `4`, or `4.0`.
- Buildx multi-arch, GitHub Actions layer cache, provenance and SBOM
  attestations.
- Trivy scan and `composer audit` results gate the push (see Security hardening).
- No scheduled rebuild: a published tag is a fixed artifact and is never re-pushed. Fixes to base images or Drupal reach users in a new release; Dependabot and the Trivy gate keep that release current and clean. (An opt-in weekly rebuild was designed and then removed at the owner's request.)
- Smoke test before push: start the built image against a MariaDB service
  container, wait for auto-install, and request the front page.

## Environment variables

Every variable is optional unless marked **required**. Secrets accept a `_FILE`
variant (path to a file, for Docker/Compose secrets): `MUKURTU_DB_PASSWORD_FILE`,
`MUKURTU_ADMIN_PASSWORD_FILE`, `MUKURTU_HASH_SALT_FILE`. If both forms are set,
`_FILE` wins.

### Database

| Variable | Default | Notes |
| --- | --- | --- |
| `MUKURTU_DB_HOST` | **required** | |
| `MUKURTU_DB_NAME` | **required** | Must be utf8mb4 (README requirement) |
| `MUKURTU_DB_USER` | **required** | |
| `MUKURTU_DB_PASSWORD` | **required** | or `_FILE` |
| `MUKURTU_DB_PORT` | `3306` | |
| `MUKURTU_DB_PREFIX` | empty | |

### Site and install

| Variable | Default | Notes |
| --- | --- | --- |
| `MUKURTU_AUTO_INSTALL` | `true` | Install on first boot if the DB has no Drupal tables |
| `MUKURTU_ADMIN_USER` | `admin` | |
| `MUKURTU_ADMIN_PASSWORD` | **required if auto-install runs** | No default; the entrypoint refuses to install without one. or `_FILE` |
| `MUKURTU_ADMIN_EMAIL` | **required if auto-install runs** | |
| `MUKURTU_SITE_NAME` | `Mukurtu` | |
| `MUKURTU_SITE_EMAIL` | admin email | |
| `MUKURTU_LOCALE` | `en` | Install language |
| `MUKURTU_HASH_SALT` | generated once, persisted in the private volume | or `_FILE` |
| `MUKURTU_TRUSTED_HOSTS` | **required** | Comma-separated hostnames; turned into `trusted_host_patterns` |
| `MUKURTU_RUN_UPDATES` | `true` | `drush updb` and cache rebuild on boot |
| `MUKURTU_CONFIG_SYNC_DIR` | `/var/www/files/private/config-sync` | |
| `MUKURTU_CRON` | `true` | Run Drupal cron inside the container |
| `MUKURTU_CRON_INTERVAL` | `3600` | Seconds |
| `MUKURTU_DB_WAIT_TIMEOUT` | `60` | Seconds to wait for the database at boot before failing |

### Reverse proxy

| Variable | Default | Notes |
| --- | --- | --- |
| `MUKURTU_REVERSE_PROXY` | `false` | Trust `X-Forwarded-*` |
| `MUKURTU_REVERSE_PROXY_ADDRESSES` | empty | Comma-separated proxy IPs/CIDRs; required when the above is `true` |

### PHP

| Variable | Default | Notes |
| --- | --- | --- |
| `MUKURTU_PHP_MEMORY_LIMIT` | `512M` | Upstream recommendation |
| `MUKURTU_UPLOAD_MAX` | `100M` | Sets `upload_max_filesize`, `post_max_size`, and nginx `client_max_body_size` together |
| `MUKURTU_PHP_MAX_EXECUTION_TIME` | `120` | Also used for nginx FastCGI timeouts |
| `MUKURTU_PHP_MAX_INPUT_VARS` | `3000` | Value used by `.tugboat/config.yml` |
| `MUKURTU_PHP_CLI_MEMORY_LIMIT` | `1G` | Memory limit for Drush (install, updates, cron). The CLI otherwise has no php.ini and defaults to 128M |
| `MUKURTU_PHP_TIMEZONE` | `UTC` | PHP default time zone |
| `MUKURTU_APCU_MEMORY` | `32M` | APCu shared memory; Drupal's chained-fast cache uses it |
| `MUKURTU_FILE_TEMP_PATH` | `/tmp` | Drupal `file_temp_path`. Uploads land here first. The operator chooses what backs it: a tmpfs (uses RAM) or a size-capped volume |
| `MUKURTU_OPCACHE_MEMORY` | `128` | MB. Sized for Mukurtu; raise if `opcache_get_status()` shows it full |
| `MUKURTU_OPCACHE_MAX_FILES` | `20000` | |

### php-fpm (tenant density)

| Variable | Default | Notes |
| --- | --- | --- |
| `MUKURTU_FPM_PM` | `ondemand` | `ondemand`, `dynamic`, or `static`. `ondemand` frees all workers when idle |
| `MUKURTU_FPM_MAX_CHILDREN` | `4` | Hard cap on concurrent PHP requests |
| `MUKURTU_FPM_PROCESS_IDLE_TIMEOUT` | `10s` | `ondemand` only |
| `MUKURTU_FPM_START_SERVERS` | `2` | `dynamic` only |
| `MUKURTU_FPM_MIN_SPARE_SERVERS` | `1` | `dynamic` only |
| `MUKURTU_FPM_MAX_SPARE_SERVERS` | `3` | `dynamic` only |
| `MUKURTU_FPM_MAX_REQUESTS` | `500` | Recycle workers to bound memory growth |
| `MUKURTU_FPM_FILES_MAX_CHILDREN` | `2` | Cap for the second pool that serves `/system/files/*` (private downloads), so large downloads cannot starve page loads |

### nginx

| Variable | Default | Notes |
| --- | --- | --- |
| `MUKURTU_NGINX_WORKER_PROCESSES` | `1` | Low-memory default |
| `MUKURTU_NGINX_WORKER_CONNECTIONS` | `1024` | |

### Outbound mail (msmtp)

| Variable | Default | Notes |
| --- | --- | --- |
| `MUKURTU_SMTP_HOST` | empty | If empty, mail is not configured and the entrypoint warns |
| `MUKURTU_SMTP_PORT` | `587` | |
| `MUKURTU_SMTP_USER` | empty | |
| `MUKURTU_SMTP_PASSWORD` | empty | or `_FILE` |
| `MUKURTU_SMTP_TLS` | `starttls` | `starttls`, `tls`, or `off` |
| `MUKURTU_SMTP_FROM` | site email | |

### Search (optional Solr)

| Variable | Default | Notes |
| --- | --- | --- |
| `MUKURTU_SOLR_HOST` | empty | If set, overrides the Search API Solr connector config |
| `MUKURTU_SOLR_PORT` | `8983` | |
| `MUKURTU_SOLR_CORE` | empty | |
| `MUKURTU_SOLR_SCHEME` | `http` | |

Solr is the least certain item: the exact Search API server ID and config path
need checking against the profile's config before implementing. If that proves
brittle, it moves out of v1 and into documentation.

## Security hardening

Each item is explained in `docs/docker-build.md` with the reason for it.

nginx:
- Never execute PHP under `sites/*/files`; only `index.php` is passed to
  php-fpm; deny dotfiles, `*.yml`, `*.twig`, `composer.*`, `vendor`, and
  direct `update.php` / `install.php` (updates run through `drush updb`).
- Image-style requests are routed through `index.php`.
- `server_tokens off`, `X-Content-Type-Options: nosniff`,
  `Referrer-Policy`. HSTS and CSP are left to the reverse proxy and documented.

PHP:
- `expose_php=0`, `display_errors=0`, `cgi.fix_pathinfo=0`,
  `zend.assertions=-1`, `zend.exception_ignore_args=1`, `allow_url_include=0`,
  strict session cookies (`httponly`, `use_strict_mode`, `samesite`).
- `opcache.save_comments=1` stays on (Drupal reads attributes and annotations).
- `exec` stays enabled because Mukurtu needs it. The residual risk is
  documented.
- php-fpm `clear_env = no`, so `getenv()` works in `settings.php`. Documented
  with the reasoning (code running in the pool can read `settings.php` anyway).
- The CLI shares the rendered ini (`PHPRC`), so Drush has the same limits.

Container:
- Non-root, `read_only` root filesystem, `cap_drop: [ALL]`,
  `no-new-privileges` in the compose examples. `settings.php` is baked into
  the image.
- `tini` is PID 1, so orphaned `ffmpeg` and `pdftoppm` processes are reaped.
- Secrets via `_FILE`; the docs say environment variables are visible in
  `docker inspect`.

Supply chain:
- Base images pinned by digest; Dockerfile and Actions entries in
  `.github/dependabot.yml`.
- `composer audit` in the build stage.
- Trivy scan before push, failing on fixable HIGH or CRITICAL findings.
- The resolved `composer.lock` is copied into the image and attested. The
  template ships no lock file, so two builds of one tag can resolve different
  Drupal and contrib versions; the lock records exactly what shipped.
- An explicit `MUKURTU_HASH_SALT` is recommended for production. An
  auto-generated salt lives in the private volume; losing that volume changes
  the salt and invalidates image-style tokens.

## Performance

- APCu installed and sized by `MUKURTU_APCU_MEMORY`.
- `realpath_cache_size=4096K`, `realpath_cache_ttl=600`,
  `opcache.interned_strings_buffer=16`, plus the opcache settings in the env
  table.
- nginx: `sendfile`, gzip, long `Cache-Control` for aggregated CSS and JS and
  for public files, `open_file_cache`.
- php-fpm: `request_terminate_timeout` equals max execution time.
- Healthcheck uses php-fpm's `ping.path`, which does not bootstrap Drupal.
- Second php-fpm pool for `/system/files/*` (see Known hazards).
- MariaDB settings in the compose examples: `transaction_isolation=READ-COMMITTED`
  with `binlog_format=ROW`, `max_allowed_packet=64M`, tuned
  `innodb_buffer_pool_size`.

## Known hazards (documented, mitigated where possible)

1. Private files stream through PHP. Every private download or video range
   request holds a php-fpm worker. Mitigation: a second pool for
   `/system/files/*` capped by `MUKURTU_FPM_FILES_MAX_CHILDREN`. Residual: a
   download-heavy site needs that value raised. Serving private files with
   `X-Accel-Redirect` would remove the limit but needs code in the profile and
   is out of scope.
2. `ffmpeg` and `pdftoppm` run synchronously inside the upload request and
   their memory is not covered by PHP's `memory_limit`; it counts toward the
   container limit. The docs and compose examples reserve headroom (768M–1G
   per tenant) and the measured figures in Task 10 include a video upload.
3. Anything an operator passes in the environment is visible in `docker
   inspect`; use `_FILE` secrets.

## Hosting many tenants

Run one container per site from the same image, behind a reverse proxy that
routes by domain and terminates TLS.

- Each tenant has its own env, database, and two volumes. Upgrades are per
  tenant: change the image tag, recreate the container, `MUKURTU_RUN_UPDATES`
  applies update hooks.
- One shared MariaDB, one database per tenant (utf8mb4), is the example default.
- Estimated cost (not measured): about 150–250 MB per warm idle tenant, plus
  roughly 60–120 MB per concurrent PHP request. Measure with `docker stats` once
  the image exists and replace these figures in the docs.
- `docker-compose.multisite.example.yml` uses Traefik, per-service `mem_limit`,
  and two tenants at different domains.

## Files to add

- `Dockerfile`
- `.dockerignore`
- `docker/` (nginx config, php config templates, php-fpm pool template, msmtp
  template, entrypoint and helper scripts, healthcheck; the nginx template
  includes the Drupal rules above)
- `docker-compose.example.yml` (one site plus MariaDB)
- `docker-compose.multisite.example.yml`
- `.github/workflows/docker-publish.yml`
- `docs/docker-build.md`: how the image is built and why (inputs, source of the code, runtime, configuration, startup, security hardening, performance tuning, known hazards, publishing and supply chain, measured footprint). Written for people evaluating the image; grows with every implementation task
- `docs/docker.md` (usage, the full variable reference above, tags, upgrade
  procedure, multi-tenant hosting)
- A short pointer from `README.md` and from `docs/releasing.md` (the release
  step now also publishes images)

## Left to the builder

- Choice between s6-overlay and a smaller supervisor, by image size.
- Exact Alpine and PHP minor versions to pin.
- Healthcheck endpoint.
- How the entrypoint renders config from env (envsubst or a small shell
  script), as long as every variable above is honored and validated, with a
  clear error on bad values.
- Exact wording of the smoke test.

## My calls (not yet confirmed by the owner)

- `_FILE` variants for secrets.
- `msmtp` and the `MUKURTU_SMTP_*` variables: the profile ships no SMTP module,
  and Drupal core mail needs a local sendmail, so without this no mail works.
- `MUKURTU_LOCALE`, `MUKURTU_SITE_*`, cron variables, and the numeric defaults
  (`FPM_MAX_CHILDREN=4`, `OPCACHE_MEMORY=128`, and so on) are starting points to
  be validated by measurement.
- Refusing to auto-install without an admin password rather than generating one.
- Logging to stdout/stderr only.
- Optional Solr variables (see the caveat above).
