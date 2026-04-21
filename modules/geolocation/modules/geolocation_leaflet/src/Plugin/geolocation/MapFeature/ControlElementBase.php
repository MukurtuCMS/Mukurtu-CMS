<?php

namespace Drupal\geolocation_leaflet\Plugin\geolocation\MapFeature;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\geolocation\MapFeatureBase;
use Drupal\geolocation_leaflet\Plugin\geolocation\MapProvider\Leaflet;

/**
 * Class ControlMapFeatureBase.
 */
abstract class ControlElementBase extends MapFeatureBase {

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings() {
    return [
      'position' => 'topright',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(array $settings, array $parents) {
    $form = parent::getSettingsForm($settings, $parents);

    $settings = array_replace_recursive(
      self::getDefaultSettings(),
      $settings
    );

    $form['position'] = [
      '#type' => 'select',
      '#title' => $this->t('Position'),
      '#options' => Leaflet::getControlPositions(),
      '#default_value' => $settings['position'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateSettingsForm(array $values, FormStateInterface $form_state, array $parents) {
    if (!in_array($values['position'], array_keys(Leaflet::getControlPositions()))) {
      $form_state->setErrorByName(implode('][', $parents), $this->t('No valid position.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function alterMap(array $render_array, array $feature_settings, array $context = []) {
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
                  'position' => empty($feature_settings['position']) ? '' : $feature_settings['position'],
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
