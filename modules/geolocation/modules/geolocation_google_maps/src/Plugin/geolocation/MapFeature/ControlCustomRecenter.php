<?php

namespace Drupal\geolocation_google_maps\Plugin\geolocation\MapFeature;

use Drupal\Core\Render\BubbleableMetadata;

/**
 * Provides Recenter control element.
 *
 * @MapFeature(
 *   id = "control_recenter",
 *   name = @Translation("Map Control - Recenter"),
 *   description = @Translation("Add button to recenter map."),
 *   type = "google_maps",
 * )
 */
class ControlCustomRecenter extends ControlCustomElementBase {

  /**
   * {@inheritdoc}
   */
  public function alterMap(array $render_array, array $settings, array $context = []) {
    $render_array = parent::alterMap($render_array, $settings, $context);

    $render_array['#attached'] = BubbleableMetadata::mergeAttachments(
      empty($render_array['#attached']) ? [] : $render_array['#attached'],
      [
        'library' => [
          'geolocation_google_maps/mapfeature.' . $this->getPluginId(),
        ],
        'drupalSettings' => [
          'geolocation' => [
            'maps' => [
              $render_array['#id'] => [
                $this->getPluginId() => [
                  'enable' => TRUE,
                ],
              ],
            ],
          ],
        ],
      ]
    );

    $render_array['#controls'][$this->pluginId]['control_recenter'] = [
      '#type' => 'html_tag',
      '#tag' => 'button',
      '#value' => $this->t('Recenter'),
      '#attributes' => [
        'class' => ['recenter'],
      ],
    ];

    return $render_array;
  }

}
