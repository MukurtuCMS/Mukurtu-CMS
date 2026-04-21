<?php

namespace Drupal\geocoder\Plugin\Geocoder\Formatter;

use Geocoder\Location;

/**
 * Provides an interface for geocoder formatter plugins.
 *
 * Formatters are plugins that can reformat address components into custom
 * formatted string.
 */
interface FormatterInterface {

  /**
   * Dumps the argument into a specific format.
   *
   * @param \Geocoder\Location $address
   *   The address to be formatted.
   *   This (might but) is not referring to Geocoder\Location for backport
   *   compatibility with 8.x-2.x version. Third party modules might have
   *   already created their own custom formatters.
   *
   * @return string
   *   The formatted address.
   */
  public function format(Location $address);

}
