<?php

namespace Drupal\geolocation\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a LocationInput annotation object.
 *
 * @see \Drupal\geolocation\LocationInputManager
 * @see plugin_api
 *
 * @Annotation
 */
class LocationInput extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The name of the LocationInput.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $name;

  /**
   * The description of the LocationInput.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

}
