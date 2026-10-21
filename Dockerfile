# syntax=docker/dockerfile:1
#
# Mukurtu CMS image. See docs/docker-build.md for how and why it is built this
# way, and docs/docker.md for how to run it.
#
# The one build argument is MUKURTU_TEMPLATE_VERSION, the mukurtu/mukurtu-template
# version the image is built on. Its default below is the pinned version: change that
# line to move to a newer template. There are no options for what the image contains:
# ffmpeg and poppler-utils, which Mukurtu needs for video and PDF thumbnails and PDF
# text, are always installed. (The running container is configured with MUKURTU_*
# environment variables, not build arguments.)
#
# The base images are pinned by version and digest in the FROM lines so that
# Dependabot can update them and a rebuild of the same tag starts from the same
# bytes.

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
      libavif \
      libjpeg-turbo \
      libmaxminddb \
      libpng \
      libwebp \
      libzip; \
    apk add --no-cache --virtual .build-deps \
      $PHPIZE_DEPS \
      freetype-dev \
      icu-dev \
      libavif-dev \
      libjpeg-turbo-dev \
      libmaxminddb-dev \
      libpng-dev \
      libwebp-dev \
      libzip-dev; \
    docker-php-ext-configure gd --with-avif --with-freetype --with-jpeg --with-webp; \
    docker-php-ext-install -j"$(nproc)" gd intl pdo_mysql zip bcmath; \
    pecl install apcu maxminddb; \
    docker-php-ext-enable apcu maxminddb; \
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

ARG MUKURTU_TEMPLATE_VERSION=4.0.2

COPY --from=composer /usr/bin/composer /usr/bin/composer
RUN apk add --no-cache git unzip

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1 \
    COMPOSER_MEMORY_LIMIT=-1

# The checkout, minus the files that only build or document the image. Leaving
# them out keeps the Composer layers cached when only those files change.
# Composer copies the result into web/profiles/mukurtu (symlink=false) rather
# than linking it.
COPY --exclude=Dockerfile --exclude=.dockerignore --exclude=docker --exclude=scripts/docker \
     . /src/mukurtu

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
# root project. (COMPOSER_AUDIT_FLAGS is empty, and so strict, unless a build is started
# by hand while Drupal's advisory service is down: --ignore-unreachable. The Trivy scan
# in the pipeline still checks the finished image against its own advisory database.)
ARG COMPOSER_AUDIT_FLAGS=
RUN set -eux; \
    ignore="$(php -r 'echo json_encode((object) (json_decode(file_get_contents("/src/mukurtu/composer.json"), true)["config"]["audit"]["ignore"] ?? []));')"; \
    composer config --json audit.ignore "$ignore"; \
    composer audit --no-dev --abandoned=report $COMPOSER_AUDIT_FLAGS

# Drop what the running site never reads: version-control metadata and test
# suites (including Drupal core's own, whose fixtures also trip vulnerability
# scanners with fake package data). Licences, READMEs, and everything else stay.
RUN set -eux; \
    find /app/web/profiles/mukurtu /app/vendor /app/web/modules/contrib /app/web/themes/contrib \
      -name .git -prune -exec rm -rf {} +; \
    find /app/web/profiles/mukurtu /app/vendor /app/web/modules/contrib /app/web/themes/contrib \
      \( -name tests -o -name Tests \) -type d -prune -exec rm -rf {} +; \
    find /app/web/core \( -name tests -o -name Tests \) -type d -prune -exec rm -rf {} +

# ---------------------------------------------------------------------------
# runtime: the image that ships. nginx + php-fpm on Alpine, started by
# entrypoint.sh under tini. No Composer, no dev tooling.
# ---------------------------------------------------------------------------
FROM php-base AS runtime

# fontconfig and font-liberation: many PDFs name a standard font (Helvetica, Times, Courier)
# without embedding it. With no fonts installed pdftoppm draws nothing for them, so the
# thumbnail is blank while text extraction still works. Liberation is metric-compatible
# with those fonts. poppler-data holds the character maps poppler needs for CJK and other
# non-Latin PDFs. (PDFs in other scripts must embed their fonts, so need nothing more.)
RUN set -eux; \
    apk add --no-cache \
      mariadb-client \
      msmtp \
      nginx \
      tini \
      ffmpeg \
      poppler-utils \
      poppler-data \
      fontconfig \
      font-liberation

# Fail the build if a media tool does not run. Mukurtu skips thumbnails and PDF text
# silently when a tool is missing, so the image must never lack one. The probes are
# the ones Mukurtu itself uses to report a missing tool
# (modules/mukurtu_core/src/Hook/MediaToolRequirements.php).
RUN set -eu; \
    ffmpeg -version >/dev/null || { echo "ERROR: ffmpeg does not run" >&2; exit 1; }; \
    pdftotext -v >/dev/null 2>&1 || { echo "ERROR: pdftotext does not run" >&2; exit 1; }; \
    pdftoppm --help >/dev/null 2>&1 || { echo "ERROR: pdftoppm does not run" >&2; exit 1; }

# Fail the build if PHP cannot do what Drupal and Mukurtu need of it.
RUN set -eu; \
    php -r ' \
      $missing = []; \
      foreach (["ctype","curl","dom","fileinfo","filter","gd","hash","iconv","intl","json","libxml","mbstring","openssl","pcre","PDO","pdo_mysql","session","SimpleXML","SPL","tokenizer","xml","xmlwriter","zip","zlib","Zend OPcache","apcu","maxminddb"] as $e) { \
        if (!extension_loaded($e)) { $missing[] = $e; } \
      } \
      $gd = gd_info(); \
      foreach (["JPEG Support","WebP Support","FreeType Support","AVIF Support"] as $f) { \
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
    ln -s /var/www/files/public /var/www/html/web/sites/default/files

# Entrypoint, config templates, and settings.php. Last so that editing them
# does not rebuild the layers above.
COPY docker/ /usr/local/lib/mukurtu/
RUN cp /usr/local/lib/mukurtu/settings.php /var/www/html/web/sites/default/settings.php; \
    ln -s /usr/local/lib/mukurtu/drush /usr/local/bin/drush

VOLUME ["/var/www/files/public", "/var/www/files/private"]

# Where render-config.sh writes the rendered configuration. A tmpfs mounted at
# /run replaces this directory; the entrypoint then creates it again.
RUN mkdir -p /run/mukurtu && chown mukurtu:mukurtu /run/mukurtu

# PHPRC makes every command-line PHP (Drush, `docker exec ... drush`) read the
# rendered CLI ini instead of PHP's 128M defaults.
# XDG_CACHE_HOME gives fontconfig (used by pdftoppm) a writable place for its cache; the
# root file system is read-only, and without one every PDF render logs two errors.
ENV MUKURTU_RUN_DIR=/run/mukurtu \
    PHPRC=/run/mukurtu/cli \
    XDG_CACHE_HOME=/run/mukurtu/cache

WORKDIR /var/www/html
USER mukurtu

EXPOSE 8080

# php-fpm answers /healthz through its own ping handler, without bootstrapping Drupal,
# so the check is cheap and does not need the database. Installing a fresh site takes
# minutes; do not call the container unhealthy while it does.
HEALTHCHECK --interval=30s --timeout=5s --start-period=600s --retries=3 \
  CMD curl -fsS --max-time 4 http://127.0.0.1:8080/healthz | grep -q pong

# The php-fpm base image stops containers with SIGQUIT. The supervisor handles
# SIGTERM (Docker's usual signal, and Kubernetes') and passes the right signal on
# to each process.
STOPSIGNAL SIGTERM

ENTRYPOINT ["/sbin/tini", "--", "/usr/local/lib/mukurtu/entrypoint.sh"]
CMD ["supervise"]
