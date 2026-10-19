# How the Mukurtu CMS container image is built

This document is for people who need to judge how the Mukurtu CMS container
image is built: hosting providers, security reviewers, and communities deciding
whether to run it. It explains what the image contains, where the code comes
from, how it is hardened and tuned, and why each choice was made.

To *run* the image, see [docker.md](docker.md). This document is about how it is
made.

The image is built from the `Dockerfile` in the repository root and published to
the GitHub Container Registry when a release is published. The sections below
follow the order of the build.

## Pinned inputs and the build guard

A container image is only trustworthy if the same inputs produce the same
image. Anything that changes what ends up inside the image is therefore an
explicit, validated input.

**Build arguments are not container settings.** Three options exist when the image
is *built*. They decide what is inside the image, and cannot be changed by whoever
runs it. (The running container is configured by `MUKURTU_*` environment
variables; see [docker.md](docker.md).) This is why the full and slim images are
different tags and not a setting.

| Build arg | Default | Allowed values | What it decides |
| --- | --- | --- | --- |
| `WITH_FFMPEG` | `true` | `true` or `false` | Whether `ffmpeg` is installed. Mukurtu extracts video thumbnails with it. |
| `WITH_POPPLER` | `true` | `true` or `false` | Whether `poppler-utils` is installed. Mukurtu extracts PDF text and thumbnails with `pdftotext` and `pdftoppm`. |
| `MUKURTU_TEMPLATE_VERSION` | none: required | a `mukurtu/mukurtu-template` version, for example `4.0.2` | The project template the image is built on. It defines the Drupal core version, the Composer plugins, and the install paths. Floating on `^4.0` would make one release tag build different images over time. |

The tools default to **on** so that a plain build gives a complete Mukurtu. What
makes it safe to have a default is what the build does next: it runs each tool and
fails if the image does not match what was asked for, in either direction (see
"Runtime image, tools and extensions"), so an image can never silently lack a tool
it claims to have. The template version has no default on purpose: it is the one
input that must be a deliberate, recorded choice.

The first stage of the `Dockerfile`, `guard`, checks the values and fails with a
message that names the argument:

```
ERROR: build arg WITH_POPPLER must be 'true' or 'false' (got 'yes')
ERROR: build arg MUKURTU_TEMPLATE_VERSION must be set (for example 4.0.2)
```

Every later stage copies a marker file from `guard`, so BuildKit cannot skip it.
The publish workflow does not rely on the defaults: each variant in its build
matrix states both explicitly, so what a published tag contains is written down in
the workflow. The full image uses `WITH_FFMPEG=true WITH_POPPLER=true`, and the
`-slim` image uses both `false`.

**Base images.** The PHP, Composer, and Alpine base images are written as
literal `FROM` lines with an exact version *and* a content digest, for example
`alpine:3.24@sha256:…`. The tag says what was intended; the digest guarantees
which bytes were used, even if the tag is later re-pushed. Dependabot's
`docker` ecosystem reads these lines and proposes updates as pull requests.
To refresh one by hand:

```
docker pull alpine:3.24
docker image inspect alpine:3.24 --format '{{index .RepoDigests 0}}'
```

and replace the digest in the `FROM` line.

## Where the code comes from

A Mukurtu site is a Composer project. This repository is only the *profile*
(`mukurtu/mukurtu`); the project around it, which pulls in Drupal core, the
scaffold files, the Composer plugin allow-list, and the install paths, is
[`mukurtu/mukurtu-template`](https://github.com/MukurtuCMS/mukurtu-template).
The image follows the same structure the documented Composer install uses.

The `build` stage:

1. Runs `composer create-project mukurtu/mukurtu-template:$MUKURTU_TEMPLATE_VERSION`.
2. Adds this checkout as a Composer `path` repository, with `symlink: false` so
   the files are copied into `web/profiles/mukurtu`.
3. Runs `composer update --no-dev --optimize-autoloader`.

**Why the checkout and not Packagist.** The image must contain exactly the code
of the release tag.

- A release workflow starts the moment the release is published, but Packagist
  learns about the new tag through a webhook that can take minutes. A build that
  asked Packagist for the profile could get the previous version or fail.
- [releasing.md](releasing.md) requires the profile to be tagged *before* the
  template. A build that depended on the template having been released for this
  tag would break that order.
- A path repository is canonical for Composer, so Packagist is never consulted
  for `mukurtu/mukurtu`. The version is declared as the placeholder
  `4.9999.9999`, "the 4.x checkout being built", so the template's `^4.0`
  requirement is satisfied. `.tugboat/config.yml` and the CI workflow use the
  same convention.

**Patches.** The profile's `composer.json` lists patches for Drupal core and
several contributed modules. They are applied during the install exactly as in a
Composer install, and the list of what was applied is kept in
`patches.lock.json`. If a patch does not apply, the build fails.

**Dependencies are resolved at build time.** The template ships no
`composer.lock`, so each build resolves the newest versions its constraints
allow. Two builds of the same tag at different times can therefore contain
different Drupal and contributed-module patch releases. The resolved
`composer.lock` is kept in the image (`/var/www/html/composer.lock`) and
attached to the published image as an attestation, so what shipped is always
recorded.

**Security advisories fail the build.** The stage runs `composer audit --no-dev`.
Advisories the profile has already triaged are listed under
`config.audit.ignore` in its `composer.json`; Composer only honours that setting
in the *root* project, so the build copies it across. Abandoned packages are
reported, not fatal.

**Autoloader.** Composer generates an optimised class map (`--optimize-autoloader`)
but not an *authoritative* one (`--classmap-authoritative`), which is a common
production recommendation for other PHP applications. Drupal registers the
namespaces of core and of each module with the class loader at run time, and an
authoritative class map refuses those lookups; Drupal then fails with
`Class "Drupal\Core\Serialization\Yaml" not found` before it can install.
The end-to-end test in `scripts/docker/test-entrypoint.sh` catches this.

**What is removed.** Only things the running site never reads: `.git`
directories and the `tests`/`Tests` directories of the profile, Composer
dependencies, and contributed modules and themes, plus Drupal core's own test
tree. Licence files, documentation, and everything else stay. Removing more
would save a little space and risk breaking an autoload path.

**PHP platform.** The stage runs on the same PHP image, with the same
extensions, as the runtime image (`php-base`), so Composer checks the real
platform requirements instead of ignoring them.

## Runtime image, tools and extensions

The `runtime` stage is the image that is published. It starts from the official
`php:8.4-fpm-alpine` image, pinned by version and digest, and adds nginx.

**Why nginx, php-fpm, and Alpine.** The goal is the smallest idle memory
footprint, because the motivating use is one host running many separate Mukurtu
sites. nginx and php-fpm are separate, small processes; Alpine's base is a few
megabytes. The Mukurtu team does its own work with nginx. Apache is not used
because its image is larger and the team has not tested it extensively. The
trade-off is that PHP on Alpine uses the musl C library; the performance
difference is small for a Drupal workload, which is dominated by the database
and by opcache.

**PHP extensions.** Everything Drupal core requires, plus:

| Extension | Used for |
| --- | --- |
| `gd` (JPEG, WebP, FreeType) | Image styles and thumbnails. Checked at build time. |
| `intl` | Sorting, transliteration, and number and date formatting. |
| `zip` | Archive handling by Drupal and modules. |
| `pdo_mysql` | The database (MariaDB or MySQL; PostgreSQL is not supported by Mukurtu). |
| `bcmath` | Required by some contributed modules. |
| `Zend OPcache` | Compiled-code cache. Configured in the next section. |
| `apcu` | Drupal's fast cache backend for bootstrap and discovery data. |

The build runs a check that every extension is loaded and that `gd` was built
with JPEG, WebP and FreeType support. A failure stops the build.

**Media tools.** Mukurtu shells out to external programs, and silently skips the
feature when they are missing:

| Program | Package | Mukurtu uses it to |
| --- | --- | --- |
| `pdftotext` | `poppler-utils` | Extract PDF text so documents can be found by search. |
| `pdftoppm` | `poppler-utils` | Render the first page of a PDF as its thumbnail. |
| `ffmpeg` | `ffmpeg` | Extract the first frame of a video as its thumbnail. |

(See `modules/mukurtu_core/src/Hook/MediaToolRequirements.php`, which also
reports a missing tool on the site's status page.) The image is published in two
variants, chosen with the build args `WITH_FFMPEG` and `WITH_POPPLER` (both default
to `true`; they are build options, not container settings):

- **full** (`WITH_FFMPEG=true`, `WITH_POPPLER=true`): the default tags.
- **slim** (both `false`): the `-slim` tags. About 130 MB smaller; PDF text
  search, PDF thumbnails and video thumbnails do not work.

The build runs each tool with the same probe Mukurtu uses and fails if a
variant does not match what was asked for, in either direction: a slim image
that somehow contains `ffmpeg` fails the build, just as a full image without it
does.

**`exec()` stays enabled.** Hardening guides often disable PHP's process
functions. Mukurtu cannot work with them disabled, because it launches the
tools above with `exec()`. The build fails if `exec` appears in
`disable_functions`. The residual risk is that a code-injection bug in Mukurtu,
Drupal, or a module could be used to start a process. It is mitigated by keeping
the image's packages current (see "Publishing and supply chain"), by running as
a non-root user with no extra privileges, and by keeping the code read-only to
that user.

**Users, files, and volumes.**

- The process runs as user `mukurtu`, uid `1000`, a fixed number so ownership of
  mounted volumes is predictable between hosts.
- The code in `/var/www/html` belongs to root and is not writable by the runtime
  user. A compromised PHP process cannot modify the site's code. Only two
  directories are writable, and both are declared as volumes:
  `/var/www/files/public` and `/var/www/files/private`.
- Mukurtu's access control depends on the Drupal *private* file system, which is
  why the private volume is mandatory. `web/sites/default/files` is a symlink to
  the public volume.
- There is no Composer in the image. It is a build tool, not something a running
  site needs, and leaving it out removes a tool that could be misused and a few
  megabytes.
- `tini` is the container's first process. It reaps orphaned child processes,
  which matters because `ffmpeg` and `pdftoppm` are launched from PHP requests.
- Drush is on the `PATH`. `mariadb-client` is included so `drush sql:dump` and
  `drush sql:cli` work for backups. `msmtp` provides the `sendmail` Drupal uses
  for outbound mail (configured from environment variables).

## Configuration rendering

Nothing is edited inside a running container. Every setting is an environment
variable (`MUKURTU_*`), listed with its default and meaning in
[docker.md](docker.md). At every start, `render-config.sh` reads the variables,
validates them, and writes the configuration files for PHP, php-fpm, nginx, and
msmtp from templates in `docker/templates/`.

- **Output location.** Files are written to `/run/mukurtu` (override with
  `MUKURTU_RUN_DIR`), which can be a tmpfs. Nothing else in the image needs to
  be writable by the configuration step, so the container can run with a
  read-only root filesystem.
- **Validation.** Every value is checked before anything starts, and the first
  invalid one stops the container with a message that names the variable, for
  example `ERROR: MUKURTU_UPLOAD_MAX must be a size such as 128M or 1G (got
  '100MB')`. A misspelt unit is far easier to fix at start-up than to diagnose
  as a failed upload.
- **One variable, several settings.** `MUKURTU_UPLOAD_MAX` sets PHP's
  `upload_max_filesize` and `post_max_size` and nginx's `client_max_body_size`
  together. Setting them separately is a classic source of uploads that fail
  with no explanation.
- **Secrets can be files.** `MUKURTU_DB_PASSWORD`, `MUKURTU_ADMIN_PASSWORD`,
  `MUKURTU_HASH_SALT`, and `MUKURTU_SMTP_PASSWORD` also accept a `_FILE` form
  naming a file, the convention used by Docker secrets and Kubernetes. When both
  are set, the file wins. This matters because environment variables are visible
  to anyone who can run `docker inspect` on the container.
- **Values are never run through a shell.** Placeholders in the templates are
  replaced by a small `awk` routine that reads the value from the environment,
  so a password containing `$`, quotes, backslashes, `&`, or `%` reaches the
  configuration unchanged. The SMTP password is not written into the msmtp
  configuration at all: it is stored in its own `0600` file and msmtp is told to
  read it (`passwordeval`), which avoids the quoting rules of that file format.
- **The command line gets its own ini file.** The official PHP image has no
  `php.ini`, so Drush would run with PHP's 128 MB default and run out of memory
  during installation. A second file, `cli/php.ini`, has
  `MUKURTU_PHP_CLI_MEMORY_LIMIT` and no execution-time limit, and the
  entrypoint points `PHPRC` at it.

## Security hardening

Each rule below is covered by a test in `scripts/docker/`. Where a rule follows
published guidance, that guidance is named.

### nginx

The configuration follows
[Drupal's recommended nginx configuration](https://www.drupal.org/docs/getting-started/system-requirements/nginx),
tightened in one respect: Drupal's version runs any `.php` file that exists;
this one runs only `index.php`.

- **PHP is never executed from `sites/*/files`.** Uploaded files land there. If
  an attacker gets a `.php` file uploaded and the server executes it, they own
  the site. Requests for PHP-like extensions under `files` or `private` get a
  404.
- **Only `index.php` reaches php-fpm.** `install.php`, `update.php`,
  `authorize.php`, and any stray script return 404. Database updates run through
  `drush updb` when the container starts, so `update.php` is not needed.
- **Source and configuration files are not served.** Dotfiles (except
  `/.well-known`), `*.yml`, `*.twig`, `*.module`, `*.install`, `*.inc`,
  `*.theme`, `*.sql`, backup and editor files, `composer.json`, and
  `composer.lock` return 404. The Composer `vendor` directory is outside the web
  root altogether.
- **Image styles go through Drupal.** A missing file under `files/styles/` is
  handed to `index.php`, which generates and saves it.
- **Headers.** `server_tokens off` hides the nginx version;
  `X-Content-Type-Options: nosniff` and `Referrer-Policy:
  strict-origin-when-cross-origin` are set on every response. HSTS and
  Content-Security-Policy are deliberately **not** set here: they depend on the
  site's real scheme and origins, which only the TLS-terminating reverse proxy
  knows. Set them there.
- **Listens on port 8080.** A non-root process cannot bind port 80. The reverse
  proxy forwards to 8080.

### PHP

- `expose_php`, `display_errors`, and `html_errors` off: no version banner and
  no stack traces in responses. Errors go to the container log.
- `zend.exception_ignore_args = On`: stack traces in logs omit argument values,
  so passwords passed to functions do not end up in logs.
- `cgi.fix_pathinfo = 0`, the standard guard against path-info tricks that make
  the server run the wrong file.
- `zend.assertions = -1` and `allow_url_include = Off`.
- Session cookies are `HttpOnly`, `SameSite=Lax`, and strict mode refuses
  session IDs the server did not issue.
- `opcache.save_comments` stays **on**. Drupal reads PHP attributes and doc
  comments at run time; turning it off, a common "optimisation", breaks Drupal.
- `exec()` stays enabled. See "Runtime image, tools and extensions".

### php-fpm and the environment

php-fpm normally clears the environment before running PHP. Mukurtu's
`settings.php` reads its configuration from environment variables, so both
pools set `clear_env = no`. This gives PHP code access to the container's
variables, including any secrets passed as plain environment variables. Code
running in the pool can already read `settings.php`, and with it the database
password, so nothing is gained for an attacker. Using `_FILE` secrets keeps the
values out of `docker inspect`, which is the exposure that does matter.

### Container

- Runs as a non-root user; the code is owned by root and is read-only to that
  user.
- The Compose examples add `read_only: true`, `cap_drop: [ALL]`, and
  `no-new-privileges`. `settings.php` is baked into the image, and temporary
  files go to a tmpfs or volume of your choosing.
- `tini` is the first process and reaps orphaned child processes.

## Performance tuning

Defaults are chosen so many idle sites fit on one small server, and so a busy
site is not held back. Every number below is an environment variable; the
reasoning is here, the reference is in [docker.md](docker.md).

- **`pm = ondemand`.** With the usual `dynamic` setting, php-fpm keeps spare
  workers alive per site. Under `ondemand`, an idle site has no PHP workers at
  all; they start on the first request and exit after
  `MUKURTU_FPM_PROCESS_IDLE_TIMEOUT` (10 s). A cold request pays a few
  milliseconds to start a worker, in exchange for an idle footprint of tens of
  megabytes instead of hundreds. `MUKURTU_FPM_MAX_CHILDREN` (4) caps concurrent
  PHP requests, which also caps the memory a busy site can use.
  `MUKURTU_FPM_MAX_REQUESTS` (500) recycles workers to bound memory growth.
- **Two pools.** Drupal serves *private* files through PHP so it can check
  access, and Mukurtu relies on this for culturally sensitive content. Each
  download holds a PHP worker for as long as the client takes to receive the
  file. Requests under `/system/files/` therefore go to a separate pool capped by
  `MUKURTU_FPM_FILES_MAX_CHILDREN` (2), so a handful of large downloads or video
  streams cannot use up the workers that serve pages. Public files do not go
  through PHP at all: nginx serves them directly.
- **opcache.** `validate_timestamps = 0` because the image is immutable: PHP
  never has to check whether a source file changed. `MUKURTU_OPCACHE_MEMORY`
  (128 MB) is sized for Mukurtu's code, and `MUKURTU_OPCACHE_MAX_FILES` (20000)
  above the number of PHP files it loads. The JIT is off: it adds memory and
  little for a request/response workload like Drupal.
- **APCu.** Drupal's chained-fast cache backend keeps bootstrap and discovery
  data in APCu when it is available, avoiding database reads on every request.
  `MUKURTU_APCU_MEMORY` is 32 MB.
- **`realpath_cache`.** Raised to 4 MB with a 10-minute lifetime. Drupal
  resolves many file paths per request; PHP's default cache is too small and
  short-lived for it.
- **nginx.** `sendfile`, gzip for text types, an open-file cache, one year of
  caching for content-hashed aggregated CSS and JS, and 30 days for other
  static assets and image-style derivatives. One worker process by default
  (`MUKURTU_NGINX_WORKER_PROCESSES`), because nginx is not the bottleneck for a
  Drupal site and each worker costs memory.
- **Timeouts.** php-fpm's `request_terminate_timeout` equals
  `MUKURTU_PHP_MAX_EXECUTION_TIME` (120 s). PHP's own limit counts CPU time only,
  so a request stuck waiting on something could otherwise hold a worker for
  ever. The private-file pool has no terminate timeout, since a download may
  legitimately take a long time.

## Drupal settings

A Drupal site's `settings.php` normally differs for every site, and is edited by
hand. In this image it is a single file, `docker/settings.php`, copied into
`web/sites/default/` at build time and read-only to the runtime user. It holds
no site-specific values: it reads them from environment variables when Drupal
starts. The same image therefore serves any number of sites, and a site's whole
configuration is the environment you give its container.

**Database.** `MUKURTU_DB_HOST`, `MUKURTU_DB_NAME`, `MUKURTU_DB_USER`, and
`MUKURTU_DB_PASSWORD` are required (plus optional `MUKURTU_DB_PORT` and
`MUKURTU_DB_PREFIX`). A missing one stops Drupal with a message naming it. The
connection uses the `utf8mb4` character set, as Drupal and Mukurtu recommend, so
characters outside the Basic Multilingual Plane (emoji, some scripts) work. The
database itself must be created as `utf8mb4` too; the Compose examples do this.

**Trusted hosts.** `MUKURTU_TRUSTED_HOSTS` is required: a comma-separated list
of the names the site answers to. Drupal refuses requests whose `Host` header
is anything else, which blocks host-header attacks such as poisoned password
reset links. Each entry is matched exactly (its dots are literal, so
`example.com` does not trust `exampleXcom` or `example.com.evil.org`) and
without regard to case. An entry written `*.example.com` trusts any subdomain.

**Reverse proxy.** With `MUKURTU_REVERSE_PROXY=true`, Drupal takes the client
address, scheme, and host from the `X-Forwarded-*` headers, but **only** from the
addresses in `MUKURTU_REVERSE_PROXY_ADDRESSES`. The list is mandatory when the
proxy is enabled, because trusting those headers from anyone lets a visitor
pretend to be any client.

**Hash salt.** Drupal uses a secret salt to sign one-time login links and
image-style tokens. Set `MUKURTU_HASH_SALT` (or `MUKURTU_HASH_SALT_FILE`)
explicitly for production. If you do not, the entrypoint generates one on first
start and keeps it in the private volume. That works, but it ties the salt to
that volume: if the volume is lost or replaced while the database is kept, the
salt changes, previously issued image-style tokens stop validating, and images
are regenerated until caches are rebuilt. An explicit salt avoids the problem.

**Files.**

- `file_private_path` is `/var/www/files/private`. Mukurtu's access control
  depends on a private file system; the image makes sure one exists.
- `file_temp_path` is `MUKURTU_FILE_TEMP_PATH` (default `/tmp`). Uploads are
  written here before Drupal moves them into place, so a 100 MB upload needs
  100 MB here, and in fact twice that: nginx buffers a request body in a
  `nginx-body` directory under this path (and spills large responses to
  `nginx-fastcgi`) before passing it to PHP, which then
  receives a copy. (The rendered configuration directory, `/run/mukurtu`, is
  small and is not used for uploads.) What backs this directory is your choice: a **tmpfs** is fast but
  counts against the container's memory, which matters for large video uploads;
  a **size-capped volume** uses disk instead. The Compose examples show both.
- `config_sync_directory` is `MUKURTU_CONFIG_SYNC_DIR`, by default a directory
  in the private volume, outside the web root.

**Other settings.**

- `skip_permissions_hardening` is on. Drupal's usual habit of making
  `sites/default` read-only does not apply, because the code is already
  read-only to the runtime user.
- `class_loader_auto_detect` is off. The image never changes, so Drupal does not
  need to look for new classes on each request.
- The on-screen error level is forced to `hide`. Errors still go to the
  container log and Drupal's database log, where administrators can read them,
  but they are never shown to visitors.

## Startup, supervision and health

The container's first process is `tini`, which starts `entrypoint.sh`. The
entrypoint does the following, in order, and stops with a message naming the
setting or path at fault if any step fails:

1. **Resolve secrets and render configuration.** `_FILE` secrets are read, then
   every `MUKURTU_*` variable is validated and the PHP, php-fpm, nginx and msmtp
   files are written (see "Configuration rendering").
2. **Check required settings.** `MUKURTU_DB_HOST`, `MUKURTU_DB_NAME`,
   `MUKURTU_DB_USER`, `MUKURTU_DB_PASSWORD` and `MUKURTU_TRUSTED_HOSTS` must be
   set.
3. **Check the volumes.** `/var/www/files/public`, `/var/www/files/private` and
   `MUKURTU_FILE_TEMP_PATH` must be writable by uid 1000. If not, the error
   names the path and the uid, and says how to fix it. A fresh named volume
   works without any preparation, because Docker gives it the image directory's
   ownership.
4. **Make a hash salt if there is none.** Only when `MUKURTU_HASH_SALT` is
   unset: generated once, stored in the private volume with mode `0600`, and
   reused on every later start.
5. **Warn about missing tools.** One warning per missing program (`ffmpeg`,
   `pdftotext`, `pdftoppm`), so a slim image says what it cannot do, and one for
   an unconfigured mail server.
6. **Wait for the database**, up to `MUKURTU_DB_WAIT_TIMEOUT` seconds (default
   60), retrying every second. This removes the usual race in which the site
   container starts before the database accepts connections. When time runs
   out, the error gives the host, port, and the database's last error message.
7. **Install or update.** The database is classified as one of three states:
   - *empty* and `MUKURTU_AUTO_INSTALL=true`: run `drush site:install mukurtu`
     with the admin account from the environment. `MUKURTU_ADMIN_PASSWORD` and
     `MUKURTU_ADMIN_EMAIL` are required, with no default: an image that came up
     with a well-known password would be an open door. (The password is passed
     to Drush as an argument, so it is visible to processes inside the
     container for the duration of the install.)
   - *installed*: if `MUKURTU_RUN_UPDATES=true`, run `drush updatedb` and
     `drush cache:rebuild`. A new image version contains new code; the cache
     rebuild stops Drupal from using a service container built for the old code.
   - *partial* (tables exist, but not a complete Drupal): refuse to continue.
     Installing over unknown data could destroy it.
8. **Hand over to the supervisor.**

**Supervision.** `supervise.sh` runs php-fpm, nginx, and, when `MUKURTU_CRON` is
`true`, the cron loop. If any of them stops unexpectedly, it stops the others
and exits non-zero, so the container restarts (or stops, visibly) rather than
serving errors. On `SIGTERM` it shuts everything down gracefully and exits `0`.
nginx is started with `-e stderr`: before it reads its configuration, nginx opens a
compiled-in default error log that the unprivileged user cannot write, and would
print a `could not open error log file` alert on every start. A test fails on any
nginx alert in the start-up log.
A plain shell script does this instead of a supervisor package such as s6 or
supervisord: it needs nothing installed, runs as the unprivileged user, and has
no configuration to go wrong.

**Cron.** Drupal needs its periodic tasks run: search indexing, cache cleanup,
notifications, and Mukurtu's own jobs. `cron.sh` runs `drush core:cron` every
`MUKURTU_CRON_INTERVAL` seconds (default 3600). A failed run is logged and
retried at the next interval; it never stops the container. Set
`MUKURTU_CRON=false` if you run cron from outside.

**Health.** The healthcheck requests `/healthz`, which nginx answers by calling
php-fpm's built-in ping. It confirms nginx and php-fpm are alive without
bootstrapping Drupal, so it is cheap enough to run every 30 seconds for every
tenant on a host, and it does not touch the database. A database outage shows in
the site's own status report and in the logs, not as a restart loop. The
healthcheck's start period is ten minutes, because installing a new site takes a
few minutes.

**Running one-off commands.** `docker run ... <image> drush status` (or any
other command) renders the same configuration, then runs the command *without*
waiting for the database, installing, or updating. `docker exec <container>
drush ...` works too; `PHPRC` is set in the image, so Drush always gets the
command-line memory limit.

**Base URL.** Drush needs to know the site's address to write correct links in
output generated by cron (notification emails, for example) and in `drush
user:login`. It defaults to `https://` plus the first entry of
`MUKURTU_TRUSTED_HOSTS`; set `MUKURTU_BASE_URL` to override it. A wildcard first
entry gives no default. `render-config.sh` writes the address to a file at every
start, and `drush` on the `PATH` is a small wrapper that reads it. This is what makes
the address apply to `docker exec <container> drush ...` as well: `docker exec` does
not run the entrypoint, so an environment variable exported there would be missing.

## Known hazards

These are properties of Mukurtu and Drupal that the image can reduce but not
remove. They are documented so operators can size and monitor for them.

1. **Private files are served by PHP.** Access control is the point of Mukurtu's
   private files, so Drupal checks every request and then streams the file. A
   download holds a php-fpm worker for as long as the client takes to receive
   it. The image sends these requests to a separate pool
   (`MUKURTU_FPM_FILES_MAX_CHILDREN`, default 2) so downloads cannot use up the
   workers that serve pages, and the test suite checks that a page request is
   still answered while that pool is full. The limit that remains is that only
   that many private downloads, or video range requests, can run at once per
   site; the others wait. A site that serves a lot of private media should raise
   the value and the container's memory limit together. Removing the limit
   altogether would mean serving private files with `X-Accel-Redirect`, which
   needs a change to the Mukurtu profile and is out of scope for the image.
2. **ffmpeg and poppler memory is outside PHP's `memory_limit`.** Mukurtu runs
   `ffmpeg` and `pdftoppm` inside the upload request, to make thumbnails. Their
   memory belongs to the container, not to the PHP process, so a container limit
   sized for PHP alone can be exceeded and the container killed while a video is
   being uploaded. Leave headroom. The Compose examples allow 1 GB per site; see
   "Measured footprint" for the numbers.
3. **Environment variables can be read by anyone who can inspect the
   container.** `docker inspect` shows them. Use the `_FILE` form for the
   database, admin, hash salt and SMTP secrets.
4. **Dependencies are resolved at build time** (see "Where the code comes
   from"): the lock file recorded in the image is the authoritative list of what
   it contains.

## Hosting examples

Two Compose files show how the image is meant to be run. Both are tested: the
test suite checks that they are valid, that every Mukurtu service has the
hardening below, that the single-site file starts a working site, and that the
hosting file starts two sites behind Caddy, each answering only on its own name.

**Single site** (`docker-compose.example.yml`): the image, MariaDB, two named
volumes for files, and Docker secrets for every password.

**Several sites on one host** (`docker-compose.multi-tenant.example.yml`): one
container per site, all from the same image, behind a Caddy reverse proxy that
routes by host name and obtains TLS certificates. The proxy is Caddy and not a
label-driven proxy such as Traefik because Caddy reads a plain configuration file:
Traefik discovers containers through the Docker socket, and a service with access to
that socket controls the host. Adding a site costs three lines of Caddyfile instead. Each site has its own
environment, database, user, and file volumes, so sites can be upgraded,
restarted, and resource-limited independently, and a problem in one cannot
reach another's data or files. This matters for Mukurtu in particular: sites
often hold culturally sensitive material, and the private-file access control
of one community's site should not share a failure domain with another's. The
alternative, one Drupal installation serving many sites ("multisite"), saves
some memory but shares one codebase, one set of upgrades, and one PHP process
pool. It is not supported by this image.

### What the examples set, and why

| Setting | Reason |
| --- | --- |
| `read_only: true` | The image's code cannot be modified at run time, even by a compromised process. Writable places are only the two file volumes and two tmpfs mounts. |
| `cap_drop: [ALL]` | The process needs no Linux capabilities. Removing them limits what an attacker who gets a shell can do. |
| `security_opt: no-new-privileges` | Stops a process from gaining privileges through setuid binaries. |
| `mem_limit: 1g` per site | A ceiling so one site cannot take the host's memory, with headroom for `ffmpeg` and `pdftoppm`, whose memory is not covered by PHP's `memory_limit`. See "Measured footprint" for what a site actually uses. |
| `tmpfs: /tmp` with a `size` | Where uploads land first (`MUKURTU_FILE_TEMP_PATH`). A tmpfs is fast but counts against the container's memory; for large video uploads, mount a size-capped volume there instead. The size cap bounds the worst case. |
| `tmpfs: /run` | Where the rendered configuration is written (`MUKURTU_RUN_DIR`), so nothing else in the container needs to be writable. |
| `*_FILE` secrets | Passwords are mounted as files and read from there. They never appear in `docker inspect`. |
| Hosting file: sites publish no port | Only Caddy is reachable from outside; each site speaks plain HTTP to it on an internal network. |
| Hosting file: unlisted domains are not served | Caddy only obtains certificates for, and answers, the names in its Caddyfile. |
| Published on `127.0.0.1` only | The reverse proxy connects to the site; the site is not reachable from outside the host except through the proxy. |
| `MUKURTU_REVERSE_PROXY_ADDRESSES` | Only the proxy network may set `X-Forwarded-*` headers. The hosting file gives the proxy network a fixed address range so this can be exact. |
| Database on an `internal` network | The database is reachable from the sites and nothing else. |

### MariaDB settings

The examples start MariaDB with the settings Drupal documents for MySQL and
MariaDB:

- `--character-set-server=utf8mb4 --collation-server=utf8mb4_general_ci`: the
  recommended character set, so emoji and scripts outside the Basic
  Multilingual Plane are stored correctly.
- `--transaction-isolation=READ-COMMITTED` with `--binlog-format=ROW`: Drupal
  recommends READ-COMMITTED to avoid lock contention and deadlocks under
  concurrent writes; row-based binary logging is required to use it safely.
- `--max-allowed-packet=64M`: Drupal stores large serialized values.
- `--innodb-buffer-pool-size`: set to roughly half of the database
  container's memory limit.

### Adding a site

Copy a `site_*` block and its two volumes, give it a new host name, database
name and credentials, and add its database to the `db_init` config (which runs
on the database's first start). For a database that already exists, create the
database and user by hand.

## Publishing and supply chain

Images are published by `.github/workflows/docker-publish.yml` to
`ghcr.io/<owner>/mukurtu`, where `<owner>` is the account that owns the
repository (lower-cased). No owner is written into any file, so the same workflow
runs unchanged in the upstream repository and in a fork.

### When it runs

| Trigger | What happens |
| --- | --- |
| A release is **published** | That release's tag is built, tested, scanned, and pushed. |
| **Run workflow** (manual) with a tag | The same, for a tag you name. Use this if a release was created by another workflow using `GITHUB_TOKEN`: GitHub does not start workflows from events caused by that token, so `release: published` never fires. |

The tag being built is checked out, so the image is built from that tag's
`Dockerfile` and code. A tag that predates the `Dockerfile` cannot be built.

The workflow refuses two mistakes before building anything: a tag that is not a
release tag (`v4.0.5`, `4.x-dev`, `4.0.5-r0`), and a release that GitHub marks as
a pre-release but whose tag has no pre-release suffix. Tags are derived from the
version alone, so the second would let a pre-release take `latest`. Re-tag it as,
for example, `4.0.5-rc1`.

### Tags

There are three kinds of release tag, and every one publishes its exact tag in both
variants (full, and slim with a `-slim` suffix):

| Tag | Kind | Also publishes |
| --- | --- | --- |
| `4.0.5` | A Mukurtu release | `4.0`, `4`, `latest` (when it is the newest in each scope) |
| `4.0.5-r1` | An **image revision**: a new image of the same Mukurtu release, to ship a platform fix | `4.0`, `4`, `latest` (when it is the newest in each scope) |
| `4.0.0-rc`, `4.0.0-beta37` | A pre-release | nothing |

A pre-release never becomes what `latest`, `4`, or `4.0` pulls. A revision exists
because the image has dependencies Mukurtu does not (the base image, PHP, nginx,
`ffmpeg`, `poppler-utils`), and a fix to one of them is a new image of the same
Mukurtu code, not a new Mukurtu release. `-r<number>` counts from 1; it is distinct
from `-rc1`, and Composer rejects it as a version, so Packagist never offers a
revision as a Composer release. The procedure is in
[releasing.md](releasing.md#releasing-an-image-revision).

**Moving tags only advance.** `4.0`, `4`, and `latest` are published for a tag only
if that tag is the newest stable or revision tag in the scope of each (the same
minor version, the same major version, everything). The workflow reads the
repository's existing tags to decide. So `4.0.5-r1` published after `4.0.5` moves
`latest`, but `4.0.4-r1` published after `4.0.5` does not: a backport can never
pull `latest` backwards. The plain tag `4.0.5` is never re-pushed once published,
so a deployment pinned to it stays on that image until it moves to `4.0.5-r1` on
purpose.

The rules are in `scripts/docker/compute-tags.sh` and are tested, including the
backport and ordering cases.

Pin an exact tag (`4.0.5` or `4.0.5-r1`) in production and upgrade on purpose.
`4.0` follows patch releases and revisions, `4` follows minor releases, and
`latest` follows everything.

### What must pass before anything is pushed

The `publish` job needs the `test` job, which:

1. Runs every `scripts/docker/test-*.sh`. These build the images and exercise them:
   the build guard, the build stage, the runtime contents, configuration
   rendering, the nginx rules, `settings.php`, a real install against MariaDB with
   restart, cron, health and shutdown checks, the Compose examples, and this
   workflow's own structure.
2. Scans the full and slim images with Trivy for **fixable** vulnerabilities of
   **HIGH or CRITICAL** severity. A finding fails the run. Findings with no fix
   yet are ignored (nothing can be done about them), and anyone who accepts a
   specific one must add it to `docker/.trivyignore` with a reason and a review
   date. The scan covers the operating system packages and the Composer
   dependencies, including Drupal core and contributed modules.

The Composer build stage also runs `composer audit --no-dev`, which checks
Drupal's security advisories, so an advisory fails the build before the image is
even assembled.

### Supply chain

- **Base images are pinned by version and digest** in the Dockerfile's `FROM`
  lines, and Dependabot (`docker` ecosystem) proposes updates as pull requests.
  GitHub Actions are pinned to major versions and updated by Dependabot as well,
  following the other workflows in this repository.
- **Provenance and SBOM.** Each push carries a BuildKit provenance attestation
  (`mode=max`) and an SBOM, and a signed build-provenance attestation is added
  with `actions/attest-build-provenance`, so consumers can verify that an image
  was built by this workflow from this repository. Verify with
  `gh attestation verify oci://ghcr.io/<owner>/mukurtu:<tag> --owner <owner>`.
- **The resolved `composer.lock`** is inside the image
  (`/var/www/html/composer.lock`) and is also kept as a workflow artifact for
  each variant. The template has no lock file, so two builds of the same tag can
  differ; this file records exactly what each one contained.
- **Least privilege.** The workflow's default permission is read-only. Only the
  `publish` job can write packages and create attestations.
  Event data (tag names, inputs) reaches scripts through environment variables,
  never by interpolation into script text, which would allow script injection.

### How fixes reach users

A published tag is a fixed artifact. Nothing rebuilds and re-pushes `4.0.5` after
it is published: what you pulled yesterday is what you pull today, so a tag, a
rollback, and an audit trail mean what they say.

That means a fix to a base image (Alpine, PHP), to nginx, or to Drupal does not
reach anyone until it is released. The pieces that make this practical:

- Dependabot proposes updates to the pinned base images and to the GitHub Actions
  as pull requests.
- Every release is scanned by Trivy, and a fixable HIGH or CRITICAL finding stops
  it from being published, so a release does not ship with a known, fixable
  vulnerability of that severity in the image's packages.
- `composer audit` stops the build on a Drupal advisory.
- If a fix is needed, publish it: a new Mukurtu release if the fix is in Mukurtu
  or Drupal, or an **image revision** (`4.0.5-r1`) if it is only in the platform.
  Nothing else changes in the image, so the Mukurtu version stays what it was.

Operators who want updates without watching for them can follow a moving tag
(`4.0`, which advances with each new patch release and revision, or `4`, which also
advances with minor releases) or let their
own tooling propose new versions. Deployments that need bit-for-bit
reproducibility should pin the image digest.

### One-time setup

- GHCR creates the package **private** on first push. To let others pull the image,
  open the package's settings on GitHub and set its visibility to **public**.
- The pinned template version is in `docker/TEMPLATE_VERSION`. Update it when a
  new `mukurtu/mukurtu-template` release is wanted in the image.

## Measured footprint

These figures were measured with `scripts/docker/measure.sh`, which builds both
variants, installs a real site, and samples the container with `docker stats`
(memory in use, excluding reclaimable cache). Run it yourself to check them on
your hardware.

**Conditions:** 2026-10-18, `linux/amd64`, Docker 29.9.0 on a 16-CPU, 28 GB Linux
host, Mukurtu 4.0.5 with `mukurtu-template` 4.0.2, a freshly installed site with
no content, default settings (`ondemand`, 4 workers, 128 MB opcache, 32 MB APCu).

| Measurement | Result |
| --- | --- |
| Image size, full (uncompressed) | 456 MB |
| Image size, slim (uncompressed) | 327 MB |
| Site just started, before any request | 123 MB |
| Idle, after warm-up (no PHP workers running) | **89 MB** |
| Under load: four concurrent request streams, 4 PHP workers | **145 MB** peak |
| Back to idle after the load | 92 MB |
| `ffmpeg` extracting a thumbnail from a 100 MB 1080p H.264 video | 159 MB peak (the process alone) |

**What the idle figure is made of.** nginx and the supervising scripts together
use about 12 MB. Most of the rest is shared memory holding the compiled code
(opcache) and the APCu cache, which stays allocated after the PHP workers exit.
That is why an idle site costs far less than a PHP application with permanent
workers, and why a second site cannot share it: each container has its own.

**How to read the load figure.** Four workers added about 55 MB on top of idle,
roughly 14 MB each, because workers share the compiled code. This was measured
against an empty site's anonymous login page, which is a light request. Pages of a
site with real content (collections, search results, Layout Builder pages, an
authenticated administrator working in the editor) use considerably more per
worker, up to `MUKURTU_PHP_MEMORY_LIMIT`. Treat 145 MB as a floor for a lightly
used site, not as a typical figure for a busy one, and measure with your own
content.

**What to budget for a site.** A 1 GB container limit, as in the Compose examples,
is a ceiling, not a reservation. The worst case it has to hold is: the idle
footprint (about 90 MB), plus one PHP worker at its limit (512 MB), plus `ffmpeg`
working on an upload (about 160 MB for this video, more for 4K), plus the
uploaded file itself if `MUKURTU_FILE_TEMP_PATH` is a tmpfs (held twice, so 200 MB
for a 100 MB upload): roughly 960 MB. A site that never has a video uploaded needs far less.

**Planning a host.** Most of the time an idle site costs under 100 MB, so many
low-traffic sites fit on a small server; the limit is how many are busy *at the
same moment*. Add up the idle figures of all the sites, add the database server
and the operating system, then leave room for a few sites to burst to several
hundred megabytes at once. Memory limits per container keep one site's burst from
taking the others' memory. Swap or zram on the host gives some protection against
the occasional overlap.
