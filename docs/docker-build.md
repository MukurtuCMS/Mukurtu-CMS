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
