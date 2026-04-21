<?php

namespace Drupal\geolocation;

use Drupal\Core\Plugin\PluginBase;

/**
 * Defines an interface for geolocation google geocoder country  plugins.
 */
abstract class GeocoderCountryFormattingBase extends PluginBase implements GeocoderCountryFormattingInterface {

  /**
   * {@inheritdoc}
   */
  public function format(array $atomics) {
    $address_elements = [
      'organization' => '',
      'addressLine1' => '',
      'addressLine2' => '',
      'locality' => '',
      'postalCode' => '',
      'administrativeArea' => '',
      'countryCode' => '',
    ];

    return $address_elements;
  }

}
