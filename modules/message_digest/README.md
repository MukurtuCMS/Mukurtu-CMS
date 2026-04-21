CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Maintainers


INTRODUCTION
------------

Message Digest is a plugin to the Message module which adds the ability to
send email messages in a digest format every day or week, rather than on demand.
This is useful for sites with too much activity for on demand messages to be
useful, or with users who prefer to not get multiple emails a day.

A field titled 'How often would you like to receive email notifications?' is
added to the user's profile via the "Message Digest UI" module
(included with Message Digest)., allowing the user to select their desired email
frequency.

 * For a full description of the module, visit the project page:
   https://www.drupal.org/project/message_digest

 * To submit bug reports and feature suggestions, or to track changes:
   https://www.drupal.org/project/issues/message_digest


REQUIREMENTS
------------

This module requires the following module:

* Message - https://www.drupal.org/project/message
* Message Notify - https://www.drupal.org/project/message_notify

The Message Digest UI enables users to choose their notification preferences
via a UI, It requires:

* Message Subscribe Email Frequency -
https://www.drupal.org/project/message_subscribe_email_frequency

INSTALLATION
------------

Install the Message Digest module as you would normally install a contributed
Drupal module. Visit https://www.drupal.org/node/1897420 for further
information.


CONFIGURATION
-------------

This module provides two new notification plugins -- "digest_day" and
"digest_week". Just use one of those as the $notifier_name for
message_notify_send_message().

Doing so will prevent the message from sending an immediate notification, and
instead will add it to the daily or weekly digest for that user which will be
emailed on cron every day or week.

If you'd like to use a different interval than "day" or "week", doing so is as
easy as creating a new plugin with a custom interval. Take a look at this
module's "digest_day" or "digest_week" plugin (under plugins/notifer/digest_*)
and you'll see that all it needs to do is override the getInterval() function
with some new interval string that will be accepted by strtotime().


MAINTAINERS
-----------

 * Jonathan Hedstrom (jhedstrom) - https://www.drupal.org/u/jhedstrom
 * Mike Potter (mpotter) - https://www.drupal.org/u/mpotter
 * Mike Crittenden (mcrittenden) - https://www.drupal.org/u/mcrittenden
