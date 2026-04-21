<?php

namespace Drupal\geolocation_google_maps;

use Drupal\geolocation\GeocoderCountryFormattingBase;
use Drupal\geolocation\GeocoderCountryFormattingInterface;

/**
 * Defines an interface for geolocation google geocoder country  plugins.
 */
abstract class GoogleCountryFormattingBase extends GeocoderCountryFormattingBase implements GeocoderCountryFormattingInterface {

  /**
   * {@inheritdoc}
   */
  public function format(array $atomics) {
    $address_elements = parent::format($atomics);

    if (
      isset($atomics['streetNumber'])
      && isset($atomics['route'])
      && $atomics['streetNumber']
      && $atomics['route']
    ) {
      $address_elements['addressLine1'] = $atomics['streetNumber'] . ' ' . $atomics['route'];
    }
    elseif (
      isset($atomics['route'])
      && $atomics['route']
    ) {
      $address_elements['addressLine1'] = $atomics['route'];
    }
    elseif (
      isset($atomics['premise'])
      && $atomics['premise']
    ) {
      $address_elements['addressLine1'] = $atomics['premise'];
    }

    if (
      isset($atomics['locality'])
      && isset($atomics['postalTown'])
      && $atomics['locality']
      && $atomics['postalTown']
      && $atomics['locality'] !== $atomics['postalTown']
    ) {
      $address_elements['addressLine2'] = $atomics['locality'];
    }
    elseif (
      empty($atomics['locality'])
      && isset($atomics['neighborhood'])
      && $atomics['neighborhood']
    ) {
      $address_elements['addressLine2'] = $atomics['neighborhood'];
    }

    if (
      isset($atomics['locality'])
      && $atomics['locality']
    ) {
      $address_elements['locality'] = $atomics['locality'];
    }
    elseif (
      isset($atomics['postalTown'])
      && $atomics['postalTown']
    ) {
      $address_elements['locality'] = $atomics['postalTown'];
    }
    elseif (
      empty($atomics['locality'])
      && isset($atomics['political'])
      && $atomics['political']
    ) {
      $address_elements['locality'] = $atomics['political'];
    }

    if (
      isset($atomics['postalCode'])
      && $atomics['postalCode']
    ) {
      $address_elements['postalCode'] = $atomics['postalCode'];
    }

    if (
      isset($atomics['postalCode'])
      && $atomics['postalCode']
    ) {
      $address_elements['postalCode'] = $atomics['postalCode'];
    }

    if (
      isset($atomics['administrativeArea'])
      && $atomics['administrativeArea']
    ) {
      $address_elements['administrativeArea'] = $atomics['administrativeArea'];
    }

    if (
      isset($atomics['countryCode'])
      && $atomics['countryCode']
    ) {
      $address_elements['countryCode'] = $atomics['countryCode'];
    }

    return $address_elements;
  }

}
