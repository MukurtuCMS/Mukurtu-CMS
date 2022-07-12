<?php

namespace Drupal\mukurtu_core\Plugin\Field\FieldWidget;

use Drupal\leaflet\Plugin\Field\FieldWidget\LeafletDefaultWidget;

/**
 * Widget implementation of the 'geofield_mukurtu' widget.
 *
 * @FieldWidget(
 *   id = "geofield_mukurtu",
 *   label = @Translation("Mukurtu Leaflet (GeoJSON)"),
 *   field_types = {
 *     "geofield"
 *   }
 * )
 */
class GeofieldMukurtuWidget extends LeafletDefaultWidget {
  /**
   * Return the specific Geofield Backend Value.
   *
   * Use GeoJSON.
   *
   * @param mixed|null $value
   *   The data to load.
   *
   * @return mixed|null
   *   The specific backend format value.
   */
  protected function geofieldBackendValue($value) {
    return $value;
  }

}
