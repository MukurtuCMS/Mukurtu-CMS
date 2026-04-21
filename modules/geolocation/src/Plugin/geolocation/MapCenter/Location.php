<?php

namespace Drupal\geolocation\Plugin\geolocation\MapCenter;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\geolocation\LocationManager;
use Drupal\geolocation\MapCenterBase;
use Drupal\geolocation\MapCenterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Location based map center.
 *
 * @MapCenter(
 *   id = "location_plugins",
 *   name = @Translation("Location Plugins"),
 *   description = @Translation("Select a location plugin."),
 * )
 */
class Location extends MapCenterBase implements MapCenterInterface {

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
  public function getSettingsForm($location_plugin_id = NULL, array $settings = [], $context = NULL) {
    if (!$this->locationManager->hasDefinition($location_plugin_id)) {
      return [];
    }

    $form = [];

    /** @var \Drupal\geolocation\LocationInterface $location_plugin */
    $location_plugin = $this->locationManager->createInstance($location_plugin_id);
    $location_options = $location_plugin->getAvailableLocationOptions($context);

    if (!$location_options) {
      return [];
    }

    $option_id = NULL;

    if (!empty($settings['location_option_id'])) {
      $option_id = $settings['location_option_id'];
    }

    if (count($location_options) == 1) {
      $option_id = key($location_options);
      $form['location_option_id'] = [
        '#type' => 'value',
        '#value' => $option_id,
      ];
    }
    else {
      $options = [];
      foreach ($location_options as $location_option_id => $location_option_definition) {
        $options[$location_option_id] = $location_option_definition['name'];
      }
      $form['location_option_id'] = [
        '#type' => 'select',
        '#options' => $options,
        '#default_value' => $option_id,
      ];
    }

    /** @var \Drupal\geolocation\LocationInterface $location_plugin */
    $location_plugin = $this->locationManager->createInstance($location_plugin_id);
    $form = array_merge_recursive($form, $location_plugin->getSettingsForm($option_id, $settings, $context));

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableMapCenterOptions($context = NULL) {
    $options = [];

    /** @var \Drupal\geolocation\LocationInterface $location_plugin */
    foreach ($this->locationManager->getDefinitions() as $location_plugin_id => $location_plugin_definition) {
      /** @var \Drupal\geolocation\LocationInterface $location_plugin */
      $location_plugin = $this->locationManager->createInstance($location_plugin_id);
      $location_options = $location_plugin->getAvailableLocationOptions($context);

      if (!$location_options) {
        continue;
      }
      $options[$location_plugin_id] = $location_plugin_definition['name'];
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function alterMap(array $map, $center_plugin_id, array $center_option_settings, $context = NULL) {
    if (!$this->locationManager->hasDefinition($center_plugin_id)) {
      return $map;
    }

    /** @var \Drupal\geolocation\LocationInterface $location */
    $location = $this->locationManager->createInstance($center_plugin_id);

    if (!empty($center_option_settings['location_option_id'])) {
      $location_id = $center_option_settings['location_option_id'];
    }
    else {
      $location_id = $center_plugin_id;
    }

    $map['#attached']['drupalSettings']['geolocation']['maps'][$map['#id']]['map_center']['location_plugins_' . $location_id] = $map['#attached']['drupalSettings']['geolocation']['maps'][$map['#id']]['map_center']['location_plugins'];
    unset($map['#attached']['drupalSettings']['geolocation']['maps'][$map['#id']]['map_center']['location_plugins']);

    $map_center = $location->getCoordinates($location_id, $center_option_settings, $context);
    if (!empty($map_center)) {
      $map['#centre'] = $map_center;
    }
    $map['#attached'] = BubbleableMetadata::mergeAttachments($map['#attached'], [
      'library' => [
        'geolocation/map_center.static_location',
      ],
      'drupalSettings' => [
        'geolocation' => [
          'maps' => [
            $map['#id'] => [
              'map_center' => [
                'location_plugins_' . $location_id => [
                  'success' => !empty($map_center),
                ],
              ],
            ],
          ],
        ],
      ],
    ]);

    return $map;
  }

}
