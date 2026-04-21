<?php

namespace Drupal\geolocation_leaflet\Plugin\geolocation\MapFeature;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\geolocation\MapFeatureBase;

/**
 * Provides map tile layer overlay support.
 *
 * @MapFeature(
 *   id = "leaflet_tile_layer_overlay",
 *   name = @Translation("Tile Layer - Overlays"),
 *   description = @Translation("Select a map tile layer overlay."),
 *   type = "leaflet",
 * )
 */
class LeafletTileLayerOverlay extends MapFeatureBase {

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings() {
    return [
      'tile_layer_overlay' => 'OpenInfraMap.Power',
    ];
  }

  /**
   * Return options array for tile overlay.
   *
   * @param string $overlay
   *   Map tile overlay selected.
   *
   * @return array
   *   Options form.
   */
  public static function getOptionsForm($overlay) {

    $form = [
      '#prefix' => '<div id="tile-overlay-settings">',
      '#suffix' => '</div>',
    ];
    if ($overlay == 'OpenWeatherMap') {
      $form['apiKey'] = [
        '#type' => 'textfield',
        '#title' => t('API key'),
        '#default_value' => '',
        '#description' => t('Get your API Key here <a href="@url">@overlay</a>.', [
          '@url' => 'https://openweathermap.org/',
          '@overlay' => $overlay,
        ]),
      ];
    }

    return $form;
  }

  /**
   * Return settings array for tile overlay after select change.
   *
   * @param array $form
   *   Form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Current From State.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Settings form.
   */
  public static function addTileOverlaySettingsFormAjax(array $form, FormStateInterface $form_state) {

    $ajax_response = new AjaxResponse();

    $triggering_element_value = $form_state->getTriggeringElement()['#value'];
    $overlay = explode('.', $triggering_element_value)[0];

    $form = LeafletTileLayerOverlay::getOptionsForm($overlay);
    $ajax_response->addCommand(new ReplaceCommand('#tile-overlay-settings', $form));

    return $ajax_response;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(array $settings, array $parents) {
    $settings = array_replace_recursive(
      self::getDefaultSettings(),
      $settings
    );

    $form['tile_layer_overlay'] = [
      '#type' => 'select',
      '#options' => $this->getTileOverlays(),
      '#default_value' => $settings['tile_layer_overlay'],
      '#ajax' => [
        'callback' => [$this, 'addTileOverlaySettingsFormAjax'],
        'wrapper' => 'tile-overlay-settings',
        'effect' => 'fade',
      ],
    ];

    $overlay = explode('.', $settings['tile_layer_overlay'])[0];
    $form['tile_overlay_options'] = LeafletTileLayerOverlay::getOptionsForm($overlay);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function alterMap(array $render_array, array $feature_settings, array $context = []) {
    $render_array = parent::alterMap($render_array, $feature_settings, $context);

    $tileLayer = [
      'enable' => TRUE,
      'tileLayerOverlay' => $feature_settings['tile_layer_overlay'],
    ];
    if (isset($feature_settings['tile_overlay_options'])) {
      $tileLayer['tileLayerOptions'] = $feature_settings['tile_overlay_options'];
    }
    $render_array['#attached'] = BubbleableMetadata::mergeAttachments(
      empty($render_array['#attached']) ? [] : $render_array['#attached'],
      [
        'library' => [
          'geolocation_leaflet/mapfeature.tilelayeroverlay',
        ],
        'drupalSettings' => [
          'geolocation' => [
            'maps' => [
              $render_array['#id'] => [
                $this->getPluginId() => $tileLayer,
              ],
            ],
          ],
        ],
      ]
    );

    return $render_array;
  }

  /**
   * Provide some available tile overlays.
   *
   * @return array
   *   An array containing tile overlay IDs.
   */
  private function getTileOverlays() {
    return [
      'OpenInfraMap' => [
        'OpenInfraMap.Power' => 'OpenInfraMap Power',
        'OpenInfraMap.Telecom' => 'OpenInfraMap Telecom',
        'OpenInfraMap.Petroleum' => 'OpenInfraMap Petroleum',
        'OpenInfraMap.Water' => 'OpenInfraMap Water',
      ],
      'OpenSeaMap' => [
        'OpenSeaMap' => 'OpenSeaMap',
      ],
      'OpenPtMap' => [
        'OpenPtMap' => 'OpenPtMap',
      ],
      'OpenRailwayMap' => [
        'OpenRailwayMap' => 'OpenRailwayMap',
      ],
      'OpenFireMap' => [
        'OpenFireMap' => 'OpenFireMap',
      ],
      'SafeCast' => [
        'SafeCast' => 'SafeCast',
      ],
      'OpenMapSurfer' => [
        'OpenMapSurfer.AdminBounds' => 'OpenMapSurfer AdminBounds',
      ],
      'Hydda' => [
        'Hydda.RoadsAndLabels' => 'Hydda RoadsAndLabels',
      ],
      'Stamen' => [
        'Stamen.TonerHybrid' => 'Stamen TonerHybrid',
        'Stamen.TonerLines' => 'Stamen TonerLines',
        'Stamen.TonerLabels' => 'Stamen TonerLabels',
        'Stamen.TopOSMFeatures' => 'Stamen TopOSMFeatures',
      ],
      'OpenWeatherMap' => [
        'OpenWeatherMap.Clouds' => 'OpenWeatherMap Clouds',
        'OpenWeatherMap.CloudsClassic' => 'OpenWeatherMap CloudsClassic',
        'OpenWeatherMap.Precipitation' => 'OpenWeatherMap Precipitation',
        'OpenWeatherMap.PrecipitationClassic' => 'OpenWeatherMap PrecipitationClassic',
        'OpenWeatherMap.Rain' => 'OpenWeatherMap Rain',
        'OpenWeatherMap.RainClassic' => 'OpenWeatherMap RainClassic',
        'OpenWeatherMap.Pressure' => 'OpenWeatherMap Pressure',
        'OpenWeatherMap.PressureContour' => 'OpenWeatherMap PressureContour',
        'OpenWeatherMap.Wind' => 'OpenWeatherMap Wind',
        'OpenWeatherMap.Temperature' => 'OpenWeatherMap Temperature',
        'OpenWeatherMap.Snow' => 'OpenWeatherMap Snow',
      ],
      'JusticeMap' => [
        'JusticeMap.income' => 'JusticeMap income',
        'JusticeMap.americanIndian' => 'JusticeMap americanIndian',
        'JusticeMap.asian' => 'JusticeMap asian',
        'JusticeMap.black' => 'JusticeMap black',
        'JusticeMap.hispanic' => 'JusticeMap hispanic',
        'JusticeMap.multi' => 'JusticeMap multi',
        'JusticeMap.nonWhite' => 'JusticeMap nonWhite',
        'JusticeMap.white' => 'JusticeMap white',
        'JusticeMap.plurality' => 'JusticeMap plurality',
      ],
    ];
  }

}
