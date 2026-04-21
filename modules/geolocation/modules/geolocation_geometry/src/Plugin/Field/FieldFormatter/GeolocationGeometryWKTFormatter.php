<?php

namespace Drupal\geolocation_geometry\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the 'geolocation_latlng' formatter.
 *
 * @FieldFormatter(
 *   id = "geolocation_geometry_wkt",
 *   label = @Translation("Geolocation Geometry WKT"),
 *   field_types = {
 *     "geolocation_geometry_geometry",
 *     "geolocation_geometry_geometrycollection",
 *     "geolocation_geometry_point",
 *     "geolocation_geometry_linestring",
 *     "geolocation_geometry_polygon",
 *     "geolocation_geometry_multipoint",
 *     "geolocation_geometry_multilinestring",
 *     "geolocation_geometry_multipolygon",
 *   }
 * )
 */
class GeolocationGeometryWKTFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];

    foreach ($items as $delta => $item) {
      $element[$delta] = [
        '#markup' => $item->wkt,
      ];
    }

    return $element;
  }

}
