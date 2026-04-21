# Dashboards

This module is heavily inspired from [Mini Layouts](https://drupal.org/project/mini_layouts).

## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).


## Features

- `>= 2.0.1` Gin support
- Create dashboards with layout builder.
- Possibility personalize dashboards per user.
- All dashboards are config entities and exportable.
- Add a new plugin base for dashboards components.
- Integration into drupal toolbar.
- Chart integration for dashboards plugins.
- Example plugins for statistics.
- Plugins for display embed views.
- RSS Plugin

## Known issues

Apply following patch to show dashboards in admin theme.
Only Drupal 8.8
[#3005403: Cannot delete or edit a block that is placed in a section of the layout_builder](https://www.drupal.org/project/drupal/issues/3005403)

There is a issue with Layout builder Restrictions < 2.2 .
Layouts could not be saved if this module is enabled.
[#3097098: Unable to save layout changes for Mini Layouts section storage plugins](https://www.drupal.org/project/layout_builder_restrictions/issues/3097098)
Apply Patch 11, so layout could be saved.

With version 2.2 all should work correct.

## Maintainers

- Erik Seifert - [Erik Seifert](https://www.drupal.org/u/sun)
