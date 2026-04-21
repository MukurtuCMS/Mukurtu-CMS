<?php

namespace Drupal\geolocation_geometry\Plugin\Field\FieldFormatter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\geolocation\Plugin\Field\FieldFormatter\GeolocationMapFormatterBase;

/**
 * Plugin implementation of the 'geometry' formatter.
 *
 * @FieldFormatter(
 *   id = "geolocation_geometry",
 *   module = "geolocation",
 *   label = @Translation("Geolocation Geometry Formatter - Map"),
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
class GeolocationGeometryMapFormatter extends GeolocationMapFormatterBase {

  /**
   * {@inheritdoc}
   */
  static protected $dataProviderId = 'geolocation_geometry';

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    unset($element['set_marker']);
    // @todo re-enable?
    unset($element['title']);
    unset($element['info_text']);
    unset($element['replacement_patterns']);

    return $element;
  }

}
