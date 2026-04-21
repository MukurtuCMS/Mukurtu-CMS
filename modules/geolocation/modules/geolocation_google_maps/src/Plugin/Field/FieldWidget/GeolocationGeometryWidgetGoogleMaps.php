<?php

namespace Drupal\geolocation_google_maps\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\geolocation\Plugin\Field\FieldWidget\GeolocationGeometryWidgetBase;

/**
 * Plugin implementation of 'geolocation_geometry_widget_google_maps' widget.
 *
 * @FieldWidget(
 *   id = "geolocation_geometry_widget_google_maps",
 *   label = @Translation("Geolocation Geometry Google Maps API - GeoJSON"),
 *   field_types = {
 *     "geolocation_geometry_point",
 *     "geolocation_geometry_multipoint",
 *     "geolocation_geometry_linestring",
 *     "geolocation_geometry_multilinestring",
 *     "geolocation_geometry_polygon",
 *     "geolocation_geometry_multipolygon",
 *     "geolocation_geometry_geometry",
 *     "geolocation_geometry_geometrycollection"
 *   }
 * )
 */
class GeolocationGeometryWidgetGoogleMaps extends GeolocationGeometryWidgetBase {

  /**
   * {@inheritdoc}
   */
  static protected $mapProviderId = 'google_maps';

  /**
   * {@inheritdoc}
   */
  static protected $mapProviderSettingsFormId = 'google_map_settings';

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $element['#attached'] = [
      'library' => [
        'geolocation_google_maps/widget.google_maps.geojson',
      ],
    ];

    return $element;
  }

}
