<?php

namespace Drupal\geolocation_leaflet\Plugin\geolocation\MapFeature;

use Drupal\Core\Render\BubbleableMetadata;

/**
 * Provides Attribution control element.
 *
 * @MapFeature(
 *   id = "leaflet_control_attribution",
 *   name = @Translation("Map Control - Attribution"),
 *   description = @Translation("Add attribution the map."),
 *   type = "leaflet",
 * )
 */
class LeafletControlAttribution extends ControlElementBase {

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings() {
    return array_replace_recursive(
      parent::getDefaultSettings(),
      [
        'prefix' => 'Leaflet',
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(array $settings, array $parents) {
    $form = parent::getSettingsForm($settings, $parents);

    $form['prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Prefix'),
      '#description' => $this->t('The HTML text shown before the attributions.'),
      '#default_value' => $settings['prefix'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function alterMap(array $render_array, array $feature_settings, array $context = []) {
    $render_array = parent::alterMap($render_array, $feature_settings, $context);

    $render_array['#attached'] = BubbleableMetadata::mergeAttachments(
      empty($render_array['#attached']) ? [] : $render_array['#attached'],
      [
        'drupalSettings' => [
          'geolocation' => [
            'maps' => [
              $render_array['#id'] => [
                $this->getPluginId() => [
                  'position' => $feature_settings['position'],
                  'prefix' => $feature_settings['prefix'],
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
