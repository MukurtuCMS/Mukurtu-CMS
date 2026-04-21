<?php

namespace Drupal\geofield\GeoPHP;

/**
 * Provides a geoPHP Wrapper class.
 */
class GeoPHPWrapper implements GeoPHPInterface {

  /**
   * {@inheritdoc}
   */
  public function version() {
    return \geoPHP::version();
  }

  /**
   * {@inheritdoc}
   */
  public function load(mixed $data = NULL, ?string $type = NULL): ?\Geometry {
    try {
      $geometry = call_user_func_array(['\geoPHP', 'load'], func_get_args());
      return $geometry instanceof \Geometry ? $geometry : NULL;
    }
    catch (\Exception $e) {
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getAdapterMap() {
    return call_user_func_array(['\geoPHP', 'getAdapterMap'], func_get_args());
  }

}
