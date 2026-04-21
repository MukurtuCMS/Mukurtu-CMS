<?php

namespace Drupal\geofield\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for Geofield backends.
 *
 * Modules implementing this interface may want to extend GeofieldBackendBase
 * class, which provides default implementations of each method.
 *
 * @see \Drupal\geofield\Annotation\GeofieldBackend
 * @see \Drupal\geofield\Plugin\GeofieldBackendBase
 * @see \Drupal\geofield\Plugin\GeofieldBackendManager
 * @see plugin_api
 */
interface GeofieldBackendPluginInterface extends PluginInspectionInterface {

  /**
   * Provides the specific database schema for the specific backend.
   *
   * @return array
   *   The schema value array.
   */
  public function schema();

  /**
   * Saves the Geo value into the Specific Backend Format.
   *
   * @param mixed|null $geometry
   *   The Geometry to save.
   *
   * @return mixed|null
   *   The specific backend format value.
   */
  public function save($geometry);

}
