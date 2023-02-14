<?php

namespace Drupal\mukurtu_protocol\Plugin\Field\FieldWidget;

use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Mukurtu Cultural Protocol widget.
 *
 * @FieldWidget(
 *   id = "cultural_protocol_widget",
 *   label = @Translation("Cultural Protocol widget"),
 *   field_types = {
 *     "cultural_protocol",
 *   }
 * )
 */
class CulturalProtocolWidget extends WidgetBase {
  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $protocols = $items[$delta]->protocols ?? NULL;
    $sharing_setting = $items[$delta]->sharing_setting ?? NULL;
    $value = $protocols && $sharing_setting ? "{$sharing_setting}({$protocols})" : "";
    $element += [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#default_value' => $value,
    ];

    return ['value' => $element];
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    return $values;
  }

}
