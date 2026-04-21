<?php

namespace Drupal\geocoder_field\Annotation;

use Drupal\geocoder\Annotation\GeocoderPluginBase;

/**
 * Defines a geocoder preprocessor plugin annotation object.
 *
 * @Annotation
 */
class GeocoderPreprocessor extends GeocoderPluginBase {

  /**
   * The field types where this plugin applies.
   *
   * @var array
   */
  public $fieldTypes;

  /**
   * The weight of this preprocessor.
   *
   * Many preprocessors are called to pre-process the same field. This value
   * can determine an order in which the preprocessors are called.
   *
   * @var int
   */
  public $weight = 0;

}
