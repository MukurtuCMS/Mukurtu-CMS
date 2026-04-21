# Message Subscribe Example

This code is intended to be used as the initial implementation of the complete
 Message stack. The following modules are required:

- [Message](https://www.drupal.org/project/message)
- [Message Notify](https://www.drupal.org/project/message_notify)
- [Message Subscribe](https://www.drupal.org/project/message_subscribe)
- [Message UI](https://www.drupal.org/project/message_ui)

This code starts with some of the example modules in the Message stack, changing
 template and field names to remove the word "Example" since this is intended to
  be the starting point for an actual implementation, not just an example.

**Note: The assumption is that this code will be used on a site that has no
 previous use of the Message stack, so no regard is given to the possibility
 that there might be conflicting names for message templates and fields.**

**Note: Several patches to Message Subscribe are suggested.**

Add the following to your composer.json to add necessary patches:

```
"patches": {
  "drupal/message_subscribe": {
    "Issue #2928789: Fatal exception with flag module": "https://www.drupal.org/files/issues/2019-12-15/account_id_2928789_0.patch",
    "Issue #3101137: Fix endless loop": "https://www.drupal.org/files/issues/2019-12-15/3101137-fix-endless-loop.patch,
    "Issue #3101141: Message Subscribe Email removes all emails": "https://www.drupal.org/files/issues/2019-12-15/3101141-check-email-flag_0.patch"
  }
},
```


## To use:

- Clone this repo into the custom modules directory of a Drupal site.
- Make sure all the above modules are available by running `composer require` on
 each of them.
- Enable this module and all the Message modules except the example modules
 (which will conflict with code included here). You can just enable this module
 since it will install all the required modules:
 `drush en message_subscribe_example`
- Navigate to `admin/structure/flags` to enable the flags you want to use for
 subscriptions.
- Edit the display mode for each content type to position the subscription flags
 where you want them.
- Subscribe to some content.
- Create/edit content and add comments to it.
- Run cron to trigger queued messages and emails.
- Navigate to `admin/content/messages` to see the generated messages.

## To customize:

- Navigate to `admin/config/message/message` to adjust message settings.
- Navigate to `admin/structure/message` to edit and change message templates.

Review the code in `message_implementation.module` to see what hooks are being
 used to generate messages. You can alter them later as needed. Lots of examples
 are provided, you won't use all of them, so remove and change as you
 like.
