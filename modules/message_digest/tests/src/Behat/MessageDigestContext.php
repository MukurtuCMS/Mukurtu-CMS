<?php

namespace Drupal\Tests\message_digest\Behat;

use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\message_digest\Traits\MessageDigestContextTrait;

/**
 * Example Behat step definitions for the Message Digest module.
 *
 * This class provides step definitions to interact with message digests.
 * Developers are encouraged to use this as an example for creating their own
 * step definitions that are tailored to the business language of their project.
 *
 * For example, a project that internally uses the terms "periodic mailing" and
 * "news items" instead of "message digest" and "messages" when communicating
 * with the business stakeholders can extend this class in their own context and
 * include the following:
 *
 * @code
 * /**
 *  * @Then the :interval periodic mailing for :username should contain the following items:
 *  *\/
 * public function assertDigestContains(TableNode $table, $interval, $username, $label = NULL, $entity_type = NULL) {
 *   return parent::assertDigestContains($table, $interval, $username, $label, $entity_type);
 * }
 * @endcode
 */
class MessageDigestContext extends RawDrupalContext {

  use MessageDigestContextTrait;

}
