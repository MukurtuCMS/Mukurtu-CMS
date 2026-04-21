<?php

namespace Drupal\facets_exposed_filters\Plugin\views\filter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\facets\Entity\Facet;
use Drupal\facets\FacetInterface;
use Drupal\facets\Hierarchy\HierarchyPluginBase;
use Drupal\facets\Processor\ProcessorInterface;
use Drupal\facets\Processor\SortProcessorInterface;
use Drupal\views\Plugin\views\filter\FilterPluginBase;

/**
 * Provides exposing facets as a filter.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("facets_filter")
 */
class FacetsFilter extends FilterPluginBase {

  /**
   * {@inheritdoc}
   */
  // phpcs:ignore Drupal.NamingConventions.ValidVariableName.LowerCamelName
  public $no_operator = FALSE;

  /**
   * Stores the facet results after the query is executed.
   *
   * @var \Drupal\facets\Result\ResultInterface[]
   */
  // phpcs:ignore Drupal.NamingConventions.ValidVariableName.LowerCamelName
  public $facet_results = [];

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['exposed'] = ['default' => TRUE];
    $options['expose']['contains']['identifier']['default'] = $this->configuration["search_api_field_identifier"];
    $options["expose"]["contains"]["label"]["default"] = $this->configuration["search_api_field_label"];
    $options['expose']['contains']['multiple'] = ['default' => TRUE];
    $options['hierarchy'] = ['default' => FALSE];
    $options['label_display']['default'] = BlockPluginInterface::BLOCK_LABEL_VISIBLE;
    $options['facet']['contains']['show_numbers'] = ['default' => FALSE];
    $options['facet']['contains']['min_count'] = ['default' => 1];
    $options['facet']['contains']['query_operator'] = ['default' => 'or'];
    $options['facet']['contains']['hard_limit'] = ['default' => 0];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $form["expose"]["remember"]['#access'] = FALSE;
    $form["expose"]["remember_roles"]['#access'] = FALSE;

    // @todo Not supported for now, needs work.
    $form["expose"]["required"]['#access'] = FALSE;

    // Expose settings are not needed for this filter. It does not make sense to
    // create a facet filter and not expose it.
    $form["expose_button"]['#access'] = FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function adminLabel($short = FALSE) {
    return 'Facet: ' . $this->options["expose"]["label"] . ' (' . $this->options["expose"]["identifier"] . ')';
  }

  /**
   * {@inheritdoc}
   */
  public function valueForm(&$form, FormStateInterface $form_state) {
    $form['value'] = [];

    // Extra checks when in views UI.
    if (isset($_POST["form_id"]) && $_POST["form_id"] === 'view_preview_form') {
      if (!$this->isViewAndDisplaySaved()) {
        // Set warning.
        \Drupal::messenger()
          ->addWarning('The current display has not been saved yet. You need to save this display before facet filters are visible.');
        return $form;
      }
    }

    // Due to how views works, this form is built before results are queried.
    // We render this form again in facets_exposed_filters_views_post_execute()
    // once the views query is executed, having the actual facets available.
    if (!isset($this->view->filter[$this->options["id"]]->facet_results)) {
      return $form;
    }

    // Retrieve the processed facet if already handled in the current request.
    $processed_facet = facets_exposed_filters_get_processed_facet($this->view->id(), $this->view->current_display, $this->options["id"]);

    // Empty facet results, return empty form.
    if (!isset($this->view->facets_query_post_execute) && !$processed_facet) {
      return $form;
    }

    if ($processed_facet) {
      $facet = $processed_facet;
    }
    else {
      /** @var \Drupal\facets\FacetInterface $facet */
      $facet = $this->getFacet();

      $active_facet_values = $this->getActiveFacetValues();
      $facet->setActiveItems($active_facet_values);

      // Load the query_type plugin and execute build.
      $qtpm = \Drupal::service('plugin.manager.facets.query_type');
      /** @var \Drupal\facets\QueryType\QueryTypeInterface $query_type_plugin */
      $query_type_plugin = $qtpm->createInstance(
        $facet->getQueryType(),
        [
          'query' => $this->query->getSearchApiQuery(),
          'facet' => $facet,
          'results' => $this->view->filter[$this->options["id"]]->facet_results ?? [],
        ]
      );
      $query_type_plugin->build();

      // Skip facet processing and form rendering if there are no results.
      if (!$facet->getResults()) {
        return;
      }

      // Trigger post query stage.
      $processors = $facet->getProcessorsByStage(ProcessorInterface::STAGE_POST_QUERY);
      foreach ($processors as $processor) {
        $processor->postQuery($facet);
      }

      // Trigger build stage.
      $processors = $facet->getProcessorsByStage(ProcessorInterface::STAGE_BUILD);
      foreach ($processors as $processor) {
        $facet->setResults($processor->build($facet, $facet->getResults()));
      }

      // Allow processors to sort the results.
      $active_sort_processors = [];
      foreach ($facet->getProcessorsByStage(ProcessorInterface::STAGE_SORT) as $processor) {
        $active_sort_processors[] = $processor;
      }
      if (!empty($active_sort_processors)) {
        $facet->setResults($this->sortFacetResults($active_sort_processors, $facet->getResults()));
      }
      $facet->setActiveItems(array_values($active_facet_values));

      // Store the processed facet so we can access it later (e.g. in an exposed
      // form rendered as a block).
      facets_exposed_filters_get_processed_facet($this->view->id(), $this->view->current_display, $this->options["id"], $facet);
    }

    // We need to merge the existing #process callbacks with our own.
    $select_element = \Drupal::service('element_info')->getInfo('select');

    $this->value = $facet->getActiveItems();
    // Store processed results so other modules can use these.
    $this->facet_results = $facet->getResults();
    $form['value'] = [
      '#type' => 'select',
      '#options' => $this->buildOptions($facet->getResults(), $facet),
      '#multiple' => $this->options["expose"]["multiple"],
      '#process' => array_merge($select_element["#process"], ['facets_exposed_filters_remove_validation']),
    ];

    $exposed_form_type = $this->displayHandler->getPlugin('exposed_form')->getPluginId();
    if ($exposed_form_type == 'bef') {
      $form['value']['#process'] = ['facets_exposed_filters_remove_validation'];
    }
  }

  /**
   * Helper function which transforms results into options.
   *
   * This can be called recursively to build a hierarchy.
   *
   * @param \Drupal\facets\Result\ResultInterface[] $results
   *   The array of result objects.
   * @param \Drupal\facets\FacetInterface $facet
   *   The facet to build the options.
   * @param int $depth
   *   The level of depth.
   *
   * @return array
   *   The built options to be used on a select form element.
   */
  private function buildOptions(array $results, FacetInterface $facet, $depth = 0): array {
    $hierarchy_prefix = "";
    for ($i = 0; $i < $depth; $i++) {
      $hierarchy_prefix .= "-";
    }
    $options = [];
    foreach ($results as $result) {
      $label = $result->getDisplayValue();
      if ($this->options["facet"]["show_numbers"] && $result->getCount() !== FALSE) {
        $label .= ' (' . $result->getCount() . ')';
      }
      $options[$result->getRawValue()] = $hierarchy_prefix . $label;
      if ($facet->getUseHierarchy()) {
        $children = $result->getChildren();
        if ($children && ($facet->getExpandHierarchy() || $result->isActive() || $result->hasActiveChildren())) {
          $options = $options + $this->buildOptions($children, $facet, $depth + 1);
        }
      }
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function acceptExposedInput($input) {
    // Modules like views_dependent_filters alter the exposed option to ignore the filter when hidden.
    // We need to check for this.
    return $this->isExposed();
  }

  /**
   * {@inheritdoc}
   */
  public function canGroup() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $facet = $this->getFacet();
    $active_values = $this->getActiveFacetValues();
    $facet->setActiveItems($active_values);
    $qtpm = \Drupal::service('plugin.manager.facets.query_type');

    /** @var \Drupal\facets\QueryType\QueryTypeInterface $query_type_plugin */
    $query_type_plugin = $qtpm->createInstance(
      $facet->getQueryType(),
      [
        'query' => $this->query->getSearchApiQuery(),
        'facet' => $facet,
      ]
    );
    $query_type_plugin->execute();
  }

  /**
   * Expose configuration for facets_exposed_filters_views_post_execute().
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function hasExtraOptions() {
    return TRUE;
  }

  /**
   * Returns TRUE if current view and display has been saved.
   */
  protected function isViewAndDisplaySaved() {
    // Check if the view has been saved yet.
    if ($this->view->storage->get('base_field') !== 'search_api_id') {
      return FALSE;
    }
    // Check if the current display has been saved yet.
    $display_saved_config = \Drupal::config('views.view.' . $this->view->id())
      ->get('display');
    if (!isset($display_saved_config[$this->view->current_display])) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildExtraOptionsForm(&$form, FormStateInterface $form_state) {

    if (!$this->isViewAndDisplaySaved()) {
      $form['message']['#markup'] = $this->t('The current display has not been saved yet. You need to save this display before you can configure the facet settings.');
      return $form;
    }

    $facet = $this->getFacet();
    $all_processors = $facet->getProcessors(FALSE);
    unset($all_processors["url_processor_handler"]);
    $enabled_processors = $facet->getProcessors(TRUE);

    $form['facet'] = [
      '#type' => 'container',
      '#title' => $this->t('Facet settings'),
      '#tree' => TRUE,
    ];
    $form['facet']['processors'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];
    foreach ($all_processors as $processor_id => $processor) {
      if (!($processor instanceof SortProcessorInterface) && $processor->supportsFacet($facet)) {
        $clean_css_id = Html::cleanCssIdentifier($processor_id);
        $default_value = !empty($enabled_processors[$processor_id]);
        $form['facet']['processors'][$processor_id]['status'] = [
          '#type' => 'checkbox',
          '#title' => (string) $processor->getPluginDefinition()['label'],
          '#default_value' => $default_value,
          '#description' => $processor->getDescription(),
          '#attributes' => ['data-processor-id' => $clean_css_id],
        ];

        $form['facet']['processors'][$processor_id]['settings'] = [];
        $processor_form_state = SubformState::createForSubform($form['facet']['processors'][$processor_id]['settings'], $form, $form_state);
        $processor_form = $processor->buildConfigurationForm($form, $processor_form_state, $facet);
        if ($processor_form) {
          $form['facet']['processors'][$processor_id]['settings'] = [
            '#type' => 'details',
            '#title' => $this->t('%processor settings', ['%processor' => (string) $processor->getPluginDefinition()['label']]),
            '#open' => TRUE,
            '#states' => [
              'visible' => [
                ':input[name="options[facet][processors][' . $processor_id . '][status]"]' => ['checked' => TRUE],
              ],
            ],
          ];
          $form['facet']['processors'][$processor_id]['settings'] += $processor_form;
        }
      }
    }

    $form['facet_sort_processors'] = [
      '#type' => 'details',
      '#title' => $this->t('Sort results by'),
    ];
    foreach ($all_processors as $processor_id => $processor) {
      if ($processor instanceof SortProcessorInterface) {
        $clean_css_id = Html::cleanCssIdentifier($processor_id);
        $default_value = !empty($enabled_processors[$processor_id]);
        $form['facet_sort_processors'][$processor_id]['status'] = [
          '#type' => 'checkbox',
          '#title' => (string) $processor->getPluginDefinition()['label'],
          '#default_value' => $default_value,
          '#description' => $processor->getDescription(),
          '#attributes' => ['data-processor-id' => $clean_css_id],
        ];

        $form['facet_sort_processors'][$processor_id]['settings'] = [];
        $processor_form_state = SubformState::createForSubform($form['facet_sort_processors'][$processor_id]['settings'], $form, $form_state);
        $processor_form = $processor->buildConfigurationForm($form, $processor_form_state, $facet);
        if ($processor_form) {
          $form['facet_sort_processors'][$processor_id]['settings'] = [
            '#type' => 'container',
            '#open' => TRUE,
            '#states' => [
              'visible' => [
                ':input[name="options[facet_sort_processors][' . $processor_id . '][status]"]' => ['checked' => TRUE],
              ],
            ],
          ];
          $form['facet_sort_processors'][$processor_id]['settings'] += $processor_form;
        }
      }
    }

    $form['facet']['query_operator'] = [
      '#type' => 'radios',
      '#title' => $this->t('Operator'),
      '#options' => ['or' => $this->t('OR'), 'and' => $this->t('AND')],
      '#description' => $this->t('AND filters are exclusive and narrow the result set. OR filters are inclusive and widen the result set.'),
      '#default_value' => $facet->getQueryOperator(),
    ];

    $form['facet']['hard_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Hard limit'),
      '#default_value' => $facet->getHardLimit(),
      '#description' => $this->t('Display no more than this number of facet items.<br>*Note: Some search backends will use 0 as "no limit", and some still apply a default limit when hard limit is set to 0. If this affects you, set this to an appropriately-high value or a value that indicates "no limit" to your search backend'),
    ];

    $hierarchy = $facet->getHierarchy();
    $options = array_map(function (HierarchyPluginBase $plugin) {
      return $plugin->getPluginDefinition()['label'];
    }, $facet->getHierarchies());
    $form['facet']['hierarchy'] = [
      '#type' => 'select',
      '#title' => $this->t('Hierarchy type'),
      '#options' => $options,
      '#default_value' => $hierarchy ? $hierarchy['type'] : '',
      '#states' => [
        'visible' => [
          ':input[name="options[facet][processors][hierarchy_processor][status]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['facet']['expand_hierarchy'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Always expand hierarchy'),
      '#description' => $this->t('Render entire tree, regardless of whether the parents are active or not.'),
      '#default_value' => $facet->getExpandHierarchy(),
      '#states' => [
        'visible' => [
          ':input[name="options[facet][processors][hierarchy_processor][status]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['facet']['min_count'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum count'),
      '#default_value' => $facet->getMinCount(),
      '#description' => $this->t('Only display the results if there is this minimum amount of results. The default is "1". A setting "0" might result in a list of all possible facet items, regardless of the actual search query. But the result of a minimum count of "0" is not reliable and may very on the type of the field, the Search API backend and even between different releases or runtime configurations of the backend (for example Solr). Therefore it is highly recommended to avoid any feature that depends on a minimum count of "0".'),
      '#maxlength' => 4,
      '#min' => 0,
      '#required' => TRUE,
    ];

    $form['facet']['show_numbers'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show the amount of results'),
      '#default_value' => $this->options["facet"]["show_numbers"],
    ];

    $form['weights'] = [
      '#type' => 'details',
      '#title' => $this->t('Processor order'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];

    $form['weights']['order'] = [
      '#type' => 'container',
    ];

    $processor_plugin_manager = \Drupal::service('plugin.manager.facets.processor');
    $stages = $processor_plugin_manager->getProcessingStages();
    $processors_by_stage = [];
    foreach ($stages as $stage => $definition) {
      foreach ($facet->getProcessorsByStage($stage, FALSE) as $processor_id => $processor) {
        if ($processor_id == 'url_processor_handler') {
          continue;
        }
        if ($processor->supportsFacet($facet)) {
          $processors_by_stage[$stage][$processor_id] = $processor;
        }
      }
      unset($processor_id, $processor);
    }
    // Order enabled processors per stage, create all the containers for the
    // different stages.
    foreach ($stages as $stage => $description) {
      $form['weights'][$stage] = [
        '#type' => 'fieldset',
        '#title' => $description['label'],
        '#attributes' => [
          'class' => [
            'search-api-stage-wrapper-' . Html::cleanCssIdentifier($stage),
          ],
        ],
      ];
      $form['weights'][$stage]['order'] = [
        '#type' => 'table',
      ];
      $form['weights'][$stage]['order']['#tabledrag'][] = [
        'action' => 'order',
        'relationship' => 'sibling',
        'group' => 'search-api-processor-weight-' . Html::cleanCssIdentifier($stage),
      ];
    }

    // Fill in the containers previously created with the processors that are
    // enabled on the facet.
    foreach ($processors_by_stage as $stage => $processors) {
      /** @var \Drupal\facets\Processor\ProcessorInterface $processor */
      foreach ($processors as $processor_id => $processor) {
        $weight = $this->options["facet"]["processor_configs"][$processor_id]["weights"][$stage] ?? $processor->getDefaultWeight($stage);
        if ($processor->isHidden()) {
          $form['processors'][$processor_id]['weights'][$stage] = [
            '#type' => 'value',
            '#value' => $weight,
          ];
          continue;
        }
        $form['weights'][$stage]['order'][$processor_id]['#attributes']['class'][] = 'draggable';
        $form['weights'][$stage]['order'][$processor_id]['#attributes']['class'][] = 'search-api-processor-weight--' . Html::cleanCssIdentifier($processor_id);
        $form['weights'][$stage]['order'][$processor_id]['#weight'] = $weight;
        $form['weights'][$stage]['order'][$processor_id]['label']['#plain_text'] = (string) $processor->getPluginDefinition()['label'];
        $form['weights'][$stage]['order'][$processor_id]['weight'] = [
          '#type' => 'weight',
          '#title' => $this->t('Weight for processor %title', ['%title' => (string) $processor->getPluginDefinition()['label']]),
          '#title_display' => 'invisible',
          '#default_value' => $weight,
          '#parents' => ['weights', $processor_id, 'weights', $stage],
          '#attributes' => [
            'class' => [
              'search-api-processor-weight-' . Html::cleanCssIdentifier($stage),
            ],
          ],
        ];
      }
    }
    $form['#attached']['library'][] = 'facets_exposed_filters/edit_filter';
    return $form;
  }

  /**
   * Helper function to retrieve the representing facet.
   */
  private function getFacet() {
    $facet = Facet::create([
      'id' => $this->options["field"],
      'field_identifier' => $this->configuration["search_api_field_identifier"],
      'facet_source_id' => 'search_api:views_' . $this->displayHandler->getPluginId() . '__' . $this->view->id() . '__' . $this->view->current_display,
      'query_operator' => $this->options["facet"]["query_operator"] ?? 'or',
      'use_hierarchy' => isset($this->options["facet"]["processor_configs"]["hierarchy_processor"]),
      'expand_hierarchy' => $this->options["facet"]["expand_hierarchy"] ?? FALSE,
      'min_count' => $this->options["facet"]["min_count"] ?? 1,
      'widget' => '<nowidget>',
      'facet_type' => 'facets_exposed_filter',
    ]);
    $facet->setHardLimit($this->options["facet"]["hard_limit"] ?? 0);
    if ($facet->getUseHierarchy()) {
      $facet->setHierarchy($this->options["facet"]["hierarchy"], []);
    }
    if (isset($this->options["facet"]["processor_configs"])) {
      foreach ($this->options["facet"]["processor_configs"] as $processor_id => $processor_settings) {
        $facet->addProcessor([
          'processor_id' => $processor_id,
          'settings' => $processor_settings["settings"] ?? [],
          'weights' => $processor_settings["weights"] ?? [],
        ]);
      }
    }
    return $facet;
  }

  /**
   * Returns the active facet values for the current filter as an array.
   *
   * Since we build the exposed form again after the query is triggered, we need
   * to retrieve the active filters from the request ourself.
   */
  private function getActiveFacetValues() {
    // Reset button in ajax request. We probably want a better way to detect if
    // this was clicked.
    if (isset($_GET["reset"])) {
      return [];
    }
    $exposed = $this->view->getExposedInput();
    if (!isset($exposed[$this->options["expose"]["identifier"]])) {
      return [];
    }
    $enabled = $exposed[$this->options["expose"]["identifier"]];
    if ($enabled == 'All') {
      return [];
    }
    elseif (!is_array($enabled)) {
      $enabled = [$enabled];
    }
    return $enabled;
  }

  /**
   * {@inheritdoc}
   */
  public function submitExtraOptionsForm($form, FormStateInterface $form_state) {
    $options = $form_state->getValue('options');
    $weights = $form_state->getValue('weights');
    $processor_configs = [];
    // Remove disabled processors.
    foreach ($options["facet"]["processors"] as $processor_id => $processor_data) {
      if ($processor_data["status"] == 1) {
        $processor_configs[$processor_id] = [
          'processor_id' => $processor_id,
          'settings' => $processor_data["settings"] ?? [],
        ];
      }
    }
    foreach ($options["facet_sort_processors"] as $processor_id => $processor_data) {
      if ($processor_data["status"] == 1) {
        $processor_configs[$processor_id] = [
          'processor_id' => $processor_id,
          'settings' => $processor_data["settings"] ?? [],
        ];
      }
    }
    foreach ($processor_configs as $processor_id => $processor_config) {
      foreach ($weights[$processor_id]["weights"] as $stage => $weight) {
        $processor_configs[$processor_id]['weights'][$stage] = (int) $weight;
      }
    }
    unset($options["weights"]);
    // Values are merged and stored in processor_configs.
    unset($options["facet"]["processors"]);
    unset($options["facet_sort_processors"]);
    $options["facet"]["processor_configs"] = $processor_configs;

    // Better exposed filters checks for a specific key to determine if
    // hierarchy should be enabled. Lets ensure it is set.
    if (isset($options["facet"]["processor_configs"]["hierarchy_processor"])) {
      $options["hierarchy"] = TRUE;
    }
    else {
      $options["hierarchy"] = FALSE;
      unset($options["facet"]["hierarchy"]);
      unset($options["facet"]["expand_hierarchy"]);
    }

    $options["facet"]["min_count"] = (int) $options["facet"]["min_count"];
    $options["facet"]["show_numbers"] = (bool) $options["facet"]["show_numbers"];

    $form_state->setValue('options', $options);
    parent::submitExtraOptionsForm($form, $form_state);
  }

  /**
   * Sort the facet results, and recurse to children to do the same.
   *
   * @param \Drupal\facets\Processor\SortProcessorInterface[] $active_sort_processors
   *   An array of sort processors.
   * @param \Drupal\facets\Result\ResultInterface[] $results
   *   An array of results.
   *
   * @return \Drupal\facets\Result\ResultInterface[]
   *   A sorted array of results.
   */
  protected function sortFacetResults(array $active_sort_processors, array $results) {
    uasort($results, function ($a, $b) use ($active_sort_processors) {
      $return = 0;
      foreach ($active_sort_processors as $sort_processor) {
        if ($return = $sort_processor->sortResults($a, $b)) {
          if ($sort_processor->getConfiguration()['sort'] == 'DESC') {
            $return *= -1;
          }
          break;
        }
      }
      return $return;
    });
    // Loop over the results and see if they have any children, if they do, fire
    // a request to this same method again with the children.
    foreach ($results as &$result) {
      if (!empty($result->getChildren())) {
        $children = $this->sortFacetResults($active_sort_processors, $result->getChildren());
        $result->setChildren($children);
      }
    }
    return $results;
  }

}
