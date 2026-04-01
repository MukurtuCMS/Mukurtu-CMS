# Update Hooks

Update hooks are necessary to push certain changes to when updating existing sites. For example:

- Schema Changes: Adding, updating, or deleting tables/fields via hook_schema() in the .install file.
- Configuration Updates: Updating default configuration values that have changed in a new version of the module.
- Data Migration/Manipulation: Updating, restructuring, or migrating existing data to a new format, particularly using the $sandbox for large datasets.
- https://www.drupal.org/docs/drupal-apis/update-api/introduction-to-the-update-api-for-drupal-8

Basically if a file in `modules/module_name/config/install` is added or changed, an update hook is likely required. There are other cases as well.

We need to be cautious about changes that:

- Modify site content.
- May clobber intentional site configuration.

Sometimes changes are minor enough, or may affect common config changes that they should not be hooked, and are suitable to be recommended in update documentation instead. Eg: changing the weight/placement of forms in fields

## Update hook placement

Update hooks should be placed in the corresponding `/modules/module_name/module_name.install` file.
If there isn't a clear module to use, include the hook in `/modules/mukurtu_core/mukurtu_core.install`.

## Update hook naming

Use the function hook_update_N naming convention: https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Extension%21module.api.php/function/hook_update_N/11.x

Include the name of the module (`mukurtu_core_update`), followed by the three digits to indicate major/minor/patch numbering (`400`), and the last two digits are increased sequentially for each hook within the module (`01`)

For example, the first update hook created within `mukurtu_core` will be `mukurtu_core_update_40001`.

## Further reading

- https://git.drupalcode.org/project/drupal/-/blob/main/core/modules/system/system.install?ref_type=heads#L42
- https://git.drupalcode.org/project/drupal/-/blob/main/core/modules/system/system.post_update.php?ref_type=heads
- https://www.drupal.org/project/drupal/issues/3263053
- https://www.drupal.org/project/drupal/issues/3307646

## To-do

- Read more into how users can maintain select config changes that conflict with updates.
- Plan a post-4.0 clean up of stale update hooks (we will likely remove all existing hooks in the first tagged release after 4.0.1 and then start fresh, to minimize redundant code while allowing anyone operating in the beta environment to receive updates to 4.0.0).