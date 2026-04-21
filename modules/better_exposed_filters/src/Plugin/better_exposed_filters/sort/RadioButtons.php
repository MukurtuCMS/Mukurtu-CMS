<?php

namespace Drupal\better_exposed_filters\Plugin\better_exposed_filters\sort;

use Drupal\better_exposed_filters\Attribute\SortWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Radio Buttons sort widget implementation.
 */
#[SortWidget(
  id: 'bef',
  title: new TranslatableMarkup('Radio Buttons'),
)]
class RadioButtons extends SortWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function exposedFormAlter(array &$form, FormStateInterface $form_state): void {
    parent::exposedFormAlter($form, $form_state);

    foreach ($this->sortElements as $element) {
      if (!empty($form[$element])) {
        $form[$element]['#theme'] = 'bef_radios';
        $form[$element]['#type'] = 'radios';
      }
    }
  }

}
