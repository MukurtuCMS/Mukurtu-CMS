<?php

/**
 * @file
 * Contains \MessageDigestSubContext.
 */

use Drupal\DrupalExtension\Context\DrupalSubContextBase;
use Drupal\DrupalExtension\Context\DrupalSubContextInterface;
use Drupal\message_digest\Traits\MessageDigestContextTrait;

/**
 * Behat step definitions for the Message Digest module.
 *
 * phpcs:disable Drupal.Commenting.Deprecated.DeprecatedWrongSeeUrlFormat
 *
 * @deprecated in message_digest:8.x-1.0 and is removed from
 *   message_digest:8.x-2.0. Subcontexts are deprecated in Behat Drupal
 *   Extension 4.x. Instead use MessageDigestContext and include it directly in
 *   your behat.yml file.
 * @see \Drupal\Tests\message_digest\Behat\MessageDigestContext
 * @see https://github.com/jhedstrom/drupalextension/issues/518
 */
class MessageDigestSubContext extends DrupalSubContextBase implements DrupalSubContextInterface {

  use MessageDigestContextTrait;

}
