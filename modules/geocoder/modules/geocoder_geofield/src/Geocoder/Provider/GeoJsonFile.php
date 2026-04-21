<?php

namespace Drupal\geocoder_geofield\Geocoder\Provider;

/**
 * Provides a file handler to be used by 'geojsonfile' plugin.
 */
class GeoJsonFile extends AbstractGeometryProvider {

  /**
   * Geophp Type.
   *
   * @var string
   */
  protected $geophpType = 'geojson';

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return 'geojsonfile';
  }

}
