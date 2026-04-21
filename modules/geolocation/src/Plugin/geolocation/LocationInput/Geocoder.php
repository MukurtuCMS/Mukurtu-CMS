<?php

namespace Drupal\geolocation\Plugin\geolocation\LocationInput;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\geolocation\GeocoderManager;
use Drupal\geolocation\LocationInputBase;
use Drupal\geolocation\LocationInputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Location based proximity center.
 *
 * @LocationInput(
 *   id = "geocoder",
 *   name = @Translation("Geocoder address input"),
 *   description = @Translation("Enter an address and use the geocoded location."),
 * )
 */
class Geocoder extends LocationInputBase implements LocationInputInterface, ContainerFactoryPluginInterface {

  /**
   * Geocoder Manager.
   *
   * @var \Drupal\geolocation\GeocoderManager
   */
  protected $geocoderManager;

  /**
   * Geocoder constructor.
   *
   * @param array $configuration
   *   Configuration.
   * @param string $plugin_id
   *   Plugin ID.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\geolocation\GeocoderManager $geocoder_manager
   *   Geocoder Manager.
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
  public static function getDefaultSettings() {
    $settings = parent::getDefaultSettings();

    $settings['auto_submit'] = FALSE;
    $settings['hide_form'] = FALSE;
    $settings['plugin_id'] = '';
    $settings['settings'] = [];

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm($option_id = NULL, array $settings = [], $context = NULL) {
    $form = [];

    $settings = $this->getSettings($settings);

    $geocoder_options = [];
    foreach ($this->geocoderManager->getDefinitions() as $geocoder_id => $geocoder_definition) {
      if (empty($geocoder_definition['locationCapable'])) {
        continue;
      }
      $geocoder_options[$geocoder_id] = $geocoder_definition['name'];
    }

    if ($geocoder_options) {

      $form['plugin_id'] = [
        '#type' => 'select',
        '#options' => $geocoder_options,
        '#title' => $this->t('Geocoder plugin'),
        '#default_value' => $settings['plugin_id'],
        '#ajax' => [
          'callback' => [
            get_class($this->geocoderManager), 'addGeocoderSettingsFormAjax',
          ],
          'wrapper' => 'location-input-geocoder-plugin-settings',
          'effect' => 'fade',
        ],
      ];

      if (!empty($settings['plugin_id'])) {
        $geocoder_plugin = $this->geocoderManager->getGeocoder(
          $settings['plugin_id'],
          $settings['settings']
        );
      }
      elseif (current(array_keys($geocoder_options))) {
        $geocoder_plugin = $this->geocoderManager->getGeocoder(current(array_keys($geocoder_options)));
      }

      if (!empty($geocoder_plugin)) {
        $geocoder_settings_form = $geocoder_plugin->getOptionsForm();
        if ($geocoder_settings_form) {
          $form['settings'] = $geocoder_settings_form;
        }
      }

      if (empty($form['settings'])) {
        $form['settings'] = [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $this->t("No settings available."),
        ];
      }

      $form['settings'] = array_replace_recursive($form['settings'], [
        '#flatten' => TRUE,
        '#prefix' => '<div id="location-input-geocoder-plugin-settings">',
        '#suffix' => '</div>',
      ]);

      $form['auto_submit'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Auto-submit form'),
        '#default_value' => $settings['auto_submit'],
        '#description' => $this->t('Only triggers if location could be set'),
      ];

      $form['hide_form'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Hide coordinates form'),
        '#default_value' => $settings['hide_form'],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getCoordinates($form_value, $option_id, array $option_settings, $context = NULL) {
    $coordinates = parent::getCoordinates($form_value, $option_id, $option_settings, $context);
    if ($coordinates) {
      return $coordinates;
    }

    if (empty($form_value['geocoder'])) {
      return [];
    }

    $settings = $this->getSettings($option_settings);
    $location_data = $this->geocoderManager
      ->getGeocoder($settings['plugin_id'], $settings['settings'])
      ->geocode($form_value['geocoder']['geolocation_geocoder_address'] ?? '');

    if (!empty($location_data['location'])) {
      return $location_data['location'];
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getForm(string $center_option_id, array $center_option_settings, $context = NULL, array $default_value = NULL) {
    $form = parent::getForm($center_option_id, $center_option_settings, $context, $default_value);

    if (empty($form['coordinates'])) {
      return $form;
    }

    $center_option_settings = $this->getSettings($center_option_settings);

    $identifier = uniqid($center_option_id);

    $form['coordinates']['#attributes'] = [
      'class' => [
        $identifier,
        'location-input-geocoder',
      ],
    ];

    $form['geocoder'] = [
      '#type' => 'container',
      '#attached' => [
        'library' => [
          'geolocation/location_input.geocoder',
        ],
        'drupalSettings' => [
          'geolocation' => [
            'locationInput' => [
              'geocoder' => [
                [
                  'identifier' => $identifier,
                  'autoSubmit' => $center_option_settings['auto_submit'],
                  'hideForm' => $center_option_settings['hide_form'],
                ],
              ],
            ],
          ],
        ],
      ],
    ];

    /** @var \Drupal\geolocation\GeocoderInterface $geocoder_plugin */
    $geocoder_plugin = $this->geocoderManager->getGeocoder(
      $center_option_settings['plugin_id'],
      $center_option_settings['settings']
    );

    $geocoder_plugin->formAttachGeocoder($form['geocoder'], $identifier);

    return $form;
  }

}
