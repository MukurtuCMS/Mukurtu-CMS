<?php

namespace Drupal\geofield\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Process WKT string and return the value for the Drupal Geofield.
 *
 * @MigrateProcessPlugin(
 *   id = "geofield_wkt"
 * )
 *
 * Note: As remarked in issue #3074552
 * this Migrate process plugin doesn't perform any transformation of
 * source values. It just takes the value and returns it.
 * So it is redundant with the Drupal core's get plugin, which just takes the
 * source value as-is and inserts it into the field
 *
 * In other words, these following are equivalent:
 *
 * process:
 *  my_geofield:
 *   plugin: geofield_wkt
 *   source: my_wkt_source
 *
 *  process:
 *   my_geofield: my_wkt_source
 *
 * This is kept as a placeholder, just in case any additional logic needs to
 * be added in the future.
 */
class GeofieldWKT extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    return $value;
  }

}
