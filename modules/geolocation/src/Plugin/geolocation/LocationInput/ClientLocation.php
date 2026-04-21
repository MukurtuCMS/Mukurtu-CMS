<?php

namespace Drupal\geolocation\Plugin\geolocation\LocationInput;

use Drupal\geolocation\LocationInputBase;
use Drupal\geolocation\LocationInputInterface;

/**
 * Location based proximity center.
 *
 * @LocationInput(
 *   id = "client_location",
 *   name = @Translation("Client location"),
 *   description = @Translation("If client provides location, use it."),
 * )
 */
class ClientLocation extends LocationInputBase implements LocationInputInterface {

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings() {
    $settings = parent::getDefaultSettings();

    $settings['auto_submit'] = FALSE;
    $settings['hide_form'] = FALSE;

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm($option_id = NULL, array $settings = [], $context = NULL) {
    $settings = $this->getSettings($settings);

    $form = parent::getSettingsForm($option_id, $settings, $context);

    $form['auto_submit'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Auto-submit form'),
      '#default_value' => $settings['auto_submit'],
      '#description' => $this->t('Only triggers if location could be set'),
    ];

    $form['hide_form'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide coordinates form'),
      '#default_value' => $settings['hide_form'],
    ];

    $form['#description'] = $this->t('Location will be set if it is empty and client location is available. This requires a https connection.');

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getForm(string $center_option_id, array $center_option_settings, $context = NULL, array $default_value = NULL) {
    $form = parent::getForm($center_option_id, $center_option_settings, $context, $default_value);

    $identifier = uniqid($center_option_id);

    if (!empty($form['coordinates'])) {
      $form['coordinates']['#attributes'] = [
        'class' => [
          $identifier,
          'location-input-client-location',
        ],
      ];

      $form['coordinates']['#attached'] = [
        'library' => [
          'geolocation/location_input.client_location',
        ],
        'drupalSettings' => [
          'geolocation' => [
            'locationInput' => [
              'clientLocation' => [
                [
                  'identifier' => $identifier,
                  'autoSubmit' => $center_option_settings['auto_submit'],
                  'hideForm' => $center_option_settings['hide_form'],
                ],
              ],
            ],
          ],
        ],
      ];
    }

    return $form;
  }

}
