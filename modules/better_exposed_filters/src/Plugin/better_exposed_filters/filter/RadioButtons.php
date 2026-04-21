<?php

namespace Drupal\better_exposed_filters\Plugin\better_exposed_filters\filter;

use Drupal\better_exposed_filters\Attribute\FiltersWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\better_exposed_filters\BetterExposedFiltersHelper;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Default widget implementation.
 */
#[FiltersWidget(
  id: 'bef',
  title: new TranslatableMarkup('Checkboxes/Radio Buttons'),
)]
class RadioButtons extends FilterWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return parent::defaultConfiguration() + [
      'select_all_none' => FALSE,
      'select_all_none_nested' => FALSE,
      'display_inline' => FALSE,
      'soft_limit' => 0,
      'soft_limit_label_less' => '',
      'soft_limit_label_more' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    /** @var \Drupal\views\Plugin\views\filter\FilterPluginBase $filter */
    $filter = $this->handler;

    $form = parent::buildConfigurationForm($form, $form_state);

    $form['select_all_none'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add select all/none links'),
      '#default_value' => !empty($this->configuration['select_all_none']),
      '#disabled' => !$filter->options['expose']['multiple'],
      '#description' => $this->t('Add a "Select All/None" link when rendering the exposed filter using checkboxes. If this option is disabled, edit the filter and check the "Allow multiple selections".'
      ),
    ];

    $form['select_all_none_nested'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add nested all/none selection'),
      '#default_value' => !empty($this->configuration['select_all_none_nested']),
      '#disabled' => (!$filter->options['expose']['multiple']) || (isset($filter->options['hierarchy']) && !$filter->options['hierarchy']),
      '#description' => $this->t('When a parent checkbox is checked, check all its children. If this option is disabled, edit the filter and check "Allow multiple selections" and edit the filter settings and check "Show hierarchy in dropdown".'
      ),
    ];

    $form['display_inline'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display inline'),
      '#default_value' => !empty($this->configuration['display_inline']),
      '#description' => $this->t('Display checkbox/radio options inline.'
      ),
    ];

    $options = [50, 40, 30, 20, 15, 10, 5, 3];
    $form['soft_limit'] = [
      '#type' => 'select',
      '#title' => $this->t('Soft limit'),
      '#default_value' => $this->configuration['soft_limit'] ?: 0,
      '#options' => array_combine($options, $options),
      '#empty_option' => $this->t('No limit'),
      "#empty_value" => 0,
      '#description' => $this->t('Limit the number of displayed items via JavaScript.'),
    ];
    $form['soft_limit_label_less'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Show less label'),
      '#description' => $this->t('This text will be used for "Show less" link.'),
      '#default_value' => $this->configuration['soft_limit_label_less'] ?: t('Show less'),
      '#states' => [
        'invisible' => [
          ':input[name="exposed_form_options[bef][filter][' . $filter->field . '][configuration][soft_limit]"]' =>
            [
              'value' => 0,
            ],
        ],
      ],
    ];
    $form['soft_limit_label_more'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Show more label'),
      '#description' => $this->t('This text will be used for "Show more" link.'),
      '#default_value' => $this->configuration['soft_limit_label_more'] ?: t('Show more'),
      '#states' => [
        'invisible' => [
          ':input[name="exposed_form_options[bef][filter][' . $filter->field . '][configuration][soft_limit]"]' =>
            [
              'value' => 0,
            ],
        ],
      ],
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
    // configurable.
    $field_id = $filter->options['is_grouped'] ? $filter->options['group_info']['identifier'] : $filter->options['expose']['identifier'];

    $input = $form_state->getUserInput();
    foreach ($input as $key => $value) {
      if (is_array($value)) {
        $value = array_filter($value, function ($item) {
          return !($item === '' || $item === NULL || $item === 0 || $item === '0');
        });

        if (empty($value)) {
          unset($input[$key]);
        }
        else {
          $input[$key] = $value;
        }
      }
      else {
        if (is_null($value)) {
          unset($input[$key]);
        }
      }
    }
    $form_state->setUserInput($input);

    parent::exposedFormAlter($form, $form_state);
    // If expose filters with operator enable.
    if (!empty($form[$field_id . '_wrapper'][$field_id])) {
      // Clean up filters that pass objects as options instead of strings.
      if (!empty($form[$field_id . '_wrapper'][$field_id]['#options'])) {
        $form[$field_id . '_wrapper'][$field_id]['#options'] = BetterExposedFiltersHelper::flattenOptions($form[$field_id . '_wrapper'][$field_id]['#options']);
      }

      // Support rendering hierarchical checkboxes/radio buttons (e.g. taxonomy
      // terms).
      if (!empty($filter->options['hierarchy'])) {
        $form[$field_id . '_wrapper'][$field_id]['#bef_nested'] = TRUE;
      }

      // Display inline.
      $form[$field_id . '_wrapper'][$field_id]['#bef_display_inline'] = $this->configuration['display_inline'];

      // Render as checkboxes if filter allows multiple selections.
      if (!empty($form[$field_id . '_wrapper'][$field_id]['#multiple'])) {
        $form[$field_id . '_wrapper'][$field_id]['#theme'] = 'bef_checkboxes';
        $form[$field_id . '_wrapper'][$field_id]['#type'] = 'checkboxes';

        // Show all/none option.
        $form[$field_id . '_wrapper'][$field_id]['#bef_select_all_none'] = $this->configuration['select_all_none'];
        $form[$field_id . '_wrapper'][$field_id]['#bef_select_all_none_nested'] = $this->configuration['select_all_none_nested'];

        // Attach the JS (@see /js/bef_select_all_none.js)
        $form['#attached']['library'][] = 'better_exposed_filters/select_all_none';
      }
      // Else render as radio buttons.
      else {
        $form[$field_id . '_wrapper'][$field_id]['#theme'] = 'bef_radios';
        $form[$field_id . '_wrapper'][$field_id]['#type'] = 'radios';
      }
    }
    elseif (!empty($form[$field_id])) {
      // Clean up filters that pass objects as options instead of strings.
      if (!empty($form[$field_id]['#options'])) {
        $form[$field_id]['#options'] = BetterExposedFiltersHelper::flattenOptions($form[$field_id]['#options']);
      }

      // Support rendering hierarchical checkboxes/radio buttons (e.g. taxonomy
      // terms).
      if (!empty($filter->options['hierarchy'])) {
        $form[$field_id]['#bef_nested'] = TRUE;
      }

      // Display inline.
      $form[$field_id]['#bef_display_inline'] = $this->configuration['display_inline'];

      // Render as checkboxes if filter allows multiple selections or filter
      // is already trying to render checkboxes.
      if (!empty($form[$field_id]['#multiple']) || $form[$field_id]['#type'] === 'checkboxes') {
        $form[$field_id]['#theme'] = 'bef_checkboxes';
        $form[$field_id]['#type'] = 'checkboxes';

        // Show all/none option.
        $form[$field_id]['#bef_select_all_none'] = $this->configuration['select_all_none'];
        $form[$field_id]['#bef_select_all_none_nested'] = $this->configuration['select_all_none_nested'];

        // Attach the JS (@see /js/bef_select_all_none.js)
        $form['#attached']['library'][] = 'better_exposed_filters/select_all_none';
      }
      // Else render as radio buttons.
      else {
        $form[$field_id]['#theme'] = 'bef_radios';
        $form[$field_id]['#type'] = 'radios';
      }
    }

    $soft_limit = (int) $this->configuration['soft_limit'];
    if ($soft_limit !== 0) {
      $form['#attached']['library'][] = 'better_exposed_filters/soft_limit';
      $form['#attached']['drupalSettings']['better_exposed_filters']['soft_limit'][$field_id]['limit'] = $soft_limit;
      $form['#attached']['drupalSettings']['better_exposed_filters']['soft_limit'][$field_id]['show_less'] = $this->configuration['soft_limit_label_less'];
      $form['#attached']['drupalSettings']['better_exposed_filters']['soft_limit'][$field_id]['show_more'] = $this->configuration['soft_limit_label_more'];

      $is_checkboxes = !empty($form[$field_id]['#multiple']);
      $is_hierarchical = isset($filter->options["hierarchy"]) && $filter->options["hierarchy"];
      $list_selector = $is_checkboxes ? '.bef-checkboxes' : '.form-radios';
      $item_selector = $is_checkboxes ? '.js-form-type-checkbox' : '.js-form-type-radio';
      if ($is_hierarchical) {
        $list_selector = $list_selector . ' > ul';
        $item_selector = ' > li';
      }
      $form['#attached']['drupalSettings']['better_exposed_filters']['soft_limit'][$field_id]['list_selector'] = $list_selector;
      $form['#attached']['drupalSettings']['better_exposed_filters']['soft_limit'][$field_id]['item_selector'] = $item_selector;
    }
  }

}
