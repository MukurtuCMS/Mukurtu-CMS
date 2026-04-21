<?php

namespace Drupal\geofield\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElementBase;
use Drupal\geofield\DmsConverter;

/**
 * Provides a Geofield DMS form element.
 *
 * @FormElementBase("geofield_dms")
 */
class GeofieldDms extends FormElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#input' => TRUE,
      '#process' => [
        [$class, 'dmsProcess'],
      ],
      '#element_validate' => [
        [$class, 'elementValidate'],
      ],
      '#theme_wrappers' => ['fieldset'],
    ];
  }

  /**
   * Generates the Geofield DMS form element.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   element. Note that $element must be taken by reference here, so processed
   *   child elements are taken over into $form_state.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return array
   *   The processed element.
   */
  public static function dmsProcess(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $element['#tree'] = TRUE;
    $element['#input'] = TRUE;
    $default_value = [];
    if (!empty($element['#default_value']['lon']) && !empty($element['#default_value']['lat'])) {
      $default_value = DmsConverter::decimalToDms($element['#default_value']['lon'], $element['#default_value']['lat']);
    }

    $options = [
      'lat' => [
        'N' => t('North'),
        'S' => t('South'),
      ],
      'lon' => [
        'E' => t('East'),
        'W' => t('West'),
      ],
    ];

    foreach ($options as $type => $option) {
      $component_default = !empty($default_value) ? $default_value->get($type) : [];
      self::processComponent($element, $type, $option, $component_default);
    }

    unset($element['#value']);
    // Set this to false always to prevent notices.
    $element['#required'] = FALSE;

    return $element;
  }

  /**
   * Validates a Geofield DMS form element.
   *
   * @param array $element
   *   The element being processed.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   */
  public static function elementValidate(array &$element, FormStateInterface $form_state, array &$complete_form) {

  }

  /**
   * Helper function to generate each coordinate component form element.
   *
   * @param array $element
   *   The form element.
   * @param string $type
   *   The component type.
   * @param array $options
   *   The component options.
   * @param array $default_value
   *   The component default value array.
   */
  protected static function processComponent(array &$element, $type, array $options, array $default_value) {
    $element[$type] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'container-inline',
        ],
      ],
    ];

    $element[$type]['orientation'] = [
      '#type' => 'select',
      '#title' => '',
      '#options' => $options,
      '#multiple' => FALSE,
      '#required' => (!empty($element['#required'])) ? $element['#required'] : FALSE,
      '#default_value' => (!empty($default_value)) ? $default_value['orientation'] : '',
      '#attributes' => [
        'class' => [
          'geofield-' . $type . '-orientation',
          'container-inline',
        ],
        'style' => [
          'min-width: 6em',
        ],
      ],
    ];
    $element[$type]['degrees'] = [
      '#type' => 'number',
      '#min' => 0,
      '#step' => 1,
      '#max' => 180,
      '#title' => '',
      '#required' => (!empty($element['#required'])) ? $element['#required'] : FALSE,
      '#default_value' => (!empty($default_value)) ? $default_value['degrees'] : '',
      '#suffix' => '°',
      '#attributes' => [
        'class' => [
          'geofield-' . $type . '-degrees',
          'container-inline',
        ],
      ],
    ];
    $element[$type]['minutes'] = [
      '#type' => 'number',
      '#min' => 0,
      '#max' => 59,
      '#step' => 1,
      '#title' => '',
      '#required' => (!empty($element['#required'])) ? $element['#required'] : FALSE,
      '#default_value' => (!empty($default_value)) ? $default_value['minutes'] : '',
      '#suffix' => '\'',
      '#attributes' => [
        'class' => [
          'geofield-' . $type . '-minutes',
          'container-inline',
        ],
      ],
    ];
    $element[$type]['seconds'] = [
      '#type' => 'number',
      '#min' => 0,
      '#max' => 59,
      '#step' => 1,
      '#title' => '',
      '#required' => (!empty($element['#required'])) ? $element['#required'] : FALSE,
      '#default_value' => (!empty($default_value)) ? $default_value['seconds'] : '',
      '#suffix' => '"',
      '#attributes' => [
        'class' => [
          'geofield-' . $type . '-seconds',
          'container-inline',
        ],
      ],
    ];
  }

}
