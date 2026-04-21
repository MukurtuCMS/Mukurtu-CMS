<?php

namespace Drupal\geofield\Plugin\GeofieldBackend;

use Drupal\geofield\Plugin\GeofieldBackendBase;

/**
 * Default backend for Geofield.
 *
 * Definition of a default Geofield Backend for storing values in WKT Format.
 *
 * @GeofieldBackend(
 *   id = "geofield_backend_default",
 *   admin_label = @Translation("Default (WKT)"),
 *   description = @Translation("Default Geofield Backend for storing values in WKT Format")
 * )
 */
class GeofieldBackendDefault extends GeofieldBackendBase {

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
      $output = $geom->out('wkt');
    }
    return $output;
  }

}
