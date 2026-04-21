<?php

namespace Drupal\better_exposed_filters\Plugin\better_exposed_filters\sort;

use Drupal\better_exposed_filters\Attribute\SortWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Default widget implementation.
 */
#[SortWidget(
  id: 'default',
  title: new TranslatableMarkup('Default'),
)]
class DefaultWidget extends SortWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function exposedFormAlter(array &$form, FormStateInterface $form_state): void {
    parent::exposedFormAlter($form, $form_state);

    foreach ($this->sortElements as $element) {
      if (!empty($form[$element])) {
        $form[$element]['#type'] = 'select';
        $form[$element]['#default_value'] = $form[$element]['#default_value'] ?? '';
      }
    }
  }

}
