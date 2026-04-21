# Migrating to Twig Tweak 3.x

Twig Tweak 3.x branch is mainly API compatible with 2.x
Below are known BC breaks that may require updating your Twig templates.

## Dependencies
Twig Tweak 3.x requires Drupal 9, Twig 2 and PHP 7.3.

## Rendering entities

### Entity ID is now required
Entity ID parameter in `drupal_entity()` and `drupal_field()` functions is now
mandatory. Previously it was possible to load entities from current route by
omitting entity ID parameter. However, that was making Twig templates coupled
with routes and could cause caching issues.

Before:
```twig
{{ drupal_entity('node', null, 'teaser') }}
```

After:
```twig
{{ drupal_entity('node', node.id, 'teaser') }}
```

In case a template does not contain a variable with entity object you may
prepare it in a preprocess hook.
```php
/**
 * Implements hook_preprocess_page().
 */
function preprocess_page(array &$variables): void {
  $variables['entity'] = \Drupal::routeMatch()->getParameter('entity_type');
}
```

### Default view mode has changed
The view mode parameter in `drupal_field()` has changed from `default` to `full`. If you are using `drupal_field()` without specifying a view mode, you should update your templates to specify the `default` one.

Before:
```
{{ drupal_field('field_name', 'node', node.id) }}
```

After:
```
{{ drupal_field('field_name', 'node', node.id, 'default') }}
```

## Rendering blocks
`drupal_block()` now moves attributes provided by block plugin to the outer
element. This may break some CSS rules.

Before:
```html
<div>
  <div class="from-block-plugin">Block content</div>
</div>
```

After:
```html
<div class="from-block-plugin">
  <div>Block content</div>
</div>
```

See https://www.drupal.org/node/3068078 for details.
