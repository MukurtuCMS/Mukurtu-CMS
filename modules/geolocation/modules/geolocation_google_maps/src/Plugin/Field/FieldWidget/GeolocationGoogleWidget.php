<?php

namespace Drupal\geolocation_google_maps\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\geolocation\Plugin\Field\FieldWidget\GeolocationMapWidgetBase;

/**
 * Plugin implementation of the 'geolocation_googlegeocoder' widget.
 *
 * @FieldWidget(
 *   id = "geolocation_googlegeocoder",
 *   label = @Translation("Geolocation Google Maps API - Geocoding and Map"),
 *   field_types = {
 *     "geolocation"
 *   }
 * )
 */
class GeolocationGoogleWidget extends GeolocationMapWidgetBase {

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
  public static function defaultSettings() {
    $settings = parent::defaultSettings();

    $settings[self::$mapProviderSettingsFormId]['map_features']['control_geocoder'] = [
      'enabled' => TRUE,
      'weight' => -100,
    ];
    $settings[self::$mapProviderSettingsFormId]['map_features']['control_recenter']['enabled'] = TRUE;
    $settings[self::$mapProviderSettingsFormId]['map_features']['control_locate']['enabled'] = TRUE;

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function form(FieldItemListInterface $items, array &$form, FormStateInterface $form_state, $get_delta = NULL) {
    $element = parent::form($items, $form, $form_state, $get_delta);

    $element['#attributes']['data-widget-type'] = 'google';

    $element['#attached'] = BubbleableMetadata::mergeAttachments(
      $element['#attached'],
      [
        'library' => [
          'geolocation_google_maps/widget.google',
        ],
      ]
    );

    return $element;
  }

}
