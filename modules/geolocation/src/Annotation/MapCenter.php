<?php

namespace Drupal\geolocation\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a MapCenter annotation object.
 *
 * @see \Drupal\geolocation\MapCenterManager
 * @see plugin_api
 *
 * @Annotation
 */
class MapCenter extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The name of the MapCenter.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $name;

  /**
   * The description of the MapCenter.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

}
