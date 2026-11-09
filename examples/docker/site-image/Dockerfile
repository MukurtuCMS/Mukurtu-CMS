# syntax=docker/dockerfile:1
# A site's own image: the official Mukurtu image plus contributed modules from
# Composer and your own code. Use this when a module needs Composer libraries;
# otherwise the read-only folders in the other examples are simpler. Rebuild it
# for every Mukurtu update. How to use it: README.md.

# Required: the exact Mukurtu release to build on, for example
#   docker build --build-arg MUKURTU_IMAGE=ghcr.io/mukurtucms/mukurtu:4.0.8-r1 -t my-site:4.0.8-r1-1 .
ARG MUKURTU_IMAGE
FROM ${MUKURTU_IMAGE:?MUKURTU_IMAGE_is_required} AS mukurtu

# Composer runs in a throwaway stage, so the finished image has no Composer and
# no build tools in it.
FROM mukurtu AS build
USER root
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN apk add --no-cache git unzip patch
ENV COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_NO_INTERACTION=1 COMPOSER_MEMORY_LIMIT=-1
COPY composer-packages.txt /tmp/composer-packages.txt
# --minimal-changes keeps Mukurtu, Drupal and everything already in the image
# at the versions the release shipped with. The local-dev repository points at
# a folder that is only there while the official image is built.
RUN set -eu; \
    packages=$(grep -v '^[[:space:]]*\(#\|$\)' /tmp/composer-packages.txt || true); \
    if [ -n "$packages" ]; then \
      composer config --unset repositories.local-dev; \
      composer require --no-progress --update-no-dev --optimize-autoloader --minimal-changes $packages; \
    fi

FROM mukurtu
COPY --from=build --chown=0:0 /var/www/html /var/www/html
COPY --chown=0:0 custom/modules /var/www/html/web/modules/custom
COPY --chown=0:0 custom/themes /var/www/html/web/themes/custom
