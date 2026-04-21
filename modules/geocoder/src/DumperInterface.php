<?php

namespace Drupal\geocoder;

use Geocoder\Location;

/**
 * Provides an interface for geocoder dumper plugins.
 *
 * Dumpers are plugins that knows to format geographical data into an industry
 * standard format.
 */
interface DumperInterface {

  /**
   * Dumps the argument into a specific format.
   *
   * @param \Geocoder\Location $location
   *   The address to be formatted.
   *
   * @return string
   *   The formatted address.
   */
  public function dump(Location $location);

}
