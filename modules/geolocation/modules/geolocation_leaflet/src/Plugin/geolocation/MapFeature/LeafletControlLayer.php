<?php

namespace Drupal\geolocation_leaflet\Plugin\geolocation\MapFeature;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\geolocation_leaflet\LeafletTileLayerProviders;

/**
 * Provides Tile Layer control element.
 *
 * @MapFeature(
 *   id = "leaflet_control_layer",
 *   name = @Translation("Map Control - Tile layer"),
 *   description = @Translation("Add button to change layers."),
 *   type = "leaflet",
 * )
 */
class LeafletControlLayer extends ControlCustomElementBase {
  use LeafletTileLayerProviders;

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings() {
    return [
      'default_label' => 'Default',
      'tile_layer_providers' => [],
      'tile_providers_options' => [],
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

    $form['default_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default layer label'),
      '#description' => $this->t('Label for the layer in the control.'),
      '#default_value' => $settings['default_label'],
    ];
    $form['tile_layer_providers'] = [
      '#type' => 'details',
      '#title' => $this->t('Providers'),
    ];
    $providers = $this->getBaseMaps();
    $form['tile_providers_options'] = $this->getProviderOptionsForm($settings['tile_providers_options']);
    foreach ($providers as $provider => $variants) {
      $form['tile_layer_providers'][$provider] = [
        '#type' => 'details',
        '#title' => $provider,
      ];
      foreach ($variants as $key => $variant) {
        $form['tile_layer_providers'][$provider][$key]['checkbox'] = [
          '#type' => 'checkbox',
          '#title' => $variant,
          '#default_value' => $settings['tile_layer_providers'][$provider][$key]['checkbox'] ?? 0,
        ];
        $form['tile_layer_providers'][$provider][$key]['label'] = [
          '#type' => 'textfield',
          '#description' => $this->t('Label for the layer in the control.'),
          '#default_value' => $settings['tile_layer_providers'][$provider][$key]['label'] ?? '',
          '#states' => [
            'visible' => [
              ':input[name="' . $parents_string . '][tile_layer_providers][' . $provider . '][' . $key . '][checkbox]"]' => ['checked' => TRUE],
            ],
          ],
        ];
      }
      if (in_array($provider, $this->register)) {
        $states = [];
        foreach ($providers[$provider] as $key => $variant) {
          $states[][':input[name="' . $parents_string . '][tile_layer_providers][' . $provider . '][' . $key . '][checkbox]"]'] = ['checked' => TRUE];
        }
        foreach ($form['tile_providers_options'][$provider] as $key => $value) {
          $form['tile_providers_options'][$provider][$key]['#states']['visible'] = $states;
        }
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function alterMap(array $render_array, array $feature_settings, array $context = []) {
    $render_array = parent::alterMap($render_array, $feature_settings, $context);

    $providers = [];
    foreach ($feature_settings['tile_layer_providers'] as $list) {
      foreach ($list as $variant => $values) {
        if ($values['checkbox']) {
          $index = str_replace(' ', '.', $variant);
          $providers[$index] = $values['label'];
        }
      }
    }

    $render_array['#attached'] = BubbleableMetadata::mergeAttachments(
      empty($render_array['#attached']) ? [] : $render_array['#attached'],
      [
        'drupalSettings' => [
          'geolocation' => [
            'maps' => [
              $render_array['#id'] => [
                $this->getPluginId() => [
                  'defaultLabel' => $feature_settings['default_label'],
                  'tileLayerProviders' => $providers,
                  'tileLayerOptions' => $feature_settings['tile_providers_options'],
                ],
              ],
            ],
          ],
        ],
      ]
    );

    return $render_array;
  }

}
