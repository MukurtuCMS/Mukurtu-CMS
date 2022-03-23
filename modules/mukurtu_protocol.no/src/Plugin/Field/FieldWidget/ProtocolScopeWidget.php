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
    $options = $items[$delta]->getPossibleOptions();
    $default_keys = array_keys($options);
    $default = reset($default_keys);
    $value = isset($items[$delta]->value) ? $items[$delta]->value : $default;

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
    $field_name = $element['#array_parents'][0];

    // Write scope depends on read scope.
    if ($field_name == MUKURTU_PROTOCOL_FIELD_NAME_WRITE_SCOPE) {
      $write_scope = $element['#value'];
      $read_scope = $form_state->getValue(MUKURTU_PROTOCOL_FIELD_NAME_READ_SCOPE);
      if (isset($read_scope[0]['value'])) {
        // Read scope is personal, only valid write scope is default.
        if ($read_scope[0]['value'] == MUKURTU_PROTOCOL_PERSONAL && $write_scope != 'default') {
          $form_state->setError($element, t('Protocol write scope must be default if read scope is set to personal only'));
        }

        // Read scope is public, only valid write scopes are any/all.
        if ($read_scope[0]['value'] == MUKURTU_PROTOCOL_PUBLIC && $write_scope == 'default') {
          $form_state->setError($element, t('Protocol write scope must be "any" or "all" if read scope is set to public'));
        }
      }
    }
  }

}
