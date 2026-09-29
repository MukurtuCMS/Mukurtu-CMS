# Cutting a release

## Bumping the version

From the repository root:

```bash
scripts/set-version.sh 4.0.1
```

That is the whole version bump. It writes **30 files**:

| File(s) | Where the value shows up |
| --- | --- |
| `VERSION.md` | The "Mukurtu Version" element on the Mukurtu Dashboard |
| `mukurtu.info.yml` | The profile's version |
| 26 module `.info.yml` files | Per-module version at `/admin/modules` |
| `themes/mukurtu_v4/mukurtu_v4.info.yml` | `/admin/appearance` |
| `package.json`, `themes/mukurtu_v4/package.json` | Build tooling, kept in step |

Then review and commit:

```bash
git diff --stat          # sanity check: 30 files
git commit -am "Update version from 4.0.0 to 4.0.1"
```

### Accepted formats

`4.0.1`, `4.0.1-beta1`, `4.x-dev`. Anything else is refused, nothing is written,
and the script exits `1` — so `4.0.1beta` fails loudly instead of writing a
malformed version into 30 files. Safe to call from another script.

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
4. **Bump the version** with `scripts/set-version.sh`, as above.
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
   does, release it *after* this tag is published, never before.

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
