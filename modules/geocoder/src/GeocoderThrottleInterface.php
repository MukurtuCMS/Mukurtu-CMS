<?php

namespace Drupal\geocoder;

/**
 * Provides a geocoder throttle interface.
 */
interface GeocoderThrottleInterface {

  /**
   * Sleeps until the throttle rate is not reached anymore.
   *
   * @param string $key
   *   An identifier for the service where we send the requests.
   * @param array|null $throttle_info
   *   An associative array with:
   *   - period: in seconds
   *   - limit: number of requests allowed in the period
   *   or null not to limit the requests.
   *
   * @return void|null
   *   No return value.
   */
  public function waitForAvailability(string $key, array|null $throttle_info);

}
