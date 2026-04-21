<?php

namespace Drupal\geofield\Plugin;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\geofield\GeoPHP\GeoPHPInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a base class for geofield backends.
 *
 * A complete sample plugin definition should be defined as in this example:
 *
 * @code
 * @GeofieldBackend(
 *   id = "geofield_backend_default",
 *   admin_label = @Translation("Default Backend")
 * )
 * @endcode
 *
 * @see \Drupal\geofield\Annotation\GeofieldBackend
 * @see \Drupal\geofield\Plugin\GeofieldBackendPluginInterface
 * @see \Drupal\geofield\Plugin\GeofieldBackendManager
 * @see plugin_api
 */
abstract class GeofieldBackendBase extends PluginBase implements GeofieldBackendPluginInterface, ContainerFactoryPluginInterface {

  /**
   * The geoPhpWrapper service.
   *
   * @var \Drupal\geofield\GeoPHP\GeoPHPInterface
   */
  protected $geoPhpWrapper;

  /**
   * Constructs the GeofieldBackendDefault.
   *
   * @param array $configuration
   *   The configuration.
   * @param string $plugin_id
   *   The plugin ID for the migration process to do.
   * @param mixed $plugin_definition
   *   The configuration for the plugin.
   * @param \Drupal\geofield\GeoPHP\GeoPHPInterface $geophp_wrapper
   *   The geoPhpWrapper.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    GeoPHPInterface $geophp_wrapper,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->geoPhpWrapper = $geophp_wrapper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('geofield.geophp')
    );
  }

}
