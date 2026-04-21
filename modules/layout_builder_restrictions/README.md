Layout Builder Restrictions
---------------------------

* Introduction
* Requirements
* Installation & management in the UI
* Choose which restrictions are active
* Adding new restrictions via plugin (developers)
* Advanced restrictions using hooks
* Maintainers

INTRODUCTION
------------

The core [Layout Builder](https://www.drupal.org/project/ideas/issues/2884601) module allows all blocks/fields and layouts to
be used; this module supplements it by allowing site builders to set which
blocks and which layouts should be available for placement in Layout Builder.

Each entity type can individually set which blocks are available to be placed,
and each entity type's view mode can have different settings.

Developers can add additional/alternate restriction contexts via Drupal plugins
(see below).

The following image shows the user interface for restricting the "one-column"
layout:

![alt text](https://www.drupal.org/files/layout_builder_restrictions.gif "Restrict one-column layout with checkbox in UI")

REQUIREMENTS
------------

* PHP: 7.0 or above
* Drupal core: 8.6.x or above
* Layout Builder (core module)

INSTALLATION & MANAGEMENT IN THE UI
-----------------------------------

1. After enabling this module, go to any node content type's edit page
(e.g., `/admin/structure/types/manage/page`)
2. Expand the "Layout options" fieldset and choose either "Blocks available for
placement" or "Layouts available for placement". Initially, all blocks and
layouts are available, as would be the case if the module were not enabled.
For blocks, each "provider" is listed, and can either be allowlisted to allow
all blocks from the given provider, or restricted with the "Choose specific..."
option:

![alt text](https://www.drupal.org/files/issues/2018-06-05/layout_builder_restrictions_ui.png "Logo Title Text 1")

Restrictions will affect both which blocks/layouts are available when setting
the entity type's defaults, and individual content item overrides (note: you
must check "Allow each content item to have its layout customized" to support
overrides).

Depending on the use case, Layout Builder Restrictions can be used in conjunction with [Block List Override](https://www.drupal.org/project/block_list_override), and/or with block type permissions (introduced in Drupal 8.8; see https://www.drupal.org/node/3041203).

CHOOSE WHICH RESTRICTIONS ARE ACTIVE
------------------------------------

Layout Builder Restrictions provides a plugin architecture so that different
restriction methodologies can be used. The module provides a single restriction
plugin, which restricts blocks and layouts per entity (and per view mode). If
additional plugins have been added, site builders can control which plugins are
active at `/admin/config/content/layout-builder-restrictions`

### Disambiguation: block types, individual custom blocks, and inline blocks
This module provides separate restrictions for "CUSTOM BLOCK TYPES",
"CONTENT BLOCKS", and "INLINE BLOCKS."

Restrictions for "CUSTOM BLOCK TYPES" will prevent *any* individual blocks of a
restricted type, created through the block library, from being placed.

If, on the other hand, the "CONTENT BLOCKS" restriction section is used -- i.e.,
restrictions are placed on *specific instances* of blocks -- this restriction
will take precedence over those defined in "CUSTOM BLOCK TYPES." For most site
configurations, you will likely use either block type-level restrictions or
individual block restrictions, but not both.

Separately, the "INLINE BLOCKS" section regulates which block types are
restricted from being created inline (i.e., on the `Layout` tab of a Layout
Builder-enabled entity).

ADD NEW RESTRICTIONS VIA PLUGIN (DEVELOPERS)
--------------------------------------------

Developers can add their own plugins (via `@LayoutBuilderRestriction`
annotations) for use cases not covered by the module.

A separate plugin could, for example:

* Restrict block placement based on the selected layout section or region
* Supplement the UI for restricting blocks based on, say, machine name regular
 expression matching

Developers looking to implement their own restriction should be able to start
from the default `EntityViewModeRestriction` plugin and modify as needed.

New plugins are expected to implement the following methods:

* `alterBlockDefinitions(array $definitions, array $context)`: given a list of
 available block definitions, and a context for where the block is being placed,
 return an array of allowed block definitions.
* `alterSectionDefinitions(array $definitions, array $context)`: given a list
 of available layouts, and a context for where the layout is being placed,
 return an array of allowed layouts.
* `blockAllowedinContext()`: given a variety of contexts (including where in
 the layout the block is coming from and where the block is moving to),
 return validation in the form of `TRUE`, or restriction message array.

Plugins are responsible for storing configuration associated with the plugin.
The default plugin stores its configuration as a third-party-setting on
Drupal's entity view mode configuration. Plugins whose restrictions aren't
specific to entity view modes, for example, would use a different storage
location.

Plugins are also expected to provide their own UI for settings restrictions.
The default plugin uses a form alter to modify each entity view mode's settings
form. New plugins could use a similar alter, or provide their own standalone
configuration form.

Helper methods for plugins are provided by `PluginHelperTrait`:

* `getBlockDefinitions(LayoutEntityDisplayInterface $display)`: Returns a list
 of all registered blocks by provider, as well as a list of custom block types,
 where $display specifies which entity type is requesting the available blocks
* `getLayoutDefinitions()`: Returns a list of all registered layouts

ADVANCED RESTRICTIONS USING HOOKS
---------------------------------

More idiosyncratic restrictions not requiring a UI such as restricting certain
content fields (like the "Title" field from placement), can be done by hook
implementation of [hook_plugin_filter_TYPE__CONSUMER_alter()](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Plugin%21plugin.api.php/function/hook_plugin_filter_TYPE__CONSUMER_alter/8.6.x)
(which is invoked for both themes and modules).

This hook is what this module, itself uses (see the .module file); an example
implementation, from the layout_builder_test.module file, is below:

```php
/**
 * Implements hook_plugin_filter_TYPE__CONSUMER_alter().
 */
function layout_builder_test_plugin_filter_block__layout_builder_alter(array &$definitions) {
  // Explicitly remove the "Help" blocks from the list.
  unset($definitions['help_block']);

  // Explicitly remove the "Sticky at top of lists field_block".
  $disallowed_fields = [
    'sticky',
  ];

  foreach ($definitions as $plugin_id => $definition) {
    // Field block IDs are in the form 'field_block:{entity}:{bundle}:{name}',
    // for example 'field_block:node:article:revision_timestamp'.
    preg_match('/field_block:.*:.*:(.*)/', $plugin_id, $parts);
    if (isset($parts[1]) && in_array($parts[1], $disallowed_fields, TRUE)) {
      // Unset any field blocks that match our predefined list.
      unset($definitions[$plugin_id]);
    }
  }
}
```

MAINTAINERS
-----------

Current maintainers:

* eiriksm - https://www.drupal.org/u/eiriksm
* UT Austin - https://www.drupal.org/university-of-texas-at-austin

This project has been sponsored by:

* [The University of Texas at Austin](https://www.drupal.org/university-of-texas-at-austin)
* [NY Media AS](https://www.drupal.org/ny-media-as)
