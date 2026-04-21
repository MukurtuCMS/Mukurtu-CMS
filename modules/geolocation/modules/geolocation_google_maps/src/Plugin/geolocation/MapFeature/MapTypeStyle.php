<?php

namespace Drupal\geolocation_google_maps\Plugin\geolocation\MapFeature;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\geolocation\MapFeatureBase;

/**
 * Provides map styling support.
 *
 * @MapFeature(
 *   id = "map_type_style",
 *   name = @Translation("Map Type Style"),
 *   description = @Translation("Add map styling JSON."),
 *   type = "google_maps",
 * )
 */
class MapTypeStyle extends MapFeatureBase {

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings() {
    return [
      'style' => '[]',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(array $settings, array $parents) {
    $form['style'] = [
      '#title' => $this->t('JSON styles'),
      '#type' => 'textarea',
      '#default_value' => $settings['style'],
      '#description' => $this->t('A JSON encoded styles array to customize the presentation of the Google Map. See the <a href=":styling">Styled Map</a> section of the Google Maps website for further information.', [
        ':styling' => 'https://developers.google.com/maps/documentation/javascript/styling',
      ]),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateSettingsForm(array $values, FormStateInterface $form_state, array $parents) {
    $json_style = $values['style'];
    if (!empty($json_style)) {
      $style_parents = $parents;
      $style_parents[] = 'styles';
      if (!is_string($json_style)) {
        $form_state->setErrorByName(implode('][', $style_parents), $this->t('Please enter a JSON string as style.'));
      }
      $json_result = json_decode($json_style);
      if ($json_result === NULL) {
        $form_state->setErrorByName(implode('][', $style_parents), $this->t('Decoding style JSON failed. Error: %error.', ['%error' => json_last_error()]));
      }
      elseif (!is_array($json_result)) {
        $form_state->setErrorByName(implode('][', $style_parents), $this->t('Decoded style JSON is not an array.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function alterMap(array $render_array, array $feature_settings, array $context = []) {
    $render_array = parent::alterMap($render_array, $feature_settings, $context);

    if (
      !empty($feature_settings['style'])
      && is_string($feature_settings['style'])
    ) {
      $feature_settings['style'] = json_decode($feature_settings['style']);
    }

    $render_array['#attached'] = BubbleableMetadata::mergeAttachments(
      empty($render_array['#attached']) ? [] : $render_array['#attached'],
      [
        'drupalSettings' => [
          'geolocation' => [
            'maps' => [
              $render_array['#id'] => [
                'settings' => [
                  'google_map_settings' => [
                    'style' => $feature_settings['style'],
                  ],
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
