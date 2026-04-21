<?php

namespace Drupal\geolocation_geometry_data\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a GeolocationGeometryData annotation object.
 *
 * @Annotation
 */
class GeolocationGeometryData extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The name of the MapProvider.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $name;

  /**
   * The description of the MapProvider.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

}
