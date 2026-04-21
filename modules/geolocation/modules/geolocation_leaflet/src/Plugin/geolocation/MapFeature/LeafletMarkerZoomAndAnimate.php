<?php

namespace Drupal\geolocation_leaflet\Plugin\geolocation\MapFeature;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Template\Attribute;
use Drupal\geolocation\MapFeatureBase;

/**
 * Provides Feature.
 *
 * @MapFeature(
 *   id = "leaflet_marker_zoom_and_animate",
 *   name = @Translation("Marker Zoom & Animate"),
 *   description = @Translation("Set a URL anchor."),
 *   type = "leaflet",
 * )
 */
class LeafletMarkerZoomAndAnimate extends MapFeatureBase {

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings() {
    return [
      'marker_zoom_anchor_id' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(array $settings, array $parents) {
    $form['marker_zoom_anchor_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Anchor ID'),
      '#description' => $this->t('Clicking a link with the class "geolocation-marker-zoom" and this anchor target will zoom to the specific marker and animate it. Tokens supported.'),
      '#default_value' => $settings['marker_zoom_anchor_id'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function alterMap(array $render_array, array $feature_settings, array $context = []) {
    if (empty($render_array['#children']['locations'])) {
      return $render_array;
    }

    if (!empty($context['view'])) {
      /** @var \Drupal\views\ViewExecutable $view */
      $view = $context['view'];
    }

    foreach ($render_array['#children']['locations'] as &$location) {
      $anchor_id = \Drupal::token()->replace($feature_settings['marker_zoom_anchor_id'], $context);

      if (empty($view)) {
        continue;
      }

      if (empty($location['#attributes'])) {
        $location['#attributes'] = [];
      }
      elseif (!is_array($location['#attributes'])) {
        $location['#attributes'] = new Attribute($location['#attributes']);
        $location['#attributes'] = $location['#attributes']->toArray();
      }

      if (isset($location['#attributes']['data-views-row-index'])) {
        $anchor_id = $view->getStyle()->tokenizeValue($anchor_id, (int) $location['#attributes']['data-views-row-index']);
        $location['#attributes']['data-marker-zoom-anchor-id'] = $anchor_id;
      }
    }

    $render_array = parent::alterMap($render_array, $feature_settings, $context);

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
                $this->getPluginId() => [
                  'enable' => TRUE,
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
