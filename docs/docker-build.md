# How the Mukurtu CMS container image is built

This document is for people who need to judge how the image is built: hosting providers,
security reviewers, and communities deciding whether to run it. It explains what the image
contains, where the code comes from, how it is hardened and tuned, and why. To *run* the
image, see [docker.md](docker.md).

The image is built from the `Dockerfile` in the repository root and published to the GitHub
Container Registry when a release is published. The sections follow the order of the build.

## Pinned inputs

The same inputs should produce the same image, so everything that changes what is inside it
is pinned or explicit.

- **One build argument, `MUKURTU_TEMPLATE_VERSION`**, the `mukurtu/mukurtu-template` version
  the image is built on (it defines the Drupal core version, the Composer plugins and the
  install paths). Its default in the `Dockerfile` is the pinned version; change that line to
  move to a newer template. It is never a floating range such as `^4.0`, which would make one
  release tag build different images over time. It can be overridden with `--build-arg`.
- **No options for what the image contains.** `ffmpeg` and `poppler-utils` are always
  installed: Mukurtu needs them for video and PDF thumbnails and PDF text, and silently skips
  those features when they are missing, so an option to leave them out could only produce an
  image that quietly lacks thumbnails. The build runs each tool and fails if one does not work.
- **Base images** (PHP, Composer, Alpine) are literal `FROM` lines with an exact version *and*
  a content digest, such as `alpine:3.24@sha256:…`. The tag says what was intended; the digest
  guarantees which bytes were used. Dependabot's `docker` ecosystem reads these lines and
  proposes updates as pull requests. To refresh one by hand, pull the new tag and copy
  `docker image inspect <image> --format '{{index .RepoDigests 0}}'` into the `FROM` line.

Build arguments decide what is *inside* the image. The running container is configured by
`MUKURTU_*` environment variables (see [docker.md](docker.md)).

## Where the code comes from

A Mukurtu site is a Composer project. This repository is only the *profile*
(`mukurtu/mukurtu`); the project around it (Drupal core, scaffold files, the plugin allow-list,
install paths) is [`mukurtu/mukurtu-template`](https://github.com/MukurtuCMS/mukurtu-template).
The `build` stage follows the documented Composer install:

1. `composer create-project mukurtu/mukurtu-template:$MUKURTU_TEMPLATE_VERSION`.
2. Add this checkout as a Composer `path` repository (`symlink: false`, so the files are
   copied into `web/profiles/mukurtu`).
3. `composer update --no-dev --optimize-autoloader`.

**Why the checkout and not Packagist.** The image must contain exactly the code of the release
tag. A release workflow starts the moment the release is published, but Packagist learns of
the new tag through a webhook that can take minutes, so a build asking Packagist for the profile
could get the previous version or fail; and [releasing.md](releasing.md) requires the profile
to be tagged *before* the template. A path repository is canonical to Composer, so Packagist is
never consulted for `mukurtu/mukurtu`. The version is declared as the placeholder `4.9999.9999`
("the 4.x checkout being built"), which satisfies the template's `^4.0`; `.tugboat/config.yml`
and the CI workflow use the same convention.

- **Patches** in the profile's `composer.json` (Drupal core and several contributed modules)
  are applied as in any Composer install; what was applied is kept in `patches.lock.json`. A
  patch that does not apply fails the build.
- **Dependencies are resolved at build time.** The template ships no `composer.lock`, so two
  builds of the same tag can contain different Drupal and contributed-module patch releases.
  The resolved lock is kept in the image (`/var/www/html/composer.lock`) and as a workflow
  artifact, so what shipped is always recorded.
- **Advisories fail the build.** `composer audit --no-dev` runs in the stage. Advisories the
  profile already triaged (`config.audit.ignore` in its `composer.json`) are copied across,
  because Composer only honours that setting in the *root* project. Abandoned packages are
  reported, not fatal.
- **The autoloader is optimised but not authoritative.** Drupal registers the namespaces of
  core and every module with the class loader at run time, and an authoritative class map
  (`--classmap-authoritative`) refuses those lookups: Drupal then fails with `Class
  "Drupal\Core\Serialization\Yaml" not found` before it can install. The real-install test
  catches this.
- **Only unused things are removed:** `.git` directories and the `tests`/`Tests` directories
  of the profile, dependencies, contributed code and Drupal core. Licences and everything else
  stay.
- **The stage runs on the same PHP image and extensions as the runtime** (`php-base`), so
  Composer checks the real platform requirements instead of ignoring them.

## Runtime image, tools and extensions

The `runtime` stage starts from the official `php:8.4-fpm-alpine` image and adds nginx.

**Why nginx, php-fpm and Alpine.** The motivating use is one host running many separate
Mukurtu sites, so the goal is the smallest idle memory. nginx and php-fpm are separate, small
processes, and Alpine's base is a few megabytes. The Mukurtu team works with nginx; Apache is
larger and less tested. The trade-off is PHP on Alpine's musl C library, whose difference is
small for a Drupal workload dominated by the database and opcache.

**PHP extensions:** everything Drupal core requires, plus `gd` (JPEG, WebP, FreeType: image
styles and thumbnails), `intl`, `zip`, `pdo_mysql` (MariaDB or MySQL; Mukurtu does not support
PostgreSQL), `bcmath`, opcache and `apcu` (Drupal's fast cache backend). The build checks that
every extension is loaded and that `gd` has JPEG, WebP and FreeType support.

**Media tools.** Mukurtu runs these as external programs (see
`modules/mukurtu_core/src/Hook/MediaToolRequirements.php`, which also reports a missing tool on
the status page):

| Program | Package | Used to |
| --- | --- | --- |
| `pdftotext` | `poppler-utils` | Extract PDF text so documents can be found by search. |
| `pdftoppm` | `poppler-utils` | Render the first page of a PDF as its thumbnail. |
| `ffmpeg` | `ffmpeg` | Extract the first frame of a video as its thumbnail. |

The build runs each with the same probe Mukurtu uses.

**`exec()` stays enabled.** Hardening guides often disable PHP's process functions, but
Mukurtu launches the tools above with `exec()`; the build fails if `exec` is in
`disable_functions`. The residual risk is that a code-injection bug in Mukurtu, Drupal or a
module could start a process. It is mitigated by keeping packages current (see "Publishing and
supply chain"), running as a non-root user with no extra privileges, and keeping the code
read-only to that user.

**Users, files and volumes.**

- The process runs as user `mukurtu`, uid `1000`, a fixed number so ownership of mounted
  volumes is predictable between hosts.
- The code in `/var/www/html` belongs to root and is not writable by that user, so a
  compromised PHP process cannot modify the site. Only two directories are writable, both
  volumes: `/var/www/files/public` (`web/sites/default/files` is a symlink to it) and
  `/var/www/files/private`, which Mukurtu's access control requires.
- There is no Composer in the image; it is a build tool, and leaving it out removes a tool
  that could be misused. `tini` is the first process and reaps orphans (`ffmpeg` and `pdftoppm`
  are launched from PHP requests). Drush is on the `PATH`; `mariadb-client` makes
  `drush sql:dump` work for backups; `msmtp` provides the `sendmail` Drupal uses.

## Configuration rendering

Nothing is edited inside a running container. At every start `render-config.sh` reads the
`MUKURTU_*` variables (listed in [docker.md](docker.md)), validates them, and writes the
configuration for PHP, php-fpm, nginx and msmtp from the templates in `docker/templates/`.

- **Output** goes to `/run/mukurtu` (`MUKURTU_RUN_DIR`), which can be a tmpfs, so the container
  can run with a read-only root filesystem.
- **Validation.** The first invalid value stops the container with a message naming the
  variable, e.g. `ERROR: MUKURTU_UPLOAD_MAX must be a size such as 128M or 1G (got '100MB')`.
  Line breaks in a value are refused, so a value cannot inject a second line into a file.
- **One variable, several settings.** `MUKURTU_UPLOAD_MAX` sets PHP's `upload_max_filesize` and
  `post_max_size` and nginx's `client_max_body_size` together, avoiding the classic upload that
  fails with no explanation.
- **Secrets can be files.** `MUKURTU_DB_PASSWORD`, `MUKURTU_ADMIN_PASSWORD`,
  `MUKURTU_HASH_SALT` and `MUKURTU_SMTP_PASSWORD` accept a `_FILE` form (the Docker and
  Kubernetes secrets convention; the file wins). Environment variables are visible to anyone
  who can run `docker inspect`.
- **Values never pass through a shell.** An `awk` routine reads each value from the environment,
  so `$`, quotes, backslashes and `&` reach the configuration unchanged. The SMTP password is
  not written into the msmtp configuration at all: it is stored in its own `0600` file that
  msmtp reads (`passwordeval`), which avoids that format's quoting rules.
- **The command line gets its own ini.** The PHP image has no `php.ini`, so Drush would run
  with PHP's 128 MB default and run out of memory while installing. `cli/php.ini` has
  `MUKURTU_PHP_CLI_MEMORY_LIMIT` and no time limit, and the image's `PHPRC` points at it.

## Security hardening

Each rule is covered by a test in `scripts/docker/`.

**nginx** follows
[Drupal's recommended configuration](https://www.drupal.org/docs/getting-started/system-requirements/nginx),
tightened in one respect: Drupal's runs any `.php` file that exists; this one runs only
`index.php`.

- **PHP is never executed from `sites/*/files`**, where uploads land: an uploaded `.php` file
  would otherwise own the site. Requests for PHP-like extensions there get a 404.
- **Only `index.php` reaches php-fpm.** `install.php`, `update.php`, `authorize.php` and any
  stray script return 404; updates run through `drush updb` at start-up.
- **Source and configuration files are not served:** dotfiles (except `/.well-known`), `*.yml`,
  `*.twig`, `*.module`, `*.install`, `*.inc`, `*.theme`, `*.sql`, backup and editor files,
  `composer.json` and `composer.lock`. The `vendor` directory is outside the web root.
- **Headers.** `server_tokens off`; `X-Content-Type-Options: nosniff` and `Referrer-Policy:
  strict-origin-when-cross-origin` on every response. HSTS and Content-Security-Policy are left
  to the TLS-terminating reverse proxy, which alone knows the real scheme and origins.
- **Port 8080**, because a non-root process cannot bind port 80.

**PHP:** `expose_php`, `display_errors` and `html_errors` off (errors go to the container log);
`zend.exception_ignore_args` on (log traces omit argument values such as passwords);
`cgi.fix_pathinfo = 0`; `zend.assertions = -1`; `allow_url_include` off; session cookies
`HttpOnly`, `SameSite=Lax`, strict mode. `opcache.save_comments` stays **on**: Drupal reads
attributes and doc comments at run time, and turning it off breaks Drupal.

**php-fpm and the environment.** php-fpm normally clears the environment before running PHP.
`settings.php` reads its configuration from environment variables, so both pools set
`clear_env = no`. Code running in the pool can already read `settings.php` and so the database
password; using `_FILE` secrets keeps the values out of `docker inspect`, which is the exposure
that matters.

**Container:** non-root, code read-only to the runtime user; the Compose examples add
`read_only: true`, `cap_drop: [ALL]` and `no-new-privileges`.

## Performance tuning

Defaults let many idle sites share one small server without holding a busy site back. Every
number is an environment variable; the reference is [docker.md](docker.md).

- **`pm = ondemand`.** An idle site has no PHP workers; they start on the first request and exit
  after `MUKURTU_FPM_PROCESS_IDLE_TIMEOUT` (10 s). A cold request pays a few milliseconds, in
  exchange for an idle footprint of tens of megabytes instead of hundreds.
  `MUKURTU_FPM_MAX_CHILDREN` (4) caps concurrent requests and so the memory a busy site can use;
  `MUKURTU_FPM_MAX_REQUESTS` (500) recycles workers to bound memory growth.
- **Two pools.** Drupal serves *private* files through PHP so it can check access, and Mukurtu
  relies on that for sensitive content. Each download holds a worker as long as the client takes
  to receive the file, so `/system/files/` has its own pool (`MUKURTU_FPM_FILES_MAX_CHILDREN`, 2)
  and downloads cannot starve page requests. Public files never touch PHP: nginx serves them.
- **opcache** with `validate_timestamps = 0` (the image is immutable, so PHP never re-checks
  source files), 128 MB (`MUKURTU_OPCACHE_MEMORY`) sized for Mukurtu, and the JIT off (it adds
  memory and little for request/response work). **APCu** (32 MB) holds Drupal's bootstrap and
  discovery caches. **`realpath_cache`** is raised to 4 MB for ten minutes, because Drupal
  resolves many paths per request.
- **nginx:** `sendfile`, gzip for text, an open-file cache, one year of caching for the
  content-hashed aggregated CSS and JS and 30 days for other static files and image-style
  derivatives (set with `expires`), and one worker process by default.
- **Timeouts.** php-fpm's `request_terminate_timeout` equals `MUKURTU_PHP_MAX_EXECUTION_TIME`
  (120 s), because PHP's own limit counts CPU time only and a request stuck waiting could hold
  a worker for ever. The private-file pool has no terminate timeout: a download can legitimately
  be slow.

## Drupal settings

`docker/settings.php` is the site's `settings.php`: one file, copied into `web/sites/default/`
at build time, read-only to the runtime user, with no site-specific values. It reads them from
the environment when Drupal starts, so the same image serves any number of sites. The settings
themselves are in [docker.md](docker.md); the reasons:

- **Database** over `utf8mb4`, as Drupal and Mukurtu recommend, so emoji and scripts outside the
  Basic Multilingual Plane work. The database must be created `utf8mb4` too.
- **Trusted hosts** (`MUKURTU_TRUSTED_HOSTS`, required): Drupal refuses any other `Host` header,
  blocking attacks such as poisoned password-reset links. Entries match exactly with dots
  literal (`example.com` does not trust `exampleXcom` or `example.com.evil.org`), ignoring case;
  `*.example.com` trusts any subdomain.
- **Reverse proxy:** `X-Forwarded-*` is trusted only from `MUKURTU_REVERSE_PROXY_ADDRESSES`,
  which is mandatory when the proxy is enabled; trusting those headers from anyone lets a
  visitor pretend to be any client.
- **Hash salt.** Signs one-time login links and image-style tokens. Set it explicitly for
  production; otherwise the entrypoint generates one into the private volume, and if that volume
  is lost while the database is kept, the salt changes and previously issued image-style
  tokens stop validating.
- **Files.** `file_private_path` is `/var/www/files/private`. `MUKURTU_FILE_TEMP_PATH` holds
  uploads before Drupal moves them; an upload is held there **twice** (nginx buffers the body,
  then PHP receives a copy, and large responses spill there too), so size it for twice the
  largest upload. Back it with a tmpfs (fast, counts against the container's memory) or a
  size-capped volume. The small rendered-configuration directory is not used for uploads.
- **Also:** `skip_permissions_hardening` on (the code is already read-only), `class_loader_auto_detect`
  off (the image never changes), and the on-screen error level forced to `hide`: errors still
  reach the container log and Drupal's database log, never visitors.

## Startup, supervision and health

`tini` starts `entrypoint.sh`, which does the following in order and stops with a message naming
the setting or path at fault if any step fails:

1. **Resolve secrets and render configuration** (see above).
2. **Check required settings:** the four database variables and `MUKURTU_TRUSTED_HOSTS`.
3. **Check the volumes:** `/var/www/files/public`, `/var/www/files/private` and
   `MUKURTU_FILE_TEMP_PATH` must be writable by uid 1000. A fresh named volume works without
   preparation, because Docker gives it the image directory's ownership.
4. **Make a hash salt** if `MUKURTU_HASH_SALT` is unset: generated once into the private volume
   (mode `0600`) and reused.
5. **Warn** if `MUKURTU_SMTP_HOST` is unset, because the site then cannot send mail.
6. **Wait for the database** up to `MUKURTU_DB_WAIT_TIMEOUT` seconds, which removes the usual
   race in which the site starts before the database accepts connections; on timeout the error
   shows the host, port and the database's last message.
7. **Install or update**, by the database's state:
   - *empty* and `MUKURTU_AUTO_INSTALL=true`: `drush site:install mukurtu` with the admin account
     from the environment. `MUKURTU_ADMIN_PASSWORD` and `MUKURTU_ADMIN_EMAIL` are required, with
     no default (a well-known default password would be an open door). The password is an
     argument to Drush, so it is visible to processes inside the container during the install.
   - *installed*: with `MUKURTU_RUN_UPDATES=true`, `drush updatedb` and `drush cache:rebuild`; a
     new image is new code, and the rebuild stops Drupal using a service container built for
     the old code.
   - *partial* (tables, but no complete Drupal): refuse to continue, because installing over
     unknown data could destroy it.
8. **Hand over to the supervisor.**

**Supervision.** `supervise.sh` runs php-fpm, nginx and the cron loop. If any stops unexpectedly
it stops the others and exits non-zero, so the container restarts (or stops, visibly) instead of
serving errors; on `SIGTERM` it shuts everything down gracefully and exits `0`. nginx is started
with `-e stderr`, because before reading its configuration it opens a compiled-in default error
log that the unprivileged user cannot write and would print an alert on every start (a test
fails on any nginx alert). A plain shell script does this instead of s6 or supervisord: nothing
to install, runs unprivileged, nothing to configure.

**Cron.** `cron.sh` runs `drush core:cron` every `MUKURTU_CRON_INTERVAL` seconds (3600);
`MUKURTU_CRON=false` turns it off. Mukurtu's own guidance is that running cron from outside the
site is the proper setup and Drupal's Automated Cron (hourly, from web requests) is the fallback
(Mukurtu 4's setup checklist has a "Set up automated cron" task that counts as done when cron
ran in the last 24 hours). This loop is the outside cron: it also runs on a site nobody is
visiting, and from the command line, without the web request's 120 s limit. A failed run is
logged and retried at the next interval.

**Health.** The `HEALTHCHECK` requests `/healthz`, which nginx answers through php-fpm's built-in
ping, so it confirms nginx and php-fpm are alive without bootstrapping Drupal or touching the
database and is cheap enough to run every 30 seconds for every tenant on a host. A database
outage shows in the site's status report and logs, not as a restart loop. Its start period is ten
minutes, because installing a new site takes a few minutes.

**One-off commands.** `docker run ... <image> drush status` renders the same configuration and
runs the command without waiting for the database, installing or updating. `docker exec
<container> drush ...` works too: `PHPRC` is set in the image, and `drush` is a small wrapper
that reads the site's address from a file written at every start (`MUKURTU_BASE_URL`, default
`https://` plus the first trusted host), so links in cron mail and `drush user:login` are right
even though `docker exec` does not run the entrypoint.

## Known hazards

Properties of Mukurtu and Drupal that the image can reduce but not remove.

1. **Private files are served by PHP**, so each download or video range request holds a worker
   while the client receives it. The separate pool keeps pages responsive (a test checks that a
   page request is answered while that pool is full), but only `MUKURTU_FPM_FILES_MAX_CHILDREN`
   private downloads can run at once per site; the rest wait. A site serving a lot of private
   media should raise it together with the container's memory limit. Removing the limit would
   need `X-Accel-Redirect`, a change to the Mukurtu profile.
2. **ffmpeg and poppler memory is outside PHP's `memory_limit`.** Mukurtu runs them inside the
   upload request; their memory counts against the container, so a limit sized for PHP alone can
   be exceeded and the container killed during a video upload. Leave headroom (the examples allow
   1 GB per site; see "Measured footprint").
3. **Environment variables can be read by anyone who can inspect the container.** Use the `_FILE`
   form for secrets.
4. **Dependencies are resolved at build time:** the lock file in the image is the authoritative
   list of what it contains.

## Hosting examples

[`examples/docker/`](../examples/docker) has three self-contained examples, each with a Compose
file and, where needed, a `.env.example` and a secrets generator. The tests check that each is
valid, that `.env.example` covers every required variable, that the generators make random,
private, distinct secrets and never overwrite them, that every Mukurtu service has the hardening
below, and that each example **starts a working site** (the multi-tenant one starts two behind
Caddy, each answering only on its own name).

- **`minimal/`**: the image and MariaDB with throwaway passwords in the file and nothing to
  prepare. For trying Mukurtu, not keeping it.
- **`single-site/`**: secret files for every password, a read-only container, memory limits.
  `generate-secrets.sh` writes random values; sample secrets are deliberately **not** committed,
  because anyone copying them would run a site with a publicly known password, and `.gitignore`
  keeps the generated ones out of git. `MUKURTU_IMAGE` is required with no default, so a version
  is always chosen deliberately.
- **`multi-tenant/`**: one container per site, all from the same image, behind a Caddy reverse
  proxy that routes by host name and obtains certificates. Each site has its own environment,
  database, user and file volumes, so sites are upgraded, restarted and limited independently and
  a problem in one cannot reach another's data. That matters for Mukurtu, whose sites often hold
  culturally sensitive material. (One Drupal installation serving many sites, "multisite", saves
  some memory but shares one codebase, one set of upgrades and one PHP pool; the image does not
  support it.) The proxy is Caddy and not a label-driven proxy such as Traefik because Caddy
  reads a plain file: Traefik discovers containers through the Docker socket, and anything with
  that access controls the host. Adding a site costs three Caddyfile lines.

| Setting | Reason |
| --- | --- |
| `read_only: true` | The code cannot be modified at run time, even by a compromised process; the only writable places are the two file volumes and two tmpfs mounts. |
| `cap_drop: [ALL]`, `no-new-privileges` | The process needs no capabilities, and cannot gain privilege through setuid binaries. |
| `mem_limit: 1g` per site | A ceiling so one site cannot take the host's memory, with headroom for `ffmpeg`/`pdftoppm` (see "Measured footprint"). |
| `tmpfs: /tmp` with a `size` | Where uploads land first (`MUKURTU_FILE_TEMP_PATH`); the size cap bounds the worst case. For large video uploads use a size-capped volume instead. |
| `tmpfs: /run` | Where the rendered configuration goes, so nothing else needs to be writable. |
| `*_FILE` secrets | Passwords are mounted as files and never appear in `docker inspect`. |
| Sites publish no port; unlisted domains are not served | Only Caddy is reachable; it answers and obtains certificates only for names in its Caddyfile. |
| `MUKURTU_REVERSE_PROXY_ADDRESSES` | Only the proxy network may set `X-Forwarded-*`; the multi-tenant file fixes the proxy network's address range so this can be exact. |
| Database on an `internal` network | Reachable from the sites and nothing else. |

**MariaDB** is started with the settings Drupal documents: `utf8mb4` with
`utf8mb4_general_ci` (emoji and scripts outside the Basic Multilingual Plane); `READ-COMMITTED`
isolation with `binlog_format=ROW` (avoids lock contention and deadlocks under concurrent
writes; row logging is needed to use it safely); `max_allowed_packet=64M` (large serialized
values); and `innodb_buffer_pool_size` at roughly half the database container's memory limit.

## Publishing and supply chain

`.github/workflows/docker-publish.yml` publishes to `ghcr.io/<owner>/mukurtu`, where `<owner>` is
the repository's owner (lower-cased); no owner is written into any file, so it runs unchanged in a
fork and upstream.

**When it runs.** On a published release, or by hand (*Run workflow*) with a tag, for a release
created by another workflow with `GITHUB_TOKEN`, which does not fire `release: published`. The
tag is checked out, so the image is built from that tag's `Dockerfile` and code; a tag that
predates the `Dockerfile` cannot be built. The workflow refuses a tag that is not a release tag
(`v4.0.5`, `4.x-dev`, `4.0.5-r0`) and a release that GitHub marks as a pre-release but whose tag
has no pre-release suffix (tags are derived from the version alone, so it would take `latest`).

### Tags

| Tag | Kind | Also publishes |
| --- | --- | --- |
| `4.0.5` | A Mukurtu release | `4.0`, `4`, `latest` (when newest in each scope) |
| `4.0.5-r1` | An **image revision**: a new image of the same Mukurtu release, shipping a platform fix | `4.0`, `4`, `latest` (when newest in each scope) |
| `4.0.0-rc`, `4.0.0-beta37` | A pre-release | nothing |

A revision exists because the image has dependencies Mukurtu does not (base image, PHP, nginx,
`ffmpeg`, `poppler-utils`), and a fix to one is a new image of the same Mukurtu code. `-r<number>`
counts from 1, cannot be confused with `-rc1`, and Composer rejects it as a version, so Packagist
never offers a revision as a release. The procedure is in
[releasing.md](releasing.md#releasing-an-image-revision).

**A published tag never changes, and the moving tags only advance.** Nothing rebuilds and
re-pushes `4.0.5`, so a tag, a rollback and an audit trail mean what they say. `4.0`, `4` and
`latest` are published for a tag only if it is the newest stable or revision tag in each scope
(same minor, same major, everything), judged against the repository's existing tags; so
`4.0.5-r1` after `4.0.5` moves `latest`, but a backport `4.0.4-r1` after `4.0.5` does not. Pin an
exact tag in production; `4.0` follows patch releases and revisions, `4` follows minor releases,
and `latest` follows everything. The rules are in `scripts/docker/compute-tags.sh` and are tested.

### What must pass before anything is pushed

The `publish` job needs the `test` job, which:

1. Runs every `scripts/docker/test-*.sh`: the image's contents, the web stack, configuration
   rendering, `settings.php`, a real install against MariaDB with restart, health and shutdown
   checks, the Compose examples, and static checks of the workflow and documentation.
2. Scans the image with Trivy for **fixable HIGH or CRITICAL** vulnerabilities (operating system
   packages and Composer dependencies, including Drupal core). A finding fails the run; findings
   with no fix yet are ignored. To accept a specific one, add its ID, a reason and a review date to
   a `.trivyignore` file in the repository root, which Trivy reads automatically.

The Composer build stage's `composer audit --no-dev` additionally fails the build on a Drupal
advisory before the image is assembled.

### Supply chain and how fixes reach users

- **Pinned bases and actions.** Base images are pinned by version and digest; GitHub Actions by
  major version, following the repository's other workflows. Dependabot proposes updates to both.
- **Provenance and SBOM.** Each push carries a BuildKit provenance attestation (`mode=max`), an
  SBOM, and a build-provenance attestation from `actions/attest-build-provenance`. Verify with
  `gh attestation verify oci://ghcr.io/<owner>/mukurtu:<tag> --owner <owner>`.
- **Least privilege.** Default permission is read-only; only `publish` can write packages and
  create attestations. Event data (tag names, inputs) reaches scripts through environment
  variables, never interpolated into script text, which would allow script injection.
- **Fixes arrive as releases.** Because published tags never change, a fix to a base image, nginx
  or Drupal reaches users only when released: a new Mukurtu release if the fix is in Mukurtu or
  Drupal, or an **image revision** if it is only in the platform. Dependabot, the Trivy gate and
  `composer audit` keep each release current and clean. Operators can follow `4.0` or `4`, or let
  their own tooling propose new versions; those needing bit-for-bit reproducibility should pin
  the image digest.
- **One-time setup.** GHCR creates the package private on first push; set its visibility to
  public in the package's settings so others can pull it.

## Measured footprint

Measured by building the image, installing a real site, and sampling the container with `docker
stats` (memory in use, excluding reclaimable cache). **Conditions:** 2026-10-18, `linux/amd64`,
Docker 29.9.0 on a 16-CPU, 28 GB Linux host, Mukurtu 4.0.5 with `mukurtu-template` 4.0.2, a
freshly installed empty site, default settings (`ondemand`, 4 workers, 128 MB opcache, 32 MB
APCu).

| Measurement | Result |
| --- | --- |
| Image size (uncompressed) | 456 MB |
| Site just started, before any request | 123 MB |
| Idle, after warm-up (no PHP workers running) | **89 MB** |
| Four concurrent request streams, 4 PHP workers | **145 MB** peak |
| Back to idle after the load | 92 MB |
| `ffmpeg` extracting a thumbnail from a 100 MB 1080p H.264 video | 159 MB peak (the process alone) |

Idle is mostly shared memory for compiled code (opcache) and APCu, which stays allocated after
the workers exit; nginx and the scripts use about 12 MB. That is why an idle site costs far less
than a PHP application with permanent workers, and why sites cannot share it: each container has
its own. Four workers added about 55 MB, roughly 14 MB each, because they share compiled code,
but this was an empty site's anonymous login page, a light request. Pages with real content
(collections, search, Layout Builder, an administrator editing) use more per worker, up to
`MUKURTU_PHP_MEMORY_LIMIT`, so treat 145 MB as a floor and measure with your own content.

**Budget.** The 1 GB limit in the examples is a ceiling, not a reservation. Its worst case is the
idle footprint (about 90 MB), plus one worker at its limit (512 MB), plus `ffmpeg` on an upload
(about 160 MB here, more for 4K), plus the upload itself if `MUKURTU_FILE_TEMP_PATH` is a tmpfs
(held twice: 200 MB for 100 MB): roughly 960 MB. A site that never has a video uploaded needs far
less. **Planning a host:** most of the time an idle site costs under 100 MB, so the limit is how
many are busy at the same moment. Add the sites' idle figures, the database and the operating
system, leave room for a few sites to burst to several hundred megabytes, and use per-container
limits (and swap or zram) so one burst does not take the others' memory.
