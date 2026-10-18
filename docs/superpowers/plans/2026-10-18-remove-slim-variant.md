# Remove the slim variant and the tool build options

**Status:** TODO, deliberately deferred. Do this after the `4.0.5-rc1` pipeline run
and the friend's testing round are finished, and ship it as `4.0.5-rc2`. Do not
change anything while that experiment is running.

**Decision (owner, 2026-10-18):** the slim image is pointless, because every Mukurtu
site needs `ffmpeg` and `poppler-utils`. Remove the slim variant **and** the
`WITH_FFMPEG` / `WITH_POPPLER` build arguments (option B). The tools are always
installed. There is one image, with one set of tags.

**What stays:** the build still runs `ffmpeg -version`, `pdftotext -v` and
`pdftoppm --help` and fails if any does not work (the "image cannot silently lack a
tool" guarantee). `MUKURTU_TEMPLATE_VERSION` stays a required build argument and the
guard stage keeps validating it. The runtime-contents tests keep checking the tools.

## Changes

- [ ] **`Dockerfile`**
  - Delete the `ARG WITH_FFMPEG=true` / `ARG WITH_POPPLER=true` block and its header
    comment; rewrite the header to say there is one build argument,
    `MUKURTU_TEMPLATE_VERSION`.
  - `guard` stage: drop the tool loop; keep the template-version check.
  - `runtime` stage: drop both `ARG`s; install `ffmpeg` and `poppler-utils` in the
    main `apk add`; make the verification step unconditional (each tool must run);
    delete the "absent" branches.
- [ ] **`.github/workflows/docker-publish.yml`**
  - `publish`: remove the matrix (`variant`, `with_ffmpeg`, `with_poppler`), the
    `VARIANT` env, the `WITH_*` build-args, the variant in the job name, and the
    `-${{ matrix.variant }}` artifact and cache scopes.
  - `test`: remove the "Scan the slim image" step (only `mukurtu-test:full` exists;
    rename the tag to `mukurtu-test:image` everywhere or keep `full`).
- [ ] **`scripts/docker/compute-tags.sh`**: drop the `<full|slim>` argument and the
  suffix logic. Usage becomes `compute-tags.sh [--existing FILE|-] <tag>`.
- [ ] **Tests** (RED first for each)
  - `test-guard.sh`: remove the tool-argument cases; keep the template-version cases.
  - `test-runtime.sh`: build one image; remove the slim build and the "no tool" checks;
    keep every "has ..." check. Drop the "no build args means full" case.
  - `test-entrypoint.sh`: remove the slim build, the `slim-warns` case, and the `$SLIM`
    plumbing.
  - `test-lib.sh`: `DEFAULT_BUILD_ARGS` keeps only the template version.
  - `test-workflow.sh`: remove the `-slim` tag cases and the matrix-variant check;
    assert there is a single publish job without a matrix.
  - `measure.sh`: drop the slim build and the slim size line.
- [ ] **`docker/entrypoint.sh`**: remove the "tool is not installed" warnings loop
  (the tools cannot be missing now). Update `docs` and the troubleshooting table.
- [ ] **Docs**
  - `docs/docker.md`: remove the full/slim table and the `-slim` tags, "Full and slim
    are different images", the `WITH_*` rows in "Building the image yourself" and the
    "add `--build-arg WITH_FFMPEG=false`" sentence, and the two tool-warning rows in
    Troubleshooting.
  - `docs/docker-build.md`: "Pinned inputs and the build guard" (the tools rows and the
    paragraph on defaults), "Runtime image, tools and extensions" (the full/slim list,
    the 130 MB sentence), "Tags" (the slim columns), the workflow matrix text, the
    measured-footprint table (drop the slim size row).
  - `docs/releasing.md`: the `-slim` mention in the image-revision section.
  - Spec `docs/superpowers/specs/2026-10-17-docker-image-design.md`: the Image section,
    the Tags section, the decisions table.
- [ ] **Friend bundle** (`../mukurtu-compose-bundle`): no slim references; only the
  image tag needs updating to `4.0.5-rc2`.

## Verification

Run the whole suite (`for t in scripts/docker/test-*.sh; do $t || break; done`).
Note `test-compose.sh` publishes `127.0.0.1:8080`; stop any local Compose experiment
first. Then push, publish the pre-release `4.0.5-rc2`, and check that the run builds
**one** image and tags only `4.0.5-rc2`.

## Cleanup outside the repo

Delete the `4.0.5-rc1-slim` package version in the GitHub UI (Packages, mukurtu,
Package settings). The `gh` token does not have package scopes.
