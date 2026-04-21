<?php

namespace Drupal\geofield\Plugin\migrate\field;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate_drupal\Plugin\migrate\field\FieldPluginBase;

/**
 * MigrateField Plugin for Drupal 6 and 7 email fields.
 *
 * @MigrateField(
 *   id = "geofield",
 *   core = {7},
 *   type_map = {
 *     "geofield" = "geofield"
 *   },
 *   source_module = "geofield",
 *   destination_module = "geofield"
 * )
 */
class Geofield extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getFieldWidgetMap() {
    return [
      'geofield_wkt' => 'geofield_default',
      'geofield_geojson' => 'geofield_default',
      'geofield_kml' => 'geofield_default',
      'geofield_gpx' => 'geofield_default',
      'geofield_geohash' => 'geofield_default',
      'geofield_latlon' => 'geofield_latlon',
      'geofield_lat' => 'geofield_default',
      'geofield_lon' => 'geofield_default',
      'geofield_geo_type' => 'geofield_default',
      'geofield_def_list' => 'geofield_default',
      'geofield_description' => 'geofield_default',
      'geofield_openlayers' => 'geofield_default',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldFormatterMap() {
    return [
      'geofield_map_map' => 'geofield_default',
      'geofield_wkt' => 'geofield_default',
      'geofield_latlon' => 'geofield_latlon',
      'geofield_geojson' => 'geofield_default',
      'geofield_openlayers' => 'geofield_default',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defineValueProcessPipeline(MigrationInterface $migration, $field_name, $data) {
    $process = [
      'plugin' => 'geofield_d7d8',
      'source' => $field_name,
    ];
    $migration->mergeProcessOfProperty($field_name, $process);
  }

  /**
   * {@inheritdoc}
   */
  public function processFieldValues(MigrationInterface $migration, $field_name, $data) {
    $this->defineValueProcessPipeline($migration, $field_name, $data);
  }

  /**
   * {@inheritdoc}
   */
  public function alterFieldMigration(MigrationInterface $migration) {
    $settings = [
      'geofield' => [
        'plugin' => 'geofield_field_settings',
      ],
    ];
    $migration->mergeProcessOfProperty('settings', $settings);
  }

}
