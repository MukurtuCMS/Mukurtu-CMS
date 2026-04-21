<?php

namespace Drupal\geocoder\Annotation;

/**
 * Defines a geocoder dumper plugin annotation object.
 *
 * @Annotation
 */
class GeocoderDumper extends GeocoderPluginBase {

  /**
   * The plugin handler.
   *
   * This is the fully qualified class name of the plugin handler.
   *
   * @var string
   */
  public $handler = NULL;

}
