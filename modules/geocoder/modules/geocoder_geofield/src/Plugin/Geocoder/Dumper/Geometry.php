<?php

namespace Drupal\geocoder_geofield\Plugin\Geocoder\Dumper;

use Drupal\geocoder\Plugin\Geocoder\Dumper\GeoJson;
use Drupal\geofield\GeoPHP\GeoPHPInterface;
use Geocoder\Location;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Geometry geocoder dumper plugin.
 *
 * @GeocoderDumper(
 *   id = "geometry",
 *   name = "Geometry",
 *   handler = "\Geocoder\Dumper\GeoJson"
 * )
 */
class Geometry extends GeoJson {

  /**
   * Geophp interface.
   *
   * @var \Drupal\geofield\GeoPHP\GeoPHPInterface
   */
  protected $geophp;

  /**
   * Constructs a Drupal\Component\Plugin\PluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\geofield\GeoPHP\GeoPHPInterface $geophp
   *   The geophp service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, GeoPHPInterface $geophp) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->geophp = $geophp;
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

  /**
   * {@inheritdoc}
   */
  public function dump(Location $location) {
    return json_encode($this->geophp->load(parent::dump($location), 'json'));
  }

}
