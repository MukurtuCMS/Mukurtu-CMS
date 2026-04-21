<?php

namespace Drupal\geofield\Plugin\GeofieldProximitySource;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\geofield\Plugin\GeofieldProximitySourceBase;
use Drupal\geofield\Plugin\GeofieldProximitySourceManager;
use Drupal\geofield\Plugin\views\argument\GeofieldProximityArgument;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines 'Geofield Context Filter' plugin.
 *
 * @package Drupal\geofield\Plugin
 *
 * @GeofieldProximitySource(
 *   id = "geofield_context_filter",
 *   label = @Translation("Context Filter (By context filter)"),
 *   description = @Translation("Allow the contextual input of Origin as couple of Latitude and Longitude in decimal degrees."),
 *   exposedDescription = @Translation("Contextual input of Origin as couple of Latitude and Longitude in decimal degrees."),
 *   context = {
 *     "sort",
 *     "field",
 *   },
 * )
 */
class ContextProximityFilter extends GeofieldProximitySourceBase implements ContainerFactoryPluginInterface {

  /**
   * The geofield proximity manager.
   *
   * @var \Drupal\geofield\Plugin\GeofieldProximitySourceManager
   */
  protected $proximitySourceManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.geofield_proximity_source')
    );
  }

  /**
   * Constructs a GeocodeOrigin object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\geofield\Plugin\GeofieldProximitySourceManager $proximitySourceManager
   *   The Geofield Proximity Source manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, GeofieldProximitySourceManager $proximitySourceManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->proximitySourceManager = $proximitySourceManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getOrigin() {
    $origin = [];
    if (isset($this->viewHandler)) {
      foreach ($this->viewHandler->view->argument as $argument) {
        if ($argument instanceof GeofieldProximityArgument && $argument_values = $argument->getParsedReferenceLocation()) {
          $origin = [
            'lat' => $argument_values['lat'],
            'lon' => $argument_values['lon'],
          ];
        }
      }
    }
    return $origin;
  }

}
