<?php

namespace Drupal\geolocation\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a MapFeature annotation object.
 *
 * @see \Drupal\geolocation\MapFeatureManager
 * @see plugin_api
 *
 * @Annotation
 */
class MapFeature extends Plugin {

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

  /**
   * The map type supported by this MapFeature.
   *
   * @var string
   */
  public $type;

}
