<?php

namespace Drupal\geofield\Element;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a Geofield Lat Lon form element.
 *
 * @FormElement("geofield_latlon")
 */
class GeofieldLatLon extends GeofieldElementBase {

  /**
   * {@inheritdoc}
   */
  public static function getComponents() {
    return [
      'lat' => [
        'title' => t('Latitude'),
        'range' => 90,
      ],
      'lon' => [
        'title' => t('Longitude'),
        'range' => 180,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#input' => TRUE,
      '#process' => [
        [$class, 'latlonProcess'],
      ],
      '#element_validate' => [
        [$class, 'elementValidate'],
      ],
      '#theme_wrappers' => ['fieldset'],
    ];
  }

  /**
   * Generates the Geofield Lat Lon form element.
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
  public static function latlonProcess(array &$element, FormStateInterface $form_state, array &$complete_form) {
    static::elementProcess($element, $form_state, $complete_form);

    if (!empty($element['#geolocation']) && $element['#geolocation'] === TRUE) {
      $element['#attached']['library'][] = 'geofield/geolocation';
      $element['geocode'] = [
        '#type' => 'button',
        '#value' => t('Find my location'),
        '#name' => 'geofield-html5-geocode-button',
      ];
      $element['#attributes']['class'] = ['auto-geocode'];
    }

    return $element;
  }

}
