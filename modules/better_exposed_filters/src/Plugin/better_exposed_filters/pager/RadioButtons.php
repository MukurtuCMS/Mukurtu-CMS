<?php

namespace Drupal\better_exposed_filters\Plugin\better_exposed_filters\pager;

use Drupal\better_exposed_filters\Attribute\PagerWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Radio Buttons pager widget implementation.
 */
#[PagerWidget(
  id: 'bef',
  title: new TranslatableMarkup('Radio Buttons'),
)]
class RadioButtons extends PagerWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function exposedFormAlter(array &$form, FormStateInterface $form_state): void {
    parent::exposedFormAlter($form, $form_state);

    if (!empty($form['items_per_page'])) {
      $form['items_per_page']['#type'] = 'radios';
      $form['items_per_page']['#prefix'] = '<div class="bef-sortby bef-select-as-radios">';
      $form['items_per_page']['#suffix'] = '</div>';
    }
  }

}
