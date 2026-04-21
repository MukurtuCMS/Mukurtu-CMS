<?php

namespace Drupal\tagify\Element;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\Select;

/**
 * Provides a 'select_tagify' form element for a scrolling selection box.
 *
 * Properties:
 * - #options: An associative array of options for the select. Do not use
 *   placeholders that sanitize data in any labels, as doing so will lead to
 *   double-escaping. Each array value can be:
 *   - A single translated string representing an HTML option element, where
 *     the outer array key is the option value and the translated string array
 *     value is the option label. The option value will be visible in the HTML
 *     and can be modified by malicious users, so it should not contain
 *     sensitive information and should be treated as possibly malicious data in
 *     processing.
 *   - An array representing an HTML optgroup element. The outer array key
 *     should be a translated string, and is used as the label for the group.
 *     The inner array contains the options for the group (with the keys as
 *     option values, and translated string values as option labels). Nesting
 *     option groups is not supported.
 *   - An object with an 'option' property. In this case, the outer array key
 *     is ignored, and the contents of the 'option' property are interpreted as
 *     an array of options to be merged with any other regular options and
 *     option groups found in the outer array.
 * - #sort_options: (optional) If set to TRUE (default is FALSE), sort the
 *   options by their labels, after rendering and translation is complete.
 *   Can be set within an option group to sort that group.
 * - #sort_start: (optional) Option index to start sorting at, where 0 is the
 *   first option. Can be used within an option group. If an empty option is
 *   being added automatically (see #empty_option and #empty_value properties),
 *   this defaults to 1 to keep the empty option at the top of the list.
 *   Otherwise, it defaults to 0.
 * - #empty_option: (optional) The label to show for the first default option.
 *   By default, the label is automatically set to "- Select -" for a required
 *   field and "- None -" for an optional field.
 * - #empty_value: (optional) The value for the first default option, which is
 *   used to determine whether the user submitted a value or not.
 *   - If #required is TRUE, this defaults to '' (an empty string).
 *   - If #required is not TRUE and this value isn't set, then no extra option
 *     is added to the select control, leaving the control in a slightly
 *     illogical state, because there's no way for the user to select nothing,
 *     since all user agents automatically preselect the first available
 *     option. But people are used to this being the behavior of select
 *     controls.
 *
 *     @todo Address the above issue in Drupal 8.
 *   - If #required is not TRUE and this value is set (most commonly to an
 *     empty string), then an extra option (see #empty_option above)
 *     representing a "non-selection" is added with this as its value.
 * - #multiple: (optional) Indicates whether one or more options can be
 *   selected. Defaults to FALSE.
 * - #default_value: Must be NULL or not set in case there is no value for the
 *   element yet, in which case a first default option is inserted by default.
 *   Whether this first option is a valid option depends on whether the field
 *   is #required or not.
 * - #required: (optional) Whether the user needs to select an option (TRUE)
 *   or not (FALSE). Defaults to FALSE.
 * - #size: The number of rows in the list that should be visible at one time.
 *
 * Usage example:
 * @code
 * $form['example_select_tagify'] = [
 *   '#type' => 'select_tagify',
 *   '#title' => $this->t('Select a tagify element'),
 *   '#options' => [
 *     '1' => $this->t('One'),
 *     '2' => [
 *       '2.1' => $this->t('Two point one'),
 *       '2.2' => $this->t('Two point two'),
 *     ],
 *     '3' => $this->t('Three'),
 *   ],
 * ];
 * @endcode
 *
 * @FormElement("select_tagify")
 */
class SelectTagify extends Select {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $info = parent::getInfo();
    array_unshift($info['#process'], [static::class, 'processSelectTagify']);
    $info['#mode'] = NULL;
    $info['#identifier'] = NULL;
    $info['#limit'] = 1;
    $info['#mode'] = 'select';
    $info['#multiple'] = FALSE;
    $info['#cardinality'] = 0;
    $info['#match_limit'] = 20;
    $info['#match_operator'] = 'CONTAINS';
    $info['#placeholder'] = '';
    $info['#show_entity_id'] = 0;
    $info['#parent_selection'] = 1;
    return $info;
  }

  /**
   * Adds select tagify functionality to a form element.
   *
   * @param array $element
   *   The form element to process. Properties used:
   *   - #target_type: The ID of the target entity type.
   *   - #selection_handler: The plugin ID of the entity reference selection
   *     handler.
   *   - #selection_settings: An array of settings that will be passed to the
   *     selection handler.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return array
   *   The form element.
   */
  public static function processSelectTagify(&$element, FormStateInterface $form_state, &$complete_form) {
    $element['#attached'] = NestedArray::mergeDeep($element['#attached'] ?? [], [
      'library' => [
        'tagify/default',
      ],
    ]);

    if (_tagify_is_gin_theme_active()) {
      $element['#attached']['library'][] = 'tagify/gin';
    }

    if (_tagify_is_claro_theme_active()) {
      $element['#attached']['library'][] = 'tagify/claro';
    }

    // Tagify settings.
    $element['#attributes']['data-mode'] = $element['#mode'];
    $element['#attributes']['data-identifier'] = $element['#identifier'];
    $element['#attributes']['data-cardinality'] = $element['#cardinality'];
    $element['#attributes']['data-match-operator'] = ($element['#match_operator'] === 'CONTAINS') ? 1 : 0;
    $element['#attributes']['data-match-limit'] = $element['#match_limit'];
    $element['#attributes']['data-placeholder'] = $element['#placeholder'];
    $element['#attributes']['data-show-entity-id'] = $element['#show_entity_id'] ?? '';
    $element['#attributes']['data-parent-selection'] = $element['#parent_selection'] ?? '';

    // Information text.
    $element['#attached']['drupalSettings']['tagify_select']['information_message'] = [
      'limit_tag' => t('Tags are limited to:'),
      'no_matching_suggestions' => t('No matching suggestions found for:'),
    ];

    // Modify sorting for select multiple (unlimited).
    if ($element['#multiple']) {
      $selected_values = array_values($element['#value']);
      // Sort options based on selection order.
      uksort($element['#options'], function ($a, $b) use ($selected_values) {
        return array_search($a, $selected_values) <=> array_search($b, $selected_values);
      });
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function preRenderSelect($element) {
    $element = parent::preRenderSelect($element);
    static::setAttributes($element, ['tagify-select-widget']);
    return $element;
  }

}
