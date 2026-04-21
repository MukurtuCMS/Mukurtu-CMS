<?php

namespace Drupal\geofield;

/**
 * Helper class to map DMS Point structure.
 */
class DmsPoint {

  /**
   * The longitude component.
   *
   * @var array
   */
  protected $lon;

  /**
   * The latitude component.
   *
   * @var array
   */
  protected $lat;

  /**
   * DmsPoint constructor.
   *
   * @param array $lon
   *   The longitude components.
   * @param array $lat
   *   The latitude components.
   */
  public function __construct(array $lon, array $lat) {
    $this->lat = $lat;
    $this->lon = $lon;
  }

  /**
   * Retrieves an object property.
   *
   * @param string $property
   *   The property to get.
   *
   * @return array|null
   *   The property if exists, otherwise NULL.
   */
  public function get($property) {
    return $this->{$property} ?? NULL;
  }

  /**
   * Get the Longitude property.
   *
   * @return array
   *   The lon components.
   */
  public function getLon() {
    return $this->lon;
  }

  /**
   * Set the Longitude property.
   *
   * @param array $lon
   *   The lon components.
   */
  public function setLon(array $lon) {
    $this->lon = $lon;
  }

  /**
   * Get the Latitude property.
   *
   * @return array
   *   The lat components.
   */
  public function getLat() {
    return $this->lat;
  }

  /**
   * Set the Latitude property.
   *
   * @param array $lat
   *   The lat components.
   */
  public function setLat(array $lat) {
    $this->lat = $lat;
  }

}
