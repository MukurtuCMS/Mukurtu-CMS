<?php

namespace Drupal\geolocation_yandex\Plugin\geolocation\MapProvider;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\geolocation\MapProviderBase;

/**
 * Provides Yandex Maps API.
 *
 * @MapProvider(
 *   id = "yandex",
 *   name = @Translation("Yandex Maps"),
 *   description = @Translation("Yandex support."),
 * )
 */
class Yandex extends MapProviderBase {

  /**
   * Yandex API Url.
   *
   * @var string
   */
  public static $apiBaseUrl = 'https://api-maps.yandex.ru/2.1/';

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings(): array {
    return array_replace_recursive(
      parent::getDefaultSettings(),
      [
        'zoom' => 10,
        'min_zoom' => 0,
        'max_zoom' => 20,
        'height' => '400px',
        'width' => '100%',
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings(array $settings): array {
    $settings = parent::getSettings($settings);

    $settings['zoom'] = (int) $settings['zoom'];

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsSummary(array $settings): array {
    $settings = array_replace_recursive(
      self::getDefaultSettings(),
      $settings
    );
    $summary = parent::getSettingsSummary($settings);
    $summary[] = $this->t('Zoom level: @zoom', ['@zoom' => $settings['zoom']]);
    $summary[] = $this->t('Height: @height', ['@height' => $settings['height']]);
    $summary[] = $this->t('Width: @width', ['@width' => $settings['width']]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(array $settings, array $parents = []): array {
    $settings += self::getDefaultSettings();
    if ($parents) {
      $parents_string = implode('][', $parents);
    }
    else {
      $parents_string = NULL;
    }

    $form = parent::getSettingsForm($settings, $parents);

    $form['height'] = [
      '#group' => $parents_string,
      '#type' => 'textfield',
      '#title' => $this->t('Height'),
      '#description' => $this->t('Enter the dimensions and the measurement units. E.g. 200px or 100%.'),
      '#size' => 4,
      '#default_value' => $settings['height'],
    ];
    $form['width'] = [
      '#group' => $parents_string,
      '#type' => 'textfield',
      '#title' => $this->t('Width'),
      '#description' => $this->t('Enter the dimensions and the measurement units. E.g. 200px or 100%.'),
      '#size' => 4,
      '#default_value' => $settings['width'],
    ];
    $form['zoom'] = [
      '#group' => $parents_string,
      '#type' => 'select',
      '#title' => $this->t('Zoom level'),
      '#options' => range(0, 20),
      '#description' => $this->t('The initial resolution at which to display the map, where zoom 0 corresponds to a map of the Earth fully zoomed out, and higher zoom levels zoom in at a higher resolution.'),
      '#default_value' => $settings['zoom'],
      '#process' => [
        ['\Drupal\Core\Render\Element\RenderElement', 'processGroup'],
        ['\Drupal\Core\Render\Element\Select', 'processSelect'],
      ],
      '#pre_render' => [
        ['\Drupal\Core\Render\Element\RenderElement', 'preRenderGroup'],
      ],
    ];

    $form['min_zoom'] = [
      '#group' => $parents_string,
      '#type' => 'select',
      '#title' => $this->t('Minimum zoom'),
      '#options' => range(0, 20),
      '#description' => $this->t('Minimum map zoom level.'),
      '#default_value' => $settings['min_zoom'],
      '#process' => [
        ['\Drupal\Core\Render\Element\RenderElement', 'processGroup'],
        ['\Drupal\Core\Render\Element\Select', 'processSelect'],
      ],
      '#pre_render' => [
        ['\Drupal\Core\Render\Element\RenderElement', 'preRenderGroup'],
      ],
    ];

    $form['max_zoom'] = [
      '#group' => $parents_string,
      '#type' => 'select',
      '#title' => $this->t('Maximum zoom'),
      '#options' => range(0, 20),
      '#description' => $this->t('Maximum map zoom level.'),
      '#default_value' => $settings['max_zoom'],
      '#process' => [
        ['\Drupal\Core\Render\Element\RenderElement', 'processGroup'],
        ['\Drupal\Core\Render\Element\Select', 'processSelect'],
      ],
      '#pre_render' => [
        ['\Drupal\Core\Render\Element\RenderElement', 'preRenderGroup'],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function alterRenderArray(array $render_array, array $map_settings, array $context = []): array {
    $yandex_url_parts = parse_url(self::$apiBaseUrl);

    $render_array['#attached'] = BubbleableMetadata::mergeAttachments(
      empty($render_array['#attached']) ? [] : $render_array['#attached'],
      [
        'library' => [
          'geolocation_yandex/geolocation.yandex',
        ],
        'drupalSettings' => [
          'geolocation' => [
            'maps' => [
              $render_array['#id'] => [
                'settings' => [
                  'yandex_settings' => $map_settings,
                ],
              ],
            ],
          ],
        ],
        // Add 'preconnect' resource hint.
        'html_head' => [
          [
            [
              '#tag' => 'link',
              '#attributes' => [
                'rel' => 'preconnect',
                'href' => $yandex_url_parts['scheme'] . "://" . $yandex_url_parts['host'],
              ],
            ],
            'geolocation_yandex_link_preconnect_map',
          ],
        ],
      ]
    );

    return parent::alterRenderArray($render_array, $map_settings, $context);
  }

  /**
   * {@inheritdoc}
   */
  public static function getControlPositions() {
    return [
      'top' => t('Top'),
      'right' => t('Right'),
      'left' => t('Left'),
      'bottom' => t('Bottom'),
    ];
  }

  /**
   * Selection of Yandex API packages.
   *
   * @see https://tech.yandex.ru/maps/archive/doc/jsapi/2.0/ref/reference/packages-docpage/
   */
  public static function getPackages(): array {
    return [
      'full' => t('Full'),
      'standard' => t('Standard'),
      'map' => t('Map'),
      'controls' => t('Controls'),
      'search' => t('Search'),
      'geoObjects' => t('GeoObjects'),
      'clusters' => t('Clusters'),
      'traffic' => t('Traffic'),
      'route' => t('Route'),
      'geoXml' => t('GeoXml'),
      'editor' => t('Editor'),
      'overlays' => t('Overlays'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function alterCommonMap(array $render_array, array $map_settings, array $context): array {
    $render_array['#attached'] = BubbleableMetadata::mergeAttachments(
      empty($render_array['#attached']) ? [] : $render_array['#attached'],
      [
        'library' => [
          'geolocation_yandex/commonmap.yandex',
        ],
      ]
    );

    return $render_array;
  }

  /**
   * Get Yandex API Base URL.
   *
   * @return string
   *   Base Url.
   */
  public function getApiUrl(): string {
    $config = \Drupal::config('geolocation_yandex.settings');
    $api_key = $config->get('api_key');

    $packages = $config->get('packages');
    foreach ($packages as &$package) {
      $package = 'package.' . $package;
    }
    $packages_str = implode(',', $packages);

    $base_url = self::$apiBaseUrl;
    $langcode = self::getApiUrlLangcode();
    return "$base_url?apikey=$api_key&load=$packages_str&lang=$langcode&coordorder=longlat";
  }

  /**
   * Get allowed langcode by language ID.
   *
   * @param string|null $langId
   *   Two-letter language code.
   *
   * @return string
   *   Yandex API allowed language code.
   */
  public static function getApiUrlLangcode(string $langId = NULL): string {
    if (empty($langId)) {
      $langId = \Drupal::languageManager()->getCurrentLanguage()->getId();
    }

    $langId = strtolower((string) $langId);

    $langcode = 'en_US';
    $langcode_mapping = [
      'ru' => 'ru_RU',
      'uk' => 'uk_UA',
      'tr' => 'tr_TR',
    ];

    if (!empty($langcode_mapping[$langId])) {
      return $langcode_mapping[$langId];
    }

    return $langcode;
  }

}
