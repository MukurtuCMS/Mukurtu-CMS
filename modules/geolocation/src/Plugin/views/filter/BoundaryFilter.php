<?php

namespace Drupal\geolocation\Plugin\views\filter;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\geolocation\BoundaryTrait;
use Drupal\geolocation\GeocoderManager;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\views\Plugin\views\query\Sql;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filter handler for search keywords.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("geolocation_filter_boundary")
 */
class BoundaryFilter extends FilterPluginBase implements ContainerFactoryPluginInterface {

  use BoundaryTrait;

  /**
   * {@inheritdoc}
   */
  public $no_operator = TRUE; // phpcs:ignore

  /**
   * Can be used for CommonMap interactive filtering.
   *
   * @var bool
   */
  public $isGeolocationCommonMapOption = TRUE;

  /**
   * {@inheritdoc}
   */
  protected $alwaysMultiple = TRUE;

  /**
   * The GeocoderManager object.
   *
   * @var \Drupal\geolocation\GeocoderManager
   */
  protected $geocoderManager;

  /**
   * Constructs a Handler object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\geolocation\GeocoderManager $geocoder_manager
   *   The Geocoder manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, GeocoderManager $geocoder_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->geocoderManager = $geocoder_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.geolocation.geocoder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    return $this->t("Boundary filter");
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();

    $options['expose']['contains']['input_by_geocoding_widget'] = ['default' => FALSE];
    $options['expose']['contains']['geocoder_plugin_settings'] = [
      'default' => [
        'plugin_id' => '',
        'settings' => [],
      ],
    ];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildExposeForm(&$form, FormStateInterface $form_state) {
    $geocoder_settings = NestedArray::getValue(
      $form_state->getUserInput(),
      ['options', 'expose', 'geocoder_plugin_settings']
    );

    if (empty($geocoder_settings)) {
      $geocoder_settings = $this->options['expose']['geocoder_plugin_settings'];
    }
    if (empty($geocoder_settings)) {
      $geocoder_settings = [];
    }

    $geocoder_options = [];
    foreach ($this->geocoderManager->getDefinitions() as $id => $definition) {
      if (empty($definition['frontendCapable'])) {
        continue;
      }
      $geocoder_options[$id] = $definition['name'];
    }

    if ($geocoder_options) {
      $form['expose']['input_by_geocoding_widget'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Use geocoding widget to retrieve boundary values'),
        '#default_value' => $this->options['expose']['input_by_geocoding_widget'],
      ];

      $form['expose']['geocoder_plugin_settings'] = [
        '#type' => 'container',
        '#states' => [
          'visible' => [
            'input[name="options[expose][input_by_geocoding_widget]"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $geocoder_container = &$form['expose']['geocoder_plugin_settings'];

      $geocoder_container['plugin_id'] = [
        '#type' => 'select',
        '#options' => $geocoder_options,
        '#title' => $this->t('Geocoder plugin'),
        '#default_value' => $geocoder_settings['plugin_id'],
        '#ajax' => [
          'callback' => [
            get_class($this->geocoderManager), 'addGeocoderSettingsFormAjax',
          ],
          'wrapper' => 'boundary-geocoder-plugin-settings',
          'effect' => 'fade',
        ],
      ];

      if (!empty($geocoder_settings['plugin_id'])) {
        $geocoder_plugin = $this->geocoderManager
          ->getGeocoder(
            $geocoder_settings['plugin_id'],
            $geocoder_settings['settings']
          );
      }
      elseif (current(array_keys($geocoder_options))) {
        $geocoder_plugin = $this->geocoderManager->getGeocoder(current(array_keys($geocoder_options)));
      }

      if (!empty($geocoder_plugin)) {
        $geocoder_settings_form = $geocoder_plugin->getOptionsForm();
        if ($geocoder_settings_form) {
          $geocoder_container['settings'] = $geocoder_settings_form;
        }
      }

      if (empty($geocoder_container['settings'])) {
        $geocoder_container['settings'] = [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $this->t("No settings available."),
        ];
      }

      $geocoder_container['settings'] = array_replace_recursive($geocoder_container['settings'], [
        '#flatten' => TRUE,
        '#prefix' => '<div id="boundary-geocoder-plugin-settings">',
        '#suffix' => '</div>',
      ]);
    }

    parent::buildExposeForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function buildExposedForm(&$form, FormStateInterface $form_state) {
    parent::buildExposedForm($form, $form_state);

    $identifier = $this->options['expose']['identifier'];
    if (empty($form[$identifier . '_wrapper'][$identifier])) {
      return;
    }
    $form[$identifier . '_wrapper']['#tree'] = FALSE;
    $form[$identifier . '_wrapper'][$identifier]['#tree'] = TRUE;

    if (
      !$this->options['expose']['input_by_geocoding_widget']
      || empty($this->options['expose']['geocoder_plugin_settings'])
    ) {
      return;
    }

    $geocoder_configuration = $this->options['expose']['geocoder_plugin_settings']['settings'];

    /** @var \Drupal\geolocation\GeocoderInterface $geocoder_plugin */
    $geocoder_plugin = $this->geocoderManager->getGeocoder(
      $this->options['expose']['geocoder_plugin_settings']['plugin_id'],
      $geocoder_configuration
    );

    if (empty($geocoder_plugin)) {
      return;
    }

    $form[$identifier . '_wrapper'][$identifier]['lat_north_east']['#type'] = 'hidden';
    $form[$identifier . '_wrapper'][$identifier]['lng_north_east']['#type'] = 'hidden';
    $form[$identifier . '_wrapper'][$identifier]['lat_south_west']['#type'] = 'hidden';
    $form[$identifier . '_wrapper'][$identifier]['lng_south_west']['#type'] = 'hidden';
    $geocoder_plugin->formAttachGeocoder($form[$identifier . '_wrapper'][$identifier], $identifier);

    $form = BubbleableMetadata::mergeAttachments($form, [
      '#attached' => [
        'library' => [
          'geolocation/geolocation.views.filter.geocoder',
        ],
        'drupalSettings' => [
          'geolocation' => [
            'geocoder' => [
              'viewsFilterGeocoder' => [
                $identifier => [
                  'type' => 'boundary',
                ],
              ],
            ],
          ],
        ],
      ],
    ]);

  }

  /**
   * {@inheritdoc}
   */
  public function acceptExposedInput($input): bool {
    if (!parent::acceptExposedInput($input)) {
      return FALSE;
    }

    if ($this->isBoundarySet($this->value)) {
      return TRUE;
    }

    $identifier = $this->options['expose']['identifier'];
    if (
      empty($input[$identifier]['geolocation_geocoder_address'])
      || empty($this->options['expose']['input_by_geocoding_widget'])
      || empty($this->options['expose']['geocoder_plugin_settings']['plugin_id'])
    ) {
      return FALSE;
    }

    $geocoder_configuration = $this->options['expose']['geocoder_plugin_settings']['settings'];
    /** @var \Drupal\geolocation\GeocoderInterface $geocoder_plugin */
    $geocoder_plugin = $this->geocoderManager->getGeocoder(
      $this->options['expose']['geocoder_plugin_settings']['plugin_id'],
      $geocoder_configuration
    );

    if (empty($geocoder_plugin)) {
      return FALSE;
    }

    $location_data = $geocoder_plugin->geocode($input[$this->options['expose']['identifier']]['geolocation_geocoder_address']);

    // Location geocoded server-side. Add to input for later processing.
    if (!empty($location_data['boundary'])) {
      $this->value = array_replace($input[$identifier], $location_data['boundary']);
      return TRUE;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {

    parent::valueForm($form, $form_state);

    $value_element = &$form['value'];

    // Add the Latitude and Longitude elements.
    $value_element += [
      'lat_north_east' => [
        '#type' => 'textfield',
        '#title' => $this->t('North East Boundary - Latitude'),
        '#default_value' => !empty($this->value['lat_north_east']) ? $this->value['lat_north_east'] : '',
        '#weight' => 10,
      ],
      'lng_north_east' => [
        '#type' => 'textfield',
        '#title' => $this->t('North East Boundary - Longitude'),
        '#default_value' => !empty($this->value['lng_north_east']) ? $this->value['lng_north_east'] : '',
        '#weight' => 20,
      ],
      'lat_south_west' => [
        '#type' => 'textfield',
        '#title' => $this->t('South West Boundary - Latitude'),
        '#default_value' => !empty($this->value['lat_south_west']) ? $this->value['lat_south_west'] : '',
        '#weight' => 30,
      ],
      'lng_south_west' => [
        '#type' => 'textfield',
        '#title' => $this->t('South West Boundary - Longitude'),
        '#default_value' => !empty($this->value['lng_south_west']) ? $this->value['lng_south_west'] : '',
        '#weight' => 40,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    if (!($this->query instanceof Sql)) {
      return;
    }

    if (empty($this->value)) {
      return;
    }

    // Get the field alias.
    $lat_north_east = $this->value['lat_north_east'];
    $lng_north_east = $this->value['lng_north_east'];
    $lat_south_west = $this->value['lat_south_west'];
    $lng_south_west = $this->value['lng_south_west'];

    if (
      !is_numeric($lat_north_east)
      || !is_numeric($lng_north_east)
      || !is_numeric($lat_south_west)
      || !is_numeric($lng_south_west)
    ) {
      return;
    }

    $this->query->addWhereExpression(
      $this->options['group'],
      self::getBoundaryQueryFragment($this->ensureMyTable(), $this->realField, $lat_north_east, $lng_north_east, $lat_south_west, $lng_south_west)
    );
  }

  /**
   * Test validity of boundary values.
   *
   * @param mixed $values
   *   Values to test.
   *
   * @return bool
   *   Success.
   */
  private function isBoundarySet($values): bool {
    if (!is_array($values)) {
      return FALSE;
    }

    if (
      isset($values['lat_north_east'])
      && is_numeric($values['lat_north_east'])
      && isset($values['lng_north_east'])
      && is_numeric($values['lng_north_east'])
      && isset($values['lat_south_west'])
      && is_numeric($values['lat_south_west'])
      && isset($values['lng_south_west'])
      && is_numeric($values['lng_south_west'])
    ) {
      return TRUE;
    }

    return FALSE;
  }

}
