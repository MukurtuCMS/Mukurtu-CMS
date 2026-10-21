# Cutting a release

## Bumping the version

From the repository root:

```bash
scripts/set-version.sh 4.0.1
```

That is the whole version bump. It writes **31 files**:

| File(s) | Where the value shows up |
| --- | --- |
| `VERSION.md` | The "Mukurtu Version" element on the Mukurtu Dashboard |
| `mukurtu.info.yml` | The profile's version |
| 26 module `.info.yml` files | Per-module version at `/admin/modules` |
| `themes/mukurtu_v4/mukurtu_v4.info.yml` | `/admin/appearance` |
| `package.json`, `themes/mukurtu_v4/package.json` | Build tooling, kept in step |
| `themes/mukurtu_v4/package-lock.json` | The root package's version, which npm copies in |

`tests/src/Unit/VersionConsistencyTest.php` fails if any of these disagree with
`VERSION.md`, so a hand edit to one of them is caught in CI.

Then review and commit:

```bash
git diff --stat          # sanity check: 31 files
git commit -am "Update version from 4.0.0 to 4.0.1"
```

### Accepted formats

`4.0.1`, `4.0.1-beta1`, `4.x-dev`. Anything else is refused, nothing is written,
and the script exits `1` — so `4.0.1beta` fails loudly instead of writing a
malformed version into 31 files. Safe to call from another script.

### Between releases, `main` reads `4.x-dev`

Only a tagged commit carries a release number. Everything after it on `main` is
unreleased code, often with new update hooks, so it must not call itself the
release it came after. Otherwise every Tugboat preview, test image and dev site
built from `main` shows the last release on the Dashboard while running code
that release does not contain, and a bug report quoting that version points at
the wrong code. The *Set `main` back to `4.x-dev`* step of the checklist below does that once
the tag exists and the image has published.

### What it deliberately skips

The two test fixture modules under `tests/` (`drafts_entity_test` and
`mukurtu_multilingual_translation_test`). They are not part of the distribution
and carry their own unrelated `8.x-1.x-dev` versions.

### Why the script exists

The version used to be a hand edit of `VERSION.md`, and the `.info.yml` files
were never touched at all. That is why at `4.0.0-rc` the Dashboard correctly
reported `4.0.0-rc` while all 27 `.info.yml` files still read `4.x-dev`, so
`/admin/modules` showed something different from the Dashboard. The script keeps
them from drifting apart again.

## Release checklist

1. **Update from `main`** and confirm CI is green.
2. **Check update hooks.** See [update-hooks.md](update-hooks.md). Any new
   `hook_update_N()` must be numbered above the module's existing maximum, and
   above its `hook_update_last_removed()` if it has one.
3. **Compile SCSS** if the theme changed, without minifying `style.css`.
4. **Bump the version** with `scripts/set-version.sh`, as above, and add the
   release's entry to `CHANGELOG.md`.
5. **Merge to `main`.** If the release work is a stack of pull requests, confirm
   each one's base actually moved before merging the next — see
   [stacked-prs.md](stacked-prs.md), which documents three occasions where a
   merged PR never reached `main`.
6. **Tag** the merge commit with the bare version, matching existing tags
   (`4.0.0-beta37`, `4.0.0-rc`, `4.0.0`).
7. **Publish release notes.** Call out anything that changes the upgrade
   procedure, and any release that must be passed through rather than skipped.
8. **Check whether `mukurtu-template` needs a release.** See [Releasing
   alongside mukurtu-template](#releasing-alongside-mukurtu-template). If it
   does, release it *after* the profile tag is published, never before.
9. **The container image publishes itself.** Publishing the release starts
   `.github/workflows/docker-publish.yml`, which builds the image on both
   architectures, runs the test suite against it, scans it, and pushes it to
   `ghcr.io/<owner>/mukurtu`. Check that the run succeeded. If the release was
   created by a workflow using `GITHUB_TOKEN`, the event does not fire; run the
   workflow by hand with the tag in `ref` (leave `dev` unticked). After a new
   `mukurtu-template` release, update the `ARG MUKURTU_TEMPLATE_VERSION` default
   in the `Dockerfile` (in the next release, or as an [image
   revision](#releasing-an-image-revision)). See [docker-build.md](docker-build.md),
   "Publishing and supply chain".

   The image is built from the **tag**, not from `main`, so what `main` holds at
   that moment does not matter to it.
10. **Set `main` back to `4.x-dev`.** Once the tag is pushed and the image has
    published, run `scripts/set-version.sh 4.x-dev` on a branch from `main`, and
    merge it as the first change after the release. See [Between releases, `main`
    reads `4.x-dev`](#between-releases-main-reads-4x-dev).

**Order.** These steps depend on each other by name, not by number, so other steps
can be added around them. The order is: *Tag*, then *Check whether
`mukurtu-template` needs a release*, then the image publishes (starting from the
published release), and only then any step that moves `main` on (for example setting
it back to `4.x-dev`). The image workflow checks out the tag it was given, so it
does not depend on that last step.

To ship a fix to the platform without a new Mukurtu release, see [Releasing an
image revision](#releasing-an-image-revision). To try the image before a release
(from `main` or a pull request's branch), run the workflow by hand with `dev` ticked;
see [Development images](docker-build.md#development-images).

## Before a release: the manual checks for the container image

The automated tests (`scripts/docker/test-*.sh`) run in the pipeline for every image.
These checks need a real host or a human, so they are done by hand before a release
that changes the image, and before the first release of a new line.

1. **Upgrade drill.** Installs a site with the previous release's code, then starts the
   current image on the same data:
   `scripts/docker/drill-upgrade.sh 4.0.5` (the previous release tag; fetch the tags
   first). It takes about ten minutes and checks that the site still serves pages, that
   users and files survive, and that no update is left pending and the Status report
   has no errors. It is not in the default suite because it builds a second image.
2. **A real domain with HTTPS.** Run the `multi-tenant` example on a host with a real
   domain, using Let's Encrypt's *staging* environment first (add
   `acme_ca https://acme-staging-v02.api.letsencrypt.org/directory` next to `email`
   in the Caddyfile's global options, so a mistake does not use up the rate limit). Check that the certificate is issued, that `http://` redirects to
   `https://`, that the login link emailed to a user opens on `https://`, and that
   `docker compose logs caddy` shows no errors.
3. **Uploads and media, by hand.** On the running example, as an administrator: upload
   a 100 MB video and see that its thumbnail appears; upload a PDF and see its
   thumbnail and that its text is searchable; download a file from the private
   file system as a user who may, and see it refused for one who may not.
   (The Playwright suite in `tests/playwright` covers pages and accessibility, not
   uploads, and it needs seeded content, so it is not part of the image tests.)
4. **Read the log.** `docker compose logs mukurtu` from a fresh start through a
   restart: no PHP warnings, no `pending database updates` that the release notes did
   not mention.

## Releasing an image revision

The image has dependencies Mukurtu does not: the Alpine, PHP and nginx packages,
`ffmpeg` and `poppler-utils`, and the pinned base images. A fix to one of them (a
Dependabot update to the `Dockerfile`'s `FROM` lines, a vulnerability the Trivy scan
reports) is a new **image** of an existing Mukurtu release, not a new Mukurtu release.
Ship it as an **image revision**: the Mukurtu version with `-r1`, then `-r2`, and so on
(`4.0.5`, then `4.0.5-r1`; the next Mukurtu release `4.0.6` starts again with no suffix).
Published tags are never rebuilt. Why `-r1`: a bare `-1` reads as a SemVer pre-release,
`-r<number>` cannot be confused with `-rc1`, and Composer rejects it as a version, so
Packagist never offers a revision as a release (`-p1` would be read as a *newer patch
level*). How the tags behave is in [docker-build.md](docker-build.md#tags).

To release one:

1. **Branch from the release being revised**, so the Mukurtu code stays exactly as
   released: `git switch -c image-4.0.5-r1 4.0.5`.
2. **Apply the platform fix**, usually the commit Dependabot's pull request put on
   `main` (`git cherry-pick <sha>`), or edit the `FROM` lines or `docker/` by hand. Do
   **not** run `scripts/set-version.sh`: the Mukurtu version stays `4.0.5`.
3. **Run the image tests**: `for t in scripts/docker/test-*.sh; do $t || break; done`.
4. **Tag and push**: `git tag 4.0.5-r1 && git push origin image-4.0.5-r1 4.0.5-r1`.
5. **Publish a GitHub release** for `4.0.5-r1` (not a pre-release) saying what changed,
   for example "Image-only release. No change to Mukurtu 4.0.5. Updates the base image to
   Alpine 3.24.3." The workflow tests, scans and pushes it. `4.0`, `4` and `latest` move
   only if it is the newest in their scope, so a backport (`4.0.4-r1` after `4.0.5`)
   never pulls `latest` backwards. `4.0.5` itself never changes.
6. **Make sure the same fix is on `main`** (merging Dependabot's pull request does it),
   so the next Mukurtu release has it too.

## Releasing alongside mukurtu-template

[mukurtu-template](https://github.com/MukurtuCMS/mukurtu-template) is the root
project sites install from. It requires `"mukurtu/mukurtu": "^4.0"`, so it does
**not** need a release for every profile release: template `4.0.0` installs the
newest 4.x profile on its own.

A template release is needed only when the root `composer.json` itself must
change. Composer honours these keys **only** in the root project, so the profile
cannot declare them for itself:

- stability flags (`@beta`, `@dev`, `@alpha`), and `minimum-stability` /
  `prefer-stable`
- `config.allow-plugins`
- `repositories`
- `extra.installer-paths` and `extra.installer-types`

**When a release needs both, tag the profile first and the template second.**
The template can only require a profile version that already exists. Releasing
it first leaves `^4.0` resolving to tags that predate the matching profile
change, and every fresh install fails.

That is what happened on 2026-09-14. Template `4.0.1` pinned `drupal/altcha` to
`2.0.0-beta1@beta` as the companion to #2075, which was never tagged, so every
released `mukurtu/mukurtu` still required `drupal/altcha ^1.2`:

```
- mukurtu/mukurtu[4.0.0, ..., 4.0.2] require drupal/altcha ^1.2 -> found
  drupal/altcha[1.2.0] but it conflicts with your root composer.json
  require (2.0.0-beta1@beta).
```

Both halves were reverted (#2210, mukurtu-template#7, released as template
`4.0.2`); the re-land is tracked in #2211. The template repo now resolves
against live Packagist on every pull request, which fails a root requirement no
released profile can satisfy.

Two further consequences worth knowing:

- **Merging a template fix is not enough; it must be tagged.** Packagist serves
  the newest matching tag, so `^4.0` keeps resolving to the broken release until
  a new one is cut.
- **A profile PR's CI resolves against the released template.**
  `.github/workflows/build-and-test.yml` and `.tugboat/config.yml` both scaffold
  with `composer create mukurtu/mukurtu-template:^4.0`, then overlay the branch
  as a path repo, so a profile change needing a template change stays red until
  the template is released. Tugboat runs its `composer create` in the `init:`
  phase, which is cached in the base preview, so it also needs a base preview
  rebuild.

## Releases that remove update hooks

When a release drops `hook_update_N()` implementations, each affected module
needs a `hook_update_last_removed()` returning the highest number that release's
predecessor shipped, and the numbering rule in step 2 then applies to everything
added afterwards.

Be aware that Drupal core does **not** enforce `hook_update_last_removed()`.
`update_get_update_list()` only lists updates above the site's installed
version, so a site too far behind sees "no pending updates" and silently runs on
stale configuration rather than being stopped. Blocking such a site needs an
explicit `hook_update_requirements()` returning `REQUIREMENT_ERROR`; that is
honoured by both `update.php` and `drush updb`.
