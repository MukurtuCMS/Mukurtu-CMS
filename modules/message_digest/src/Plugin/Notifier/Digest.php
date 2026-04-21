<?php

namespace Drupal\message_digest\Plugin\Notifier;

/**
 * Defines a derived message digest plugin.
 *
 * @Notifier(
 *   id = "message_digest",
 *   deriver = "\Drupal\message_digest\Plugin\Deriver\DigestDeriver",
 *   viewModes = {
 *     "mail_subject",
 *     "mail_body"
 *   }
 * )
 */
class Digest extends DigestBase {
}
