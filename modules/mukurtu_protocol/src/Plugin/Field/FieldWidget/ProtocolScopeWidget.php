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

    $element['value'] = $element + [
      '#type' => 'radios',
      '#description' => $this->t('Who can view this content?'),
      '#options' => [
        'personal' => $this->t('Only me, this content is not ready to be shared.'),
        'public' => $this->t('Anyone, this is public content.'),
        'any' => $this->t('This content may be shared with members of ANY protocols listed.'),
        'all' => $this->t('This content may only be shared with members belonging to ALL protocols listed.'),
      ],
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
    $value = $element['#value'];
    //dpm("Validate $value");
  }

}
