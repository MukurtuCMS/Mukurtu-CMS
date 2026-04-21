<?php

namespace Drupal\geolocation_yandex\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\geolocation\Plugin\Field\FieldWidget\GeolocationMapWidgetBase;

/**
 * Plugin implementation of the 'geolocation_yandex' widget.
 *
 * @FieldWidget(
 *   id = "geolocation_yandex",
 *   label = @Translation("Geolocation Yandex Map"),
 *   field_types = {
 *     "geolocation"
 *   }
 * )
 */
class GeolocationYandexWidget extends GeolocationMapWidgetBase {

  /**
   * {@inheritdoc}
   */
  static protected $mapProviderId = 'yandex';


  /**
   * {@inheritdoc}
   */
  static protected $mapProviderSettingsFormId = 'yandex_settings';

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $settings = parent::defaultSettings();

    $settings[self::$mapProviderSettingsFormId]['map_features']['yandex_control_search'] = [
      'enabled' => TRUE,
      'weight' => -100,
    ];
    $settings[self::$mapProviderSettingsFormId]['map_features']['yandex_control_zoom']['enabled'] = TRUE;
    $settings[self::$mapProviderSettingsFormId]['map_features']['yandex_control_geolocation']['enabled'] = TRUE;

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function form(FieldItemListInterface $items, array &$form, FormStateInterface $form_state, $get_delta = NULL) {
    $element = parent::form($items, $form, $form_state, $get_delta);

    $element['#attributes']['data-widget-type'] = 'yandex';

    $element['#attached'] = BubbleableMetadata::mergeAttachments(
      $element['#attached'],
      [
        'library' => [
          'geolocation_yandex/widget.yandex',
        ],
      ]
    );

    return $element;
  }

}
