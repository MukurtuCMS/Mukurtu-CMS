<?php

namespace Drupal\geolocation\Plugin\geolocation\MapFeature;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Template\Attribute;
use Drupal\geolocation\MapFeatureBase;

/**
 * Provides map tilt.
 *
 * @MapFeature(
 *   id = "geolocation_marker_scroll_to_id",
 *   name = @Translation("Marker Scroll-to-ID"),
 *   description = @Translation("Clicking on a marker will try to scroll to the respective ID."),
 *   type = "all",
 * )
 */
class GeolocationMarkerScrollToId extends MapFeatureBase {

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings() {
    return [
      'scroll_target_id' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(array $settings, array $parents) {
    $form['scroll_target_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Scroll target ID'),
      '#description' => $this->t('ID to scroll to on click. Tokens supported.'),
      '#default_value' => $settings['scroll_target_id'],
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
      $scroll_target_id = \Drupal::token()->replace($feature_settings['scroll_target_id'], $context);

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
        $scroll_target_id = $view->getStyle()->tokenizeValue($scroll_target_id, (int) $location['#attributes']['data-views-row-index']);
        $location['#attributes']['data-scroll-target-id'] = $scroll_target_id;
      }
    }

    $render_array = parent::alterMap($render_array, $feature_settings, $context);

    $render_array['#attached'] = BubbleableMetadata::mergeAttachments(
      empty($render_array['#attached']) ? [] : $render_array['#attached'],
      [
        'library' => [
          'geolocation/geolocation.marker_scroll_to_id',
        ],
        'drupalSettings' => [
          'geolocation' => [
            'maps' => [
              $render_array['#id'] => [
                'geolocation_marker_scroll_to_id' => [
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
