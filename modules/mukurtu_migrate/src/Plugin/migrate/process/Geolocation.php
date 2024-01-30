<?php

namespace Drupal\mukurtu_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Converts a v3 geolocation value to its v4 geolocation value.
 *
 * @MigrateProcessPlugin(
 *   id = "geolocation",
 *   handle_multiples = TRUE
 * )
 */
class Geolocation extends ProcessPluginBase
{
  /**
   * {@inheritDoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property)
  {
    // v3 only supports Points, so assume each value coming in is a Point.
    $format = '{"type":"FeatureCollection","features":[{"type":"Feature","properties":{},"geometry":{"type":"Point","coordinates":[%s,%s]}}]}';
    $lat = $row->getSourceProperty('field_coverage')[0]['lat'] ?? '';
    $lon = $row->getSourceProperty('field_coverage')[0]['lon'] ?? '';
    $value = sprintf($format, $lon, $lat);
    return $value;
  }
}
