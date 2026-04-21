<?php

namespace Drupal\geolocation\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElementBase;

/**
 * Provides a render element to display a geolocation map.
 *
 * Usage example:
 * @code
 * $form['map'] = [
 *   '#type' => 'geolocation_input',
 *   '#prefix' => $this->t('Geolocation Input'),
 *   '#description' => $this->t('Form element type "geolocation_input"'),
 * ];
 * @endcode
 *
 * @FormElement("geolocation_input")
 */
class GeolocationInput extends FormElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#input' => TRUE,
      '#default_value' => NULL,
      '#process' => [
        [$class, 'processGeolocation'],
        [$class, 'processGroup'],
      ],
      '#pre_render' => [
        [$class, 'preRenderGroup'],
      ],
      '#element_validate' => [
        [$class, 'validateGeolocation'],
      ],
      '#theme_wrappers' => ['fieldset'],
    ];
  }

  /**
   * Processes the geolocation form element.
   *
   * @param array $element
   *   The form element to process.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return array
   *   The processed element.
   */
  public static function processGeolocation(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $default_field_values = [
      'lat' => '',
      'lng' => '',
    ];

    if (
      $element['#defaults_loaded']
      && isset($element['#value']['lat'])
      && isset($element['#value']['lng'])
    ) {
      $default_field_values = [
        'lat' => $element['#value']['lat'],
        'lng' => $element['#value']['lng'],
      ];
    }
    elseif (
      !empty($element['#default_value'])
      && isset($element['#default_value']['lat'])
      && isset($element['#default_value']['lng'])
    ) {
      $default_field_values = [
        'lat' => $element['#default_value']['lat'],
        'lng' => $element['#default_value']['lng'],
      ];
    }

    $element['lat'] = [
      '#type' => 'textfield',
      '#title' => t('Latitude'),
      '#default_value' => $default_field_values['lat'],
      '#attributes' => [
        'class' => [
          'geolocation-input-latitude',
          'geolocation-input-latitude',
        ],
      ],
    ];
    $element['lng'] = [
      '#type' => 'textfield',
      '#title' => t('Longitude'),
      '#default_value' => $default_field_values['lng'],
      '#attributes' => [
        'class' => [
          'geolocation-input-longitude',
        ],
      ],
    ];

    if (empty($element['#wrapper_attributes'])) {
      $element['#wrapper_attributes'] = [];
    }

    $element['#wrapper_attributes'] = array_merge_recursive(
      $element['#wrapper_attributes'],
      [
        'class' => [
          'geolocation-input',
        ],
      ]
    );

    return $element;
  }

  /**
   * Form element validation handler for #type 'email'.
   *
   * @param array $element
   *   The form element to process.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   */
  public static function validateGeolocation(array &$element, FormStateInterface $form_state, array &$complete_form) {
    if (
      empty($element['#value']['lng'])
      && empty($element['#value']['lat'])
    ) {
      return;
    }

    if (!is_numeric($element['#value']['lng'])) {
      $form_state->setError($element, t('Longitude not numeric.'));
    }

    if (!is_numeric($element['#value']['lat'])) {
      $form_state->setError($element, t('Latitude not numeric.'));
    }

    $longitude = floatval($element['#value']['lng']);
    $latitude = floatval($element['#value']['lat']);

    if ($latitude < -90 || $latitude > 90) {
      $form_state->setError($element, t('Latitude must be between -90 and 90.'));
    }

    if ($longitude < -180 || $longitude > 180) {
      $form_state->setError($element, t('Longitude must be between -180 and 180.'));
    }
  }

}
