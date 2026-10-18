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
