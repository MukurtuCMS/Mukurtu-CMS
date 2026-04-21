<?php

namespace Drupal\geofield\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'geofield_bounds' widget.
 *
 * @FieldWidget(
 *   id = "geofield_bounds",
 *   label = @Translation("Bounding Box"),
 *   field_types = {
 *     "geofield"
 *   }
 * )
 */
class GeofieldBoundsWidget extends GeofieldBaseWidget {

  /**
   * Bounds widget components.
   *
   * @var array
   */
  public $components = ['top', 'right', 'bottom', 'left'];

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $bounds_value = [];

    foreach ($this->components as $component) {
      $bounds_value[$component] = isset($items[$delta]->{$component}) ? floatval($items[$delta]->{$component}) : '';
    }

    $element['value'] += [
      '#type' => 'geofield_bounds',
      '#default_value' => $bounds_value,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as $delta => $value) {
      foreach ($this->components as $component) {
        if (empty($value['value'][$component]) || !is_numeric($value['value'][$component])) {
          $values[$delta]['value'] = '';
          continue 2;
        }
      }
      $components = $value['value'];
      $bounds = [
        [$components['right'], $components['top']],
        [$components['right'], $components['bottom']],
        [$components['left'], $components['bottom']],
        [$components['left'], $components['top']],
        [$components['right'], $components['top']],
      ];

      $values[$delta]['value'] = $this->wktGenerator->wktBuildPolygon($bounds);
    }

    return $values;
  }

}
