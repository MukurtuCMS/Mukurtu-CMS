<?php

namespace Drupal\geolocation\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a geocoder annotation object.
 *
 * @see \Drupal\geolocation\GeocoderManager
 * @see plugin_api
 *
 * @Annotation
 */
class Geocoder extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The name of the geocoder.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $name;

  /**
   * The description of the geocoder.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

  /**
   * Can the geocoder retrieve coordinates.
   *
   * @var bool
   */
  public $locationCapable;

  /**
   * Can the geocoder retrieve boundaries.
   *
   * @var bool
   */
  public $boundaryCapable;

  /**
   * Can the geocoder be used in the frontend.
   *
   * @var bool
   */
  public $frontendCapable;

  /**
   * Can the geocoder perform reverse geocoding.
   *
   * @var bool
   */
  public $reverseCapable;

}
