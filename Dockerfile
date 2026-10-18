# syntax=docker/dockerfile:1
#
# Mukurtu CMS image. See docs/docker-build.md for how and why it is built this
# way, and docs/docker.md for how to run it.
#
# Every input that changes what ends up in the image is a build arg with no
# default (WITH_FFMPEG, WITH_POPPLER, MUKURTU_TEMPLATE_VERSION). The base images
# are pinned by version and digest in the FROM lines so that Dependabot can
# update them and a rebuild of the same tag starts from the same bytes.

# ---------------------------------------------------------------------------
# guard: refuse to build unless the required inputs were passed explicitly.
# Later stages copy the marker file so BuildKit cannot skip this stage.
# ---------------------------------------------------------------------------
FROM alpine:3.24@sha256:294b683cb724975bec92580e1e685676bd4b50bda910ddb8c51d4cabeaec77e6 AS guard

ARG WITH_FFMPEG
ARG WITH_POPPLER
ARG MUKURTU_TEMPLATE_VERSION

RUN set -eu; \
    for name in WITH_FFMPEG WITH_POPPLER; do \
      eval "value=\${$name:-}"; \
      case "$value" in \
        true|false) ;; \
        *) echo "ERROR: build arg $name must be 'true' or 'false' (got '$value')" >&2; exit 1 ;; \
      esac; \
    done; \
    if [ -z "${MUKURTU_TEMPLATE_VERSION:-}" ]; then \
      echo "ERROR: build arg MUKURTU_TEMPLATE_VERSION must be set (for example 4.0.2)" >&2; \
      exit 1; \
    fi; \
    touch /guard-ok

# ---------------------------------------------------------------------------
# php-base: PHP with the extensions Drupal and Mukurtu need. Shared by `build`
# (so Composer resolves against the real platform instead of ignoring it) and
# by `runtime`, so the extensions are compiled once.
# ---------------------------------------------------------------------------
FROM php:8.4.26-fpm-alpine3.24@sha256:78cd8de9970a9cd6ff4d98860a94eb5bf37dd2d5776b4785562dbfad01c29d5a AS php-base

# Runtime libraries stay; the -dev packages and compilers are removed in the
# same layer so they never reach the image.
RUN set -eux; \
    apk add --no-cache \
      freetype \
      icu-libs \
      libjpeg-turbo \
      libpng \
      libwebp \
      libzip; \
    apk add --no-cache --virtual .build-deps \
      $PHPIZE_DEPS \
      freetype-dev \
      icu-dev \
      libjpeg-turbo-dev \
      libpng-dev \
      libwebp-dev \
      libzip-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp; \
    docker-php-ext-install -j"$(nproc)" gd intl pdo_mysql zip bcmath; \
    pecl install apcu; \
    docker-php-ext-enable apcu; \
    apk del --no-network .build-deps; \
    rm -rf /tmp/pear /var/cache/apk/*

# ---------------------------------------------------------------------------
# composer: only supplies the Composer binary to `build`. A named stage, not
# `COPY --from=composer:2.x`, so that Dependabot (which reads FROM lines) can
# update the pinned version and digest.
# ---------------------------------------------------------------------------
FROM composer:2.10.3@sha256:af98f42dfff7c68ba8d53c2164fd9fde1087b7d449514baa38c418b1f6bc4bac AS composer

# ---------------------------------------------------------------------------
# build: the Composer project. mukurtu-template is the skeleton (Drupal core,
# scaffold, plugin allow-list, install paths); this checkout replaces the
# Packagist copy of the profile, so the image holds exactly the tagged code.
# ---------------------------------------------------------------------------
FROM php-base AS build

ARG MUKURTU_TEMPLATE_VERSION

COPY --from=composer /usr/bin/composer /usr/bin/composer
RUN apk add --no-cache git unzip

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1 \
    COMPOSER_MEMORY_LIMIT=-1

# The checkout, minus the files that only build or document the image. Leaving
# them out keeps the Composer layers cached when only those files change.
# Composer copies the result into web/profiles/mukurtu (symlink=false) rather
# than linking it.
COPY --exclude=Dockerfile --exclude=.dockerignore --exclude=docker --exclude=docker-compose*.yml \
     --exclude=scripts/docker . /src/mukurtu

WORKDIR /app

# A path repository takes its version from git, which is absent here, so the
# version is declared. mukurtu-template requires mukurtu/mukurtu ^4.0, and the
# placeholder 4.9999.9999 stands for "the 4.x checkout being built" (the same
# convention .tugboat/config.yml uses). Composer treats a path repository as
# canonical, so Packagist is never consulted for the profile.
RUN set -eux; \
    composer create-project "mukurtu/mukurtu-template:${MUKURTU_TEMPLATE_VERSION}" /app \
      --no-install --no-dev --no-progress --no-scripts; \
    composer config repositories.local-dev \
      '{"type":"path","url":"/src/mukurtu","options":{"symlink":false,"versions":{"mukurtu/mukurtu":"4.9999.9999"}}}'; \
    composer update --no-dev --no-progress --optimize-autoloader

# Note: no --classmap-authoritative. Drupal registers the namespaces of core and
# of every module with the class loader at run time; an authoritative class map
# refuses those lookups and Drupal cannot even start installing.

# Fail the build on a known security advisory. Advisories the profile already
# triages (config.audit.ignore in its composer.json) are copied into the root
# project's config first, because Composer only reads that setting from the
# root project.
RUN set -eux; \
    ignore="$(php -r 'echo json_encode((object) (json_decode(file_get_contents("/src/mukurtu/composer.json"), true)["config"]["audit"]["ignore"] ?? []));')"; \
    composer config --json audit.ignore "$ignore"; \
    composer audit --no-dev --abandoned=report

# Drop what the running site never reads: version-control metadata and test
# suites (including Drupal core's own, whose fixtures also trip vulnerability
# scanners with fake package data). Licences, READMEs, and everything else stay.
RUN set -eux; \
    find /app/web/profiles/mukurtu /app/vendor /app/web/modules/contrib /app/web/themes/contrib \
      -name .git -prune -exec rm -rf {} +; \
    find /app/web/profiles/mukurtu /app/vendor /app/web/modules/contrib /app/web/themes/contrib \
      \( -name tests -o -name Tests \) -type d -prune -exec rm -rf {} +; \
    find /app/web/core \( -name tests -o -name Tests \) -type d -prune -exec rm -rf {} +

# Marker so BuildKit cannot skip the guard stage.
COPY --from=guard /guard-ok /guard-ok

# ---------------------------------------------------------------------------
# runtime: the image that ships. nginx + php-fpm on Alpine, run by an
# entrypoint added in a later layer of this file. No Composer, no dev tooling.
# ---------------------------------------------------------------------------
FROM php-base AS runtime

# Both are required, with no default, and checked by the guard stage. They
# decide whether the media tools Mukurtu shells out to are in the image.
ARG WITH_FFMPEG
ARG WITH_POPPLER

COPY --from=guard /guard-ok /guard-ok

RUN set -eux; \
    apk add --no-cache \
      mariadb-client \
      msmtp \
      nginx \
      tini; \
    if [ "$WITH_FFMPEG" = true ]; then apk add --no-cache ffmpeg; fi; \
    if [ "$WITH_POPPLER" = true ]; then apk add --no-cache poppler-utils; fi

# Fail the build if the image does not match the variant that was asked for.
# The probes are the ones Mukurtu itself uses to report a missing tool
# (modules/mukurtu_core/src/Hook/MediaToolRequirements.php).
RUN set -eu; \
    if [ "$WITH_FFMPEG" = true ]; then \
      ffmpeg -version >/dev/null || { echo "ERROR: WITH_FFMPEG=true but ffmpeg does not run" >&2; exit 1; }; \
    elif command -v ffmpeg >/dev/null; then \
      echo "ERROR: WITH_FFMPEG=false but ffmpeg is installed" >&2; exit 1; \
    fi; \
    if [ "$WITH_POPPLER" = true ]; then \
      pdftotext -v >/dev/null 2>&1 || { echo "ERROR: WITH_POPPLER=true but pdftotext does not run" >&2; exit 1; }; \
      pdftoppm --help >/dev/null 2>&1 || { echo "ERROR: WITH_POPPLER=true but pdftoppm does not run" >&2; exit 1; }; \
    elif command -v pdftotext >/dev/null || command -v pdftoppm >/dev/null; then \
      echo "ERROR: WITH_POPPLER=false but poppler-utils is installed" >&2; exit 1; \
    fi

# Fail the build if PHP cannot do what Drupal and Mukurtu need of it.
RUN set -eu; \
    php -r ' \
      $missing = []; \
      foreach (["ctype","curl","dom","fileinfo","filter","gd","hash","iconv","intl","json","libxml","mbstring","openssl","pcre","PDO","pdo_mysql","session","SimpleXML","SPL","tokenizer","xml","xmlwriter","zip","zlib","Zend OPcache","apcu"] as $e) { \
        if (!extension_loaded($e)) { $missing[] = $e; } \
      } \
      $gd = gd_info(); \
      foreach (["JPEG Support","WebP Support","FreeType Support"] as $f) { \
        if (empty($gd[$f])) { $missing[] = "gd: " . $f; } \
      } \
      if (in_array("exec", array_map("trim", explode(",", (string) ini_get("disable_functions"))), true)) { \
        $missing[] = "exec() is disabled, but Mukurtu needs it for thumbnails and PDF text"; \
      } \
      if ($missing) { fwrite(STDERR, "ERROR: PHP is missing: " . implode(", ", $missing) . "\n"); exit(1); } \
    '

# A fixed uid so volume ownership is predictable across hosts.
RUN adduser -D -u 1000 -h /home/mukurtu mukurtu

# The code is owned by root and not writable by the runtime user: a compromised
# PHP process cannot rewrite the site. Only the two file volumes are writable.
COPY --from=build /app /var/www/html

RUN set -eux; \
    mkdir -p /var/www/files/public /var/www/files/private; \
    chown mukurtu:mukurtu /var/www/files/public /var/www/files/private; \
    rm -rf /var/www/html/web/sites/default/files; \
    ln -s /var/www/files/public /var/www/html/web/sites/default/files; \
    ln -s /var/www/html/vendor/bin/drush /usr/local/bin/drush

# Entrypoint, config templates, and settings.php. Last so that editing them
# does not rebuild the layers above.
COPY docker/ /usr/local/lib/mukurtu/
RUN cp /usr/local/lib/mukurtu/settings.php /var/www/html/web/sites/default/settings.php

VOLUME ["/var/www/files/public", "/var/www/files/private"]

# Where render-config.sh writes the rendered configuration. A tmpfs mounted at
# /run replaces this directory; the entrypoint then creates it again.
RUN mkdir -p /run/mukurtu && chown mukurtu:mukurtu /run/mukurtu

# PHPRC makes every command-line PHP (Drush, `docker exec ... drush`) read the
# rendered CLI ini instead of PHP's 128M defaults.
ENV MUKURTU_RUN_DIR=/run/mukurtu \
    PHPRC=/run/mukurtu/cli

WORKDIR /var/www/html
USER mukurtu

EXPOSE 8080

# Installing a fresh site takes minutes; do not call the container unhealthy
# while it does.
HEALTHCHECK --interval=30s --timeout=5s --start-period=600s --retries=3 \
  CMD ["/usr/local/lib/mukurtu/healthcheck.sh"]

# The php-fpm base image stops containers with SIGQUIT. The supervisor handles
# SIGTERM (Docker's usual signal, and Kubernetes') and passes the right signal on
# to each process.
STOPSIGNAL SIGTERM

ENTRYPOINT ["/sbin/tini", "--", "/usr/local/lib/mukurtu/entrypoint.sh"]
CMD ["supervise"]
