<?php

namespace Drupal\better_exposed_filters\Plugin\better_exposed_filters\filter;

use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\better_exposed_filters\BetterExposedFiltersHelper;
use Drupal\better_exposed_filters\Plugin\BetterExposedFiltersWidgetBase;
use Drupal\better_exposed_filters\Plugin\BetterExposedFiltersWidgetInterface;
use Drupal\views\Plugin\views\filter\NumericFilter;
use Drupal\views\Plugin\views\filter\StringFilter;

/**
 * Base class for Better exposed filters widget plugins.
 */
abstract class FilterWidgetBase extends BetterExposedFiltersWidgetBase implements BetterExposedFiltersWidgetInterface {

  use StringTranslationTrait;

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

    // Check various filter types and determine what options are available.
    if (is_a($filter, 'Drupal\views\Plugin\views\filter\StringFilter') || is_a($filter, 'Drupal\views\Plugin\views\filter\InOperator')) {
      if (in_array($filter->operator, ['in', 'or', 'and', 'not'])) {
        $is_applicable = TRUE;
      }
      if (in_array($filter->operator, ['empty', 'not empty'])) {
        $is_applicable = TRUE;
      }
    }

    if (is_a($filter, 'Drupal\views\Plugin\views\filter\BooleanOperator')) {
      $is_applicable = TRUE;
    }

    if (is_a($filter, 'Drupal\taxonomy\Plugin\views\filter\TaxonomyIndexTid')) {
      // Autocomplete and dropdown taxonomy filter are both instances of
      // TaxonomyIndexTid, but we can't show BEF options for the autocomplete
      // widget.
      if ($filter_options['type'] == 'select') {
        $is_applicable = TRUE;
      }
    }

    if ($filter->isAGroup()) {
      $is_applicable = TRUE;
    }

    if (is_a($filter, 'Drupal\search_api\Plugin\views\filter\SearchApiFulltext')) {
      $is_applicable = TRUE;
    }

    if (is_a($filter, 'Drupal\facets_exposed_filters\Plugin\views\filter\FacetsFilter')) {
      $is_applicable = TRUE;
    }

    return $is_applicable;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return parent::defaultConfiguration() + [
      'advanced' => [
        'collapsible' => FALSE,
        'collapsible_disable_automatic_open' => FALSE,
        'open_by_default' => FALSE,
        'is_secondary' => FALSE,
        'placeholder_text' => '',
        'rewrite' => [
          'filter_rewrite_values' => '',
          'filter_rewrite_values_key' => FALSE,
        ],
        'sort_options' => FALSE,
        'sort_options_method' => 'alphabetical_asc',
        'sort_options_natural' => TRUE,
        'hide_label' => FALSE,
        'field_classes' => '',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    /** @var \Drupal\views\Plugin\views\filter\FilterPluginBase $filter */
    $filter = $this->handler;
    $filter_widget_type = $this->getExposedFilterWidgetType();

    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced filter options'),
      '#weight' => 10,
    ];

    // Allow users to sort options.
    if ($this->isFieldSortingSupported($filter)) {
      $form['advanced']['sort_options'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Sort filter options'),
        '#default_value' => !empty($this->configuration['advanced']['sort_options']),
        '#description' => $this->t('Enable custom sorting of filter options. Note: This feature is not available for entity reference fields (taxonomy, users, content) due to technical limitations.'),
      ];

      $form['advanced']['sort_options_method'] = [
        '#type' => 'select',
        '#title' => $this->t('Sort method'),
        '#default_value' => $this->configuration['advanced']['sort_options_method'],
        '#options' => [
          'alphabetical_asc' => $this->t('Ascending'),
          'alphabetical_desc' => $this->t('Descending'),
          'key_asc' => $this->t('By value key (ascending)'),
          'key_desc' => $this->t('By value key (descending)'),
          'result_count' => $this->t('By result count (if available)'),
        ],
        '#description' => $this->t('Choose how the filter options should be sorted.'),
        '#states' => [
          'visible' => [
            ':input[name="exposed_form_options[bef][filter][' . $filter->options['id'] . '][configuration][advanced][sort_options]"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form['advanced']['sort_options_natural'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Use natural sorting'),
        '#default_value' => !empty($this->configuration['advanced']['sort_options_natural']),
        '#description' => $this->t('Use natural sorting algorithm (e.g., "Item 2" comes before "Item 10"). This works better with numbers and mixed content.'),
        '#states' => [
          'visible' => [
            ':input[name="exposed_form_options[bef][filter][' . $filter->options['id'] . '][configuration][advanced][sort_options]"]' => ['checked' => TRUE],
            0 => [':input[name="exposed_form_options[bef][filter][' . $filter->options['id'] . '][configuration][advanced][sort_options_method]"]' => ['value' => 'alphabetical_asc']],
            1 => 'or',
            2 => [':input[name="exposed_form_options[bef][filter][' . $filter->options['id'] . '][configuration][advanced][sort_options_method]"]' => ['value' => 'alphabetical_desc']],
          ],
        ],
      ];
    }
    else {
      // Provide information about unsupported field types.
      $form['advanced']['sort_options_unsupported'] = [
        '#type' => 'item',
        '#title' => $this->t('Sorting options'),
        '#description' => $this->t('Custom sorting is not available for this filter type. Entity reference fields (taxonomy terms, users, content) cannot be easily reordered due to deep integration with Drupal core. Consider using JavaScript-based client-side sorting if needed.'),
        '#wrapper_attributes' => [
          'class' => ['messages', 'messages--warning'],
        ],
      ];
    }

    // Allow users to specify placeholder text.
    $supported_types = ['entity_autocomplete', 'textfield'];
    if (in_array($filter_widget_type, $supported_types)) {
      $form['advanced']['placeholder_text'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Placeholder text'),
        '#description' => $this->t('Text to be shown in the text field until it is edited. Leave blank for no placeholder to be set.'),
        '#default_value' => $this->configuration['advanced']['placeholder_text'],
      ];
    }

    // Allow rewriting of filter options for any filter. String and numeric
    // filters allow unlimited filter options via textfields, so we can't
    // offer rewriting for those.
    // @todo check other core filter types
    if ((!$filter instanceof StringFilter && !$filter instanceof NumericFilter) || $filter->isAGroup()) {
      $form['advanced']['rewrite']['filter_rewrite_values'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Rewrite the text displayed'),
        '#default_value' => $this->configuration['advanced']['rewrite']['filter_rewrite_values'],
        '#description' => $this->t('Use this field to rewrite the filter options displayed. Use the format of current_text|replacement_text, one replacement per line. For example: <pre>
  Current|Replacement
  On|Yes
  Off|No
  </pre> Leave the replacement text blank to remove an option altogether. If using hierarchical taxonomy filters, do not including leading hyphens in the current text.
          '),
      ];
      $form['advanced']['rewrite']['filter_rewrite_values_key'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Rewrite the text displayed based on key'),
        '#default_value' => $this->configuration['advanced']['rewrite']['filter_rewrite_values_key'],
        '#description' => $this->t('Change behavior of "Rewrite the text displayed" to overwrite labels based on option key. eg. All|New label'),
      ];
    }

    $form['advanced']['hide_label'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide the label'),
      '#description' => $this->t('Hides the label visually, so it is still usable for accessibility purposes.'),
      '#default_value' => !empty($this->configuration['advanced']['hide_label']),
    ];

    // Allow any filter to be collapsible.
    $form['advanced']['collapsible'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Make filter options collapsible'),
      '#default_value' => !empty($this->configuration['advanced']['collapsible']),
      '#description' => $this->t(
        'Puts the filter options in a collapsible details element.'
      ),
    ];

    // Allow any filter to be collapsible.
    $form['advanced']['collapsible_disable_automatic_open'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable the automatic opening of collapsed filters with selections'),
      '#default_value' => !empty($this->configuration['advanced']['collapsible_disable_automatic_open']),
      '#description' => $this->t(
        'When a selection is made, by default the collapsed filter will be set to open. If you provide an alternative means for the user to see filter selections, you can the default open behavior by enabling this.'
      ),
      '#states' => [
        'visible' => [
          ':input[name="exposed_form_options[bef][filter][' . $filter->options['id'] . '][configuration][advanced][collapsible]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Make filter open by default.
    $form['advanced']['open_by_default'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Open by default'),
      '#default_value' => !empty($this->configuration['advanced']['open_by_default']),
      '#description' => $this->t(
        'Collapsible filter will be opened by default. It can be collapsed by the user if they wish, but after the page reload (or AJAX view refresh) it will be opened again.'
      ),
      '#states' => [
        'visible' => [
          ':input[name="exposed_form_options[bef][filter][' . $filter->options['id'] . '][configuration][advanced][collapsible]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Allow any filter to be moved into the secondary options' element.
    $form['advanced']['is_secondary'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('This is a secondary option'),
      '#default_value' => !empty($this->configuration['advanced']['is_secondary']),
      '#states' => [
        'visible' => [
          ':input[name="exposed_form_options[bef][general][allow_secondary]"]' => ['checked' => TRUE],
        ],
      ],
      '#description' => $this->t('Places this element in the secondary options portion of the exposed form.'),
    ];

    $form['advanced']['field_classes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom classes added to the field element'),
      '#default_value' => $this->configuration['advanced']['field_classes'],
      '#description' => $this->t('To add multiple classes separate them with a space'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function exposedFormAlter(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\views\Plugin\views\filter\FilterPluginBase $filter */
    $filter = $this->handler;
    $filter_id = $filter->options['expose']['identifier'];
    $field_id = $this->getExposedFilterFieldId();
    $is_collapsible = $this->configuration['advanced']['collapsible'];
    $collapsible_disable_automatic_open = $this->configuration['advanced']['collapsible_disable_automatic_open'];
    $open_by_default = $this->configuration['advanced']['open_by_default'] ?? FALSE;
    $is_secondary = !empty($form['secondary']) && $this->configuration['advanced']['is_secondary'];

    // Sort options alphabetically.
    if ($this->configuration['advanced']['sort_options']) {
      $form[$field_id]['#pre_process'][] = [$this, 'processCustomSortedOptions'];
    }

    // Check for placeholder text.
    if (!empty($this->configuration['advanced']['placeholder_text'])) {
      // @todo Add token replacement for placeholder text.
      $form[$field_id]['#placeholder'] = $this->configuration['advanced']['placeholder_text'];
    }

    // Visually hidden label.
    if (!empty($this->configuration['advanced']['hide_label'])) {
      // Check if the field was wrapped with a fieldset.
      // @see \Drupal\views\Plugin\views\filter\FilterPluginBase::buildExposedForm
      // @see \Drupal\views\Plugin\views\filter\FilterPluginBase::buildValueWrapper
      if (empty($form["{$field_id}_wrapper"][$field_id])) {
        $form[$field_id]['#title_display'] = 'invisible';
      }
      else {
        $form["{$field_id}_wrapper"]['#title_display'] = 'invisible';
      }
    }

    // Handle filter value rewrites.
    if (!empty($form[$field_id]['#options']) && $this->configuration['advanced']['rewrite']['filter_rewrite_values']) {
      // Reorder options based on rewrite values, if sort options is disabled.
      $form[$field_id]['#options'] = BetterExposedFiltersHelper::rewriteOptions($form[$field_id]['#options'], $this->configuration['advanced']['rewrite']['filter_rewrite_values'], !$this->configuration['advanced']['sort_options'], $this->configuration['advanced']['rewrite']['filter_rewrite_values_key']);
      // @todo what is $selected?
      // if (isset($selected) &&
      // !isset($form[$field_id]['#options'][$selected])) {
      // Avoid "Illegal choice" errors.
      // $form[$field_id]['#default_value'] = NULL;
      // }
    }

    // Identify all exposed filter elements.
    $identifier = $filter_id;
    $exposed_label = $filter->options['expose']['label'];
    $exposed_description = $filter->options['expose']['description'];

    if ($filter->isAGroup()) {
      $identifier = $filter->options['group_info']['identifier'];
      $exposed_label = $filter->options['group_info']['label'];
      $exposed_description = $filter->options['group_info']['description'];
    }

    // If selected, collect our collapsible filter form element and put it in
    // a details' element.
    if (!empty($form[$field_id]) || !empty($form["{$field_id}_wrapper"])) {
      if ($is_collapsible) {
        $details = [];
        $details[$field_id . '_collapsible'] = [
          '#type' => 'details',
          '#title' => $exposed_label,
          '#description' => $exposed_description,
          '#attributes' => [
            'class' => ['form-item'],
          ],
          '#collapsible_disable_automatic_open' => $collapsible_disable_automatic_open,
        ];

        if (!empty($open_by_default)) {
          $details[$field_id . '_collapsible']['#open'] = TRUE;
        }

        // Retain same weight as the original fields for details.
        $pos = array_search($field_id, array_keys($form));
        $form = array_merge(array_slice($form, 0, $pos), $details, array_slice($form, $pos));
      }
    }

    // Add possible field wrapper to validate for "between" operator.
    $element_wrapper = $field_id . '_wrapper';

    $filter_elements = [
      $identifier,
      $element_wrapper,
      $filter->options['expose']['operator_id'],
    ];

    // Iterate over all exposed filter elements.
    foreach ($filter_elements as $element) {
      // Sanity check to make sure the element exists.
      if (empty($form[$element])) {
        continue;
      }

      // "Between" operator fields to validate for.
      $fields = ['min', 'max'];

      // Check if the element is a part of a wrapper.
      $wrapper_array = $form[$element];
      if ($element === $element_wrapper) {
        // Determine if wrapper element has min or max fields or if
        // collapsible, if so then update type.
        if (array_intersect($fields, array_keys($wrapper_array[$field_id])) || $is_collapsible) {
          $form[$element] = [
            '#type' => 'container',
            $element => $wrapper_array,
          ];
        }
      }
      else {
        // Determine if element has min or max child fields,
        // if so then update type.
        if (array_intersect($fields, array_keys($form[$field_id]))) {
          $form[$element] = [
            '#type' => 'container',
            $element => $wrapper_array,
          ];
        }
      }

      // Handle secondary elements first.
      if ($is_secondary) {
        if ($is_collapsible) {
          $this->addElementToGroup($form, $form_state, $field_id . '_collapsible', 'secondary');
        }
        else {
          $this->addElementToGroup($form, $form_state, $element, 'secondary');
        }
      }

      // Move collapsible elements.
      if ($is_collapsible) {
        $this->addElementToGroup($form, $form_state, $element, $field_id . '_collapsible');
      }
      else {
        $form[$element]['#title'] = $exposed_label;
        $form[$element]['#description'] = $exposed_description;
      }

      // Add custom classes to the field form element.
      if ($this->configuration['advanced']['field_classes']) {
        $field_classes = $this->configuration['advanced']['field_classes'];
        $field_classes_array = explode(' ', $field_classes);
        foreach ($field_classes_array as $class) {
          $form[$element]['#attributes']['class'][] = $class;
        }
      }

      // Finally, add some metadata to the form element.
      $this->addContext($form[$element]);
    }
  }

  /**
   * Check if a filter supports custom sorting.
   *
   * @param \Drupal\views\Plugin\views\filter\FilterPluginBase $filter
   *   The filter plugin.
   *
   * @return bool
   *   TRUE if the filter supports custom sorting, FALSE otherwise.
   */
  protected function isFieldSortingSupported($filter): bool {
    // Support for InOperator-based filters (list fields, boolean, etc.)
    if (is_a($filter, 'Drupal\views\Plugin\views\filter\InOperator')) {
      return TRUE;
    }

    // Support for BooleanOperator.
    if (is_a($filter, 'Drupal\views\Plugin\views\filter\BooleanOperator')) {
      return TRUE;
    }

    // Support for String filters that have options (not free text)
    if (is_a($filter, 'Drupal\views\Plugin\views\filter\StringFilter')) {
      // Only if it has predefined options and uses 'in' operators.
      return in_array($filter->operator, ['in', 'or', 'and', 'not']);
    }

    // Exclude entity reference filters (the problematic ones)
    $excluded_classes = [
      'Drupal\taxonomy\Plugin\views\filter\TaxonomyIndexTid',
      'Drupal\views\Plugin\views\filter\EntityReference',
      'Drupal\user\Plugin\views\filter\UserReference',
      'Drupal\node\Plugin\views\filter\NodeReference',
    ];

    foreach ($excluded_classes as $excluded_class) {
      if (is_a($filter, $excluded_class)) {
        return FALSE;
      }
    }

    // Support for grouped filters.
    if ($filter->isAGroup()) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Sorts the options for a given form element with enhanced methods.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   *
   * @return array
   *   The altered element.
   */
  public function processCustomSortedOptions(array $element, FormStateInterface $form_state): array {
    $options = &$element['#options'];
    $sort_method = $this->configuration['advanced']['sort_options_method'] ?? 'alphabetical_asc';
    $natural_sort = $this->configuration['advanced']['sort_options_natural'] ?? TRUE;

    // Find and preserve "- Any -" or similar default option.
    $any_option = FALSE;
    $any_key = FALSE;

    if (empty($element['#required'])) {
      // Look for common "any" option patterns.
      foreach ($options as $key => $value) {
        $value_string = is_object($value) ? (string) $value : $value;
        // Check for common "any" patterns.
        if ($key === '' || $key === 'All' ||
            strpos($value_string, '- Any') === 0 ||
            strpos($value_string, 'All') === 0 ||
            strpos($value_string, '- Select') === 0) {
          $any_option = [$key => $value];
          $any_key = $key;
          unset($options[$key]);
          break;
        }
      }
    }

    switch ($sort_method) {
      case 'alphabetical_asc':
        $options = BetterExposedFiltersHelper::sortOptionsCustom($options, 'alpha', 'asc', $natural_sort);
        break;

      case 'alphabetical_desc':
        $options = BetterExposedFiltersHelper::sortOptionsCustom($options, 'alpha', 'desc', $natural_sort);
        break;

      case 'key_asc':
        $options = BetterExposedFiltersHelper::sortOptionsCustom($options, 'key', 'asc');
        break;

      case 'key_desc':
        $options = BetterExposedFiltersHelper::sortOptionsCustom($options, 'key', 'desc');
        break;

      case 'result_count':
        // This would require additional logic to get result counts
        // For now, fall back to alphabetical.
        $options = BetterExposedFiltersHelper::sortOptionsCustom($options, 'alpha', 'asc', $natural_sort);
        break;

      default:
        $options = BetterExposedFiltersHelper::sortOptions($options);
    }

    // Restore the "- Any -" value at the first position.
    if ($any_option && $any_key !== FALSE) {
      $options = $any_option + $options;
    }

    return $element;
  }

  /**
   * Sorts the options for a given form element alphabetically.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   *
   * @return array
   *   The altered element.
   */
  public function processSortedOptions(array $element, FormStateInterface $form_state): array {
    $options = &$element['#options'];

    // Ensure "- Any -" value does not get sorted.
    $any_option = FALSE;
    if ($element['#required']) {
      // We use array_slice to preserve they keys needed to determine the value
      // when using a filter (e.g. taxonomy terms).
      $first_option = array_slice($options, 0, 1, TRUE);

      // Only preserve the first option if it's actually "- Any -"
      // translated or untranslated.
      $first_option_value = reset($first_option);
      if ($first_option_value === '- Any -' || $first_option_value === $this->t('- Any -')) {
        $any_option = $first_option;
        // Array_slice does not modify the existing array, we need to remove the
        // option manually.
        unset($options[key($any_option)]);
      }
    }

    // Not all option arrays will have simple data types. We perform a custom
    // sort in case users want to sort more complex fields
    // (example taxonomy terms).
    if (!empty($element['#nested'])) {
      $delimiter = $element['#nested_delimiter'] ?? '-';
      $options = BetterExposedFiltersHelper::sortNestedOptions($options, $delimiter);
    }
    else {
      $options = BetterExposedFiltersHelper::sortOptions($options);
    }

    // Restore the "- Any -" value at the first position.
    if ($any_option) {
      $options = $any_option + $options;
    }

    return $element;
  }

  /**
   * Helper function to get the unique identifier for the exposed filter.
   *
   * Takes into account grouped filters with custom identifiers.
   */
  protected function getExposedFilterFieldId() {
    /** @var \Drupal\views\Plugin\views\filter\FilterPluginBase $filter */
    $filter = $this->handler;
    $field_id = $filter->options['expose']['identifier'];
    $is_grouped_filter = $filter->options['is_grouped'];

    // Grouped filters store their identifier elsewhere.
    if ($is_grouped_filter) {
      $field_id = $filter->options['group_info']['identifier'];
    }

    return $field_id;
  }

  /**
   * Helper function to get the widget type of the exposed filter.
   *
   * @return string
   *   The type of the form render element use for the exposed filter.
   */
  protected function getExposedFilterWidgetType(): string {
    // We need to dig into the exposed form configuration to retrieve the
    // form type of the filter.
    $form = [];
    $form_state = new FormState();
    $form_state->set('exposed', TRUE);
    /** @var \Drupal\views\Plugin\views\filter\FilterPluginBase $filter */
    $filter = $this->handler;
    $filter->buildExposedForm($form, $form_state);
    $filter_id = $filter->options['expose']['identifier'];

    return $form[$filter_id]['#type'] ?? $form[$filter_id]['value']['#type'] ?? '';
  }

}
