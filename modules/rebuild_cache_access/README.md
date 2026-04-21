# Rebuild Cache Access

`composer require drupal/rebuild_cache_access`

This module provides a new permission: `rebuild cache access`. Every role with
this permission set will have a nice and big Rebuild Cache admin toolbar button.
Clicking this button simply fires [`drupal_flush_all_caches()`][1]. That's all.

Note: Normally it should be sufficient that all relevant caches are flushed
automatically when changes to content are made. Nevertheless in real world it
sometimes may be necessary to have other roles than the ones with `administer
site configuration` permission being able to rebuild the caches.

![Rebuild cache toolbar button screenshot][2]

[1]: https://api.drupal.org/api/drupal/core%21includes%21common.inc/function/drupal_flush_all_caches/8.5.x
[2]: https://www.drupal.org/files/project-images/drupal_rebuild_cache_access.png

## Requirements

```yaml
dependencies:
  - drupal:toolbar
```

## Installation

* Install as you would normally install a contributed drupal module.
  See: https://drupal.org/documentation/install/modules-themes/modules-8
  for further information.

## Configuration

* Configure the user permissions in Administration > People > Permissions:
  > Rebuild Cache Access > Rebuild Cache
