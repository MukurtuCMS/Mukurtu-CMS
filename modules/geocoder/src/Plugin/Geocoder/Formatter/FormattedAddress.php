<?php

namespace Drupal\geocoder\Plugin\Geocoder\Formatter;

use Geocoder\Location;

/**
 * Provides a Default Formatted Address plugin.
 *
 * @GeocoderFormatter(
 *   id = "default_formatted_address",
 *   name = "Default Formatted Address"
 * )
 */
class FormattedAddress extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function format(Location $address) {
    $formatted_address = $this->formatter->format($address, '%S %n, %z %L %c, %C');
    // Clean the address, from double whitespaces, ending/starting commas, etc.
    $this->cleanFormattedAddress($formatted_address);
    return $formatted_address;
  }

}
