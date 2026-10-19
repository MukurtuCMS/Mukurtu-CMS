# Docker Image Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Publish a small, env-var-configured Mukurtu CMS image to GHCR on every GitHub release, suitable for hosting many single-site containers on one VPS.

**Architecture:** Multi-stage Dockerfile. Stage `build` creates the `mukurtu/mukurtu-template` project and swaps the profile for the checked-out release tag. Stage `runtime` is Alpine with nginx + php-fpm run by a small non-root supervisor; an entrypoint validates env, renders config into a tmpfs, waits for the DB, auto-installs on an empty DB, runs updates, then execs the supervisor under `tini`. Shell scripts under `scripts/docker/` test each layer; the publish workflow runs them before pushing.

**Tech Stack:** Docker buildx, Alpine, PHP 8.4 (php-fpm), nginx, Composer 2, Drush 13, msmtp, bash/POSIX sh, GitHub Actions (`docker/metadata-action`, `docker/build-push-action`), Trivy, APCu.

**Spec:** `docs/superpowers/specs/2026-10-17-docker-image-design.md`

> **Amended after execution:** the opt-in weekly rebuild described in Task 8 (schedule trigger, `DOCKER_WEEKLY_REBUILD`, dated tags) was removed at the owner's request: published tags are never rebuilt in place.

## Global Constraints

- PHP 8.4; Drupal core `~11.4.0` (from `composer.json`); Drush 13 is already a profile requirement.
- Final image: no Composer, no dev dependencies (`--no-dev`), non-root user, logs to stdout/stderr only.
- Build args `WITH_FFMPEG` and `WITH_POPPLER` have **no defaults**; the build fails with a clear message when either is not exactly `true` or `false`.
- Tools probed exactly as `modules/mukurtu_core/src/Hook/MediaToolRequirements.php` does: `pdftotext -v`, `pdftoppm --help`, `ffmpeg -version`. `exec` must not be in `disable_functions`.
- Required PHP extensions asserted at build: `gd` (JPEG, WebP, FreeType), `intl`, `zip`, `Zend OPcache`, `pdo_mysql`, `mbstring`.
- Build source is the checkout of the release tag, never Packagist, on top of `mukurtu/mukurtu-template` pinned by `MUKURTU_TEMPLATE_VERSION`. Other pinned args: `PHP_VERSION`, `ALPINE_VERSION`, `COMPOSER_VERSION`.
- Image name `ghcr.io/${{ github.repository_owner }}/mukurtu`, lowercased; no hard-coded owner anywhere.
- Platforms `linux/amd64`, `linux/arm64`. Volumes `/var/www/files/public`, `/var/www/files/private`.
- Env var names, defaults, and `_FILE` secret variants exactly as in the spec's "Environment variables" section. No admin-password default; auto-install refuses without one.
- Repo conventions: bare version tags (`4.0.5`, `4.0.0-beta37`); new docs in `docs/`; shell scripts in `scripts/` (existing: `scripts/lint`, `scripts/tugboat`).
- Documentation as we go: `docs/docker-build.md` (public, for people evaluating how the image is built) gains the section named in each task before that task's commit. `scripts/docker/test-docs.sh` (created in Task 1, extended in later tasks) fails if a section for a finished task is missing. Sections explain what was chosen and why, not just what.
- Security and performance items in the spec's "Security hardening", "Performance", and "Known hazards" sections are requirements, not suggestions; the task that owns each is named below.
- Standard practice: where Drupal, PHP, nginx, or Docker have a documented convention, follow it and cite it in `docs/docker-build.md` rather than inventing one.
- Container listens on port 8080 (non-root cannot bind 80). This is the plan's call; the docs must say so.

## Review Focus

In addition to the list below, every item in the spec's Security hardening list has a test or a documented reason it cannot be tested here.

1. Secrets containing `$ " ' \ @ # %` (DB password, SMTP password, admin password) arrive byte-identical in Drupal settings, php-fpm, and msmtp. Pinned in Task 4, Task 5.
2. `MUKURTU_TRUSTED_HOSTS=example.com` must not accept `exampleXcom` (regex-escaped dots) and must accept an uppercase `Example.COM` request host. Pinned in Task 5.
3. A named volume mounted fresh (empty, image-default ownership) or a bind mount owned by root: the container either works or fails with one clear message naming the path. Pinned in Task 6.
4. Restart of an already-installed container neither re-installs nor changes the hash salt, and a DB that is not ready at boot is waited for, then fails with a clear timeout message. Pinned in Task 6.
5. Under php-fpm (not just the CLI), `getenv('MUKURTU_DB_HOST')` returns a value; Drush has the rendered php.ini (`memory_limit` = `MUKURTU_PHP_CLI_MEMORY_LIMIT`). Pinned in Task 6.
6. A PHP file uploaded to `sites/default/files` is never executed (404/403/downloaded as text, never run). Pinned in Tasks 4 and 6.
7. Four concurrent slow private-file downloads do not stop the front page from answering. Pinned in Task 6.
8. `MUKURTU_REVERSE_PROXY=true` with no `MUKURTU_REVERSE_PROXY_ADDRESSES`, and `MUKURTU_UPLOAD_MAX=100MB` (invalid unit), fail fast naming the variable. Pinned in Task 4.

---

## File Structure

| Path | Responsibility |
| --- | --- |
| `Dockerfile` | Stages `guard`, `build`, `runtime`; build-time assertions |
| `.dockerignore` | Keep context small; never ship `.git`, `docs`, `tests` into the context |
| `docker/lib.sh` | Sourced helpers: `env_or_file`, `require_env`, `validate_size`, `validate_bool`, `die` |
| `docker/render-config.sh` | Validate env, render templates in `docker/templates/` to `$MUKURTU_RUN_DIR` |
| `docker/templates/` | `php.ini.tmpl`, `php-fpm.conf.tmpl`, `nginx.conf.tmpl`, `msmtprc.tmpl` |
| `docker/settings.php` | Drupal `settings.php` that reads all site config from env |
| `docker/entrypoint.sh` | Orchestration: render, volume checks, DB wait, install, updb, exec supervisor |
| `docker/supervise.sh` | Non-root supervisor for nginx, php-fpm, cron loop; forwards signals |
| `docker/cron.sh` | Loop running `drush core:cron` every `MUKURTU_CRON_INTERVAL` |
| `docker/healthcheck.sh` | HTTP probe of local nginx |
| `docker-compose.example.yml`, `docker-compose.multisite.example.yml` | Usage examples |
| `.github/workflows/docker-publish.yml` | Test, then publish on release |
| `scripts/docker/test-*.sh` | One test script per task; each exits non-zero on failure |
| `docs/docker.md` | Usage, full variable reference, tags, upgrades, multi-tenant hosting |
| `docs/docker-build.md` | How the image is built and why; written task by task |
| `.github/dependabot.yml` | Modify: add `docker` and `github-actions` ecosystems if absent |

Shared test helpers live in `scripts/docker/lib.sh` (`assert_eq`, `assert_contains`, `assert_fails`, `build_image <target> <tag> [build args...]`), created in Task 1.

---

### Task 1: Build-arg guard and test harness

**Files:**
- Create: `Dockerfile`, `.dockerignore`, `scripts/docker/lib.sh`, `scripts/docker/test-guard.sh`, `scripts/docker/test-docs.sh` (skeleton), `docs/docker-build.md`

**Interfaces:**
- Produces: Dockerfile stage `guard` (first stage; later stages `FROM guard` or copy its marker); the `ARG` set `WITH_FFMPEG`, `WITH_POPPLER`, `PHP_VERSION`, `ALPINE_VERSION`, `COMPOSER_VERSION`, `MUKURTU_TEMPLATE_VERSION`, none defaulted. `lib.sh` functions listed above, used by every later test.

- [ ] **Step 1: Write `scripts/docker/test-guard.sh`** asserting, via `build_image guard`: build fails with a message containing `WITH_FFMPEG` when it is unset; fails containing `WITH_POPPLER` when `WITH_POPPLER=yes`; succeeds when both are `true`/`false` and the four pinned args are given.
- [ ] **Step 2: Run it** (`scripts/docker/test-guard.sh`); expected: FAIL (no Dockerfile).
- [ ] **Step 3: Create the `guard` stage** in `Dockerfile` with no-default `ARG`s and a `RUN` that exits 1 with `ERROR: build arg <NAME> must be 'true' or 'false' (got '<value>')` (or `... must be set` for the pinned args).
- [ ] **Step 4: Create `.dockerignore`** excluding `.git`, `docs`, `tests`, `node_modules`, `.github`, `.tugboat`, `.vscode`.
- [ ] **Step 5: Run the test**; expected: PASS.
- [ ] **Step 6: Create `docs/docker-build.md`** with an intro (audience, what it covers) and the section "Pinned inputs and the build guard": why every input is a required build arg, and what the guard refuses. Create `test-docs.sh` taking a list of required `##` headings and failing on any missing; call it with this section. Base images are pinned by digest as well as tag (record how to refresh them).
- [ ] **Step 7: Commit** `Add docker build-arg guard, test harness and build document`.

### Task 2: Build stage (template + tagged checkout)

**Files:**
- Modify: `Dockerfile` (stage `build`)
- Create: `scripts/docker/test-build-stage.sh`
- Modify: `docs/docker-build.md` (section "Where the code comes from")

**Interfaces:**
- Consumes: `guard` args.
- Produces: stage `build` containing `/app` (Composer project root with `web/`, `vendor/`, `recipes/`). Profile at `/app/web/profiles/mukurtu` is the checkout, not the Packagist copy. `/app/vendor/bin/drush` exists.

- [ ] **Step 1: Write the test.** Build target `build`; assert `/app/web/profiles/mukurtu/VERSION.md` equals the repo's `VERSION.md`; `/app/vendor/bin/drush` exists; `/app/vendor/bin/phpunit` does not; no `.git` under `/app/web/profiles/mukurtu`; no `tests/` directories under `/app/web/profiles/mukurtu` or `/app/vendor`; `composer audit` ran in the stage (the build fails on a known advisory); `/app/composer.lock` exists.
- [ ] **Step 2: Run; expected FAIL** (no `build` stage).
- [ ] **Step 3: Implement stage `build`** from `composer:${COMPOSER_VERSION}` plus the PHP extensions Composer needs to resolve (use `--ignore-platform-req` only if unavoidable, and say why in a comment). Steps: `composer create-project mukurtu/mukurtu-template:${MUKURTU_TEMPLATE_VERSION} /app --no-dev --no-install`, add the `path` repository for the copied checkout with `options.versions` override and `symfony/runtime` allow-plugin exactly as `.tugboat/config.yml` lines 38-62 do, then `composer install --no-dev --optimize-autoloader` (not `--classmap-authoritative`; see the ledger ruling). Prune tests/docs/`.git`/node tooling from `vendor` and the profile.
- [ ] **Step 4: Run; expected PASS** (slow first run).
- [ ] **Step 5: Document** "Where the code comes from": template plus tagged checkout, why not Packagist (webhook race, release order in `docs/releasing.md`), that the template ships no `composer.lock` so builds resolve fresh versions and the resolved lock is kept (`/var/www/html/composer.lock`) and attested, and what `composer audit` does.
- [ ] **Step 6: Commit** `Add docker build stage from template and tagged checkout`.

### Task 3: Runtime stage, tools, and extensions (full and slim)

**Files:**
- Modify: `Dockerfile` (stage `runtime`, final)
- Create: `scripts/docker/test-runtime.sh`
- Modify: `docs/docker-build.md` (section "Runtime image, tools and extensions")

**Interfaces:**
- Consumes: `/app` from `build`.
- Produces: final image with `/app` at `/var/www/html` (docroot `/var/www/html/web`), user `mukurtu` (uid 1000), `drush` on `PATH`, directories `/var/www/files/public` and `/var/www/files/private` owned by that user and declared `VOLUME`s, no `composer` binary.

- [ ] **Step 1: Write the test** building `runtime` as `mukurtu:test-full` (`WITH_FFMPEG=true WITH_POPPLER=true`) and `mukurtu:test-slim` (both `false`). Assert full: `ffmpeg -version`, `pdftotext -v`, `pdftoppm --help` exit 0. Assert slim: all three `command -v` fail. Assert both: `php -m` contains each required extension; `php -r` shows `gd_info()` with JPEG, WebP, FreeType true; `ini_get('disable_functions')` does not contain `exec`; `command -v composer` fails; `id -u` is not 0; `apcu` loaded; `tini` present; image size printed (informational).
- [ ] **Step 2: Run; expected FAIL.**
- [ ] **Step 3: Implement stage `runtime`** from `php:${PHP_VERSION}-fpm-alpine${ALPINE_VERSION}`: install nginx, msmtp, mariadb-client, `tini`, the `apcu` extension, gd/intl/zip/opcache/pdo_mysql via `docker-php-ext-*` with build deps removed in the same layer. `RUN` `apk add ffmpeg` / `poppler-utils` conditionally on the args, followed by the assertion for the chosen value (installed and working, or absent). `USER mukurtu`. Add `HEALTHCHECK` wired in Task 6.
- [ ] **Step 4: Run; expected PASS.**
- [ ] **Step 5: Document** "Runtime image, tools and extensions": why Alpine + nginx + php-fpm, each extension and tool and what Mukurtu uses it for (cite `MediaToolRequirements.php`), the full and slim variants, why `exec` must stay enabled and the residual risk, why APCu, why `tini`.
- [ ] **Step 6: Commit** `Add docker runtime stage with explicit media tools`.

### Task 4: Env helpers and config rendering

**Files:**
- Create: `docker/lib.sh`, `docker/render-config.sh`, `docker/templates/php.ini.tmpl`, `docker/templates/php-fpm.conf.tmpl`, `docker/templates/nginx.conf.tmpl`, `docker/templates/msmtprc.tmpl`, `scripts/docker/test-render.sh`, `scripts/docker/test-nginx-rules.sh`
- Modify: `Dockerfile` (copy `docker/` to `/usr/local/lib/mukurtu/`), `docs/docker-build.md` (sections "Configuration rendering", "Security hardening", "Performance tuning")

**Interfaces:**
- Produces: `render-config.sh` reads env, writes `php.ini`, `php-cli.ini`, `php-fpm.conf` (two pools: `www` on socket `$MUKURTU_RUN_DIR/www.sock`, `files` on `$MUKURTU_RUN_DIR/files.sock`), `nginx.conf`, `msmtprc` (mode 0600) into `$MUKURTU_RUN_DIR` (default `/run/mukurtu`), exits 1 printing `ERROR: <VAR> ...` on invalid input. `lib.sh`: `env_or_file VAR` (prints value; `VAR_FILE` wins; exits non-zero if file unreadable), `require_env VAR`, `validate_size VAR` (regex `^[0-9]+[KMG]$`), `validate_bool VAR` (`true|false`), `validate_int VAR`, `die MSG`.

- [ ] **Step 1: Write `test-render.sh`** running `render-config.sh` inside the test image with `MUKURTU_RUN_DIR` set to a temp dir and no DB access. Cases (name each): `defaults` (php.ini has `memory_limit = 512M`, `upload_max_filesize = 100M`, `post_max_size = 100M`, `opcache.memory_consumption=128`, `opcache.validate_timestamps=0`, `opcache.jit=0`; fpm has `pm = ondemand`, `pm.max_children = 4`, `pm.process_idle_timeout = 10s`, `pm.max_requests = 500`; nginx has `client_max_body_size 100M`, `worker_processes 1`, `listen 8080`); `upload-one-var` (`MUKURTU_UPLOAD_MAX=250M` changes all three); `dynamic-pm` (renders start/min/max spare only for `dynamic`); `invalid-size` (`100MB` fails naming `MUKURTU_UPLOAD_MAX`); `invalid-pm`; `proxy-needs-addresses` (fails naming `MUKURTU_REVERSE_PROXY_ADDRESSES`); `file-secret` (`MUKURTU_SMTP_PASSWORD_FILE` beats `MUKURTU_SMTP_PASSWORD`); `special-chars` (SMTP password `p$a"s'\w#%` appears byte-identical, correctly quoted for msmtp); `no-smtp` (no `msmtprc` host line, exit 0, warning on stderr); `exec-allowed` (rendered php.ini never sets `disable_functions` containing `exec`); `hardening-ini` (php.ini has `expose_php = Off`, `display_errors = Off`, `cgi.fix_pathinfo = 0`, `zend.assertions = -1`, `zend.exception_ignore_args = On`, `allow_url_include = Off`, `session.use_strict_mode = 1`, `session.cookie_httponly = 1`, `opcache.save_comments=1`, `realpath_cache_size = 4096K`, `realpath_cache_ttl = 600`, `opcache.interned_strings_buffer=16`, `apc.shm_size=32M`, `date.timezone = UTC`, `sys_temp_dir` and `upload_tmp_dir` = `MUKURTU_FILE_TEMP_PATH`); `fpm-env` (both pools have `clear_env = no` and `request_terminate_timeout` equal to `MUKURTU_PHP_MAX_EXECUTION_TIME`; `files` pool has `pm.max_children = 2`, changed by `MUKURTU_FPM_FILES_MAX_CHILDREN`; `ping.path` set on `www`); `cli-ini` (`php-cli.ini` has `memory_limit` = `MUKURTU_PHP_CLI_MEMORY_LIMIT`, default `1G`).
- [ ] **Step 2: Run; expected FAIL.**
- [ ] **Step 2b: Write `test-nginx-rules.sh`** that renders `nginx.conf` and validates it with `nginx -t`, then runs nginx against stub fastcgi sockets that record which socket received a request, asserting: `/sites/default/files/x.php` is never forwarded; `/index.php` is; `/core/install.php` and `/update.php` return 403/404; `/.git/config`, `/composer.json`, `/vendor/autoload.php`, `/config.yml`, `/x.twig` return 403/404; a missing `/sites/default/files/styles/thumb/public/a.jpg` is routed to `index.php`; `/system/files/a.pdf` goes to the `files` socket and `/node/1` to the `www` socket; responses carry `X-Content-Type-Options: nosniff` and `Referrer-Policy` and no nginx version string; `/sites/default/files/css/x.css` has a long-lived `Cache-Control`.
- [ ] **Step 3: Implement `lib.sh` and `render-config.sh`** with the signatures above. Substitute values with a method that does not re-interpret them (not `sed` with raw values); one template per config. Defaults come from the spec tables. `MUKURTU_PHP_MAX_EXECUTION_TIME` also feeds nginx `fastcgi_read_timeout`.
- [ ] **Step 4: Run; expected PASS.**
- [ ] **Step 5: Document** three sections. "Configuration rendering": env to config, validation, `_FILE` secrets, why values are never interpolated through a shell. "Security hardening" (nginx and PHP halves): each rule with its reason, why `clear_env = no` (and the secrets caveat), why `opcache.save_comments` stays on, `exec`, HSTS/CSP left to the proxy. "Performance tuning": ondemand pool, opcache/realpath/APCu sizing, nginx caching, `request_terminate_timeout`, the two-pool design.
- [ ] **Step 6: Commit** `Render hardened php, fpm, nginx and msmtp config from env vars`.

### Task 5: `settings.php` from env

**Files:**
- Create: `docker/settings.php`, `scripts/docker/test-settings.sh`
- Modify: `docs/docker-build.md` (section "Drupal settings")
- Modify: `Dockerfile` (copy to `/var/www/html/web/sites/default/settings.php`, read-only to the runtime user)

**Interfaces:**
- Consumes: `lib.sh` semantics reimplemented in PHP as one function `mukurtu_env(string $name, ?string $default = null): ?string` (honours `_FILE`).
- Produces: `$databases['default']['default']` (driver `mysql`, namespace `Drupal\Core\Database\Driver\mysql`, utf8mb4 collation `utf8mb4_general_ci`), `$settings['hash_salt']`, `trusted_host_patterns`, `reverse_proxy*`, `file_private_path = /var/www/files/private`, `file_temp_path` from `MUKURTU_FILE_TEMP_PATH`, `file_public_path`, `config_sync_directory`, `skip_permissions_hardening = TRUE`, `display_errors` off, error log to stderr.

- [ ] **Step 1: Write `test-settings.sh`** that sets env and runs `php -r 'include "…/settings.php"; echo json_encode([...]);'` in the image, asserting: DB values map (including `MUKURTU_DB_PORT`, `MUKURTU_DB_PREFIX`); password `p$a"s'\w@#%` arrives byte-identical; `_FILE` beats plain; hash salt from `MUKURTU_HASH_SALT`, else from `/var/www/files/private/.hash_salt` when it exists, else the include throws a `RuntimeException` naming `MUKURTU_HASH_SALT`; trusted hosts: `example.com,www.example.com` yields patterns that match `Example.COM` and `www.example.com` and do not match `exampleXcom` nor `example.com.evil.org` (test by `preg_match` with Drupal's `#` delimiter handling); `MUKURTU_TRUSTED_HOSTS` unset throws naming the variable; reverse proxy settings applied when `true` with addresses `10.0.0.0/8,172.16.0.1`.
- [ ] **Step 2: Run; expected FAIL.**
- [ ] **Step 3: Implement `docker/settings.php`.** Patterns built with `preg_quote` per host, anchored `^…$`, case-insensitive flag.
- [ ] **Step 4: Run; expected PASS.**
- [ ] **Step 5: Document** "Drupal settings": what `settings.php` reads, trusted host pattern construction, reverse proxy trust, hash salt sourcing and why an explicit salt is recommended for production (image-style tokens), temp path choices (tmpfs uses RAM; size-capped volume), `skip_permissions_hardening` and why it is set with a read-only root.
- [ ] **Step 6: Commit** `Configure Drupal settings from environment variables`.

### Task 6: Entrypoint, supervisor, cron, healthcheck

**Files:**
- Create: `docker/entrypoint.sh`, `docker/supervise.sh`, `docker/cron.sh`, `docker/healthcheck.sh`, `scripts/docker/test-entrypoint.sh`
- Modify: `Dockerfile` (`ENTRYPOINT ["/sbin/tini", "--", "entrypoint.sh"]`, `CMD`, `HEALTHCHECK`, `EXPOSE 8080`), `docs/docker-build.md` (sections "Startup, supervision and health", "Known hazards")

**Interfaces:**
- Consumes: `render-config.sh`, `settings.php`.
- Produces: `entrypoint.sh` sequence: (1) `render-config.sh`, then export `PHPRC` to the rendered CLI ini so Drush gets it; (2) check `/var/www/files/public` and `/var/www/files/private` writable, else `ERROR: <path> is not writable by uid <n>; ...` naming a fix; (3) persist hash salt to `/var/www/files/private/.hash_salt` if `MUKURTU_HASH_SALT` unset (generated once); (4) warn per missing tool (`ffmpeg`, `pdftotext`, `pdftoppm`) and for unset SMTP; (5) wait for the DB up to `MUKURTU_DB_WAIT_TIMEOUT` seconds (default 60; new optional variable, add to the docs), else `ERROR: database <host>:<port> not reachable after <n>s`; (6) if `MUKURTU_AUTO_INSTALL=true` and DB has no `users` table, require `MUKURTU_ADMIN_PASSWORD` and `MUKURTU_ADMIN_EMAIL` then `drush site:install mukurtu`; (7) if `MUKURTU_RUN_UPDATES=true` and installed, `drush updb -y` and `drush cr`; (8) `exec supervise.sh`. `supervise.sh` runs php-fpm, nginx, and (when `MUKURTU_CRON=true`) `cron.sh`; forwards SIGTERM/SIGINT; exits non-zero if any child dies. Use a MariaDB container on a temporary Docker network for tests.

- [ ] **Step 1: Write `test-entrypoint.sh`** with these named cases using `mariadb:10.11` (`--character-set-server=utf8mb4`): `fresh-install` (front page returns 200 and body contains the site name; `/user/login` returns 200; healthcheck status becomes `healthy`); `restart-idempotent` (stop and start the same container: salt file content unchanged, no install log line the second time, `users` count unchanged); `db-late` (start the app 10s before the DB; it waits then succeeds); `db-never` (`MUKURTU_DB_WAIT_TIMEOUT=3` exits non-zero with the timeout message); `no-admin-password` (exits non-zero naming `MUKURTU_ADMIN_PASSWORD`, does not install); `unwritable-private` (bind-mount a root-owned dir; message names the path); `fresh-named-volume` (empty named volumes work without chown); `no-host-header` (request with `Host: evil.test` is rejected, not served); `slim-warns` (slim image logs a warning naming `ffmpeg`, `pdftotext`, `pdftoppm`); `sigterm` (container stops within 10s with exit code 0 or 143); `cron` (with `MUKURTU_CRON_INTERVAL=5`, `drush core:cron` runs: check last-cron state increases); `env-under-fpm` (a request through php-fpm to a temporary test-only script that echoes `getenv('MUKURTU_DB_HOST')` returns the value; the script is removed afterwards; Drush `php:eval 'echo ini_get("memory_limit");'` prints `1G`); `php-in-uploads` (write `x.php` into `sites/default/files` and request it: the body is not executed output); `downloads-dont-starve` (start four slow `/system/files/…` downloads through a throttled client; `/` still answers 200 within 5s); `zombies` (after an `exec`'d process is orphaned, no `<defunct>` entries remain); `ping-health` (the healthcheck uses fpm `ping.path`: the nginx access log shows no Drupal page request from it).
- [ ] **Step 2: Run; expected FAIL.**
- [ ] **Step 3: Implement the four scripts** per the sequence above. Supervisor: choose the smallest option that runs non-root, forwards signals, and fails the container when a child exits (a plain POSIX `sh` script with `wait -n` is acceptable and preferred if it meets this). Drush runs as the same non-root user.
- [ ] **Step 4: Run; expected PASS.**
- [ ] **Step 5: Document** "Startup, supervision and health" (boot sequence, idempotency, failure messages, why `tini`, why the healthcheck avoids Drupal) and "Known hazards" (private downloads hold a worker and the two-pool mitigation with its residual limit; ffmpeg/pdftoppm memory counts against the container, not `memory_limit`; env visibility in `docker inspect`).
- [ ] **Step 6: Commit** `Add entrypoint with auto-install, supervisor, cron and healthcheck`.

### Task 7: Compose examples

**Files:**
- Create: `docker-compose.example.yml`, `docker-compose.multisite.example.yml`, `scripts/docker/test-compose.sh`
- Modify: `docs/docker-build.md` (section "Hosting examples")

**Interfaces:**
- Produces: single-site example (MariaDB `--character-set-server=utf8mb4 --collation-server=utf8mb4_general_ci`, two named volumes, Docker secrets for DB and admin passwords via `_FILE`, `mem_limit` of 1G with a comment explaining the ffmpeg headroom, `read_only: true`, `tmpfs` for `/run` and `/tmp` with a `size=` cap and a comment on the volume alternative, `cap_drop: [ALL]`, `security_opt: [no-new-privileges:true]`, healthcheck-gated `depends_on`; MariaDB started with `--transaction-isolation=READ-COMMITTED --binlog-format=ROW --max-allowed-packet=64M --innodb-buffer-pool-size=<sized>`); multi-site example (Traefik with Let's Encrypt, one shared MariaDB with an init script creating one utf8mb4 database and user per tenant, two Mukurtu services on different domains each with `mem_limit`, `MUKURTU_REVERSE_PROXY=true` and the Traefik network CIDR).

- [ ] **Step 1: Write `test-compose.sh`:** `docker compose -f <file> config -q` succeeds for both (with a placeholder `.env`); the single-site file, run with the test image tag substituted via `MUKURTU_IMAGE`, reaches a 200 on the front page. Assert both files set `mem_limit`, `read_only: true`, `cap_drop`, and `no-new-privileges` on every Mukurtu service, and that MariaDB uses `READ-COMMITTED`.
- [ ] **Step 2: Run; expected FAIL.**
- [ ] **Step 3: Write the two files.** Image reference `${MUKURTU_IMAGE:-ghcr.io/OWNER/mukurtu:latest}` where `OWNER` is documented as a placeholder.
- [ ] **Step 4: Run; expected PASS.**
- [ ] **Step 5: Document** "Hosting examples": container hardening flags with reasons, MariaDB settings and why (Drupal's isolation guidance), multi-tenant layout, memory sizing including video-upload headroom, temp storage options.
- [ ] **Step 6: Commit** `Add single-site and multi-tenant compose examples`.

### Task 8: Publish workflow

**Files:**
- Create: `.github/workflows/docker-publish.yml`, `scripts/docker/test-workflow.sh`
- Modify: `.github/dependabot.yml` (add `docker` and `github-actions` entries if absent), `docs/docker-build.md` (section "Publishing and supply chain")

**Interfaces:**
- Produces: workflow with `on: release: types: [published]` and `workflow_dispatch` (input `tag`); job `test` (matrix `full`/`slim`, amd64, builds with explicit args, runs `scripts/docker/test-*.sh`); job `publish` (needs `test`; matrix `full`/`slim`; `permissions: contents: read, packages: write, id-token: write, attestations: write`; QEMU + buildx; `docker/metadata-action` with `flavor: latest=false` and per-variant `suffix`; `docker/build-push-action` with `platforms: linux/amd64,linux/arm64`, `provenance: true`, `sbom: true`, GHA cache). Tags: exact version always; `major.minor`, `major`, and `latest` only for non-prerelease; slim variants get `-slim`. Checkout uses the release tag (or `inputs.tag`). `test` also runs Trivy (`aquasecurity/trivy-action`, `severity: HIGH,CRITICAL`, `ignore-unfixed: true`, `exit-code: 1`) on the loaded image. A weekly `schedule` trigger runs only when `vars.DOCKER_WEEKLY_REBUILD == 'true'`; it resolves the newest non-pre-release release tag through the GitHub API, rebuilds, re-pushes its tags, and adds `<version>-<YYYYMMDD>`. The resolved `composer.lock` is attached to the attestation.

- [ ] **Step 1: Write `test-workflow.sh`:** `actionlint` passes if installed (else the test prints SKIP and checks YAML parses); the file contains neither a literal repository owner nor `github.event.release.tag_name` interpolated into a `run:` line (pass via `env:`); both matrix rows set `WITH_FFMPEG` and `WITH_POPPLER` explicitly; a Trivy step precedes every push step; the scheduled job is guarded by `vars.DOCKER_WEEKLY_REBUILD`; `.github/dependabot.yml` has `docker` and `github-actions` ecosystems. Then verify the tag logic with `docker/metadata-action` semantics by running the tag-computation locally for `4.0.5`, `4.0.0-rc`, `4.0.0-beta37` and recording the expected tag list in the test as a table: `4.0.5` → `4.0.5 4.0 4 latest` (+`-slim` forms); pre-releases → exact tag only. Check the action's documented behavior and adjust patterns (`{{version}}`, `{{major}}.{{minor}}`, `{{major}}`, `latest`) with `enable=${{ !github.event.release.prerelease }}`-style conditions as needed.
- [ ] **Step 2: Run; expected FAIL.**
- [ ] **Step 3: Write the workflow.**
- [ ] **Step 4: Run; expected PASS.**
- [ ] **Step 5: Document** "Publishing and supply chain": triggers, tags and what each means, pre-release handling, Trivy gate and threshold, `composer audit`, digest pinning and Dependabot, attestations and the lock file, the opt-in weekly rebuild and why tags move, the one-time GHCR visibility step, and the `GITHUB_TOKEN` release-trigger caveat.
- [ ] **Step 6: Commit** `Publish docker images to GHCR on release`.

### Task 9: Documentation and doc/code agreement test

**Files:**
- Create: `docs/docker.md`
- Modify: `scripts/docker/test-docs.sh` (add the env-var agreement check)
- Modify: `README.md` (one pointer under Requirements or Installing), `docs/docker-build.md` (add a short "Reading this document" index linking each section and `docs/docker.md`), `docs/releasing.md` (release step: images publish on release; GHCR package visibility is set to public once; manual `workflow_dispatch` fallback)

**Interfaces:**
- Produces: `docs/docker.md` sections: Quick start; Tags and variants; Environment variable reference (tables as in the spec, plus `MUKURTU_RUN_DIR` if user-visible); Secrets (`_FILE`); Volumes and permissions; Port 8080 and reverse proxies; Mail; Solr (if implemented) ; Upgrades; Hosting many tenants (with measured figures from Task 10); Building locally with the required build args.

- [ ] **Step 1: Extend `test-docs.sh`:** collects every `MUKURTU_[A-Z0-9_]+` token in `docker/`, `docker-compose*.yml`, and `.github/workflows/docker-publish.yml`, and in `docs/docker.md`; fails listing names present in code but undocumented, and documented names that no code reads (excluding the `_FILE` suffix forms, which must each be mentioned once).
- [ ] **Step 2: Run; expected FAIL.**
- [ ] **Step 3: Write the docs** and edit `README.md` and `docs/releasing.md`.
- [ ] **Step 4: Run; expected PASS.**
- [ ] **Step 5: Commit** `Document the docker image and its environment variables`.

### Task 10: Measure, tune, and finish

**Files:**
- Modify: `docs/docker.md` (replace estimated figures with measured), `docs/docker-build.md` (section "Measured footprint"), `docker/templates/*.tmpl` or defaults only if measurement shows a default is wrong
- Create: `scripts/docker/measure.sh`

**Interfaces:**
- Produces: `measure.sh` prints image size (full and slim), idle RSS from `docker stats --no-stream` after a warm-up of 3 front-page requests, RSS during 4 concurrent requests, and peak container memory during a 100 MB video upload (full image) so the `mem_limit` guidance is measured.

- [ ] **Step 1: Write and run `measure.sh`** against the Task 6 setup.
- [ ] **Step 2: Compare** with spec estimates (idle 150–250 MB warm; 60–120 MB per request). If `OPCACHE_MEMORY=128` shows a full cache via `opcache_get_status()`, raise the default and update docs and `test-render.sh`.
- [ ] **Step 3: Update `docs/docker.md`** with the measured figures, labelled with the date and platform, replacing all "estimated" wording.
- [ ] **Step 4: Run every `scripts/docker/test-*.sh`;** expected all PASS. Build for `linux/arm64` with buildx/QEMU if available; otherwise say so in the PR description (CI builds it).
- [ ] **Step 5: Document** "Measured footprint" in `docs/docker-build.md`: method, hardware, date, image sizes (full/slim), idle and loaded memory, video-upload peak, and the resulting `mem_limit` recommendation.
- [ ] **Step 6: Run `test-docs.sh`** with every section heading from Tasks 1–10 required; expected PASS.
- [ ] **Step 7: Commit** `Replace estimated image resource figures with measurements`.

---

## Self-review notes

- Spec coverage: guard/pinning (T1), source of build (T2), tools/extensions/non-root (T3), env tables and `_FILE` (T4, T5), install/update/cron/health/volumes (T6), compose + multi-tenant (T7), tags/publish/attestations/smoke-before-push (T8; T6 and T7 tests run in its `test` job), docs and releasing note (T9), measurement (T10). Solr variables are deliberately not assigned a task: the spec makes them conditional on checking the Search API server config. If that check is done, add them to Task 5 (`settings.php` config override) and Task 9. Otherwise document the manual approach in `docs/docker.md` and drop them from the spec table.
- New names not in the original spec, flagged for the owner: `MUKURTU_RUN_DIR`, port 8080, and the repository variable `DOCKER_WEEKLY_REBUILD`. `MUKURTU_DB_WAIT_TIMEOUT`, `MUKURTU_PHP_CLI_MEMORY_LIMIT`, `MUKURTU_PHP_TIMEZONE`, `MUKURTU_APCU_MEMORY`, `MUKURTU_FILE_TEMP_PATH`, and `MUKURTU_FPM_FILES_MAX_CHILDREN` are now in the spec.
- Review feedback folded in: `clear_env`, CLI php.ini, nginx Drupal rules, PHP hardening, container hardening, supply chain, APCu, `tini`, ping healthcheck, second fpm pool, MariaDB flags, ffmpeg headroom, temp path, and the public `docs/docker-build.md` written task by task.
