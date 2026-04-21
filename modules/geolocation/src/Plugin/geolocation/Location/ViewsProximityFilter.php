<?php

namespace Drupal\geolocation\Plugin\geolocation\Location;

use Drupal\geolocation\LocationBase;
use Drupal\geolocation\LocationInputManager;
use Drupal\geolocation\LocationInterface;
use Drupal\geolocation\ViewsContextTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Derive center from proximity filter.
 *
 * @Location(
 *   id = "views_proximity_filter",
 *   name = @Translation("Proximity filter"),
 *   description = @Translation("Set map center from proximity filter."),
 * )
 */
class ViewsProximityFilter extends LocationBase implements LocationInterface {

  use ViewsContextTrait;

  /**
   * Proximity center manager.
   *
   * @var \Drupal\geolocation\LocationInputManager
   */
  protected $locationInputManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LocationInputManager $location_input_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->locationInputManager = $location_input_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.geolocation.locationinput')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableLocationOptions($context): array {
    $options = [];

    if ($displayHandler = self::getViewsDisplayHandler($context)) {
      /** @var \Drupal\views\Plugin\views\filter\FilterPluginBase $filter */
      foreach ($displayHandler->getHandlers('filter') as $delta => $filter) {
        if (
          $filter->getPluginId() === 'geolocation_filter_proximity'
          && $filter !== $context
        ) {
          $options[$delta] = $this->t('Proximity filter') . ' - ' . $filter->adminLabel();
        }
      }
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function getCoordinates($location_option_id, array $location_option_settings, $context = NULL): array {
    if (!($displayHandler = self::getViewsDisplayHandler($context))) {
      return parent::getCoordinates($location_option_id, $location_option_settings, $context);
    }

    $filter = $displayHandler->getHandler('filter', $location_option_id);
    if (empty($filter)) {
      return parent::getCoordinates($location_option_id, $location_option_settings, $context);
    }

    if (
      array_key_exists('lat', $filter->value)
      && array_key_exists('lng', $filter->value)
    ) {
      return [
        'lat' => (float) $filter->value['lat'],
        'lng' => (float) $filter->value['lng'],
      ];
    }

    return $this->locationInputManager->getCoordinates((array) $filter->value['center'], $filter->options['location_input'], $filter);
  }

}
