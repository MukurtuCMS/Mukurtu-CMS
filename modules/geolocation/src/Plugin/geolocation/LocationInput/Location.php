<?php

namespace Drupal\geolocation\Plugin\geolocation\LocationInput;

use Drupal\geolocation\LocationInputBase;
use Drupal\geolocation\LocationInputInterface;
use Drupal\geolocation\LocationManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Location based proximity center.
 *
 * @LocationInput(
 *   id = "location_plugins",
 *   name = @Translation("Location Plugins"),
 *   description = @Translation("Select a location plugin."),
 * )
 */
class Location extends LocationInputBase implements LocationInputInterface {

  /**
   * Location manager.
   *
   * @var \Drupal\geolocation\LocationManager
   */
  protected $locationManager;

  /**
   * Location Plugin ID.
   *
   * @var string
   */
  protected $locationPluginId = '';

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LocationManager $location_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->locationManager = $location_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.geolocation.location')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings() {
    $settings = parent::getDefaultSettings();
    $settings['location_settings'] = [
      'settings' => [],
    ];
    $settings['location_plugin_id'] = '';

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm($option_id = NULL, array $settings = [], $context = NULL) {
    $values = explode(':', $option_id);
    if (count($values) !== 2) {
      return [];
    }
    $location_plugin_id = $values[0];
    $location_option_id = $values[1];

    if (!$this->locationManager->hasDefinition($location_plugin_id)) {
      return [];
    }

    /** @var \Drupal\geolocation\LocationInterface $location_plugin */
    $location_plugin = $this->locationManager->createInstance($location_plugin_id);
    $form = parent::getSettingsForm($location_plugin->getPluginId(), $settings, $context);
    // A bit more complicated to use location schema.
    $form['location_settings']['settings'] = $location_plugin->getSettingsForm($location_option_id, $settings['location_settings']['settings'], $context);
    $form['location_plugin_id'] = [
      '#type' => 'value',
      '#value' => $location_plugin_id,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableLocationInputOptions($context) {
    $options = [];

    foreach ($this->locationManager->getDefinitions() as $location_plugin_id => $location_plugin_definition) {
      /** @var \Drupal\geolocation\LocationInterface $location_plugin */
      $location_plugin = $this->locationManager->createInstance($location_plugin_id);
      foreach ($location_plugin->getAvailableLocationOptions($context) as $location_id => $location_label) {
        $options[$location_plugin_id . ':' . $location_id] = $location_label;
      }
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function getCoordinates($form_value, $center_option_id, array $center_option_settings, $context = NULL) {
    $values = explode(':', $center_option_id);
    if (count($values) !== 2) {
      return [];
    }
    $location_plugin_id = $values[0];
    $location_option_id = $values[1];

    if (!$this->locationManager->hasDefinition($location_plugin_id)) {
      return [];
    }

    /** @var \Drupal\geolocation\LocationInterface $location */
    $location = $this->locationManager->createInstance($location_plugin_id);

    $center = $location->getCoordinates($location_option_id, $center_option_settings['location_settings']['settings'], $context);
    if (empty($center)) {
      return FALSE;
    }

    return $center;
  }

  /**
   * {@inheritdoc}
   */
  public function getForm(string $center_option_id, array $center_option_settings, $context = NULL, array $default_value = NULL) {
    return [];
  }

}
