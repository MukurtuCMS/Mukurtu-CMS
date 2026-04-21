<?php

namespace Drupal\geocoder\Plugin\GeofieldProximitySource;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\geocoder\Entity\GeocoderProvider;
use Drupal\geocoder\FormatterPluginManager;
use Drupal\geocoder\Geocoder;
use Drupal\geocoder\ProviderPluginManager;
use Drupal\geofield\Plugin\GeofieldProximitySourceBase;
use Geocoder\Model\AddressCollection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines 'Geocode Origin (with Autocomplete option)' proximity source plugin.
 *
 * @GeofieldProximitySource(
 *   id = "geofield_geocode_origin",
 *   label = @Translation("Geocode Origin (with Autocomplete option)"),
 *   description = @Translation("Geocodes origin from free text input, with Autocomplete option."),
 *   exposedDescription = @Translation("Geocode origin from free text input, with Autocomplete option."),
 *   context = {},
 * )
 */
class GeocodeOrigin extends GeofieldProximitySourceBase implements ContainerFactoryPluginInterface {

  /**
   * The Geocoder Service.
   *
   * @var \Drupal\geocoder\Geocoder
   */
  protected $geocoder;

  /**
   * The Providers Plugin Manager.
   *
   * @var \Drupal\geocoder\ProviderPluginManager
   */
  protected $providerPluginManager;

  /**
   * The Formatter Plugin Manager.
   *
   * @var \Drupal\geocoder\FormatterPluginManager
   */
  protected $formatterPluginManager;

  /**
   * Geocoder Plugins not compatible with Geofield Proximity Geocoding.
   *
   * @var array
   */
  protected $incompatiblePlugins = [
    'file',
    'gpxfile',
    'kmlfile',
    'geojsonfile',
  ];

  /**
   * The origin address to geocode and measure proximity from.
   *
   * @var array
   */
  protected $originAddress;

  /**
   * The flag for Address search autocomplete option.
   *
   * @var bool
   */
  protected $useAutocomplete;

  /**
   * The (minimum) number of terms for the Geocoder to start processing.
   *
   * @var array
   */
  protected $minTerms;

  /**
   * The delay for starting the Geocoder search.
   *
   * @var array
   */
  protected $delay;

  /**
   * Geocoder Control Specific Options.
   *
   * @var array
   */
  protected $options;

  /**
   * The Address Format for autocomplete suggestions.
   *
   * @var array
   */
  protected $addressFormat;

  /**
   * Constructs a GeocodeOrigin object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\geocoder\Geocoder $geocoder
   *   The Geocoder Service.
   * @param \Drupal\geocoder\ProviderPluginManager $providerPluginManager
   *   The Providers Plugin Manager.
   * @param \Drupal\geocoder\FormatterPluginManager $formatterPluginManager
   *   The Providers Plugin Manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Geocoder $geocoder, ProviderPluginManager $providerPluginManager, FormatterPluginManager $formatterPluginManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->originAddress = $configuration['origin_address'] ?? '';
    $this->useAutocomplete = $configuration['use_autocomplete'] ?? 0;
    $this->geocoder = $geocoder;
    $this->providerPluginManager = $providerPluginManager;
    $this->options = $configuration['settings']['options'] ?? '';
    $this->formatterPluginManager = $formatterPluginManager;
    $this->origin = $this->getAddressOrigin($this->originAddress);
    $this->minTerms = $configuration['settings']['autocomplete']['min_terms'] ?? 4;
    $this->delay = $configuration['settings']['autocomplete']['delay'] ?? 800;
    $this->addressFormat = $configuration['settings']['autocomplete']['address_format'] ?? 'default_formatted_address';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('geocoder'),
      $container->get('plugin.manager.geocoder.provider'),
      $container->get('plugin.manager.geocoder.formatter')
    );
  }

  /**
   * Geocode the Origin Address.
   *
   * @param string $address
   *   The String address to Geocode.
   *
   * @return array
   *   The Origin array.
   */
  protected function getAddressOrigin($address) {
    $origin = [
      'lat' => '',
      'lon' => '',
    ];

    if (!empty($address)) {
      // Try static geocoding cache.
      $cache = &drupal_static("geocoder_proximity_cache:$address", NULL);
      if (is_array($cache) && array_key_exists('lat', $cache) && array_key_exists('lon', $cache)) {
        return $cache;
      }

      $provider_plugins = $this->getEnabledProviderPlugins();

      // Try geocoding and extract coordinates of the first match.
      $address_collection = $this->geocoder->geocode($address, GeocoderProvider::loadMultiple(array_keys($provider_plugins)));
      if ($address_collection instanceof AddressCollection && count($address_collection) > 0) {
        $address = $address_collection->get(0);
        $coordinates = $address->getCoordinates();
        $origin = [
          'lat' => $coordinates->getLatitude(),
          'lon' => $coordinates->getLongitude(),
        ];
      }
      $cache = $origin;
    }
    return $origin;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(array &$form, FormStateInterface $form_state, array $options_parents, $is_exposed = FALSE) {

    $form['origin_address'] = [
      '#title' => $this->t('Origin'),
      '#type' => 'textfield',
      '#description' => $this->t('Address, City, Zip-Code, Country, ...'),
      '#default_value' => $this->originAddress,
      '#attributes' => [
        'class' => ['address-input'],
      ],
    ];

    if (!$is_exposed) {
      $form['origin_address']['#title'] = $this->t('Default Origin');
      $form['origin_address']['#description'] = $this->t('Address, City, Zip-Code, Country that would be set as Default Geocoded Address in the Exposed Filter');

      // Attach Geofield Map Library.
      $form['#attached']['library'] = [
        'geocoder/general',
      ];

      $plugins_settings = $this->configuration['plugins'] ?? [];

      // Get the enabled/selected plugins.
      $enabled_plugins = [];
      foreach ($plugins_settings as $plugin_id => $plugin) {
        if (!empty($plugin['checked'])) {
          $enabled_plugins[] = $plugin_id;
        }
      }

      // Generates the Draggable Table of Selectable Geocoder Plugins.
      $form['plugins'] = $this->providerPluginManager->providersPluginsTableList($enabled_plugins);

      // Filter out the Geocoder Plugins that are not compatible with Geofield
      // Proximity Geocoding.
      $form['plugins'] = array_filter($form['plugins'], function ($e) {
        return !in_array($e, $this->incompatiblePlugins);
      }, ARRAY_FILTER_USE_KEY);

      // Set a validation for the plugins selection.
      $form['plugins']['#element_validate'] = [
                                                [
                                                  get_class($this),
                                                  'validatePluginsSettingsForm',
                                                ],
      ];

      $form['use_autocomplete'] = [
        '#type' => 'checkbox',
        '#title' => $this->t("Enable Autocomplete"),
        '#default_value' => $this->useAutocomplete,
        '#description' => $this->t('Check this to activate the Autocomplete
            Geocoding in the Address Origin Input.</br>Note: This will
            increase/double the Quota of Geocoding operations requested to the
            selected Geocoder Providers<br>(requests related to the
            Autocomplete phase plus the ones related to the Exposed Filter
            Submission)'),
        '#states' => [
          'invisible' => [':input[name="options[expose_button][checkbox][checkbox]"]' => ['checked' => FALSE]],
        ],
      ];

      $form['settings'] = [
        '#type' => 'details',
        '#title' => $this->t('Geocoder fine Settings'),
        '#open' => FALSE,
        '#states' => [
          'invisible' => [
            [':input[name="options[source_configuration][use_autocomplete]"]' => ['checked' => FALSE]],
            [':input[name="options[expose_button][checkbox][checkbox]"]' => ['checked' => FALSE]],
          ],
        ],
      ];

      $form['settings']['autocomplete'] = [
        '#type' => 'details',
        '#title' => $this->t('Autocomplete Settings'),
        '#open' => TRUE,
      ];

      $form['settings']['autocomplete']['min_terms'] = [
        '#type' => 'number',
        '#default_value' => $this->minTerms,
        '#title' => $this->t('The (minimum) number of terms for the Geocoder to start processing.'),
        '#description' => $this->t('Valid values ​​for the widget are between 2 and 10. A too low value (<= 3) will affect the application Geocode Quota usage.<br>Try to increase this value if you are experiencing Quota usage matters.'),
        '#min' => 2,
        '#max' => 10,
        '#size' => 3,
      ];

      $form['settings']['autocomplete']['delay'] = [
        '#type' => 'number',
        '#default_value' => $this->delay,
        '#title' => $this->t('The delay (in milliseconds) between pressing a key in the Address Input field and starting the Geocoder search.'),
        '#description' => $this->t('Valid values ​​for the widget are multiples of 100, between 300 and 3000. A too low value (<= 300) will affect / increase the application Geocode Quota usage.<br>Try to increase this value if you are experiencing Quota usage matters.'),
        '#min' => 300,
        '#max' => 3000,
        '#step' => 100,
        '#size' => 4,
      ];

      $form['settings']['autocomplete']['address_format'] = [
        '#title' => $this->t('Address Format'),
        '#type' => 'select',
        '#options' => $this->formatterPluginManager->getPluginsAsOptions(),
        '#description' => $this->t('The address formatter plugin, used for autocomplete suggestions'),
        '#default_value' => $this->addressFormat,
        '#attributes' => [
          'class' => ['address-format'],
        ],
      ];
    }
    elseif ($this->useAutocomplete) {
      $form['#attributes']['class'][] = 'origin-address-autocomplete';
      $form['#attached']['library'] = ['geocoder/geocoder'];
      $form['#attached']['drupalSettings'] = [
        'geocode_origin_autocomplete' => [
          'providers' => array_keys($this->getEnabledProviderPlugins()),
          'minTerms' => $this->minTerms,
          'delay' => $this->delay,
          'address_format' => $this->addressFormat,
        ],
      ];

    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateOptionsForm(array &$form, FormStateInterface $form_state, array $options_parents) {
    $user_input = $form_state->getUserInput();
    if (!empty($user_input['options']['source_configuration']['origin_address'])) {
      $this->origin = $this->getAddressOrigin($user_input['options']['source_configuration']['origin_address']);
    }
  }

  /**
   * Get the list of enabled Provider plugins.
   *
   * @return array
   *   Provider plugin IDs and their properties (id, name, arguments...).
   */
  public function getEnabledProviderPlugins() {
    $geocoder_plugins = $this->providerPluginManager->getPlugins();
    $plugins_settings = $this->configuration['plugins'] ?? [];

    // Filter out unchecked plugins.
    $provider_plugin_ids = array_filter($plugins_settings, function ($plugin) {
      return isset($plugin['checked']) && $plugin['checked'] == TRUE;
    });

    $provider_plugin_ids = array_combine(array_keys($provider_plugin_ids), array_keys($provider_plugin_ids));

    foreach ($geocoder_plugins as $plugin) {
      if (isset($provider_plugin_ids[$plugin['id']])) {
        $provider_plugin_ids[$plugin['id']] = $plugin;
      }
    }

    return $provider_plugin_ids;
  }

  /**
   * {@inheritdoc}
   */
  public static function validatePluginsSettingsForm(array $element, FormStateInterface &$form_state) {
    $plugins = is_array($element['#value']) ? array_filter($element['#value'], function ($value) {
      return isset($value['checked']) && TRUE == $value['checked'];
    }) : [];

    if (empty($plugins)) {
      $form_state->setError($element, t('The Geocode Origin option needs at least one geocoder plugin selected.'));
    }
  }

}
