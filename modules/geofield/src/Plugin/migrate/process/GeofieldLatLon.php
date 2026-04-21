<?php

namespace Drupal\geofield\Plugin\migrate\process;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\geofield\WktGeneratorInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Migrate latitude & longitude single values into a Geofield.
 *
 * The "geofield_latlon" process plugin transforms pairs of
 * latitude & longitude single values into Geofield WKT format value.
 *
 * Example:
 *
 * @code
 *  process:
 *    field_geofield:
 *    plugin: geofield_latlon
 *    source:
 *    - latitude
 *    - longitude
 *
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "geofield_latlon"
 * )
 */
class GeofieldLatLon extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The WktGenerator service.
   *
   * @var \Drupal\geofield\WktGeneratorInterface
   */
  protected $wktGenerator;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, WktGeneratorInterface $wkt_generator) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->wktGenerator = $wkt_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('geofield.wkt_generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $value = array_map('floatval', $value);
    [$lat, $lon] = $value;

    if (empty($lat) && empty($lon)) {
      return NULL;
    }

    return $this->wktGenerator->WktBuildPoint([$lon, $lat]);
  }

}
