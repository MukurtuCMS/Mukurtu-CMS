<?php

namespace Drupal\geocoder\Plugin\Geocoder\Dumper;

use Drupal\geocoder\DumperBase;
use Drupal\geocoder\FormatterPluginManager;
use Geocoder\Location;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an address string geocoder dumper plugin.
 *
 * @GeocoderDumper(
 *   id = "addresstext",
 *   name = "Address string"
 * )
 */
class AddressText extends DumperBase {

  /**
   * The Geocoder formatter plugin manager service.
   *
   * @var \Drupal\geocoder\FormatterPluginManager
   */
  protected $geocoderFormatterPluginManager;

  /**
   * The Geocoder formatter.
   *
   * @var \Drupal\geocoder\Plugin\Geocoder\Formatter\FormatterInterface
   */
  protected $geocoderFormatter;

  /**
   * Constructs an AddressText Dumper.
   *
   * @param array $configuration
   *   The configuration.
   * @param string $plugin_id
   *   The plugin ID for the migration process to do.
   * @param mixed $plugin_definition
   *   The configuration for the plugin.
   * @param \Drupal\geocoder\FormatterPluginManager $geocoder_formatter_plugin_manager
   *   The geocoder formatter plugin manager service.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    FormatterPluginManager $geocoder_formatter_plugin_manager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->geocoderFormatterPluginManager = $geocoder_formatter_plugin_manager;
    $this->geocoderFormatter = $this->geocoderFormatterPluginManager->createInstance('default_formatted_address');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.geocoder.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function dump(Location $location) {
    return $this->geocoderFormatter->format($location);
  }

}
