<?php

namespace Drupal\geocoder;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Geocoder\Location;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a base class for geocoder dumper plugins.
 */
abstract class DumperBase extends PluginBase implements DumperInterface, ContainerFactoryPluginInterface {

  /**
   * The geocoder dumper handler.
   *
   * @var \Geocoder\Dumper\Dumper
   */
  protected $handler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function dump(Location $location) {
    return $this->getHandler()->dump($location);
  }

  /**
   * Returns the dumper handler.
   *
   * @return \Geocoder\Dumper\Dumper
   *   Returns dumper handler.
   */
  protected function getHandler() {
    if ($this->handler === NULL) {
      $definition = $this->getPluginDefinition();
      $class = $definition['handler'];
      $this->handler = new $class();
    }

    return $this->handler;
  }

}
