<?php

namespace Drupal\better_exposed_filters\Plugin\better_exposed_filters\filter;

use Drupal\better_exposed_filters\Attribute\FiltersWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Single on/off widget implementation.
 */
#[FiltersWidget(
  id: 'bef_single',
  title: new TranslatableMarkup('Single On/Off Checkbox'),
)]
class Single extends FilterWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return parent::defaultConfiguration() + [
      'treat_as_false' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(mixed $filter = NULL, array $filter_options = []): bool {
    /** @var \Drupal\views\Plugin\views\filter\FilterPluginBase $filter */
    $is_applicable = FALSE;

    // Sanity check to ensure we have a filter to work with.
    if (is_null($filter)) {
      return FALSE;
    }

    if (is_a($filter, 'Drupal\views\Plugin\views\filter\BooleanOperator') || ($filter->isAGroup() && count($filter->options['group_info']['group_items']) == 1)) {
      $is_applicable = TRUE;
    }

    return $is_applicable;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['treat_as_false'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Check to treat field as FALSE'),
      '#default_value' => !empty($this->configuration['treat_as_false']),
      '#description' => $this->t('A boolean field can be three values (TRUE, FALSE, ANY). So when unchecked default will be act like "ANY" but this setting will treat the filter as FALSE.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function exposedFormAlter(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\views\Plugin\views\filter\FilterPluginBase $filter */
    $filter = $this->handler;
    // Form element is designated by the element ID which is user-
    // configurable, and stored differently for grouped filters.
    $exposed_id = $filter->options['expose']['identifier'];
    $field_id = $this->getExposedFilterFieldId();

    parent::exposedFormAlter($form, $form_state);

    if (!empty($form[$field_id])) {
      // Views populates missing values in $form_state['input'] with the
      // defaults and a checkbox does not appear in $_GET (or $_POST) so it
      // will appear to be missing when a user submits a form. Because of
      // this, instead of unchecking the checkbox value will revert to the
      // default. More, the default value for select values (i.e. 'Any') is
      // reused which results in the checkbox always checked.
      $input = $form_state->getUserInput();

      // The input value ID is not always consistent.
      // Prioritize the field ID, but default to exposed ID.
      // @todo Remove $exposed_id once
      //   https://www.drupal.org/project/drupal/issues/288429 is fixed.
      $input_value = $input[$field_id] ?? ($input[$exposed_id] ?? NULL);

      // Force checkbox submission with fallback value.
      $form[$field_id . '_hidden'] = [
        '#type' => 'hidden',
        '#value' => 0,
        '#attributes' => ['name' => $field_id],
        '#weight' => $form[$field_id]['#weight'] ?? 0,
      ];

      $form[$field_id]['#type'] = 'checkbox';
      $form[$field_id]['#attributes']['class'][] = 'single-checkbox';
      $form[$field_id]['#return_value'] = 1;

      // Determine value from user input.
      if (isset($input_value)) {
        $false = 0;
        if ($this->configuration['treat_as_false']) {
          $false = FALSE;
        }
        $form[$field_id]['#value'] = ((string) $input_value === '1') ? 1 : $false;
      }
      else {
        $form[$field_id]['#value'] = 0;
      }
    }
  }

}
