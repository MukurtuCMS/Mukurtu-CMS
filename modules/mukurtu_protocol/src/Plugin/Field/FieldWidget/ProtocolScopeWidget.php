<?php

namespace Drupal\mukurtu_protocol\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'mukurtu_protocol_scope_widget' widget.
 *
 * @FieldWidget(
 *   id = "mukurtu_protocol_scope_widget",
 *   module = "mukurtu_protocol",
 *   label = @Translation("Mukurtu Protocol Scope Picker"),
 *   field_types = {
 *     "list_string"
 *   }
 * )
 */
class ProtocolScopeWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $value = isset($items[$delta]->value) ? $items[$delta]->value : 'personal';
    $options = $items[$delta]->getPossibleOptions();

    $element['value'] = $element + [
      '#type' => 'radios',
      '#options' => $options,
      '#default_value' => $value,
      '#element_validate' => [
        [static::class, 'validate'],
      ],
    ];

    return $element;
  }

  /**
   * Validate the protocol scope field.
   */
  public static function validate($element, FormStateInterface $form_state) {
  }

}
