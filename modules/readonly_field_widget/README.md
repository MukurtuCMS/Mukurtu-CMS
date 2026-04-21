This module provides a new field widget which shows a read-only version of a
field on a form for Drupal 8.x

The widget is available to use for any field type.

NOTE: The field must have a value for this widget to show - this can either be
a default value (set while the widget uses a form type widget or
programmatically) or from the saved value while on an entity edit form.

- Still shows up if the user doesn't have edit access to the field but they do
have view access.
- The formatter options for the field are available under the widget settings.
- Label position and hide/show option is available under the widget settings.
