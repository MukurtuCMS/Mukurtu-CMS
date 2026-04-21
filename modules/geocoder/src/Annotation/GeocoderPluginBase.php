<?php

namespace Drupal\geocoder\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a base class for geocoder plugin annotations.
 */
class GeocoderPluginBase extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the geocoder plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $name;

}
