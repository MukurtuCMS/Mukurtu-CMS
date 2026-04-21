<?php

namespace Drupal\geofield\Plugin\migrate\process;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\geofield\GeoPHP\GeoPHPInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Maps D7 geofield values to new the geofield values.
 *
 * @MigrateProcessPlugin(
 *   id = "geofield_d7d8"
 * )
 */
class GeoField extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The geoPhpWrapper service.
   *
   * @var \Drupal\geofield\GeoPHP\GeoPHPInterface
   */
  protected $geoPhpWrapper;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, GeoPHPInterface $geo_php) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->geoPhpWrapper = $geo_php;
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
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    return [
      'value' => $this->toWtk($value['geom']),
      'geo_type' => $value['geo_type'],
      'lat' => $value['lat'],
      'lon' => $value['lon'],
      'left' => $value['left'],
      'top' => $value['top'],
      'right' => $value['right'],
      'bottom' => $value['bottom'],
      'geohash' => $value['geohash'],
    ];
  }

  /**
   * Convert geometric data to WTK format.
   *
   * @param string $geom
   *   The geometric data.
   *
   * @return string
   *   The geo data in WKT format.
   */
  protected function toWtk($geom) {
    $geometry = $this->geoPhpWrapper->load($geom);

    if ($geometry instanceof \Geometry) {
      return $geometry->out('wkt');
    }

    return '';
  }

}
