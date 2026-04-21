<?php

/**
 * @file
 * Test hook implementation fixture.
 *
 * @see \Drupal\Tests\message_subscribe\Unit\SubscribersTest
 */

use Drupal\message_subscribe\Subscribers\DeliveryCandidate;

if (!function_exists('foo_message_subscribe_get_subscribers')) {

  /**
   * Implements hook_message_subscribe_get_subscribers().
   */
  function foo_message_subscribe_get_subscribers() {
    return [
      1 => new DeliveryCandidate([], [], 1),
      2 => new DeliveryCandidate([], [], 2),
      4 => new DeliveryCandidate([], [], 4),
      7 => new DeliveryCandidate([], [], 7),
    ];
  }

}
