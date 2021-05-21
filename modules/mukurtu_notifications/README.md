# Description

The Mukurtu Notifications module provides integration with the 'message' module stack and configures the flag module to allow users to follow and be notified of events.

## Events
Mukurtu records certain Drupal events as messages. Messages have been extend to use protocols for access control. The following is the list of events Mukurtu tracks:

| Event | Message Template Name | Message Template Machine Name |
|---|---|---|
| Node insert |Mukurtu Single Node Insert | mukurtu_single_node_insert |
| Node update | Mukurtu Single Node Update | mukurtu_single_node_update |
| Node delete | Mukurtu Single Node Delete | mukurtu_single_node_delete |
| Batch Import | Mukurtu Batch Import | mukurtu_batch_import |

<br/>

Drupal administrators can alter the message templates at `/admin/structure/message`.

Events are displayed in the activity log on the dashboard.

<br/>

## Notifications
Notifications are messages that are pushed to users via e-mail or other communication methods.

The following are flags that can be toggled per user to control notifications.

| Flag | Machine Name | Message Template(s) | Description |
| --- | --- | --- | --- |
| Mukurtu Follow Content | mukurtu_follow_content| Mukurtu Single Node Update (mukurtu_single_node_update) | Notify on node update, new comments |
| Mukurtu Follow Protocol | mukurtu_follow_protocol | Mukurtu New Item in Protocol (mukurtu_new_item_in_protocol) | Notify on new items added to protocol |
| Mukurtu Follow Community | mukurtu_follow_community | Mukurtu New Item in Community (mukurtu_new_item_in_community) | Notify on new, accessible items added to the community |
| Mukurtu Follow Collection | mukurtu_follow_collection | Mukurtu New Item in Collection (mukurtu_new_item_in_collection) |Notify on new items added to a collection, new comments |
| Mukurtu Follow Language | mukurtu_follow_language | | Notify on new dictionary words added to the language |

<br/>
Notifications are also sent to the content's author for certain events:

* New comment on content

Drupal administrators can alter the flags at `/admin/structure/flags`.
