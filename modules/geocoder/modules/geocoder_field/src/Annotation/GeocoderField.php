<?php

namespace Drupal\geocoder_field\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a geocoder field plugin annotation object.
 *
 * @Annotation
 */
class GeocoderField extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the geocoder field plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * A list of field types to be handled by this plugin.
   *
   * @var array
   */
  public $fieldTypes;

}
