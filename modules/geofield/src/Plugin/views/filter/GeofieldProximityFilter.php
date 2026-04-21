<?php

namespace Drupal\geofield\Plugin\views\filter;

use Drupal\Core\Database\Query\Condition;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Render\RendererInterface;
use Drupal\geofield\Plugin\GeofieldProximitySourceManager;
use Drupal\views\Plugin\views\filter\NumericFilter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Field handler to filter Geofields by proximity.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("geofield_proximity_filter")
 */
class GeofieldProximityFilter extends NumericFilter {

  use LoggerChannelTrait;

  /**
   * The Renderer service property.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $renderer;

  /**
   * The geofield proximity manager.
   *
   * @var \Drupal\geofield\Plugin\GeofieldProximitySourceManager
   */
  protected $proximitySourceManager;

  /**
   * The Geofield Radius Options.
   *
   * @var array
   */
  protected $geofieldRadiusOptions;

  /**
   * The Geofield Proximity Source Plugin.
   *
   * @var \Drupal\geofield\Plugin\GeofieldProximitySourceInterface
   */
  protected $sourcePlugin;

  /**
   * The current request.
   *
   * @var null|\Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The Value Label.
   *
   * @var string
   */
  protected $valueLabel;

  /**
   * The Min Label.
   *
   * @var string
   */
  protected $minLabel;

  /**
   * The Max Label.
   *
   * @var string
   */
  protected $maxLabel;

  /**
   * The Origin Label.
   *
   * @var string
   */
  protected $originLabel;

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    // Override some default settings from the NumericFilter.
    $options['operator'] = ['default' => '<='];

    $options['units'] = ['default' => 'GEOFIELD_KILOMETERS'];

    $options['exposed_units'] = [
      'default' => FALSE,
    ];

    // Default Data sources Info.
    $options['source'] = ['default' => 'geofield_manual_origin'];
    $options['source_configuration'] = [
      'default' => [
        'exposed_summary' => TRUE,
      ],
    ];

    return $options;
  }

  /**
   * Constructs the GeofieldProximityFilter object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\geofield\Plugin\GeofieldProximitySourceManager $proximity_source_manager
   *   The Geofield Proximity Source manager service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    RendererInterface $renderer,
    GeofieldProximitySourceManager $proximity_source_manager,
    RequestStack $request_stack,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->renderer = $renderer;
    $this->proximitySourceManager = $proximity_source_manager;
    $this->geofieldRadiusOptions = geofield_radius_options();
    $this->request = $request_stack;
    $this->valueLabel = $this->t('Distance');
    $this->minLabel = $this->t('Min');
    $this->maxLabel = $this->t('Max');
    $this->originLabel = $this->t('Origin');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('renderer'),
      $container->get('plugin.manager.geofield_proximity_source'),
      $container->get('request_stack')
    );
  }

  /**
   * Provide Operators List.
   */
  public function operators() {
    $operators = [
      '<' => [
        'title' => $this->t('Is less than'),
        'method' => 'opSimple',
        'short' => $this->t('<'),
        'values' => 1,
      ],
      '<=' => [
        'title' => $this->t('Is less than or equal to'),
        'method' => 'opSimple',
        'short' => $this->t('<='),
        'values' => 1,
      ],
      '=' => [
        'title' => $this->t('Is equal to'),
        'method' => 'opSimple',
        'short' => $this->t('='),
        'values' => 1,
      ],
      '!=' => [
        'title' => $this->t('Is not equal to'),
        'method' => 'opSimple',
        'short' => $this->t('!='),
        'values' => 1,
      ],
      '>=' => [
        'title' => $this->t('Is greater than or equal to'),
        'method' => 'opSimple',
        'short' => $this->t('>='),
        'values' => 1,
      ],
      '>' => [
        'title' => $this->t('Is greater than'),
        'method' => 'opSimple',
        'short' => $this->t('>'),
        'values' => 1,
      ],
      'between' => [
        'title' => $this->t('Is between'),
        'method' => 'opBetween',
        'short' => $this->t('between'),
        'values' => 2,
      ],
      'not between' => [
        'title' => $this->t('Is not between'),
        'method' => 'opBetween',
        'short' => $this->t('not between'),
        'values' => 2,
      ],
    ];

    return $operators;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();
    $lat_alias = $this->realField . '_lat';
    $lon_alias = $this->realField . '_lon';

    try {
      /** @var \Drupal\geofield\Plugin\GeofieldProximitySourceInterface $source_plugin */
      $this->sourcePlugin = $this->proximitySourceManager->createInstance($this->options['source'], $this->options['source_configuration']);
      $this->sourcePlugin->setViewHandler($this);
      $this->sourcePlugin->setUnits($this->options['units']);
      $info = $this->operators();

      // Add query condition in case of valid proximity filter options.
      if ($haversine_options = $this->sourcePlugin->getHaversineOptions()) {
        $haversine_options['destination_latitude'] = $this->tableAlias . '.' . $lat_alias;
        $haversine_options['destination_longitude'] = $this->tableAlias . '.' . $lon_alias;
        $this->{$info[$this->operator]['method']}($haversine_options);

        // Ensure that destination is valid.
        $condition = (new Condition('AND'))->isNotNull($haversine_options['destination_latitude'])->isNotNull($haversine_options['destination_longitude']);
        $this->query->addWhere(0, $condition);
      }
      // Otherwise output empty result in case of unexposed proximity filter.
      elseif (!$this->isExposed()) {
        // Origin is not valid so return no results (if not exposed filter).
        $this->query->addWhereExpression($this->options['group'], '1=0');
      }
    }
    catch (\Exception $e) {
      $this->getLogger('geofield')->error($e->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function opBetween($field) {
    if (!empty($this->value['min']) && is_numeric($this->value['min']) &&
      !empty($this->value['max']) && is_numeric($this->value['max'])) {
      // Be sure to convert $options into array,
      // as this method PhpDoc might expect $options to be an object.
      $field = (array) $field;
      $this->query->addWhereExpression($this->options['group'], geofield_haversine($field) . ' ' . strtoupper($this->operator) . ' ' . $this->value['min'] . ' AND ' . $this->value['max']);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function opSimple($field) {
    if (!empty($this->value['value']) && is_numeric($this->value['value'])) {
      // Be sure to convert $options into array,
      // as this method PhpDoc might expect $options to be an object.
      $field = (array) $field;
      $this->query->addWhereExpression($this->options['group'], geofield_haversine($field) . ' ' . $this->operator . ' ' . $this->value['value']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $context = $this->pluginDefinition['plugin_type'];

    $user_input = $form_state->getUserInput();
    $source_plugin_id = $user_input['options']['source'] ?? $this->options['source'];
    $source_plugin_configuration = $user_input['options']['source_configuration'] ?? $this->options['source_configuration'];

    $this->proximitySourceManager->buildCommonFormElements($form, $form_state, $this->options, $context);

    $form['units']['#default_value'] = $user_input['options']['units'] ?? $this->options['units'];
    $form['source']['#default_value'] = $source_plugin_id;

    $form['source_configuration']['exposed_summary'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Expose Summary Description for the specific Proximity Filter Source'),
      '#default_value' => $user_input['options']['source_configuration']['exposed_summary'] ?? $this->options['source_configuration']['exposed_summary'],
      '#states' => [
        'visible' => [
          ':input[name="options[expose_button][checkbox][checkbox]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    try {
      $this->sourcePlugin = $this->proximitySourceManager->createInstance($source_plugin_id, $source_plugin_configuration);
      $this->sourcePlugin->setViewHandler($this);
      $form['source_configuration']['origin_description'] = [
        '#markup' => $this->sourcePlugin->getPluginDefinition()['description'],
        '#weight' => -10,
      ];
      $this->sourcePlugin->buildOptionsForm($form['source_configuration'], $form_state, ['source_configuration']);
    }
    catch (\Exception $e) {
      $this->getLogger('geofield')->error($e->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateOptionsForm(&$form, FormStateInterface $form_state) {
    parent::validateOptionsForm($form, $form_state);
    try {
      $this->sourcePlugin->validateOptionsForm($form['source_configuration'], $form_state, ['source_configuration']);
    }
    catch (\Exception $e) {
      $this->getLogger('geofield')->error($e->getMessage());
      $form_state->setErrorByName($form['source'], $this->t("The Proximity Source couldn't be set due to: @error", [
        '@error' => $e,
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateExposed(&$form, FormStateInterface $form_state) {
    parent::validateExposed($form, $form_state);
    $form_values = $form_state->getValues();
    $identifier = $this->options['expose']['identifier'];
    $identifier_operator = $form_values[$identifier . '_op'] ?? NULL;
    $which = isset($identifier_operator) && in_array($identifier_operator, $this->operatorValues(2)) ? 'minmax' : 'value';

    // Set/alter the Unit value, if present in the form option.
    if (isset($form_values["field_geofield_proximity"]["unit"])) {
      $this->options["units"] = $form_values["field_geofield_proximity"]["unit"];
    }

    // Validate the Distance field.
    if ($which !== 'minmax' && isset($form_values[$identifier]['value']) && (!empty($form_values[$identifier]['value']) && !is_numeric($form_values[$identifier]['value']))) {
      $form_state->setError($form[$identifier . '_wrapper'][$identifier]['value'], $this->t('The @value_label value is not valid.', [
        '@value_label' => $this->valueLabel,
      ]));
    }

    // Validate the Distance field as positive value.
    if ($which !== 'minmax' && !empty($form_values[$identifier]['value']) && $form_values[$identifier]['value'] < 0) {
      $form_state->setError($form[$identifier . '_wrapper'][$identifier]['value'], $this->t('The @value_label value should be positive.', [
        '@value_label' => $this->valueLabel,
      ]));
    }

    // Validate the Min value.
    if ($which !== 'value' && !empty($form_values[$identifier]['min']) && !is_numeric($form_values[$identifier]['min'])) {
      $form_state->setError($form[$identifier . '_wrapper'][$identifier]['min'], $this->t('The @min_label value is not valid.', [
        '@min_label' => $this->minLabel,
      ]));
    }

    // Validate the Max value.
    if ($which !== 'value' && !empty($form_values[$identifier]['max']) && !is_numeric($form_values[$identifier]['max'])) {
      $form_state->setError($form[$identifier . '_wrapper'][$identifier]['max'], $this->t('The @max_label value is not valid.', [
        '@max_label' => $this->maxLabel,
      ]));
    }

    // Validate the Min value as positive value.
    if ($which !== 'value' && !empty($form_values[$identifier]['min']) && $form_values[$identifier]['min'] < 0) {
      $form_state->setError($form[$identifier . '_wrapper'][$identifier]['min'], $this->t('The @min_label value should be positive.', [
        '@min_label' => $this->minLabel,
      ]));
    }

    // Validate the Max value as positive value.
    if ($which !== 'value' && !empty($form_values[$identifier]['max']) && $form_values[$identifier]['max'] < 0) {
      $form_state->setError($form[$identifier . '_wrapper'][$identifier]['max'], $this->t('The @max_label value should be positive.', [
        '@max_label' => $this->maxLabel,
      ]));
    }

    // Validate the Min and Max values relationship.
    if ($which !== 'value' && !empty($form_values[$identifier]['min']) && isset($form_values[$identifier]['max'])
      && ($form_values[$identifier]['min'] > $form_values[$identifier]['max'])) {
      $form_state->setError($form[$identifier . '_wrapper'][$identifier]['min'], $this->t('The @min_label value should be smaller than the @max_label value.', [
        '@min_label' => $this->minLabel,
        '@max_label' => $this->maxLabel,
      ]));
    }

    // Validate the Origin (not null) value, when the filter is required.
    if ($this->options['expose']['required']) {
      if (isset($form_values[$identifier]['source_configuration']['origin_address'])) {
        $input_address = $form_values[$identifier]['source_configuration']['origin_address'];
        if (empty($input_address)) {
          $form_state->setError($form[$identifier . '_wrapper'][$identifier]['source_configuration']['origin_address'], $this->t('The @origin_label Address is required', [
            '@origin_label' => $this->originLabel,
          ]));
        }
      }
      elseif (isset($form_values[$identifier]['source_configuration']['origin'])) {
        $input_origin = $form_values[$identifier]['source_configuration']['origin'];
        if ($this->sourcePlugin->isEmptyLocation($input_origin['lat'], $input_origin['lon'])) {
          $form_state->setError($form[$identifier . '_wrapper'][$identifier]['source_configuration']['origin'], $this->t('The @origin_label (Lat/Lon) is required', [
            '@origin_label' => $this->originLabel,
          ]));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function valueForm(&$form, FormStateInterface $form_state) {
    parent::valueForm($form, $form_state);

    $form['value'] = [
      '#tree' => TRUE,
    ];

    $units_description = '';
    $user_input = $form_state->getUserInput();

    // We have to make some choices when creating this as an exposed
    // filter form. For example, if the operator is locked and thus
    // not rendered, we can't render dependencies; instead we only
    // render the form items we need.
    $which = 'all';
    $source = !empty($form['operator']) ? ':input[name="options[operator]"]' : '';

    if ($exposed = $form_state->get('exposed')) {
      $identifier = $this->options['expose']['identifier'];

      if (!isset($user_input[$identifier]) || !is_array($user_input[$identifier])) {
        $user_input[$identifier] = [];
      }

      if (isset($this->options["exposed_units"]) && !$this->options["exposed_units"]) {
        $units_description = $this->t('Units: @units', [
          '@units' => isset($user_input['options']['units']) ? $this->geofieldRadiusOptions[$user_input['options']['units']] : $this->geofieldRadiusOptions[$this->options['units']],
        ]);
      }

      if (empty($this->options['expose']['use_operator']) || empty($this->options['expose']['operator_id'])) {
        // Exposed and locked.
        $which = in_array($this->operator, $this->operatorValues(2)) ? 'minmax' : 'value';
      }
      else {
        $source = ':input[name="' . $this->options['expose']['operator_id'] . '"]';
      }
    }

    if ($which == 'all' || $which == 'value') {
      $form['value']['value'] = [
        '#type' => 'textfield',
        '#title' => $exposed && empty($source) ? $this->valueLabel . ' ' . $this->operator : (!$exposed ? $this->valueLabel : ''),
        '#size' => 30,
        '#default_value' => $this->value['value'],
        '#description' => $exposed && isset($units_description) ? $units_description : '',
      ];
      if (!empty($this->options['expose']['placeholder'])) {
        $form['value']['value']['#attributes']['placeholder'] = $this->options['expose']['placeholder'];
      }

      if ($exposed && isset($identifier) && !isset($user_input[$identifier]['value'])) {
        $user_input[$identifier]['value'] = $this->value['value'];
        $form_state->setUserInput($user_input);
      }
    }

    if ($which == 'all') {
      // Setup #states for all operators with one value.
      foreach ($this->operatorValues(1) as $operator) {
        $form['value']['value']['#states']['visible'][] = [
          $source => ['value' => $operator],
        ];
      }
    }

    if ($which == 'all' || $which == 'minmax') {
      $form['value']['min'] = [
        '#type' => 'textfield',
        '#title' => $exposed && empty($source) ? $this->valueLabel . ' ' . $this->operator . ' ' . $this->minLabel : (!$exposed ? $this->minLabel : $this->minLabel),
        '#size' => 30,
        '#default_value' => $this->value['min'],
        '#description' => $exposed ? $units_description : '',
      ];

      if (!empty($this->options['expose']['min_placeholder'])) {
        $form['value']['min']['#attributes']['placeholder'] = $this->options['expose']['min_placeholder'];
      }
      $form['value']['max'] = [
        '#type' => 'textfield',
        '#title' => $this->maxLabel,
        '#size' => 30,
        '#default_value' => $this->value['max'],
        '#description' => $exposed ? $units_description : '',
      ];
      if (!empty($this->options['expose']['max_placeholder'])) {
        $form['value']['max']['#attributes']['placeholder'] = $this->options['expose']['max_placeholder'];
      }
      if ($which == 'all') {
        $states = [];
        // Setup #states for all operators with two values.
        foreach ($this->operatorValues(2) as $operator) {
          $states['#states']['visible'][] = [
            $source => ['value' => $operator],
          ];
        }
        $form['value']['min'] = array_merge((array) $form['value']['min'], $states);
        $form['value']['max'] = array_merge((array) $form['value']['max'], $states);
      }
      if ($exposed && isset($identifier) && !isset($user_input[$identifier]['min'])) {
        $user_input[$identifier]['min'] = $this->value['min'];
      }
      if ($exposed && isset($identifier) && !isset($user_input[$identifier]['max'])) {
        $user_input[$identifier]['max'] = $this->value['max'];
      }

      if (isset($identifier) && isset($form[$identifier . '_wrapper'])) {
        unset($form[$identifier . '_wrapper'][$identifier . '_op']['#title_display']);
        $form[$identifier . '_wrapper'][$identifier . '_op']['#title'] = $this->valueLabel;
      }

      if (!isset($form['value'])) {
        // Ensure there is something in the 'value'.
        $form['value'] = [
          '#type' => 'value',
          '#value' => NULL,
        ];
      }
    }

    // Build the specific Geofield Proximity Form Elements.
    if ($exposed && isset($identifier)) {
      $form['value']['#type'] = 'fieldset';

      // Expose the Units selector, if required.
      if (isset($this->options["exposed_units"]) && $this->options["exposed_units"]) {
        $form['value']['unit'] = [
          '#type' => 'select',
          '#options' => geofield_radius_options(),
          '#default_value' => $user_input['options']['units'] ?? $this->options['units'],
        ];
      }

      $form['value']['source_configuration'] = [
        '#type' => 'container',
      ];

      try {
        $source_plugin_id = $this->options['source'];
        $source_plugin_configuration = isset($identifier) && isset($user_input[$identifier]['origin']) ? $user_input[$identifier] : $this->options['source_configuration'];

        /** @var \Drupal\geofield\Plugin\GeofieldProximitySourceInterface $source_plugin */
        $this->sourcePlugin = $this->proximitySourceManager->createInstance($source_plugin_id, $source_plugin_configuration);
        $this->sourcePlugin->setViewHandler($this);
        $proximity_origin = $this->sourcePlugin->getOrigin();
        $this->sourcePlugin->buildOptionsForm($form['value']['source_configuration'], $form_state, ['source_configuration'], $exposed);

        // Write the Proximity Filter exposed summary.
        if ($this->options['source_configuration']['exposed_summary']) {
          $form['value']['exposed_summary'] = $this->exposedSummary();
        }

        if (!isset($user_input[$identifier]['origin']) && !empty($proximity_origin)) {
          $user_input[$identifier]['origin'] = [
            'lat' => $proximity_origin['lat'],
            'lon' => $proximity_origin['lon'],
          ];
          $form_state->setUserInput($user_input);
        }
      }
      catch (\Exception $e) {
        $this->getLogger('geofield')->error($e->getMessage());
        $form_state->setErrorByName($form['value']['source_configuration'], $this->t("The Proximity Source couldn't be set due to: @error", [
          '@error' => $e,
        ]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function acceptExposedInput($input) {
    if (empty($this->options['exposed'])) {
      return TRUE;
    }

    // Set the correct source configurations origin from exposed filter input
    // coordinates.
    $identifier = $this->options['expose']['identifier'];
    if (!empty($input[$identifier]['source_configuration'])) {
      foreach ($input[$identifier]['source_configuration'] as $k => $value) {
        $this->options['source_configuration'][$k] = $input[$identifier]['source_configuration'][$k];
      }
    }

    // The parent NumericFilter acceptExposedInput will care to correctly set
    // the options value.
    return parent::acceptExposedInput($input);
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    $output = parent::adminSummary();
    return $this->options['source'] . ' ' . $output;
  }

  /**
   * Expose a Summary.
   */
  protected function exposedSummary() {
    try {
      return [
        '#type' => 'html_tag',
        '#tag' => 'div',
        "#value" => $this->sourcePlugin->getPluginDefinition()['description'],
        "#attributes" => [
          'class' => ['proximity-filter-summary'],
        ],
      ];
    }
    catch (\Exception $e) {
      $this->getLogger('geofield')->error($e->getMessage());
      return NULL;
    }
  }

}
