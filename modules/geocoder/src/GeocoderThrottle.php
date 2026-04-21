<?php

namespace Drupal\geocoder;

use Stiphle\Storage\Process;
use Stiphle\Throttle\LeakyBucket;

/**
 * Provides a throttle mecanism for geocoder requests.
 */
class GeocoderThrottle implements GeocoderThrottleInterface {

  /**
   * The throttle mecanism.
   *
   * @var \Stiphle\Throttle\LeakyBucket
   */
  protected $throttle;

  /**
   * Constructs a new throttle service.
   *
   * The storage must be instantiated once and reused to work correctly.
   */
  public function __construct() {
    $this->throttle = new LeakyBucket();
    // @todo For now, we use a per-process storage, which means that requests
    // sent at the same time by another process (like another user on the
    // website) will be throttled separately, so that the actual limit of the
    // provider could still be reached.
    // In common use cases, it's not a problem because bulk geocoding is handled
    // by one process, such as a drush command.
    // But it could be improved by using a more shared and persistent storage
    // that would fit more use cases.
    $this->throttle->setStorage(new Process());
  }

  /**
   * {@inheritdoc}
   */
  public function waitForAvailability(string $key, array|null $throttle_info = []) {
    // Use throttle info if set.
    if (isset($throttle_info['limit']) && isset($throttle_info['period'])) {
      // The throttle mechanism uses milliseconds, so we convert the argument
      // and convert back the result as sleep() uses seconds.
      $time_to_wait = $this->throttle->throttle($key, $throttle_info['limit'], $throttle_info['period'] * 1000);
      sleep(ceil($time_to_wait / 1000));
    }
  }

}
