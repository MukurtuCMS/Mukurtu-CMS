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
explicit input, and the build refuses to start without it.

**Required build arguments.** These have no defaults:

| Build arg | Allowed values | Why it is required |
| --- | --- | --- |
| `WITH_FFMPEG` | `true` or `false` | Mukurtu extracts video thumbnails with `ffmpeg`. An image that silently lacks it would accept video uploads and never produce thumbnails, with nothing logged. |
| `WITH_POPPLER` | `true` or `false` | Mukurtu extracts PDF text and thumbnails with `pdftotext` and `pdftoppm` (from poppler-utils). The same silent failure applies. |
| `MUKURTU_TEMPLATE_VERSION` | a `mukurtu/mukurtu-template` version, for example `4.0.2` | The template defines the Drupal core version, the Composer plugins, and the install paths. Floating on `^4.0` would make one release tag build different images over time. |

The first stage of the `Dockerfile`, `guard`, checks them and fails with a
message that names the argument:

```
ERROR: build arg WITH_POPPLER must be 'true' or 'false' (got 'yes')
ERROR: build arg MUKURTU_TEMPLATE_VERSION must be set (for example 4.0.2)
```

Every later stage copies a marker file from `guard`, so BuildKit cannot skip it.
The published images set these explicitly: the full image uses
`WITH_FFMPEG=true WITH_POPPLER=true`, and the `-slim` image uses both `false`.

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
3. Runs `composer update --no-dev --optimize-autoloader --classmap-authoritative`.

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
variants, chosen with the required build args `WITH_FFMPEG` and `WITH_POPPLER`:

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
  100 MB here. What backs this directory is your choice: a **tmpfs** is fast but
  counts against the container's memory, which matters for large video uploads;
  a **size-capped volume** uses disk instead. The Compose examples show both.
- `config_sync_directory` is `MUKURTU_CONFIG_SYNC_DIR`, by default a directory
  in the private volume, outside the web root.

**Other settings.**

- `skip_permissions_hardening` is on. Drupal's usual habit of making
  `sites/default` read-only does not apply, because the code is already
  read-only to the runtime user.
- `class_loader_auto_detect` is off. The autoloader is a complete class map and
  the image never changes, so Drupal does not need to look for new classes on
  each request.
- The on-screen error level is forced to `hide`. Errors still go to the
  container log and Drupal's database log, where administrators can read them,
  but they are never shown to visitors.
