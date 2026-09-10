# Update hooks

Update hooks push changes to sites that already exist. For example:

- Schema changes: adding, updating or deleting tables and fields declared via
  `hook_schema()` in the `.install` file.
- Configuration updates: changing default configuration values that shipped in
  an earlier version of the module.
- Data migration: restructuring or migrating existing data, using `$sandbox`
  for anything large enough to need batching.
- https://www.drupal.org/docs/drupal-apis/update-api/introduction-to-the-update-api-for-drupal-8

Basically, if a file under `modules/<module>/config/install` is added or
changed, an update hook is probably required. There are other cases too.

**A hook never runs on a fresh install.** Drupal seeds a newly installed
module's schema version to its highest available update number without invoking
any of them, so a change made only in a hook reaches existing sites and never
new ones. Whatever end state the hook produces has to be in `config/install` as
well, or the two diverge permanently.

Be cautious about changes that:

- Modify site content.
- May clobber intentional site configuration.

Some changes are minor enough, or touch config that sites commonly customise,
that they are better recommended in release notes than forced by a hook. For
example, changing the weight or placement of fields on a form.

## Placement

- Put the hook in the matching `modules/<module>/<module>.install`.
- If there is no clear module, use `modules/mukurtu_core/mukurtu_core.install`.
- Do not put update hooks in `mukurtu.install`.
- Add them at the END of the file.
- Never reuse a number already present in that file.

## Numbering

Use Drupal's [`hook_update_N`](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Extension%21module.api.php/function/hook_update_N/11.x)
convention: the module name, then a number whose first three digits are the
major/minor/patch version and whose last two increment per hook within that
module. The first `mukurtu_core` hook for 4.0.0 was `mukurtu_core_update_40001`.

Two rules constrain the number, both enforced by
[`scripts/lint/update-hook-numbering.sh`](../scripts/lint/update-hook-numbering.sh)
in CI:

1. **It must not repeat a number already in the file.** Drupal runs only one of
   a duplicated pair, so the other silently never executes.
2. **It must be greater than the module's `hook_update_last_removed()`**, if the
   module has one. Anything at or below that value is dead code that looks
   live, for the reasons in the next section.

### The two-digit sequence can overflow

The scheme assumes fewer than 100 hooks per version. `mukurtu_core` exceeded
that during 4.0.0 and ran to `mukurtu_core_update_40122`, consuming the whole
`401xx` band that 4.0.1 would otherwise have used. So the number is not always
derivable from the version.

Check the file's existing maximum before picking a number, rather than assuming
the version tells you. The lint will catch a number that is too low, but it
cannot tell you which one you meant.

## Removing update hooks

Hooks accumulate. A release may clear them out by deleting every
`hook_update_N()` from a module and adding a `hook_update_last_removed()`
returning the highest number that release's predecessor shipped. Drupal then
seeds fresh installs at that number, so a new site is correctly treated as
already past everything the removed hooks did.

Sites must reach the predecessor release before they can take the release that
removed the hooks, since the intervening updates no longer exist to run.

### What core does and does not do about a site that is too far behind

Core reports it, but does not reliably stop it. Both halves matter, and the
difference is easy to get wrong.

**Core does detect it, in the update phase.**
`SystemRequirementsHooks::checkRequirements()` walks every installed module,
calls its `hook_update_last_removed()`, and returns an error-severity
requirement for each one whose installed schema version is lower:

> Unsupported schema version: Mukurtu Core
> The installed version of the Mukurtu Core module is too old to update. Update
> to an intermediate version first (last removed version: 40123, installed
> version: 40122).

So a project does not need its own hook to get this detected. What a project's
own `hook_update_requirements()` adds is a single consolidated message that can
name the specific release to update to first, which core's generic wording
cannot.

**Core does not detect it at runtime.** That check is gated on
`$phase == 'update'`, so nothing appears on the status report. A site that
updates its code and never runs `updb` gets no indication at all. Covering that
needs a `hook_runtime_requirements()` of your own.

**An error-severity requirement does not stop `drush updb -y`.** This is the
one that bites in practice. Drush treats it as a confirmable prompt, not a
failure (`UpdateDBCommands.php`):

```php
if (!$this->updateCheckRequirements()) {
    if (!$this->io()->confirm(dt('Requirements check reports errors. Do you wish to continue?'))) {
        throw new UserAbortException();
    }
}
```

Verified behaviour on a real site left behind by a strip:

| Command | Outcome |
| --- | --- |
| `drush updatedb -y` | errors printed, then "No pending updates", exit 0 |
| `drush updatedb` non-interactive | same |
| `drush updatedb --no` | "Cancelled", exit 1 |

Since `-y` is what every deployment script and our own `.tugboat/config.yml`
use, treat the requirement as a loud warning rather than a gate, and rely on the
runtime check to keep the problem visible afterwards.

**Attribute-hook rules.** `requirements` is on core's deny list for
attribute-based hooks (`HookCollectorPass::checkForProceduralOnlyHooks()` throws
a `LogicException`) and must stay procedural in a `.install` file. Neither
`update_requirements` nor `runtime_requirements` is on that list, so both may be
`#[Hook]` classes under `src/Hook/`. Update-phase code is still safer left
procedural, since it runs while the container may not reflect the code on disk.

## Further reading

- https://git.drupalcode.org/project/drupal/-/blob/main/core/modules/system/system.install?ref_type=heads#L42
- https://git.drupalcode.org/project/drupal/-/blob/main/core/modules/system/system.post_update.php?ref_type=heads
- https://www.drupal.org/project/drupal/issues/3263053
- https://www.drupal.org/project/drupal/issues/3307646

## To-do

- Read more into how users can maintain select config changes that conflict with
  updates.
