# Composer patches

Every patch this profile applies to a third-party package (drupal/core, pathauto,
media_entity_soundcloud, etc.) is declared in this repo's own `composer.json`, under
`extra.patches`.

## Always use a pinned remote URL, never a local path

A patch's `url` must be an absolute, pinned URL — for example a
`raw.githubusercontent.com` link at a specific commit SHA:

```
https://raw.githubusercontent.com/MukurtuCMS/Mukurtu-CMS/<commit-sha>/patches/<file>.patch
```

Never a path relative to this repo (e.g. `web/profiles/mukurtu/patches/<file>.patch`).

**Why:** this profile's own patch files are declared with paths relative to the site
root, not to itself. When a release adds a *new* patch, an in-place
`composer update mukurtu/* -W` on an already-installed site applies that patch to its
target package *before* this profile's own package update finishes extracting to disk
— so a local-path URL points at a file that doesn't exist yet, and the update fails
with "file could not be downloaded". A pinned remote URL sidesteps this entirely,
since Composer can download the patch over HTTP regardless of what's extracted
locally. See the README's "Troubleshooting" section for the failure this caused before
this convention existed.

## Adding a new patch

1. In your PR, add the `.patch` file under `patches/` and the `composer.json` entry
   pointing at that PR branch's own last commit SHA (already resolvable via
   `raw.githubusercontent.com` once pushed to GitHub, even before merge).
2. After merging to `main`, check whether the merge preserved that commit SHA. If not
   (e.g. a squash merge), push one small follow-up commit updating just that entry's
   `url` to the actual `main` merge-commit SHA — before any release tag containing this
   change is cut.

## Updating an existing patch

Only change a patch's `url` when the patch file's *content* changes — point it at the
new commit that changes the file. A patch that hasn't changed never needs its pin
touched again, even across many later releases.
