<?php

namespace Drupal\geolocation_leaflet\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\geolocation\Plugin\Field\FieldWidget\GeolocationMapWidgetBase;

/**
 * Plugin implementation of the 'geolocation_leaflet' widget.
 *
 * @FieldWidget(
 *   id = "geolocation_leaflet",
 *   label = @Translation("Geolocation Leaflet - Geocoding and Map"),
 *   field_types = {
 *     "geolocation"
 *   }
 * )
 */
class GeolocationLeafletWidget extends GeolocationMapWidgetBase {

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
  public static function defaultSettings() {
    $settings = parent::defaultSettings();

    $settings[self::$mapProviderSettingsFormId]['map_features']['leaflet_control_geocoder'] = [
      'enabled' => TRUE,
      'weight' => -100,
    ];

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function form(FieldItemListInterface $items, array &$form, FormStateInterface $form_state, $get_delta = NULL) {
    $element = parent::form($items, $form, $form_state, $get_delta);

    $element['#attached'] = BubbleableMetadata::mergeAttachments(
      $element['#attached'],
      [
        'library' => [
          'geolocation_leaflet/widget.leaflet',
        ],
      ]
    );

    return $element;
  }

}
