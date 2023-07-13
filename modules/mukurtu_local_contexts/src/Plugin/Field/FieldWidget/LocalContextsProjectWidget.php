<?php

namespace Drupal\mukurtu_local_contexts\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the 'local_contexts_project' field widget.
 *
 * @FieldWidget(
 *   id = "local_contexts_project",
 *   label = @Translation("Local Contexts Project Widget"),
 *   field_types = {"local_contexts_project"},
 * )
 */
class LocalContextsProjectWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    $element['value'] = $element + [
      '#type' => 'textfield',
      '#default_value' => isset($items[$delta]->value) ? $items[$delta]->value : NULL,
    ];

    return $element;
  }

}
