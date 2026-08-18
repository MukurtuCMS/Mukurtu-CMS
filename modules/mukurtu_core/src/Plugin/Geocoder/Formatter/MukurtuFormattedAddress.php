<?php

namespace Drupal\mukurtu_core\Plugin\Geocoder\Formatter;

use Drupal\geocoder\Plugin\Geocoder\Formatter\FormatterBase;
use Geocoder\Location;

/**
 * Provides a formatted address that includes state/province.
 *
 * @GeocoderFormatter(
 *   id = "mukurtu_formatted_address",
 *   name = "Mukurtu Formatted Address"
 * )
 */
class MukurtuFormattedAddress extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function format(Location $address): string {
    $parts = [];

    $street = trim(($address->getStreetNumber() ?? '') . ' ' . ($address->getStreetName() ?? ''));
    if ($street !== '') {
      $parts[] = $street;
    }
    if ($locality = $address->getLocality()) {
      $parts[] = $locality;
    }

    // Nominatim maps 'state' to admin level 1 and 'county' to level 2; when
    // a location has no state-level division, fall back to the county.
    $adminLevels = $address->getAdminLevels();
    if ($adminLevels->has(1)) {
      $parts[] = $adminLevels->get(1)->getName();
    }
    elseif ($adminLevels->has(2)) {
      $parts[] = $adminLevels->get(2)->getName();
    }

    if ($postalCode = $address->getPostalCode()) {
      $parts[] = $postalCode;
    }
    if (($country = $address->getCountry()) && $country->getName()) {
      $parts[] = $country->getName();
    }

    return implode(', ', $parts);
  }

}
