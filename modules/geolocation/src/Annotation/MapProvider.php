<?php

namespace Drupal\geolocation\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a MapProvider annotation object.
 *
 * @see \Drupal\geolocation\MapProviderManager
 * @see plugin_api
 *
 * @Annotation
 */
class MapProvider extends Plugin {

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
