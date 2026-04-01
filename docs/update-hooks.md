# Update Hooks

Update hooks are necessary to push certain changes to when updating existing sites.


## Update hook placement

Update hooks should be placed in the corresponding `/modules/module_name/module_name.install` file.
If there isn't a clear module to use, include the hook in `/modules/mukurtu_core/mukurtu_core.install`.

# Update hook naming

Use the function hook_update_N naming convention: https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Extension%21module.api.php/function/hook_update_N/11.x

Include the name of the module (`mukurtu_core_update`), followed by the three digits to indicate major/minor/patch numbering (`400`), and the last two digits are increased sequentially for each hook within the module (`01`)

For example, the first update hook created within `mukurtu_core` will be `mukurtu_core_update_40001`.