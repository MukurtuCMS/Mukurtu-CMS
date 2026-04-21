<?php

namespace Drupal\better_exposed_filters\Plugin\better_exposed_filters\filter;

use Drupal\better_exposed_filters\Attribute\FiltersWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Default widget implementation.
 */
#[FiltersWidget(
  id: 'bef_hidden',
  title: new TranslatableMarkup('Hidden'),
)]
class Hidden extends FilterWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function exposedFormAlter(array &$form, FormStateInterface $form_state): void {
    $field_id = $this->getExposedFilterFieldId();

    parent::exposedFormAlter($form, $form_state);

    if (empty($form[$field_id]['#multiple'])) {
      // Single entry filters can simply be changed to a different element
      // type.
      $form[$field_id]['#type'] = 'hidden';
    }
    else {
      // Hide the label.
      $form['#info']["filter-$field_id"]['label'] = '';
      $form[$field_id]['#title'] = '';

      // Use BEF's preprocess and template to output the hidden elements.
      $form[$field_id]['#theme'] = 'bef_hidden';
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(mixed $filter = NULL, array $filter_options = []): bool {
    $is_applicable = parent::isApplicable($filter, $filter_options);

    if ((is_a($filter, 'Drupal\views\Plugin\views\filter\Date') || !empty($filter->date_handler)) && !$filter->isAGroup()) {
      $is_applicable = TRUE;
    }

    return $is_applicable;
  }

}
