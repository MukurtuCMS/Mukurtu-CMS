# Rendering blocks with Twig Tweak

This subject is rather confusing because too many things in Drupal are referred
to as "Blocks". So it is essential to understand what kind of block you are
going to render. This guide covers three main cases you may deal when rendering
blocks in a Twig template.

## Block - plugin
Technically speaking block plugin is a PHP class with a special annotation. See
[Branding block plugin](https://git.drupalcode.org/project/drupal/-/blob/9.1.0/core/modules/system/src/Plugin/Block/SystemBrandingBlock.php#L16-22) as an example.

The simplest way to render block plugin is as follows.
```twig
{{ drupal_block('plugin_id') }}
```

Optionally you can pass block label and plugin configuration in the second
parameter.
```twig
{{ drupal_block('plugin_id', {label: 'Example'|t, some_setting: 'example', setting_array: {value: value}}) }}
```

By default, blocks are rendered using `block.html.twig` template. This can be
turned off by setting wrapper parameter to false.
```twig
{{ drupal_block('plugin_id', wrapper=false) }}
```

The tricky thing here is figuring out block plugin ID. If you know which module
provides a particular plugin, you can find its PHP class under the
`MODULE_NAME/src/Plugin/Block` directory and locate the ID in the class
annotation. For instance the plugin ID of login block can be found in the
following file: `core/modules/user/src/Plugin/Block/UserLoginBlock.php`. When
using the plugin ID, convert its format to snake_case (meaning the words are
lowercase and separated by underscores e.g. `system_branding_block`).

To look up all core block plugins use grep search.

```shell
grep -r ' id = ' core/modules/*/src/Plugin/Block/;'
```

However, this does not work for block types that are defined dynamically using
plugin derivatives (like views blocks).

The best way to get all registered plugin IDs is fetching them with block plugin
manager
```shell
drush ev "print_r(array_keys(\Drupal::service('plugin.manager.block')->getDefinitions()));"
```

Note that the plugin_id needs to be wrapped in quotes. For example,
```twig
{{ drupal_block('system_breadcrumb_block') }}
```

## Block - configuration entity
This is what we configure on `admin/structure/block` page. It's important to know
that eventually these entities are rendered using block plugins described above.
The purpose of the configuration entities is to store plugin IDs and
configuration. Additionally, they reference theme and region where a block
should be printed, but this data are not used when rendering through Twig Tweak.

So having configured a block through administrative interface you can print it
using the following code.
```twig
{{ drupal_entity('block', 'block_id') }}
```

Disabled blocks won't be printed unless you suppress access control as follows.

```twig
{{ drupal_entity('block', 'block_id', check_access=false) }}
```

Note that block_id here has nothing to do with 'block_plugin_id' we discussed
before. It is an ID (machine_name) of block configuration entity. You may copy
it from the block configuration form.

The following Drush command will list all available block entities.
```shell
drush ev 'print_r(\Drupal::configFactory()->listAll("block.block."));'
```

## Block - content entity
Content blocks, also known as custom blocks are configured on
`admin/structure/block/block-content` page. Actually they have little to do with
Drupal block system. These blocks are just content entities like node, user,
comment and so on. Their provider (Custom block module) also offers a plugin to
display them in blocks.

The primary way to display content blocks is like follows.
```twig
{{ drupal_entity('block_content', 'content_block_id') }}
```

Though it looks similar to rendering configuration entities (Section 2), you
should note two important distinctions.

Entity type is 'block_content' not 'block'.
Content block ID stands for an ID of respective content entity. This is a
numeric value that can be found in URL when editing custom block. Getting
content block IDs is as simple as executing a single SQL query.
```shell
drush sqlq 'SELECT id, info FROM block_content_field_data'
```

Since this method does not use block template (`block.html.twig`) you may need to
supply block subject and wrappers manually.
```twig
<div class="block">
  <h2>{{ 'Example'|t }}</h2>
  {{ drupal_entity('block_content', content_block_id) }}
</div>
```

Another way to accomplish this task is using block plugin (see Section 1).
```twig
{{ drupal_block('block_content:<uuid>', {label: 'Example'|t}) }}
```

Note that plugin ID in this case consists of entity type and entity UUID
separated by a colon.

It is also possible to create a configuration entity for this content block and
print it as described in [Configuration entity section](#block-configuration-entity).
