<?php

namespace Drupal\geolocation_leaflet\Plugin\geolocation\MapFeature;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\geolocation\MapFeatureBase;
use Drupal\geolocation_leaflet\LeafletTileLayerProviders;

/**
 * Provides map tile layer support.
 *
 * @MapFeature(
 *   id = "leaflet_tile_layer",
 *   name = @Translation("Tile Layer - Providers"),
 *   description = @Translation("Select a map tile layer."),
 *   type = "leaflet",
 * )
 */
class LeafletTileLayer extends MapFeatureBase {
  use LeafletTileLayerProviders;

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings() {
    return [
      'tile_layer_provider' => 'OpenStreetMap Mapnik',
      'tile_provider_options' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(array $settings, array $parents) {
    $form = parent::getSettingsForm($settings, $parents);

    if ($parents) {
      $first = array_shift($parents);
      $parents_string = $first . '[' . implode('][', $parents);
    }
    else {
      $parents_string = NULL;
    }

    $providers = $this->getBaseMaps();
    $form['tile_layer_provider'] = [
      '#type' => 'select',
      '#options' => $providers,
      '#default_value' => $settings['tile_layer_provider'],
    ];

    $form['tile_provider_options'] = $this->getProviderOptionsForm($settings['tile_provider_options']);
    foreach ($this->register as $provider) {
      $states = [];
      foreach ($providers[$provider] as $key => $variant) {
        $states[][':input[name="' . $parents_string . '][tile_layer_provider]"]'] = ['value' => $key];
      }
      foreach ($form['tile_provider_options'][$provider] as $key => $value) {
        $form['tile_provider_options'][$provider][$key]['#states']['visible'] = $states;
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function alterMap(array $render_array, array $feature_settings, array $context = []) {
    $render_array = parent::alterMap($render_array, $feature_settings, $context);

    $tileLayer = [
      'enable' => TRUE,
      'tileLayerProvider' => str_replace(' ', '.', $feature_settings['tile_layer_provider']),
    ];

    $provider = explode('.', $feature_settings['tile_layer_provider'])[0];
    if (isset($feature_settings['tile_provider_options'][$provider])) {
      $tileLayer['tileLayerOptions'] = $feature_settings['tile_provider_options'][$provider];
    }

    $render_array['#attached'] = BubbleableMetadata::mergeAttachments(
      empty($render_array['#attached']) ? [] : $render_array['#attached'],
      [
        'library' => [
          'geolocation_leaflet/mapfeature.' . $this->getPluginId(),
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

}
