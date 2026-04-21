# Gin Layout Builder

This module brings the Gin admin theme to the layout builder.


## Requirements

This module requires the following modules and themes:
- [Gin](https://www.drupal.org/project/gin)
- [Gin Toolbar](https://www.drupal.org/project/gin_toolbar)


## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).


## Configuration

The configuration page (`/admin/config/gin_lb/settings`) provides some settings.


## Troubleshooting & FAQ


### Conflicts with your frontend theme

To avoid conflicts with your frontend theme, the module adds a CSS prefix `glb-`
to all layout builder styles.

If your theme uses theme suggestions there could be conflicts with the module
theme suggestions from "gin layout builder".

To avoid these conflicts add the following code to your
`hook_theme_suggestions_alter` inside your theme.

```php
/**
 * Implements hook_theme_suggestions_alter().
 */
function MYTHEME_theme_suggestions_alter(array &$suggestions, array $variables, $hook) {
  if (isset($variables['element']['#gin_lb_form'])) {
    return;
  }
}
```
