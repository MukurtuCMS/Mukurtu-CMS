<?php

namespace Drupal\geolocation_leaflet\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\geolocation\Plugin\Field\FieldWidget\GeolocationGeometryWidgetBase;

/**
 * Plugin implementation of 'geolocation_geometry_widget_leaflet' widget.
 *
 * @FieldWidget(
 *   id = "geolocation_geometry_widget_leaflet",
 *   label = @Translation("Geolocation Geometry Leaflet - GeoJSON"),
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
class GeolocationGeometryWidgetLeaflet extends GeolocationGeometryWidgetBase {

  /**
   * {@inheritdoc}
   */
  static protected $mapProviderId = 'leaflet';

  /**
   * {@inheritdoc}
   */
  static protected $mapProviderSettingsFormId = 'leaflet_settings';

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $element['#attached'] = [
      'library' => [
        'geolocation_leaflet/widget.leaflet.geojson',
      ],
    ];

    return $element;
  }

}
