<?php

namespace Drupal\geolocation\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a GeocoderCountryFormatting annotation object.
 *
 * @see \Drupal\geolocation\GeocoderCountryFormattingManager
 * @see plugin_api
 *
 * @Annotation
 */
class GeocoderCountryFormatting extends Plugin {

  /**
   * The ID.
   *
   * @var string
   */
  public $id;

  /**
   * The country code.
   *
   * @var string
   */
  public $countryCode;

  /**
   * The geocoder ID.
   *
   * @var string
   */
  public $geocoder;

}
