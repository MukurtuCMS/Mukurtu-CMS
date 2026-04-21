<?php

namespace Drupal\geocoder_geofield\Geocoder\Provider;

use Geocoder\Exception\LogicException;
use Geocoder\Exception\UnsupportedOperation;

/**
 * Provides an abstract file handler to be used by GeoPHP Wrapper.
 */
abstract class AbstractGeometryProvider implements GeometryProviderInterface {

  /**
   * Geophp interface.
   *
   * @var \Drupal\geofield\GeoPHP\GeoPHPInterface
   */
  protected $geophp;

  /**
   * Geophp Type.
   *
   * @var string
   */
  protected $geophpType = '';

  /**
   * {@inheritdoc}
   */
  public function __construct() {
    $this->geophp = \Drupal::service('geofield.geophp');
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return 'geophp_provider';
  }

  /**
   * {@inheritdoc}
   */
  public function geocode($filename): \Geometry {
    if (file_exists($filename)) {
      $geophp_string = file_get_contents($filename);
      /** @var \Geometry $geometry */
      $geometry = $this->geophp->load($geophp_string, $this->geophpType);
      if (!empty($geometry->components) || $geometry instanceof \Geometry) {
        return $geometry;
      }
    }
    throw new LogicException(sprintf('Could not find %s data in file: "%s".', $this->geophpType, basename($filename)));
  }

  /**
   * {@inheritdoc}
   */
  public function reverse($latitude, $longitude) {
    throw new UnsupportedOperation(sprintf('The %s plugin is not able to do reverse geocoding.', $this->geophpType));
  }

}
