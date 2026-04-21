<?php

namespace Drupal\geocoder_geofield\Geocoder\Provider;

/**
 * Provides a file handler to be used by 'kmlfile' plugin.
 */
class KMLFile extends AbstractGeometryProvider {

  /**
   * Geophp Type.
   *
   * @var string
   */
  protected $geophpType = 'kml';

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return 'kmlfile';
  }

}
