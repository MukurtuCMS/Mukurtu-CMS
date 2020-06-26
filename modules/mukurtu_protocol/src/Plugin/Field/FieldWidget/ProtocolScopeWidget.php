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
    $element += [
      '#type' => 'radios',
      '#title' => $this->t('Who can view this content?'),
      '#options' => [
        'personal' => $this->t('Only me, this content is not ready to be shared.'),
        'public' => $this->t('Anyone, this is public content.'),
        'any' => $this->t('This content may be shared with members of ANY protocols listed.'),
        'all' => $this->t('This content may only be shared with members belonging to ALL protocols listed.'),
      ],
      '#attributes' => [
        'name' => $this->fieldDefinition->getName() . '_input',
      ],
      '#element_validate' => [
        [static::class, 'validate'],
      ],
    ];

    return ['value' => $element];
  }

  /**
   * Validate the color text field.
   */
  public static function validate($element, FormStateInterface $form_state) {
  }

}
