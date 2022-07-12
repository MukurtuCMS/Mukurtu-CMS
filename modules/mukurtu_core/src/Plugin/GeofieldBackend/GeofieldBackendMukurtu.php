<?php

namespace Drupal\mukurtu_core\Plugin\GeofieldBackend;

use Drupal\geofield\Plugin\GeofieldBackendBase;

/**
 * Mukurtu backend for Geofield.
 *
 * Definition of the Mukurtu Geofield Backend for storing values in GeoJSON Format.
 *
 * @GeofieldBackend(
 *   id = "geofield_backend_mukurtu",
 *   admin_label = @Translation("Mukurtu GeoJSON"),
 *   description = @Translation("Mukurtu Geofield Backend for storing values in GeoJSON")
 * )
 */
class GeofieldBackendMukurtu extends GeofieldBackendBase {

  /**
   * {@inheritdoc}
   */
  public function schema() {
    return [
      'type' => 'blob',
      'size' => 'big',
      'not null' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function save($geometry) {
    $output = NULL;
    if ($geom = $this->geoPhpWrapper->load($geometry)) {
      $output = $geom->out('json');
    }

    return $output;
  }

}
