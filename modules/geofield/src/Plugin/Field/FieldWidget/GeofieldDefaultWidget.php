<?php

namespace Drupal\geofield\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Widget implementation of the 'geofield_default' widget.
 *
 * @FieldWidget(
 *   id = "geofield_default",
 *   label = @Translation("Geofield (WKT)"),
 *   field_types = {
 *     "geofield"
 *   }
 * )
 */
class GeofieldDefaultWidget extends GeofieldBaseWidget {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'geometry_validation' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);

    $elements['geometry_validation'] = [
      '#type' => 'checkbox',
      '#title' => 'Enable Geometry Validation',
      '#default_value' => $this->getSetting('geometry_validation'),
      '#description' => $this->t('Enable input Geometry validation, in WKT or Geojson format. If not checked invalid Geometries will be set as NULL.'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    return [
      $this->t('Geometry Validation: @state', ['@state' => $this->getSetting('geometry_validation') ? $this->t('enabled') : $this->t('disabled')]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    $element += [
      '#type' => 'textarea',
      '#default_value' => $items[$delta]->value ?: NULL,
    ];

    if ($this->getSetting('geometry_validation')) {
      // Append notice to the field description in the widget:
      $geometry_validation_enabled = $this->t('Geometry Validation enabled (valid <a href="https://en.wikipedia.org/wiki/Well-known_text_representation_of_geometry" target="blank">WKT</a> or <a href="https://en.wikipedia.org/wiki/GeoJSON" target="blank">GeoJson</a> format & values required).');
      $element['#description'] = !empty($element['#description']) ? $element['#description'] . '<br />' . $geometry_validation_enabled : $geometry_validation_enabled;
      $element['#element_validate'] = [
        [get_class($this), 'validateGeofieldGeometryText'],
      ];
    }
    else {
      // Append notice to the field description in the widget:
      $geometry_validation_not_enabled = $this->t('Geometry Validation disabled (invalid WKT or Geojson format & values will be set as NULL.)');
      $element['#description'] = !empty($element['#description']) ? $element['#description'] . '<br />' . $geometry_validation_not_enabled : $geometry_validation_not_enabled;
    }

    return ['value' => $element];
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as $delta => $value) {
      $values[$delta]['value'] = $this->geofieldBackendValue($value['value']);
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public static function validateGeofieldGeometryText(array $element, FormStateInterface $form_state) {
    if (!empty($element['#value']) && is_null(\Drupal::service('geofield.geophp')->load($element['#value']))) {
      $form_state->setError($element, t('The @value is not a valid geospatial content.', [
        '@value' => $element['#value'],
      ]));
    }
  }

}
