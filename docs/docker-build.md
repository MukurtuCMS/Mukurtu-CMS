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
