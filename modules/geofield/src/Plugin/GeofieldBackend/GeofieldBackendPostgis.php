<?php

namespace Drupal\geofield\Plugin\GeofieldBackend;

use Drupal\geofield\Plugin\GeofieldBackendBase;

/**
 * PostgreSQL/PostGIS Backend for Geofield.
 *
 * @GeofieldBackend(
 *   id = "geofield_backend_postgis",
 *   admin_label = @Translation("PostGIS Geometry"),
 *   description = @Translation("Geofield Backend storing values in EWKB Format, suitable for PostgreSQL/PostGIS (needs PostGis enabled)")
 * )
 */
class GeofieldBackendPostgis extends GeofieldBackendBase {

  /**
   * {@inheritdoc}
   */
  public function schema() {
    return [
      'type' => 'blob',
      'not null' => FALSE,
      'pgsql_type' => 'geometry',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function save($geometry) {
    $geom = $this->geoPhpWrapper->load($geometry);
    $unpacked = unpack('H*', $geom->out('ewkb'));
    return $unpacked[1];
  }

}
