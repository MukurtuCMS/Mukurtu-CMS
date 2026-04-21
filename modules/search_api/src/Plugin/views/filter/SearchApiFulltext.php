<?php

namespace Drupal\search_api\Plugin\views\filter;

use Drupal\views\Attribute\ViewsFilter;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\ParseMode\ParseModePluginManager;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a filter for adding a fulltext search to the view.
 *
 * @ingroup views_filter_handlers
 */
#[ViewsFilter('search_api_fulltext')]
class SearchApiFulltext extends FilterPluginBase {

  use SearchApiFilterTrait;

  /**
   * The list of fields selected for the search.
   *
   * @var array
   */
  public $searchedFields = [];

  /**
   * The parse mode manager.
   *
   * @var \Drupal\search_api\ParseMode\ParseModePluginManager|null
   */
  protected $parseModeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $plugin */
    $plugin = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $plugin->setParseModeManager($container->get('plugin.manager.search_api.parse_mode'));

    return $plugin;
  }

  /**
   * Retrieves the parse mode manager.
   *
   * @return \Drupal\search_api\ParseMode\ParseModePluginManager
   *   The parse mode manager.
   */
  public function getParseModeManager() {
    return $this->parseModeManager ?: \Drupal::service('plugin.manager.search_api.parse_mode');
  }

  /**
   * Sets the parse mode manager.
   *
   * @param \Drupal\search_api\ParseMode\ParseModePluginManager $parse_mode_manager
   *   The new parse mode manager.
   *
   * @return $this
   */
  public function setParseModeManager(ParseModePluginManager $parse_mode_manager) {
    $this->parseModeManager = $parse_mode_manager;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function showOperatorForm(&$form, FormStateInterface $form_state) {
    parent::showOperatorForm($form, $form_state);

    if (!empty($form['operator'])) {
      $form['operator']['#description'] = $this->t('Depending on the parse mode set, some of these options might not work as expected. Either use "@multiple_words" as the parse mode or make sure that the filter behaves as expected for multiple words.', ['@multiple_words' => $this->t('Multiple words')]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function operatorOptions($which = 'title') {
    $options = [];
    foreach ($this->operators() as $id => $info) {
      $options[$id] = $info[$which];
    }

    return $options;
  }

  /**
   * Returns information about the available operators for this filter.
   *
   * @return array[]
   *   An associative array mapping operator identifiers to their information.
   *   The operator information itself is an associative array with the
   *   following keys:
   *   - title: The translated title for the operator.
   *   - short: The translated short title for the operator.
   *   - values: The number of values the operator requires as input.
   */
  public function operators() {
    return [
      'and' => [
        'title' => $this->t('Contains all of these words'),
        'short' => $this->t('and'),
        'values' => 1,
      ],
      'or' => [
        'title' => $this->t('Contains any of these words'),
        'short' => $this->t('or'),
        'values' => 1,
      ],
      'not' => [
        'title' => $this->t('Contains none of these words'),
        'short' => $this->t('not'),
        'values' => 1,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defineOptions() {
    $options = parent::defineOptions();

    $options['operator']['default'] = 'and';
    $options['parse_mode'] = ['default' => 'terms'];
    $options['min_length'] = ['default' => ''];
    $options['fields'] = ['default' => []];
    $options['expose']['contains']['placeholder'] = ['default' => ''];
    $options['expose']['contains']['expose_fields'] = ['default' => FALSE];
    $options['expose']['contains']['searched_fields_id'] = ['default' => ''];
    $options['expose']['contains']['value_maxlength'] = ['default' => 128];
    $options['expose']['contains']['value_max_words'] = ['default' => ''];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultExposeOptions() {
    parent::defaultExposeOptions();

    $this->options['expose']['searched_fields_id'] = $this->options['id'] . '_searched_fields';
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['parse_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Parse mode'),
      '#description' => $this->t('Choose how the search keys will be parsed.'),
      '#options' => [],
      '#default_value' => $this->options['parse_mode'],
    ];
    foreach ($this->getParseModeManager()->getInstances() as $key => $mode) {
      if ($mode->isHidden()) {
        continue;
      }
      $form['parse_mode']['#options'][$key] = $mode->label();
      if ($mode->getDescription()) {
        $states['visible'][':input[name="options[parse_mode]"]']['value'] = $key;
        $form["parse_mode_{$key}_description"] = [
          '#type' => 'item',
          '#title' => $mode->label(),
          '#description' => $mode->getDescription(),
          '#states' => $states,
        ];
      }
    }

    $fields = $this->getFulltextFields();
    if (!empty($fields)) {
      $form['fields'] = [
        '#type' => 'select',
        '#title' => $this->t('Searched fields'),
        '#description' => $this->t('Select the fields that will be searched. If no fields are selected, all available fulltext fields will be searched.'),
        '#options' => $fields,
        '#size' => min(4, count($fields)),
        '#multiple' => TRUE,
        '#default_value' => $this->options['fields'],
      ];
    }
    else {
      $form['fields'] = [
        '#type' => 'value',
        '#value' => [],
      ];
    }
    if (isset($form['expose'])) {
      $form['expose']['#weight'] = -5;
    }

    $form['min_length'] = [
      '#title' => $this->t('Minimum keyword length'),
      '#description' => $this->t('Minimum length of each word in the search keys. Leave empty to allow all words.'),
      '#type' => 'number',
      '#min' => 1,
      '#default_value' => $this->options['min_length'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildExposeForm(&$form, FormStateInterface $form_state) {
    parent::buildExposeForm($form, $form_state);

    $form['expose']['placeholder'] = [
      '#type' => 'textfield',
      '#default_value' => $this->options['expose']['placeholder'],
      '#title' => $this->t('Placeholder'),
      '#size' => 40,
      '#description' => $this->t('Hint text that appears inside the field when empty.'),
    ];

    $form['expose']['value_maxlength'] = [
      '#title' => $this->t('Search field character limit'),
      '#description' => $this->t('Maximum number of characters to allow as keywords input.'),
      '#type' => 'number',
      '#min' => 1,
      '#default_value' => $this->options['expose']['value_maxlength'],
    ];

    $form['expose']['value_max_words'] = [
      '#title' => $this->t('Maximum number of words'),
      '#description' => $this->t('Set a maximum number of words to allow as keywords input.'),
      '#type' => 'number',
      '#min' => 1,
      '#default_value' => $this->options['expose']['value_max_words'],
    ];

    $form['expose']['expose_fields'] = [
      '#type' => 'checkbox',
      '#default_value' => $this->options['expose']['expose_fields'],
      '#title' => $this->t('Expose searched fields'),
      '#description' => $this->t('Expose the list of searched fields. This allows users to narrow the search to the desired fields.'),
    ];
    $form['expose']['searched_fields_id'] = [
      '#type' => 'textfield',
      '#default_value' => $this->options['expose']['searched_fields_id'],
      '#title' => $this->t('Searched fields identifier'),
      '#size' => 40,
      '#description' => $this->t('This will appear in the URL after the ? to identify this searched fields form element.'),
      '#states' => [
        'visible' => [
          ':input[name="options[expose][expose_fields]"]' => ['checked' => TRUE],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildExposedForm(&$form, FormStateInterface $form_state) {
    parent::buildExposedForm($form, $form_state);

    if (empty($this->options['exposed'])) {
      return;
    }

    if ($this->options['expose']['expose_fields']) {
      $fields = $this->getFulltextFields();
      $configured_fields = $this->options['fields'];
      // Only keep the configured fields.
      if (!empty($configured_fields)) {
        $configured_fields = array_flip($configured_fields);
        $fields = array_intersect_key($fields, $configured_fields);
      }

      $searched_fields_identifier = $this->options['id'] . '_searched_fields';
      if (!empty($this->options['expose']['searched_fields_id'])) {
        $searched_fields_identifier = $this->options['expose']['searched_fields_id'];
      }
      $form[$searched_fields_identifier] = [
        '#type' => 'select',
        '#title' => $this->t('Search fields'),
        '#options' => $fields,
        '#multiple' => TRUE,
        '#size' => min(count($fields), 5),
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    parent::valueForm($form, $form_state);

    $exposed = (bool) $form_state->get('exposed');
    $max_length = NULL;
    if ($exposed && $this->options['expose']['value_maxlength']) {
      $max_length = $this->options['expose']['value_maxlength'];
    }
    $form['value'] = [
      '#type' => 'textfield',
      '#title' => !$exposed ? $this->t('Value') : '',
      '#size' => 30,
      '#default_value' => $this->value,
      '#maxlength' => $max_length,
    ];
    if (!empty($this->options['expose']['placeholder'])) {
      $form['value']['#attributes']['placeholder'] = $this->options['expose']['placeholder'];
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function exposedTranslate(&$form, $type) {
    parent::exposedTranslate($form, $type);

    // We use custom validation for "required", so don't want the Form API to
    // interfere.
    // @see ::validateExposed()
    $form['#required'] = FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function validateExposed(&$form, FormStateInterface $form_state) {
    // Only validate exposed input.
    if (empty($this->options['exposed']) || empty($this->options['expose']['identifier'])) {
      return;
    }

    // Store searched fields.
    $this->searchedFields = [];
    if ($this->options['expose']['expose_fields']) {
      $searched_fields_identifier = $this->options['id'] . '_searched_fields';
      if (!empty($this->options['expose']['searched_fields_id'])) {
        $searched_fields_identifier = $this->options['expose']['searched_fields_id'];
      }
      $this->searchedFields = $form_state->getValue($searched_fields_identifier, []);
    }

    $identifier = $this->options['expose']['identifier'];
    $input = &$form_state->getValue($identifier, '');

    if ($this->options['is_grouped'] && isset($this->options['group_info']['group_items'][$input])) {
      $this->operator = $this->options['group_info']['group_items'][$input]['operator'];
      $input = &$this->options['group_info']['group_items'][$input]['value'];
    }

    // Under some circumstances, input will be an array containing the string
    // value. Not sure why, but just deal with that.
    while (is_array($input)) {
      $input = $input ? reset($input) : '';
    }
    if (trim($input) === '') {
      // No input was given by the user. If the filter was set to "required" and
      // there is a query (not the case when an exposed filter block is
      // displayed stand-alone), abort it.
      if (!empty($this->options['expose']['required']) && $this->getQuery()) {
        $this->getQuery()->abort();
      }
      // If the input is empty, there is nothing to validate: return early.
      return;
    }

    if (!Unicode::validateUtf8($input)) {
      $msg = $this->t('Invalid input.');
      $form_state->setErrorByName($identifier, $msg);
    }

    // Abort if the number of words exceeds the limit.
    $words = preg_split('/\s+/', $input) ?: [];
    $max_words = $this->options['expose']['value_max_words'];
    if (is_numeric($max_words) && $max_words > 0 && count($words) > $max_words) {
      $this->getQuery()->abort();
      $msg = $this->t('Maximum number of words exceeded. You may enter a maximum of @count words.', [
        '@count' => $max_words,
      ]);
      $form_state->setErrorByName($identifier, $msg);
    }

    // Only continue if there is a minimum word length set.
    if ($this->options['min_length'] < 2) {
      return;
    }

    foreach ($words as $i => $word) {
      if (mb_strlen($word) < $this->options['min_length']) {
        unset($words[$i]);
      }
    }
    if (!$words) {
      $msg = $this->formatPlural($this->options['min_length'], 'You must include at least one keyword to match in the content, and punctuation is ignored.', 'You must include at least one keyword to match in the content. Keywords must be at least @count characters, and punctuation is ignored.');
      $form_state->setErrorByName($identifier, $msg);
    }
    $input = implode(' ', $words);
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    while (is_array($this->value)) {
      $this->value = $this->value ? reset($this->value) : '';
    }
    // Catch empty strings entered by the user, but not "0".
    if ($this->value === '') {
      return;
    }
    $query = $this->getQuery();

    $indexed_fulltext_fields = $query->getIndex()->getFulltextFields();
    $fields = array_intersect($this->options['fields'], $indexed_fulltext_fields);
    $fields = $fields ?: $indexed_fulltext_fields;
    // Override the search fields, if exposed.
    if (!empty($this->searchedFields)) {
      $fields = array_intersect($fields, $this->searchedFields);
    }

    // Save any keywords that were already set.
    $old = $query->getKeys();
    $old_original = $query->getOriginalKeys();

    if ($this->options['parse_mode']) {
      /** @var \Drupal\search_api\ParseMode\ParseModeInterface $parse_mode */
      $parse_mode = $this->getParseModeManager()
        ->createInstance($this->options['parse_mode']);
      $query->setParseMode($parse_mode);
    }

    // If something already specifically set different fields, we silently fall
    // back to mere filtering.
    $old_fields = $query->getFulltextFields();
    $use_conditions = $old_fields
      && (array_diff($old_fields, $fields) || array_diff($fields, $old_fields));

    if ($use_conditions) {
      $conditions = $query->createAndAddConditionGroup('OR');
      $op = $this->operator === 'not' ? '<>' : '=';
      foreach ($fields as $field) {
        $conditions->addCondition($field, $this->value, $op);
      }
      return;
    }

    // If the operator was set to OR or NOT, set OR as the conjunction. It is
    // also set for NOT since otherwise it would be "not all of these words".
    if ($this->operator != 'and') {
      $query->getParseMode()->setConjunction('OR');
    }

    $query->setFulltextFields($fields);
    $query->keys($this->value);
    if ($this->operator == 'not') {
      $keys = &$query->getKeys();
      if (is_array($keys)) {
        $keys['#negation'] = TRUE;
      }
      else {
        // We can't know how negation is expressed in the server's syntax.
      }
      unset($keys);
    }

    // If there were fulltext keys set, we take care to combine them in a
    // meaningful way (especially with negated keys).
    if ($old) {
      $keys = &$query->getKeys();
      // Array-valued keys are combined.
      if (is_array($keys)) {
        // If the old keys weren't parsed into an array, we instead have to
        // combine the original keys.
        if (is_scalar($old)) {
          $keys = "($old) ({$this->value})";
        }
        else {
          // If the conjunction or negation settings aren't the same, we have to
          // nest both old and new keys array.
          if (empty($keys['#negation']) !== empty($old['#negation'])
              || $keys['#conjunction'] !== $old['#conjunction']) {
            $keys = [
              '#conjunction' => 'AND',
              $old,
              $keys,
            ];
          }
          // Otherwise, just add all individual words from the old keys to the
          // new ones.
          else {
            foreach ($old as $key => $value) {
              if (str_starts_with($key, '#')) {
                continue;
              }
              $keys[] = $value;
            }
          }
        }
      }
      // If the parse mode was "direct" for both old and new keys, we
      // concatenate them and set them both via method and reference (to also
      // update the originalKeys property.
      elseif (is_scalar($old_original)) {
        $combined_keys = "($old_original) ($keys)";
        $query->keys($combined_keys);
        $keys = $combined_keys;
      }
      unset($keys);
    }
  }

  /**
   * Retrieves a list of all available fulltext fields.
   *
   * @return string[]
   *   An options list of fulltext field identifiers mapped to their prefixed
   *   labels.
   */
  protected function getFulltextFields() {
    $fields = [];
    /** @var \Drupal\search_api\IndexInterface $index */
    $index = Index::load(substr($this->table, 17));

    $fields_info = $index->getFields();
    foreach ($index->getFulltextFields() as $field_id) {
      $fields[$field_id] = $fields_info[$field_id]->getPrefixedLabel();
    }

    return $fields;
  }

}
